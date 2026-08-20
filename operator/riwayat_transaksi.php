<?php
require_once __DIR__ . '/../config/koneksi.php';
cekLogin(['petugas']);
$page_title = 'Riwayat Transaksi';

$mode    = $_GET['mode'] ?? 'riwayat';       // riwayat | rekap
$tanggal = $_GET['tanggal'] ?? '';
$bulan   = $_GET['bulan'] ?? date('Y-m');    // format YYYY-MM

$success = '';
$error   = '';

// ============================
// HAPUS SATU RIWAYAT TRANSAKSI (tombol per baris)
// ============================
// Soft delete: baris TIDAK dihapus dari database, hanya ditandai dihapus=1
// supaya tidak muncul lagi di daftar riwayat. Data tetap dipakai untuk
// perhitungan rekap pendapatan (lihat query rekap di bawah), jadi angka
// pendapatan tidak akan berubah walau riwayatnya "dihapus" dari tampilan.
// Hanya transaksi berstatus 'keluar' (sudah selesai) yang boleh ditandai —
// transaksi yang masih 'masuk' (kendaraan sedang fisik parkir) tidak boleh
// disentuh di sini.
if (isset($_GET['hapus'])) {
    $id_parkir = $_GET['hapus'];
    $stmt = $koneksi->prepare(
        "UPDATE tb_transaksi SET dihapus = 1
         WHERE id_parkir = ? AND id_user = ? AND status = 'keluar' AND dihapus = 0"
    );
    $stmt->execute([$id_parkir, $_SESSION['id_user']]);

    $redirect = "riwayat_transaksi.php?" . ($stmt->rowCount() > 0
        ? "sukses=Riwayat transaksi berhasil dihapus"
        : "gagal=Transaksi tidak dapat dihapus karena masih aktif");
    if ($tanggal !== '') $redirect .= "&tanggal=" . urlencode($tanggal);
    header("Location: " . $redirect);
    exit;
}

// ============================
// HAPUS RIWAYAT TERPILIH (checkbox) / HAPUS SEMUA RIWAYAT TRANSAKSI
// ============================
// Sama seperti hapus satuan: soft delete, hanya transaksi berstatus 'keluar'
// yang bisa ikut ditandai, difilter langsung di query SQL supaya tidak bisa
// dimanipulasi dari sisi form. "Hapus Semua" mengikuti filter tanggal yang
// sedang aktif (kalau ada), jadi hanya menandai riwayat yang sedang ditampilkan.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && in_array($_POST['aksi'], ['hapus_terpilih', 'hapus_semua'], true)) {
    $tanggalFilter  = $_POST['tanggal_filter'] ?? '';
    $jumlahDihapus  = 0;

    if ($_POST['aksi'] === 'hapus_semua') {
        if ($tanggalFilter !== '') {
            $stmt = $koneksi->prepare(
                "UPDATE tb_transaksi SET dihapus = 1
                 WHERE id_user = ? AND status = 'keluar' AND dihapus = 0 AND DATE(waktu_masuk) = ?"
            );
            $stmt->execute([$_SESSION['id_user'], $tanggalFilter]);
        } else {
            $stmt = $koneksi->prepare(
                "UPDATE tb_transaksi SET dihapus = 1
                 WHERE id_user = ? AND status = 'keluar' AND dihapus = 0"
            );
            $stmt->execute([$_SESSION['id_user']]);
        }
        $jumlahDihapus = $stmt->rowCount();
    } else {
        $idTerpilih = $_POST['id_parkir'] ?? [];
        $idTerpilih = array_values(array_filter(array_map('intval', (array) $idTerpilih)));

        if (!empty($idTerpilih)) {
            $placeholder = implode(',', array_fill(0, count($idTerpilih), '?'));
            $params = array_merge($idTerpilih, [$_SESSION['id_user']]);
            $stmt = $koneksi->prepare(
                "UPDATE tb_transaksi SET dihapus = 1
                 WHERE id_parkir IN ($placeholder) AND id_user = ? AND status = 'keluar' AND dihapus = 0"
            );
            $stmt->execute($params);
            $jumlahDihapus = $stmt->rowCount();
        }
    }

    $redirect = "riwayat_transaksi.php?" . ($jumlahDihapus > 0
        ? "sukses=" . $jumlahDihapus . " riwayat transaksi berhasil dihapus"
        : "gagal=Tidak ada riwayat transaksi yang dapat dihapus (pastikan status sudah Keluar)");
    if ($tanggalFilter !== '') $redirect .= "&tanggal=" . urlencode($tanggalFilter);
    header("Location: " . $redirect);
    exit;
}

if (isset($_GET['sukses'])) $success = $_GET['sukses'];
if (isset($_GET['gagal']))  $error   = $_GET['gagal'];

