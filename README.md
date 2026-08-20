# Aplikasi Parkir - Hanma Fitness Gym

Aplikasi manajemen parkir untuk gym **Hanma Fitness**, dibangun dengan PHP native + MySQL (PDO)
dan tampilan Bootstrap 5, mengikuti struktur folder yang diminta serta ketentuan soal
**Uji Kompetensi Keahlian - Pengembangan Aplikasi Parkir (KM25.4.1.1)**.

Selain alur parkir dasar (masuk/keluar/struk), aplikasi ini juga dilengkapi fitur
**booking online oleh member**, **notifikasi booking real-time** untuk petugas,
**denda keterlambatan booking**, serta **landing page publik** dengan video profil,
QRIS, dan moderasi testimoni.

**Live demo:** [hanmagym.infinityfreeapp.com](https://hanmagym.infinityfreeapp.com)

## 1. Struktur Folder

```
parkir_hanmafitness/
├── admin/                    -> Halaman & fitur untuk role ADMIN
│   ├── template/               (header, footer, sidebar_admin)
│   ├── index.php               (dashboard admin)
│   ├── kelola_user.php         (CRUD user: admin/petugas/owner/member)
│   ├── kelola_tarif.php        (CRUD tarif parkir)
│   ├── kelola_area.php         (CRUD area parkir & kapasitas slot)
│   ├── kelola_kendaraan.php    (CRUD data kendaraan)
│   ├── testimoni.php           (moderasi komentar & rating landing page)
│   ├── testimoni_action.php    (aksi approve/reject/hapus testimoni)
│   ├── log_aktivitas.php       (akses log aktivitas seluruh user)
│   └── edit_profil.php         (edit profil & foto admin)
├── assets/                   -> css, js, dan audio
│   ├── css/style.css
│   ├── js/main.js, sound-effect.js
│   └── audio/benar.wav, salah.wav   (efek suara notifikasi sukses/gagal)
├── auth/                     -> login.php, logout.php, register.php
│   (registrasi mandiri publik hanya membuat akun dengan role **member**)
├── config/                   -> koneksi.php
│   (koneksi PDO + helper cekLogin, catatLog, rupiah, hitungMenitTelat, hitungDenda)
├── img/                      -> logo, foto & video landing page (hanma.mp4, qris.jpeg, dll.)
├── member/                   -> Halaman & fitur untuk role MEMBER
│   ├── index.php                (dashboard member: kendaraan, riwayat & parkir aktif)
│   ├── kendaraan.php            (CRUD kendaraan milik member)
│   ├── booking.php              (booking slot parkir online)
│   ├── cek_slot_area.php        (endpoint JSON: cek sisa slot area per tanggal)
│   ├── cek_status_booking.php   (endpoint JSON: polling status booking)
│   └── edit_profil.php          (edit profil & upload foto member)
├── operator/                 -> Halaman & fitur untuk role PETUGAS
│   ├── components/              (navbar, header, footer)
│   ├── index.php                (dashboard petugas)
│   ├── transaksi_masuk.php      (input kendaraan masuk, termasuk dari booking)
│   ├── transaksi_keluar.php     (proses kendaraan keluar & hitung biaya + denda telat)
│   ├── kelola_booking.php       (konfirmasi/tolak/selesaikan booking member)
│   ├── cek_booking_baru.php     (endpoint JSON: polling notifikasi booking baru)
│   ├── cetak_struk.php          (cetak struk masuk/keluar)
│   └── riwayat_transaksi.php    (riwayat transaksi milik petugas)
├── owner/                    -> Halaman & fitur untuk role OWNER
│   ├── components/              (sidebar_owner, header, footer)
│   ├── index.php                (dashboard owner: grafik pendapatan)
│   ├── rekap_transaksi.php      (rekap transaksi sesuai rentang tanggal)
│   └── edit_profil.php          (edit profil & foto owner)
├── uploads/                  -> profil, profile_admin, profile_operator, profile_user
├── index.php                 -> landing page publik (profil, video, QRIS, testimoni) +
│                                  redirect otomatis ke dashboard jika sudah login
├── testimoni_submit.php      -> handler simpan komentar/rating dari landing page (status pending)
└── db_hanmafitness_parkir.sql  -> file SQL untuk import database
```

> **Catatan:** struktur folder mengikuti kerangka dasar dari soal (Admin/Petugas/Owner),
> ditambah folder `member/` untuk role keempat yaitu pengguna umum yang bisa mendaftar
> mandiri dan melakukan booking parkir online.

## 2. Instalasi Lokal (XAMPP / Laragon)

1. Copy folder `parkir_hanmafitness` ke dalam `htdocs` (XAMPP) atau `www` (Laragon).
2. Buka **phpMyAdmin**, buat database baru bernama **`db_hanmafitness_parkir`**, lalu
   **Import** file `db_hanmafitness_parkir.sql` (tabel & data seeder akan otomatis dibuat).
3. Buka `config/koneksi.php`, sesuaikan bila perlu:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'db_hanmafitness_parkir');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('APP_ENV', 'development');
   define('BASE_URL', 'http://localhost/parkir_hanmafitness/');
   define('TOLERANSI_TELAT_MENIT', 15);
   ```
   Ubah `BASE_URL` jika nama folder project anda berbeda. `TOLERANSI_TELAT_MENIT` adalah
   toleransi (dalam menit) sebelum booking yang telat dijemput dikenai denda — nilai
   default denda per jam-nya sendiri diatur lewat tabel `tb_setting_denda`.
4. Akses aplikasi melalui `http://localhost/parkir_hanmafitness/`.

