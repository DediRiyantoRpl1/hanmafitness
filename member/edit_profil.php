<?php
require_once __DIR__ . '/../config/koneksi.php';

// Hanya boleh diakses oleh yang sudah login dan berrole member
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'member') {
    header("Location: " . BASE_URL . "auth/login.php?err=akses_ditolak");
    exit;
}

$id_user = $_SESSION['id_user'];
$error = '';
$success = '';

$stmtUser = $koneksi->prepare("SELECT * FROM tb_user WHERE id_user = ?");
$stmtUser->execute([$id_user]);
$user = $stmtUser->fetch();

// ==== UPLOAD / GANTI FOTO PROFIL ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'upload_foto') {
    if (!isset($_FILES['foto']) || $_FILES['foto']['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Silakan pilih foto terlebih dahulu.';
    } elseif ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Terjadi kesalahan saat upload foto.';
    } else {
        $file = $_FILES['foto'];
        $ekstensiValid = ['jpg', 'jpeg', 'png', 'webp'];
        $ekstensi = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $ukuranMaks = 2 * 1024 * 1024; // 2 MB

        if (!in_array($ekstensi, $ekstensiValid)) {
            $error = 'Format foto harus JPG, PNG, atau WEBP.';
        } elseif ($file['size'] > $ukuranMaks) {
            $error = 'Ukuran foto maksimal 2 MB.';
        } else {
            $folderUpload = __DIR__ . '/../uploads/profil/';
            if (!is_dir($folderUpload)) {
                mkdir($folderUpload, 0755, true);
            }

            $namaFileBaru = 'user_' . $id_user . '_' . time() . '.' . $ekstensi;
            $tujuan = $folderUpload . $namaFileBaru;

            if (move_uploaded_file($file['tmp_name'], $tujuan)) {
                // Hapus foto lama kalau ada, biar folder tidak menumpuk
                if (!empty($user['foto'])) {
                    $fotoLama = $folderUpload . $user['foto'];
                    if (file_exists($fotoLama)) {
                        @unlink($fotoLama);
                    }
                }

                $stmtUpdate = $koneksi->prepare("UPDATE tb_user SET foto = ? WHERE id_user = ?");
                $stmtUpdate->execute([$namaFileBaru, $id_user]);

                header("Location: edit_profil.php?sukses=Foto profil berhasil diperbarui");
                exit;
            } else {
                $error = 'Gagal menyimpan foto. Coba lagi.';
            }
        }
    }

    // refresh data user kalau ada error di atas
    $stmtUser->execute([$id_user]);
    $user = $stmtUser->fetch();
}

// ==== HAPUS FOTO PROFIL ====
if (isset($_GET['hapus_foto'])) {
    if (!empty($user['foto'])) {
        $fotoLama = __DIR__ . '/../uploads/profil/' . $user['foto'];
        if (file_exists($fotoLama)) {
            @unlink($fotoLama);
        }
        $stmtUpdate = $koneksi->prepare("UPDATE tb_user SET foto = NULL WHERE id_user = ?");
        $stmtUpdate->execute([$id_user]);
    }
    header("Location: edit_profil.php?sukses=Foto profil dihapus");
    exit;
}

// ==== UPDATE PASSWORD (khusus member, boleh ganti sendiri) ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'ganti_password') {
    $passwordLama = $_POST['password_lama'] ?? '';
    $passwordBaru = $_POST['password_baru'] ?? '';
    $passwordKonfirmasi = $_POST['password_konfirmasi'] ?? '';

    if ($passwordLama === '' || $passwordBaru === '' || $passwordKonfirmasi === '') {
        $error = 'Semua kolom password wajib diisi.';
    } elseif (!password_verify($passwordLama, $user['password'])) {
        $error = 'Password lama tidak sesuai.';
    } elseif (strlen($passwordBaru) < 6) {
        $error = 'Password baru minimal 6 karakter.';
    } elseif ($passwordBaru !== $passwordKonfirmasi) {
        $error = 'Konfirmasi password baru tidak sama.';
    } else {
        $hashBaru = password_hash($passwordBaru, PASSWORD_DEFAULT);
        $stmtUpdate = $koneksi->prepare("UPDATE tb_user SET password = ? WHERE id_user = ?");
        $stmtUpdate->execute([$hashBaru, $id_user]);
        header("Location: edit_profil.php?sukses=Password berhasil diperbarui");
        exit;
    }

    $stmtUser->execute([$id_user]);
    $user = $stmtUser->fetch();
}

