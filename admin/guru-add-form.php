<!-- <?php
// admin/guru-add-form.php (Formulir untuk menambah guru baru)
include 'auth-check.php'; 
include '../config/db-koneksi.php';

$current_user = isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin';
$message = '';
$new_guru_name = ''; // Inisialisasi untuk menyimpan nilai form
$new_guru_email = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_guru_name = trim($_POST['new_guru_name']);
    $new_guru_email = trim($_POST['new_guru_email']);
    
    // Simpan nilai input untuk ditampilkan jika ada error
    $new_guru_name_display = htmlspecialchars($new_guru_name);
    $new_guru_email_display = htmlspecialchars($new_guru_email);
    
    if (!empty($new_guru_name) && filter_var($new_guru_email, FILTER_VALIDATE_EMAIL)) {
        try {
            // Cek apakah email sudah ada
            $check_stmt = $koneksi->prepare("SELECT COUNT(*) FROM guru WHERE email = ?");
            $check_stmt->bind_param("s", $new_guru_email);
            $check_stmt->execute();
            $check_stmt->bind_result($count);
            $check_stmt->fetch();
            $check_stmt->close();
            
            if ($count > 0) {
                 $message = "<div class='alert error'>Gagal menambahkan guru. Email $new_guru_email_display sudah terdaftar.</div>";
            } else {
                // Insert guru baru
                $insert_guru_stmt = $koneksi->prepare("INSERT INTO guru (nama_guru, email) VALUES (?, ?)");
                $insert_guru_stmt->bind_param("ss", $new_guru_name, $new_guru_email);
                
                if ($insert_guru_stmt->execute()) {
                    // Encode pesan sukses untuk dikirim melalui URL saat redirect
                    $success_msg = urlencode("✅ Guru " . $new_guru_name_display . " berhasil ditambahkan!");
                    
                    // Redirect ke halaman list setelah sukses (PRG Pattern)
                    header("Location: guru-list.php?msg=" . $success_msg);
                    exit();
                } else {
                    // Jika terjadi error SQL lain
                    $message = "<div class='alert error'>Gagal menambahkan guru. Terjadi kesalahan database.</div>";
                }
                $insert_guru_stmt->close();
            }
        } catch (Exception $e) {
            $message = "<div class='alert error'>Terjadi kesalahan database: " . $e->getMessage() . "</div>";
        }
    } else {
        $message = "<div class='alert warning'>Nama dan Email Guru harus diisi dengan format yang benar.</div>";
    }
} else {
    // Inisialisasi display value untuk form saat GET request pertama
    $new_guru_name_display = '';
    $new_guru_email_display = '';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin - Tambah Guru Baru | Si Mantap PKL</title>
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

        .alert { padding: 12px 15px; margin-bottom: 20px; border-radius: 8px; border: 1px solid; font-size: 14px; font-weight: 500; }
        .alert.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .alert.warning { background-color: #fff3cd; color: #856404; border-color: #ffeeba; }
        
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
        }
        
        .form-add-container input[type="text"],
        .form-add-container input[type="email"] {
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
        .form-add-container input:focus { outline: none; border-color: #1e40af; background-color: white; }

        /* BUTTONS STYLING */
        .btn-submit-add {
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
            gap: 6px;
        }
        .btn-submit-add:hover { background-color: #16a34a; }
        
        .sidebar-nav ul, .sidebar-nav li, .dropdown-menu, .dropdown-menu li { list-style: none !important; margin: 0; padding: 0; }

        /* =========================================================================
           RESPONSIVE VIEWPORT HANDPHONE (HP)
        ========================================================================= */
        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 

            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; gap: 10px !important; padding: 5px 0px !important; }
            .page-header-controls h1 { font-size: 1.4rem !important; }
            
            .form-add-container { padding: 20px 14px !important; border-radius: 12px !important; }
            .form-add-container input { font-size: 13.5px !important; padding: 10px !important; }
            
            .form-add-container button.btn-submit-add { width: 100% !important; justify-content: center !important; padding: 12px !important; border-radius: 8px !important; font-size: 14px !important; }
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
            <h1>Tambah Guru Pembimbing</h1>
            <a href="guru-list.php" class="btn-back-link">
                <i class="fas fa-arrow-left"></i> Kembali ke Daftar Pembimbing
            </a>
        </div>
        
        <?php echo $message; ?>

        <div class="form-add-container">
            <form method="POST" action="guru-add-form.php" style="box-shadow: none; max-width: 100%; margin: 0; padding: 0;">
                
                <label for="new_guru_name">Nama Lengkap Pembimbing:</label>
                <input type="text" id="new_guru_name" name="new_guru_name" placeholder="Contoh: Ahmad Saiful, S.Pd." value="<?php echo $new_guru_name_display; ?>" required>

                <label for="new_guru_email">Email Pembimbing Resmi:</label>
                <input type="email" id="new_guru_email" name="new_guru_email" placeholder="contoh@smk.sch.id" value="<?php echo $new_guru_email_display; ?>" required>

                <button type="submit" class="btn-submit-add">
                    <i class="fas fa-plus-circle"></i> Daftarkan Pembimbing Baru
                </button>
            </form>
        </div>
    </div>

</div>

<?php include 'panel/footer.php'; ?>
</body>
</html> -->