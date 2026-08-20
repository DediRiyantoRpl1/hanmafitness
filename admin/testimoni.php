<?php
require_once __DIR__ . '/../config/koneksi.php';
cekLogin(['admin']); // hanya admin yang boleh akses halaman ini

$page_title = 'Moderasi Testimoni';

// Ambil semua komentar: pending dulu, lalu terbaru
$stmt = $koneksi->prepare(
    "SELECT id, nama, role, rating, komentar, status, created_at
     FROM testimoni
     ORDER BY FIELD(status, 'pending', 'approved', 'rejected'), created_at DESC"
);
$stmt->execute();
$semuaTestimoni = $stmt->fetchAll();

$jumlahPending = 0;
foreach ($semuaTestimoni as $t) {
    if ($t['status'] === 'pending') $jumlahPending++;
}

include __DIR__ . '/template/header.php';
?>

<style>
    .badge-status-pending { background: #ffc107; color: #222; }
    .badge-status-approved { background: #198754; }
    .badge-status-rejected { background: #6c757d; }
    .komentar-card {
        background: #fff;
        border-radius: 14px;
        padding: 22px;
        margin-bottom: 16px;
        box-shadow: 0 4px 14px rgba(0,0,0,.06);
        border: 1px solid rgba(0,0,0,.04);
    }
    .komentar-card .stars { color: #ff6a00; }
    .btn-approve { background: #198754; color: #fff; border: none; }
    .btn-approve:hover { background: #157347; color: #fff; }
    .btn-reject { background: #6c757d; color: #fff; border: none; }
    .btn-reject:hover { background: #5c636a; color: #fff; }
    .btn-hapus { background: #b3121b; color: #fff; border: none; }
    .btn-hapus:hover { background: #8f0e15; color: #fff; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h6 class="fw-bold mb-0 text-white">Daftar komentar &amp; rating dari pengguna landing page</h6>
    <?php if ($jumlahPending > 0): ?>
        <span class="badge bg-warning text-dark"><?= $jumlahPending ?> menunggu persetujuan</span>
    <?php endif; ?>
</div>

<?php if (empty($semuaTestimoni)): ?>
    <div class="text-center text-muted py-5">
        <i class="bi bi-inbox" style="font-size:2.5rem;"></i>
        <p class="mt-3">Belum ada komentar yang masuk.</p>
    </div>
<?php else: ?>
    <?php foreach ($semuaTestimoni as $t): ?>
        <div class="komentar-card">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <h6 class="fw-bold mb-1">
                        <?= htmlspecialchars($t['nama']) ?>
                        <span class="text-muted fw-normal">&mdash; <?= htmlspecialchars($t['role']) ?></span>
                    </h6>
                    <div class="stars mb-2">
                        <?php
                        $r = (int)$t['rating'];
                        for ($i = 1; $i <= 5; $i++) {
                            echo $i <= $r ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star"></i>';
                        }
                        ?>
                    </div>
                </div>
                <span class="badge badge-status-<?= $t['status'] ?> align-self-start">
                    <?= ucfirst($t['status']) ?>
                </span>
            </div>

            <p class="mb-2"><?= nl2br(htmlspecialchars($t['komentar'])) ?></p>
            <small class="text-muted d-block mb-3">
                Dikirim: <?= date('d M Y, H:i', strtotime($t['created_at'])) ?>
            </small>

            <div class="d-flex gap-2 flex-wrap">
                <?php if ($t['status'] !== 'approved'): ?>
                    <form action="<?= BASE_URL ?>admin/testimoni_action.php" method="POST">
                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                        <input type="hidden" name="aksi" value="approve">
                        <button type="submit" class="btn btn-sm btn-approve"><i class="bi bi-check-lg"></i> Setujui</button>
                    </form>
                <?php endif; ?>

                <?php if ($t['status'] !== 'rejected'): ?>
                    <form action="<?= BASE_URL ?>admin/testimoni_action.php" method="POST">
                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                        <input type="hidden" name="aksi" value="reject">
                        <button type="submit" class="btn btn-sm btn-reject"><i class="bi bi-x-lg"></i> Tolak</button>
                    </form>
                <?php endif; ?>

                <form action="<?= BASE_URL ?>admin/testimoni_action.php" method="POST"
                      onsubmit="return confirm('Hapus komentar ini secara permanen?');">
                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                    <input type="hidden" name="aksi" value="hapus">
                    <button type="submit" class="btn btn-sm btn-hapus"><i class="bi bi-trash"></i> Hapus</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php include __DIR__ . '/template/footer.php'; ?>