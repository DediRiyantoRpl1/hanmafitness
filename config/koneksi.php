<?php
/**
 * =====================================================
 * Konfigurasi Koneksi Database
 * Aplikasi Parkir - Hanma Fitness Gym
 * =====================================================
 * Sesuaikan BASE_URL dengan nama folder project anda di htdocs/www
 * Contoh jika folder project = "parkir_hanmafitness" di XAMPP:
 * http://localhost/parkir_hanmafitness/
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==== Konfigurasi Database ====
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_hanmafitness_parkir');
define('DB_USER', 'root');
define('DB_PASS', '');

// ==== Konfigurasi URL Dasar Aplikasi ====
define('BASE_URL', 'http://localhost/parkir_hanmafitness/');

// ==== Konfigurasi Denda Keterlambatan Booking ====
// Toleransi keterlambatan (menit) sebelum booking dianggap telat & kena denda
define('TOLERANSI_TELAT_MENIT', 15);

try {
    $koneksi = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    $koneksi->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $koneksi->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

/**
 * Memastikan user sudah login & memiliki role yang diizinkan.
 * @param array $allowed_roles daftar role yang boleh mengakses halaman ini
 */
function cekLogin($allowed_roles = []) {
    if (!isset($_SESSION['id_user'])) {
        header("Location: " . BASE_URL . "auth/login.php");
        exit;
    }
    if (!empty($allowed_roles) && !in_array($_SESSION['role'], $allowed_roles)) {
        header("Location: " . BASE_URL . "auth/login.php?err=akses_ditolak");
        exit;
    }
}

/**
 * Mencatat aktivitas user ke tabel log
 */
function catatLog($koneksi, $id_user, $aktivitas) {
    $stmt = $koneksi->prepare("INSERT INTO tb_log_aktivitas (id_user, aktivitas, waktu_aktivitas) VALUES (?, ?, NOW())");
    $stmt->execute([$id_user, $aktivitas]);
}

/**
 * Menampilkan notifikasi toast (misalnya pesan "berhasil login") satu kali saja,
 * plus memicu suara KHUSUS LOGIN (mainkanSuaraLogin) -- bukan suara benar/salah
 * umum, karena fungsi ini hanya dipakai untuk notifikasi setelah login.
 * Panggil fungsi ini di halaman yang membutuhkan (contoh: dashboard setelah login).
 * Pesan diambil dari $_SESSION['notif_login'] lalu langsung dihapus agar tidak
 * muncul lagi saat halaman di-refresh.
 */
function tampilkanNotifikasiLogin() {
    if (empty($_SESSION['notif_login'])) {
        return;
    }

    $notif  = $_SESSION['notif_login'];
    $pesan  = htmlspecialchars($notif['pesan'] ?? '', ENT_QUOTES);
    $tipe   = $notif['tipe'] ?? 'success'; // success | danger | warning | info

    $warnaBg = [
        'success' => 'bg-success',
        'danger'  => 'bg-danger',
        'warning' => 'bg-warning',
        'info'    => 'bg-info',
    ][$tipe] ?? 'bg-success';

    $icon = [
        'success' => 'bi-check-circle-fill',
        'danger'  => 'bi-x-circle-fill',
        'warning' => 'bi-exclamation-triangle-fill',
        'info'    => 'bi-info-circle-fill',
    ][$tipe] ?? 'bi-check-circle-fill';

    echo '
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090;">
    <div id="toastLoginNotif" class="toast align-items-center text-white ' . $warnaBg . ' border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">
                <i class="bi ' . $icon . ' me-2"></i>' . $pesan . '
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {
    var elToastLogin = document.getElementById("toastLoginNotif");
    if (elToastLogin && window.bootstrap) {
        var toastLogin = new bootstrap.Toast(elToastLogin, { delay: 4000 });
        toastLogin.show();
    }
    // Notifikasi ini khusus untuk setelah LOGIN, jadi pakai suara login tersendiri.
    if (typeof mainkanSuaraLogin === "function") {
        mainkanSuaraLogin();
    }
});
</script>';

    // Hapus supaya notifikasi cuma tampil sekali (tidak muncul lagi saat refresh)
    unset($_SESSION['notif_login']);
}

/**
 * Menampilkan notifikasi toast setelah pembayaran selesai, satu kali saja,
 * plus memicu suara KHUSUS PEMBAYARAN (mainkanSuaraPembayaran).
 * Panggil di halaman setelah proses pembayaran (contoh: halaman struk/keluar).
 * Pesan diambil dari $_SESSION['notif_pembayaran'] lalu langsung dihapus agar
 * tidak muncul lagi saat halaman di-refresh.
 *
 * Cara set di file proses pembayaran (sebelum redirect):
 *   $_SESSION['notif_pembayaran'] = [
 *       'pesan' => 'Pembayaran berhasil! Total: ' . rupiah($biayaTotal),
 *       'tipe'  => 'success'
 *   ];
 */
