<?php
require_once __DIR__ . '/../config/koneksi.php';
cekLogin(['owner']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id_parkir'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Permintaan tidak valid']);
    exit;
}

$id = (int) $_POST['id_parkir'];

// Soft delete: tandai dihapus, JANGAN hapus row-nya supaya total pendapatan tetap akurat
$stmt = $koneksi->prepare("UPDATE tb_transaksi SET dihapus = 1 WHERE id_parkir = ?");
$stmt->execute([$id]);

if ($stmt->rowCount() > 0) {
    echo json_encode(['status' => 'ok']);
} else {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Transaksi tidak ditemukan']);
}