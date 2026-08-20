<?php
require_once __DIR__ . '/../config/koneksi.php';

// Jika sudah login, langsung arahkan ke dashboard sesuai role
if (isset($_SESSION['id_user'])) {
    switch ($_SESSION['role']) {
        case 'admin': header("Location: " . BASE_URL . "admin/index.php"); exit;
        case 'petugas': header("Location: " . BASE_URL . "operator/index.php"); exit;
        case 'owner': header("Location: " . BASE_URL . "owner/index.php"); exit;
        case 'member': header("Location: " . BASE_URL . "member/index.php"); exit;
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $no_tlp = trim($_POST['no_tlp'] ?? '');
    $password = $_POST['password'] ?? '';
    $konfirmasi_password = $_POST['konfirmasi_password'] ?? '';

    if ($nama_lengkap === '' || $username === '' || $no_tlp === '' || $password === '' || $konfirmasi_password === '') {
        $error = 'Semua kolom wajib diisi.';
    } elseif (strlen($username) < 4) {
        $error = 'Username minimal 4 karakter.';
    } elseif (!preg_match('/^[0-9+\-\s]{9,15}$/', $no_tlp)) {
        $error = 'Nomor telepon tidak valid.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($password !== $konfirmasi_password) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        // Cek username sudah dipakai atau belum
        $cek = $koneksi->prepare("SELECT id_user FROM tb_user WHERE username = ? LIMIT 1");
        $cek->execute([$username]);

        if ($cek->fetch()) {
            $error = 'Username sudah digunakan, silakan pilih username lain.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'member'; // registrasi mandiri publik HANYA untuk role member

            $stmt = $koneksi->prepare(
                "INSERT INTO tb_user (nama_lengkap, username, no_tlp, password, role, status_aktif) 
                 VALUES (?, ?, ?, ?, ?, 1)"
            );
            $stmt->execute([$nama_lengkap, $username, $no_tlp, $hash, $role]);

            $success = 'Registrasi berhasil! Silakan login menggunakan akun anda.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Registrasi - Hanma Fitness Parking System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <style>
        .btn-back-landing {
            position: absolute;
            top: 20px;
            left: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255,255,255,.9);
            color: #222;
            font-weight: 600;
            font-size: .9rem;
            padding: 8px 16px;
            border-radius: 50px;
            text-decoration: none;
            box-shadow: 0 4px 14px rgba(0,0,0,.12);
            transition: transform .15s ease, box-shadow .15s ease;
            z-index: 10;
        }
        .btn-back-landing:hover {
            color: #b3121b;
            transform: translateX(-3px);
            box-shadow: 0 6px 18px rgba(0,0,0,.18);
        }
    </style>
</head>
<body>
<a href="<?= BASE_URL ?>auth/login.php" class="btn-back-landing">
    <i class="bi bi-arrow-left"></i> Kembali ke Login
</a>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-side">
            <span class="badge-tag">GYM PARKING SYSTEM</span>
            <h1>HANMA FITNESS</h1>
            <p class="mb-0">Sistem manajemen parkir untuk member &amp; tamu Hanma Fitness Gym. Cepat, rapi, dan
                terpantau real-time untuk Admin, Petugas, dan Owner.</p>
            <hr class="border-light opacity-25 my-4">
            <small class="opacity-75"><i class="bi bi-shield-check"></i> Akses berbasis peran (role-based access)</small><br>
            <small class="opacity-75"><i class="bi bi-p-circle"></i> Pantau slot area parkir secara real-time</small><br>
            <small class="opacity-75"><i class="bi bi-receipt"></i> Cetak struk &amp; rekap transaksi otomatis</small>
        </div>
        <div class="auth-form">
            <h3>Buat Akun Baru 📝</h3>
            <p class="text-muted mb-4">Daftar sebagai member untuk menikmati layanan parkir Hanma Fitness Gym</p>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" class="form-control" placeholder="Masukkan nama lengkap"
                           value="<?= htmlspecialchars($_POST['nama_lengkap'] ?? '') ?>" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" placeholder="Masukkan username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">No. Telepon</label>
                    <input type="text" name="no_tlp" class="form-control" placeholder="Contoh: 081234567890"
                           value="<?= htmlspecialchars($_POST['no_tlp'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Konfirmasi Password</label>
                    <input type="password" name="konfirmasi_password" class="form-control" placeholder="Ulangi password" required>
                </div>
                <button type="submit" class="btn btn-hanma w-100 py-2 mt-2">Daftar <i class="bi bi-person-plus"></i></button>
            </form>
            <div class="text-center mt-4">
                <small class="text-muted">Sudah punya akun? <a href="<?= BASE_URL ?>auth/login.php">Masuk di sini</a></small>
            </div>
        </div>
    </div>
</div>
</body>
</html>