<?php
require_once __DIR__ . '/../config/koneksi.php';
cekLogin(['petugas']);
$page_title = 'Kendaraan Masuk';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plat = strtoupper(trim($_POST['plat_nomor']));
    $jenis = $_POST['jenis_kendaraan'] ?? '';
    $warna = trim($_POST['warna']);
    $pemilik = trim($_POST['pemilik']);
    $id_area = $_POST['id_area'];
    $id_tarif = $_POST['id_tarif'];
    $id_booking = $_POST['id_booking'] ?? '';

    // Cek kapasitas area
    $stmtArea = $koneksi->prepare("SELECT * FROM tb_area_parkir WHERE id_area = ?");
    $stmtArea->execute([$id_area]);
    $area = $stmtArea->fetch();

    if (!$area || $area['terisi'] >= $area['kapasitas']) {
        $error = 'Area parkir yang dipilih sudah penuh. Silakan pilih area lain.';
    } else {
        $koneksi->beginTransaction();
        try {
            $denda_telat_masuk = 0;

            if ($id_booking !== '') {
                // ==== DARI BOOKING: pakai id_kendaraan yang sudah ada, jangan insert baru ====
                $stmtBooking = $koneksi->prepare(
                    "SELECT b.id_kendaraan, b.tanggal_booking, b.jam_booking_masuk FROM tb_booking b WHERE b.id_booking = ? AND b.status = 'dikonfirmasi'"
                );
                $stmtBooking->execute([$id_booking]);
                $booking = $stmtBooking->fetch();

                if (!$booking) {
                    throw new Exception('Booking tidak ditemukan atau sudah diproses.');
                }
                $id_kendaraan = $booking['id_kendaraan'];

                // ==== HITUNG DENDA TELAT DATANG (server-side, tidak bisa dimanipulasi dari form) ====
                $jadwalMasuk = $booking['tanggal_booking'] . ' ' . $booking['jam_booking_masuk'];
                $menitTelatMasuk = hitungMenitTelat($jadwalMasuk, date('Y-m-d H:i:s'));

                $stmtTarifDenda = $koneksi->prepare("SELECT denda_per_jam FROM tb_tarif WHERE id_tarif = ?");
                $stmtTarifDenda->execute([$id_tarif]);
                $dendaPerJam = (float) ($stmtTarifDenda->fetch()['denda_per_jam'] ?? 0);

                $denda_telat_masuk = hitungDenda($menitTelatMasuk, $dendaPerJam);
            } else {
                // ==== WALK-IN: cek dulu apakah plat nomor sudah pernah terdaftar ====
                $cekPlat = $koneksi->prepare("SELECT id_kendaraan FROM tb_kendaraan WHERE plat_nomor = ?");
                $cekPlat->execute([$plat]);
                $existing = $cekPlat->fetch();

                if ($existing) {
                    $id_kendaraan = $existing['id_kendaraan'];
                } else {
                    $stmtK = $koneksi->prepare("INSERT INTO tb_kendaraan (plat_nomor, jenis_kendaraan, warna, pemilik, id_user) VALUES (?, ?, ?, ?, ?)");
                    $stmtK->execute([$plat, $jenis, $warna, $pemilik, $_SESSION['id_user']]);
                    $id_kendaraan = $koneksi->lastInsertId();
                }
            }

            // Simpan transaksi
            $stmtT = $koneksi->prepare("INSERT INTO tb_transaksi (id_kendaraan, id_booking, waktu_masuk, id_tarif, denda_telat_masuk, status, id_user, id_area) VALUES (?, ?, NOW(), ?, ?, 'masuk', ?, ?)");
            $stmtT->execute([$id_kendaraan, $id_booking !== '' ? $id_booking : null, $id_tarif, $denda_telat_masuk, $_SESSION['id_user'], $id_area]);
            $id_parkir = $koneksi->lastInsertId();

            // Update area terisi
            $stmtUp = $koneksi->prepare("UPDATE tb_area_parkir SET terisi = terisi + 1 WHERE id_area = ?");
            $stmtUp->execute([$id_area]);

            // Kalau berasal dari booking, tandai booking selesai
            if ($id_booking !== '') {
                $stmtSelesai = $koneksi->prepare("UPDATE tb_booking SET status = 'selesai' WHERE id_booking = ?");
                $stmtSelesai->execute([$id_booking]);
            }

            catatLog($koneksi, $_SESSION['id_user'], "Mencatat kendaraan masuk: $plat" . ($id_booking !== '' ? " (dari booking #$id_booking)" : '') . ($denda_telat_masuk > 0 ? " - kena denda telat masuk " . rupiah($denda_telat_masuk) : ''));

            $koneksi->commit();
            header("Location: cetak_struk.php?id=$id_parkir&mode=masuk");
            exit;
        } catch (Exception $e) {
            $koneksi->rollBack();
            $error = 'Gagal menyimpan transaksi: ' . $e->getMessage();
        }
    }
}

