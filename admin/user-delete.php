<?php
// admin/user-delete.php
include 'auth-check.php'; // Proteksi Login
include '../config/db-koneksi.php';

$current_user = isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin';
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$current_logged_in_id = $_SESSION['user_id'];

// 1. Cek validitas ID
if ($user_id <= 0) {
    header("Location: user-list.php?status=delete_failed");
    exit();
}

// 2. Pencegahan: Jangan biarkan admin menghapus dirinya sendiri
if ($user_id === $current_logged_in_id) {
    $error_msg = urlencode("❌ Anda tidak dapat menghapus akun Anda sendiri saat sedang login!");
    header("Location: user-list.php?status=error&msg=" . $error_msg);
    exit();
}

// 3. Proses Penghapusan
$delete_stmt = $koneksi->prepare("DELETE FROM users WHERE id = ?");
$delete_stmt->bind_param("i", $user_id);

if ($delete_stmt->execute()) {
    // Redirect ke halaman list dengan status sukses
    header("Location: user-list.php?status=delete_success");
} else {
    // Redirect ke halaman list dengan status gagal
    $error_msg = urlencode("Gagal menghapus user. Pastikan user tidak membimbing atau data terikat lainnya.");
    header("Location: user-list.php?status=error&msg=" . $error_msg);
}

$delete_stmt->close();
$koneksi->close();
exit();
?>