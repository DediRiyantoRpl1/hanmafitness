<?php
require_once __DIR__ . '/../config/koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'member') {
    http_response_code(403);
    exit;
}

header('Content-Type: application/json');

$stmt = $koneksi->prepare("SELECT id_booking, status FROM tb_booking WHERE id_user = ?");
$stmt->execute([$_SESSION['id_user']]);
echo json_encode($stmt->fetchAll());