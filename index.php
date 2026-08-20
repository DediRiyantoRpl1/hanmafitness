<?php
require_once __DIR__ . '/config/koneksi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ambil flash old input (jika ada pengajuan aktivasi yang gagal sebelumnya)
$oldAjukan = $_SESSION['old_ajukan'] ?? null;
unset($_SESSION['old_ajukan']); // sekali pakai

// Jika sudah login, langsung arahkan ke dashboard sesuai role
if (isset($_SESSION['id_user'])) {
    switch ($_SESSION['role']) {
        case 'admin': header("Location: " . BASE_URL . "admin/index.php"); exit;
        case 'petugas': header("Location: " . BASE_URL . "operator/index.php"); exit;
        case 'owner': header("Location: " . BASE_URL . "owner/index.php"); exit;
    }
}

// ===== Ambil testimoni yang sudah disetujui (approved) dari database =====
$daftarTestimoni = [];
try {
    $stmtTesti = $koneksi->prepare(
        "SELECT nama, role, rating, komentar, created_at
         FROM testimoni
         WHERE status = 'approved'
         ORDER BY created_at DESC
         LIMIT 6"
    );
    $stmtTesti->execute();
    $daftarTestimoni = $stmtTesti->fetchAll();
} catch (PDOException $e) {
    $daftarTestimoni = [];
}

// ===== Ambil data tren transaksi 7 hari terakhir untuk grafik =====
$labelTren = [];
$dataTren = [];

try {
    $stmtGrafik = $koneksi->prepare(
        "SELECT DATE(waktu_masuk) AS tanggal, COUNT(*) AS jumlah
         FROM tb_transaksi
         WHERE waktu_masuk >= (CURDATE() - INTERVAL 6 DAY)
         GROUP BY tanggal"
    );
    $stmtGrafik->execute();
    $hasilGrafik = $stmtGrafik->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    $hasilGrafik = [];
}

for ($i = 6; $i >= 0; $i--) {
    $tgl = date('Y-m-d', strtotime("-$i day"));
    $labelTren[] = date('d/m', strtotime($tgl));
    $dataTren[] = isset($hasilGrafik[$tgl]) ? (int) $hasilGrafik[$tgl] : 0;
}

