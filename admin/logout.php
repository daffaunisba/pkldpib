<?php
// admin/logout.php

session_start();
include '../config/db-koneksi.php';

// Ambil ID user dari sesi SEBELUM sesi dihapus dan dihancurkan
$current_user_id = $_SESSION['user_id'] ?? 0;

if ($current_user_id > 0) {
    // --- TRIGGER LOG AKTIVITAS (LOGOUT) ---
    catatLog($koneksi, $current_user_id, "Berhasil logout (keluar) dari sistem");
}

// Hapus semua variabel sesi
$_SESSION = array();

// Hancurkan sesi
session_destroy();

// Arahkan kembali ke halaman login
header("Location: login.php");
exit();
?>