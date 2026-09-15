<?php
// admin/klasifikasi-edit.php
include 'auth-check.php';
include '../config/db-koneksi.php';

$current_user = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin';
$klasifikasi_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$mode = ($klasifikasi_id > 0) ? 'edit' : 'add';

$message = '';
$form_data = ['kode' => '', 'nama_klasifikasi' => ''];
$page_title = ($mode === 'edit') ? 'Edit Klasifikasi Surat' : 'Tambah Klasifikasi Baru';

// --- LOGIKA LOAD DATA (untuk mode edit) ---
if ($mode === 'edit') {
    $stmt = $koneksi->prepare("SELECT kode, nama_klasifikasi FROM klasifikasi_surat WHERE id = ?");
    $stmt->bind_param("i", $klasifikasi_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $form_data = $result->fetch_assoc();
    } else {
        $message = "<div class='alert error'>Klasifikasi tidak ditemukan.</div>";
        $mode = 'add'; 
    }
    $stmt->close();
}

// --- LOGIKA SIMPAN DATA (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $posted_id = (int)$_POST['klasifikasi_id'] ?? 0;
    $kode = trim($_POST['kode']);
    $nama = trim($_POST['nama_klasifikasi']);
    
    $form_data = ['kode' => $kode, 'nama_klasifikasi' => $nama];

    if (empty($kode) || empty($nama)) {
        $message = "<div class='alert error'>Kode dan Nama Klasifikasi wajib diisi.</div>";
    } else {
        if ($posted_id === 0) {
            $stmt = $koneksi->prepare("INSERT INTO klasifikasi_surat (kode, nama_klasifikasi) VALUES (?, ?)");
            $stmt->bind_param("ss", $kode, $nama);
        } else {
            $stmt = $koneksi->prepare("UPDATE klasifikasi_surat SET kode = ?, nama_klasifikasi = ? WHERE id = ?");
            $stmt->bind_param("ssi", $kode, $nama, $posted_id);
        }
        
        if ($stmt->execute()) {
            $success_msg = urlencode("Klasifikasi '{$kode}' berhasil " . (($posted_id === 0) ? "ditambahkan" : "diperbarui") . ".");
            header("Location: kode-surat-manage.php?msg=" . $success_msg);
            exit();
        } else {
            if ($koneksi->errno == 1062) {
                $message = "<div class='alert error'>Gagal menyimpan: Kode '{$kode}' sudah ada dalam sistem.</div>";
            } else {
                $message = "<div class='alert error'>Gagal menyimpan: " . $koneksi->error . "</div>";
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin - <?php echo $page_title; ?> | Si Mantap PKL</title>
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
        .form-edit-container {
            background-color: white;
            padding: 30px;
            border-radius: 16px;
            max-width: 600px;
            width: 100%;
            border: 2px solid #e2e8f0;
            box-sizing: border-box;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        }

        .form-edit-container label { 
            display: block; 
            margin-bottom: 6px; 
            font-weight: 600; 
            font-size: 13.5px; 
            color: #0f172a; 
            text-align: left;
        }
        
        .form-edit-container input[type="text"] {
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
        .form-edit-container input:focus { outline: none; border-color: #1e40af; background-color: white; }

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

        /* RESPONSIVE HP */
        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 
            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; gap: 10px !important; padding: 5px 0px !important; }
            .page-header-controls h1 { font-size: 1.4rem !important; }
            .form-edit-container { padding: 20px 14px !important; border-radius: 12px !important; border: 2px solid #cbd5e1 !important; }
            .form-edit-container input { font-size: 13.5px !important; padding: 10px !important; }
            .form-edit-container button.btn-submit-save { padding: 12px !important; border-radius: 8px !important; font-size: 14px !important; }
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
            <h1><?php echo $page_title; ?></h1>
            <a href="kode-surat-manage.php" class="btn-back-link">
                <i class="fas fa-arrow-left"></i> Kembali ke Daftar Klasifikasi
            </a>
        </div>
        
        <?php echo $message; ?>

        <div class="form-edit-container">
            <form method="POST" action="klasifikasi-edit.php?id=<?php echo $klasifikasi_id; ?>" style="box-shadow: none; max-width: 100%; margin: 0; padding: 0;">
                <input type="hidden" name="klasifikasi_id" value="<?php echo $klasifikasi_id; ?>">

                <label for="kode">Kode Klasifikasi (Contoh: 421.1):</label>
                <input type="text" id="kode" name="kode" maxlength="10" value="<?php echo htmlspecialchars($form_data['kode']); ?>" placeholder="Masukkan kode unik..." required>

                <label for="nama_klasifikasi">Nama Klasifikasi Lengkap:</label>
                <input type="text" id="nama_klasifikasi" name="nama_klasifikasi" value="<?php echo htmlspecialchars($form_data['nama_klasifikasi']); ?>" placeholder="Contoh: Surat Pengantar PKL..." required>

                <button type="submit" class="btn-submit-save">
                    <i class="fas fa-save"></i> Simpan Klasifikasi
                </button>
            </form>
        </div>
    </div>
</div>

<?php include 'panel/footer.php'; ?>
</body>
</html>