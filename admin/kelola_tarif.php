<?php
require_once __DIR__ . '/../config/koneksi.php';
cekLogin(['admin']);
$page_title = 'Tarif Parkir';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['aksi'] === 'tambah') {
    $stmt = $koneksi->prepare("INSERT INTO tb_tarif (jenis_kendaraan, tarif_per_jam, denda_per_jam) VALUES (?, ?, ?)");
    $stmt->execute([$_POST['jenis_kendaraan'], $_POST['tarif_per_jam'], $_POST['denda_per_jam'] ?? 0]);
    catatLog($koneksi, $_SESSION['id_user'], "Menambahkan tarif jenis {$_POST['jenis_kendaraan']}");
    header("Location: kelola_tarif.php?sukses=Tarif berhasil ditambahkan"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['aksi'] === 'edit') {
    $stmt = $koneksi->prepare("UPDATE tb_tarif SET jenis_kendaraan=?, tarif_per_jam=?, denda_per_jam=? WHERE id_tarif=?");
    $stmt->execute([$_POST['jenis_kendaraan'], $_POST['tarif_per_jam'], $_POST['denda_per_jam'] ?? 0, $_POST['id_tarif']]);
    catatLog($koneksi, $_SESSION['id_user'], "Mengubah tarif ID {$_POST['id_tarif']}");
    header("Location: kelola_tarif.php?sukses=Tarif berhasil diperbarui"); exit;
}

if (isset($_GET['hapus'])) {
    $stmt = $koneksi->prepare("DELETE FROM tb_tarif WHERE id_tarif = ?");
    $stmt->execute([$_GET['hapus']]);
    catatLog($koneksi, $_SESSION['id_user'], "Menghapus tarif ID {$_GET['hapus']}");
    header("Location: kelola_tarif.php?sukses=Tarif berhasil dihapus"); exit;
}

$tarifs = $koneksi->query("SELECT * FROM tb_tarif ORDER BY jenis_kendaraan")->fetchAll();
include __DIR__ . '/template/header.php';
?>

<div class="alert alert-info py-2">
    <i class="bi bi-info-circle"></i> Denda dihitung otomatis saat kendaraan booking <strong>terlambat datang</strong> atau
    <strong>terlambat keluar</strong> dari jam yang dijadwalkan, setelah melewati toleransi
    <strong><?= TOLERANSI_TELAT_MENIT ?> menit</strong>, dibulatkan ke atas per jam keterlambatan.
</div>

<div class="card card-hanma">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Daftar Tarif Parkir</span>
        <button class="btn btn-hanma btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambah"><i class="bi bi-plus-lg"></i> Tambah Tarif</button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>#</th><th>Jenis Kendaraan</th><th>Tarif / Jam</th><th>Denda Telat / Jam</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($tarifs as $i => $t): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td class="text-capitalize fw-semibold"><?= $t['jenis_kendaraan'] ?></td>
                    <td><?= rupiah($t['tarif_per_jam']) ?></td>
                    <td class="text-danger"><?= rupiah($t['denda_per_jam']) ?></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $t['id_tarif'] ?>"><i class="bi bi-pencil"></i></button>
                        <a href="?hapus=<?= $t['id_tarif'] ?>" class="btn btn-sm btn-outline-danger btn-hapus-konfirmasi"><i class="bi bi-trash"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!--
    Modal Edit dipindahkan ke luar <table> (sebelumnya ada di dalam foreach
    yang masih di dalam <tbody>, yaitu HTML tidak valid: <div> tidak boleh
    jadi anak langsung <tbody>. Itu yang bikin modal tembus/rusak.
-->
<?php foreach ($tarifs as $t): ?>
<div class="modal fade" id="modalEdit<?= $t['id_tarif'] ?>" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="aksi" value="edit">
            <input type="hidden" name="id_tarif" value="<?= $t['id_tarif'] ?>">
            <div class="modal-header"><h5 class="modal-title">Edit Tarif</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Jenis Kendaraan</label>
                    <select name="jenis_kendaraan" class="form-select" required>
                        <option value="motor" <?= $t['jenis_kendaraan']==='motor'?'selected':'' ?>>Motor</option>
                        <option value="mobil" <?= $t['jenis_kendaraan']==='mobil'?'selected':'' ?>>Mobil</option>
                        <option value="lainnya" <?= $t['jenis_kendaraan']==='lainnya'?'selected':'' ?>>Lainnya</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tarif per Jam (Rp)</label>
                    <input type="number" name="tarif_per_jam" class="form-control" value="<?= $t['tarif_per_jam'] ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Denda Telat per Jam (Rp)</label>
                    <input type="number" name="denda_per_jam" class="form-control" value="<?= $t['denda_per_jam'] ?>" min="0">
                    <div class="form-text">Dikenakan jika kendaraan booking telat datang/keluar melebihi toleransi <?= TOLERANSI_TELAT_MENIT ?> menit.</div>
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
            <div class="modal-header"><h5 class="modal-title">Tambah Tarif</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Jenis Kendaraan</label>
                    <select name="jenis_kendaraan" class="form-select" required>
                        <option value="motor">Motor</option>
                        <option value="mobil">Mobil</option>
                        <option value="truk/bus">Truk/Bus</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tarif per Jam (Rp)</label>
                    <input type="number" name="tarif_per_jam" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Denda Telat per Jam (Rp)</label>
                    <input type="number" name="denda_per_jam" class="form-control" value="0" min="0">
                    <div class="form-text">Dikenakan jika kendaraan booking telat datang/keluar melebihi toleransi <?= TOLERANSI_TELAT_MENIT ?> menit.</div>
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