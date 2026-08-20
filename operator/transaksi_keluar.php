<?php
require_once __DIR__ . '/../config/koneksi.php';
cekLogin(['petugas']);
$page_title = 'Kendaraan Keluar';
$error = '';

// Proses kendaraan keluar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_parkir'])) {
    $id_parkir = $_POST['id_parkir'];
    $metode_bayar = in_array($_POST['metode_bayar'] ?? '', ['tunai', 'qris']) ? $_POST['metode_bayar'] : 'tunai';

    $stmt = $koneksi->prepare("
        SELECT t.*, tf.tarif_per_jam, tf.denda_per_jam,
               bk.tanggal_booking AS booking_tanggal, bk.jam_booking_keluar AS booking_jam_keluar
        FROM tb_transaksi t
        JOIN tb_tarif tf ON tf.id_tarif = t.id_tarif
        LEFT JOIN tb_booking bk ON bk.id_booking = t.id_booking
        WHERE t.id_parkir = ?
    ");
    $stmt->execute([$id_parkir]);
    $trx = $stmt->fetch();

    if ($trx && $trx['status'] === 'masuk') {
        $waktu_masuk = new DateTime($trx['waktu_masuk']);
        $waktu_keluar = new DateTime();
        $selisih = $waktu_masuk->diff($waktu_keluar);
        $jam = ($selisih->days * 24) + $selisih->h + ($selisih->i > 0 || $selisih->s > 0 ? 1 : 0);
        $jam = max(1, $jam); // minimal 1 jam
        $biayaParkir = $jam * $trx['tarif_per_jam'];

        // ==== HITUNG DENDA TELAT KELUAR (dibandingkan jam_booking_keluar jika kendaraan berasal dari booking) ====
        $denda_telat_keluar = 0;
        if (!empty($trx['booking_jam_keluar'])) {
            $jadwalKeluar = $trx['booking_tanggal'] . ' ' . $trx['booking_jam_keluar'];
            $menitTelatKeluar = hitungMenitTelat($jadwalKeluar, date('Y-m-d H:i:s'));
            $denda_telat_keluar = hitungDenda($menitTelatKeluar, (float) $trx['denda_per_jam']);
        }

        $denda_telat_masuk = (float) $trx['denda_telat_masuk'];
        $biaya = $biayaParkir + $denda_telat_masuk + $denda_telat_keluar;

        $koneksi->beginTransaction();
        try {
            $upd = $koneksi->prepare("UPDATE tb_transaksi SET waktu_keluar = NOW(), durasi_jam = ?, biaya_total = ?, denda_telat_keluar = ?, status = 'keluar', metode_bayar = ? WHERE id_parkir = ?");
            $upd->execute([$jam, $biaya, $denda_telat_keluar, $metode_bayar, $id_parkir]);

            $updArea = $koneksi->prepare("UPDATE tb_area_parkir SET terisi = GREATEST(terisi - 1, 0) WHERE id_area = ?");
            $updArea->execute([$trx['id_area']]);

            $ketDenda = '';
            if ($denda_telat_masuk > 0) $ketDenda .= " + denda telat masuk " . rupiah($denda_telat_masuk);
            if ($denda_telat_keluar > 0) $ketDenda .= " + denda telat keluar " . rupiah($denda_telat_keluar);
            catatLog($koneksi, $_SESSION['id_user'], "Memproses kendaraan keluar transaksi #$id_parkir (bayar: " . strtoupper($metode_bayar) . ")" . $ketDenda);

            $koneksi->commit();

            // Notifikasi + suara khusus pembayaran, akan tampil sekali di halaman struk
            $_SESSION['notif_pembayaran'] = [
                'pesan' => 'Pembayaran berhasil (' . strtoupper($metode_bayar) . ') &mdash; Total: ' . rupiah($biaya),
                'tipe'  => 'success'
            ];

            header("Location: cetak_struk.php?id=$id_parkir&mode=keluar");
            exit;
        } catch (Exception $e) {
            $koneksi->rollBack();
            $error = 'Gagal memproses kendaraan keluar: ' . $e->getMessage();
        }
    } else {
        $error = 'Transaksi tidak ditemukan atau kendaraan sudah keluar.';
    }
}

