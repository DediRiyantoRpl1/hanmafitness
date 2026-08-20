<?php
require_once __DIR__ . '/../config/koneksi.php';
cekLogin(['admin']);
$page_title = 'Kelola User';

// ==== TAMBAH USER ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'tambah') {
    $nama = trim($_POST['nama_lengkap']);
    $username = trim($_POST['username']);
    $no_tlp = trim($_POST['no_tlp'] ?? '');
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    $cek = $koneksi->prepare("SELECT id_user FROM tb_user WHERE username = ?");
    $cek->execute([$username]);
    if ($cek->fetch()) {
        header("Location: kelola_user.php?gagal=Username sudah digunakan");
        exit;
    }

    $stmt = $koneksi->prepare("INSERT INTO tb_user (nama_lengkap, username, no_tlp, password, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$nama, $username, $no_tlp, $password, $role]);
    catatLog($koneksi, $_SESSION['id_user'], "Menambahkan user baru: $username ($role)");
    header("Location: kelola_user.php?sukses=User berhasil ditambahkan");
    exit;
}

// ==== EDIT USER ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'edit') {
    $id = $_POST['id_user'];
    $nama = trim($_POST['nama_lengkap']);
    $no_tlp = trim($_POST['no_tlp'] ?? '');
    $role = $_POST['role'];
    $status = isset($_POST['status_aktif']) ? 1 : 0;

    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $koneksi->prepare("UPDATE tb_user SET nama_lengkap=?, no_tlp=?, role=?, status_aktif=?, password=? WHERE id_user=?");
        $stmt->execute([$nama, $no_tlp, $role, $status, $password, $id]);
    } else {
        $stmt = $koneksi->prepare("UPDATE tb_user SET nama_lengkap=?, no_tlp=?, role=?, status_aktif=? WHERE id_user=?");
        $stmt->execute([$nama, $no_tlp, $role, $status, $id]);
    }
    catatLog($koneksi, $_SESSION['id_user'], "Mengubah data user ID $id");
    header("Location: kelola_user.php?sukses=Data user berhasil diperbarui");
    exit;
}

// ==== HAPUS USER ====
if (isset($_GET['hapus'])) {
    $id = $_GET['hapus'];

    if ((int)$id === (int)$_SESSION['id_user']) {
        header("Location: kelola_user.php?gagal=Tidak dapat menghapus akun sendiri");
        exit;
    }

    // Cek apakah user masih memiliki data booking terkait
    // (mencegah PDOException akibat foreign key constraint tb_booking_ibfk_1)
    $cekBooking = $koneksi->prepare("SELECT COUNT(*) FROM tb_booking WHERE id_user = ?");
    $cekBooking->execute([$id]);
    if ($cekBooking->fetchColumn() > 0) {
        header("Location: kelola_user.php?gagal=User tidak dapat dihapus karena masih memiliki riwayat booking");
        exit;
    }

    $stmt = $koneksi->prepare("DELETE FROM tb_user WHERE id_user = ?");
    $stmt->execute([$id]);
    catatLog($koneksi, $_SESSION['id_user'], "Menghapus user ID $id");
    header("Location: kelola_user.php?sukses=User berhasil dihapus");
    exit;
}

$users = $koneksi->query("SELECT * FROM tb_user ORDER BY role, nama_lengkap")->fetchAll();

include __DIR__ . '/template/header.php';
?>

<div class="card card-hanma">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Daftar User (Admin, Petugas, Owner, Member)</span>
        <button class="btn btn-hanma btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="bi bi-plus-lg"></i> Tambah User
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>#</th><th>Nama Lengkap</th><th>Username</th><th>No. Telepon</th><th>Role</th><th>Status</th><th>Dibuat</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($users as $i => $u): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars($u['nama_lengkap']) ?></td>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['no_tlp'] ?? '-') ?></td>
                    <td><span class="badge bg-secondary text-uppercase"><?= $u['role'] ?></span></td>
                    <td>
                        <?php if ($u['status_aktif']): ?>
                            <span class="badge bg-success">Aktif</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Nonaktif</span>
                        <?php endif; ?>
                    </td>
                    <td><small class="text-muted"><?= date('d/m/Y', strtotime($u['created_at'])) ?></small></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal"
                                data-bs-target="#modalEdit<?= $u['id_user'] ?>"><i class="bi bi-pencil"></i></button>
                        <a href="?hapus=<?= $u['id_user'] ?>" class="btn btn-sm btn-outline-danger btn-hapus-konfirmasi"><i class="bi bi-trash"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!--
    PENTING: Semua modal (Edit & Tambah) DIPINDAHKAN KE LUAR <table>.
    Sebelumnya modal Edit ada di dalam foreach yang masih berada di dalam
    <tbody>/<table>, sehingga <div class="modal"> jadi anak langsung dari
    <tbody> — ini HTML tidak valid. Browser otomatis "membuang" div itu
    keluar dari tabel dengan cara yang tidak terduga (foster parenting),
    sehingga modal jadi tidak full-overlay, kelihatan tembus/transparan,
    dan tombol di baliknya masih bisa diklik.
-->

<!-- Modal Edit (satu per user, tapi sudah di luar <table>) -->
<?php foreach ($users as $u): ?>
<div class="modal fade" id="modalEdit<?= $u['id_user'] ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form method="POST" class="modal-content">
            <input type="hidden" name="aksi" value="edit">
            <input type="hidden" name="id_user" value="<?= $u['id_user'] ?>">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($u['nama_lengkap']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($u['username']) ?>" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label">No. Telepon</label>
                    <input type="text" name="no_tlp" class="form-control" value="<?= htmlspecialchars($u['no_tlp'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Password Baru (kosongkan jika tidak diubah)</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••">
                </div>
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select" required>
                        <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="petugas" <?= $u['role'] === 'petugas' ? 'selected' : '' ?>>Petugas</option>
                        <option value="owner" <?= $u['role'] === 'owner' ? 'selected' : '' ?>>Owner</option>
                        <option value="member" <?= $u['role'] === 'member' ? 'selected' : '' ?>>Member</option>
                    </select>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="status_aktif" <?= $u['status_aktif'] ? 'checked' : '' ?>>
                    <label class="form-check-label">Akun Aktif</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-hanma">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form method="POST" class="modal-content">
            <input type="hidden" name="aksi" value="tambah">
            <div class="modal-header">
                <h5 class="modal-title">Tambah User Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">No. Telepon</label>
                    <input type="text" name="no_tlp" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select" required>
                        <option value="admin">Admin</option>
                        <option value="petugas" selected>Petugas</option>
                        <option value="owner">Owner</option>
                        <option value="member">Member</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-hanma">Simpan</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/template/footer.php'; ?>