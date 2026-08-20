<?php
$page_title = 'Booking Parkir';
require_once __DIR__ . '/../config/koneksi.php';

if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'member') {
    header("Location: " . BASE_URL . "auth/login.php?err=akses_ditolak");
    exit;
}

$id_user = $_SESSION['id_user'];
$error = '';
$success = '';

$stmtKendaraan = $koneksi->prepare("SELECT * FROM tb_kendaraan WHERE id_user = ? ORDER BY plat_nomor");
$stmtKendaraan->execute([$id_user]);
$daftarKendaraan = $stmtKendaraan->fetchAll();

$area = $koneksi->query("SELECT * FROM tb_area_parkir ORDER BY nama_area")->fetchAll();

// ==== TAMBAH BOOKING ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'tambah') {
    $mode_kendaraan     = $_POST['mode_kendaraan'] ?? 'lama';
    $id_area            = $_POST['id_area'] ?? '';
    $tanggal_booking    = $_POST['tanggal_booking'] ?? '';
    $jam_booking_masuk  = $_POST['jam_booking_masuk'] ?? '';
    $jam_booking_keluar = trim($_POST['jam_booking_keluar'] ?? '');
    $catatan            = trim($_POST['catatan'] ?? '');

    if ($id_area === '' || $tanggal_booking === '' || $jam_booking_masuk === '') {
        $error = 'Area, tanggal, dan jam masuk booking wajib diisi.';
    } elseif (strtotime($tanggal_booking) < strtotime(date('Y-m-d'))) {
        $error = 'Tanggal booking tidak boleh di masa lalu.';
    } elseif ($jam_booking_keluar !== '' && $jam_booking_keluar <= $jam_booking_masuk) {
        $error = 'Jam keluar harus lebih besar dari jam masuk.';
    } else {
        $stmtArea = $koneksi->prepare("SELECT nama_area, kapasitas, terisi FROM tb_area_parkir WHERE id_area = ?");
        $stmtArea->execute([$id_area]);
        $areaInfo = $stmtArea->fetch();

        $stmtHitung = $koneksi->prepare(
            "SELECT COUNT(*) AS jumlah FROM tb_booking
             WHERE id_area = ? AND tanggal_booking = ? AND status IN ('menunggu','dikonfirmasi')"
        );
        $stmtHitung->execute([$id_area, $tanggal_booking]);
        $jumlahTerpakai = (int) $stmtHitung->fetch()['jumlah'];

        if ($areaInfo && $tanggal_booking === date('Y-m-d')) {
            $jumlahTerpakai += (int) $areaInfo['terisi'];
        }

        if ($areaInfo && $jumlahTerpakai >= (int) $areaInfo['kapasitas']) {
            $error = 'Maaf, area "' . htmlspecialchars($areaInfo['nama_area']) . '" sudah penuh pada tanggal tersebut. Silakan pilih area atau tanggal lain.';
        } else {
            $id_kendaraan = null;

            if ($mode_kendaraan === 'baru') {
                $plat_nomor      = strtoupper(trim($_POST['plat_nomor_baru'] ?? ''));
                $jenis_kendaraan = $_POST['jenis_kendaraan_baru'] ?? '';
                $warna           = trim($_POST['warna_baru'] ?? '');

                if ($plat_nomor === '' || $jenis_kendaraan === '') {
                    $error = 'Plat nomor dan jenis kendaraan wajib diisi.';
                } else {
                    $cekPlat = $koneksi->prepare("SELECT id_kendaraan FROM tb_kendaraan WHERE plat_nomor = ?");
                    $cekPlat->execute([$plat_nomor]);
                    $existing = $cekPlat->fetch();

                    if ($existing) {
                        $id_kendaraan = $existing['id_kendaraan'];
                    } else {
                        $stmtK = $koneksi->prepare(
                            "INSERT INTO tb_kendaraan (plat_nomor, jenis_kendaraan, warna, pemilik, id_user)
                             VALUES (?, ?, ?, ?, ?)"
                        );
                        $stmtK->execute([$plat_nomor, $jenis_kendaraan, $warna ?: null, $_SESSION['nama_lengkap'], $id_user]);
                        $id_kendaraan = $koneksi->lastInsertId();
                    }
                }
            } else {
                $id_kendaraan = $_POST['id_kendaraan'] ?? '';
                if ($id_kendaraan === '') {
                    $error = 'Silakan pilih kendaraan.';
                } else {
                    $cekKendaraan = $koneksi->prepare("SELECT id_kendaraan FROM tb_kendaraan WHERE id_kendaraan = ? AND id_user = ?");
                    $cekKendaraan->execute([$id_kendaraan, $id_user]);
                    if (!$cekKendaraan->fetch()) {
                        $error = 'Kendaraan tidak valid.';
                    }
                }
            }

            if (!$error && $id_kendaraan) {
                $stmtHitung->execute([$id_area, $tanggal_booking]);
                $jumlahTerpakaiFinal = (int) $stmtHitung->fetch()['jumlah'];

                if ($areaInfo && $jumlahTerpakaiFinal >= (int) $areaInfo['kapasitas']) {
                    $error = 'Maaf, area "' . htmlspecialchars($areaInfo['nama_area']) . '" baru saja penuh. Silakan pilih area atau tanggal lain.';
                } else {
                    $stmt = $koneksi->prepare(
                        "INSERT INTO tb_booking (id_user, id_kendaraan, id_area, tanggal_booking, jam_booking_masuk, jam_booking_keluar, catatan, status)
                         VALUES (?, ?, ?, ?, ?, ?, ?, 'menunggu')"
                    );
                    $stmt->execute([$id_user, $id_kendaraan, $id_area, $tanggal_booking, $jam_booking_masuk, $jam_booking_keluar ?: null, $catatan ?: null]);
                    $success = 'Booking berhasil dibuat. Menunggu konfirmasi dari petugas.';

                    $stmtKendaraan->execute([$id_user]);
                    $daftarKendaraan = $stmtKendaraan->fetchAll();
                }
            }
        }
    }
}