// ===== Ambil data seluruh area parkir beserta sisa slotnya (real-time: kapasitas - terisi) =====
$daftarAreaLanding = [];
try {
    $stmtAreaLanding = $koneksi->query("SELECT * FROM tb_area_parkir ORDER BY id_area");
    $daftarAreaLanding = $stmtAreaLanding->fetchAll();
} catch (PDOException $e) {
    $daftarAreaLanding = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Hanma Fitness Parking System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <style>
        :root {
            --hanma-dark: #1a0505;
            --hanma-red: #b3121b;
            --hanma-orange: #ff6a00;
        }
        html {
            scroll-behavior: smooth;
        }
        body {
            margin: 0;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #f5f5f5;
            color: #222;
        }
        .navbar-hanma {
            background: linear-gradient(90deg, var(--hanma-dark), var(--hanma-red));
            padding: 16px 0;
            position: sticky;
            top: 0;
            z-index: 1030;
        }
        .navbar-hanma .brand {
            color: #fff;
            font-weight: 800;
            font-size: 1.3rem;
            letter-spacing: 1px;
        }
        .navbar-logo {
            height: 38px;
            width: auto;
            object-fit: contain;
        }
        .navbar-hanma .nav-link {
            color: rgba(255,255,255,.85);
            font-weight: 600;
            padding: 8px 16px !important;
        }
        .navbar-hanma .nav-link:hover,
        .navbar-hanma .nav-link.active {
            color: #fff;
        }
        .navbar-hanma .navbar-toggler {
            border-color: rgba(255,255,255,.4);
        }
        .navbar-hanma .navbar-toggler-icon {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
        .btn-hanma {
            background: linear-gradient(90deg, var(--hanma-red), var(--hanma-orange));
            color: #fff;
            border: none;
            font-weight: 600;
            border-radius: 8px;
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .btn-hanma:hover {
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(179,18,27,.35);
        }
        .btn-outline-hanma {
            border: 2px solid #fff;
            color: #fff;
            font-weight: 600;
            border-radius: 8px;
        }
        .btn-outline-hanma:hover {
            background: #fff;
            color: var(--hanma-red);
        }
        .hero {
            background:
                linear-gradient(135deg, rgba(58,10,10,.35), rgba(18,2,2,.45) 70%),
                url('<?= BASE_URL ?>img/hanma.jpg') center center / cover no-repeat;
            color: #fff;
            padding: 100px 0 120px;
            position: relative;
            overflow: hidden;
            scroll-margin-top: 90px;
        }
        .hero .badge-tag {
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.2);
            color: #fff;
            padding: 6px 16px;
            border-radius: 50px;
            font-size: .75rem;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .hero h1 {
            font-weight: 800;
            font-size: 3rem;
            margin: 20px 0;
            text-shadow: 0 2px 12px rgba(0,0,0,.55);
        }
        .hero h1 span {
            color: var(--hanma-orange);
        }
        .hero p.lead {
            color: rgba(255,255,255,.85);
            max-width: 560px;
            text-shadow: 0 1px 8px rgba(0,0,0,.5);
        }
        section[id] {
            scroll-margin-top: 90px;
        }
        .section-title {
            font-weight: 800;
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .section-sub {
            color: #6b6b6b;
            margin-bottom: 50px;
        }
        .feature-card {
            background: #fff;
            border-radius: 16px;
            padding: 32px 26px;
            height: 100%;
            box-shadow: 0 8px 24px rgba(0,0,0,.06);
            border: 1px solid rgba(0,0,0,.04);
            transition: transform .2s ease, box-shadow .2s ease;
            cursor: pointer;
        }
        .feature-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 14px 32px rgba(179,18,27,.12);
        }
        .feature-card .feature-more {
            font-size: .85rem;
            font-weight: 600;
            color: var(--hanma-red);
            margin-top: 14px;
            margin-bottom: 0;
        }
        .modal-content {
            border-radius: 18px;
            border: none;
        }
        .modal-header {
            background: linear-gradient(120deg, var(--hanma-dark), var(--hanma-red));
            color: #fff;
            border-radius: 18px 18px 0 0;
            border-bottom: none;
        }
        .modal-header .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
        .modal-icon-lg {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--hanma-red), var(--hanma-orange));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.8rem;
            margin-bottom: 18px;
        }
        .feature-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--hanma-red), var(--hanma-orange));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.5rem;
            margin-bottom: 18px;
        }
        .role-card {
            border-radius: 16px;
            padding: 28px;
            height: 100%;
            color: #fff;
            background: linear-gradient(160deg, #2a0808, #120202);
            border: 1px solid rgba(255,255,255,.06);
        }
        .role-card i {
            font-size: 2rem;
            color: var(--hanma-orange);
        }
        .video-parkir-wrap {
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0,0,0,.25);
            border: 1px solid rgba(0,0,0,.05);
            background: #000;
        }
        .video-parkir-wrap video {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
        }
        .video-img-wrap {
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0,0,0,.25);
            border: 1px solid rgba(0,0,0,.05);
            height: 100%;
        }
        .video-img-wrap img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
        }
        .grafik-card {
            background: #fff;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 8px 24px rgba(0,0,0,.06);
            border: 1px solid rgba(0,0,0,.04);
        }
        .grafik-card canvas {
            max-height: 320px;
        }
        .info-section {
            background: #fff;
        }
        .info-stat {
            text-align: center;
            padding: 20px;
        }
        .info-stat h3 {
            font-weight: 800;
            font-size: 2.2rem;
            color: var(--hanma-red);
            margin-bottom: 4px;
        }
        .info-stat p {
            color: #6b6b6b;
            margin: 0;
            font-weight: 600;
        }
        .info-box {
            background: #fff;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 8px 24px rgba(0,0,0,.06);
            border: 1px solid rgba(0,0,0,.04);
            height: 100%;
        }
        .info-box i {
            color: var(--hanma-red);
            font-size: 1.4rem;
            margin-right: 10px;
        }
        .info-box.info-box-clickable {
            cursor: pointer;
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .info-box.info-box-clickable:hover {
            transform: translateY(-6px);
            box-shadow: 0 14px 32px rgba(179,18,27,.12);
        }
        .help-float-btn {
            position: fixed;
            right: 24px;
            bottom: 24px;
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--hanma-red), var(--hanma-orange));
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            border: none;
            box-shadow: 0 10px 26px rgba(179,18,27,.4);
            z-index: 1040;
            animation: help-pulse 2.4s infinite;
        }
        .help-float-btn:hover {
            color: #fff;
        }
        @keyframes help-pulse {
            0% { box-shadow: 0 0 0 0 rgba(179,18,27,.45); }
            70% { box-shadow: 0 0 0 14px rgba(179,18,27,0); }
            100% { box-shadow: 0 0 0 0 rgba(179,18,27,0); }
        }
        #modalBantuan .accordion-button:not(.collapsed) {
            background: rgba(179,18,27,.08);
            color: var(--hanma-red);
            box-shadow: none;
        }
        #modalBantuan .accordion-button:focus {
            box-shadow: none;
        }
        .bantuan-contact-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 12px;
            background: #f7f7f7;
            margin-bottom: 10px;
            text-decoration: none;
            color: #222;
        }
        .bantuan-contact-item:hover {
            background: rgba(179,18,27,.08);
            color: var(--hanma-red);
        }
        .bantuan-contact-item i {
            font-size: 1.3rem;
            color: var(--hanma-red);
        }
        .testi-section {
            background: #f5f5f5;
        }
        .testi-card {
            background: #fff;
            border-radius: 16px;
            padding: 28px;
            height: 100%;
            box-shadow: 0 8px 24px rgba(0,0,0,.06);
            border: 1px solid rgba(0,0,0,.04);
        }
        .testi-card .stars {
            color: var(--hanma-orange);
            margin-bottom: 12px;
        }
        .testi-card p.testi-text {
            color: #444;
            font-style: italic;
            min-height: 90px;
        }
        .testi-user {
            display: flex;
            align-items: center;
            margin-top: 18px;
        }
        .testi-avatar {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--hanma-red), var(--hanma-orange));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            margin-right: 12px;
            flex-shrink: 0;
        }
        .testi-user h6 {
            margin: 0;
            font-weight: 700;
        }
        .testi-user small {
            color: #888;
        }
        .cta-section {
            background:
                linear-gradient(120deg, rgba(26,5,5,.85), rgba(179,18,27,.80)),
                url('<?= BASE_URL ?>img/barbel.jpg') center center / cover no-repeat;
            color: #fff;
            border-radius: 24px;
            padding: 60px 40px;
            margin: 20px 0 80px;
        }
        .cta-section .btn-hanma {
            background: linear-gradient(90deg, rgba(179,18,27,.75), rgba(255,106,0,.75));
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,.25);
        }
        footer {
            background: var(--hanma-dark);
            color: rgba(255,255,255,.6);
            padding: 30px 0;
            font-size: .9rem;
        }
        .rating-input {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 4px;
        }
        .rating-input input {
            display: none;
        }
        .rating-input label {
            font-size: 2rem;
            color: #ddd;
            cursor: pointer;
            transition: color .15s ease;
        }
        .rating-input input:checked ~ label,
        .rating-input label:hover,
        .rating-input label:hover ~ label {
            color: var(--hanma-orange);
        }
        .testi-card .stars i {
            font-size: .95rem;
        }
        .bantuan-contact-item.btn {
            width: 100%;
            border: none;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-hanma">
    <div class="container">
        <a class="brand navbar-brand d-flex align-items-center gap-2" href="#beranda">
            <img src="<?= BASE_URL ?>img/fitnes.png" alt="Logo Hanma Fitness" class="navbar-logo"
                 onerror="this.style.display='none'">
            HANMA FITNESS
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarHanma">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarHanma">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <li class="nav-item"><a class="nav-link" href="#beranda">Beranda</a></li>
                <li class="nav-item"><a class="nav-link" href="#fitur">Fitur Unggulan</a></li>
                <li class="nav-item"><a class="nav-link" href="#video-parkir">Tentang Parkir</a></li>
                <li class="nav-item"><a class="nav-link" href="#informasi">Informasi</a></li>
                <li class="nav-item"><a class="nav-link" href="#testimoni">Testimoni</a></li>
                <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                    <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-outline-hanma btn-sm px-4 py-2">
                        Login <i class="bi bi-box-arrow-in-right"></i>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<section class="hero" id="beranda">
    <div class="container">
        <div class="row align-items-start g-5">
            <div class="col-lg-9">
                <span class="badge-tag">GYM PARKING SYSTEM</span>
                <h1>Kelola Parkir <span>Hanma Fitness</span> Lebih Mudah &amp; Real-Time</h1>
                <p class="lead">
                    Sistem manajemen parkir untuk member &amp; tamu Hanma Fitness Gym.
                    Cepat, rapi, dan terpantau real-time untuk Admin, Petugas, dan Owner.
                </p>
                <div class="d-flex gap-3 mt-4">
                    <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-hanma px-4 py-2">
                        Masuk ke Sistem <i class="bi bi-arrow-right-circle"></i>
                    </a>
                    <a href="#fitur" class="btn btn-outline-hanma px-4 py-2">
                        Lihat Fitur
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="container py-5" id="fitur">
    <div class="text-center">
        <h2 class="section-title">Fitur Unggulan</h2>
        <p class="section-sub">Semua yang dibutuhkan untuk operasional parkir gym, dalam satu sistem.</p>
    </div>
    <div class="row g-4">
        <div class="col-md-4">
            <div class="feature-card" data-bs-toggle="modal" data-bs-target="#modalFitur1">
                <div class="feature-icon"><i class="bi bi-shield-check"></i></div>
                <h5 class="fw-bold">Akses Berbasis Peran</h5>
                <p class="text-muted mb-0">Role-based access untuk Admin, Petugas, dan Owner dengan hak akses masing-masing.</p>
                <p class="feature-more">Lihat detail <i class="bi bi-arrow-right"></i></p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="feature-card" data-bs-toggle="modal" data-bs-target="#modalFitur2">
                <div class="feature-icon"><i class="bi bi-p-circle"></i></div>
                <h5 class="fw-bold">Pantau Slot Real-Time</h5>
                <p class="text-muted mb-0">Ketahui ketersediaan area parkir secara langsung, kapan saja dibutuhkan.</p>
                <p class="feature-more">Lihat detail <i class="bi bi-arrow-right"></i></p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="feature-card" data-bs-toggle="modal" data-bs-target="#modalFitur3">
                <div class="feature-icon"><i class="bi bi-receipt"></i></div>
                <h5 class="fw-bold">Struk &amp; Rekap Otomatis</h5>
                <p class="text-muted mb-0">Cetak struk transaksi dan rekap laporan parkir secara otomatis.</p>
                <p class="feature-more">Lihat detail <i class="bi bi-arrow-right"></i></p>
            </div>
        </div>
    </div>
</section>

<!-- Modal Fitur 1: Akses Berbasis Peran -->
<div class="modal fade" id="modalFitur1" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Akses Berbasis Peran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="modal-icon-lg"><i class="bi bi-shield-check"></i></div>
                <p>Sistem membagi hak akses ke dalam tiga peran, masing-masing dengan tampilan dan kewenangan berbeda:</p>
                <ul>
                    <li><strong>Admin</strong> &mdash; kelola akun pengguna, master data, dan konfigurasi sistem.</li>
                    <li><strong>Petugas</strong> &mdash; proses transaksi parkir harian dan cetak struk.</li>
                    <li><strong>Owner</strong> &mdash; pantau laporan dan performa operasional secara keseluruhan.</li>
                </ul>
                <p class="mb-0 text-muted">Setiap login otomatis diarahkan ke dashboard sesuai peran, sehingga tidak ada akses yang tumpang tindih.</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal Fitur 2: Pantau Slot Real-Time -->
<div class="modal fade" id="modalFitur2" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Pantau Slot Real-Time</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="modal-icon-lg"><i class="bi bi-p-circle"></i></div>
                <p>Ketersediaan area parkir dapat dipantau secara langsung, sehingga petugas dan owner selalu tahu:</p>
                <ul>
                    <li>Jumlah slot yang masih kosong.</li>
                    <li>Kendaraan mana saja yang sedang parkir.</li>
                    <li>Estimasi kepadatan area parkir pada jam tertentu.</li>
                </ul>
                <p class="mb-0 text-muted">Membantu menghindari penumpukan kendaraan dan mempercepat pengambilan keputusan operasional.</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal Fitur 3: Struk & Rekap Otomatis -->
<div class="modal fade" id="modalFitur3" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Struk &amp; Rekap Otomatis</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="modal-icon-lg"><i class="bi bi-receipt"></i></div>
                <p>Setiap transaksi parkir tercatat otomatis dan dapat langsung dicetak dalam bentuk struk. Sistem juga menyediakan:</p>
                <ul>
                    <li>Rekap transaksi harian, mingguan, hingga bulanan.</li>
                    <li>Ringkasan pendapatan yang siap dilihat owner.</li>
                    <li>Riwayat transaksi yang mudah ditelusuri kembali.</li>
                </ul>
                <p class="mb-0 text-muted">Mengurangi pencatatan manual dan risiko kesalahan hitung.</p>
            </div>
        </div>
    </div>
</div>

<section class="container py-4">
    <div class="text-center">
        <h2 class="section-title">Dibuat untuk Setiap Peran</h2>
        <p class="section-sub">Tampilan dan hak akses disesuaikan dengan tanggung jawab masing-masing.</p>
    </div>
    <div class="row g-4">
        <div class="col-md-4">
            <div class="role-card">
                <i class="bi bi-person-gear"></i>
                <h5 class="fw-bold mt-3">Admin</h5>
                <p class="mb-0 opacity-75">Kelola akun pengguna, master data, dan konfigurasi sistem secara penuh.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="role-card">
                <i class="bi bi-person-badge"></i>
                <h5 class="fw-bold mt-3">Petugas</h5>
                <p class="mb-0 opacity-75">Proses transaksi parkir harian, cetak struk, dan input data kendaraan.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="role-card">
                <i class="bi bi-graph-up-arrow"></i>
                <h5 class="fw-bold mt-3">Owner</h5>
                <p class="mb-0 opacity-75">Pantau laporan, rekap pendapatan, dan performa operasional parkir.</p>
            </div>
        </div>
    </div>
</section>

<!-- Section Video + Gambar Area Parkir (sejajar) -->
<section class="container py-5" id="video-parkir">
    <div class="text-center mb-4">
        <h2 class="section-title">Lihat Area Parkir Kami</h2>
        <p class="section-sub">Tonton video singkat suasana dan lihat denah tata letak area parkir Hanma Fitness.</p>
    </div>
    <div class="row g-4 align-items-center">
        <div class="col-lg-6">
            <div class="ratio ratio-16x9 video-parkir-wrap">
                <video controls preload="metadata" poster="<?= BASE_URL ?>img/hanma.mp4">
                    <source src="<?= BASE_URL ?>img/hanma.mp4" type="video/mp4">
                    Maaf, browser Anda tidak mendukung pemutaran video. Anda dapat
                    <a href="<?= BASE_URL ?>video/area-parkir.mp4">mengunduh video di sini</a>.
                </video>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="ratio ratio-16x9 video-img-wrap">
                <img src="<?= BASE_URL ?>img/hanma parkir.png"
                     alt="Ilustrasi Parkir Hanma Fitness"
                     onerror="this.src='https://placehold.co/600x500/1a0505/ff6a00?text=Foto+Hanma+Fitness'">
            </div>
        </div>
    </div>
</section>

<!-- Section Informasi -->
<section class="info-section py-5" id="informasi">
    <div class="container">
        <div class="text-center">
            <h2 class="section-title">Informasi</h2>
            <p class="section-sub">Sekilas tentang layanan parkir Hanma Fitness.</p>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-6 col-md-3">
                <div class="info-stat">
                    <h3>24/7</h3>
                    <p>Pemantauan Real-Time</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="info-stat">
                    <h3>3</h3>
                    <p>Peran Pengguna</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="info-stat">
                    <h3>100%</h3>
                    <p>Struk Otomatis</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="info-stat">
                    <h3>0</h3>
                    <p>Pencatatan Manual</p>
                </div>
            </div>
        </div>

        <?php if (isset($_GET['pengajuan']) && $_GET['pengajuan'] === 'sukses'): ?>
            <div class="alert alert-success text-center" role="alert">
                Pengajuan aktivasi akun Anda berhasil dikirim. Admin akan meninjau dan menghubungi Anda melalui nomor HP yang didaftarkan.
            </div>
        <?php elseif (isset($_GET['pengajuan']) && $_GET['pengajuan'] === 'gagal'): ?>
            <div class="alert alert-danger text-center" role="alert">
                Pengajuan gagal dikirim. Pastikan semua data terisi dengan benar.
            </div>
        <?php elseif (isset($_GET['pengajuan']) && $_GET['pengajuan'] === 'duplikat'): ?>
            <div class="alert alert-warning text-center" role="alert">
                Akun Masih Belum Kena Nonaktif Untuk Itu Kami Tidak Bisa Memproses
            </div>
        <?php endif; ?>

        <div class="row g-4 mb-5">
            <div class="col-12">
                <div class="grafik-card">
                    <h6 class="fw-bold mb-1"><i class="bi bi-p-circle"></i> Ketersediaan Slot Area Parkir</h6>
                    <p class="text-muted small mb-3">Pantau langsung sisa slot tiap area parkir Hanma Fitness saat ini.</p>
                    <?php if (count($daftarAreaLanding) === 0): ?>
                        <p class="text-muted text-center py-3 mb-0">Data area parkir belum tersedia.</p>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($daftarAreaLanding as $al):
                                $kapasitasLanding = (int) $al['kapasitas'];
                                $slotLanding      = hitungSlotAreaHariIni($koneksi, $al['id_area'], $kapasitasLanding, $al['terisi']);
                                $terisiLanding    = $slotLanding['terpakai'];
                                $sisaLanding      = $slotLanding['sisa'];
                                $persenLanding    = $kapasitasLanding > 0 ? round(($terisiLanding / $kapasitasLanding) * 100) : 0;
                                $warnaLanding     = $persenLanding >= 90 ? 'bg-danger' : ($persenLanding >= 60 ? 'bg-warning' : 'bg-success');
                            ?>
                            <div class="col-md-4">
                                <div class="border rounded p-3 h-100 bg-white">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-semibold"><?= htmlspecialchars($al['nama_area']) ?></span>
                                        <?php if ($sisaLanding === 0): ?>
                                            <span class="badge bg-danger">Penuh</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Sisa <?= $sisaLanding ?> slot</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="progress mb-1" style="height:8px;">
                                        <div class="progress-bar <?= $warnaLanding ?>" style="width: <?= $persenLanding ?>%"></div>
                                    </div>
                                    <small class="text-muted">Terisi <?= $terisiLanding ?> dari <?= $kapasitasLanding ?> kapasitas</small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- Grafik Kepadatan Parkir per Jam                                -->
        <!-- Tampilan disamakan dengan grafik "Pendapatan 7 Hari Terakhir" -->
        <!-- di dashboard admin: line chart dengan area fill.               -->
        <!-- ============================================================= -->
        <div class="row g-4 mb-5">
            <div class="col-12">
                <div class="grafik-card">
                    <h6 class="fw-bold mb-1"><i class="bi bi-bar-chart-line"></i> Tren Transaksi 7 Hari Terakhir</h6>
                    <p class="text-muted small mb-3">Jumlah transaksi per hari, 7 hari terakhir.</p>
                    <div style="position: relative; height: 280px;">
                        <canvas id="chartTren"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="info-box">
                    <h6 class="fw-bold"><i class="bi bi-clock-history"></i>Jam Operasional</h6>
                    <p class="text-muted mb-0">Sistem parkir aktif mengikuti jam operasional Hanma Fitness Gym setiap hari.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-box">
                    <h6 class="fw-bold"><i class="bi bi-person-plus"></i>Akun Pengguna</h6>
                    <p class="text-muted mb-0">Belum punya akun? Ajukan aktivasi melalui menu Bantuan di bawah, Admin akan meninjaunya.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-box info-box-clickable" data-bs-toggle="modal" data-bs-target="#modalBantuan">
                    <h6 class="fw-bold"><i class="bi bi-headset"></i>Bantuan</h6>
                    <p class="text-muted mb-0">Kendala login atau transaksi dapat dilaporkan langsung ke Admin sistem.</p>
                    <p class="feature-more mb-0">Lihat FAQ &amp; kontak <i class="bi bi-arrow-right"></i></p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Section Testimoni -->
<section class="testi-section py-5" id="testimoni">
    <div class="container">
        <div class="text-center">
            <h2 class="section-title">Testimoni</h2>
            <p class="section-sub">Apa kata pengguna sistem parkir Hanma Fitness.</p>
            <button type="button" class="btn btn-hanma px-4 py-2 mb-4" data-bs-toggle="modal" data-bs-target="#modalTestimoni">
                <i class="bi bi-chat-left-text"></i> Tulis Komentar &amp; Rating
            </button>
        </div>

        <?php if (isset($_GET['testimoni']) && $_GET['testimoni'] === 'sukses'): ?>
            <div class="alert alert-success text-center" role="alert">
                Terima kasih! Komentar Anda sudah terkirim dan akan tampil setelah disetujui Admin.
            </div>
        <?php elseif (isset($_GET['testimoni']) && $_GET['testimoni'] === 'gagal'): ?>
            <div class="alert alert-danger text-center" role="alert">
                Gagal mengirim komentar. Pastikan nama, rating, dan komentar terisi dengan benar.
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <?php if (!empty($daftarTestimoni)): ?>
                <?php foreach ($daftarTestimoni as $t): ?>
                    <div class="col-md-4">
                        <div class="testi-card">
                            <div class="stars">
                                <?php
                                $r = (int)$t['rating'];
                                for ($i = 1; $i <= 5; $i++) {
                                    echo $i <= $r
                                        ? '<i class="bi bi-star-fill"></i>'
                                        : '<i class="bi bi-star"></i>';
                                }
                                ?>
                            </div>
                            <p class="testi-text">"<?= htmlspecialchars($t['komentar']) ?>"</p>
                            <div class="testi-user">
                                <div class="testi-avatar"><?= strtoupper(substr($t['nama'], 0, 1)) ?></div>
                                <div>
                                    <h6><?= htmlspecialchars($t['nama']) ?></h6>
                                    <small><?= htmlspecialchars($t['role']) ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-md-4">
                    <div class="testi-card">
                        <div class="stars">
                            <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                        </div>
                        <p class="testi-text">"Sejak pakai sistem ini, transaksi parkir jadi lebih cepat dan struk langsung tercetak otomatis."</p>
                        <div class="testi-user">
                            <div class="testi-avatar">R</div>
                            <div>
                                <h6>Rian</h6>
                                <small>Petugas Parkir</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testi-card">
                        <div class="stars">
                            <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                        </div>
                        <p class="testi-text">"Laporan pendapatan parkir bisa saya pantau kapan saja tanpa harus datang langsung ke lokasi."</p>
                        <div class="testi-user">
                            <div class="testi-avatar">D</div>
                            <div>
                                <h6>Coach Dedi</h6>
                                <small>Owner Hanma Fitness</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testi-card">
                        <div class="stars">
                            <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-half"></i>
                        </div>
                        <p class="testi-text">"Sebagai Admin, mengelola akun dan data pengguna jadi jauh lebih rapi dan terstruktur."</p>
                        <div class="testi-user">
                            <div class="testi-avatar">A</div>
                            <div>
                                <h6>Admin D</h6>
                                <small>Administrator</small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Modal Tulis Testimoni (Komentar & Rating) -->
<div class="modal fade" id="modalTestimoni" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-chat-left-text"></i> Tulis Komentar &amp; Rating</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>testimoni_submit.php" method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="inputNama" class="form-label fw-semibold">Nama</label>
                        <input type="text" class="form-control" id="inputNama" name="nama" maxlength="100" required>
                    </div>
                    <div class="mb-3">
                        <label for="inputRole" class="form-label fw-semibold">Peran / Status <span class="text-muted fw-normal">(opsional)</span></label>
                        <input type="text" class="form-control" id="inputRole" name="role" maxlength="50" placeholder="Contoh: Member Gym, Petugas, Owner">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold d-block">Rating</label>
                        <div class="rating-input">
                            <input type="radio" id="star5" name="rating" value="5" required><label for="star5" title="5 bintang"><i class="bi bi-star-fill"></i></label>
                            <input type="radio" id="star4" name="rating" value="4"><label for="star4" title="4 bintang"><i class="bi bi-star-fill"></i></label>
                            <input type="radio" id="star3" name="rating" value="3"><label for="star3" title="3 bintang"><i class="bi bi-star-fill"></i></label>
                            <input type="radio" id="star2" name="rating" value="2"><label for="star2" title="2 bintang"><i class="bi bi-star-fill"></i></label>
                            <input type="radio" id="star1" name="rating" value="1"><label for="star1" title="1 bintang"><i class="bi bi-star-fill"></i></label>
                        </div>
                    </div>
                    <div class="mb-1">
                        <label for="inputKomentar" class="form-label fw-semibold">Komentar</label>
                        <textarea class="form-control" id="inputKomentar" name="komentar" rows="4" maxlength="1000" required placeholder="Ceritakan pengalaman Anda menggunakan sistem parkir Hanma Fitness..."></textarea>
                    </div>
                    <p class="text-muted small mb-0">Komentar Anda akan ditinjau oleh Admin sebelum tampil di halaman ini.</p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-hanma px-4">Kirim <i class="bi bi-send"></i></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Ajukan Aktivasi Akun -->
<div class="modal fade" id="modalAjukanAktivasi" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus"></i> Ajukan Aktivasi Akun</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>ajukan_aktivasi_submit.php" method="POST">
                <div class="modal-body p-4">
                    <p class="text-muted small">
                        Isi form berikut untuk mengajukan akun yang dinonaktifkan. Pengajuan akan ditinjau oleh
                        Admin sebelum akun diaktifkan.
                    </p>
                    <div class="mb-3">
                        <label for="ajukanNama" class="form-label fw-semibold">Nama Lengkap</label>
                        <input type="text" class="form-control" id="ajukanNama" name="nama_lengkap" maxlength="100"
                               value="<?= htmlspecialchars($oldAjukan['namaLengkap'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="ajukanUsername" class="form-label fw-semibold">Username</label>
                        <input type="text" class="form-control" id="ajukanUsername" name="username" maxlength="50"
                               pattern="[a-zA-Z0-9_.]+" title="Hanya huruf, angka, titik, dan underscore"
                               value="<?= htmlspecialchars($oldAjukan['username'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="ajukanRole" class="form-label fw-semibold">Role Sebagai</label>
                        <select class="form-select" id="ajukanRole" name="role_diajukan" required>
                            <option value="" <?= empty($oldAjukan['roleDiajukan']) ? 'selected' : '' ?> disabled>-- Pilih Peran --</option>
                            <option value="member" <?= (($oldAjukan['roleDiajukan'] ?? '') === 'member') ? 'selected' : '' ?>>Member (Pengguna Gym)</option>
                            <option value="petugas" <?= (($oldAjukan['roleDiajukan'] ?? '') === 'petugas') ? 'selected' : '' ?>>Petugas (Operator Parkir)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="ajukanHp" class="form-label fw-semibold">No. HP / WhatsApp</label>
                        <input type="text" class="form-control" id="ajukanHp" name="no_hp" maxlength="20"
                               placeholder="08xxxxxxxxxx" value="<?= htmlspecialchars($oldAjukan['noHp'] ?? '') ?>">
                    </div>
                    <div class="mb-1">
                        <label for="ajukanAlasan" class="form-label fw-semibold">Alasan Pengajuan</label>
                        <textarea class="form-control" id="ajukanAlasan" name="alasan" rows="3" maxlength="500" required
                                  placeholder="Contoh: Min Akun kena nonaktif untuk itu tolong aktifkan akun saya."><?= htmlspecialchars($oldAjukan['alasan'] ?? '') ?></textarea>
                    </div>
                    <p class="text-muted small mb-0">Admin akan menghubungi Anda melalui WhatsApp/Email setelah pengajuan diproses.</p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-hanma px-4">Kirim Pengajuan <i class="bi bi-send"></i></button>
                </div>
            </form>
        </div>
    </div>
</div>

<section class="container">
    <div class="cta-section text-center">
        <h3 class="fw-bold mb-2">Siap mengelola parkir Hanma Fitness?</h3>
        <p class="opacity-75 mb-4">Masuk ke sistem untuk mulai memantau dan mengelola transaksi parkir.</p>
        <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-hanma px-5 py-2">
            Masuk Sekarang <i class="bi bi-box-arrow-in-right"></i>
        </a>
        <div class="mt-3">
            <small class="opacity-50">Akun Petugas/Owner hanya dapat dibuat oleh Administrator.</small>
        </div>
    </div>
</section>

<footer class="text-center">
    <div class="container">
        &copy; <?= date('Y') ?> Hanma Fitness Parking System. All rights reserved.
        <br>
        BY Dedi Riyanto
    </div>
</footer>

<!-- Modal Bantuan -->
<div class="modal fade" id="modalBantuan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-headset"></i> Pusat Bantuan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <h6 class="fw-bold mb-3">Pertanyaan yang Sering Diajukan</h6>
                <div class="accordion mb-4" id="accordionBantuan">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                Saya lupa password akun, bagaimana cara reset?
                            </button>
                        </h2>
                        <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#accordionBantuan">
                            <div class="accordion-body text-muted">
                                Reset password hanya dapat dilakukan oleh Administrator. Silakan hubungi Admin melalui kontak di bawah dengan menyertakan username akun Anda.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                Bagaimana cara membuat akun baru?
                            </button>
                        </h2>
                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#accordionBantuan">
                            <div class="accordion-body text-muted">
                                Akun untuk Admin, Petugas, maupun Owner hanya dapat dibuat oleh Administrator melalui menu kelola pengguna. Namun Anda dapat mengajukan permintaan aktivasi akun Member atau Petugas melalui tombol
                                <strong>"Ajukan Aktivasi Akun"</strong> di bawah, dan Admin akan meninjau pengajuan tersebut.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                Struk transaksi tidak tercetak, apa yang harus dilakukan?
                            </button>
                        </h2>
                        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#accordionBantuan">
                            <div class="accordion-body text-muted">
                                Periksa koneksi printer terlebih dahulu. Jika masih bermasalah, transaksi tetap tersimpan di sistem dan struk dapat dicetak ulang oleh Petugas melalui riwayat transaksi.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                Data slot parkir tidak update secara real-time?
                            </button>
                        </h2>
                        <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#accordionBantuan">
                            <div class="accordion-body text-muted">
                                Pastikan koneksi internet perangkat stabil, lalu muat ulang (refresh) halaman. Jika masalah berlanjut, laporkan ke Admin sistem.
                            </div>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold mb-3">Hubungi Kami</h6>
                <a href="https://wa.me/6288216158488?text=Halo%2C%20min%20saya%20perlu%20bantuan%20Anda" target="_blank" rel="noopener" class="bantuan-contact-item">
                    <i class="bi bi-whatsapp"></i>
                    <div>
                        <strong>WhatsApp Admin</strong>
                        <div class="small text-muted">Respon cepat untuk kendala teknis</div>
                    </div>
                </a>
                <a href="mailto:dedir8642@gmail.com" class="bantuan-contact-item">
                    <i class="bi bi-envelope"></i>
                    <div>
                        <strong>Email</strong>
                        <div class="small text-muted">dedir8642@gmail.com</div>
                    </div>
                </a>
                <div class="bantuan-contact-item" style="cursor:default;">
                    <i class="bi bi-geo-alt"></i>
                    <div>
                        <strong>Lokasi</strong>
                        <div class="small text-muted">Hanma Fitness Gym, area resepsionis</div>
                    </div>
                </div>

                <hr class="my-4">
                <h6 class="fw-bold mb-3">Pengajuan Akun Yang Dinonaktifkan</h6>
                <button type="button"
                        class="bantuan-contact-item btn text-start"
                        data-bs-toggle="modal"
                        data-bs-target="#modalAjukanAktivasi">
                    <i class="bi bi-person-plus"></i>
                    <div>
                        <strong>Ajukan Aktivasi Akun</strong>
                        <div class="small text-muted">Pengajuan akun yang dinonaktifkan Member atau Petugas, menunggu persetujuan Admin</div>
                    </div>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Tombol Bantuan Mengambang -->
<button type="button" class="help-float-btn" data-bs-toggle="modal" data-bs-target="#modalBantuan" title="Bantuan">
    <i class="bi bi-headset"></i>
</button>

<?php if (isset($_GET['testimoni']) || isset($_GET['pengajuan'])): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const targetId = <?= isset($_GET['pengajuan']) ? "'informasi'" : "'testimoni'" ?>;
        const el = document.getElementById(targetId);
        if (el) el.scrollIntoView({ behavior: 'instant', block: 'start' });

        <?php if (isset($_GET['modal']) && $_GET['modal'] === 'aktivasi'): ?>
        const modalAktivasiEl = document.getElementById('modalAjukanAktivasi');
        if (modalAktivasiEl) {
            const modalAktivasi = new bootstrap.Modal(modalAktivasiEl);
            modalAktivasi.show();
        }
        <?php endif; ?>
    });
</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
    // ================================================================
    // Grafik Tren Transaksi 7 Hari Terakhir — line chart, gaya sama
    // dengan grafik "Pendapatan 7 Hari Terakhir" di dashboard admin.
    // ================================================================
    const ctxTren = document.getElementById('chartTren');
    if (ctxTren) {
        new Chart(ctxTren, {
            type: 'line',
            data: {
                labels: <?= json_encode($labelTren) ?>,
                datasets: [{
                    label: 'Jumlah Transaksi',
                    data: <?= json_encode($dataTren) ?>,
                    borderColor: '#b3121b',
                    backgroundColor: 'rgba(179,18,27,0.12)',
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#b3121b',
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 400 },
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                }
            }
        });
    }
</script>
</body>
</html>