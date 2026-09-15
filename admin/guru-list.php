<?php
// admin/guru-list.php
include 'auth-check.php'; 
include '../config/db-koneksi.php';

// Generate CSRF Token untuk keamanan
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Ambil level user dari session
$user_level = isset($_SESSION['level']) ? strtolower($_SESSION['level']) : 'pembimbing';
$current_username = isset($_SESSION['username']) ? strtolower($_SESSION['username']) : 'admin';
$current_user_id = $_SESSION['user_id'] ?? 0;
$message = '';
$guru_per_page = 15; 

// Proteksi: Hanya Admin, Ade, atau Pembimbing yang bisa mengakses halaman ini
if ($user_level !== 'admin' && $current_username !== 'ade' && $user_level !== 'pembimbing') {
    header("Location: dashboard.php?status=restricted");
    exit();
}

$can_manage_users = ($user_level === 'admin' || $current_username === 'ade');

// =========================================================================================
// LOGIKA BACKEND 1: UPDATE LOKASI BIMBINGAN (GURU)
// =========================================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['proses_update_bimbingan'])) {
    if ($can_manage_users) {
        $guru_id_update = (int)$_POST['guru_id'];
        $lokasi_ids = isset($_POST['lokasi_ids']) ? $_POST['lokasi_ids'] : [];

        // Ambil nama guru untuk keperluan log
        $nama_guru_log = "ID " . $guru_id_update;
        $cek_guru = $koneksi->query("SELECT nama_guru FROM guru WHERE guru_id = $guru_id_update");
        if ($cek_guru && $cek_guru->num_rows > 0) {
            $nama_guru_log = $cek_guru->fetch_assoc()['nama_guru'];
        }

        $koneksi->query("UPDATE lokasi_pkl SET guru_id = NULL WHERE guru_id = $guru_id_update");
        if (!empty($lokasi_ids)) {
            $ids = array_map('intval', $lokasi_ids);
            $ids_string = implode(',', $ids);
            $koneksi->query("UPDATE lokasi_pkl SET guru_id = $guru_id_update WHERE lokasi_id IN ($ids_string)");
        }
        
        // --- TRIGGER LOG AKTIVITAS ---
        catatLog($koneksi, $current_user_id, "Memperbarui daftar penugasan instansi bimbingan PKL untuk guru: " . $nama_guru_log);

        header("Location: guru-list.php?status=success_bimbingan");
        exit;
    }
}

// =========================================================================================
// LOGIKA BACKEND 2: TAMBAH USER / GURU BARU
// =========================================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['proses_add_user'])) {
    if ($can_manage_users) {
        $username = trim($_POST['username']);
        $full_name = trim($_POST['full_name']);
        $role = strtolower($_POST['role']);
        $password = $_POST['password'];
        $no_hp = trim($_POST['no_hp']);
        
        $photo_baru = '';

        if (empty($password) || empty($username) || empty($full_name)) {
            header("Location: guru-list.php?status=error_empty"); exit;
        } else {
            // Upload Foto Baru
            $upload_dir = '../uploads/profiles/';
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
            if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
                $file_ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
                if (in_array($file_ext, $allowed_ext)) {
                    $new_file_name = 'prof_new_' . time() . '.' . $file_ext;
                    if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_dir . $new_file_name)) {
                        $photo_baru = $new_file_name;
                    }
                }
            }

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_stmt = $koneksi->prepare("INSERT INTO users (username, password, full_name, role, profile_photo) VALUES (?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("sssss", $username, $hashed_password, $full_name, $role, $photo_baru);

            if ($insert_stmt->execute()) {
                if ($role === 'pembimbing') {
                    $new_user_id = $insert_stmt->insert_id;
                    $insert_guru = $koneksi->prepare("INSERT INTO guru (nama_guru, no_hp, user_id) VALUES (?, ?, ?)");
                    $insert_guru->bind_param("ssi", $full_name, $no_hp, $new_user_id);
                    $insert_guru->execute();
                    $insert_guru->close();
                }

                // --- TRIGGER LOG AKTIVITAS (ADD) ---
                catatLog($koneksi, $current_user_id, "Menambahkan pengguna sistem baru: {$full_name} (Username: {$username}, Role: {$role})");

                header("Location: guru-list.php?status=add_user_success"); exit;
            } else {
                if ($koneksi->errno == 1062) { header("Location: guru-list.php?status=duplicate_user"); exit; } 
                else { header("Location: guru-list.php?status=error_user"); exit; }
            }
            $insert_stmt->close();
        }
    }
}

