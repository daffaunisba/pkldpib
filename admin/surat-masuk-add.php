<?php
// admin/surat-masuk-add.php
include 'auth-check.php';
include '../config/db-koneksi.php';

$current_user = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin';
$current_user_id = $_SESSION['user_id'] ?? 0; // Kebutuhan Log
$message = '';
$form_data = [
    'nomor_surat' => '',
    'tanggal_terima' => date('Y-m-d'),
    'asal_perusahaan' => '',
    'perihal' => ''
];

$upload_dir = '../uploads/surat/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// ---------------------------------------------------------------------
// LOGIKA TAMBAH SURAT MASUK
// ---------------------------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nomor_surat = trim($_POST['nomor_surat_masuk']); 
    $tanggal_terima = trim($_POST['tanggal_terima']);
    $asal_perusahaan = trim($_POST['asal_perusahaan']);
    $perihal = trim($_POST['perihal']);
    $file_name = null;
    $error_upload = false;

    // Simpan data input jika terjadi error
    $form_data = [
        'nomor_surat' => $nomor_surat,
        'tanggal_terima' => $tanggal_terima,
        'asal_perusahaan' => $asal_perusahaan,
        'perihal' => $perihal
    ];

    if (empty($nomor_surat) || empty($tanggal_terima) || empty($asal_perusahaan) || empty($perihal)) {
        $message = "<div class='alert error'>Semua kolom wajib diisi.</div>";
    } else {
        // --- Proses Upload File ---
        if (isset($_FILES['file_dokumen']) && $_FILES['file_dokumen']['error'] == 0) {
            $file_ext = strtolower(pathinfo($_FILES['file_dokumen']['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
            
            if (in_array($file_ext, $allowed_ext)) {
                $file_name = 'SM_' . time() . '_' . str_replace('/', '_', $nomor_surat) . '.' . $file_ext;
                $file_destination = $upload_dir . $file_name;
                
                if (!move_uploaded_file($_FILES['file_dokumen']['tmp_name'], $file_destination)) {
                    $message = "<div class='alert error'>Gagal mengunggah file dokumen.</div>";
                    $error_upload = true;
                }
            } else {
                $message = "<div class='alert error'>Jenis file tidak didukung. Harap upload PDF, DOC, DOCX, JPG, JPEG, atau PNG.</div>";
                $error_upload = true;
            }
        } else {
             if ($_FILES['file_dokumen']['error'] != 4 && $_FILES['file_dokumen']['error'] != 0) { 
                 $message = "<div class='alert error'>Terjadi kesalahan saat upload file.</div>";
                 $error_upload = true;
             }
        }
        // --- Akhir Proses Upload ---

        if (empty($message) && !$error_upload) {
            $insert_stmt = $koneksi->prepare("INSERT INTO surat_masuk_pkl (nomor_surat_masuk, tanggal_terima, asal_perusahaan, perihal, file_path) VALUES (?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("sssss", $nomor_surat, $tanggal_terima, $asal_perusahaan, $perihal, $file_name);

            if ($insert_stmt->execute()) {
                
                // --- TRIGGER LOG AKTIVITAS (ADD SURAT MASUK) ---
                catatLog($koneksi, $current_user_id, "Mencatat arsip Surat Masuk baru dari Instansi {$asal_perusahaan} (Nomor: {$nomor_surat})");

                // Redirect ke halaman list dengan status sukses
                header("Location: persuratan.php?status=input_success&tab=masuk");
                exit();
            } else {
                $message = "<div class='alert error'>Gagal menyimpan data: Nomor surat mungkin sudah ada.</div>";
            }
            $insert_stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin - Catat Surat Masuk | Si Mantap PKL</title>
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
            max-width: 750px;
            width: 100%;
            border: 2px solid #e2e8f0;
            box-sizing: border-box;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        }

        .form-edit-container label, .form-add-container label { 
            display: block; 
            margin-bottom: 6px; 
            font-weight: 600; 
            font-size: 13.5px; 
            color: #0f172a; 
            text-align: left;
        }
        
        .form-add-container input[type="text"], 
        .form-add-container input[type="date"], 
        .form-add-container input[type="file"], 
        .form-add-container textarea {
            width: 100%; 
            padding: 11px 14px; 
            margin-bottom: 16px; 
            border: 1px solid #cbd5e1; 
            border-radius: 8px; 
            font-family: 'Poppins', sans-serif;
            font-size: 13.5px;
            box-sizing: border-box; 
            background-color: #f8fafc;
            resize: vertical;
        }
        .form-add-container input:focus, .form-add-container textarea:focus { outline: none; border-color: #1e40af; background-color: white; }

        /* BUTTONS STYLING */
        .btn-submit-save {
            padding: 11px 24px !important;
            font-weight: 600 !important;
            background-color: var(--mantap-blue-main);
            color: white;
            border: none;
            border-radius: 20px;
            font-size: 13px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            box-shadow: 0 4px 6px -1px rgba(30, 64, 175, 0.2);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: 100%;
            box-sizing: border-box;
        }
        .btn-submit-save:hover { background-color: var(--mantap-blue-light); }

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
            .form-add-container input, .form-add-container textarea { font-size: 13.5px !important; padding: 10px !important; }
            
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
            <h1>Catat Surat Masuk Baru</h1>
            <a href="persuratan.php?tab=masuk" class="btn-back-link">
                <i class="fas fa-arrow-left"></i> Kembali ke Administrasi Persuratan
            </a>
        </div>
        
        <?php echo $message; ?>

        <div class="form-add-container">
            <form method="POST" action="surat-masuk-add.php" enctype="multipart/form-data" style="box-shadow: none; max-width: 100%; margin: 0; padding: 0;">
                
                <label for="nomor_surat_masuk">Nomor Surat Masuk:</label>
                <input type="text" id="nomor_surat_masuk" name="nomor_surat_masuk" value="<?php echo htmlspecialchars($form_data['nomor_surat']); ?>" placeholder="Contoh: 001/HRD/XYZ/I/2026" required>
                
                <label for="tanggal_terima">Tanggal Terima Surat:</label>
                <input type="date" id="tanggal_terima" name="tanggal_terima" value="<?php echo $form_data['tanggal_terima']; ?>" required>

                <label for="asal_perusahaan">Asal Perusahaan / Instansi Pengirim:</label>
                <input type="text" id="asal_perusahaan" name="asal_perusahaan" value="<?php echo htmlspecialchars($form_data['asal_perusahaan']); ?>" placeholder="Nama Perusahaan yang Mengirim Surat" required>

                <label for="perihal">Perihal / Isi Ringkas Surat Masuk:</label>
                <input type="text" id="perihal" name="perihal" value="<?php echo htmlspecialchars($form_data['perihal']); ?>" placeholder="Contoh: Balasan Penerimaan PKL Kelompok 3" required>

                <label for="file_dokumen">Upload Lampiran File Pindai/Scan (PDF/Doc/Gambar - Opsional):</label>
                <input type="file" id="file_dokumen" name="file_dokumen" accept=".pdf, .doc, .docx, .jpg, .jpeg, .png">

                <button type="submit" class="btn-submit-save">
                    <i class="fas fa-save"></i> Simpan Surat Masuk
                </button>
            </form>
        </div>
    </div>

</div>

<?php include 'panel/footer.php'; ?>
</body>
</html>