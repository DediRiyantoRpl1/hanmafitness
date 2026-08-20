<?php
require_once __DIR__ . '/../config/koneksi.php';
cekLogin(['owner']);
$page_title = 'Dashboard Owner';

$pendapatanBulanIni = $koneksi->query("SELECT COALESCE(SUM(biaya_total),0) t FROM tb_transaksi WHERE status='keluar' AND MONTH(waktu_keluar)=MONTH(CURDATE()) AND YEAR(waktu_keluar)=YEAR(CURDATE())")->fetch()['t'];
$pendapatanHariIni = $koneksi->query("SELECT COALESCE(SUM(biaya_total),0) t FROM tb_transaksi WHERE status='keluar' AND DATE(waktu_keluar) = CURDATE()")->fetch()['t'];
$totalTransaksi = $koneksi->query("SELECT COUNT(*) c FROM tb_transaksi WHERE status='keluar'")->fetch()['c'];
$sedangParkir = $koneksi->query("SELECT COUNT(*) c FROM tb_transaksi WHERE status='masuk'")->fetch()['c'];

$pendapatan7hari = $koneksi->query("
    SELECT DATE(waktu_keluar) tgl, SUM(biaya_total) total
    FROM tb_transaksi
    WHERE status='keluar' AND waktu_keluar >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(waktu_keluar) ORDER BY tgl
")->fetchAll();

$petugasTerbaik = $koneksi->query("
    SELECT u.nama_lengkap, COUNT(*) jumlah, COALESCE(SUM(t.biaya_total),0) total
    FROM tb_transaksi t JOIN tb_user u ON u.id_user = t.id_user
    WHERE t.status='keluar'
    GROUP BY t.id_user ORDER BY total DESC LIMIT 5
")->fetchAll();

include __DIR__ . '/components/header.php';
?>

<div class="row g-3 mb-2">
    <div class="col-md-3 col-6">
        <div class="stat-card bg-grad-green">
            <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
            <h3><?= rupiah($pendapatanBulanIni) ?></h3>
            <span class="label">Pendapatan Bulan Ini</span>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card bg-grad-red">
            <div class="stat-icon"><i class="bi bi-cash-coin"></i></div>
            <h3><?= rupiah($pendapatanHariIni) ?></h3>
            <span class="label">Pendapatan Hari Ini</span>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card bg-grad-orange">
            <div class="stat-icon"><i class="bi bi-receipt"></i></div>
            <h3><?= $totalTransaksi ?></h3>
            <span class="label">Total Transaksi Selesai</span>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card bg-grad-dark">
            <div class="stat-icon"><i class="bi bi-p-circle"></i></div>
            <h3><?= $sedangParkir ?></h3>
            <span class="label">Kendaraan Sedang Parkir</span>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card card-hanma">
            <div class="card-header">Pendapatan 7 Hari Terakhir</div>
            <div class="card-body">
                <canvas id="chartPendapatan" height="140"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card card-hanma">
            <div class="card-header">Performa Petugas</div>
            <div class="card-body p-0">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Petugas</th><th>Transaksi</th><th>Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($petugasTerbaik as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['nama_lengkap']) ?></td>
                            <td><?= $p['jumlah'] ?></td>
                            <td><?= rupiah($p['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($petugasTerbaik)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-4">Belum ada data</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('chartPendapatan');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: [<?php foreach ($pendapatan7hari as $p) echo "'" . date('d/m', strtotime($p['tgl'])) . "',"; ?>],
        datasets: [{
            label: 'Pendapatan (Rp)',
            data: [<?php foreach ($pendapatan7hari as $p) echo $p['total'] . ","; ?>],
            backgroundColor: '#ff3b30',
            borderRadius: 6
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});
</script>

<?php include __DIR__ . '/components/footer.php'; ?>
