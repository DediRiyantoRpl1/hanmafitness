<?php $current = basename($_SERVER['PHP_SELF']); ?>
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
        <div class="nav-section">Petugas Parkir</div>
        <a href="<?= BASE_URL ?>operator/index.php" class="nav-link <?= $current === 'index.php' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        <a href="<?= BASE_URL ?>operator/transaksi_masuk.php" class="nav-link <?= $current === 'transaksi_masuk.php' ? 'active' : '' ?>">
            <i class="bi bi-box-arrow-in-right"></i> Kendaraan Masuk
        </a>
        <a href="<?= BASE_URL ?>operator/transaksi_keluar.php" class="nav-link <?= $current === 'transaksi_keluar.php' ? 'active' : '' ?>">
            <i class="bi bi-box-arrow-right"></i> Kendaraan Keluar
        </a>
        <a href="<?= BASE_URL ?>operator/riwayat_transaksi.php" class="nav-link <?= $current === 'riwayat_transaksi.php' ? 'active' : '' ?>">
            <i class="bi bi-clock-history"></i> Riwayat Transaksi
        </a>

        <div class="nav-section">Akun</div>
        <a href="<?= BASE_URL ?>operator/edit_profil.php" class="nav-link <?= $current === 'edit_profil.php' ? 'active' : '' ?>">
            <i class="bi bi-person-circle"></i> Edit Profil
        </a>
        <a href="<?= BASE_URL ?>auth/logout.php" class="nav-link">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</div>

<div class="hanma-content">
    <div class="hanma-topbar">
        <div class="d-flex align-items-center gap-3">
            <button id="sidebarToggle" class="btn btn-sm btn-light d-lg-none"><i class="bi bi-list"></i></button>
            <h1 class="page-title"><?= isset($page_title) ? htmlspecialchars($page_title) : 'Dashboard' ?></h1>
        </div>
        <div class="dropdown">
            <div class="user-chip" role="button" data-bs-toggle="dropdown">
                <div class="avatar-mini">
                    <?php if (!empty($_SESSION['foto'])): ?>
                        <img src="<?= BASE_URL ?>uploads/profil/<?= htmlspecialchars($_SESSION['foto']) ?>"
                             alt="Foto Profil"
                             style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                    <?php else: ?>
                        <?= strtoupper(substr($_SESSION['nama_lengkap'], 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <div style="font-size:.85rem;font-weight:600;line-height:1;"><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></div>
                    <div style="font-size:.7rem;color:#8a8f98;">Petugas Parkir</div>
                </div>
                <i class="bi bi-chevron-down small"></i>
            </div>
            <ul class="dropdown-menu dropdown-menu-end mt-2">
                <li><a class="dropdown-item" href="<?= BASE_URL ?>operator/edit_profil.php"><i class="bi bi-person-circle me-2"></i>Edit Profil</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= BASE_URL ?>auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
        </div>
    </div>
    <div class="hanma-body">
        <?php if (isset($_GET['sukses'])): ?>
            <div class="alert alert-success alert-auto-dismiss"><i class="bi bi-check-circle me-1"></i> <?= htmlspecialchars($_GET['sukses']) ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['gagal'])): ?>
            <div class="alert alert-danger alert-auto-dismiss"><i class="bi bi-x-circle me-1"></i> <?= htmlspecialchars($_GET['gagal']) ?></div>
        <?php endif; ?>