// =========================================================================================
// LOGIKA BACKEND 3: EDIT USER / GURU
// =========================================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['proses_edit_user'])) {
    if ($can_manage_users) {
        $user_id_post = (int)$_POST['edit_user_id'];
        $username = trim($_POST['edit_username']);
        $full_name = trim($_POST['edit_full_name']);
        $password_new = trim($_POST['edit_password_new']); 
        $role = strtolower($_POST['edit_role']);
        $no_hp = trim($_POST['edit_no_hp']);
        $photo_lama = $_POST['profile_photo_lama'] ?? '';
        $photo_baru = $photo_lama;

        // Proteksi & Deteksi Perubahan Data (Untuk Log)
        $stmt_check = $koneksi->prepare("SELECT role, username, full_name, profile_photo FROM users WHERE id = ?");
        $stmt_check->bind_param("i", $user_id_post);
        $stmt_check->execute();
        $res_check = $stmt_check->get_result()->fetch_assoc();
        
        $old_role = strtolower($res_check['role'] ?? '');
        $old_username = strtolower($res_check['username'] ?? '');
        $old_fullname = $res_check['full_name'] ?? '';
        $old_photo = $res_check['profile_photo'] ?? '';
        $stmt_check->close();

        // Ambil No HP Lama jika dia guru
        $old_hp = '';
        if ($old_role === 'pembimbing') {
            $cek_hp = $koneksi->query("SELECT no_hp FROM guru WHERE user_id = $user_id_post");
            if ($cek_hp && $cek_hp->num_rows > 0) {
                $old_hp = $cek_hp->fetch_assoc()['no_hp'];
            }
        }

        if ($current_username === 'ade' && $old_role === 'admin') {
            header("Location: guru-list.php?status=restricted"); exit;
        }

        // Upload Foto Baru
        $upload_dir = '../uploads/profiles/';
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
            $file_ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($file_ext, $allowed_ext)) {
                $new_file_name = 'prof_' . $user_id_post . '_' . time() . '.' . $file_ext;
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_dir . $new_file_name)) {
                    if (!empty($photo_lama) && file_exists($upload_dir . $photo_lama)) { unlink($upload_dir . $photo_lama); }
                    $photo_baru = $new_file_name;
                }
            }
        }

        // Identifikasi perbandingan untuk log
        $perubahan = [];
        if ($old_username != $username) $perubahan[] = "Username";
        if ($old_fullname != $full_name) $perubahan[] = "Nama Lengkap";
        if ($old_role != $role) $perubahan[] = "Hak Akses (Role)";
        if (!empty($password_new)) $perubahan[] = "Password Akun";
        if ($role === 'pembimbing' && $old_hp != $no_hp) $perubahan[] = "No HP / WhatsApp";
        if ($old_photo != $photo_baru) $perubahan[] = "Foto Profil";

        // Sinkronisasi Tabel Guru jika role berubah
        if ($old_role === 'pembimbing' && $role !== 'pembimbing') {
            $cek_g = $koneksi->query("SELECT guru_id FROM guru WHERE user_id = $user_id_post")->fetch_assoc();
            if ($cek_g) {
                $g_id = $cek_g['guru_id'];
                $koneksi->query("UPDATE lokasi_pkl SET guru_id = NULL WHERE guru_id = $g_id");
                $koneksi->query("DELETE FROM guru WHERE guru_id = $g_id");
            }
        } elseif ($old_role !== 'pembimbing' && $role === 'pembimbing') {
            $koneksi->query("INSERT IGNORE INTO guru (nama_guru, no_hp, user_id) VALUES ('$full_name', '$no_hp', $user_id_post)");
        } elseif ($old_role === 'pembimbing' && $role === 'pembimbing') {
            $koneksi->query("UPDATE guru SET nama_guru = '$full_name', no_hp = '$no_hp' WHERE user_id = $user_id_post");
        }

        // Query Update Table Users
        $sql_parts = ["username = ?", "full_name = ?", "role = ?", "profile_photo = ?"];
        $params = [$username, $full_name, $role, $photo_baru];
        $types = "ssss";

        if (!empty($password_new)) {
            $sql_parts[] = "password = ?";
            $params[] = password_hash($password_new, PASSWORD_DEFAULT);
            $types .= "s";
        }
        $params[] = $user_id_post;
        $types .= "i";

        $sql = "UPDATE users SET " . implode(', ', $sql_parts) . " WHERE id = ?";
        $update_stmt = $koneksi->prepare($sql);
        $update_stmt->bind_param($types, ...$params);

        if ($update_stmt->execute()) {
            // --- TRIGGER LOG AKTIVITAS (UPDATE DETAIL) ---
            if (count($perubahan) > 0) {
                $detail_ubah = implode(", ", $perubahan);
                $pesan_log = "Memperbarui data akun pengguna: " . $old_fullname . " (Detail yang diubah: " . $detail_ubah . ")";
                catatLog($koneksi, $current_user_id, $pesan_log);
            }

            if ($current_username === $old_username) { $_SESSION['username'] = $username; }
            header("Location: guru-list.php?status=edit_user_success"); exit;
        } else {
            header("Location: guru-list.php?status=duplicate_user"); exit;
        }
        $update_stmt->close();
    }
}

// =========================================================================================
// LOGIKA BACKEND 4: HAPUS USER / GURU
// =========================================================================================
if (isset($_GET['action']) && $_GET['action'] == 'delete_user' && isset($_GET['id'])) {
    if ($can_manage_users) {
        $del_user_id = (int)$_GET['id'];
        
        if ($del_user_id === (int)$current_user_id) {
            header("Location: guru-list.php?status=self_delete"); exit();
        }

        // Proteksi Ade & Ambil Nama untuk Log
        $nama_dihapus = "ID " . $del_user_id;
        $cek_data = $koneksi->query("SELECT role, full_name FROM users WHERE id = $del_user_id")->fetch_assoc();
        
        if ($cek_data) {
            $nama_dihapus = $cek_data['full_name'];
            if ($current_username === 'ade' && strtolower($cek_data['role']) === 'admin') { 
                header("Location: guru-list.php?status=restricted"); exit(); 
            }
        }

        // Hapus referensi guru & lokasi terlebih dahulu
        $cek_guru = $koneksi->query("SELECT guru_id FROM guru WHERE user_id = $del_user_id")->fetch_assoc();
        if ($cek_guru) {
            $g_id = $cek_guru['guru_id'];
            $koneksi->query("UPDATE lokasi_pkl SET guru_id = NULL WHERE guru_id = $g_id");
            $koneksi->query("DELETE FROM guru WHERE guru_id = $g_id");
        }

        // Hapus User
        $delete_stmt = $koneksi->prepare("DELETE FROM users WHERE id = ?");
        $delete_stmt->bind_param("i", $del_user_id);
        if ($delete_stmt->execute()) { 
            // --- TRIGGER LOG AKTIVITAS (DELETE) ---
            catatLog($koneksi, $current_user_id, "Menghapus permanen akun pengguna sistem: " . $nama_dihapus);

            header("Location: guru-list.php?status=delete_user_success"); 
        } 
        else { header("Location: guru-list.php?status=error_user"); }
        $delete_stmt->close();
        exit();
    }
}