// ==== BATALKAN BOOKING ====
if (isset($_GET['batal'])) {
    $id_booking = $_GET['batal'];
    $stmt = $koneksi->prepare("UPDATE tb_booking SET status = 'dibatalkan' WHERE id_booking = ? AND id_user = ? AND status = 'menunggu'");
    $stmt->execute([$id_booking, $id_user]);
    header("Location: booking.php?sukses=Booking berhasil dibatalkan");
    exit;
}

// ==== HAPUS SATU RIWAYAT BOOKING (tombol per baris) ====
if (isset($_GET['hapus'])) {
    $id_booking = $_GET['hapus'];
    $stmt = $koneksi->prepare(
        "DELETE FROM tb_booking
         WHERE id_booking = ? AND id_user = ? AND status IN ('selesai','dibatalkan')"
    );
    $stmt->execute([$id_booking, $id_user]);

    if ($stmt->rowCount() > 0) {
        header("Location: booking.php?sukses=Riwayat booking berhasil dihapus");
    } else {
        header("Location: booking.php?gagal=Booking tidak dapat dihapus karena masih aktif");
    }
    exit;
}

// ==== HAPUS RIWAYAT TERPILIH (checkbox) / HAPUS SEMUA RIWAYAT ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && in_array($_POST['aksi'], ['hapus_terpilih', 'hapus_semua'], true)) {
    $jumlahDihapus = 0;

    if ($_POST['aksi'] === 'hapus_semua') {
        $stmt = $koneksi->prepare(
            "DELETE FROM tb_booking WHERE id_user = ? AND status IN ('selesai','dibatalkan')"
        );
        $stmt->execute([$id_user]);
        $jumlahDihapus = $stmt->rowCount();
    } else {
        $idTerpilih = $_POST['id_booking'] ?? [];
        $idTerpilih = array_values(array_filter(array_map('intval', (array) $idTerpilih)));

        if (!empty($idTerpilih)) {
            $placeholder = implode(',', array_fill(0, count($idTerpilih), '?'));
            $params = array_merge($idTerpilih, [$id_user]);
            $stmt = $koneksi->prepare(
                "DELETE FROM tb_booking
                 WHERE id_booking IN ($placeholder) AND id_user = ? AND status IN ('selesai','dibatalkan')"
            );
            $stmt->execute($params);
            $jumlahDihapus = $stmt->rowCount();
        }
    }

    if ($jumlahDihapus > 0) {
        header("Location: booking.php?sukses=" . $jumlahDihapus . " riwayat booking berhasil dihapus");
    } else {
        header("Location: booking.php?gagal=Tidak ada riwayat booking yang dapat dihapus (pastikan status sudah Selesai/Dibatalkan)");
    }
    exit;
}

