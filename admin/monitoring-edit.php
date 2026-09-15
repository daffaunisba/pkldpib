<?php
// admin/monitoring-edit.php
include 'auth-check.php';
include '../config/db-koneksi.php';

$current_user = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin';
$current_user_id = $_SESSION['user_id'] ?? 0;
$current_user_level = $_SESSION['level'] ?? 'admin';

$kunjungan_id = isset($_GET['kunjungan_id']) ? (int)$_GET['kunjungan_id'] : 0;

$upload_dir = '../uploads/monitoring/';
$message = '';
$monitoring_data = null;
$has_permission = false;

if ($kunjungan_id === 0) {
    die("<div class='alert error'>ID Kunjungan tidak valid.</div>");
}

// ---------------------------------------------------------------------
// 1. QUERY DATA AWAL & CEK HAK AKSES
// ---------------------------------------------------------------------

// Ambil data kunjungan, lokasi, dan guru yang mencatat.
$stmt_get_data = $koneksi->prepare("
    SELECT 
        k.kunjungan_id, k.lokasi_id, k.guru_id, k.tanggal_kunjungan, 
        k.catatan_guru, k.kritik_saran_hrd, k.bukti_foto,
        l.nama_lokasi, l.guru_id AS lokasi_guru_id,
        g_lokasi.user_id AS lokasi_guru_user_id
    FROM monitoring_kunjungan k
    JOIN lokasi_pkl l ON k.lokasi_id = l.lokasi_id
    LEFT JOIN guru g_lokasi ON l.guru_id = g_lokasi.guru_id
    WHERE k.kunjungan_id = ?
");
$stmt_get_data->bind_param("i", $kunjungan_id);
$stmt_get_data->execute();
$result = $stmt_get_data->get_result();

if ($result->num_rows > 0) {
    $monitoring_data = $result->fetch_assoc();
    $lokasi_guru_user_id = $monitoring_data['lokasi_guru_user_id'];
    
    // Tentukan Hak Akses: Hanya Admin atau Guru yang membimbing lokasi ini yang boleh mengedit
    if ($current_user_level == 'admin' || $current_user_id == $lokasi_guru_user_id) {
        $has_permission = true;
    }
} else {
    $message = "<div class='alert error'>Data kunjungan tidak ditemukan.</div>";
}
$stmt_get_data->close();

if (!$has_permission && $monitoring_data) {
    $message = "<div class='alert error'>Akses Ditolak. Anda tidak memiliki izin untuk mengedit data ini.</div>";
    $monitoring_data = null; // Hapus data agar form tidak ditampilkan
}

// ---------------------------------------------------------------------
// 2. LOGIKA UPDATE DATA
// ---------------------------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] == "POST" && $has_permission && $monitoring_data) {
    
    $tanggal_kunjungan = trim($_POST['tanggal_kunjungan']);
    $catatan_guru = trim($_POST['catatan_guru']);
    $kritik_saran_hrd = trim($_POST['kritik_saran_hrd']);
    $bukti_foto_lama = $_POST['bukti_foto_lama'];
    $bukti_foto_baru = $bukti_foto_lama; // Default, pertahankan foto lama

    // Proses Upload Foto Baru (jika ada)
    if (isset($_FILES['bukti_foto_new']) && $_FILES['bukti_foto_new']['error'] == 0) {
        $file_info = pathinfo($_FILES['bukti_foto_new']['name']);
        $file_ext = strtolower($file_info['extension']);
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($file_ext, $allowed_ext)) {
            $new_file_name = 'mon_' . $monitoring_data['lokasi_id'] . '_' . time() . '.' . $file_ext;
            $file_destination = $upload_dir . $new_file_name;
            
            if (move_uploaded_file($_FILES['bukti_foto_new']['tmp_name'], $file_destination)) {
                // Hapus foto lama jika ada
                if (!empty($bukti_foto_lama) && file_exists($upload_dir . $bukti_foto_lama)) {
                    unlink($upload_dir . $bukti_foto_lama);
                }
                $bukti_foto_baru = $new_file_name;
            } else {
                $message = "<div class='alert error'>Gagal mengupload file foto baru.</div>";
            }
        } else {
            $message = "<div class='alert error'>Jenis file tidak didukung. Harap upload JPG, JPEG, PNG, atau WEBP.</div>";
        }
    }
    
    // Hanya lakukan update jika tidak ada error upload
    if (empty($message)) {
        $update_stmt = $koneksi->prepare("UPDATE monitoring_kunjungan SET tanggal_kunjungan = ?, catatan_guru = ?, kritik_saran_hrd = ?, bukti_foto = ? WHERE kunjungan_id = ?");
        $update_stmt->bind_param("ssssi", $tanggal_kunjungan, $catatan_guru, $kritik_saran_hrd, $bukti_foto_baru, $kunjungan_id);

        if ($update_stmt->execute()) {
            // Redirect setelah sukses
            header("Location: lokasi-siswa-detail.php?lokasi_id={$monitoring_data['lokasi_id']}&success=kunjungan");
            exit();
        } else {
            $message = "<div class='alert error'>Gagal memperbarui data: " . $update_stmt->error . "</div>";
        }
        $update_stmt->close();
    }
}

