<?php
require_once __DIR__ . '/../config/koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'member') {
    header("Location: " . BASE_URL . "auth/login.php?err=akses_ditolak");
    exit;
}

$id_user = $_SESSION['id_user'];
$error = '';
$success = '';

// ==== TAMBAH KENDARAAN ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'tambah') {
    $plat_nomor      = strtoupper(trim($_POST['plat_nomor'] ?? ''));
    $jenis_kendaraan = trim($_POST['jenis_kendaraan'] ?? '');
    $warna           = trim($_POST['warna'] ?? '');
    $pemilik         = trim($_POST['pemilik'] ?? '');

    if ($plat_nomor === '' || $jenis_kendaraan === '' || $pemilik === '') {
        $error = 'Plat nomor, jenis kendaraan, dan nama pemilik wajib diisi.';
    } else {
        // Cek duplikat plat nomor
        $cek = $koneksi->prepare("SELECT id_kendaraan FROM tb_kendaraan WHERE plat_nomor = ?");
        $cek->execute([$plat_nomor]);

        if ($cek->fetch()) {
            $error = 'Plat nomor tersebut sudah terdaftar.';
        } else {
            $stmt = $koneksi->prepare(
                "INSERT INTO tb_kendaraan (plat_nomor, jenis_kendaraan, warna, pemilik, id_user)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$plat_nomor, $jenis_kendaraan, $warna ?: null, $pemilik, $id_user]);
            header("Location: kendaraan.php?sukses=Kendaraan berhasil ditambahkan");
            exit;
        }
    }
}

// ==== EDIT KENDARAAN ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'edit') {
    $id_kendaraan    = $_POST['id_kendaraan'] ?? '';
    $plat_nomor      = strtoupper(trim($_POST['plat_nomor'] ?? ''));
    $jenis_kendaraan = trim($_POST['jenis_kendaraan'] ?? '');
    $warna           = trim($_POST['warna'] ?? '');
    $pemilik         = trim($_POST['pemilik'] ?? '');

    if ($plat_nomor === '' || $jenis_kendaraan === '' || $pemilik === '') {
        $error = 'Plat nomor, jenis kendaraan, dan nama pemilik wajib diisi.';
    } else {
        $cekMilik = $koneksi->prepare("SELECT id_kendaraan FROM tb_kendaraan WHERE id_kendaraan = ? AND id_user = ?");
        $cekMilik->execute([$id_kendaraan, $id_user]);

        if (!$cekMilik->fetch()) {
            $error = 'Kendaraan tidak ditemukan.';
        } else {
            $cekDup = $koneksi->prepare("SELECT id_kendaraan FROM tb_kendaraan WHERE plat_nomor = ? AND id_kendaraan != ?");
            $cekDup->execute([$plat_nomor, $id_kendaraan]);

            if ($cekDup->fetch()) {
                $error = 'Plat nomor tersebut sudah terdaftar.';
            } else {
                $stmt = $koneksi->prepare(
                    "UPDATE tb_kendaraan SET plat_nomor = ?, jenis_kendaraan = ?, warna = ?, pemilik = ?
                     WHERE id_kendaraan = ? AND id_user = ?"
                );
                $stmt->execute([$plat_nomor, $jenis_kendaraan, $warna ?: null, $pemilik, $id_kendaraan, $id_user]);
                header("Location: kendaraan.php?sukses=Data kendaraan berhasil diperbarui");
                exit;
            }
        }
    }
}

// ==== HAPUS KENDARAAN ====
if (isset($_GET['hapus'])) {
    $id_kendaraan = $_GET['hapus'];

    // Cegah hapus kalau masih dipakai di booking aktif
    $cekBooking = $koneksi->prepare(
        "SELECT id_booking FROM tb_booking WHERE id_kendaraan = ? AND status IN ('menunggu', 'dikonfirmasi')"
    );
    $cekBooking->execute([$id_kendaraan]);

    if ($cekBooking->fetch()) {
        header("Location: kendaraan.php?gagal=Kendaraan tidak dapat dihapus karena masih memiliki booking aktif");
        exit;
    }

    $stmt = $koneksi->prepare("DELETE FROM tb_kendaraan WHERE id_kendaraan = ? AND id_user = ?");
    $stmt->execute([$id_kendaraan, $id_user]);
    header("Location: kendaraan.php?sukses=Kendaraan berhasil dihapus");
    exit;
}

if (isset($_GET['sukses'])) $success = $_GET['sukses'];
if (isset($_GET['gagal'])) $error = $_GET['gagal'];

