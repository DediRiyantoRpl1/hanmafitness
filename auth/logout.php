<?php
require_once __DIR__ . '/../config/koneksi.php';

if (isset($_SESSION['id_user'])) {
    catatLog($koneksi, $_SESSION['id_user'], 'Logout dari sistem');
}

$_SESSION = [];
session_destroy();

header("Location: " . BASE_URL . "auth/login.php");
exit;
