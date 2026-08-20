<?php
$page_title = 'Dashboard Member';
require_once __DIR__ . '/../config/koneksi.php';

// Hanya boleh diakses oleh yang sudah login dan berrole member
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'member') {
    header("Location: " . BASE_URL . "auth/login.php?err=akses_ditolak");
    exit;
}

$id_user = $_SESSION['id_user'];

// ==== HAPUS (SOFT-DELETE) SATU RIWAYAT PARKIR - hanya yang statusnya sudah 'keluar', bukan 'masuk' ====
if (isset($_GET['hapus_riwayat'])) {
    $id_parkir = $_GET['hapus_riwayat'];
    $stmt = $koneksi->prepare(
        "UPDATE tb_transaksi t
         JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
         SET t.dihapus = 1
         WHERE t.id_parkir = ? AND k.id_user = ? AND t.status != 'masuk' AND t.dihapus = 0"
    );
    $stmt->execute([$id_parkir, $id_user]);

    if ($stmt->rowCount() > 0) {
        header("Location: index.php?sukses=Riwayat parkir berhasil dihapus");
    } else {
        header("Location: index.php?gagal=Riwayat parkir tidak dapat dihapus (pastikan status sudah Selesai)");
    }
    exit;
}

// Ambil daftar kendaraan milik member ini
$stmtKendaraan = $koneksi->prepare("SELECT * FROM tb_kendaraan WHERE id_user = ? ORDER BY id_kendaraan DESC");
$stmtKendaraan->execute([$id_user]);
$daftarKendaraan = $stmtKendaraan->fetchAll();

// Ambil riwayat parkir terbaru (join ke kendaraan milik member ini, sembunyikan yang sudah dihapus)
$stmtRiwayat = $koneksi->prepare(
    "SELECT t.*, k.plat_nomor, k.jenis_kendaraan
     FROM tb_transaksi t
     JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
     WHERE k.id_user = ? AND t.dihapus = 0
     ORDER BY t.waktu_masuk DESC
     LIMIT 10"
);
$stmtRiwayat->execute([$id_user]);
$riwayat = $stmtRiwayat->fetchAll();

// Cek apakah ada kendaraan yang sedang parkir (status masuk, belum keluar)
$stmtAktif = $koneksi->prepare(
    "SELECT t.*, k.plat_nomor, k.jenis_kendaraan
     FROM tb_transaksi t
     JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
     WHERE k.id_user = ? AND t.status = 'masuk' AND t.dihapus = 0
     ORDER BY t.waktu_masuk DESC"
);
$stmtAktif->execute([$id_user]);
$parkirAktif = $stmtAktif->fetchAll();

// Total riwayat parkir (untuk statistik, tidak termasuk yang sudah dihapus)
$stmtTotal = $koneksi->prepare(
    "SELECT COUNT(*) AS total FROM tb_transaksi t
     JOIN tb_kendaraan k ON t.id_kendaraan = k.id_kendaraan
     WHERE k.id_user = ? AND t.dihapus = 0"
);
$stmtTotal->execute([$id_user]);
$totalParkir = $stmtTotal->fetch()['total'] ?? 0;

// ===== Data booking terbaru (sinkron dengan booking.php) =====
$stmtBookingTerbaru = $koneksi->prepare(
    "SELECT b.*, k.plat_nomor, k.jenis_kendaraan, a.nama_area
     FROM tb_booking b
     JOIN tb_kendaraan k ON b.id_kendaraan = k.id_kendaraan
     LEFT JOIN tb_area_parkir a ON b.id_area = a.id_area
     WHERE b.id_user = ?
     ORDER BY b.id_booking DESC
     LIMIT 5"
);
$stmtBookingTerbaru->execute([$id_user]);
$bookingTerbaru = $stmtBookingTerbaru->fetchAll();

// Jumlah booking yang masih aktif (menunggu / dikonfirmasi) -> kartu statistik
$stmtBookingAktif = $koneksi->prepare(
    "SELECT COUNT(*) AS total FROM tb_booking WHERE id_user = ? AND status IN ('menunggu','dikonfirmasi')"
);
$stmtBookingAktif->execute([$id_user]);
$totalBookingAktif = (int) ($stmtBookingAktif->fetch()['total'] ?? 0);

require __DIR__ . '/components/header.php';
?>

<p class="text-muted mb-4">Berikut ringkasan aktivitas parkir kendaraan anda.</p>

<!-- Statistik Ringkas -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card bg-grad-red">
            <div class="stat-icon"><i class="bi bi-car-front-fill"></i></div>
            <h3><?= count($daftarKendaraan) ?></h3>
            <span class="label">Kendaraan Terdaftar</span>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-grad-orange">
            <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
            <h3><?= (int)$totalParkir ?></h3>
            <span class="label">Total Riwayat Parkir</span>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-grad-green">
            <div class="stat-icon"><i class="bi bi-p-square-fill"></i></div>
            <h3><?= count($parkirAktif) ?></h3>
            <span class="label">Sedang Parkir</span>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-grad-dark">
            <div class="stat-icon"><i class="bi bi-calendar-check-fill"></i></div>
            <h3><?= $totalBookingAktif ?></h3>
            <span class="label">Booking Aktif</span>
        </div>
    </div>
