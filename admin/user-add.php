<?php
// admin/user-add.php
include 'auth-check.php'; // Proteksi Login
include '../config/db-koneksi.php';

// --- PROTEKSI HAK AKSES MUTLAK: KHUSUS ADMIN ---
$user_level = isset($_SESSION['level']) ? strtolower($_SESSION['level']) : 'guest';
if ($user_level !== 'admin') {
    // Jika bukan admin, langsung lempar kembali ke dashboard secara diam-diam
    header("Location: dashboard.php");
    exit();
}

$current_user = isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin';
$current_user_level = $_SESSION['level'] ?? 'user'; // Ambil level user untuk sidebar

$message = '';
$form_data = [
    'username' => '',
    'full_name' => '',
    'role' => 'user' // Default role
];

// --- 1. LOGIKA TAMBAH USER BARU (DENGAN HASHING) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $role = $_POST['role'];
    $password = $_POST['password'];

    // Simpan data input jika terjadi error
    $form_data = [
        'username' => $username,
        'full_name' => $full_name,
        'role' => $role
    ];

    if (empty($password) || empty($username) || empty($full_name)) {
        $message = "<div class='alert error'>Semua kolom wajib diisi, termasuk password.</div>";
    } else {
        // PENTING: MENGGUNAKAN PASSWORD HASHING UNTUK KEAMANAN
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Query untuk memasukkan user baru
        $insert_stmt = $koneksi->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
        $insert_stmt->bind_param("ssss", $username, $hashed_password, $full_name, $role);

        if ($insert_stmt->execute()) {
            
            if ($role === 'pembimbing') {
                // Ambil ID user yang baru dibuat
                $new_user_id = $insert_stmt->insert_id;
                
                // SINRONISASI KE TABEL GURU
                $insert_guru_stmt = $koneksi->prepare("INSERT INTO guru (nama_guru, user_id) VALUES (?, ?)");
                $insert_guru_stmt->bind_param("si", $full_name, $new_user_id);
                
                // Eksekusi insert guru, error diabaikan agar tidak mengganggu user utama
                $insert_guru_stmt->execute();
                $insert_guru_stmt->close();
            }
            
            // Redirect ke halaman list dengan status sukses
            header("Location: user-list.php?status=add_success");
            exit();
        } else {
            // Error, kemungkinan username sudah ada (Unique Constraint Violation)
            if ($koneksi->errno == 1062) {
                 $message = "<div class='alert error'>Gagal menambahkan user. Username sudah terdaftar.</div>";
            } else {
                 $message = "<div class='alert error'>Gagal menambahkan user. Terjadi kesalahan database.</div>";
            }
        }
        $insert_stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin - Tambah User | Si Mantap PKL</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --mantap-blue-dark: #0f172a;
            --mantap-blue-main: #1e40af;
            --mantap-blue-light: #3b82f6;
            --mantap-blue-soft: #eff6ff;
        }

        body { 
            font-family: 'Poppins', sans-serif; 
            background-color: #f8fafc; 
            color: #334155; 
            margin: 0;
            overflow-x: hidden !important;
        }

        .main-content-wrapper {
            max-width: 100% !important;
            width: 100% !important;
            box-sizing: border-box !important;
            box-shadow: none !important;
        }

        .admin-main-content {
            padding: 20px 25px 30px 25px !important; 
            box-sizing: border-box !important;
            clear: both;
            width: 100% !important;
        }

        .page-header-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            width: 100%;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-header-controls h1 {
            font-weight: 700;
            color: #0f172a;
            font-size: 1.8rem; 
            margin: 0;
            position: relative;
        }

        .page-header-controls h1::after {
            content: ''; position: absolute; left: 0; bottom: -5px; width: 50px; height: 4px; background: var(--mantap-blue-main); border-radius: 2px;
        }

        .btn-back-link {
            color: var(--mantap-blue-main);
            text-decoration: none;
            font-weight: 600;
            font-size: 13.5px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-back-link:hover { color: var(--mantap-blue-light); }

        .alert { padding: 12px 15px; margin-bottom: 20px; border-radius: 8px; border: 1px solid; font-size: 14px; font-weight: 500; text-align: left; }
        .alert.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        
        /* CARD PANEL FORM CONTAINER */
        .form-add-container {
            background-color: white;
            padding: 30px;
            border-radius: 16px;
            max-width: 650px;
            width: 100%;
            border: 2px solid #e2e8f0;
            box-sizing: border-box;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        }

        .form-add-container label { 
            display: block; 
            margin-bottom: 6px; 
            font-weight: 600; 
            font-size: 13.5px; 
            color: #0f172a; 
            text-align: left;
        }
        
        .form-add-container input[type="text"],
        .form-add-container input[type="password"],
        .form-add-container select {
            width: 100%;
            padding: 11px 14px;
            margin-bottom: 16px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 13.5px;
            box-sizing: border-box;
            background-color: #f8fafc;
        }
        .form-add-container input:focus, .form-add-container select:focus { outline: none; border-color: #1e40af; background-color: white; }

        /* BUTTONS STYLING */
        .btn-submit-save {
            padding: 11px 24px !important;
            font-weight: 600 !important;
            background-color: #22c55e;
            color: white;
            border: none;
            border-radius: 20px;
            font-size: 13px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.2);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: 100%;
            box-sizing: border-box;
        }
        .btn-submit-save:hover { background-color: #16a34a; }

        .sidebar-nav ul, .sidebar-nav li, .dropdown-menu, .dropdown-menu li { list-style: none !important; margin: 0; padding: 0; }

        /* =========================================================================
           RESPONSIVE VIEWPORT HANDPHONE (HP)
        ========================================================================= */
        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 

            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; gap: 10px !important; padding: 5px 0px !important; }
            .page-header-controls h1 { font-size: 1.4rem !important; }
            
            .form-add-container { padding: 20px 14px !important; border-radius: 12px !important; border: 2px solid #cbd5e1 !important; }
            .form-add-container input, .form-add-container select { font-size: 13.5px !important; padding: 10px !important; }
            
            .form-add-container button.btn-submit-save { padding: 12px !important; border-radius: 8px !important; font-size: 14px !important; }
            .btn-back-link { font-size: 12.5px !important; margin-top: 5px; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; ?>

<div class="main-content-wrapper">

    <?php include 'panel/navbar.php'; ?>

    <div class="admin-main-content"> 
        <div class="page-header-controls">
            <h1>Tambah Pengguna Baru</h1>
            <a href="user-list.php" class="btn-back-link">
                <i class="fas fa-arrow-left"></i> Kembali ke Daftar Pengguna
            </a>
        </div>
        
        <?php if ($message) echo $message; ?>

        <div class="form-add-container">
            <form method="POST" action="user-add.php" style="box-shadow: none; max-width: 100%; margin: 0; padding: 0;">
                
                <label for="full_name">Nama Lengkap Pengguna:</label>
                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($form_data['full_name']); ?>" placeholder="Nama lengkap user baru" required>

                <label for="username">Username Otoritas Login:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($form_data['username']); ?>" placeholder="Username unik (misal: operator, pak_budi)" required>
                
                <label for="password">Password Utama Kontrol (Wajib):</label>
                <input type="password" id="password" name="password" placeholder="Masukkan password awal akun..." required>

                <label for="role">Tingkat Hak Akses Kontrol (Role):</label>
                <select id="role" name="role" required>
                    <option value="admin" <?php echo ($form_data['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                    <option value="pembimbing" <?php echo ($form_data['role'] == 'pembimbing') ? 'selected' : ''; ?>>Pembimbing (Guru)</option>
                    <option value="user" <?php echo ($form_data['role'] == 'user') ? 'selected' : ''; ?>>User Biasa</option>
                </select>

                <button type="submit" class="btn-submit-save">
                    <i class="fas fa-user-plus"></i> Daftarkan Pengguna Baru
                </button>
            </form>
        </div>
    </div>

</div>

<?php include 'panel/footer.php'; ?>
</body>
</html>