// ============================
// EXPORT CSV REKAP BULANAN
// ============================
// Export tetap memakai SEMUA data (termasuk yang sudah ditandai dihapus dari
// riwayat) supaya rekapan yang diunduh selalu akurat secara finansial.
if ($mode === 'export' && preg_match('/^\d{4}-\d{2}$/', $bulan)) {
    $sql = "
        SELECT t.id_parkir, k.plat_nomor, k.jenis_kendaraan, k.pemilik, a.nama_area,
               t.waktu_masuk, t.waktu_keluar, t.status, t.biaya_total
        FROM tb_transaksi t
        JOIN tb_kendaraan k ON k.id_kendaraan = t.id_kendaraan
        JOIN tb_area_parkir a ON a.id_area = t.id_area
        WHERE t.id_user = ? AND DATE_FORMAT(t.waktu_masuk, '%Y-%m') = ?
        ORDER BY t.waktu_masuk ASC
    ";
    $stmt = $koneksi->prepare($sql);
    $stmt->execute([$_SESSION['id_user'], $bulan]);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=rekap_transaksi_' . $bulan . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Plat Nomor', 'Jenis Kendaraan', 'Pemilik', 'Area', 'Waktu Masuk', 'Waktu Keluar', 'Status', 'Biaya']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id_parkir'],
            $r['plat_nomor'],
            $r['jenis_kendaraan'],
            $r['pemilik'],
            $r['nama_area'],
            $r['waktu_masuk'],
            $r['waktu_keluar'] ?? '-',
            ucfirst($r['status']),
            $r['biaya_total'] ?? 0,
        ]);
    }
    fclose($out);
    exit;
}

// ============================
// DATA: REKAP BULANAN
// ============================
// PENTING: query rekap TIDAK memfilter dihapus, supaya menghapus riwayat
// tidak pernah mengubah angka pendapatan/laporan keuangan.
if ($mode === 'rekap') {
    // Ringkasan per hari dalam bulan terpilih
    $sqlHarian = "
        SELECT
            DATE(t.waktu_masuk) AS tgl,
            COUNT(*) AS jumlah_transaksi,
            SUM(CASE WHEN t.status = 'keluar' THEN t.biaya_total ELSE 0 END) AS pendapatan
        FROM tb_transaksi t
        WHERE t.id_user = ? AND DATE_FORMAT(t.waktu_masuk, '%Y-%m') = ?
        GROUP BY DATE(t.waktu_masuk)
        ORDER BY tgl ASC
    ";
    $stmt = $koneksi->prepare($sqlHarian);
    $stmt->execute([$_SESSION['id_user'], $bulan]);
    $rekapHarian = $stmt->fetchAll();

    // Breakdown per area
    $sqlArea = "
        SELECT a.nama_area,
               COUNT(*) AS jumlah_transaksi,
               SUM(CASE WHEN t.status = 'keluar' THEN t.biaya_total ELSE 0 END) AS pendapatan
        FROM tb_transaksi t
        JOIN tb_area_parkir a ON a.id_area = t.id_area
        WHERE t.id_user = ? AND DATE_FORMAT(t.waktu_masuk, '%Y-%m') = ?
        GROUP BY a.id_area, a.nama_area
        ORDER BY pendapatan DESC
    ";
    $stmt = $koneksi->prepare($sqlArea);
    $stmt->execute([$_SESSION['id_user'], $bulan]);
    $rekapArea = $stmt->fetchAll();

    // Total keseluruhan bulan itu
    $totalTransaksi  = array_sum(array_column($rekapHarian, 'jumlah_transaksi'));
    $totalPendapatan = array_sum(array_column($rekapHarian, 'pendapatan'));
    $jumlahHariAda   = count($rekapHarian);
    $rataRataHarian  = $jumlahHariAda > 0 ? $totalPendapatan / $jumlahHariAda : 0;

// ============================
// DATA: RIWAYAT HARIAN (existing)
// ============================
} else {
    // Hanya tampilkan yang belum ditandai dihapus (dihapus = 0)
    $sql = "
        SELECT t.*, k.plat_nomor, k.jenis_kendaraan, k.pemilik, a.nama_area
        FROM tb_transaksi t
        JOIN tb_kendaraan k ON k.id_kendaraan = t.id_kendaraan
        JOIN tb_area_parkir a ON a.id_area = t.id_area
        WHERE t.id_user = ? AND t.dihapus = 0
    ";
    $params = [$_SESSION['id_user']];
    if ($tanggal !== '') {
        $sql .= " AND DATE(t.waktu_masuk) = ?";
        $params[] = $tanggal;
    }
    $sql .= " ORDER BY t.id_parkir DESC LIMIT 200";
    $stmt = $koneksi->prepare($sql);
    $stmt->execute($params);
    $riwayat = $stmt->fetchAll();

    // Apakah ada minimal satu transaksi berstatus 'keluar' (selesai)?
    // Dipakai untuk menampilkan/menyembunyikan toolbar hapus.
    $adaRiwayatBisaDihapus = false;
    foreach ($riwayat as $r) {
        if ($r['status'] === 'keluar') {
            $adaRiwayatBisaDihapus = true;
            break;
        }
    }
}

