<?php
require_once __DIR__ . '/../config/koneksi.php';
cekLogin(['petugas']);

$id = $_GET['id'] ?? 0;
$mode = $_GET['mode'] ?? 'masuk';

$stmt = $koneksi->prepare("
    SELECT t.*, k.plat_nomor, k.jenis_kendaraan, k.pemilik, k.warna, a.nama_area, u.nama_lengkap AS petugas, tf.tarif_per_jam,
           bk.jam_booking_masuk AS booking_jam_masuk, bk.jam_booking_keluar AS booking_jam_keluar
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON k.id_kendaraan = t.id_kendaraan
    JOIN tb_area_parkir a ON a.id_area = t.id_area
    JOIN tb_user u ON u.id_user = t.id_user
    JOIN tb_tarif tf ON tf.id_tarif = t.id_tarif
    LEFT JOIN tb_booking bk ON bk.id_booking = t.id_booking
    WHERE t.id_parkir = ?
");
$stmt->execute([$id]);
$trx = $stmt->fetch();

if (!$trx) {
    header("Location: index.php?gagal=Transaksi tidak ditemukan");
    exit;
}

$page_title = 'Cetak Struk';
include __DIR__ . '/components/header.php';

if ($mode === 'keluar') {
    // Halaman ini hanya muncul kalau pembayaran berhasil diproses.
    // Tampilkan toast + suara KHUSUS PEMBAYARAN (data diisi di kendaraan_keluar.php)
    tampilkanNotifikasiPembayaran();
} else {
    // Tiket masuk kendaraan (belum ada pembayaran): tetap pakai suara umum "benar"
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof mainkanSuaraBenar === 'function') mainkanSuaraBenar();
    });
    </script>
    <?php
}
?>

<div class="text-center mb-3 no-print">
    <button class="btn btn-hanma" onclick="cetakStruk()"><i class="bi bi-printer me-1"></i> Cetak Struk</button>
    <a href="<?= $mode === 'masuk' ? 'index.php' : 'transaksi_keluar.php' ?>" class="btn btn-outline-secondary">Kembali</a>
</div>

<div class="struk-box">
    <h5>HANMA FITNESS</h5>
    <p class="sub">Struk Parkir Gym &middot; <?= $mode === 'masuk' ? 'TIKET MASUK' : 'BUKTI PEMBAYARAN' ?></p>
    <hr>
    <div class="row-line"><span>No. Transaksi</span><span>#<?= str_pad($trx['id_parkir'], 6, '0', STR_PAD_LEFT) ?></span></div>
    <div class="row-line"><span>Plat Nomor</span><span><?= htmlspecialchars($trx['plat_nomor']) ?></span></div>
    <div class="row-line"><span>Jenis</span><span class="text-capitalize"><?= $trx['jenis_kendaraan'] ?></span></div>
    <div class="row-line"><span>Pemilik</span><span><?= htmlspecialchars($trx['pemilik']) ?></span></div>
    <div class="row-line"><span>Area</span><span><?= htmlspecialchars($trx['nama_area']) ?></span></div>
    <div class="row-line"><span>Petugas</span><span><?= htmlspecialchars($trx['petugas']) ?></span></div>
    <hr>
    <div class="row-line"><span>Waktu Masuk</span><span><?= date('d/m/Y H:i', strtotime($trx['waktu_masuk'])) ?></span></div>
    <?php if ($trx['booking_jam_masuk']): ?>
        <div class="row-line small text-muted"><span>Jadwal Booking Masuk</span><span><?= date('H:i', strtotime($trx['booking_jam_masuk'])) ?></span></div>
    <?php endif; ?>
    <?php if ((float) $trx['denda_telat_masuk'] > 0): ?>
        <div class="row-line text-danger"><span>Denda Telat Masuk</span><span><?= rupiah($trx['denda_telat_masuk']) ?></span></div>
    <?php endif; ?>
    <?php if ($trx['status'] === 'keluar'): ?>
        <div class="row-line"><span>Waktu Keluar</span><span><?= date('d/m/Y H:i', strtotime($trx['waktu_keluar'])) ?></span></div>
        <?php if ($trx['booking_jam_keluar']): ?>
            <div class="row-line small text-muted"><span>Jadwal Booking Keluar</span><span><?= date('H:i', strtotime($trx['booking_jam_keluar'])) ?></span></div>
        <?php endif; ?>
        <div class="row-line"><span>Durasi</span><span><?= $trx['durasi_jam'] ?> jam</span></div>
        <div class="row-line"><span>Tarif/Jam</span><span><?= rupiah($trx['tarif_per_jam']) ?></span></div>
        <div class="row-line"><span>Biaya Parkir</span><span><?= rupiah($trx['durasi_jam'] * $trx['tarif_per_jam']) ?></span></div>
        <?php if ((float) $trx['denda_telat_keluar'] > 0): ?>
            <div class="row-line text-danger"><span>Denda Telat Keluar</span><span><?= rupiah($trx['denda_telat_keluar']) ?></span></div>
        <?php endif; ?>
        <div class="row-line"><span>Metode Bayar</span><span><?= strtoupper($trx['metode_bayar'] ?? 'TUNAI') ?></span></div>
        <hr>
        <div class="total-line"><span>TOTAL BAYAR</span><span><?= rupiah($trx['biaya_total']) ?></span></div>
    <?php else: ?>
        <hr>
        <p class="text-center small mb-0">Simpan struk ini untuk proses keluar</p>
    <?php endif; ?>
    <hr>
    <p class="text-center small mb-0">Terima kasih telah berlatih di Hanma Fitness!</p>
</div>

<?php include __DIR__ . '/components/footer.php'; ?>