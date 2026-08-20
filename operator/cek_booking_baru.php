<?php
require_once __DIR__ . '/../config/koneksi.php';

if (!isset($_SESSION['id_user']) || !in_array($_SESSION['role'], ['petugas', 'admin'])) {
    http_response_code(403);
    exit;
}

header('Content-Type: application/json');

$stmt = $koneksi->query("SELECT COUNT(*) AS jumlah, MAX(id_booking) AS id_terbaru FROM tb_booking WHERE status = 'menunggu'");
$data = $stmt->fetch();

echo json_encode([
    'jumlah_menunggu' => (int)$data['jumlah'],
    'id_terbaru' => $data['id_terbaru'] ? (int)$data['id_terbaru'] : 0,
]);