include __DIR__ . '/components/header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="card card-hanma">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <ul class="nav nav-pills mb-0">
            <li class="nav-item">
                <a class="nav-link <?= $mode === 'riwayat' ? 'active' : '' ?>" href="riwayat_transaksi.php">Riwayat</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $mode === 'rekap' ? 'active' : '' ?>" href="riwayat_transaksi.php?mode=rekap&bulan=<?= htmlspecialchars($bulan) ?>">Rekap Bulanan</a>
            </li>
        </ul>

        <?php if ($mode === 'rekap'): ?>
            <form method="GET" class="d-flex gap-2">
                <input type="hidden" name="mode" value="rekap">
                <input type="month" name="bulan" class="form-control form-control-sm" value="<?= htmlspecialchars($bulan) ?>">
                <button class="btn btn-sm btn-hanma">Tampilkan</button>
                <a href="riwayat_transaksi.php?mode=export&bulan=<?= htmlspecialchars($bulan) ?>" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-download"></i> Export CSV
                </a>
            </form>
        <?php else: ?>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <form method="GET" class="d-flex gap-2">
                    <input type="date" name="tanggal" class="form-control form-control-sm" value="<?= htmlspecialchars($tanggal) ?>">
                    <button class="btn btn-sm btn-hanma">Filter</button>
                    <?php if ($tanggal !== ''): ?><a href="riwayat_transaksi.php" class="btn btn-sm btn-outline-secondary">Reset</a><?php endif; ?>
                </form>
                <?php if ($adaRiwayatBisaDihapus): ?>
                <div class="d-flex gap-2">
                    <button type="submit" form="formRiwayatTransaksi" name="aksi" value="hapus_terpilih"
                            id="btnHapusTerpilih" class="btn btn-sm btn-outline-secondary" disabled>
                        <i class="bi bi-trash"></i> Hapus Terpilih (<span id="jumlahTerpilih">0</span>)
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalHapusSemuaTransaksi">
                        <i class="bi bi-trash3"></i> Hapus Semua
                    </button>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card-body <?= $mode === 'rekap' ? '' : 'p-0' ?>">
    <?php if ($mode === 'rekap'): ?>

        <!-- Ringkasan -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="p-3 border rounded-3 h-100">
                    <div class="text-muted small">Total Transaksi</div>
                    <div class="fs-4 fw-bold"><?= number_format($totalTransaksi) ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 border rounded-3 h-100">
                    <div class="text-muted small">Total Pendapatan</div>
                    <div class="fs-4 fw-bold"><?= rupiah($totalPendapatan) ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 border rounded-3 h-100">
                    <div class="text-muted small">Rata-rata Pendapatan / Hari</div>
                    <div class="fs-4 fw-bold"><?= rupiah($rataRataHarian) ?></div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Rekap per hari -->
            <div class="col-lg-7">
                <h6 class="mb-2">Rekap Harian — <?= date('F Y', strtotime($bulan . '-01')) ?></h6>
                <div class="table-responsive" style="max-height:420px;">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="sticky-top bg-white">
                            <tr><th>Tanggal</th><th>Jumlah Transaksi</th><th>Pendapatan</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rekapHarian as $r): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($r['tgl'])) ?></td>
                                <td><?= number_format($r['jumlah_transaksi']) ?></td>
                                <td><?= rupiah($r['pendapatan']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($rekapHarian)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4">Tidak ada transaksi pada bulan ini</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Rekap per area -->
            <div class="col-lg-5">
                <h6 class="mb-2">Rekap per Area Parkir</h6>
                <div class="table-responsive" style="max-height:420px;">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="sticky-top bg-white">
                            <tr><th>Area</th><th>Jumlah</th><th>Pendapatan</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rekapArea as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['nama_area']) ?></td>
                                <td><?= number_format($r['jumlah_transaksi']) ?></td>
                                <td><?= rupiah($r['pendapatan']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($rekapArea)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4">Tidak ada data</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php else: ?>

        <!-- Tabel riwayat harian -->
        <form method="POST" id="formRiwayatTransaksi">
        <input type="hidden" name="tanggal_filter" value="<?= htmlspecialchars($tanggal) ?>">
        <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <?php if ($adaRiwayatBisaDihapus): ?>
                    <th style="width:36px;">
                        <input type="checkbox" class="form-check-input" id="checkSemuaTransaksi" title="Pilih semua yang bisa dihapus">
                    </th>
                    <?php endif; ?>
                    <th>#</th><th>Plat Nomor</th><th>Pemilik</th><th>Area</th><th>Masuk</th><th>Keluar</th><th>Status</th><th>Biaya</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($riwayat as $i => $r): ?>
                <?php $bisaDihapus = ($r['status'] === 'keluar'); ?>
                <tr>
                    <?php if ($adaRiwayatBisaDihapus): ?>
                    <td>
                        <?php if ($bisaDihapus): ?>
                        <input type="checkbox" class="form-check-input check-transaksi-item" name="id_parkir[]" value="<?= $r['id_parkir'] ?>">
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td><?= $i + 1 ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars($r['plat_nomor']) ?></td>
                    <td><?= htmlspecialchars($r['pemilik']) ?></td>
                    <td><?= htmlspecialchars($r['nama_area']) ?></td>
                    <td><?= date('d/m/y H:i', strtotime($r['waktu_masuk'])) ?></td>
                    <td><?= $r['waktu_keluar'] ? date('d/m/y H:i', strtotime($r['waktu_keluar'])) : '-' ?></td>
                    <td><span class="badge badge-status-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
                    <td><?= $r['biaya_total'] ? rupiah($r['biaya_total']) : '-' ?></td>
                    <td class="text-end">
                        <a href="cetak_struk.php?id=<?= $r['id_parkir'] ?>&mode=<?= $r['status'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></a>
                        <?php if ($bisaDihapus): ?>
                        <a href="?hapus=<?= $r['id_parkir'] ?><?= $tanggal !== '' ? '&tanggal=' . urlencode($tanggal) : '' ?>" class="btn btn-sm btn-outline-danger btn-hapus-transaksi-konfirmasi">
                            <i class="bi bi-trash"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($riwayat)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">Belum ada riwayat transaksi</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
        </form>

    <?php endif; ?>
    </div>
</div>

<?php if ($mode !== 'rekap' && !empty($adaRiwayatBisaDihapus)): ?>
<!-- Modal Konfirmasi Hapus Semua Riwayat Transaksi -->
<div class="modal fade" id="modalHapusSemuaTransaksi" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Hapus Semua Riwayat Transaksi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">
                    Apakah Anda yakin ingin menghapus <strong>semua riwayat transaksi berstatus Keluar (selesai)</strong>
                    <?= $tanggal !== '' ? 'pada tanggal <strong>' . date('d/m/Y', strtotime($tanggal)) . '</strong>' : 'di semua tanggal' ?>?
                    Transaksi yang kendaraannya <strong>masih parkir</strong> tidak akan terhapus.
                    Data akan tetap tersimpan untuk rekap pendapatan, hanya disembunyikan dari daftar riwayat ini.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" form="formRiwayatTransaksi" name="aksi" value="hapus_semua" class="btn btn-danger">
                    Ya, Hapus Semua
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.btn-hapus-transaksi-konfirmasi').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
        if (!confirm('Yakin ingin menghapus riwayat transaksi ini dari daftar?')) e.preventDefault();
    });
});

