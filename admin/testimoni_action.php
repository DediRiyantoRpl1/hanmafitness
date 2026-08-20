<?php
require_once __DIR__ . '/../config/koneksi.php';
cekLogin(['admin']); // hanya admin yang boleh menjalankan aksi ini

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "admin/testimoni.php");
    exit;
}

$id   = (int)($_POST['id'] ?? 0);
$aksi = $_POST['aksi'] ?? '';

if ($id <= 0 || !in_array($aksi, ['approve', 'reject', 'hapus'], true)) {
    header("Location: " . BASE_URL . "admin/testimoni.php?gagal=" . urlencode("Aksi tidak valid."));
    exit;
}

try {
    if ($aksi === 'hapus') {
        $stmt = $koneksi->prepare("DELETE FROM testimoni WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $pesan = "Komentar berhasil dihapus.";
    } else {
        $statusBaru = $aksi === 'approve' ? 'approved' : 'rejected';
        $stmt = $koneksi->prepare("UPDATE testimoni SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $statusBaru, ':id' => $id]);
        $pesan = $aksi === 'approve'
            ? "Komentar disetujui dan sekarang tampil di landing page."
            : "Komentar ditolak.";
    }

    // Catat aktivitas admin (fungsi ini sudah ada di koneksi.php)
    if (isset($_SESSION['id_user'])) {
        catatLog($koneksi, $_SESSION['id_user'], "Moderasi testimoni #$id: $aksi");
    }

    header("Location: " . BASE_URL . "admin/testimoni.php?sukses=" . urlencode($pesan));
} catch (PDOException $e) {
    header("Location: " . BASE_URL . "admin/testimoni.php?gagal=" . urlencode("Gagal memproses komentar."));
}
exit;