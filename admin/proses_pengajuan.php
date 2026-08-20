<?php
require_once __DIR__ . '/../config/koneksi.php';
cekLogin(['admin']); // hanya admin yang boleh memproses

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "admin/pengajuan_aktivasi.php");
    exit;
}

$idPengajuan = (int) ($_POST['id_pengajuan'] ?? 0);
$aksi        = $_POST['aksi'] ?? '';

if ($idPengajuan <= 0 || !in_array($aksi, ['setuju', 'tolak'], true)) {
    header("Location: " . BASE_URL . "admin/pengajuan_aktivasi.php?gagal=1");
    exit;
}

try {
    // Ambil data pengajuan, pastikan masih 'menunggu' (hindari double proses)
    $stmtGet = $koneksi->prepare(
        "SELECT * FROM tb_pengajuan_aktivasi WHERE id_pengajuan = :id AND status = 'menunggu' LIMIT 1"
    );
    $stmtGet->execute([':id' => $idPengajuan]);
    $pengajuan = $stmtGet->fetch();

    if (!$pengajuan) {
        header("Location: " . BASE_URL . "admin/pengajuan_aktivasi.php?gagal=1");
        exit;
    }

    $koneksi->beginTransaction();

    if ($aksi === 'setuju') {
        // Cek apakah username ini sudah ada di tb_user (aktif ATAU nonaktif)
        $stmtCek = $koneksi->prepare(
            "SELECT id_user, status_aktif FROM tb_user WHERE username = :username LIMIT 1"
        );
        $stmtCek->execute([':username' => $pengajuan['username']]);
        $userLama = $stmtCek->fetch();

        // Kalau ditemukan dan MASIH AKTIF, ini benar-benar bentrok -> gagal
        if ($userLama && (int) $userLama['status_aktif'] === 1) {
            $koneksi->rollBack();
            header("Location: " . BASE_URL . "admin/pengajuan_aktivasi.php?gagal=1");
            exit;
        }

        if ($userLama) {
            // ===== Username sudah ada tapi NONAKTIF -> aktifkan kembali akun lama =====
            // Password TIDAK diubah, akun lama dipakai kembali dengan password lama,
            // supaya pengguna bisa langsung login tanpa perlu password baru dari admin.
            $stmtAktifkanUlang = $koneksi->prepare(
                "UPDATE tb_user
                 SET nama_lengkap = :nama_lengkap,
                     role = :role,
                     status_aktif = 1
                 WHERE id_user = :id_user"
            );
            $stmtAktifkanUlang->execute([
                ':nama_lengkap' => $pengajuan['nama_lengkap'],
                ':role'         => $pengajuan['role_diajukan'],
                ':id_user'      => $userLama['id_user'],
            ]);

            // Update status pengajuan
            $stmtUpdate = $koneksi->prepare(
                "UPDATE tb_pengajuan_aktivasi
                 SET status = 'disetujui', diproses_at = NOW()
                 WHERE id_pengajuan = :id"
            );
            $stmtUpdate->execute([':id' => $idPengajuan]);

            $koneksi->commit();
            // Reaktivasi akun lama -> tidak ada password baru untuk ditampilkan
            header("Location: " . BASE_URL . "admin/pengajuan_aktivasi.php?sukses=setuju_reaktivasi");
            exit;

        } else {
            // ===== Belum pernah ada -> buat akun baru, wajib generate password baru =====
            $passwordPlain = substr(bin2hex(random_bytes(4)), 0, 8);
            $passwordHash  = password_hash($passwordPlain, PASSWORD_DEFAULT);

            $stmtBuatUser = $koneksi->prepare(
                "INSERT INTO tb_user (nama_lengkap, username, password, role, status_aktif, created_at)
                 VALUES (:nama_lengkap, :username, :password, :role, 1, NOW())"
            );
            $stmtBuatUser->execute([
                ':nama_lengkap' => $pengajuan['nama_lengkap'],
                ':username'     => $pengajuan['username'],
                ':password'     => $passwordHash,
                ':role'         => $pengajuan['role_diajukan'], // 'member' atau 'petugas'
            ]);

            // Update status pengajuan
            $stmtUpdate = $koneksi->prepare(
                "UPDATE tb_pengajuan_aktivasi
                 SET status = 'disetujui', diproses_at = NOW()
                 WHERE id_pengajuan = :id"
            );
            $stmtUpdate->execute([':id' => $idPengajuan]);

            $koneksi->commit();
            header("Location: " . BASE_URL . "admin/pengajuan_aktivasi.php?sukses=setuju&pw=" . urlencode($passwordPlain));
            exit;
        }

    } else {
        // Tolak pengajuan
        $catatan = trim($_POST['catatan_admin'] ?? '');
        if ($catatan === '') {
            $koneksi->rollBack();
            header("Location: " . BASE_URL . "admin/pengajuan_aktivasi.php?gagal=1");
            exit;
        }

        $stmtUpdate = $koneksi->prepare(
            "UPDATE tb_pengajuan_aktivasi
             SET status = 'ditolak', catatan_admin = :catatan, diproses_at = NOW()
             WHERE id_pengajuan = :id"
        );
        $stmtUpdate->execute([
            ':catatan' => $catatan,
            ':id'      => $idPengajuan,
        ]);

        $koneksi->commit();
        header("Location: " . BASE_URL . "admin/pengajuan_aktivasi.php?sukses=tolak");
        exit;
    }

} catch (Exception $e) {
    if ($koneksi->inTransaction()) {
        $koneksi->rollBack();
    }
    header("Location: " . BASE_URL . "admin/pengajuan_aktivasi.php?gagal=1");
    exit;
}