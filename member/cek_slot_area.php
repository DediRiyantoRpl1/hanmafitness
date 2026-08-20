<?php
require_once __DIR__ . '/../config/koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'member') {
    http_response_code(403);
    exit;
}

header('Content-Type: application/json');

$id_area         = $_GET['id_area'] ?? '';
$tanggal_booking = $_GET['tanggal_booking'] ?? '';

if ($id_area === '' || $tanggal_booking === '') {
    echo json_encode(['error' => 'Parameter tidak lengkap']);
    exit;
}

$stmtArea = $koneksi->prepare("SELECT nama_area, kapasitas, terisi FROM tb_area_parkir WHERE id_area = ?");
$stmtArea->execute([$id_area]);
$areaInfo = $stmtArea->fetch();

if (!$areaInfo) {
    echo json_encode(['error' => 'Area tidak ditemukan']);
    exit;
}

$kapasitas = (int) $areaInfo['kapasitas'];

// Booking lain (menunggu/dikonfirmasi) di tanggal yang sama ikut menahan slot
$stmtHitung = $koneksi->prepare(
    "SELECT COUNT(*) AS jumlah FROM tb_booking
     WHERE id_area = ? AND tanggal_booking = ? AND status IN ('menunggu','dikonfirmasi')"
);
$stmtHitung->execute([$id_area, $tanggal_booking]);
$jumlahTerpakai = (int) $stmtHitung->fetch()['jumlah'];

// Kalau tanggal yang dicek adalah HARI INI, kendaraan yang sedang fisik
// parkir (walk-in maupun booking yang sudah diproses masuk) juga ikut
// dihitung supaya sisa slot tidak dobel-jual dengan slot yang sudah
// ditempati saat ini.
if ($tanggal_booking === date('Y-m-d')) {
    $jumlahTerpakai += (int) $areaInfo['terisi'];
}

$sisa  = max(0, $kapasitas - $jumlahTerpakai);
$penuh = $jumlahTerpakai >= $kapasitas;

echo json_encode([
    'nama_area'  => $areaInfo['nama_area'],
    'kapasitas'  => $kapasitas,
    'terpakai'   => $jumlahTerpakai,
    'sisa'       => $sisa,
    'penuh'      => $penuh,
]);