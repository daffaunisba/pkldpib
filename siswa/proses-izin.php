<?php
session_start();
include '../config/db-koneksi.php';

// Pastikan user sudah login
if (!isset($_SESSION['siswa_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $siswa_id    = $_SESSION['siswa_id'];
    $jenis_izin  = $_POST['jenis_izin'];
    $tgl_mulai   = $_POST['tgl_mulai'];
    $tgl_selesai = $_POST['tgl_selesai'];
    $alasan      = mysqli_real_escape_string($koneksi, $_POST['alasan']);
    
    // Konfigurasi Upload File
    $target_dir  = "../uploads/izin/";
    
    // Cek apakah folder ada, jika tidak buat foldernya
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $file_name   = time() . "_" . basename($_FILES["lampiran"]["name"]);
    $target_file = $target_dir . $file_name;
    $file_type   = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Validasi Ukuran File (Maks 2MB)
    if ($_FILES["lampiran"]["size"] > 2000000) {
        echo "<script>alert('Gagal! Ukuran file terlalu besar (Maks 2MB).'); window.history.back();</script>";
        exit();
    }

    // Validasi Ekstensi File
    $allowed_types = array("jpg", "jpeg", "png", "pdf");
    if (!in_array($file_type, $allowed_types)) {
        echo "<script>alert('Gagal! Hanya file JPG, PNG, & PDF yang diizinkan.'); window.history.back();</script>";
        exit();
    }

    // Proses Upload
    if (move_uploaded_file($_FILES["lampiran"]["tmp_name"], $target_file)) {
        // Simpan ke Database
        $query = "INSERT INTO pengajuan_izin (siswa_id, jenis_izin, tgl_mulai, tgl_selesai, alasan, file_pendukung, status) 
                  VALUES ('$siswa_id', '$jenis_izin', '$tgl_mulai', '$tgl_selesai', '$alasan', '$file_name', 'Menunggu')";

        if ($koneksi->query($query)) {
            echo "<script>
                    alert('Berhasil! Pengajuan Anda telah dikirim dan menunggu verifikasi.');
                    window.location.href='pengajuan-izin.php';
                  </script>";
        } else {
            echo "Error: " . $koneksi->error;
        }
    } else {
        echo "<script>alert('Gagal mengunggah file. Pastikan folder uploads/izin tersedia.'); window.history.back();</script>";
    }
} else {
    header("Location: pengajuan-izin.php");
}
?>