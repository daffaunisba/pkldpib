<?php
// admin/format-laporan.php
include 'auth-check.php'; 
include '../config/db-koneksi.php'; 

// --- PROTEKSI KEAMANAN: Menggunakan $_SESSION['level'] sesuai sistem login ---
$user_level = isset($_SESSION['level']) ? strtolower($_SESSION['level']) : '';

// Jika bukan admin, tendang ke dashboard (Sesuai permintaan: Format Laporan hanya untuk Admin)
if ($user_level !== 'admin') {
    header("Location: dashboard.php?status=restricted");
    exit();
}

$current_user = $_SESSION['username'] ?? 'Admin'; 
$current_user_id = $_SESSION['user_id'] ?? 0; // Ditambahkan untuk kebutuhan Log
$message = '';
$upload_dir = '../uploads/format/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// ---------------------------------------------------------------------
// LOGIKA UPLOAD/UPDATE FILE
// ---------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['tipe_upload'])) {
    $tipe = $_POST['tipe_upload']; 
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $tipe_label = ($tipe === 'proposal') ? 'Proposal PKL' : 'Pelaporan Sidang PKL';
    
    // --- AMBIL DATA LAMA UNTUK DETEKSI PERUBAHAN LOG ---
    $stmt_old = $koneksi->prepare("SELECT deskripsi, file_path FROM format_laporan WHERE tipe = ?");
    $stmt_old->bind_param("s", $tipe);
    $stmt_old->execute();
    $old_data = $stmt_old->get_result()->fetch_assoc();
    $stmt_old->close();
    
    $old_deskripsi = $old_data['deskripsi'] ?? '';

    // Jika ada file yang diunggah
    if (isset($_FILES['file_format']) && $_FILES['file_format']['error'] == 0) {
        $file_ext = strtolower(pathinfo($_FILES['file_format']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['pdf', 'doc', 'docx', 'zip', 'rar'];
        
        if (in_array($file_ext, $allowed_ext)) {
            $safe_tipe = str_replace(['.', ' '], '_', $tipe);
            $file_name = "format_{$safe_tipe}_" . date('YmdHis') . '.' . $file_ext;
            $file_destination = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['file_format']['tmp_name'], $file_destination)) {
                $check = $koneksi->query("SELECT file_path FROM format_laporan WHERE tipe = '{$tipe}'");
                $old_file = $check->num_rows > 0 ? $check->fetch_assoc()['file_path'] : null;

                $update_stmt = $koneksi->prepare("
                    INSERT INTO format_laporan (tipe, deskripsi, file_path) 
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE deskripsi = VALUES(deskripsi), file_path = VALUES(file_path)
                ");
                $update_stmt->bind_param("sss", $tipe, $deskripsi, $file_name);
                
                if ($update_stmt->execute()) {
                    if ($old_file && file_exists($upload_dir . $old_file)) {
                        unlink($upload_dir . $old_file);
                    }
                    
                    // --- TRIGGER LOG AKTIVITAS (UPDATE BESERTA FILE BARU) ---
                    $perubahan = [];
                    if ($old_deskripsi != $deskripsi) $perubahan[] = "Deskripsi Instruksi";
                    $perubahan[] = "File Dokumen";
                    
                    $detail_ubah = implode(", ", $perubahan);
                    catatLog($koneksi, $current_user_id, "Memperbarui format unduhan siswa: {$tipe_label} (Detail yang diubah: {$detail_ubah})");

                    $message = "<div class='alert success'>✅ File format " . ucfirst($tipe) . " berhasil diperbarui!</div>";
                } else {
                    $message = "<div class='alert error'>Gagal menyimpan data ke database.</div>";
                }
                $update_stmt->close();
            } else {
                $message = "<div class='alert error'>Gagal mengunggah file ke server.</div>";
            }
        } else {
            $message = "<div class='alert error'>Jenis file tidak didukung (PDF/DOCX/ZIP/RAR).</div>";
        }
    } else {
        // Jika hanya memperbarui deskripsi tanpa ganti file
        if (isset($_POST['deskripsi'])) {
             $update_stmt = $koneksi->prepare("
                INSERT INTO format_laporan (tipe, deskripsi) 
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE deskripsi = VALUES(deskripsi)
            ");
            $update_stmt->bind_param("ss", $tipe, $deskripsi);
            
            if ($update_stmt->execute()) {
                
                // --- TRIGGER LOG AKTIVITAS (UPDATE DESKRIPSI SAJA) ---
                if ($old_deskripsi != $deskripsi) {
                    catatLog($koneksi, $current_user_id, "Memperbarui format unduhan siswa: {$tipe_label} (Detail yang diubah: Deskripsi Instruksi)");
                }

                $message = "<div class='alert success'>✅ Deskripsi " . ucfirst($tipe) . " diperbarui!</div>";
            }
            $update_stmt->close();
        }
    }
}

// QUERY DATA
$data_formats = [];
$formats_result = $koneksi->query("SELECT tipe, deskripsi, file_path FROM format_laporan");
if ($formats_result) {
    while ($row = $formats_result->fetch_assoc()) {
        $data_formats[$row['tipe']] = $row;
    }
}

$proposal = $data_formats['proposal'] ?? ['deskripsi' => '', 'file_path' => null];
$pelaporan = $data_formats['pelaporan'] ?? ['deskripsi' => '', 'file_path' => null];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin - Kelola Format Laporan | Si Mantap PKL</title>
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

        /* STRUKTUR GAP INTERN KONSISTEN */
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

        .alert { padding: 12px 15px; margin-bottom: 20px; border-radius: 8px; border: 1px solid; font-size: 14px; font-weight: 500; }
        .alert.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }

        /* GRID DUA KOLOM SINKRON */
        .format-grid { 
            display: grid; 
            grid-template-columns: repeat(2, 1fr); 
            gap: 25px; 
            margin-top: 5px; 
            box-sizing: border-box;
            width: 100%;
        }

        .card-format { 
            background: white !important; 
            padding: 25px !important; 
            border-radius: 16px !important; 
            border: 2px solid #e2e8f0 !important; 
            box-sizing: border-box;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        }

        .card-format h2 { 
            margin-top: 0; 
            font-size: 1.3rem; 
            font-weight: 700;
            color: var(--mantap-blue-dark);
            border-bottom: 2px solid #f1f5f9; 
            padding-bottom: 12px; 
            margin-bottom: 20px;
        }

        .card-format label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13.5px; color: #0f172a; }
        
        .card-format textarea, 
        .card-format input[type="file"] { 
            width: 100%; 
            padding: 11px 14px; 
            border: 1px solid #cbd5e1; 
            border-radius: 8px; 
            margin-bottom: 16px; 
            box-sizing: border-box; 
            font-family: 'Poppins', sans-serif;
            font-size: 13.5px;
            background-color: #f8fafc;
        }
        .card-format textarea:focus { outline: none; border-color: #1e40af; background-color: white; }
        .card-format textarea { resize: vertical; }

        .btn-upload { 
            background-color: var(--mantap-blue-main); 
            color: white; 
            padding: 11px 20px; 
            border: none; 
            border-radius: 20px; 
            cursor: pointer; 
            font-weight: 600; 
            font-size: 13px;
            font-family: 'Poppins', sans-serif;
            width: 100%; 
            box-shadow: 0 4px 6px -1px rgba(30, 64, 175, 0.15);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .btn-upload:hover { background-color: var(--mantap-blue-light); }

        .file-status { 
            margin-bottom: 20px; 
            font-size: 12.5px; 
            padding: 12px; 
            background: #f8fafc; 
            border-radius: 8px; 
            border: 1px solid #e2e8f0;
            font-weight: 500;
            color: #475569;
        }
        .status-file-ok { color: #22c55e; font-weight: 700; }
        .status-file-missing { color: #ef4444; font-weight: 700; }

        .sidebar-nav ul, .sidebar-nav li, .dropdown-menu, .dropdown-menu li { list-style: none !important; margin: 0; padding: 0; }

        /* =========================================================================
           RESPONSIVE VIEWPORT SMARTPHONE (HP)
        ========================================================================= */
        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 

            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; padding: 5px 0px !important; }
            .page-header-controls h1 { font-size: 1.4rem !important; }
            
            /* Mengubah grid laptop menjadi susunan bertumpuk satu kolom penuh di HP */
            .format-grid { display: flex !important; flex-direction: column !important; gap: 20px !important; }
            .card-format { padding: 20px 14px !important; border-radius: 12px !important; border: 2px solid #cbd5e1 !important; }
            .card-format h2 { font-size: 1.2rem !important; }
            
            .card-format textarea, .card-format input[type="file"] { font-size: 13.5px !important; padding: 10px !important; }
            .btn-upload { padding: 12px !important; font-size: 14.5px !important; border-radius: 8px !important; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; ?>

<div class="main-content-wrapper">

    <?php include 'panel/navbar.php'; ?>

    <div class="admin-main-content"> 
        <div class="page-header-controls">
            <h1><i class="fas fa-file-export" style="color: var(--mantap-blue-main); margin-right: 4px;"></i> Kelola Format Download Murid</h1>
        </div>
        
        <?php echo $message; ?>

        <div class="format-grid">
            <div class="card-format">
                <h2><i class="fas fa-file-alt" style="color: var(--mantap-blue-main); margin-right: 4px;"></i> Format Proposal PKL</h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="tipe_upload" value="proposal">
                    
                    <label for="desc_proposal">Deskripsi Instruksi Murid:</label>
                    <textarea id="desc_proposal" name="deskripsi" rows="3" placeholder="Tuliskan catatan atau panduan pengerjaan berkas..." required><?php echo htmlspecialchars($proposal['deskripsi']); ?></textarea>
                    
                    <label for="file_proposal">Pilih File Berkas:</label>
                    <input type="file" id="file_proposal" name="file_format" accept=".pdf,.doc,.docx,.zip,.rar">
                    
                    <div class="file-status">
                        <i class="fas fa-info-circle me-1" style="color:#94a3b8;"></i> Status Storage: 
                        <span class="status-file-<?php echo $proposal['file_path'] ? 'ok' : 'missing'; ?>">
                            <?php echo $proposal['file_path'] ? 'FILE TERSEDIA' : 'BELUM ADA FILE'; ?>
                        </span>
                    </div>
                    
                    <button type="submit" class="btn-upload"><i class="fas fa-sync-alt"></i> Update Berkas Proposal</button>
                </form>
            </div>

            <div class="card-format">
                <h2><i class="fas fa-book" style="color: var(--mantap-blue-main); margin-right: 4px;"></i> Format Pelaporan Sidang PKL</h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="tipe_upload" value="pelaporan">
                    
                    <label for="desc_pelaporan">Deskripsi Instruksi Murid:</label>
                    <textarea id="desc_pelaporan" name="deskripsi" rows="3" placeholder="Tuliskan catatan atau panduan pengerjaan berkas..." required><?php echo htmlspecialchars($pelaporan['deskripsi']); ?></textarea>
                    
                    <label for="file_pelaporan">Pilih File Berkas:</label>
                    <input type="file" id="file_pelaporan" name="file_format" accept=".pdf,.doc,.docx,.zip,.rar">
                    
                    <div class="file-status">
                        <i class="fas fa-info-circle me-1" style="color:#94a3b8;"></i> Status Storage: 
                        <span class="status-file-<?php echo $pelaporan['file_path'] ? 'ok' : 'missing'; ?>">
                            <?php echo $pelaporan['file_path'] ? 'FILE TERSEDIA' : 'BELUM ADA FILE'; ?>
                        </span>
                    </div>
                    
                    <button type="submit" class="btn-upload"><i class="fas fa-sync-alt"></i> Update Format Sidang</button>
                </form>
            </div>
        </div>
    </div>

</div>

<?php include 'panel/footer.php'; ?>
</body>
</html>