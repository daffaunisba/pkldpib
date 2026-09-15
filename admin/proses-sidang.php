<?php
include 'auth-check.php';
include '../config/db-koneksi.php';

$lokasi_id = $_POST['lokasi_id'];
$tgl = $_POST['tgl']; 
$jam = $_POST['jam']; 
$ruang = $_POST['ruang'];
$p1 = $_POST['p1']; 
$p2 = $_POST['p2'];

// 1. Validasi Penguji
if ($p1 == $p2) {
    die("<script>alert('Error: Penguji tidak boleh sama!'); history.back();</script>");
}

// 2. Validasi Pembimbing
$p_cek = $koneksi->query("SELECT guru_id FROM lokasi_pkl WHERE lokasi_id = '$lokasi_id'")->fetch_assoc();
if ($p1 == $p_cek['guru_id'] || $p2 == $p_cek['guru_id']) {
    die("<script>alert('Error: Pembimbing tidak boleh menjadi penguji!'); history.back();</script>");
}

// 3. Simpan Data (Menggunakan INSERT ... ON DUPLICATE KEY UPDATE)
// Pastikan tabel jadwal_sidang memiliki kolom lokasi_id sebagai PRIMARY atau UNIQUE KEY
$stmt = $koneksi->prepare("INSERT INTO jadwal_sidang (lokasi_id, tanggal_sidang, waktu_sidang, ruangan, penguji_id, penguji_id_2) 
                           VALUES (?, ?, ?, ?, ?, ?) 
                           ON DUPLICATE KEY UPDATE 
                           tanggal_sidang=VALUES(tanggal_sidang), 
                           waktu_sidang=VALUES(waktu_sidang), 
                           ruangan=VALUES(ruangan), 
                           penguji_id=VALUES(penguji_id), 
                           penguji_id_2=VALUES(penguji_id_2)");

$stmt->bind_param("isssii", $lokasi_id, $tgl, $jam, $ruang, $p1, $p2);

if ($stmt->execute()) {
    header("Location: sidang-pkl.php?status=success");
} else {
    // Tampilkan error database jika masih 500
    die("Database Error: " . $koneksi->error);
}
?>