function tampilkanNotifikasiPembayaran() {
    if (empty($_SESSION['notif_pembayaran'])) {
        return;
    }

    $notif  = $_SESSION['notif_pembayaran'];
    $pesan  = htmlspecialchars($notif['pesan'] ?? '', ENT_QUOTES);
    $tipe   = $notif['tipe'] ?? 'success'; // success | danger | warning | info

    $warnaBg = [
        'success' => 'bg-success',
        'danger'  => 'bg-danger',
        'warning' => 'bg-warning',
        'info'    => 'bg-info',
    ][$tipe] ?? 'bg-success';

    $icon = [
        'success' => 'bi-check-circle-fill',
        'danger'  => 'bi-x-circle-fill',
        'warning' => 'bi-exclamation-triangle-fill',
        'info'    => 'bi-info-circle-fill',
    ][$tipe] ?? 'bi-check-circle-fill';

    echo '
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090;">
    <div id="toastPembayaranNotif" class="toast align-items-center text-white ' . $warnaBg . ' border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">
                <i class="bi ' . $icon . ' me-2"></i>' . $pesan . '
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {
    var elToastBayar = document.getElementById("toastPembayaranNotif");
    if (elToastBayar && window.bootstrap) {
        var toastBayar = new bootstrap.Toast(elToastBayar, { delay: 4000 });
        toastBayar.show();
    }
    // Notifikasi ini khusus setelah PEMBAYARAN, jadi pakai suara pembayaran tersendiri.
    if (typeof mainkanSuaraPembayaran === "function") {
        mainkanSuaraPembayaran();
    }
});
</script>';

    unset($_SESSION['notif_pembayaran']);
}

/**
 * Format Rupiah
 */
function rupiah($angka) {
    return "Rp " . number_format((float)$angka, 0, ',', '.');
}

/**
 * Hitung selisih keterlambatan (menit) antara waktu terjadwal (booking)
 * dan waktu aktual. Mengembalikan 0 jika belum/tidak terlambat.
 *
 * @param string|DateTime $waktuTerjadwal
 * @param string|DateTime $waktuAktual
 * @return int menit terlambat (0 jika tidak telat)
 */
function hitungMenitTelat($waktuTerjadwal, $waktuAktual) {
    $jadwal = $waktuTerjadwal instanceof DateTime ? $waktuTerjadwal : new DateTime($waktuTerjadwal);
    $aktual = $waktuAktual instanceof DateTime ? $waktuAktual : new DateTime($waktuAktual);

    if ($aktual <= $jadwal) return 0;

    $selisihDetik = $aktual->getTimestamp() - $jadwal->getTimestamp();
    return (int) floor($selisihDetik / 60);
}

/**
 * Hitung nominal denda berdasarkan jumlah menit terlambat, dikurangi
 * toleransi (TOLERANSI_TELAT_MENIT), dibulatkan ke atas per jam.
 *
 * @param int $menitTelat
 * @param float $dendaPerJam
 * @return int nominal denda (0 jika masih dalam toleransi)
 */
function hitungDenda($menitTelat, $dendaPerJam) {
    $menitKena = $menitTelat - TOLERANSI_TELAT_MENIT;
    if ($menitKena <= 0 || $dendaPerJam <= 0) return 0;

    $jamKena = (int) ceil($menitKena / 60);
    return $jamKena * (float) $dendaPerJam;
}

/**
 * Hitung SISA SLOT REAL-TIME sebuah area parkir untuk HARI INI, dengan
 * memperhitungkan DUA sumber pemakaian slot sekaligus:
 *   1. `terisi`   -> kendaraan yang sudah benar-benar fisik parkir (walk-in
 *                    atau booking yang sudah diproses masuk oleh petugas).
 *   2. booking    -> booking member utk HARI INI yang statusnya masih
 *                    'menunggu' / 'dikonfirmasi' (sudah menahan slot,
 *                    tapi kendaraannya belum tiba / belum diproses masuk).
 *
 * Sebelumnya kedua sumber ini dihitung TERPISAH di halaman berbeda
 * (index.php & operator hanya lihat `terisi`, sedangkan booking member
 * hanya lihat tabel tb_booking) sehingga angka "sisa slot" yang tampil
 * tidak sinkron dan area bisa ke-overbooking (walk-in menghabiskan slot
 * yang sebenarnya sudah dipesan member via booking).
 *
 * Catatan: booking untuk booking yang SUDAH diproses masuk statusnya
 * berubah jadi 'selesai' pada saat bersamaan `terisi` bertambah, jadi
 * fungsi ini TIDAK menghitung dobel.
 *
 * @param PDO $koneksi
 * @param int $id_area
 * @param int $kapasitas
 * @param int $terisi
 * @return array ['terpakai' => int, 'sisa' => int, 'penuh' => bool]
 */
function hitungSlotAreaHariIni($koneksi, $id_area, $kapasitas, $terisi) {
    $stmt = $koneksi->prepare(
        "SELECT COUNT(*) AS jumlah FROM tb_booking
         WHERE id_area = ? AND tanggal_booking = CURDATE() AND status IN ('menunggu','dikonfirmasi')"
    );
    $stmt->execute([$id_area]);
    $bookingHariIni = (int) $stmt->fetch()['jumlah'];

    $kapasitas = (int) $kapasitas;
    $terpakai  = ((int) $terisi) + $bookingHariIni;
    $sisa      = max(0, $kapasitas - $terpakai);

    return [
        'terpakai' => $terpakai,
        'sisa'     => $sisa,
        'penuh'    => $terpakai >= $kapasitas,
    ];
}