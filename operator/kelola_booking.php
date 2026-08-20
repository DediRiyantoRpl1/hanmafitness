<?php
require_once __DIR__ . '/../config/koneksi.php';
cekLogin(['petugas', 'admin']);
$page_title = 'Kelola Booking';

// ==== KONFIRMASI BOOKING ====
if (isset($_GET['konfirmasi'])) {
    $id = $_GET['konfirmasi'];
    $stmt = $koneksi->prepare("UPDATE tb_booking SET status = 'dikonfirmasi' WHERE id_booking = ? AND status = 'menunggu'");
    $stmt->execute([$id]);
    catatLog($koneksi, $_SESSION['id_user'], "Mengonfirmasi booking ID $id");
    header("Location: kelola_booking.php?sukses=Booking berhasil dikonfirmasi");
    exit;
}

// ==== TOLAK BOOKING ====
if (isset($_GET['tolak'])) {
    $id = $_GET['tolak'];
    $stmt = $koneksi->prepare("UPDATE tb_booking SET status = 'dibatalkan' WHERE id_booking = ? AND status = 'menunggu'");
    $stmt->execute([$id]);
    catatLog($koneksi, $_SESSION['id_user'], "Menolak booking ID $id");
    header("Location: kelola_booking.php?sukses=Booking berhasil ditolak");
    exit;
}

// ==== TANDAI SELESAI ====
if (isset($_GET['selesai'])) {
    $id = $_GET['selesai'];
    $stmt = $koneksi->prepare("UPDATE tb_booking SET status = 'selesai' WHERE id_booking = ? AND status = 'dikonfirmasi'");
    $stmt->execute([$id]);
    catatLog($koneksi, $_SESSION['id_user'], "Menyelesaikan booking ID $id");
    header("Location: kelola_booking.php?sukses=Booking ditandai selesai");
    exit;
}

$sukses = $_GET['sukses'] ?? '';

$bookings = $koneksi->query(
    "SELECT b.*, k.plat_nomor, k.jenis_kendaraan, k.warna, u.nama_lengkap
     FROM tb_booking b
     JOIN tb_kendaraan k ON b.id_kendaraan = k.id_kendaraan
     JOIN tb_user u ON b.id_user = u.id_user
     ORDER BY FIELD(b.status, 'menunggu', 'dikonfirmasi', 'selesai', 'dibatalkan'), b.id_booking DESC"
)->fetchAll();

// id booking menunggu terbaru saat load pertama, untuk basis polling notifikasi
$stmtMax = $koneksi->query("SELECT MAX(id_booking) AS id_terbaru FROM tb_booking WHERE status = 'menunggu'");
$idTerbaruSaatLoad = (int)($stmtMax->fetch()['id_terbaru'] ?? 0);

include __DIR__ . '/template/header.php';
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

<?php if ($sukses): ?>
    <div class="alert alert-success py-2"><?= htmlspecialchars($sukses) ?></div>
<?php endif; ?>

<div class="card card-hanma">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>
            Daftar Booking Parkir
            <span id="badgeMenunggu" class="badge bg-danger ms-2" style="<?= $idTerbaruSaatLoad ? '' : 'display:none;' ?>">0</span>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th><th>Member</th><th>Kendaraan</th><th>Tanggal</th><th>Jam Masuk</th><th>Jam Keluar</th><th>Catatan</th><th>Status</th><th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($bookings as $i => $b): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($b['nama_lengkap']) ?></td>
                    <td>
                        <div class="fw-semibold"><?= htmlspecialchars($b['plat_nomor']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($b['jenis_kendaraan']) ?><?= $b['warna'] ? ' — ' . htmlspecialchars($b['warna']) : '' ?></small>
                    </td>
                    <td><?= date('d M Y', strtotime($b['tanggal_booking'])) ?></td>
                    <td>
                        <?= date('H:i', strtotime($b['jam_booking_masuk'])) ?>
                        <?php
                            // Tampilkan indikator terlambat datang untuk booking yang masih menunggu/dikonfirmasi
                            if (in_array($b['status'], ['menunggu', 'dikonfirmasi'])) {
                                $menitTelat = hitungMenitTelat($b['tanggal_booking'] . ' ' . $b['jam_booking_masuk'], date('Y-m-d H:i:s'));
                                if ($menitTelat > TOLERANSI_TELAT_MENIT) {
                                    echo '<br><span class="badge bg-danger">Telat ' . $menitTelat . ' menit</span>';
                                }
                            }
                        ?>
                    </td>
                    <td><?= $b['jam_booking_keluar'] ? date('H:i', strtotime($b['jam_booking_keluar'])) : '<span class="text-muted small">-</span>' ?></td>
                    <td><small class="text-muted"><?= htmlspecialchars($b['catatan'] ?: '-') ?></small></td>
                    <td>
                        <?php
                        $badge = ['menunggu' => 'bg-warning text-dark', 'dikonfirmasi' => 'bg-primary', 'selesai' => 'bg-success', 'dibatalkan' => 'bg-danger'];
                        $label = ['menunggu' => 'Menunggu', 'dikonfirmasi' => 'Dikonfirmasi', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'];
                        ?>
                        <span class="badge <?= $badge[$b['status']] ?>"><?= $label[$b['status']] ?></span>
                    </td>
                    <td class="text-end">
                        <?php if ($b['status'] === 'menunggu'): ?>
                            <a href="?konfirmasi=<?= $b['id_booking'] ?>" class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Konfirmasi</a>
                            <a href="?tolak=<?= $b['id_booking'] ?>" class="btn btn-sm btn-outline-danger btn-hapus-konfirmasi"><i class="bi bi-x-lg"></i> Tolak</a>
                        <?php elseif ($b['status'] === 'dikonfirmasi'): ?>
                            <a href="?selesai=<?= $b['id_booking'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-flag"></i> Tandai Selesai</a>
                        <?php else: ?>
                            <span class="text-muted small">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<script>
// ==== NOTIFIKASI SUARA + BADGE UNTUK PETUGAS/ADMIN ====
let idTerakhirDiketahui = <?= $idTerbaruSaatLoad ?>;

function bunyikanNotifikasi() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.type = 'sine';
        osc.frequency.value = 660;
        gain.gain.setValueAtTime(0.2, ctx.currentTime);
        osc.start();
        osc.stop(ctx.currentTime + 0.35);
    } catch (e) { /* browser belum mengizinkan audio */ }
}

function tampilkanToast(pesan) {
    document.getElementById('toastNotifPesan').textContent = pesan;
    const toast = new bootstrap.Toast(document.getElementById('toastNotif'));
    toast.show();
}

function cekBookingBaru() {
    fetch('cek_booking_baru.php')
        .then(res => res.json())
        .then(data => {
            const badge = document.getElementById('badgeMenunggu');
            badge.textContent = data.jumlah_menunggu;
            badge.style.display = data.jumlah_menunggu > 0 ? 'inline-block' : 'none';

            if (data.id_terbaru > idTerakhirDiketahui) {
                bunyikanNotifikasi();
                tampilkanToast('Ada booking baru masuk, mohon segera dikonfirmasi.');
                idTerakhirDiketahui = data.id_terbaru;
            }
        })
        .catch(err => console.error(err));
}

setInterval(cekBookingBaru, 8000);
</script>

<?php include __DIR__ . '/template/footer.php'; ?>