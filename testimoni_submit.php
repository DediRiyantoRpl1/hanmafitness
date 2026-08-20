<?php
require_once __DIR__ . '/config/koneksi.php';

// Handler untuk menyimpan komentar & rating dari pengguna publik (landing page).
// Menggunakan koneksi PDO ($koneksi) dari config/koneksi.php.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$nama     = trim($_POST['nama'] ?? '');
$role     = trim($_POST['role'] ?? '');
$rating   = (int)($_POST['rating'] ?? 0);
$komentar = trim($_POST['komentar'] ?? '');

// Validasi sederhana
$valid = true;
if ($nama === '' || mb_strlen($nama) > 100) {
    $valid = false;
}
if ($role === '') {
    $role = 'Pengguna';
}
if ($rating < 1 || $rating > 5) {
    $valid = false;
}
if ($komentar === '' || mb_strlen($komentar) > 1000) {
    $valid = false;
}

if (!$valid) {
    header("Location: " . BASE_URL . "index.php?testimoni=gagal#testimoni");
    exit;
}

try {
    // Simpan pakai prepared statement (mencegah SQL injection)
    $stmt = $koneksi->prepare(
        "INSERT INTO testimoni (nama, role, rating, komentar, status)
         VALUES (:nama, :role, :rating, :komentar, 'pending')"
    );
    $stmt->execute([
        ':nama'     => $nama,
        ':role'     => $role,
        ':rating'   => $rating,
        ':komentar' => $komentar,
    ]);

    // status default 'pending' -> perlu disetujui Admin dulu sebelum tampil publik
    header("Location: " . BASE_URL . "index.php?testimoni=sukses#testimoni");
} catch (PDOException $e) {
    header("Location: " . BASE_URL . "index.php?testimoni=gagal#testimoni");
}
exit;