<?php
// admin/surat-masuk-edit.php
include 'auth-check.php';
include '../config/db-koneksi.php';

$current_user = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin';
$current_user_id = $_SESSION['user_id'] ?? 0; // Ditambahkan untuk kebutuhan log
$message = '';
$upload_dir = '../uploads/surat/';
$masuk_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$surat_data = null;

if ($masuk_id === 0) {
    die("<div class='alert error'>ID Surat Masuk tidak valid.</div>");
}

// ---------------------------------------------------------------------
// LOGIKA UPDATE DATA
// ---------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $masuk_id_post = (int)$_POST['masuk_id'];
    $nomor_surat_masuk = trim($_POST['nomor_surat_masuk']);
    $tanggal_terima = trim($_POST['tanggal_terima']);
    $asal_perusahaan = trim($_POST['asal_perusahaan']);
    $perihal = trim($_POST['perihal']);
    $file_path_lama = $_POST['file_path_lama'];
    $file_path_baru = $file_path_lama;
    
    $error_upload = false;

    if (empty($nomor_surat_masuk) || empty($tanggal_terima) || empty($asal_perusahaan) || empty($perihal)) {
        $message = "<div class='alert error'>Semua kolom wajib diisi.</div>";
    } else {
        // Proses Upload File Baru (jika ada)
        if (isset($_FILES['file_dokumen_new']) && $_FILES['file_dokumen_new']['error'] == 0) {
            $file_ext = strtolower(pathinfo($_FILES['file_dokumen_new']['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
            
            if (in_array($file_ext, $allowed_ext)) {
                // Gunakan nomor surat untuk penamaan file
                $safe_nomor_surat = str_replace('/', '_', $nomor_surat_masuk);
                $file_name = 'SM_' . time() . '_' . $safe_nomor_surat . '.' . $file_ext;
                $file_destination = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['file_dokumen_new']['tmp_name'], $file_destination)) {
                    // Hapus file lama jika ada
                    if (!empty($file_path_lama) && file_exists($upload_dir . $file_path_lama)) {
                        unlink($upload_dir . $file_path_lama);
                    }
                    $file_path_baru = $file_name;
                } else {
                    $message = "<div class='alert error'>Gagal mengunggah file dokumen baru.</div>";
                    $error_upload = true;
                }
            } else {
                $message = "<div class='alert error'>Jenis file tidak didukung.</div>";
                $error_upload = true;
            }
        } 

        if (empty($message) && !$error_upload) {
            
            // --- DETEKSI PERUBAHAN DATA UNTUK LOG SUPER DETAIL ---
            $stmt_old = $koneksi->prepare("SELECT nomor_surat_masuk, tanggal_terima, asal_perusahaan, perihal, file_path FROM surat_masuk_pkl WHERE masuk_id = ?");
            $stmt_old->bind_param("i", $masuk_id_post);
            $stmt_old->execute();
            $old_data = $stmt_old->get_result()->fetch_assoc();
            $stmt_old->close();

            $perubahan = [];
            if ($old_data['nomor_surat_masuk'] != $nomor_surat_masuk) $perubahan[] = "Nomor Surat";
            if ($old_data['tanggal_terima'] != $tanggal_terima) $perubahan[] = "Tanggal Terima";
            if ($old_data['asal_perusahaan'] != $asal_perusahaan) $perubahan[] = "Instansi Pengirim";
            if ($old_data['perihal'] != $perihal) $perubahan[] = "Perihal";
            if ($old_data['file_path'] != $file_path_baru) $perubahan[] = "File Lampiran Scan";

            $pesan_log = "";
            if (count($perubahan) > 0) {
                $detail_ubah = implode(", ", $perubahan);
                $pesan_log = "Memperbarui data arsip Surat Masuk: " . $old_data['nomor_surat_masuk'] . " (Detail yang diubah: " . $detail_ubah . ")";
            } else {
                $pesan_log = "Menyimpan ulang arsip Surat Masuk: " . $old_data['nomor_surat_masuk'] . " (Tanpa perubahan data)";
            }
            // ------------------------------------------

            $update_stmt = $koneksi->prepare("UPDATE surat_masuk_pkl SET nomor_surat_masuk = ?, tanggal_terima = ?, asal_perusahaan = ?, perihal = ?, file_path = ? WHERE masuk_id = ?");
            $update_stmt->bind_param("sssssi", $nomor_surat_masuk, $tanggal_terima, $asal_perusahaan, $perihal, $file_path_baru, $masuk_id_post);

            if ($update_stmt->execute()) {
                
                // --- TRIGGER LOG AKTIVITAS (UPDATE DETAIL SURAT MASUK) ---
                catatLog($koneksi, $current_user_id, $pesan_log);

                header("Location: persuratan.php?status=edit_success&tab=masuk");
                exit();
            } else {
                 if ($koneksi->errno == 1062) {
                    $message = "<div class='alert error'>Gagal menyimpan data: Nomor surat sudah terdaftar.</div>";
                } else {
                    $message = "<div class='alert error'>Gagal memperbarui data: " . $update_stmt->error . "</div>";
                }
            }
            $update_stmt->close();
        }
    }
    $masuk_id = $masuk_id_post; 
}