if (isset($_GET['sukses'])) $success = $_GET['sukses'];
if (isset($_GET['gagal'])) $error = $_GET['gagal'];

$stmtBooking = $koneksi->prepare(
    "SELECT b.*, k.plat_nomor, k.jenis_kendaraan, k.warna, a.nama_area
     FROM tb_booking b
     JOIN tb_kendaraan k ON b.id_kendaraan = k.id_kendaraan
     LEFT JOIN tb_area_parkir a ON b.id_area = a.id_area
     WHERE b.id_user = ?
     ORDER BY b.id_booking DESC"
);
$stmtBooking->execute([$id_user]);
$daftarBooking = $stmtBooking->fetchAll();

$adaBookingBisaDihapus = false;
foreach ($daftarBooking as $b) {
    if ($b['status'] === 'selesai' || $b['status'] === 'dibatalkan') {
        $adaBookingBisaDihapus = true;
        break;
    }
}

require __DIR__ . '/components/header.php';
?>

<!-- Toast Notifikasi -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;">
    <div id="toastNotif" class="toast align-items-center text-white bg-primary border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="toastNotifPesan">Notifikasi</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<p class="text-muted mb-4">Reservasi slot parkir kendaraan anda sebelum datang ke Hanma Fitness Gym.</p>

<?php if ($error): ?>
    <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card card-hanma">
            <div class="card-header fw-semibold">
                <i class="bi bi-plus-circle"></i> Buat Booking Baru
            </div>
            <div class="card-body">
                <form method="POST" id="formBooking">
                    <input type="hidden" name="aksi" value="tambah">
                    <input type="hidden" name="mode_kendaraan" id="mode_kendaraan" value="<?= count($daftarKendaraan) > 0 ? 'lama' : 'baru' ?>">

                    <?php if (count($daftarKendaraan) > 0): ?>
                    <ul class="nav nav-pills nav-fill mb-3" role="tablist">
                        <li class="nav-item">
                            <button type="button" class="nav-link active" id="tab-lama" onclick="pilihModeKendaraan('lama')">Kendaraan Saya</button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link" id="tab-baru" onclick="pilihModeKendaraan('baru')">Kendaraan Baru</button>
                        </li>
                    </ul>
                    <?php endif; ?>

                    <div id="blok-kendaraan-lama" class="mb-3" style="<?= count($daftarKendaraan) === 0 ? 'display:none;' : '' ?>">
                        <label class="form-label">Kendaraan</label>
                        <select name="id_kendaraan" class="form-select">
                            <option value="" selected disabled>Pilih kendaraan</option>
                            <?php foreach ($daftarKendaraan as $k): ?>
                                <option value="<?= $k['id_kendaraan'] ?>">
                                    <?= htmlspecialchars($k['plat_nomor']) ?> — <?= htmlspecialchars($k['jenis_kendaraan']) ?><?= $k['warna'] ? ' (' . htmlspecialchars($k['warna']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="blok-kendaraan-baru" style="<?= count($daftarKendaraan) > 0 ? 'display:none;' : '' ?>">
                        <div class="mb-3">
                            <label class="form-label">Plat Nomor</label>
                            <input type="text" name="plat_nomor_baru" class="form-control text-uppercase" placeholder="Contoh: B 1234 XYZ">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jenis Kendaraan</label>
                            <select name="jenis_kendaraan_baru" class="form-select">
                                <option value="" selected disabled>Pilih jenis</option>
                                <option value="Motor">Motor</option>
                                <option value="Mobil">Mobil</option>
                                <option value="Truk/Bus">Truk/Bus</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Warna (opsional)</label>
                            <input type="text" name="warna_baru" class="form-control" maxlength="20">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Area Parkir</label>
                        <select name="id_area" id="id_area" class="form-select" required>
                            <option value="" selected disabled>Pilih area</option>
                            <?php foreach ($area as $a): ?>
                                <option value="<?= $a['id_area'] ?>"><?= htmlspecialchars($a['nama_area']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div id="infoSlotArea" class="form-text"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tanggal Booking</label>
                        <input type="date" name="tanggal_booking" id="tanggal_booking" class="form-control"
                               min="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Jam Masuk</label>
                            <input type="time" name="jam_booking_masuk" id="jam_booking_masuk" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Jam Keluar (estimasi)</label>
                            <input type="time" name="jam_booking_keluar" id="jam_booking_keluar" class="form-control">
                        </div>
                    </div>
                    <div class="form-text mb-3">
                        <i class="bi bi-exclamation-triangle text-warning"></i>
                        Datang/keluar dari jam di atas akan <strong>dikenakan denda</strong> jika melewati toleransi
                        <?= TOLERANSI_TELAT_MENIT ?> menit. Isi jam keluar agar tidak kena denda saat keluar.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Catatan (opsional)</label>
                        <textarea name="catatan" class="form-control" rows="2" placeholder="Contoh: butuh slot dekat pintu masuk"></textarea>
                    </div>
                    <button type="submit" id="btnAjukanBooking" class="btn btn-hanma w-100">
                        <i class="bi bi-calendar-check"></i> Ajukan Booking
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card card-hanma">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="bi bi-list-check"></i> Riwayat Booking Saya</span>
                <?php if ($adaBookingBisaDihapus): ?>
                <div class="d-flex gap-2">
                    <button type="submit" form="formRiwayatBooking" name="aksi" value="hapus_terpilih"
                            id="btnHapusTerpilih" class="btn btn-sm btn-outline-secondary" disabled>
                        <i class="bi bi-trash"></i> Hapus Terpilih (<span id="jumlahTerpilih">0</span>)
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalHapusSemuaBooking">
                        <i class="bi bi-trash3"></i> Hapus Semua
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (count($daftarBooking) === 0): ?>
                    <p class="text-muted text-center py-4 mb-0">Belum ada booking yang dibuat.</p>
                <?php else: ?>
                    <form method="POST" id="formRiwayatBooking">
                    <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <?php if ($adaBookingBisaDihapus): ?>
                                <th style="width:36px;">
                                    <input type="checkbox" class="form-check-input" id="checkSemuaBooking" title="Pilih semua yang bisa dihapus">
                                </th>
                                <?php endif; ?>
                                <th>Kendaraan</th>
                                <th>Area</th>
                                <th>Tanggal</th>
                                <th>Jam Masuk</th>
                                <th>Jam Keluar</th>
                                <th>Catatan</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($daftarBooking as $b): ?>
                            <?php $bisaDihapus = ($b['status'] === 'selesai' || $b['status'] === 'dibatalkan'); ?>
                            <tr id="booking-row-<?= $b['id_booking'] ?>">
                                <?php if ($adaBookingBisaDihapus): ?>
                                <td>
                                    <?php if ($bisaDihapus): ?>
                                    <input type="checkbox" class="form-check-input check-booking-item" name="id_booking[]" value="<?= $b['id_booking'] ?>">
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($b['plat_nomor']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($b['jenis_kendaraan']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($b['nama_area'] ?: '-') ?></td>
                                <td><?= date('d M Y', strtotime($b['tanggal_booking'])) ?></td>
                                <td><?= date('H:i', strtotime($b['jam_booking_masuk'])) ?></td>
                                <td><?= $b['jam_booking_keluar'] ? date('H:i', strtotime($b['jam_booking_keluar'])) : '<span class="text-muted">-</span>' ?></td>
                                <td><small class="text-muted"><?= htmlspecialchars($b['catatan'] ?: '-') ?></small></td>
                                <td>
                                    <?php
                                    $badge = ['menunggu' => 'bg-warning text-dark', 'dikonfirmasi' => 'bg-primary', 'selesai' => 'bg-success', 'dibatalkan' => 'bg-danger'];
                                    $label = ['menunggu' => 'Menunggu', 'dikonfirmasi' => 'Dikonfirmasi', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'];
                                    ?>
                                    <span class="badge <?= $badge[$b['status']] ?> badge-status" data-id="<?= $b['id_booking'] ?>"><?= $label[$b['status']] ?></span>
                                </td>
                                <td class="text-end">
                                    <?php if ($b['status'] === 'menunggu'): ?>
                                    <a href="?batal=<?= $b['id_booking'] ?>" class="btn btn-sm btn-outline-danger btn-batal-konfirmasi">
                                        <i class="bi bi-x-circle"></i> Batalkan
                                    </a>
                                    <?php elseif ($bisaDihapus): ?>
                                    <a href="?hapus=<?= $b['id_booking'] ?>" class="btn btn-sm btn-outline-secondary btn-hapus-konfirmasi">
                                        <i class="bi bi-trash"></i> Hapus
                                    </a>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Hapus Semua Riwayat Booking -->
<div class="modal fade" id="modalHapusSemuaBooking" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Hapus Semua Riwayat Booking</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">
                    Apakah Anda yakin ingin menghapus <strong>semua riwayat booking</strong> berstatus
                    <strong>Selesai</strong> dan <strong>Dibatalkan</strong>? Booking yang masih
                    <strong>Menunggu</strong> atau <strong>Dikonfirmasi</strong> tidak akan terhapus.
                    Tindakan ini <strong>tidak dapat dibatalkan</strong>.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" form="formRiwayatBooking" name="aksi" value="hapus_semua" class="btn btn-danger">
                    Ya, Hapus Semua
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function pilihModeKendaraan(mode) {
    document.getElementById('mode_kendaraan').value = mode;
    document.getElementById('blok-kendaraan-lama').style.display = (mode === 'lama') ? '' : 'none';
    document.getElementById('blok-kendaraan-baru').style.display = (mode === 'baru') ? '' : 'none';
    document.getElementById('tab-lama').classList.toggle('active', mode === 'lama');
    document.getElementById('tab-baru').classList.toggle('active', mode === 'baru');
}

document.querySelectorAll('.btn-batal-konfirmasi').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
        if (!confirm('Yakin ingin membatalkan booking ini?')) e.preventDefault();
    });
});

(function () {
    const checkSemua   = document.getElementById('checkSemuaBooking');
    const checkItem    = document.querySelectorAll('.check-booking-item');
    const btnHapus     = document.getElementById('btnHapusTerpilih');
    const jumlahLabel  = document.getElementById('jumlahTerpilih');
    const formRiwayat  = document.getElementById('formRiwayatBooking');

    function updateTombolHapusTerpilih() {
        if (!btnHapus) return;
        const jumlah = document.querySelectorAll('.check-booking-item:checked').length;
        jumlahLabel.textContent = jumlah;
        btnHapus.disabled = jumlah === 0;
    }

    if (checkSemua) {
        checkSemua.addEventListener('change', function () {
            checkItem.forEach(function (cb) { cb.checked = checkSemua.checked; });
            updateTombolHapusTerpilih();
        });
    }

    checkItem.forEach(function (cb) {
        cb.addEventListener('change', function () {
            if (!cb.checked && checkSemua) checkSemua.checked = false;
            updateTombolHapusTerpilih();
        });
    });

    if (formRiwayat) {
        formRiwayat.addEventListener('submit', function (e) {
            const submitter = e.submitter;
            const aksi = submitter ? submitter.value : '';
            if (aksi === 'hapus_terpilih') {
                if (!confirm('Yakin ingin menghapus riwayat booking yang dipilih? Tindakan ini tidak dapat dibatalkan.')) {
                    e.preventDefault();
                }
            }
        });
    }
})();

function bunyikanNotifikasi() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.type = 'sine';
        osc.frequency.value = 880;
        gain.gain.setValueAtTime(0.2, ctx.currentTime);
        osc.start();
        osc.stop(ctx.currentTime + 0.3);
    } catch (e) { /* browser belum mengizinkan audio */ }
}