// Ambil ulang data setelah (potensi) kegagalan POST untuk mengisi formulir
if (!$monitoring_data && $kunjungan_id > 0) {
    $stmt_get_data->bind_param("i", $kunjungan_id);
    $stmt_get_data->execute();
    $result = $stmt_get_data->get_result();
    $monitoring_data = $result->fetch_assoc();
    $stmt_get_data->close();
}


function formatTanggalInput($date_str) {
    return date('Y-m-d', strtotime($date_str));
}

function getFileName($path) {
    if (empty($path)) return '-';
    return basename($path);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin - Edit Kunjungan | Si Mantap PKL</title>
    
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

        /* INFO CARD DI DALAM FORM */
        .info-card-inline {
            background-color: var(--mantap-blue-soft);
            border-left: 5px solid var(--mantap-blue-main);
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            text-align: left;
        }
        .info-card-inline p { margin: 4px 0; font-size: 13px; color: #334155; }
        .info-card-inline strong { color: var(--mantap-blue-dark); font-weight: 700; }

        .form-add-container label { 
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
        }
        .form-add-container input:focus, .form-add-container textarea:focus { outline: none; border-color: #1e40af; background-color: white; }
        .form-add-container textarea { resize: vertical; }

        .current-photo-box {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px;
            background: #f8fafc;
            border-radius: 8px;
            margin-bottom: 16px;
            border: 1px solid #e2e8f0;
            font-size: 13.5px;
            font-weight: 500;
        }
        .current-photo-box img {
            max-width: 80px;
            height: auto;
            border-radius: 6px;
            border: 2px solid #cbd5e1;
        }

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
            box-shadow: 0 4px 6px -1px rgba(30, 64, 175, 0.15);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: 100%;
            box-sizing: border-box;
            transition: 0.2s;
        }
        .btn-submit-save:hover { background-color: var(--mantap-blue-light); transform: translateY(-1px); }

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

<?php include 'panel/sidebar.php'; include 'panel/navbar.php'; ?>

<div class="main-content-wrapper">

    <div class="admin-main-content"> 
        <div class="page-header-controls">
            <h1>Edit Kunjungan Monitoring</h1>
            <a href="lokasi-siswa-detail.php?lokasi_id=<?php echo $monitoring_data['lokasi_id'] ?? 0; ?>" class="btn-back-link">
                <i class="fas fa-arrow-left"></i> Kembali ke Detail Lokasi
            </a>
        </div>
        
        <?php echo $message; ?>

        <?php if ($monitoring_data): ?>
        <div class="form-add-container">
            <div class="info-card-inline">
                <p><strong>Lokasi Mitra DUDI:</strong> <?php echo htmlspecialchars($monitoring_data['nama_lokasi']); ?></p>
                <p><strong>Tanggal Kunjungan Awal:</strong> <?php echo formatTanggalInput($monitoring_data['tanggal_kunjungan']); ?></p>
            </div>

            <form method="POST" action="monitoring-edit.php?kunjungan_id=<?php echo $kunjungan_id; ?>" enctype="multipart/form-data" style="box-shadow: none; max-width: 100%; margin: 0; padding: 0;">
                <input type="hidden" name="bukti_foto_lama" value="<?php echo htmlspecialchars($monitoring_data['bukti_foto']); ?>">
                <input type="hidden" name="lokasi_id" value="<?php echo $monitoring_data['lokasi_id']; ?>">
                
                <label for="tanggal_kunjungan">Tanggal Kunjungan Baru:</label>
                <input type="date" id="tanggal_kunjungan" name="tanggal_kunjungan" value="<?php echo formatTanggalInput($monitoring_data['tanggal_kunjungan']); ?>" required>

                <label for="catatan_guru">Uraian / Catatan Guru:</label>
                <textarea id="catatan_guru" name="catatan_guru" rows="4" required><?php echo htmlspecialchars($monitoring_data['catatan_guru']); ?></textarea>

                <label for="kritik_saran_hrd">Kritik/Saran dari HRD/Pembimbing Lapangan:</label>
                <textarea id="kritik_saran_hrd" name="kritik_saran_hrd" rows="4" required><?php echo htmlspecialchars($monitoring_data['kritik_saran_hrd']); ?></textarea>
                
                <label>Bukti Foto Saat Ini:</label>
                <div class="current-photo-box">
                    <?php if (!empty($monitoring_data['bukti_foto'])): ?>
                        <img src="../uploads/monitoring/<?php echo urlencode($monitoring_data['bukti_foto']); ?>" alt="Foto Lama">
                        <span style="color: #475569;"><i class="fas fa-image opacity-50 me-1"></i> <?php echo getFileName($monitoring_data['bukti_foto']); ?></span>
                    <?php else: ?>
                        <span style="color: #ef4444; font-style: italic;">Tidak ada lampiran foto lama yang tersimpan.</span>
                    <?php endif; ?>
                </div>
                
                <label for="bukti_foto_new">Ganti Bukti Foto (Kosongkan jika tidak diubah):</label>
                <input type="file" id="bukti_foto_new" name="bukti_foto_new" accept=".jpg, .jpeg, .png, .webp">

                <div style="margin-top: 25px;">
                    <button type="submit" class="btn-submit-save">
                        <i class="fas fa-save"></i> Simpan Perubahan Kunjungan
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php include 'panel/footer.php'; ?>
</body>
</html>