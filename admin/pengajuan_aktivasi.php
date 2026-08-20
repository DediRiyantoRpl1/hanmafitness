<?php
require_once __DIR__ . '/../config/koneksi.php';
cekLogin(['admin']); // hanya admin yang boleh akses halaman ini

$page_title = 'Pengajuan Aktivasi Akun';

// ===== Filter status (tab) =====
$filterStatus = $_GET['status'] ?? 'menunggu';
$statusValid  = in_array($filterStatus, ['menunggu', 'disetujui', 'ditolak', 'semua'], true) ? $filterStatus : 'menunggu';

if ($statusValid === 'semua') {
    $stmt = $koneksi->prepare(
        "SELECT * FROM tb_pengajuan_aktivasi
         ORDER BY FIELD(status, 'menunggu', 'disetujui', 'ditolak'), created_at DESC"
    );
    $stmt->execute();
} else {
    $stmt = $koneksi->prepare(
        "SELECT * FROM tb_pengajuan_aktivasi WHERE status = :status ORDER BY created_at DESC"
    );
    $stmt->execute([':status' => $statusValid]);
}
$daftarPengajuan = $stmt->fetchAll();

// Hitung total menunggu (untuk badge di atas, sama seperti badge sidebar)
$stmtHitung = $koneksi->query("SELECT COUNT(*) AS total FROM tb_pengajuan_aktivasi WHERE status = 'menunggu'");
$jumlahPending = (int) $stmtHitung->fetch()['total'];

// ===== Flash message dari proses_pengajuan.php =====
$flashSukses  = $_GET['sukses'] ?? null;   // 'setuju' | 'setuju_reaktivasi' | 'tolak'
$passwordBaru = $_GET['pw'] ?? null;
$flashGagal   = $_GET['gagal'] ?? null;

include __DIR__ . '/template/header.php';
?>

