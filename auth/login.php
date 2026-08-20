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
if (isset($_GET['err']) && $_GET['err'] === 'akses_ditolak') {
    $error = 'Akses ditolak. Silakan login dengan akun yang sesuai.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = $koneksi->prepare("SELECT * FROM tb_user WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ((int)$user['status_aktif'] === 0) {
                $error = 'Akun anda dinonaktifkan. Hubungi administrator.';
            } else {
                $_SESSION['id_user'] = $user['id_user'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['foto'] = $user['foto'];

                // Notifikasi toast yang akan tampil sekali di halaman dashboard setelah login
                $_SESSION['notif_login'] = [
                    'pesan' => 'Selamat datang, ' . $user['nama_lengkap'] . '! Anda berhasil masuk sebagai ' . ucfirst($user['role']) . '.',
                    'tipe'  => 'success'
                ];

                catatLog($koneksi, $user['id_user'], 'Login ke sistem');

                switch ($user['role']) {
                    case 'admin': header("Location: " . BASE_URL . "admin/index.php"); exit;
                    case 'petugas': header("Location: " . BASE_URL . "operator/index.php"); exit;
                    case 'owner': header("Location: " . BASE_URL . "owner/index.php"); exit;
                    case 'member': header("Location: " . BASE_URL . "member/index.php"); exit;
                }
            }
        } else {
            $error = 'Username atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login - Hanma Fitness Parking System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <script src="<?= BASE_URL ?>assets/js/sound-effect.js"></script>
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
            z-index: 20;
        }
        .btn-back-landing:hover {
            color: #b3121b;
            transform: translateX(-3px);
            box-shadow: 0 6px 18px rgba(0,0,0,.18);
        }
        /* Di layar HP, tombol back digeser sedikit dan .auth-wrapper
           sudah dikasih padding-top ekstra (lihat style.css) supaya
           tombol ini tidak menimpa konten .auth-side di bawahnya. */
        @media (max-width: 768px) {
            .btn-back-landing {
                top: 14px;
                left: 14px;
                font-size: .82rem;
                padding: 7px 14px;
            }
        }
    </style>
</head>
<body>
<a href="<?= BASE_URL ?>index.php" class="btn-back-landing">
    <i class="bi bi-arrow-left"></i> Kembali ke Beranda
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
            <h3>Selamat Datang 👋</h3>
            <p class="text-muted mb-4">Masuk untuk mengelola parkir Hanma Fitness Gym</p>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" placeholder="Masukkan username" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Masukkan password" required>
                </div>
                <button type="submit" class="btn btn-hanma w-100 py-2 mt-2">Masuk <i class="bi bi-box-arrow-in-right"></i></button>
            </form>
            <div class="text-center mt-4">
                <small class="text-muted">Belum punya akun? <a href="<?= BASE_URL ?>auth/register.php">Daftar di sini</a></small>
            </div>
        </div>
    </div>
</div>
</body>
</html>