<?php
// admin/update-status-izin.php
session_start();
include '../config/db-koneksi.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id_izin']) && isset($_POST['status'])) {
    $id_izin = $_POST['id_izin'];
    $status = $_POST['status'];

    // Update status di tabel pengajuan_izin
    $stmt = $koneksi->prepare("UPDATE pengajuan_izin SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $id_izin);

    if ($stmt->execute()) {
        // Berhasil, kembali ke halaman perizinan
        header("Location: perizinan.php?status=success");
    } else {
        // Gagal
        header("Location: perizinan.php?status=error");
    }
    $stmt->close();
} else {
    header("Location: perizinan.php");
}
?>