## 3. Deploy ke Hosting (InfinityFree)

Project ini sudah live di **InfinityFree** dengan domain `hanmagym.infinityfreeapp.com`.
Ringkasan langkah deploy-nya:

1. Upload **seluruh isi** folder `parkir_hanmafitness` (bukan foldernya) langsung ke
   folder `htdocs` lewat File Manager atau FTP InfinityFree.
2. Buat database MySQL baru lewat panel InfinityFree (nama database & user otomatis
   diberi prefix, misal `if0_xxxxxxx_db_hanmafitness_parkir`), lalu import
   `db_hanmafitness_parkir.sql` lewat phpMyAdmin bawaan InfinityFree.
3. Sesuaikan `config/koneksi.php` dengan kredensial yang diberikan InfinityFree:
   ```php
   define('DB_HOST', 'sqlXXX.infinityfree.com');   // sesuai info panel
   define('DB_NAME', 'if0_xxxxxxx_db_hanmafitness_parkir');
   define('DB_USER', 'if0_xxxxxxx');
   define('DB_PASS', '••••••••');
   define('APP_ENV', 'production');
   define('BASE_URL', 'https://hanmagym.infinityfreeapp.com/');
   ```
   Di mode `APP_ENV = 'production'`, pesan error koneksi database tidak ditampilkan
   detail ke pengunjung (hanya dicatat ke log server) untuk menghindari kebocoran
   kredensial.
4. Pastikan `BASE_URL` cocok dengan struktur upload (root domain vs subfolder), karena
   semua link & asset (CSS/JS/gambar) di aplikasi mengacu ke konstanta ini.
5. Gambar hero (`img/hanma.jpg`) di `index.php` memakai cache-buster otomatis
   (`?v=<?= filemtime(...) ?>`), jadi setiap kali file gambar diganti di server,
   browser pengunjung otomatis mengambil versi terbaru tanpa perlu clear cache manual.

## 4. Akun Default (Seeder)

| Role     | Username  | Nama                | Keterangan |
|----------|-----------|----------------------|------------|
| Admin    | `admin`   | Administrator Hanma     | akses penuh |
| Petugas  | `petugas` | Rian Petugas          | operasional harian |
| Owner    | `owner`   | Coach Dedi            | monitoring & rekap |
| Member   | `awan`    | awan dwi              | contoh akun member (bisa daftar sendiri via `auth/register.php`) |

Password tersimpan ter-hash (bcrypt) di `db_hanmafitness_parkir.sql`. Jika lupa password akun
seeder, buat ulang hash-nya dengan `password_hash()` PHP lalu update kolom `password` pada
tabel `tb_user`, atau daftar akun member baru sendiri melalui halaman registrasi. Segera ubah
password melalui menu **Kelola User** / **Edit Profil** setelah berhasil login.

## 5. Hak Akses Fitur

