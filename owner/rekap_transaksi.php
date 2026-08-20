<?php
require_once __DIR__ . '/../config/koneksi.php';
cekLogin(['owner']);
$page_title = 'Rekap Transaksi';

$dari = $_GET['dari'] ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-d');

// Data untuk DITAMPILKAN di tabel: hanya yang belum dihapus
$stmt = $koneksi->prepare("
    SELECT t.*, k.plat_nomor, k.jenis_kendaraan, k.pemilik, a.nama_area, u.nama_lengkap AS petugas
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON k.id_kendaraan = t.id_kendaraan
    JOIN tb_area_parkir a ON a.id_area = t.id_area
    JOIN tb_user u ON u.id_user = t.id_user
    WHERE t.status = 'keluar' AND t.dihapus = 0 AND DATE(t.waktu_keluar) BETWEEN ? AND ?
    ORDER BY t.waktu_keluar DESC
");
$stmt->execute([$dari, $sampai]);
$rekap = $stmt->fetchAll();

// Data untuk TOTAL PENDAPATAN: semua transaksi (termasuk yang sudah dihapus dari tabel)
// supaya dana/pendapatan tidak ikut hilang saat rekap dihapus dari tampilan
$stmtTotal = $koneksi->prepare("
    SELECT t.biaya_total, t.metode_bayar
    FROM tb_transaksi t
    WHERE t.status = 'keluar' AND DATE(t.waktu_keluar) BETWEEN ? AND ?
");
$stmtTotal->execute([$dari, $sampai]);
$semuaTransaksi = $stmtTotal->fetchAll();

$totalPendapatan = array_sum(array_column($semuaTransaksi, 'biaya_total'));
$totalTransaksi = count($rekap); // jumlah baris yang tampil (tidak termasuk yang dihapus)
$totalTunai = array_sum(array_column(array_filter($semuaTransaksi, fn($r) => $r['metode_bayar'] === 'tunai'), 'biaya_total'));
$totalQris = array_sum(array_column(array_filter($semuaTransaksi, fn($r) => $r['metode_bayar'] === 'qris'), 'biaya_total'));

include __DIR__ . '/components/header.php';
?>

<div class="card card-hanma mb-3 no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small">Dari Tanggal</label>
                <input type="date" name="dari" class="form-control" value="<?= htmlspecialchars($dari) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Sampai Tanggal</label>
                <input type="date" name="sampai" class="form-control" value="<?= htmlspecialchars($sampai) ?>">
            </div>
            <div class="col-md-3">
                <button class="btn btn-hanma w-100"><i class="bi bi-funnel me-1"></i> Tampilkan Rekap</button>
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-outline-secondary w-100" onclick="cetakStruk()"><i class="bi bi-printer me-1"></i> Cetak Rekap</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-2">
    <div class="col-md-6">
        <div class="stat-card bg-grad-green">
            <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
            <h3 id="totalPendapatan"><?= rupiah($totalPendapatan) ?></h3>
            <span class="label">Total Pendapatan Periode Ini</span>
        </div>
    </div>
    <div class="col-md-6">
        <div class="stat-card bg-grad-dark">
            <div class="stat-icon"><i class="bi bi-receipt"></i></div>
            <h3><?= $totalTransaksi ?></h3>
            <span class="label">Total Transaksi Periode Ini</span>
        </div>
    </div>
</div>

<div class="row g-3 mb-2">
    <div class="col-md-6">
        <div class="stat-card bg-grad-green">
            <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
            <h3><?= rupiah($totalTunai) ?></h3>
            <span class="label">Pendapatan Tunai</span>
        </div>
    </div>
    <div class="col-md-6">
        <div class="stat-card bg-grad-dark">
            <div class="stat-icon"><i class="bi bi-qr-code"></i></div>
            <h3><?= rupiah($totalQris) ?></h3>
            <span class="label">Pendapatan QRIS</span>
        </div>
    </div>
</div>

<div class="card card-hanma">
    <div class="card-header">
        Rekap Transaksi <?= date('d/m/Y', strtotime($dari)) ?> &ndash; <?= date('d/m/Y', strtotime($sampai)) ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>#</th><th>Plat Nomor</th><th>Jenis</th><th>Pemilik</th><th>Area</th><th>Petugas</th><th>Masuk</th><th>Keluar</th><th>Durasi</th><th>Metode</th><th>Biaya</th><th class="no-print">Aksi</th></tr></thead>
            <tbody id="tabelRekap">
            <?php foreach ($rekap as $i => $r): ?>
                <tr id="row-<?= $r['id_parkir'] ?>">
                    <td><?= $i + 1 ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars($r['plat_nomor']) ?></td>
                    <td class="text-capitalize"><?= $r['jenis_kendaraan'] ?></td>
                    <td><?= htmlspecialchars($r['pemilik']) ?></td>
                    <td><?= htmlspecialchars($r['nama_area']) ?></td>
                    <td><?= htmlspecialchars($r['petugas']) ?></td>
                    <td><?= date('d/m/y H:i', strtotime($r['waktu_masuk'])) ?></td>
                    <td><?= date('d/m/y H:i', strtotime($r['waktu_keluar'])) ?></td>
                    <td><?= $r['durasi_jam'] ?> jam</td>
                    <td><span class="badge <?= $r['metode_bayar'] === 'qris' ? 'bg-dark' : 'bg-success' ?>"><?= strtoupper($r['metode_bayar']) ?></span></td>
                    <td class="fw-semibold"><?= rupiah($r['biaya_total']) ?></td>
                    <td class="no-print">
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="hapusRekap(<?= $r['id_parkir'] ?>)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($rekap)): ?>
                <tr id="rowKosong"><td colspan="12" class="text-center text-muted py-4">Tidak ada transaksi pada periode ini</td></tr>
            <?php endif; ?>
            </tbody>
            <?php if (!empty($rekap)): ?>
            <tfoot>
                <tr class="fw-bold">
                    <td colspan="10" class="text-end">TOTAL PENDAPATAN</td>
                    <td colspan="2"><?= rupiah($totalPendapatan) ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
        </div>
    </div>
</div>

<script>
function hapusRekap(idTransaksi) {
    if (!confirm('Hapus baris rekap ini dari daftar? Pendapatan tetap akan terhitung di total.')) {
        return;
    }

    const formData = new FormData();
    formData.append('id_parkir', idTransaksi);

    fetch('<?= BASE_URL ?>owner/hapus_rekap.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'ok') {
            const row = document.getElementById('row-' + idTransaksi);
            if (row) row.remove();
            if (typeof mainkanSuaraBenar === 'function') mainkanSuaraBenar();
        } else {
            if (typeof mainkanSuaraSalah === 'function') mainkanSuaraSalah();
            alert(data.message || 'Gagal menghapus rekap.');
        }
    })
    .catch(() => {
        if (typeof mainkanSuaraSalah === 'function') mainkanSuaraSalah();
        alert('Terjadi kesalahan saat menghubungi server.');
    });
}
</script>

<?php include __DIR__ . '/components/footer.php'; ?>