<?php
$current = basename($_SERVER['PHP_SELF']);

// ===== Hitung jumlah pengajuan aktivasi yang masih menunggu (untuk badge) =====
$jumlahPengajuanMenunggu = 0;
try {
    $stmtBadgePengajuan = $koneksi->query(
        "SELECT COUNT(*) AS total FROM tb_pengajuan_aktivasi WHERE status = 'menunggu'"
    );
    $jumlahPengajuanMenunggu = (int) $stmtBadgePengajuan->fetch()['total'];
} catch (PDOException $e) {
    $jumlahPengajuanMenunggu = 0;
}
?>
<div class="sidebar-bg-fixed"></div>
<div class="hanma-sidebar">
    <div class="brand">
        <div class="logo-box">
            <img src="<?= BASE_URL ?>img/fitnes.png" alt="Hanma Fitness"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
            <span class="logo-fallback"></span>
        </div>
        <div class="brand-text">
            <strong>HANMA FITNESS</strong>
            <small>PARKING SYSTEM</small>
        </div>
    </div>

    <div class="nav-area">
        <div class="nav-section">Menu Utama</div>
        <a href="<?= BASE_URL ?>admin/index.php" class="nav-link <?= $current === 'index.php' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="nav-section">Master Data</div>
        <a href="<?= BASE_URL ?>admin/kelola_user.php" class="nav-link <?= $current === 'kelola_user.php' ? 'active' : '' ?>">
            <i class="bi bi-people"></i> Kelola User
        </a>
        <a href="<?= BASE_URL ?>admin/pengajuan_aktivasi.php" class="nav-link <?= $current === 'pengajuan_aktivasi.php' ? 'active' : '' ?>">
            <i class="bi bi-person-plus"></i> Pengajuan Aktivasi
            <?php if ($jumlahPengajuanMenunggu > 0): ?>
                <span class="badge rounded-pill bg-danger ms-auto"><?= $jumlahPengajuanMenunggu ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= BASE_URL ?>admin/kelola_tarif.php" class="nav-link <?= $current === 'kelola_tarif.php' ? 'active' : '' ?>">
            <i class="bi bi-cash-coin"></i> Tarif Parkir
        </a>
        <a href="<?= BASE_URL ?>admin/kelola_area.php" class="nav-link <?= $current === 'kelola_area.php' ? 'active' : '' ?>">
            <i class="bi bi-p-square"></i> Area Parkir
        </a>
        <a href="<?= BASE_URL ?>admin/kelola_kendaraan.php" class="nav-link <?= $current === 'kelola_kendaraan.php' ? 'active' : '' ?>">
            <i class="bi bi-car-front"></i> Data Kendaraan
        </a>

        <div class="nav-section">Monitoring</div>
        <a href="<?= BASE_URL ?>admin/log_aktivitas.php" class="nav-link <?= $current === 'log_aktivitas.php' ? 'active' : '' ?>">
            <i class="bi bi-clock-history"></i> Log Aktivitas
        </a>
        <a href="<?= BASE_URL ?>admin/testimoni.php" class="nav-link <?= $current === 'testimoni.php' ? 'active' : '' ?>">
            <i class="bi bi-chat-left-text"></i> Testimoni
        </a>

        <div class="nav-section">Akun</div>
        <a href="<?= BASE_URL ?>auth/logout.php" class="nav-link">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</div>