if (isset($_GET['sukses'])) $success = $_GET['sukses'];
if (isset($_GET['gagal'])) $error = $_GET['gagal'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Profil - Hanma Fitness Parking System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="bg-light member-page">

<nav class="navbar navbar-expand-lg navbar-dark" style="background-color:#0d1b2a;">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>member/index.php">
            <i class="bi bi-p-circle"></i> HANMA FITNESS PARKING
        </a>
        <div class="d-flex align-items-center">
            <a href="<?= BASE_URL ?>member/index.php" class="btn btn-outline-light btn-sm me-2">
                <i class="bi bi-house"></i> Dashboard
            </a>
            <a href="<?= BASE_URL ?>member/booking.php" class="btn btn-outline-light btn-sm me-2">
                <i class="bi bi-calendar-plus"></i> Booking Parkir
            </a>
            <a href="<?= BASE_URL ?>member/edit_profil.php" class="btn btn-outline-light btn-sm me-2 active">
                <i class="bi bi-person-gear"></i> Edit Profil
            </a>
            <span class="text-light me-3 d-flex align-items-center">
                <?php if (!empty($user['foto'])): ?>
                    <img src="<?= BASE_URL ?>uploads/profil/<?= htmlspecialchars($user['foto']) ?>"
                         alt="Foto Profil"
                         style="width:28px;height:28px;border-radius:50%;object-fit:cover;margin-right:6px;">
                <?php else: ?>
                    <i class="bi bi-person-circle me-1"></i>
                <?php endif; ?>
                <?= htmlspecialchars($_SESSION['nama_lengkap']) ?>
            </span>
            <a href="<?= BASE_URL ?>auth/logout.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-box-arrow-right"></i> Keluar
            </a>
        </div>
    </div>
</nav>

<div class="container py-4">

    <h4 class="mb-1 text-white"><i class="bi bi-person-gear"></i> Edit Profil</h4>
    <p class="text-white mb-4">Kelola foto profil dan password akun anda.</p>

    <?php if ($error): ?>
        <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="row g-3">
        <!-- Foto Profil -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-image"></i> Foto Profil
                </div>
                <div class="card-body text-center">

                    <div class="mb-3">
                        <?php if (!empty($user['foto'])): ?>
                            <img src="<?= BASE_URL ?>uploads/profil/<?= htmlspecialchars($user['foto']) ?>?v=<?= time() ?>"
                                 alt="Foto Profil"
                                 style="width:140px;height:140px;border-radius:50%;object-fit:cover;border:3px solid #eee;">
                        <?php else: ?>
                            <div style="width:140px;height:140px;border-radius:50%;background:linear-gradient(135deg,#0d1b2a,#1b3a5c);
                                        display:flex;align-items:center;justify-content:center;color:#fff;font-size:3rem;
                                        font-weight:700;margin:0 auto;">
                                <?= strtoupper(substr($user['nama_lengkap'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <form method="POST" enctype="multipart/form-data" class="d-flex flex-column align-items-center gap-2">
                        <input type="hidden" name="aksi" value="upload_foto">
                        <input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp" class="form-control" required>
                        <small class="text-muted">Format JPG/PNG/WEBP, maksimal 2 MB.</small>
                        <button type="submit" class="btn btn-primary w-100 mt-2">
                            <i class="bi bi-upload"></i> Unggah Foto Baru
                        </button>
                    </form>

                    <?php if (!empty($user['foto'])): ?>
                        <a href="?hapus_foto=1"
                           onclick="return confirm('Hapus foto profil?');"
                           class="btn btn-outline-danger btn-sm w-100 mt-2">
                            <i class="bi bi-trash"></i> Hapus Foto
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Informasi Akun & Ganti Password -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-person-vcard"></i> Informasi Akun
                </div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tr>
                            <td class="text-muted" style="width:160px;">Nama Lengkap</td>
                            <td class="fw-semibold"><?= htmlspecialchars($user['nama_lengkap']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Username</td>
                            <td class="fw-semibold"><?= htmlspecialchars($user['username']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Role</td>
                            <td class="fw-semibold"><?= ucfirst($user['role']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Bergabung Sejak</td>
                            <td class="fw-semibold"><?= date('d M Y', strtotime($user['created_at'])) ?></td>
                        </tr>
                    </table>
                    <p class="text-muted small mt-3 mb-0">
                        Untuk mengubah nama atau username, silakan hubungi Administrator.
                    </p>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-shield-lock"></i> Ganti Password
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="aksi" value="ganti_password">
                        <div class="mb-3">
                            <label class="form-label">Password Lama</label>
                            <input type="password" name="password_lama" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password Baru</label>
                            <input type="password" name="password_baru" class="form-control" minlength="6" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Konfirmasi Password Baru</label>
                            <input type="password" name="password_konfirmasi" class="form-control" minlength="6" required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Simpan Password Baru
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>
</body>
</html>