// ---------------------------------------------------------------------
// AMBIL DATA SURAT SAAT INI
// ---------------------------------------------------------------------
$stmt_get_data = $koneksi->prepare("SELECT masuk_id, nomor_surat_masuk, tanggal_terima, asal_perusahaan, perihal, file_path FROM surat_masuk_pkl WHERE masuk_id = ?");
$stmt_get_data->bind_param("i", $masuk_id);
$stmt_get_data->execute();
$result = $stmt_get_data->get_result();

if ($result->num_rows > 0) {
    $surat_data = $result->fetch_assoc();
} else {
    $message = "<div class='alert error'>Surat tidak ditemukan.</div>";
}
$stmt_get_data->close();

function formatTanggalInput($date_str) {
    return date('Y-m-d', strtotime($date_str));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin - Edit Surat Masuk | Si Mantap PKL</title>
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
        .alert.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        
        /* CARD PANEL FORM CONTAINER */
        .form-edit-container {
            background-color: white;
            padding: 30px;
            border-radius: 16px;
            max-width: 750px;
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
        
        .form-edit-container input[type="text"], 
        .form-edit-container input[type="date"], 
        .form-edit-container select, 
        .form-edit-container input[type="file"] {
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
        .form-edit-container input:focus, .form-edit-container select:focus { outline: none; border-color: #1e40af; background-color: white; }

        .current-file-box { 
            border: 1px solid #cbd5e1; 
            padding: 12px; 
            margin-bottom: 16px; 
            border-radius: 8px; 
            background: #f8fafc; 
            font-size: 13px; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px;
            width: 100%;
            box-sizing: border-box;
        }
        .current-file-box i { color: var(--mantap-blue-main); }
        .file-preview-link { color: var(--mantap-blue-main); text-decoration: none; font-weight: 600; }
        .file-preview-link:hover { text-decoration: underline; }

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
            <h1>Edit Surat Masuk</h1>
            <a href="persuratan.php?tab=masuk" class="btn-back-link">
                <i class="fas fa-arrow-left"></i> Kembali ke Administrasi Persuratan
            </a>
        </div>
        
        <?php echo $message; ?>

        <?php if ($surat_data): ?>
        <div class="form-edit-container">
            <form method="POST" action="surat-masuk-edit.php?id=<?php echo $surat_data['masuk_id']; ?>" enctype="multipart/form-data" style="box-shadow: none; max-width: 100%; margin: 0; padding: 0;">
                <input type="hidden" name="masuk_id" value="<?php echo $surat_data['masuk_id']; ?>">
                <input type="hidden" name="file_path_lama" value="<?php echo htmlspecialchars($surat_data['file_path']); ?>">

                <label for="nomor_surat_masuk">Nomor Surat Masuk:</label>
                <input type="text" id="nomor_surat_masuk" name="nomor_surat_masuk" value="<?php echo htmlspecialchars($surat_data['nomor_surat_masuk']); ?>" placeholder="Masukkan nomor agenda surat masuk..." required>
                
                <label for="tanggal_terima">Tanggal Terima Surat:</label>
                <input type="date" id="tanggal_terima" name="tanggal_terima" value="<?php echo formatTanggalInput($surat_data['tanggal_terima']); ?>" required>

                <label for="asal_perusahaan">Asal Perusahaan / Instansi Pengirim:</label>
                <input type="text" id="asal_perusahaan" name="asal_perusahaan" value="<?php echo htmlspecialchars($surat_data['asal_perusahaan']); ?>" placeholder="Masukkan nama instansi..." required>

                <label for="perihal">Perihal / Isi Ringkas Surat Masuk:</label>
                <input type="text" id="perihal" name="perihal" value="<?php echo htmlspecialchars($surat_data['perihal']); ?>" placeholder="Masukkan perihal surat..." required>
                
                <label>Dokumen Scan Saat Ini:</label>
                <div class="current-file-box">
                    <i class="fas fa-file-alt"></i> 
                    <?php if (!empty($surat_data['file_path'])): ?>
                        <a href="<?php echo $upload_dir . urlencode($surat_data['file_path']); ?>" target="_blank" class="file-preview-link">
                            <?php echo basename($surat_data['file_path']); ?> (Klik untuk Preview)
                        </a>
                    <?php else: ?>
                        <span class="text-muted" style="font-style: italic;">Tidak ada dokumen arsip terlampir.</span>
                    <?php endif; ?>
                </div>
                
                <label for="file_dokumen_new">Ganti Lampiran File Pindai/Scan (PDF/Doc/Gambar - Opsional):</label>
                <input type="file" id="file_dokumen_new" name="file_dokumen_new" accept=".pdf, .doc, .docx, .jpg, .jpeg, .png">

                <button type="submit" class="btn-submit-save">
                    <i class="fas fa-save"></i> Simpan Perubahan Surat Masuk
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php include 'panel/footer.php'; ?>
</body>
</html>