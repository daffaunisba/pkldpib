<?php
// admin/auth-check.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. Periksa apakah user telah login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// 2. Pastikan variabel level dan username tersedia untuk pengecekan RBAC
// Jika salah satu tidak ada, maka sesi dianggap tidak valid
if (!isset($_SESSION['level']) || !isset($_SESSION['username'])) {
    session_destroy(); // Hapus sesi yang tidak lengkap
    header('Location: login.php?msg=sesi_invalid');
    exit();
}

/**
 * Catatan:
 * Kita tidak melakukan pengecekan role 'admin' atau 'ade' di sini 
 * supaya Pembimbing tetap bisa melewati file ini untuk masuk ke dashboard.
 * Filter spesifik (Admin/Ade) dilakukan di file masing-masing (seperti user-list.php).
 */
?>