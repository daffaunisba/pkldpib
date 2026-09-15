<?php
// admin/user-edit.php
include 'auth-check.php'; 
include '../config/db-koneksi.php';

$user_level = isset($_SESSION['level']) ? strtolower($_SESSION['level']) : '';
$current_username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin';
$current_user_id = $_SESSION['user_id'] ?? 0; // Ditambahkan untuk kebutuhan log

$message = '';
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_data = null;
$upload_dir = '../uploads/profiles/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Menangkap pesan sukses
if (isset($_GET['status']) && $_GET['status'] == 'edit_success') {
    $message = "<div class='alert success'>✅ Data profil berhasil diperbarui!</div>";
}

// --- PROTEKSI KEAMANAN ---
// Jika user bukan admin, pastikan ia hanya bisa mengedit profilnya sendiri
if ($user_level !== 'admin' && $user_id > 0) {
    $cek_stmt = $koneksi->prepare("SELECT username FROM users WHERE id = ?");
    $cek_stmt->bind_param("i", $user_id);
    $cek_stmt->execute();
    $cek_res = $cek_stmt->get_result()->fetch_assoc();
    $cek_stmt->close();

    if (!$cek_res || strtolower($cek_res['username']) !== strtolower($current_username)) {
        header("Location: dashboard.php?status=restricted");
        exit();
    }
}

// --- 1. LOGIKA UPDATE USER ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id_post = (int)$_POST['user_id'];
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $password_new = trim($_POST['password_new']); 
    $photo_lama = $_POST['profile_photo_lama'] ?? '';
    $photo_baru = $photo_lama;

    // Ambil Data Lama dari database (UNTUK DETEKSI LOG PERUBAHAN)
    $stmt_check = $koneksi->prepare("SELECT role, username, full_name, profile_photo FROM users WHERE id = ?");
    $stmt_check->bind_param("i", $user_id_post);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result()->fetch_assoc();
    
    $old_role = $res_check['role'] ?? '';
    $old_username = $res_check['username'] ?? '';
    $old_full_name = $res_check['full_name'] ?? '';
    $old_photo = $res_check['profile_photo'] ?? '';
    $stmt_check->close();

    // HANYA ADMIN yang bisa mengubah Role
    if ($user_level === 'admin') {
        $role = $_POST['role'];
    } else {
        $role = $old_role; // Pembimbing tetap dipaksa menggunakan role lamanya
    }

    // Upload Foto Baru
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
        $file_ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($file_ext, $allowed_ext)) {
            $new_file_name = 'prof_' . $user_id_post . '_' . time() . '.' . $file_ext;
            $file_destination = $upload_dir . $new_file_name;
            
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $file_destination)) {
                if (!empty($photo_lama) && file_exists($upload_dir . $photo_lama)) {
                    unlink($upload_dir . $photo_lama);
                }
                $photo_baru = $new_file_name;
            }
        }
    }

    // Sinkronisasi Tabel Guru jika role berubah (Hanya berlaku jika Admin yang ubah)
    if ($old_role === 'pembimbing' && $role !== 'pembimbing') {
        $koneksi->query("DELETE FROM guru WHERE user_id = $user_id_post");
    } elseif ($old_role !== 'pembimbing' && $role === 'pembimbing') {
        $koneksi->query("INSERT IGNORE INTO guru (nama_guru, user_id) VALUES ('$full_name', $user_id_post)");
    }

    // Deteksi Perubahan Untuk Log
    $perubahan = [];
    if ($old_username != $username) $perubahan[] = "Username";
    if ($old_full_name != $full_name) $perubahan[] = "Nama Lengkap";
    if ($old_role != $role) $perubahan[] = "Hak Akses (Role)";
    if ($old_photo != $photo_baru) $perubahan[] = "Foto Profil";
    if (!empty($password_new)) $perubahan[] = "Password Akun";

    // Query Update
    $sql_parts = ["username = ?", "full_name = ?", "role = ?", "profile_photo = ?"];
    $params = [$username, $full_name, $role, $photo_baru];
    $types = "ssss";

    // Jika password diisi, ikut update passwordnya
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
        
        // --- TRIGGER LOG AKTIVITAS (UPDATE PROFIL/AKUN) ---
        if (count($perubahan) > 0) {
            $pesan_log = "Memperbarui kredensial akun staf/pendidik: {$old_full_name} (Detail yang diubah: " . implode(", ", $perubahan) . ")";
            
            // Tandai khusus jika mengedit profil diri sendiri
            if ($user_id_post == $current_user_id) {
                $pesan_log = "Memperbarui profil diri sendiri (Detail yang diubah: " . implode(", ", $perubahan) . ")";
            }

            catatLog($koneksi, $current_user_id, $pesan_log);
        }

        // Jika user yang login mengedit profilnya sendiri dan mengganti username, perbarui session
        if (strtolower($current_username) === strtolower($old_username)) {
            $_SESSION['username'] = $username;
        }

        // REDIRECT BERDASARKAN LEVEL (Agar pembimbing tidak error)
        if ($user_level === 'admin') {
            header("Location: user-list.php?status=edit_success");
        } else {
            header("Location: user-edit.php?id=$user_id_post&status=edit_success");
        }
        exit();
    } else {
        $message = "<div class='alert error'>Gagal memperbarui: Username mungkin sudah ada atau terjadi kesalahan sistem.</div>";
    }
    $update_stmt->close();
}

