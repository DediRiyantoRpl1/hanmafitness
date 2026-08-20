<?php
require_once __DIR__ . '/config/koneksi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hanya izinkan method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$namaLengkap  = trim($_POST['nama_lengkap'] ?? '');
$username     = trim($_POST['username'] ?? '');
$roleDiajukan = trim($_POST['role_diajukan'] ?? '');
$noHp         = trim($_POST['no_hp'] ?? '');
$alasan       = trim($_POST['alasan'] ?? '');

// Simpan old input ke session, dipakai index.php untuk repopulate form jika gagal
$_SESSION['old_ajukan'] = [
    'namaLengkap'  => $namaLengkap,
    'username'     => $username,
    'roleDiajukan' => $roleDiajukan,
    'noHp'         => $noHp,
    'alasan'       => $alasan,
];

// ===== Validasi dasar =====
$roleValid = in_array($roleDiajukan, ['member', 'petugas'], true);

if ($namaLengkap === '' || $username === '' || $alasan === '' || !$roleValid) {
    header("Location: " . BASE_URL . "index.php?pengajuan=gagal&modal=aktivasi#informasi");
    exit;
}

// Validasi format username (huruf, angka, titik, underscore saja)
if (!preg_match('/^[a-zA-Z0-9_.]+$/', $username)) {
    header("Location: " . BASE_URL . "index.php?pengajuan=gagal&modal=aktivasi#informasi");
    exit;
}

// ===== Validasi panjang input (jaga-jaga jika maxlength di HTML di-bypass) =====
if (
    mb_strlen($namaLengkap) > 100 ||
    mb_strlen($username) > 50 ||
    mb_strlen($noHp) > 20 ||
    mb_strlen($alasan) > 500
) {
    header("Location: " . BASE_URL . "index.php?pengajuan=gagal&modal=aktivasi#informasi");
    exit;
}

try {
    // ===== Cek duplikasi username (pre-check untuk UX, bukan satu-satunya proteksi) =====
    // 1) Sudah dipakai user yang MASIH AKTIF di tb_user.
    //    Akun yang statusnya dinonaktifkan (status_aktif = 0) TIDAK dianggap bentrok,
    //    supaya pemilik akun nonaktif tetap bisa mengajukan aktivasi ulang.
    $stmtCekUser = $koneksi->prepare(
        "SELECT id_user FROM tb_user WHERE username = :username AND status_aktif = 1 LIMIT 1"
    );
    $stmtCekUser->execute([':username' => $username]);

    // 2) Masih ada pengajuan lain dengan username sama yang berstatus 'menunggu'
    $stmtCekPengajuan = $koneksi->prepare(
        "SELECT id_pengajuan FROM tb_pengajuan_aktivasi
         WHERE username = :username AND status = 'menunggu' LIMIT 1"
    );
    $stmtCekPengajuan->execute([':username' => $username]);

    if ($stmtCekUser->fetch() || $stmtCekPengajuan->fetch()) {
        header("Location: " . BASE_URL . "index.php?pengajuan=duplikat&modal=aktivasi#informasi");
        exit;
    }

    // ===== Simpan pengajuan =====
    // Catatan: pre-check di atas hanya mempercepat feedback ke user, bukan jaminan mutlak,
    // karena ada celah waktu antara SELECT dan INSERT (race condition) jika dua
    // pengajuan dengan username sama dikirim nyaris bersamaan.
    $stmtInsert = $koneksi->prepare(
        "INSERT INTO tb_pengajuan_aktivasi
            (nama_lengkap, username, role_diajukan, no_hp, alasan, status, created_at)
         VALUES
            (:nama_lengkap, :username, :role_diajukan, :no_hp, :alasan, 'menunggu', NOW())"
    );
    $stmtInsert->execute([
        ':nama_lengkap'  => $namaLengkap,
        ':username'      => $username,
        ':role_diajukan' => $roleDiajukan,
        ':no_hp'         => $noHp !== '' ? $noHp : null,
        ':alasan'        => $alasan,
    ]);

    // Sukses, hapus flash old input
    unset($_SESSION['old_ajukan']);

    header("Location: " . BASE_URL . "index.php?pengajuan=sukses#informasi");
    exit;

} catch (PDOException $e) {
    // Kode error 23000 = pelanggaran constraint (misalnya UNIQUE username,
    // jika suatu saat ditambahkan di tb_pengajuan_aktivasi).
    if ($e->getCode() === '23000') {
        header("Location: " . BASE_URL . "index.php?pengajuan=duplikat&modal=aktivasi#informasi");
        exit;
    }

    // Log error asli untuk keperluan debugging (tidak ditampilkan ke user).
    error_log('[ajukan_aktivasi_submit] ' . $e->getMessage());

    header("Location: " . BASE_URL . "index.php?pengajuan=gagal&modal=aktivasi#informasi");
    exit;
}