$stmtKendaraan = $koneksi->prepare("SELECT * FROM tb_kendaraan WHERE id_user = ? ORDER BY plat_nomor");
$stmtKendaraan->execute([$id_user]);
$daftarKendaraan = $stmtKendaraan->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kendaraan Saya - Hanma Fitness Parking System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark" style="background-color:#0d1b2a;">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>member/index.php">
            <i class="bi bi-p-circle"></i> HANMA FITNESS PARKING
        </a>
        <div class="d-flex align-items-center">
            <a href="<?= BASE_URL ?>member/index.php" class="btn btn-outline-light btn-sm me-2">
                <i class="bi bi-house"></i> Dashboard
            </a>
            <span class="text-light me-3">
                <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['nama_lengkap']) ?>
            </span>
            <a href="<?= BASE_URL ?>auth/logout.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-box-arrow-right"></i> Keluar
            </a>
        </div>
    </div>
</nav>

<div class="container py-4">

    <h4 class="mb-1"><i class="bi bi-car-front"></i> Kendaraan Saya</h4>
    <p class="text-muted mb-4">Kelola data kendaraan anda agar dapat melakukan booking parkir.</p>

    <?php if ($error): ?>
        <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-plus-circle"></i> Tambah Kendaraan
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="aksi" value="tambah">
                        <div class="mb-3">
                            <label class="form-label">Plat Nomor</label>
                            <input type="text" name="plat_nomor" class="form-control text-uppercase"
                                   placeholder="Contoh: B 1234 XYZ" maxlength="15" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jenis Kendaraan</label>
                            <select name="jenis_kendaraan" class="form-select" required>
                                <option value="" selected disabled>Pilih jenis</option>
                                <option value="Motor">Motor</option>
                                <option value="Mobil">Mobil</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Warna (opsional)</label>
                            <input type="text" name="warna" class="form-control" maxlength="20">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Pemilik</label>
                            <input type="text" name="pemilik" class="form-control" maxlength="100" required>
                        </div>
                        <button type="submit" class="btn btn-hanma w-100">
                            <i class="bi bi-check-circle"></i> Simpan Kendaraan
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-list-check"></i> Daftar Kendaraan Terdaftar
                </div>
                <div class="card-body p-0">
                    <?php if (count($daftarKendaraan) === 0): ?>
                        <p class="text-muted text-center py-4 mb-0">Belum ada kendaraan yang terdaftar.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Plat Nomor</th>
                                    <th>Jenis</th>
                                    <th>Warna</th>
                                    <th>Pemilik</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($daftarKendaraan as $k): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($k['plat_nomor']) ?></td>
                                    <td><?= htmlspecialchars($k['jenis_kendaraan']) ?></td>
                                    <td><?= htmlspecialchars($k['warna'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($k['pemilik']) ?></td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalEdit<?= $k['id_kendaraan'] ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <a href="?hapus=<?= $k['id_kendaraan'] ?>"
                                           class="btn btn-sm btn-outline-danger btn-hapus-konfirmasi">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>

                                <!-- Modal Edit -->
                                <div class="modal fade" id="modalEdit<?= $k['id_kendaraan'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <input type="hidden" name="aksi" value="edit">
                                                <input type="hidden" name="id_kendaraan" value="<?= $k['id_kendaraan'] ?>">
                                                <div class="modal-header">
                                                    <h6 class="modal-title">Edit Kendaraan</h6>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Plat Nomor</label>
                                                        <input type="text" name="plat_nomor" class="form-control text-uppercase"
                                                               value="<?= htmlspecialchars($k['plat_nomor']) ?>" maxlength="15" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Jenis Kendaraan</label>
                                                        <select name="jenis_kendaraan" class="form-select" required>
                                                            <option value="Motor" <?= $k['jenis_kendaraan'] === 'Motor' ? 'selected' : '' ?>>Motor</option>
                                                            <option value="Mobil" <?= $k['jenis_kendaraan'] === 'Mobil' ? 'selected' : '' ?>>Mobil</option>
                                                            <option value="Lainnya" <?= $k['jenis_kendaraan'] === 'Lainnya' ? 'selected' : '' ?>>Lainnya</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Warna (opsional)</label>
                                                        <input type="text" name="warna" class="form-control"
                                                               value="<?= htmlspecialchars($k['warna'] ?? '') ?>" maxlength="20">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Nama Pemilik</label>
                                                        <input type="text" name="pemilik" class="form-control"
                                                               value="<?= htmlspecialchars($k['pemilik']) ?>" maxlength="100" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-hanma btn-sm">Simpan Perubahan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.btn-hapus-konfirmasi').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
        if (!confirm('Yakin ingin menghapus kendaraan ini?')) e.preventDefault();
    });
});
</script>

</body>
</html>