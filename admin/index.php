<?php
require_once __DIR__ . '/../config/koneksi.php';
cekLogin(['admin']);

$page_title = 'Dashboard';

// Statistik
$totalKendaraan = $koneksi->query("SELECT COUNT(*) c FROM tb_kendaraan")->fetch()['c'];
$totalUser = $koneksi->query("SELECT COUNT(*) c FROM tb_user")->fetch()['c'];
$kendaraanMasuk = $koneksi->query("SELECT COUNT(*) c FROM tb_transaksi WHERE status='masuk'")->fetch()['c'];
$pendapatanHariIni = $koneksi->query("SELECT COALESCE(SUM(biaya_total),0) t FROM tb_transaksi WHERE status='keluar' AND DATE(waktu_keluar) = CURDATE()")->fetch()['t'];

$area = $koneksi->query("SELECT * FROM tb_area_parkir ORDER BY id_area")->fetchAll();

$transaksiTerbaru = $koneksi->query("
    SELECT t.*, k.plat_nomor, k.jenis_kendaraan, k.pemilik, u.nama_lengkap AS petugas
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON k.id_kendaraan = t.id_kendaraan
    JOIN tb_user u ON u.id_user = t.id_user
    ORDER BY t.id_parkir DESC LIMIT 8
")->fetchAll();

// ===== Data grafik: pendapatan 7 hari terakhir =====
$stmtGrafik = $koneksi->prepare("
    SELECT DATE(waktu_keluar) AS tanggal, COALESCE(SUM(biaya_total),0) AS total
    FROM tb_transaksi
    WHERE status = 'keluar' AND waktu_keluar >= (CURDATE() - INTERVAL 6 DAY)
    GROUP BY DATE(waktu_keluar)
");
$stmtGrafik->execute();
$hasilPendapatan = $stmtGrafik->fetchAll(PDO::FETCH_KEY_PAIR);

$labelGrafik = [];
$dataPendapatan = [];
for ($i = 6; $i >= 0; $i--) {
    $tgl = date('Y-m-d', strtotime("-$i day"));
    $labelGrafik[] = date('d/m', strtotime($tgl));
    $dataPendapatan[] = isset($hasilPendapatan[$tgl]) ? (float)$hasilPendapatan[$tgl] : 0;
}

// ===== Data grafik: kendaraan masuk per hari (7 hari terakhir) =====
$stmtGrafik2 = $koneksi->prepare("
    SELECT DATE(waktu_masuk) AS tanggal, COUNT(*) AS jumlah
    FROM tb_transaksi
    WHERE waktu_masuk >= (CURDATE() - INTERVAL 6 DAY)
    GROUP BY DATE(waktu_masuk)
");
$stmtGrafik2->execute();
$hasilKendaraan = $stmtGrafik2->fetchAll(PDO::FETCH_KEY_PAIR);

$dataKendaraan = [];
for ($i = 6; $i >= 0; $i--) {
    $tgl = date('Y-m-d', strtotime("-$i day"));
    $dataKendaraan[] = isset($hasilKendaraan[$tgl]) ? (int)$hasilKendaraan[$tgl] : 0;
}

include __DIR__ . '/template/header.php';
?>

<div class="dashboard-bg">

<div class="row g-3 mb-2">
    <div class="col-md-3 col-6">
        <div class="stat-card bg-grad-red">
            <div class="stat-icon"><i class="bi bi-car-front"></i></div>
            <h3><?= $totalKendaraan ?></h3>
            <span class="label">Total Kendaraan Terdaftar</span>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card bg-grad-orange">
            <div class="stat-icon"><i class="bi bi-p-circle"></i></div>
            <h3><?= $kendaraanMasuk ?></h3>
            <span class="label">Kendaraan di Area Parkir</span>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card bg-grad-dark">
            <div class="stat-icon"><i class="bi bi-people"></i></div>
            <h3><?= $totalUser ?></h3>
            <span class="label">Total Akun Pengguna</span>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card bg-grad-green">
            <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
            <h3><?= rupiah($pendapatanHariIni) ?></h3>
            <span class="label">Pendapatan Hari Ini</span>
        </div>
    </div>
</div>

<div class="row g-3 mb-2">
    <div class="col-lg-7">
        <div class="card card-hanma">
            <div class="card-header">Pendapatan 7 Hari Terakhir</div>
            <div class="card-body">
                <div style="position: relative; height: 280px;">
                    <canvas id="chartPendapatan"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card card-hanma">
            <div class="card-header">Kendaraan Masuk 7 Hari Terakhir</div>
            <div class="card-body">
                <div style="position: relative; height: 280px;">
                    <canvas id="chartKendaraan"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card card-hanma">
            <div class="card-header">Transaksi Terbaru</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead><tr>
                        <th>Plat Nomor</th><th>Jenis</th><th>Pemilik</th><th>Petugas</th>
                        <th>Masuk</th><th>Status</th><th>Biaya</th>
                    </tr></thead>
                    <tbody>
                    <?php if (empty($transaksiTerbaru)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Belum ada transaksi</td></tr>
                    <?php endif; ?>
                    <?php foreach ($transaksiTerbaru as $t): ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($t['plat_nomor']) ?></td>
                            <td><?= ucfirst($t['jenis_kendaraan']) ?></td>
                            <td><?= htmlspecialchars($t['pemilik']) ?></td>
                            <td><?= htmlspecialchars($t['petugas']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($t['waktu_masuk'])) ?></td>
                            <td><span class="badge badge-status-<?= $t['status'] ?>"><?= ucfirst($t['status']) ?></span></td>
                            <td><?= $t['biaya_total'] ? rupiah($t['biaya_total']) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card card-hanma">
            <div class="card-header">Kapasitas Area Parkir</div>
            <div class="card-body">
                <?php foreach ($area as $a):
                    $persen = $a['kapasitas'] > 0 ? round(($a['terisi'] / $a['kapasitas']) * 100) : 0;
                    $warna = $persen >= 90 ? 'bg-danger' : ($persen >= 60 ? 'bg-warning' : 'bg-success');
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-semibold small"><?= htmlspecialchars($a['nama_area']) ?></span>
                        <span class="small text-muted"><?= $a['terisi'] ?>/<?= $a['kapasitas'] ?></span>
                    </div>
                    <div class="progress" style="height:8px;">
                        <div class="progress-bar <?= $warna ?>" style="width: <?= $persen ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

</div><!-- /.dashboard-bg -->

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
    const ctxPendapatan = document.getElementById('chartPendapatan');
    if (ctxPendapatan) {
        new Chart(ctxPendapatan, {
            type: 'line',
            data: {
                labels: <?= json_encode($labelGrafik) ?>,
                datasets: [{
                    label: 'Pendapatan (Rp)',
                    data: <?= json_encode($dataPendapatan) ?>,
                    borderColor: '#b3121b',
                    backgroundColor: 'rgba(179,18,27,0.12)',
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#b3121b',
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 400 },
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });
    }

    const ctxKendaraan = document.getElementById('chartKendaraan');
    if (ctxKendaraan) {
        new Chart(ctxKendaraan, {
            type: 'bar',
            data: {
                labels: <?= json_encode($labelGrafik) ?>,
                datasets: [{
                    label: 'Kendaraan Masuk',
                    data: <?= json_encode($dataKendaraan) ?>,
                    backgroundColor: 'rgba(255,106,0,0.8)',
                    borderRadius: 6,
                    maxBarThickness: 36
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 400 },
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }
</script>

<?php include __DIR__ . '/template/footer.php'; ?>