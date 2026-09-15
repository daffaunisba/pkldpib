<?php 
// admin/periode-edit.php
include 'auth-check.php'; 
include '../config/db-koneksi.php';

$current_user = isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin';
$current_user_id = $_SESSION['user_id'] ?? 0; // Untuk kebutuhan log
$message = '';
$periode_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$periode_data = null;

if ($periode_id === 0) {
    die("<div class='alert error'>ID Periode tidak valid. Kembali ke <a href='periode-manage.php'>Kelola Periode</a>.</div>");
}

// --- LOGIKA UPDATE PERIODE ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $periode_id_post = (int)$_POST['periode_id'];
    $nama = trim($_POST['nama_periode']);
    $mulai = trim($_POST['tgl_mulai']);
    $akhir = trim($_POST['tgl_akhir']);
    $kuota = (int)$_POST['kuota_gelombang']; 
    
    if (!empty($nama) && !empty($mulai) && !empty($akhir) && $kuota >= 0) {
        
        // --- DETEKSI PERUBAHAN DATA UNTUK LOG SUPER DETAIL ---
        $stmt_old = $koneksi->prepare("SELECT nama_periode, tgl_mulai, tgl_akhir, kuota_gelombang FROM periode_pkl WHERE periode_id = ?");
        $stmt_old->bind_param("i", $periode_id_post);
        $stmt_old->execute();
        $old_data = $stmt_old->get_result()->fetch_assoc();
        $stmt_old->close();

        $perubahan = [];
        if ($old_data['nama_periode'] != $nama) $perubahan[] = "Nama Gelombang";
        if ($old_data['tgl_mulai'] != $mulai) $perubahan[] = "Tanggal Mulai";
        if ($old_data['tgl_akhir'] != $akhir) $perubahan[] = "Tanggal Akhir";
        if ($old_data['kuota_gelombang'] != $kuota) $perubahan[] = "Kuota Maksimum";

        $pesan_log = "";
        if (count($perubahan) > 0) {
            $detail_ubah = implode(", ", $perubahan);
            // Contoh Hasil: Memperbarui data periode PKL: Gelombang 1 (Detail yang diubah: Kuota Maksimum, Tanggal Akhir)
            $pesan_log = "Memperbarui data periode PKL: " . $old_data['nama_periode'] . " (Detail yang diubah: " . $detail_ubah . ")";
        } else {
            $pesan_log = "Mengecek/menyimpan ulang data periode PKL: " . $old_data['nama_periode'] . " (Tanpa perubahan data)";
        }
        // ------------------------------------------

        $update_stmt = $koneksi->prepare("UPDATE periode_pkl SET nama_periode = ?, tgl_mulai = ?, tgl_akhir = ?, kuota_gelombang = ? WHERE periode_id = ?");
        $update_stmt->bind_param("sssii", $nama, $mulai, $akhir, $kuota, $periode_id_post);
        
        if ($update_stmt->execute()) {
            // --- TRIGGER LOG AKTIVITAS (UPDATE DETAIL) ---
            catatLog($koneksi, $current_user_id, $pesan_log);

            // Redirect ke halaman manage setelah sukses dengan PRG Pattern
            $success_msg = urlencode("✅ Periode '{$nama}' berhasil diperbarui dengan kuota {$kuota}.");
            header("Location: periode-manage.php?status=success_update&msg=" . $success_msg);
            exit();
        } else {
            $message = "<div class='alert error'>Gagal memperbarui data: " . $update_stmt->error . "</div>";
        }
        $update_stmt->close();
    } else {
        $message = "<div class='alert warning'>Semua field wajib diisi, dan Kuota Gelombang harus angka positif.</div>";
    }
    
    // Sinkronisasi ID jika terjadi kegagalan agar data tidak kosong saat memuat ulang form
    $periode_id = $periode_id_post;
}

// --- AMBIL DATA PERIODE SAAT INI ---
$periode_stmt = $koneksi->prepare("SELECT periode_id, nama_periode, tgl_mulai, tgl_akhir, kuota_gelombang FROM periode_pkl WHERE periode_id = ?");
$periode_stmt->bind_param("i", $periode_id);
$periode_stmt->execute();
$result = $periode_stmt->get_result();