$sedangParkir = $koneksi->query("
    SELECT t.*, k.plat_nomor, k.jenis_kendaraan, k.pemilik, a.nama_area, tf.tarif_per_jam, tf.denda_per_jam,
           bk.tanggal_booking AS booking_tanggal, bk.jam_booking_keluar AS booking_jam_keluar
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON k.id_kendaraan = t.id_kendaraan
    JOIN tb_area_parkir a ON a.id_area = t.id_area
    JOIN tb_tarif tf ON tf.id_tarif = t.id_tarif
    LEFT JOIN tb_booking bk ON bk.id_booking = t.id_booking
    WHERE t.status = 'masuk'
    ORDER BY t.waktu_masuk ASC
")->fetchAll();

include __DIR__ . '/components/header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card card-hanma">
    <div class="card-header"><i class="bi bi-box-arrow-right me-1"></i> Kendaraan yang Sedang Parkir</div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Plat Nomor</th><th>Jenis</th><th>Pemilik</th><th>Area</th><th>Waktu Masuk</th><th>Tarif/Jam</th><th>Denda</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($sedangParkir as $s): ?>
                <?php
                    $dendaMasukRow = (float) $s['denda_telat_masuk'];
                    $dendaKeluarEstRow = 0;
                    if (!empty($s['booking_jam_keluar'])) {
                        $menitTelatRow = hitungMenitTelat($s['booking_tanggal'] . ' ' . $s['booking_jam_keluar'], date('Y-m-d H:i:s'));
                        $dendaKeluarEstRow = hitungDenda($menitTelatRow, (float) $s['denda_per_jam']);
                    }
                ?>
                <tr <?= (isset($_GET['id']) && $_GET['id'] == $s['id_parkir']) ? 'class="table-warning"' : '' ?>>
                    <td class="fw-semibold"><?= htmlspecialchars($s['plat_nomor']) ?></td>
                    <td class="text-capitalize"><?= $s['jenis_kendaraan'] ?></td>
                    <td><?= htmlspecialchars($s['pemilik']) ?></td>
                    <td><?= htmlspecialchars($s['nama_area']) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($s['waktu_masuk'])) ?></td>
                    <td><?= rupiah($s['tarif_per_jam']) ?></td>
                    <td>
                        <?php if ($dendaMasukRow > 0): ?>
                            <span class="badge bg-danger d-block mb-1">Telat masuk <?= rupiah($dendaMasukRow) ?></span>
                        <?php endif; ?>
                        <?php if ($dendaKeluarEstRow > 0): ?>
                            <span class="badge bg-warning text-dark d-block">Est. telat keluar <?= rupiah($dendaKeluarEstRow) ?></span>
                        <?php elseif ($dendaMasukRow == 0): ?>
                            <span class="text-muted small">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-hanma" data-bs-toggle="modal" data-bs-target="#modalBayar<?= $s['id_parkir'] ?>">
                            <i class="bi bi-flag me-1"></i>Proses Keluar
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($sedangParkir)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada kendaraan yang sedang parkir</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- Modal pilih metode pembayaran (per kendaraan) -->
<?php foreach ($sedangParkir as $s): ?>
<?php
    $jamEst = max(1, (new DateTime($s['waktu_masuk']))->diff(new DateTime())->days * 24 + (new DateTime($s['waktu_masuk']))->diff(new DateTime())->h + 1);
    $biayaParkirEst = $jamEst * $s['tarif_per_jam'];

    $dendaMasuk = (float) $s['denda_telat_masuk'];

    $dendaKeluarEst = 0;
    $menitTelatKeluarEst = 0;
    if (!empty($s['booking_jam_keluar'])) {
        $menitTelatKeluarEst = hitungMenitTelat($s['booking_tanggal'] . ' ' . $s['booking_jam_keluar'], date('Y-m-d H:i:s'));
        $dendaKeluarEst = hitungDenda($menitTelatKeluarEst, (float) $s['denda_per_jam']);
    }

    $totalEst = $biayaParkirEst + $dendaMasuk + $dendaKeluarEst;