// =========================================================================================
// PERSIAPAN DATA SISWA UNTUK DROPDOWN LOKASI
// =========================================================================================
$siswa_per_lokasi = [];
$res_siswa_lok = $koneksi->query("SELECT lokasi_id, nama, kelas FROM peserta_didik WHERE lokasi_id IS NOT NULL AND lokasi_id != 0 ORDER BY nama ASC");
if ($res_siswa_lok) {
    while ($s = $res_siswa_lok->fetch_assoc()) {
        $siswa_per_lokasi[$s['lokasi_id']][] = $s;
    }
}

// =========================================================================================
// QUERY DATA UNTUK TABEL UTAMA (GABUNGAN USER & GURU)
// =========================================================================================
$page = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;
$start = ($page - 1) * $guru_per_page;

// Logika Pembatasan Hak Akses Lihat Data
$where_users = "u.role IN ('admin', 'pembimbing')";
if (!$can_manage_users) {
    // Jika hanya pembimbing biasa, hilangkan peran Admin dari tabel
    $where_users = "u.role = 'pembimbing'";
}

$total_result = $koneksi->query("SELECT COUNT(id) FROM users u WHERE $where_users");
$total_rows = $total_result->fetch_array()[0];
$total_pages = ceil($total_rows / $guru_per_page);

// Diurutkan ID lokasi ASC agar sync
$gabungan_query = "
    SELECT 
        u.id AS user_id, u.username, u.full_name, u.role, u.profile_photo,
        g.guru_id, g.no_hp,
        COUNT(l.lokasi_id) AS total_lokasi_bimbingan,
        GROUP_CONCAT(l.nama_lokasi ORDER BY l.lokasi_id ASC SEPARATOR '@@@') AS list_lokasi,
        GROUP_CONCAT(l.lokasi_id ORDER BY l.lokasi_id ASC SEPARATOR ',') AS list_id_lokasi
    FROM users u
    LEFT JOIN guru g ON u.id = g.user_id
    LEFT JOIN lokasi_pkl l ON g.guru_id = l.guru_id
    WHERE $where_users
    GROUP BY u.id
    ORDER BY 
        CASE u.role WHEN 'admin' THEN 1 WHEN 'pembimbing' THEN 2 ELSE 3 END ASC, 
        u.full_name ASC
    LIMIT $start, $guru_per_page
";
$gabungan_data = $koneksi->query($gabungan_query);