</div>

<!-- Kendaraan Sedang Parkir -->
<?php if (count($parkirAktif) > 0): ?>
<div class="card card-hanma mb-4">
    <div class="card-header fw-semibold">
        <i class="bi bi-p-square text-warning"></i> Kendaraan Sedang Parkir
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Plat Nomor</th>
                    <th>Jenis Kendaraan</th>
                    <th>Waktu Masuk</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($parkirAktif as $p): ?>
                <tr>
                    <td><span class="fw-semibold"><?= htmlspecialchars($p['plat_nomor']) ?></span></td>
                    <td><?= htmlspecialchars($p['jenis_kendaraan']) ?></td>
                    <td><?= date('d M Y, H:i', strtotime($p['waktu_masuk'])) ?></td>
                    <td><span class="badge bg-warning text-dark">Sedang Parkir</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Riwayat Booking Terbaru -->
<div class="card card-hanma mb-4">
    <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-calendar-check"></i> Riwayat Booking Terbaru</span>
        <a href="<?= BASE_URL ?>member/booking.php" class="btn btn-sm btn-outline-secondary">
            Lihat Semua <i class="bi bi-arrow-right"></i>
        </a>
    </div>
    <div class="card-body p-0">
        <?php if (count($bookingTerbaru) === 0): ?>
            <p class="text-muted text-center py-4 mb-0">
                Belum ada booking yang dibuat.
                <a href="<?= BASE_URL ?>member/booking.php">Buat booking sekarang</a>.
            </p>
        <?php else: ?>
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Kendaraan</th>
                        <th>Area</th>
                        <th>Tanggal</th>
                        <th>Jam Masuk</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $badgeBooking = ['menunggu' => 'bg-warning text-dark', 'dikonfirmasi' => 'bg-primary', 'selesai' => 'bg-success', 'dibatalkan' => 'bg-danger'];
                    $labelBooking = ['menunggu' => 'Menunggu', 'dikonfirmasi' => 'Dikonfirmasi', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'];
                    ?>
                    <?php foreach ($bookingTerbaru as $b): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars($b['plat_nomor']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($b['jenis_kendaraan']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($b['nama_area'] ?: '-') ?></td>
                        <td><?= date('d M Y', strtotime($b['tanggal_booking'])) ?></td>
                        <td><?= date('H:i', strtotime($b['jam_booking_masuk'])) ?></td>
                        <td>
                            <span class="badge <?= $badgeBooking[$b['status']] ?>">
                                <?= $labelBooking[$b['status']] ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <!-- Daftar Kendaraan -->
    <div class="col-lg-5">
        <div class="card card-hanma h-100">
            <div class="card-header fw-semibold">
                <i class="bi bi-car-front"></i> Kendaraan Saya
            </div>
            <div class="card-body p-0">
                <?php if (count($daftarKendaraan) === 0): ?>
                    <p class="text-muted text-center py-4 mb-0">Belum ada kendaraan terdaftar.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($daftarKendaraan as $k): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold"><?= htmlspecialchars($k['plat_nomor']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($k['jenis_kendaraan']) ?></small>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Riwayat Parkir -->
    <div class="col-lg-7">
        <div class="card card-hanma h-100">
            <div class="card-header fw-semibold">
                <i class="bi bi-clock-history"></i> Riwayat Parkir Terbaru
            </div>
            <div class="card-body p-0">
                <?php if (count($riwayat) === 0): ?>
                    <p class="text-muted text-center py-4 mb-0">Belum ada riwayat parkir.</p>
                <?php else: ?>
                    <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Plat Nomor</th>
                                <th>Masuk</th>
                                <th>Keluar</th>
                                <th>Biaya</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($riwayat as $r): ?>
                            <?php $sudahSelesai = ($r['status'] !== 'masuk'); ?>
                            <tr>
                                <td><?= htmlspecialchars($r['plat_nomor']) ?></td>
                                <td><?= date('d M Y, H:i', strtotime($r['waktu_masuk'])) ?></td>
                                <td>
                                    <?= $r['waktu_keluar'] ? date('d M Y, H:i', strtotime($r['waktu_keluar'])) : '-' ?>
                                </td>
                                <td>
                                    <?= !empty($r['biaya_total']) ? 'Rp ' . number_format($r['biaya_total'], 0, ',', '.') : '-' ?>
                                </td>
                                <td>
                                    <?php if (!$sudahSelesai): ?>
                                        <span class="badge bg-warning text-dark">Sedang Parkir</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Selesai</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($sudahSelesai): ?>
                                    <a href="?hapus_riwayat=<?= $r['id_parkir'] ?>" class="btn btn-sm btn-outline-secondary btn-hapus-konfirmasi">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/components/footer.php'; ?>