<style>
    .badge-status-menunggu { background: #ffc107; color: #222; }
    .badge-status-disetujui { background: #198754; }
    .badge-status-ditolak { background: #6c757d; }
    .pengajuan-card {
        background: #fff;
        border-radius: 14px;
        padding: 22px;
        margin-bottom: 16px;
        box-shadow: 0 4px 14px rgba(0,0,0,.06);
        border: 1px solid rgba(0,0,0,.04);
    }
    .pengajuan-card .role-pill {
        font-size: .75rem;
        font-weight: 600;
        padding: 3px 12px;
        border-radius: 50px;
        background: rgba(179,18,27,.08);
        color: #b3121b;
        text-transform: uppercase;
        letter-spacing: .5px;
    }
    .btn-approve { background: #198754; color: #fff; border: none; }
    .btn-approve:hover { background: #157347; color: #fff; }
    .btn-reject { background: #6c757d; color: #fff; border: none; }
    .btn-reject:hover { background: #5c636a; color: #fff; }
    .nav-pills-pengajuan {
        border-bottom: 1px solid rgba(255,255,255,.15);
        padding-bottom: 14px;
    }
    .nav-pills-pengajuan .nav-link {
        color: #495057;
        font-weight: 600;
        border-radius: 50px;
        padding: 6px 18px;
    }
    .nav-pills-pengajuan .nav-link.active {
        background: #0d6efd;
        color: #fff9f9;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h6 class="fw-bold mb-0 text-dark">Daftar pengajuan aktivasi akun dari landing page</h6>
    <?php if ($jumlahPending > 0): ?>
        <span class="badge bg-warning text-dark"><?= $jumlahPending ?> menunggu persetujuan</span>
    <?php endif; ?>
</div>

<?php if ($flashSukses === 'setuju'): ?>
    <div class="alert alert-success">
        Pengajuan disetujui &amp; akun baru berhasil dibuat.
        <?php if ($passwordBaru): ?>
            <br>Password sementara: <strong><?= htmlspecialchars($passwordBaru) ?></strong>
            — sampaikan ke pengguna dan minta segera diganti setelah login pertama.
        <?php endif; ?>
    </div>
<?php elseif ($flashSukses === 'setuju_reaktivasi'): ?>
    <div class="alert alert-success">
        Pengajuan disetujui &amp; akun berhasil diaktifkan kembali.
        Password lama tetap berlaku, pengguna bisa langsung login seperti biasa.
    </div>
<?php elseif ($flashSukses === 'tolak'): ?>
    <div class="alert alert-secondary">Pengajuan telah ditolak.</div>
<?php elseif ($flashGagal): ?>
    <div class="alert alert-danger">Gagal memproses pengajuan. Silakan coba lagi.</div>
<?php endif; ?>

<!-- Tab filter status -->
<ul class="nav nav-pills nav-pills-pengajuan mb-4">
    <?php
    $tabs = ['menunggu' => 'Menunggu', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak', 'semua' => 'Semua'];
    foreach ($tabs as $key => $label):
        $active = $statusValid === $key ? 'active' : '';
    ?>
        <li class="nav-item">
            <a class="nav-link <?= $active ?>" href="?status=<?= $key ?>"><?= $label ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if (empty($daftarPengajuan)): ?>
    <div class="text-center text-muted py-5">
        <i class="bi bi-inbox" style="font-size:2.5rem;"></i>
        <p class="mt-3">Belum ada pengajuan pada kategori ini.</p>
    </div>
<?php else: ?>
    <?php foreach ($daftarPengajuan as $p): ?>
        <div class="pengajuan-card">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <h6 class="fw-bold mb-1">
                        <?= htmlspecialchars($p['nama_lengkap']) ?>
                        <span class="text-muted fw-normal">&mdash; @<?= htmlspecialchars($p['username']) ?></span>
                    </h6>
                    <span class="role-pill"><?= htmlspecialchars(ucfirst($p['role_diajukan'])) ?></span>
                </div>
                <span class="badge badge-status-<?= $p['status'] ?> align-self-start">
                    <?= ucfirst($p['status']) ?>
                </span>
            </div>

            <p class="mb-1 mt-3"><?= nl2br(htmlspecialchars($p['alasan'])) ?></p>
            <?php if (!empty($p['no_hp'])): ?>
                <small class="text-muted d-block"><i class="bi bi-whatsapp"></i> <?= htmlspecialchars($p['no_hp']) ?></small>
            <?php endif; ?>
            <small class="text-muted d-block mb-3">
                Diajukan: <?= date('d M Y, H:i', strtotime($p['created_at'])) ?>
                <?php if ($p['diproses_at']): ?>
                    &middot; Diproses: <?= date('d M Y, H:i', strtotime($p['diproses_at'])) ?>
                <?php endif; ?>
            </small>

            <?php if (!empty($p['catatan_admin'])): ?>
                <div class="alert alert-light border small mb-3">
                    <strong>Catatan Admin:</strong> <?= htmlspecialchars($p['catatan_admin']) ?>
                </div>
            <?php endif; ?>

            <?php if ($p['status'] === 'menunggu'): ?>
                <div class="d-flex gap-2 flex-wrap">
                    <form action="<?= BASE_URL ?>admin/proses_pengajuan.php" method="POST"
                          onsubmit="return confirm('Setujui pengajuan ini untuk mengaktifkan akun @<?= htmlspecialchars($p['username']) ?>?');">
                        <input type="hidden" name="id_pengajuan" value="<?= (int) $p['id_pengajuan'] ?>">
                        <input type="hidden" name="aksi" value="setuju">
                        <button type="submit" class="btn btn-sm btn-approve"><i class="bi bi-check-lg"></i> Setujui</button>
                    </form>

                    <button type="button" class="btn btn-sm btn-reject" data-bs-toggle="modal"
                            data-bs-target="#modalTolak<?= (int) $p['id_pengajuan'] ?>">
                        <i class="bi bi-x-lg"></i> Tolak
                    </button>
                </div>

                <!-- Modal alasan tolak -->
                <div class="modal fade" id="modalTolak<?= (int) $p['id_pengajuan'] ?>" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form action="<?= BASE_URL ?>admin/proses_pengajuan.php" method="POST">
                                <div class="modal-header">
                                    <h6 class="modal-title">Tolak Pengajuan @<?= htmlspecialchars($p['username']) ?></h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="id_pengajuan" value="<?= (int) $p['id_pengajuan'] ?>">
                                    <input type="hidden" name="aksi" value="tolak">
                                    <label class="form-label">Catatan / Alasan Penolakan</label>
                                    <textarea name="catatan_admin" class="form-control" rows="3" required></textarea>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-reject">Tolak Pengajuan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php include __DIR__ . '/template/footer.php'; ?>