if ($result->num_rows > 0) {
    $periode_data = $result->fetch_assoc();
} else {
    $message = "<div class='alert error'>Data periode tidak ditemukan.</div>";
}
$periode_stmt->close();

function formatTanggalInput($date_str) {
    return date('Y-m-d', strtotime($date_str));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin - Edit Periode PKL | Si Mantap PKL</title>
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

        /* STRUKTUR UTAMA KONSISTEN */
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

        .alert.success, .alert.error, .alert.warning { padding: 12px 15px; margin-bottom: 20px; border-radius: 8px; border: 1px solid; font-size: 14px; font-weight: 500; }
        .alert.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .alert.warning { background-color: #fff3cd; color: #856404; border-color: #ffeeba; }
        
        /* CARD PANEL FORM EDIT */
        .form-edit-container {
            background-color: white;
            padding: 30px;
            border-radius: 16px;
            max-width: 740px; /* Lebar maksimal disamakan dengan dialog modal */
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
        }
        
        .form-edit-container input[type="text"],
        .form-edit-container input[type="date"],
        .form-edit-container input[type="number"] {
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

        /* GRID FORM */
        .form-grid-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; box-sizing: border-box; }
        .form-inner-group { text-align: left; box-sizing: border-box; }

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
            gap: 6px;
            text-decoration: none;
        }
        .btn-submit-save:hover { background-color: var(--mantap-blue-light); }

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
        
        .sidebar-nav ul, .sidebar-nav li, .dropdown-menu, .dropdown-menu li { list-style: none !important; list-style-type: none !important; margin: 0; padding: 0; }

        /* =========================================================================
            RESPONSIVE HANDPHONE VIEW (MOBILE SMARTPHONE)
        ========================================================================= */
        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 

            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; gap: 10px !important; padding: 5px 0px !important; }
            .page-header-controls h1 { font-size: 1.4rem !important; }
            
            .form-edit-container { padding: 20px 14px !important; border-radius: 12px !important; }
            .form-grid-row { display: flex !important; flex-direction: column !important; gap: 0px !important; }
            
            .form-edit-container button.btn-submit-save { width: 100% !important; justify-content: center !important; padding: 12px !important; border-radius: 8px !important; font-size: 14px !important; }
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
            <h1>Edit Periode PKL</h1>
            <a href="periode-manage.php" class="btn-back-link">
                <i class="fas fa-arrow-left"></i> Kembali ke Daftar Periode
            </a>
        </div>
        
        <?php echo $message; ?>

        <?php if ($periode_data): ?>
        <div class="form-edit-container">
            <form method="POST" action="periode-edit.php?id=<?php echo $periode_data['periode_id']; ?>">
                <input type="hidden" name="periode_id" value="<?php echo $periode_data['periode_id']; ?>">

                <label for="nama_periode">Nama Gelombang/Periode:</label>
                <input type="text" id="nama_periode" name="nama_periode" value="<?php echo htmlspecialchars($periode_data['nama_periode']); ?>" required>

                <div class="form-grid-row">
                    <div class="form-inner-group">
                        <label for="tgl_mulai">Tanggal Mulai PKL:</label>
                        <input type="date" id="tgl_mulai" name="tgl_mulai" value="<?php echo formatTanggalInput($periode_data['tgl_mulai']); ?>" required>
                    </div>
                    <div class="form-inner-group">
                        <label for="tgl_akhir">Tanggal Berakhir PKL:</label>
                        <input type="date" id="tgl_akhir" name="tgl_akhir" value="<?php echo formatTanggalInput($periode_data['tgl_akhir']); ?>" required>
                    </div>
                </div>
                
                <label for="kuota_gelombang">Kuota Maksimum Siswa untuk Gelombang Ini:</label>
                <input type="number" id="kuota_gelombang" name="kuota_gelombang" 
                       value="<?php echo htmlspecialchars($periode_data['kuota_gelombang']); ?>" 
                       min="0" required>
                
                <button type="submit" class="btn-submit-save">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php include 'panel/footer.php'; ?>
</body>
</html>