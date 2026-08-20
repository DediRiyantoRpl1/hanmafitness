<?php
require_once __DIR__ . '/../config/koneksi.php';
cekLogin(['admin']);
$page_title = 'Log Aktivitas';

// ==== HAPUS SEMUA LOG AKTIVITAS ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'hapus_semua') {
    $koneksi->exec("DELETE FROM tb_log_aktivitas");
    // Dicatat SETELAH proses hapus, jadi baris ini akan jadi log pertama yang baru
    catatLog($koneksi, $_SESSION['id_user'], "Menghapus seluruh riwayat log aktivitas");
    header("Location: log_aktivitas.php?sukses=Seluruh riwayat log aktivitas berhasil dihapus");
    exit;
}

$tanggal = $_GET['tanggal'] ?? '';
$sql = "SELECT l.*, u.nama_lengkap, u.role FROM tb_log_aktivitas l JOIN tb_user u ON u.id_user = l.id_user";
$params = [];
if ($tanggal !== '') {
    $sql .= " WHERE DATE(l.waktu_aktivitas) = ?";
    $params[] = $tanggal;
}
$sql .= " ORDER BY l.id_log DESC LIMIT 200";
$stmt = $koneksi->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

include __DIR__ . '/template/header.php';
?>

<div class="card card-hanma">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>Log Aktivitas Pengguna</span>
        <div class="d-flex gap-2 flex-wrap">
            <form method="GET" class="d-flex gap-2">
                <input type="date" name="tanggal" class="form-control form-control-sm" value="<?= htmlspecialchars($tanggal) ?>">
                <button class="btn btn-sm btn-hanma">Filter</button>
                <?php if ($tanggal !== ''): ?><a href="log_aktivitas.php" class="btn btn-sm btn-outline-secondary">Reset</a><?php endif; ?>
            </form>
            <?php if (!empty($logs)): ?>
                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalHapusSemua">
                    <i class="bi bi-trash"></i> Hapus Semua Log
                </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>#</th><th>Waktu</th><th>Nama</th><th>Role</th><th>Aktivitas</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $i => $l): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= date('d/m/Y H:i:s', strtotime($l['waktu_aktivitas'])) ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars($l['nama_lengkap']) ?></td>
                    <td><span class="badge bg-secondary text-uppercase"><?= $l['role'] ?></span></td>
                    <td><?= htmlspecialchars($l['aktivitas']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada log aktivitas</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Hapus Semua Log -->
<div class="modal fade" id="modalHapusSemua" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="aksi" value="hapus_semua">
            <div class="modal-header">
                <h5 class="modal-title">Hapus Semua Log Aktivitas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">
                    Apakah Anda yakin ingin menghapus <strong>seluruh riwayat log aktivitas</strong>?
                    Tindakan ini <strong>tidak dapat dibatalkan</strong> dan akan menghapus semua data log,
                    termasuk yang tersembunyi oleh filter tanggal saat ini.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger">Ya, Hapus Semua</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/template/footer.php'; ?>