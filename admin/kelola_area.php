<?php
require_once __DIR__ . '/../config/koneksi.php';
cekLogin(['admin']);
$page_title = 'Area Parkir';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['aksi'] === 'tambah') {
    $stmt = $koneksi->prepare("INSERT INTO tb_area_parkir (nama_area, kapasitas, terisi) VALUES (?, ?, 0)");
    $stmt->execute([$_POST['nama_area'], $_POST['kapasitas']]);
    catatLog($koneksi, $_SESSION['id_user'], "Menambahkan area parkir {$_POST['nama_area']}");
    header("Location: kelola_area.php?sukses=Area berhasil ditambahkan"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['aksi'] === 'edit') {
    // Kapasitas tidak boleh diturunkan sampai di bawah jumlah kendaraan yang
    // sedang fisik terisi saat ini, supaya "sisa slot" tidak jadi minus.
    $stmtCekTerisi = $koneksi->prepare("SELECT terisi FROM tb_area_parkir WHERE id_area = ?");
    $stmtCekTerisi->execute([$_POST['id_area']]);
    $terisiSekarang = (int) ($stmtCekTerisi->fetch()['terisi'] ?? 0);

    if ((int) $_POST['kapasitas'] < $terisiSekarang) {
        header("Location: kelola_area.php?gagal=Kapasitas tidak boleh kurang dari jumlah kendaraan yang sedang terisi ($terisiSekarang)"); exit;
    }

    $stmt = $koneksi->prepare("UPDATE tb_area_parkir SET nama_area=?, kapasitas=? WHERE id_area=?");
    $stmt->execute([$_POST['nama_area'], $_POST['kapasitas'], $_POST['id_area']]);
    catatLog($koneksi, $_SESSION['id_user'], "Mengubah area parkir ID {$_POST['id_area']}");
    header("Location: kelola_area.php?sukses=Area berhasil diperbarui"); exit;
}

if (isset($_GET['hapus'])) {
    $stmt = $koneksi->prepare("DELETE FROM tb_area_parkir WHERE id_area = ?");
    $stmt->execute([$_GET['hapus']]);
    catatLog($koneksi, $_SESSION['id_user'], "Menghapus area parkir ID {$_GET['hapus']}");
    header("Location: kelola_area.php?sukses=Area berhasil dihapus"); exit;
}

$areas = $koneksi->query("SELECT * FROM tb_area_parkir ORDER BY id_area")->fetchAll();
include __DIR__ . '/template/header.php';
?>

<div class="card card-hanma">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Daftar Area Parkir</span>
        <button class="btn btn-hanma btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambah"><i class="bi bi-plus-lg"></i> Tambah Area</button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>#</th><th>Nama Area</th><th>Kapasitas</th><th>Terisi</th><th>Sisa Slot</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($areas as $i => $a): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars($a['nama_area']) ?></td>
                    <td><?= $a['kapasitas'] ?></td>
                    <td><?= $a['terisi'] ?></td>
                    <?php $sisaAdmin = max(0, $a['kapasitas'] - $a['terisi']); ?>
                    <td><span class="badge bg-<?= $sisaAdmin <= 0 ? 'danger' : 'success' ?>"><?= $sisaAdmin ?> slot</span></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $a['id_area'] ?>"><i class="bi bi-pencil"></i></button>
                        <a href="?hapus=<?= $a['id_area'] ?>" class="btn btn-sm btn-outline-danger btn-hapus-konfirmasi"><i class="bi bi-trash"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!--
    Modal Edit dipindahkan ke luar <table> (sebelumnya di dalam foreach yang
    masih di dalam <tbody> — HTML tidak valid, penyebab modal tembus/rusak).
-->
<?php foreach ($areas as $a): ?>
<div class="modal fade" id="modalEdit<?= $a['id_area'] ?>" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="aksi" value="edit">
            <input type="hidden" name="id_area" value="<?= $a['id_area'] ?>">
            <div class="modal-header"><h5 class="modal-title">Edit Area Parkir</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nama Area</label>
                    <input type="text" name="nama_area" class="form-control" value="<?= htmlspecialchars($a['nama_area']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Kapasitas</label>
                    <input type="number" name="kapasitas" class="form-control" value="<?= $a['kapasitas'] ?>" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-hanma">Simpan</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="aksi" value="tambah">
            <div class="modal-header"><h5 class="modal-title">Tambah Area Parkir</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nama Area</label>
                    <input type="text" name="nama_area" class="form-control" placeholder="Contoh: Area Depan (Motor)" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Kapasitas</label>
                    <input type="number" name="kapasitas" class="form-control" required>
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