| Fitur                                   | Admin | Petugas | Owner | Member |
|-------------------------------------------|:-----:|:-------:|:-----:|:------:|
| Login / Logout / Edit Profil               | ✔     | ✔       | ✔     | ✔      |
| Registrasi mandiri (publik)                |       |         |       | ✔      |
| CRUD User                                  | ✔     |         |       |        |
| CRUD Tarif Parkir                          | ✔     |         |       |        |
| CRUD Area Parkir (kapasitas slot)          | ✔     |         |       |        |
| CRUD Kendaraan (semua data)                | ✔     |         |       |        |
| Moderasi Testimoni (approve/reject/hapus)  | ✔     |         |       |        |
| Akses Log Aktivitas                        | ✔     |         |       |        |
| CRUD Kendaraan milik sendiri               |       |         |       | ✔      |
| Booking slot parkir online                 |       |         |       | ✔      |
| Cek sisa slot & status booking sendiri     |       |         |       | ✔      |
| Konfirmasi / Tolak / Selesaikan Booking    |       | ✔       |       |        |
| Notifikasi booking baru (real-time)        |       | ✔       |       |        |
| Kendaraan Masuk (Transaksi)                |       | ✔       |       |        |
| Kendaraan Keluar + Hitung Denda Telat      |       | ✔       |       |        |
| Cetak Struk Masuk / Keluar                 |       | ✔       |       |        |
| Riwayat Transaksi milik petugas            |       | ✔       |       |        |
| Dashboard Grafik Pendapatan                |       |         | ✔     |        |
| Rekap Transaksi sesuai periode             |       |         | ✔     |        |
| Kirim komentar & rating (landing page)     | publik / semua pengunjung situs           |||

## 6. Skema Database

File: `db_hanmafitness_parkir.sql`. Tabel utama:

| Tabel               | Fungsi |
|---------------------|--------|
| `tb_user`            | Data akun (`role`: admin, petugas, owner, member), foto profil, status aktif |
| `tb_kendaraan`       | Data kendaraan, terhubung ke pemilik (`id_user`) |
| `tb_area_parkir`     | Area/zona parkir beserta kapasitas slot |
| `tb_tarif`           | Master tarif parkir per jenis kendaraan |
| `tb_booking`         | Booking slot parkir oleh member (status: menunggu, dikonfirmasi, dibatalkan, selesai) |
| `tb_transaksi`       | Transaksi parkir masuk/keluar (bisa berasal dari booking), perhitungan biaya & denda |
| `tb_setting_denda`   | Pengaturan toleransi menit & nominal denda keterlambatan booking per jam |
| `tb_log_aktivitas`   | Log aktivitas seluruh user (login, CRUD, moderasi, dsb.) |
| `testimoni`          | Komentar & rating publik dari landing page (status: pending, approved, rejected) |

Relasi antar tabel dijaga dengan **FOREIGN KEY** (mis. `tb_booking` ↔ `tb_user`/`tb_kendaraan`/
`tb_area_parkir`, `tb_transaksi` ↔ `tb_booking`/`tb_kendaraan`/`tb_tarif`/`tb_area_parkir`).

## 7. Alur Kerja Aplikasi

1. **Pengunjung publik** membuka `index.php` (landing page): melihat profil gym, video area
   parkir, QRIS pembayaran, serta testimoni yang sudah disetujui admin, dan bisa mengirim
   komentar/rating baru (otomatis berstatus *pending* sampai dimoderasi admin).
2. **Member** mendaftar mandiri lewat `auth/register.php`, lalu login untuk mengelola
   kendaraan pribadi (`member/kendaraan.php`) dan melakukan **booking slot parkir**
   (`member/booking.php`) — sistem mengecek kapasitas area secara real-time sebelum
   booking disetujui sistem, dan member bisa memantau status booking-nya.
3. **Petugas** menerima notifikasi booking baru secara real-time (polling
   `cek_booking_baru.php`), lalu mengonfirmasi/menolak booking pada `kelola_booking.php`.
4. Saat kendaraan (baik dari booking maupun walk-in) masuk, petugas mencatatnya di
   `transaksi_masuk.php` → slot area berkurang otomatis & tiket masuk tercetak.
5. Saat kendaraan keluar, petugas memprosesnya di `transaksi_keluar.php` → sistem
   menghitung durasi & biaya parkir berdasarkan tarif per jam, ditambah **denda
   keterlambatan** jika kendaraan berbasis booking dijemput melewati toleransi waktu
   (`TOLERANSI_TELAT_MENIT` & `tb_setting_denda`) → struk pembayaran dicetak.
6. **Admin** mengelola seluruh master data (user, tarif, area, kendaraan), memoderasi
   testimoni publik, dan memantau log aktivitas seluruh pengguna.
7. **Owner** memantau dashboard pendapatan & dapat melihat rekap transaksi pada rentang
   tanggal tertentu, lengkap dengan opsi cetak.

---
Dibuat sesuai kerangka struktur project yang diberikan, untuk memenuhi ketentuan
Soal Praktik Kejuruan "Pengembangan Aplikasi Parkir" (Paket 2, RPL 2025/2026),
ditematik ulang dan dikembangkan lebih lanjut (booking online, denda keterlambatan,
landing page & testimoni) untuk kebutuhan parkir gym **Hanma Fitness**.