// Ambil SELURUH Data Lokasi (Untuk List Checkbox Instansi)
$all_lokasi = [];
$res_lok = $koneksi->query("SELECT l.lokasi_id, l.nama_lokasi, l.guru_id, g.nama_guru FROM lokasi_pkl l LEFT JOIN guru g ON l.guru_id = g.guru_id ORDER BY l.nama_lokasi ASC");
if ($res_lok) { while ($r = $res_lok->fetch_assoc()) { $all_lokasi[] = $r; } }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Daftar Pengguna & Pembimbing | Si Mantap PKL</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --mantap-blue-dark: #0f172a;
            --mantap-blue-main: #1e40af;
            --mantap-blue-light: #3b82f6;
            --mantap-blue-soft: #eff6ff;
        }

        body { font-family: 'Poppins', sans-serif; background-color: #f8fafc; color: #334155; margin: 0; overflow-x: hidden !important; }
        .main-content-wrapper { max-width: 100% !important; width: 100% !important; box-sizing: border-box !important; box-shadow: none !important; }
        .admin-main-content { padding: 20px 25px 30px 25px !important; box-sizing: border-box !important; clear: both; width: 100% !important; }

        .page-header-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; width: 100%; flex-wrap: wrap; gap: 15px; }
        .page-header-controls h1 { font-weight: 700; color: #0f172a; font-size: 1.8rem; margin: 0; position: relative; }
        .page-header-controls h1::after { content: ''; position: absolute; left: 0; bottom: -5px; width: 50px; height: 4px; background: var(--mantap-blue-main); border-radius: 2px; }

        .btn-manage { color: white !important; padding: 10px 18px; border-radius: 6px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; font-size: 13px; border: none; cursor: pointer; transition: 0.2s; font-family: 'Poppins', sans-serif;}
        .btn-green { background-color: #10b981 !important; border: none !important; } 
        .btn-green:hover { background-color: #059669 !important; transform: translateY(-2px);}

        .glass-panel-table { background: white !important; padding: 25px !important; border-radius: 12px !important; border: 1px solid #cbd5e1 !important; box-sizing: border-box; width: 100%; margin-bottom: 40px;}
        .table-container-fixed { width: 100%; overflow-x: auto; box-sizing: border-box; border: none !important; }

        .custom-table { width: 100%; table-layout: fixed; border-collapse: collapse; background: white; border-radius: 4px; overflow: hidden; border: 2px solid #1e40af; min-width: 1000px;}
        .custom-table th { background: #1e40af; color: white; padding: 14px 6px; font-size: 13px; text-transform: uppercase; font-weight: 700; text-align: center; border: 1px solid #cbd5e1; box-sizing: border-box; }

        .custom-table th.col-no { width: 60px; }
        .custom-table th.col-pengguna { width: 25%; text-align: left; padding-left: 15px; }
        .custom-table th.col-hp { width: 15%; }
        .custom-table th.col-lokasi { width: 45%; text-align: left; padding-left: 15px; }
        .custom-table th.col-aksi { width: 90px; }

        .custom-table td { padding: 12px 10px; font-size: 13px; vertical-align: top; word-wrap: break-word; overflow-wrap: break-word; text-align: center; border: 1px solid #cbd5e1; color: #0f172a; background-color: white !important; box-sizing: border-box; }
        .custom-table tbody tr:hover td { background-color: #f8fafc !important; }

        .user-meta-block { display: flex; align-items: center; gap: 12px; text-align: left; }
        .prof-thumb { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #e2e8f0; }
        .user-name-title { font-weight: 700; color: var(--mantap-blue-dark); font-size: 13.5px; display: block; margin-bottom: 2px; text-transform: uppercase; }
        .user-role { padding: 3px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; display: inline-block; letter-spacing: 0.5px;}
        .role-admin { background-color: #fef2f2; color: #ef4444; border: 1px solid #fecaca;}
        .role-pembimbing { background-color: var(--mantap-blue-soft); color: var(--mantap-blue-main); border: 1px solid #bfdbfe;}

        /* DROPDOWN LOKASI SISWA - WHITE THEME & CLEAN (Sesuai Referensi) */
        .lokasi-bimbingan-list { list-style: none; padding: 0; margin: 0; text-align: left; display: flex; flex-direction: column; gap: 8px; }
        .lokasi-item { border-radius: 4px; border: 1px solid #cbd5e1; margin-bottom: 8px; background: white; transition: 0.2s; }
        .lokasi-header { padding: 10px 15px; font-size: 13px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: space-between; user-select: none; color: #0f172a; background-color: white; }
        .lokasi-header:hover { background-color: #f8fafc; }
        .siswa-dropdown { background-color: #fff; border-top: 1px dashed #cbd5e1; padding: 12px 15px; display: none; font-size: 13px; color: #334155; }
        .siswa-dropdown ol { padding-left: 20px; margin: 0; list-style-type: decimal; }
        .siswa-dropdown li { padding: 4px 0; font-weight: 500; }

        .dashboard-btn-group { display: flex; flex-direction: column; gap: 6px; align-items: center; }
        .btn-action-sm { color: white !important; padding: 5px 0; border-radius: 4px; text-decoration: none; font-size: 11px; font-weight: 600; display: block; width: 75px; text-align: center; transition: 0.2s; border: none; cursor: pointer; font-family: 'Poppins', sans-serif;}
        .btn-kelola { background-color: #f59e0b; box-shadow: 0 2px 4px rgba(245,158,11,0.15); } .btn-kelola:hover { background-color: #d97706; }
        .btn-edit { background-color: var(--mantap-blue-main); box-shadow: 0 2px 4px rgba(30,64,175,0.15); } .btn-edit:hover { background-color: #1e3a8a; }
        .btn-delete { background-color: #ef4444; box-shadow: 0 2px 4px rgba(239,68,68,0.15); } .btn-delete:hover { background-color: #dc2626; }
        .lock-icon { color: #94a3b8; font-size: 11px; font-style: italic; font-weight: 500; }

        .pagination-container { display: flex; justify-content: center; gap: 5px; margin-top: 25px; width: 100%; }
        .pagination-link { padding: 8px 16px; border: 1px solid #cbd5e1; text-decoration: none; color: #475569; font-weight: 600; font-size: 13px; border-radius: 6px; background: white; transition: 0.2s; }
        .pagination-link:hover, .pagination-link.active { background-color: var(--mantap-blue-main); border-color: var(--mantap-blue-main); color: white !important; }

        /* CSS UNTUK MODAL POP-UP (FLAT & CLEAN) */
        .mantap-modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(15, 23, 42, 0.6); backdrop-filter: blur(2px);}
        .mantap-modal-content { background-color: white; margin: 3% auto; padding: 25px; border-radius: 8px; width: 90%; max-width: 600px; position: relative; border: 1px solid #cbd5e1; box-shadow: 0 10px 25px rgba(0,0,0,0.1); max-height: 90vh; overflow-y: auto; }
        .close-modal-btn { position: absolute; right: 15px; top: 15px; background: none; border: none; font-size: 24px; color: #64748b; cursor: pointer; line-height: 1; transition: 0.2s; }
        .close-modal-btn:hover { color: #ef4444; }
        
        .modal-title { font-size: 18px; font-weight: 800; color: #0f172a; margin: 0 0 5px 0; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 20px;}
        
        .modal-grid-landscape { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .modal-form-group { text-align: left; margin-bottom: 15px; }
        .modal-form-group label { display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px; color: #0f172a; }
        .modal-form-group input, .modal-form-group select { width: 100%; padding: 11px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: 'Poppins', sans-serif; font-size: 13px; background-color: #f8fafc; box-sizing: border-box; outline: none; transition: 0.2s;}
        .modal-form-group input:focus, .modal-form-group select:focus { border-color: var(--mantap-blue-main); background-color: white; }
        
        .btn-submit-modal { color: white; padding: 12px 20px; border: none; border-radius: 6px; font-weight: 600; width: 100%; cursor: pointer; font-family: 'Poppins', sans-serif; transition: 0.2s; display:flex; align-items:center; justify-content:center; gap:8px;}
        .btn-blue { background: var(--mantap-blue-main); } .btn-blue:hover { background: #1e3a8a; }
        .btn-green-m { background: #10b981; } .btn-green-m:hover { background: #059669; }
        .btn-orange { background: #f59e0b; } .btn-orange:hover { background: #d97706; }

        .lokasi-checkbox-list { max-height: 250px; overflow-y: auto; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; background: #f8fafc; margin-bottom: 20px; }
        .checkbox-item { display: flex; align-items: flex-start; gap: 10px; padding: 10px; border-bottom: 1px solid #e2e8f0; cursor: pointer; transition: 0.2s; }
        .checkbox-item:hover { background: white; }
        .checkbox-item:last-child { border-bottom: none; }
        .checkbox-item input { margin-top: 3px; cursor: pointer; width: 16px; height: 16px;}
        .cb-label { font-size: 13.5px; font-weight: 600; color: #0f172a; display: flex; flex-direction: column; }
        .owner-tag { font-size: 11px; font-weight: 500; margin-top: 2px;}

        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 
            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; padding: 5px 0px !important; }
            .page-header-controls h1 { font-size: 1.4rem !important; }
            .btn-manage { width: 100% !important; justify-content: center !important; padding: 11px !important; border-radius: 8px !important; font-size: 13.5px !important; }
            .glass-panel-table { padding: 16px 10px !important; border-radius: 12px !important; border: 2px solid #cbd5e1 !important; }
            .table-container-fixed { display: block !important; width: 100% !important; overflow-x: auto !important; }
            .admin-main-content table { table-layout: auto !important; min-width: 820px !important; }
            .admin-main-content table th, .admin-main-content table td { padding: 12px 10px !important; }
            .admin-main-content table th.col-no, .admin-main-content table th.col-pengguna, .admin-main-content table th.col-hp, .admin-main-content table th.col-lokasi, .admin-main-content table th.col-aksi { width: auto !important; }
            .admin-main-content table td:nth-child(2) { text-align: left !important; padding-left: 10px !important; }
            .pagination-link { padding: 10px 18px !important; font-size: 14px !important; }
            
            .modal-grid-landscape { grid-template-columns: 1fr; gap: 0; }
            .mantap-modal-content { margin: 5% auto; padding: 20px; width: 95%; max-height: 90vh;}
            .dashboard-btn-group { flex-direction: row; flex-wrap: wrap; justify-content:center;}
            .btn-action-sm { width: 70px; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; include 'panel/navbar.php'; ?>

<div class="main-content-wrapper">
    <div class="admin-main-content"> 
        
        <?php if ($can_manage_users): ?>
        <div class="page-header-controls">
            <h1>Manajemen Pengguna & Guru Pembimbing</h1>
            <button class="btn-manage btn-green" onclick="document.getElementById('modalAddUser').style.display='block'">
                <i class="fas fa-user-plus"></i> Tambah Pengguna Baru
            </button>
        </div>
        <?php else: ?>
        <div class="page-header-controls">
            <h1>Daftar Guru Pembimbing</h1>
        </div>
        <?php endif; ?>

        <div class="glass-panel-table">
            <div class="table-container-fixed">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th class="col-no">NO</th> 
                            <th class="col-pengguna">PENGGUNA & ROLE</th> 
                            <th class="col-hp">NO HP</th> 
                            <th class="col-lokasi">LOKASI INSTANSI BIMBINGAN</th> 
                            <?php if ($can_manage_users): ?>
                                <th class="col-aksi">AKSI</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($gabungan_data->num_rows == 0): ?>
                            <tr><td colspan="<?php echo $can_manage_users ? '5' : '4'; ?>" style="color: #ef4444; font-style: italic; padding: 25px;">Tidak ada data pengguna yang ditemukan.</td></tr>
                        <?php endif; ?>
                        
                        <?php $no = $start + 1; while ($row = $gabungan_data->fetch_assoc()): 
                            $is_guru = strtolower($row['role']) === 'pembimbing';
                            $role_class = ($row['role'] == 'admin') ? 'role-admin' : 'role-pembimbing';
                            $prof_img = !empty($row['profile_photo']) ? '../uploads/profiles/'.$row['profile_photo'] : '../img/default-profile.png';
                            
                            $can_edit = false;
                            if ($can_manage_users) {
                                $can_edit = true;
                                if ($current_username === 'ade' && strtolower($row['role']) === 'admin') { $can_edit = false; }
                            }
                        ?>
                        <tr>
                            <td style="font-weight: 700; color: #64748b;"><?php echo $no++; ?></td>
                            
                            <!-- DATA PENGGUNA -->
                            <td class="text-left">
                                <div class="user-meta-block">
                                    <img src="<?= $prof_img ?>" class="prof-thumb" alt="Prof">
                                    <div>
                                        <span class="user-name-title"><?php echo htmlspecialchars($row['full_name']); ?></span>
                                        <span style="display:block; font-size:11.5px; color:#64748b; margin-bottom:4px;">@<?php echo htmlspecialchars($row['username']); ?></span>
                                        <span class="user-role <?= $role_class ?>"><?= htmlspecialchars(str_replace('pembimbing', 'guru', strtolower($row['role']))) ?></span>
                                    </div>
                                </div>
                            </td>
                            
                            <!-- NO HP -->
                            <td style="font-weight: 600; color: #475569;">
                                <?php echo $is_guru && !empty($row['no_hp']) ? htmlspecialchars($row['no_hp']) : '-'; ?>
                            </td> 
                            
                            <!-- LOKASI INSTANSI DENGAN DROPDOWN SISWA -->
                            <td class="text-left">
                                <?php 
                                if ($is_guru) {
                                    if ($row['total_lokasi_bimbingan'] > 0) {
                                        $lokasi_array = explode('@@@', $row['list_lokasi']);
                                        $id_lokasi_array = explode(',', $row['list_id_lokasi']);
                                        
                                        echo '<ul class="lokasi-bimbingan-list">'; 
                                        foreach ($lokasi_array as $index => $lokasi) {
                                            $id_lok = $id_lokasi_array[$index] ?? 0;
                                            
                                            echo '<li class="lokasi-item">';
                                            
                                            // Header Trigger Dropdown (NO ICON, BLACK FONT, WHITE BG)
                                            echo '  <div class="lokasi-header" onclick="toggleDropdownSiswa(this)">';
                                            echo '      <span>' . htmlspecialchars(trim($lokasi)) . '</span>';
                                            echo '      <i class="fas fa-chevron-down" style="font-size:10px;"></i>';
                                            echo '  </div>';
                                            
                                            // Konten Dropdown Siswa (ORDERED LIST)
                                            echo '  <div class="siswa-dropdown" style="display:none;">';
                                            if (isset($siswa_per_lokasi[$id_lok])) {
                                                echo '<ol>';
                                                foreach ($siswa_per_lokasi[$id_lok] as $siswa) {
                                                    echo '<li>' . htmlspecialchars($siswa['nama']) . ' (' . htmlspecialchars($siswa['kelas']) . ')</li>';
                                                }
                                                echo '</ol>';
                                            } else {
                                                echo '<span style="font-style:italic; color:#94a3b8;">Belum ada siswa penempatan di lokasi ini.</span>';
                                            }
                                            echo '  </div>';
                                            
                                            echo '</li>';
                                        }
                                        echo '</ul>';
                                    } else {
                                        echo '<span style="color: #ef4444; font-size: 11.5px; font-weight: 500; font-style: italic;">Belum dikaitkan instansi PKL.</span>';
                                    }
                                } else {
                                    echo '<span style="color: #94a3b8; font-size: 11.5px; font-style: italic;">Bukan Pembimbing</span>';
                                }
                                ?>
                            </td>
                            
                            <!-- AKSI HANYA UNTUK ADMIN -->
                            <?php if ($can_manage_users): ?>
                            <td>
                                <div class="dashboard-btn-group">
                                    <?php if ($can_edit): ?>
                                        <?php if ($is_guru): ?>
                                            <button type="button" class="btn-action-sm btn-kelola" onclick="bukaModalInstansi(<?= $row['guru_id'] ?>, '<?= htmlspecialchars($row['full_name'], ENT_QUOTES) ?>', '<?= $row['list_id_lokasi'] ?>')">
                                                <i class="fas fa-list-check"></i> Instansi
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button type="button" class="btn-action-sm btn-edit" onclick="bukaModalEditUser(<?= $row['user_id'] ?>, '<?= htmlspecialchars($row['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['full_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['role'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['no_hp'] ?? '', ENT_QUOTES) ?>', '<?= $prof_img ?>')">
                                            <i class="fas fa-edit"></i> Ubah
                                        </button>
                                        
                                        <button type="button" class="btn-action-sm btn-delete" onclick="konfirmasiHapusUser(<?= $row['user_id'] ?>)">
                                            <i class="fas fa-trash-alt"></i> Hapus
                                        </button>
                                    <?php else: ?>
                                        <span class="lock-icon"><i class="fas fa-lock me-1"></i> Terkunci</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?p=<?php echo $i; ?>" class="pagination-link <?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODALS SECTION                                                            -->
<!-- ========================================================================= -->

<?php if ($can_manage_users): ?>

<!-- 1. MODAL TAMBAH USER / GURU BARU (URUTAN DISESUAIKAN) -->
<div class="mantap-modal" id="modalAddUser">
    <div class="mantap-modal-content">
        <button type="button" class="close-modal-btn" onclick="document.getElementById('modalAddUser').style.display='none'">&times;</button>
        <h2 class="modal-title"><i class="fas fa-user-plus text-success me-2"></i>Tambah Pengguna Baru</h2>
        
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="proses_add_user" value="1">
            
            <div class="modal-form-group">
                <label>Ganti Foto Profil (Opsional):</label>
                <input type="file" name="profile_photo" accept="image/*" style="padding: 8px;">
            </div>

            <div class="modal-form-group">
                <label>Nama Lengkap</label>
                <input type="text" name="full_name" placeholder="Masukkan nama asli" required>
            </div>

            <div class="modal-form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Tanpa spasi" required>
            </div>

            <div class="modal-form-group">
                <label>Password Baru (Wajib)</label>
                <input type="password" name="password" placeholder="Sandi login" required>
            </div>

            <div class="modal-form-group">
                <label>Hak Akses (Role)</label>
                <select name="role" id="add_role" required onchange="toggleAddNoHp()">
                    <option value="pembimbing">Pembimbing (Guru)</option>
                    <?php if ($user_level === 'admin'): ?>
                        <option value="admin">Administrator</option>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Diubah agar selalu tampil, required dihandle via JS -->
            <div class="modal-form-group" id="add_hp_group">
                <label>No HP (Wajib untuk Guru Pembimbing)</label>
                <input type="text" name="no_hp" id="add_no_hp" placeholder="Contoh: 0812xxx" required>
            </div>

            <button type="submit" class="btn-submit-modal btn-green-m"><i class="fas fa-save me-1"></i> Daftarkan Pengguna</button>
        </form>
    </div>
</div>

<!-- 2. MODAL EDIT USER / GURU (URUTAN DISESUAIKAN) -->
<div class="mantap-modal" id="modalEditUser">
    <div class="mantap-modal-content">
        <button type="button" class="close-modal-btn" onclick="document.getElementById('modalEditUser').style.display='none'">&times;</button>
        <h2 class="modal-title"><i class="fas fa-user-edit text-warning me-2"></i>Ubah Data Pengguna</h2>
        
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="proses_edit_user" value="1">
            <input type="hidden" name="edit_user_id" id="eu_id">
            <input type="hidden" name="profile_photo_lama" id="eu_photo_lama">
            
            <div style="display:flex; align-items:center; gap:15px; margin-bottom:15px; padding:10px; background:#f8fafc; border-radius:8px; border:1px solid #cbd5e1;">
                <img src="" id="eu_img_preview" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid #cbd5e1;">
                <div style="flex:1;">
                    <label style="font-size:11px; margin-bottom:2px; display:block; color:#64748b;">Ganti Foto Profil (Opsional):</label>
                    <input type="file" name="profile_photo" accept="image/*" style="width:100%; font-size:11px; padding:4px;">
                </div>
            </div>

            <div class="modal-form-group">
                <label>Nama Lengkap</label>
                <input type="text" name="edit_full_name" id="eu_fullname" required>
            </div>

            <div class="modal-form-group">
                <label>Username</label>
                <input type="text" name="edit_username" id="eu_username" required>
            </div>

            <div class="modal-form-group">
                <label>Password Baru (Opsional)</label>
                <input type="password" name="edit_password_new" placeholder="Kosongkan jika tetap">
            </div>

            <div class="modal-form-group">
                <label>Hak Akses (Role)</label>
                <select name="edit_role" id="eu_role" required onchange="toggleEditNoHp()">
                    <option value="pembimbing">Pembimbing (Guru)</option>
                    <?php if ($user_level === 'admin'): ?>
                        <option value="admin">Administrator</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="modal-form-group" id="edit_hp_group">
                <label>No HP (Wajib untuk Guru Pembimbing)</label>
                <input type="text" name="edit_no_hp" id="eu_nohp" placeholder="Contoh: 0812xxx" required>
            </div>
            
            <button type="submit" class="btn-submit-modal btn-orange"><i class="fas fa-save me-1"></i> Simpan Perubahan</button>
        </form>
    </div>
</div>

<!-- 3. MODAL KELOLA INSTANSI BIMBINGAN -->
<div class="mantap-modal" id="modalKelolaInstansi">
    <div class="mantap-modal-content">
        <button type="button" class="close-modal-btn" onclick="document.getElementById('modalKelolaInstansi').style.display='none'">&times;</button>
        <h2 class="modal-title"><i class="fas fa-list-check text-primary me-2"></i>Kelola Instansi Bimbingan</h2>
        <p style="font-size: 13px; color: #64748b; margin: 0 0 20px 0;">Pilih lokasi instansi yang akan dibimbing oleh: <strong id="namaGuruModal" style="color: var(--mantap-blue-main);"></strong></p>
        
        <form method="POST" action="">
            <input type="hidden" name="proses_update_bimbingan" value="1">
            <input type="hidden" name="guru_id" id="modal_guru_id_input">
            
            <div class="lokasi-checkbox-list">
                <?php if (count($all_lokasi) > 0): ?>
                    <?php foreach($all_lokasi as $lok): ?>
                        <label class="checkbox-item">
                            <input type="checkbox" name="lokasi_ids[]" value="<?= $lok['lokasi_id'] ?>" class="cb-lokasi" data-owner="<?= $lok['guru_id'] ?>">
                            <span class="cb-label">
                                <?= htmlspecialchars($lok['nama_lokasi']) ?>
                                <?php if($lok['guru_id']): ?>
                                    <small class="owner-tag" id="tag-owner-<?= $lok['lokasi_id'] ?>">(Saat ini diampu: <?= htmlspecialchars($lok['nama_guru']) ?>)</small>
                                <?php else: ?>
                                    <small class="owner-tag" style="color:#94a3b8;" id="tag-owner-<?= $lok['lokasi_id'] ?>">(Belum ada pembimbing)</small>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="padding: 20px; text-align: center; color: #ef4444; font-size: 13px;">Belum ada data lokasi PKL di database.</div>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn-submit-modal btn-blue"><i class="fas fa-save"></i> Simpan Penugasan</button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include 'panel/footer.php'; ?>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const urlParams = new URLSearchParams(window.location.search);
        const statusMsg = urlParams.get('status');

        if (statusMsg) {
            let sIcon = 'success', sTitle = 'Berhasil!', sText = '';
            if (statusMsg === "success_bimbingan") sText = 'Penugasan instansi untuk pembimbing diperbarui.';
            else if (statusMsg === "add_user_success") sText = 'Pengguna baru berhasil ditambahkan!';
            else if (statusMsg === "edit_user_success") sText = 'Data pengguna berhasil diperbarui!';
            else if (statusMsg === "delete_user_success") sText = 'Akun pengguna berhasil dihapus permanen.';
            else if (statusMsg === "duplicate_user") { sIcon = 'error'; sTitle = 'Gagal!'; sText = 'Username sudah digunakan orang lain.'; }
            else if (statusMsg === "self_delete") { sIcon = 'warning'; sTitle = 'Aksi Ditolak!'; sText = 'Anda tidak dapat menghapus akun Anda sendiri.'; }
            else if (statusMsg === "restricted") { sIcon = 'error'; sTitle = 'Akses Ilegal!'; sText = 'Anda tidak memiliki hak akses ini.'; }
            else if (statusMsg === "error_user" || statusMsg === "error_empty") { sIcon = 'error'; sTitle = 'Error'; sText = 'Terjadi kesalahan sistem atau form kosong.'; }

            if(sText !== '') {
                Swal.fire({ icon: sIcon, title: sTitle, text: sText, confirmButtonColor: '#1e40af' });
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        }

        window.addEventListener('click', (e) => { 
            const modals = ['modalKelolaInstansi', 'modalAddUser', 'modalEditUser'];
            modals.forEach(m => {
                const el = document.getElementById(m);
                if (el && e.target === el) el.style.display = 'none';
            });
        });
        
        // Inisialisasi awal saat load
        if(document.getElementById('add_role')){ toggleAddNoHp(); }
    });

    // --- FITUR DROPDOWN SISWA ---
    function toggleDropdownSiswa(element) {
        const dropdown = element.nextElementSibling;
        const icon = element.querySelector('.fa-chevron-down, .fa-chevron-up');
        if (dropdown.style.display === 'none') {
            dropdown.style.display = 'block';
            icon.classList.remove('fa-chevron-down');
            icon.classList.add('fa-chevron-up');
        } else {
            dropdown.style.display = 'none';
            icon.classList.remove('fa-chevron-up');
            icon.classList.add('fa-chevron-down');
        }
    }

    // --- TOGGLE REQUIRED NO HP ---
    function toggleAddNoHp() {
        const role = document.getElementById('add_role').value;
        const hpInput = document.getElementById('add_no_hp');
        if (role === 'pembimbing') { hpInput.required = true; } 
        else { hpInput.required = false; }
    }

    function toggleEditNoHp() {
        const role = document.getElementById('eu_role').value;
        const hpInput = document.getElementById('eu_nohp');
        if (role === 'pembimbing') { hpInput.required = true; } 
        else { hpInput.required = false; }
    }

    // --- FUNGSI MODAL INSTANSI ---
    function bukaModalInstansi(guruId, namaGuru, listIdLokasiStr) {
        document.getElementById('modalKelolaInstansi').style.display = 'block';
        document.getElementById('namaGuruModal').textContent = namaGuru;
        document.getElementById('modal_guru_id_input').value = guruId;

        const checkboxes = document.querySelectorAll('.cb-lokasi');
        const listLokasiMilikGuru = listIdLokasiStr ? listIdLokasiStr.split(',') : [];

        checkboxes.forEach(cb => {
            const lokId = cb.value;
            const tagOwner = document.getElementById('tag-owner-' + lokId);
            if (listLokasiMilikGuru.includes(lokId)) {
                cb.checked = true;
                if(tagOwner) { tagOwner.textContent = '(Sudah Anda ampu)'; tagOwner.className = 'owner-tag owner-self'; }
            } else {
                cb.checked = false;
                if(tagOwner) {
                    tagOwner.className = 'owner-tag';
                    if (!cb.dataset.owner) { tagOwner.textContent = '(Belum ada pembimbing)'; tagOwner.style.color = '#94a3b8'; } 
                    else { tagOwner.style.color = '#ef4444'; }
                }
            }
        });
    }

    // --- FUNGSI MODAL USER ---
    function bukaModalEditUser(id, username, fullname, role, nohp, img_src) {
        document.getElementById('modalEditUser').style.display = 'block';
        document.getElementById('eu_id').value = id;
        document.getElementById('eu_username').value = username;
        document.getElementById('eu_fullname').value = fullname;
        document.getElementById('eu_role').value = role.toLowerCase();
        document.getElementById('eu_nohp').value = nohp;
        
        let filename = img_src.substring(img_src.lastIndexOf('/')+1);
        if(filename === 'default-profile.png') filename = '';
        document.getElementById('eu_photo_lama').value = filename;
        document.getElementById('eu_img_preview').src = img_src;

        toggleEditNoHp(); 
    }

    function konfirmasiHapusUser(id) {
        Swal.fire({
            title: 'Hapus Pengguna Ini?', text: "Akun pengelola yang terhapus tidak dapat login kembali.",
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc3545', cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hapus!'
        }).then((result) => {
            if (result.isConfirmed) { window.location.href = `?action=delete_user&id=${id}`; }
        });
    }
</script>
</body>
</html>