<?php
// config/db-koneksi.php

// 1. Set zona waktu di sisi PHP
date_default_timezone_set('Asia/Jakarta');

$host = "localhost";
$user = "ingintau_pkldpib"; 
$pass = "TUsmekisa1968"; 
$db   = "ingintau_pkldpib";

$koneksi = new mysqli($host, $user, $pass, $db);

if ($koneksi->connect_error) {
    die("Koneksi gagal: " . $koneksi->connect_error);
}

// 2. Set zona waktu di sisi Database MySQL (WIB adalah +07:00)
$koneksi->query("SET time_zone = '+07:00'");

// =========================================================================
// 3. FUNGSI PENCATAT LOG AKTIVITAS SISTEM (GLOBAL)
// =========================================================================
if (!function_exists('catatLog')) {
    function catatLog($koneksi, $user_id, $aksi) {
        // Ambil IP Address User dengan deteksi Proxy/Cloudflare
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) { 
            $ip_address = $_SERVER['HTTP_CLIENT_IP']; 
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { 
            // Jika ada banyak IP dari proxy, ambil yang pertama (IP asli user)
            $ip_list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip_address = trim($ip_list[0]); 
        }

        // Simpan ke database
        $stmt = $koneksi->prepare("INSERT INTO log_aktivitas (user_id, aksi, ip_address, waktu) VALUES (?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("iss", $user_id, $aksi, $ip_address);
            $stmt->execute();
            $stmt->close();
        }
    }
}