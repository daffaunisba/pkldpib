<?php
// admin/profil-edit.php
// Halaman untuk menambah/mengedit detail profil perusahaan mitra PKL.

include 'auth-check.php';
include '../config/db-koneksi.php';

$current_user = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin';
$lokasi_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$lokasi_info = null;
$profil_data = null;
$message = '';

if ($lokasi_id === 0) {
    die("<div class='alert error'>ID Lokasi tidak valid.</div>");
}

// ---------------------------------------------------------------------
// 1. AMBIL INFO LOKASI UTAMA
// ---------------------------------------------------------------------
$stmt_lokasi = $koneksi->prepare("SELECT nama_lokasi, alamat FROM lokasi_pkl WHERE lokasi_id = ?");
$stmt_lokasi->bind_param("i", $lokasi_id);
$stmt_lokasi->execute();
$lokasi_info = $stmt_lokasi->get_result()->fetch_assoc();
$stmt_lokasi->close();

if (!$lokasi_info) {
    die("<div class='alert error'>Lokasi tidak ditemukan.</div>");
}


// ---------------------------------------------------------------------
// 2. LOGIKA UPDATE / INSERT PROFIL
// ---------------------------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $lokasi_id_post = (int)$_POST['lokasi_id'];
    $bidang_usaha = trim($_POST['bidang_usaha']);
    $website = trim($_POST['website']);
    $telp_hrd = trim($_POST['telp_hrd']);
    $deskripsi_detail = trim($_POST['deskripsi_detail']);
    
    // Cek apakah profil sudah ada (untuk menentukan INSERT atau UPDATE)
    $check_profil = $koneksi->query("SELECT lokasi_id FROM profil_perusahaan WHERE lokasi_id = {$lokasi_id_post}");
    
    if ($check_profil->num_rows > 0) {
        // UPDATE
        $stmt = $koneksi->prepare("UPDATE profil_perusahaan SET bidang_usaha = ?, website = ?, telp_hrd = ?, deskripsi_detail = ? WHERE lokasi_id = ?");
        $stmt->bind_param("ssssi", $bidang_usaha, $website, $telp_hrd, $deskripsi_detail, $lokasi_id_post);
        $action = 'diperbarui';
    } else {
        // INSERT
        $stmt = $koneksi->prepare("INSERT INTO profil_perusahaan (lokasi_id, bidang_usaha, website, telp_hrd, deskripsi_detail) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $lokasi_id_post, $bidang_usaha, $website, $telp_hrd, $deskripsi_detail);
        $action = 'ditambahkan';
    }

    if ($stmt->execute()) {
        $message = "<div class='alert success'>✅ Profil perusahaan " . htmlspecialchars($lokasi_info['nama_lokasi']) . " berhasil {$action}!</div>";
    } else {
        $message = "<div class='alert error'>Gagal menyimpan profil: " . $koneksi->error . "</div>";
    }
    $stmt->close();
}


// ---------------------------------------------------------------------
// 3. AMBIL DATA PROFIL SAAT INI (SETELAH UPDATE JIKA ADA)
// ---------------------------------------------------------------------
$stmt_profil = $koneksi->prepare("SELECT bidang_usaha, website, telp_hrd, deskripsi_detail FROM profil_perusahaan WHERE lokasi_id = ?");
$stmt_profil->bind_param("i", $lokasi_id);
$stmt_profil->execute();
$profil_result = $stmt_profil->get_result();

if ($profil_result->num_rows > 0) {
    $profil_data = $profil_result->fetch_assoc();
} else {
    // Set default empty array if no profile exists
    $profil_data = ['bidang_usaha' => '', 'website' => '', 'telp_hrd' => '', 'deskripsi_detail' => ''];
}
$stmt_profil->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin - Edit Profil <?php echo htmlspecialchars($lokasi_info['nama_lokasi']); ?> | Si Mantap PKL</title>
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
        .alert.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        
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

        .form-info-box {
            background-color: var(--mantap-blue-soft);
            border-left: 5px solid var(--mantap-blue-main);
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 8px;
            text-align: left;
        }
        .form-info-box p { margin: 6px 0; font-size: 13.5px; color: #334155; }
        .form-info-box strong { color: var(--mantap-blue-dark); font-weight: 700; }

        .form-edit-container label { 
            display: block; 
            margin-bottom: 6px; 
            font-weight: 600; 
            font-size: 13.5px; 
            color: #0f172a; 
            text-align: left;
        }
        
        .form-edit-container input[type="text"], 
        .form-edit-container input[type="url"],
        .form-edit-container textarea {
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
        .form-edit-container input:focus, .form-edit-container textarea:focus { outline: none; border-color: #1e40af; background-color: white; }

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
            .form-info-box p { font-size: 13px !important; }
            .form-edit-container input, .form-edit-container textarea { font-size: 13.5px !important; padding: 10px !important; }
            
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
            <h1>Edit Detail Profil Perusahaan</h1>
            <a href="profil.php" class="btn-back-link">
                <i class="fas fa-arrow-left"></i> Kembali ke Katalog Profil
            </a>
        </div>
        
        <?php echo $message; ?>

        <div class="form-edit-container">
            <div class="form-info-box">
                <p><strong>Nama Mitra Perusahaan:</strong> <?php echo htmlspecialchars($lokasi_info['nama_lokasi']); ?></p>
                <p><strong>Alamat Operasional:</strong> <?php echo htmlspecialchars($lokasi_info['alamat']); ?></p>
            </div>

            <form method="POST" action="profil-edit.php?id=<?php echo $lokasi_id; ?>" style="box-shadow: none; max-width: 100%; margin: 0; padding: 0;">
                <input type="hidden" name="lokasi_id" value="<?php echo $lokasi_id; ?>">

                <label for="bidang_usaha">Bidang Usaha Core Bisnis (Contoh: Jasa Konstruksi, IT Consultant, Perbankan):</label>
                <input type="text" id="bidang_usaha" name="bidang_usaha" value="<?php echo htmlspecialchars($profil_data['bidang_usaha']); ?>" placeholder="Masukkan bidang usaha..." required>
                
                <label for="website">Alamat URL Website Perusahaan (Opsional):</label>
                <input type="url" id="website" name="website" value="<?php echo htmlspecialchars($profil_data['website']); ?>" placeholder="Contoh: https://www.nama-industri.com">
                
                <label for="telp_hrd">Nomor Telepon Kontak HRD / Supervisor Lapangan:</label>
                <input type="text" id="telp_hrd" name="telp_hrd" value="<?php echo htmlspecialchars($profil_data['telp_hrd']); ?>" placeholder="Contoh: 08123456xxx atau (0342) xxxxxx">

                <label for="deskripsi_detail">Uraian Deskripsi Perusahaan / Persyaratan & Kompetensi Khusus PKL:</label>
                <textarea id="deskripsi_detail" name="deskripsi_detail" rows="5" placeholder="Tuliskan profil singkat industri, aturan seragam, atau kualifikasi tools/bahasa pemrograman khusus yang wajib dikuasai siswa bimbingan..."><?php echo htmlspecialchars($profil_data['deskripsi_detail']); ?></textarea>
                
                <button type="submit" class="btn-submit-save">
                    <i class="fas fa-save"></i> Simpan Profil Perusahaan
                </button>
            </form>
        </div>
    </div>

</div>

<?php include 'panel/footer.php'; ?>
</body>
</html>