$areas = $koneksi->query("SELECT * FROM tb_area_parkir WHERE terisi < kapasitas ORDER BY nama_area")->fetchAll();
$tarifs = $koneksi->query("SELECT * FROM tb_tarif ORDER BY jenis_kendaraan")->fetchAll();

// ==== BOOKING TERKONFIRMASI (siap diproses masuk) ====
$bookingSiap = $koneksi->query("
    SELECT b.id_booking, b.tanggal_booking, b.jam_booking_masuk, b.jam_booking_keluar, b.catatan, b.id_area,
           k.id_kendaraan, k.plat_nomor, k.jenis_kendaraan, k.warna, k.pemilik,
           u.nama_lengkap, a.nama_area
    FROM tb_booking b
    JOIN tb_kendaraan k ON k.id_kendaraan = b.id_kendaraan
    JOIN tb_user u ON u.id_user = b.id_user
    LEFT JOIN tb_area_parkir a ON a.id_area = b.id_area
    WHERE b.status = 'dikonfirmasi'
    ORDER BY b.tanggal_booking ASC, b.jam_booking_masuk ASC
")->fetchAll();

// peta denda per jenis kendaraan (huruf kecil) untuk estimasi tampilan
$petaDenda = [];
foreach ($tarifs as $t) { $petaDenda[strtolower($t['jenis_kendaraan'])] = (float) $t['denda_per_jam']; }

include __DIR__ . '/components/header.php';
?>

<div class="row justify-content-center g-3">

    <?php if (count($bookingSiap) > 0): ?>
    <div class="col-lg-9">
        <div class="card card-hanma border-primary">
            <div class="card-header bg-primary-subtle d-flex align-items-center justify-content-between">
                <span><i class="bi bi-calendar-check text-primary"></i> Booking Terkonfirmasi — Siap Diproses Masuk</span>
                <span class="badge bg-primary"><?= count($bookingSiap) ?> booking</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Kendaraan</th>
                            <th>Area</th>
                            <th>Tanggal</th>
                            <th>Jam Masuk</th>
                            <th>Jam Keluar</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($bookingSiap as $b): ?>
                        <?php
                            $menitTelat = hitungMenitTelat($b['tanggal_booking'] . ' ' . $b['jam_booking_masuk'], date('Y-m-d H:i:s'));
                            $dendaEstimasi = hitungDenda($menitTelat, $petaDenda[strtolower($b['jenis_kendaraan'])] ?? 0);
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($b['nama_lengkap']) ?></td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($b['plat_nomor']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($b['jenis_kendaraan']) ?><?= $b['warna'] ? ' - ' . htmlspecialchars($b['warna']) : '' ?></small>
                            </td>
                            <td>
                                <?php if ($b['nama_area']): ?>
                                    <span class="badge bg-info-subtle text-info-emphasis"><?= htmlspecialchars($b['nama_area']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d M Y', strtotime($b['tanggal_booking'])) ?></td>
                            <td><?= date('H:i', strtotime($b['jam_booking_masuk'])) ?></td>
                            <td><?= $b['jam_booking_keluar'] ? date('H:i', strtotime($b['jam_booking_keluar'])) : '<span class="text-muted small">-</span>' ?></td>
                            <td>
                                <?php if ($dendaEstimasi > 0): ?>
                                    <span class="badge bg-danger">Telat <?= $menitTelat ?> menit</span><br>
                                    <small class="text-danger">Denda est. <?= rupiah($dendaEstimasi) ?></small>
                                <?php else: ?>
                                    <span class="badge bg-success">Tepat waktu</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-hanma btn-proses-booking"
                                        data-id-booking="<?= $b['id_booking'] ?>"
                                        data-plat="<?= htmlspecialchars($b['plat_nomor']) ?>"
                                        data-jenis="<?= htmlspecialchars($b['jenis_kendaraan']) ?>"
                                        data-warna="<?= htmlspecialchars($b['warna'] ?? '') ?>"
                                        data-pemilik="<?= htmlspecialchars($b['pemilik']) ?>"
                                        data-id-area="<?= $b['id_area'] ?? '' ?>"
                                        data-denda="<?= $dendaEstimasi ?>">
                                    <i class="bi bi-arrow-down-circle"></i> Proses
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="col-lg-7">
        <div class="card card-hanma">
            <div class="card-header"><i class="bi bi-box-arrow-in-right me-1"></i> Form Kendaraan Masuk</div>
            <div class="card-body">
                <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

                <div id="infoBooking" class="alert alert-primary py-2 d-none">
                    <i class="bi bi-info-circle"></i> Data diisi dari booking member.
                    <span id="infoDendaMasuk"></span>
                    <button type="button" id="btnBatalBooking" class="btn btn-sm btn-outline-primary float-end">Batal, input manual</button>
                </div>

                <form method="POST">
                    <input type="hidden" name="id_booking" id="id_booking" value="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Plat Nomor</label>
                            <input type="text" name="plat_nomor" id="plat_nomor" class="form-control text-uppercase" placeholder="Contoh: AB 1234 CD" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jenis Kendaraan</label>
                            <select name="jenis_kendaraan" id="jenis_kendaraan_select" class="form-select" required>
                                <option value="">-- Pilih Jenis --</option>
                                <?php foreach ($tarifs as $t): ?>
                                    <option value="<?= $t['jenis_kendaraan'] ?>" data-tarif="<?= $t['tarif_per_jam'] ?>"><?= ucfirst($t['jenis_kendaraan']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small id="info_tarif" class="text-danger fw-semibold"></small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Warna Kendaraan</label>
                            <input type="text" name="warna" id="warna" class="form-control" placeholder="Contoh: Hitam">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nama Pemilik / Member</label>
                            <input type="text" name="pemilik" id="pemilik" class="form-control" placeholder="Nama member atau tamu" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Area Parkir</label>
                            <select name="id_area" id="id_area_select" class="form-select" required>
                                <option value="">-- Pilih Area --</option>
                                <?php foreach ($areas as $a): ?>
                                    <option value="<?= $a['id_area'] ?>"><?= htmlspecialchars($a['nama_area']) ?> (sisa <?= $a['kapasitas'] - $a['terisi'] ?> slot)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tarif</label>
                            <select name="id_tarif" class="form-select" required>
                                <option value="">-- Pilih Tarif --</option>
                                <?php foreach ($tarifs as $t): ?>
                                    <option value="<?= $t['id_tarif'] ?>"><?= ucfirst($t['jenis_kendaraan']) ?> - <?= rupiah($t['tarif_per_jam']) ?>/jam</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-hanma w-100 mt-4 py-2"><i class="bi bi-check-circle me-1"></i> Simpan &amp; Cetak Struk Masuk</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Isi otomatis tarif berdasarkan jenis kendaraan (fitur asli)
document.getElementById('jenis_kendaraan_select').addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    const tarif = opt.getAttribute('data-tarif');
    const info = document.getElementById('info_tarif');
    info.textContent = tarif ? 'Tarif: Rp ' + Number(tarif).toLocaleString('id-ID') + '/jam' : '';
});

// Proses booking: auto-isi form dan kunci field identitas kendaraan
document.querySelectorAll('.btn-proses-booking').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('id_booking').value = btn.dataset.idBooking;
        document.getElementById('plat_nomor').value = btn.dataset.plat;
        document.getElementById('warna').value = btn.dataset.warna;
        document.getElementById('pemilik').value = btn.dataset.pemilik;

        const denda = parseInt(btn.dataset.denda || '0', 10);
        const infoDenda = document.getElementById('infoDendaMasuk');
        if (denda > 0) {
            infoDenda.innerHTML = ' <strong class="text-danger">Kendaraan ini terlambat, akan dikenakan denda sekitar Rp ' + denda.toLocaleString('id-ID') + '.</strong>';
        } else {
            infoDenda.innerHTML = '';
        }

        const areaSelect = document.getElementById('id_area_select');
        const idArea = btn.dataset.idArea;
        if (idArea) {
            const adaOpsi = Array.from(areaSelect.options).some(o => o.value === idArea);
            if (adaOpsi) {
                areaSelect.value = idArea;
            } else {
                // Area dari booking kemungkinan sudah penuh saat ini, biarkan petugas pilih manual
                areaSelect.value = '';
            }
        }

        const jenisSelect = document.getElementById('jenis_kendaraan_select');
        for (const opt of jenisSelect.options) {
            if (opt.value.toLowerCase() === btn.dataset.jenis.toLowerCase()) {
                opt.selected = true;
                jenisSelect.dispatchEvent(new Event('change'));
            }
        }

        document.getElementById('plat_nomor').readOnly = true;
        document.getElementById('warna').readOnly = true;
        document.getElementById('pemilik').readOnly = true;
        jenisSelect.disabled = true;

        document.getElementById('infoBooking').classList.remove('d-none');
        document.getElementById('plat_nomor').closest('.card').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
});

document.getElementById('btnBatalBooking').addEventListener('click', function () {
    document.getElementById('id_booking').value = '';
    document.getElementById('plat_nomor').readOnly = false;
    document.getElementById('warna').readOnly = false;
    document.getElementById('pemilik').readOnly = false;
    document.getElementById('jenis_kendaraan_select').disabled = false;
    document.getElementById('plat_nomor').value = '';
    document.getElementById('warna').value = '';
    document.getElementById('pemilik').value = '';
    document.getElementById('jenis_kendaraan_select').value = '';
    document.getElementById('infoDendaMasuk').innerHTML = '';
    document.getElementById('infoBooking').classList.add('d-none');
});
</script>

<?php include __DIR__ . '/components/footer.php'; ?>