// --- 2. AMBIL DATA USER ---
if ($user_id > 0) {
    $user_stmt = $koneksi->prepare("SELECT id, username, full_name, role, profile_photo FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $result = $user_stmt->get_result();
    $user_data = $result->fetch_assoc();
    $user_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Edit User | Si Mantap PKL</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --mantap-blue-dark: #0f172a;
            --mantap-blue-main: #1e40af;
            --mantap-blue-light: #3b82f6;
            --mantap-blue-soft: #eff6ff;
        }

        body { font-family: 'Poppins', sans-serif; background-color: #f8fafc; color: #334155; margin: 0; overflow-x: hidden !important; }
        .main-content-wrapper { max-width: 100% !important; width: 100% !important; box-sizing: border-box !important; }
        .admin-main-content { padding: 20px 25px 30px 25px !important; box-sizing: border-box !important; width: 100% !important; }

        .page-header-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; width: 100%; flex-wrap: wrap; gap: 15px; }
        .page-header-controls h1 { font-weight: 700; color: #0f172a; font-size: 1.8rem; margin: 0; position: relative; }
        .page-header-controls h1::after { content: ''; position: absolute; left: 0; bottom: -5px; width: 50px; height: 4px; background: var(--mantap-blue-main); border-radius: 2px; }

        .btn-back-link { color: var(--mantap-blue-main); text-decoration: none; font-weight: 600; font-size: 13.5px; display: inline-flex; align-items: center; gap: 6px; }
        .btn-back-link:hover { color: var(--mantap-blue-light); }

        .alert { padding: 12px 15px; margin-bottom: 20px; border-radius: 8px; border: 1px solid; font-size: 14px; font-weight: 500; text-align: left; }
        .alert.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .alert.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        
        .form-edit-container { background-color: white; padding: 30px; border-radius: 16px; max-width: 600px; width: 100%; border: 2px solid #e2e8f0; box-sizing: border-box; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); }
        .form-edit-container label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13.5px; color: #0f172a; text-align: left; }
        
        .form-edit-container input[type="text"], .form-edit-container input[type="password"], .form-edit-container input[type="file"], .form-edit-container select { width: 100%; padding: 11px 14px; margin-bottom: 16px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 13.5px; box-sizing: border-box; background-color: #f8fafc; }
        .form-edit-container input:focus, .form-edit-container select:focus { outline: none; border-color: #1e40af; background-color: white; }

        .profile-preview { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; text-align: left; }
        .profile-preview img { width: 64px; height: 64px; border-radius: 50%; object-fit: cover; border: 2px solid #cbd5e1; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }

        .btn-submit-save { padding: 11px 24px !important; font-weight: 600 !important; background-color: var(--mantap-blue-main); color: white; border: none; border-radius: 20px; font-size: 13px; cursor: pointer; font-family: 'Poppins', sans-serif; box-shadow: 0 4px 6px -1px rgba(30, 64, 175, 0.2); display: inline-flex; align-items: center; justify-content: center; gap: 6px; width: 100%; }
        .btn-submit-save:hover { background-color: var(--mantap-blue-light); }

        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 
            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; gap: 10px !important; padding: 5px 0px !important; }
            .page-header-controls h1 { font-size: 1.4rem !important; }
            .form-edit-container { padding: 20px 14px !important; border-radius: 12px !important; border: 2px solid #cbd5e1 !important; }
            .form-edit-container input, .form-edit-container select { font-size: 13.5px !important; padding: 10px !important; }
            .btn-submit-save { padding: 12px !important; border-radius: 8px !important; font-size: 14px !important; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; ?>

<div class="main-content-wrapper">

    <?php include 'panel/navbar.php'; ?>

    <div class="admin-main-content"> 
        <div class="page-header-controls">
            <h1>Profil & Pengaturan Akun</h1>
            <?php if ($user_level === 'admin'): ?>
                <a href="user-list.php" class="btn-back-link">
                    <i class="fas fa-arrow-left"></i> Kembali ke Daftar User
                </a>
            <?php else: ?>
                <a href="dashboard.php" class="btn-back-link">
                    <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
                </a>
            <?php endif; ?>
        </div>
        
        <?php if ($message) echo $message; ?>

        <?php if ($user_data): ?>
        <div class="form-edit-container">
            <form method="POST" enctype="multipart/form-data" style="box-shadow: none; max-width: 100%; margin: 0; padding: 0;">
                <input type="hidden" name="user_id" value="<?php echo $user_data['id']; ?>">
                <input type="hidden" name="profile_photo_lama" value="<?php echo htmlspecialchars($user_data['profile_photo']); ?>">

                <label>Foto Profil Saat Ini:</label>
                <div class="profile-preview">
                    <?php $src = !empty($user_data['profile_photo']) ? $upload_dir . $user_data['profile_photo'] : '../img/default-profile.png'; ?>
                    <img src="<?php echo $src; ?>" alt="Profil">
                    <span style="font-size: 12px; color: #64748b; font-weight: 500;"><?php echo $user_data['profile_photo'] ? htmlspecialchars($user_data['profile_photo']) : 'Menggunakan Foto Default'; ?></span>
                </div>

                <label for="profile_photo">Unggah Ganti Foto Profil:</label>
                <input type="file" id="profile_photo" name="profile_photo" accept="image/*">

                <label for="full_name">Nama Lengkap Pengguna:</label>
                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user_data['full_name']); ?>" required>

                <label for="username">Username Otoritas Login:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user_data['username']); ?>" required>

                <?php if ($user_level === 'admin'): ?>
                    <label for="role">Tingkat Hak Akses Kontrol:</label>
                    <select id="role" name="role" required>
                        <option value="admin" <?php echo ($user_data['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                        <option value="pembimbing" <?php echo ($user_data['role'] == 'pembimbing' || $user_data['role'] == 'guru') ? 'selected' : ''; ?>>Pembimbing (Guru)</option>
                    </select>
                <?php else: ?>
                    <input type="hidden" name="role" value="<?php echo htmlspecialchars($user_data['role']); ?>">
                <?php endif; ?>

                <label for="password_new">Sandi Kunci Baru (Kosongkan jika sandi lama tidak diubah):</label>
                <input type="password" id="password_new" name="password_new" placeholder="Masukkan password baru akun...">

                <button type="submit" class="btn-submit-save">
                    <i class="fas fa-save"></i> Perbarui Data Pengguna
                </button>
            </form>
        </div>
        <?php else: ?>
            <div style="text-align: center; padding: 25px; border: 2px dashed #cbd5e1; background: #fff; border-radius: 8px; color: #ef4444; font-style: italic; font-weight: 500;">
                <i class="fas fa-exclamation-triangle fa-lg mb-2" style="display: block;"></i> Rekam data pengguna tidak ditemukan di dalam sistem.
            </div>
        <?php endif; ?>
    </div>

</div>

<?php include 'panel/footer.php'; ?>
</body>
</html>