?>
<div class="modal fade" id="modalBayar<?= $s['id_parkir'] ?>" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="id_parkir" value="<?= $s['id_parkir'] ?>">
            <div class="modal-header">
                <h5 class="modal-title">Pembayaran &middot; <?= htmlspecialchars($s['plat_nomor']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">Estimasi Durasi: <strong><?= $jamEst ?> jam</strong></p>
                <p class="mb-1">Biaya Parkir: <strong><?= rupiah($biayaParkirEst) ?></strong></p>

                <?php if ($dendaMasuk > 0): ?>
                    <p class="mb-1 text-danger">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        Denda telat datang: <strong><?= rupiah($dendaMasuk) ?></strong>
                    </p>
                <?php endif; ?>

                <?php if (!empty($s['booking_jam_keluar'])): ?>
                    <?php if ($dendaKeluarEst > 0): ?>
                        <p class="mb-1 text-danger">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            Sudah lewat <?= $menitTelatKeluarEst ?> menit dari jadwal keluar (<?= date('H:i', strtotime($s['booking_jam_keluar'])) ?>) &mdash;
                            estimasi denda telat keluar: <strong><?= rupiah($dendaKeluarEst) ?></strong>
                        </p>
                    <?php else: ?>
                        <p class="mb-1 small text-muted">Jadwal keluar booking: <?= date('H:i', strtotime($s['booking_jam_keluar'])) ?> (belum/tidak telat)</p>
                    <?php endif; ?>
                <?php endif; ?>

                <p class="mb-3">Estimasi Total: <strong><?= rupiah($totalEst) ?></strong> <span class="small text-muted">(dihitung ulang saat diproses)</span></p>

                <label class="form-label">Metode Pembayaran</label>
                <div class="btn-group w-100 mb-3" role="group">
                    <input type="radio" class="btn-check" name="metode_bayar" id="tunai<?= $s['id_parkir'] ?>" value="tunai" checked
                           onchange="document.getElementById('qrisBox<?= $s['id_parkir'] ?>').classList.add('d-none')">
                    <label class="btn btn-outline-hanma" for="tunai<?= $s['id_parkir'] ?>"><i class="bi bi-cash-stack me-1"></i>Tunai</label>

                    <input type="radio" class="btn-check" name="metode_bayar" id="qris<?= $s['id_parkir'] ?>" value="qris"
                           onchange="document.getElementById('qrisBox<?= $s['id_parkir'] ?>').classList.remove('d-none')">
                    <label class="btn btn-outline-hanma" for="qris<?= $s['id_parkir'] ?>"><i class="bi bi-qr-code me-1"></i>QRIS</label>
                </div>

                <div id="qrisBox<?= $s['id_parkir'] ?>" class="text-center d-none">
                    <img src="<?= BASE_URL ?>img/qris.jpeg" alt="QRIS Hanma Fitness" class="img-fluid border rounded p-2" style="max-width:220px;">
                    <p class="small text-muted mt-2 mb-0">Minta pelanggan scan QRIS di atas menggunakan e-wallet / m-banking, lalu tekan tombol di bawah setelah pembayaran berhasil.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-hanma" onclick="return confirm('Proses kendaraan <?= htmlspecialchars($s['plat_nomor']) ?> keluar sekarang?')">
                    <i class="bi bi-check2-circle me-1"></i>Konfirmasi &amp; Proses Keluar
                </button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<?php include __DIR__ . '/components/footer.php'; ?>