(function () {
    const checkSemua  = document.getElementById('checkSemuaTransaksi');
    const checkItem   = document.querySelectorAll('.check-transaksi-item');
    const btnHapus    = document.getElementById('btnHapusTerpilih');
    const jumlahLabel = document.getElementById('jumlahTerpilih');
    const formRiwayat = document.getElementById('formRiwayatTransaksi');

    function updateTombolHapusTerpilih() {
        if (!btnHapus) return;
        const jumlah = document.querySelectorAll('.check-transaksi-item:checked').length;
        jumlahLabel.textContent = jumlah;
        btnHapus.disabled = jumlah === 0;
    }

    if (checkSemua) {
        checkSemua.addEventListener('change', function () {
            checkItem.forEach(function (cb) { cb.checked = checkSemua.checked; });
            updateTombolHapusTerpilih();
        });
    }

    checkItem.forEach(function (cb) {
        cb.addEventListener('change', function () {
            if (!cb.checked && checkSemua) checkSemua.checked = false;
            updateTombolHapusTerpilih();
        });
    });

    if (formRiwayat) {
        formRiwayat.addEventListener('submit', function (e) {
            const submitter = e.submitter;
            const aksi = submitter ? submitter.value : '';
            if (aksi === 'hapus_terpilih') {
                if (!confirm('Yakin ingin menghapus riwayat transaksi yang dipilih dari daftar?')) {
                    e.preventDefault();
                }
            }
            // aksi === 'hapus_semua' sudah dikonfirmasi lewat modal, tidak perlu confirm() lagi
        });
    }
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/components/footer.php'; ?>