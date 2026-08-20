<?php
/**
 * Dipakai di dalam admin/*.php setelah $page_title didefinisikan.
 * Membutuhkan variabel $koneksi & session admin aktif (cekLogin(['admin'])).
 */

// Ambil foto profil terbaru dari database (biar langsung update tanpa perlu login ulang)
$fotoAdmin = null;
if (isset($_SESSION['id_user'])) {
    $stmtFotoAdmin = $koneksi->prepare("SELECT foto FROM tb_user WHERE id_user = ?");
    $stmtFotoAdmin->execute([$_SESSION['id_user']]);
    $rowFotoAdmin = $stmtFotoAdmin->fetch();
    $fotoAdmin = $rowFotoAdmin['foto'] ?? null;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' - ' : '' ?>Hanma Fitness Parking</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>
<?php tampilkanNotifikasiLogin(); ?>
<?php include __DIR__ . '/sidebar_admin.php'; ?>

<div class="hanma-content">
    <div class="hanma-topbar">
        <div class="d-flex align-items-center gap-3">
            <button id="sidebarToggle" class="btn btn-sm btn-light d-lg-none"><i class="bi bi-list"></i></button>
            <h1 class="page-title"><?= isset($page_title) ? htmlspecialchars($page_title) : 'Dashboard' ?></h1>
        </div>
        <div class="dropdown">
            <div class="user-chip" role="button" data-bs-toggle="dropdown">
                <div class="avatar-mini avatar-mini-img">
                    <?php if ($fotoAdmin): ?>
                        <img src="<?= BASE_URL ?>uploads/profil/<?= htmlspecialchars($fotoAdmin) ?>"
                             alt="Foto Profil"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <?php endif; ?>
                    <span class="avatar-fallback" style="<?= $fotoAdmin ? 'display:none;' : 'display:flex;' ?>">
                        <?= strtoupper(substr($_SESSION['nama_lengkap'], 0, 1)) ?>
                    </span>
                </div>
                <div>
                    <div style="font-size:.85rem;font-weight:600;line-height:1;"><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></div>
                    <div style="font-size:.7rem;color:#8a8f98;">Administrator</div>
                </div>
                <i class="bi bi-chevron-down small"></i>
            </div>
            <ul class="dropdown-menu dropdown-menu-end mt-2">
                <li><a class="dropdown-item" href="<?= BASE_URL ?>admin/edit_profil.php"><i class="bi bi-person-circle me-2"></i>Edit Profil</a></li>
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