function tampilkanToast(pesan) {
    document.getElementById('toastNotifPesan').textContent = pesan;
    const toast = new bootstrap.Toast(document.getElementById('toastNotif'));
    toast.show();
}

const labelStatus = { menunggu: 'Menunggu', dikonfirmasi: 'Dikonfirmasi', selesai: 'Selesai', dibatalkan: 'Dibatalkan' };
const kelasBadge = { menunggu: 'bg-warning text-dark', dikonfirmasi: 'bg-primary', selesai: 'bg-success', dibatalkan: 'bg-danger' };

function cekStatusBooking() {
    fetch('cek_status_booking.php')
        .then(res => res.json())
        .then(data => {
            let statusLama = JSON.parse(localStorage.getItem('statusBookingSaya') || '{}');
            let statusBaru = {};

            data.forEach(function (b) {
                statusBaru[b.id_booking] = b.status;

                if (statusLama[b.id_booking] && statusLama[b.id_booking] !== b.status) {
                    bunyikanNotifikasi();
                    if (b.status === 'dikonfirmasi') {
                        tampilkanToast('Booking anda telah dikonfirmasi petugas!');
                    } else if (b.status === 'dibatalkan') {
                        tampilkanToast('Booking anda dibatalkan/ditolak petugas.');
                    }
                    const badgeEl = document.querySelector('.badge-status[data-id="' + b.id_booking + '"]');
                    if (badgeEl) {
                        badgeEl.className = 'badge badge-status ' + kelasBadge[b.status];
                        badgeEl.textContent = labelStatus[b.status];
                    }
                }
            });

            localStorage.setItem('statusBookingSaya', JSON.stringify(statusBaru));
        })
        .catch(err => console.error(err));
}

