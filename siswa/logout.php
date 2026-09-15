<?php
session_start();

// 1. Hapus semua data di dalam Session
$_SESSION = [];

// 2. Hancurkan Session
session_destroy();

// 3. HAPUS COOKIE (Ini yang paling penting!)
// Cara menghapus cookie adalah dengan mengatur masa berlakunya ke waktu masa lalu (misal: mundur 1 jam / -3600 detik)
if (isset($_COOKIE['id_murid'])) {
    setcookie('id_murid', '', time() - 3600, '/');
}
if (isset($_COOKIE['key'])) {
    setcookie('key', '', time() - 3600, '/');
}

// 4. Arahkan kembali ke halaman login
header("Location: login.php"); // Sesuaikan nama file login kamu jika berbeda
exit();