setInterval(cekStatusBooking, 8000);

const selectArea   = document.getElementById('id_area');
const inputTanggal = document.getElementById('tanggal_booking');
const infoSlotArea = document.getElementById('infoSlotArea');
const btnAjukan    = document.getElementById('btnAjukanBooking');

let areaSudahPenuh = false;

function cekSlotArea() {
    const idArea = selectArea.value;
    const tanggal = inputTanggal.value;

    if (!idArea || !tanggal) {
        infoSlotArea.innerHTML = '';
        areaSudahPenuh = false;
        btnAjukan.disabled = false;
        return;
    }

    infoSlotArea.innerHTML = '<span class="text-muted">Mengecek ketersediaan slot...</span>';

    fetch('cek_slot_area.php?id_area=' + encodeURIComponent(idArea) + '&tanggal_booking=' + encodeURIComponent(tanggal))
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                infoSlotArea.innerHTML = '';
                areaSudahPenuh = false;
                btnAjukan.disabled = false;
                return;
            }

            if (data.penuh) {
                infoSlotArea.innerHTML = '<span class="text-danger fw-semibold"><i class="bi bi-exclamation-triangle-fill"></i> Area "' + data.nama_area + '" sudah PENUH pada tanggal ini (' + data.terpakai + '/' + data.kapasitas + ' slot terpakai).</span>';
                areaSudahPenuh = true;
                btnAjukan.disabled = true;
                bunyikanNotifikasi();
                tampilkanToast('Area "' + data.nama_area + '" sudah penuh pada tanggal yang dipilih!');
            } else {
                infoSlotArea.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill"></i> Sisa ' + data.sisa + ' dari ' + data.kapasitas + ' slot tersedia.</span>';
                areaSudahPenuh = false;
                btnAjukan.disabled = false;
            }
        })
        .catch(err => console.error(err));
}

if (selectArea && inputTanggal) {
    selectArea.addEventListener('change', cekSlotArea);
    inputTanggal.addEventListener('change', cekSlotArea);
}

document.getElementById('formBooking').addEventListener('submit', function (e) {
    if (areaSudahPenuh) {
        e.preventDefault();
        tampilkanToast('Tidak bisa mengajukan booking, area sudah penuh.');
    }
});
</script>

<?php require __DIR__ . '/components/footer.php'; ?>