<?php
// admin/kendali-detail.php
// Halaman untuk melihat dan mengelola checklist kemajuan (Kartu Kendali) per siswa.

include 'auth-check.php';
include '../config/db-koneksi.php';

$current_user = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin';
$siswa_id = isset($_GET['siswa_id']) ? (int)$_GET['siswa_id'] : 0;
$message = '';
$kegiatan_list = [];

if ($siswa_id === 0) {
    die("<div class='alert error'>ID Siswa tidak valid. Harap kembali ke <a href='kartu-kendali.php'>daftar kartu kendali</a>.</div>");
}

// ---------------------------------------------------------------------
// 1. DAFTAR KEGIATAN KENDALI (DINAMIS DARI DB)
// ---------------------------------------------------------------------
$kegiatan_result = $koneksi->query("
    SELECT id, urutan, deskripsi 
    FROM kendali_kegiatan_def 
    WHERE is_active = TRUE 
    ORDER BY urutan ASC
");

$kegiatan_def = [];
if ($kegiatan_result) {
    while ($row = $kegiatan_result->fetch_assoc()) {
        $kegiatan_def[$row['id']] = [
            'urutan' => $row['urutan'],
            'deskripsi' => $row['deskripsi']
        ];
    }
}

// ---------------------------------------------------------------------
// 2. QUERY DATA SISWA & STATUS KENDALI
// ---------------------------------------------------------------------
$siswa_query = $koneksi->prepare("
    SELECT 
        p.id, p.nama AS nama_siswa, p.kelas, p.nisn, p.no_hp AS no_hp_siswa,
        l.nama_lokasi, l.lokasi_id,
        g.nama_guru, g.no_hp AS no_hp_pembimbing
    FROM peserta_didik p
    LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
    LEFT JOIN guru g ON l.guru_id = g.guru_id
    WHERE p.id = ?
");
$siswa_query->bind_param("i", $siswa_id);
$siswa_query->execute();
$siswa_info = $siswa_query->get_result()->fetch_assoc();
$siswa_query->close();

if (!$siswa_info) {
    die("<div class='alert error'>Data siswa ID {$siswa_id} tidak ditemukan.</div>");
}

$kendali_query_stmt = $koneksi->prepare("
    SELECT kegiatan_id, status FROM kartu_kendali WHERE siswa_id = ?
");
$kendali_query_stmt->bind_param("i", $siswa_id);
$kendali_query_stmt->execute();
$kendali_result = $kendali_query_stmt->get_result();

$status_kendali = [];
if ($kendali_result) {
    while ($row = $kendali_result->fetch_assoc()) {
        $status_kendali[$row['kegiatan_id']] = $row['status'];
    }
}
$kendali_query_stmt->close(); 

// ---------------------------------------------------------------------
// 3. LOGIKA UPDATE STATUS (HANDLE POST)
// ---------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_kendali'])) {
    $siswa_id_post = (int)$_POST['siswa_id']; 
    $kegiatan_id_post = (int)$_POST['kegiatan_id'];
    $new_status = isset($_POST['status']) ? 1 : 0; 

    if (array_key_exists($kegiatan_id_post, $kegiatan_def)) {
        
        $check_exist = $koneksi->prepare("SELECT COUNT(*) FROM kartu_kendali WHERE siswa_id = ? AND kegiatan_id = ?");
        $check_exist->bind_param("ii", $siswa_id_post, $kegiatan_id_post);
        $check_exist->execute();
        $is_exist = $check_exist->get_result()->fetch_array()[0];
        $check_exist->close();
        
        if ($is_exist) {
            $stmt = $koneksi->prepare("UPDATE kartu_kendali SET status = ? WHERE siswa_id = ? AND kegiatan_id = ?");
            $stmt->bind_param("iii", $new_status, $siswa_id_post, $kegiatan_id_post);
        } else {
            $stmt = $koneksi->prepare("INSERT INTO kartu_kendali (siswa_id, kegiatan_id, status) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $siswa_id_post, $kegiatan_id_post, $new_status);
        }

        if ($stmt->execute()) {
            header("Location: kendali-detail.php?siswa_id={$siswa_id_post}&msg=success");
            exit();
        } else {
            $message = "<div class='alert error'>Gagal memperbarui status kendali.</div>";
        }
        $stmt->close();
    }
}

if (isset($_GET['msg']) && $_GET['msg'] == 'success') {
    $message = "<div class='alert success'>✅ Status kendali berhasil diperbarui secara instan.</div>";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin - Kartu Kendali: <?php echo htmlspecialchars($siswa_info['nama_siswa']); ?> | Si Mantap PKL</title>
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

        .btn-back {
            color: white !important;
            padding: 8px 16px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            font-size: 12px;
            background-color: #64748b;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08);
        }
        .btn-back:hover { background-color: #475569; }

        .alert { padding: 12px 15px; margin-bottom: 20px; border-radius: 8px; border: 1px solid; font-size: 14px; font-weight: 500; }
        .alert.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }

        .glass-panel-table {
            background: white !important; 
            padding: 25px !important; 
            border-radius: 16px !important; 
            border: 2px solid #e2e8f0 !important; 
            box-sizing: border-box; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); 
            width: 100%;
        }

        .info-siswa {
            background-color: var(--mantap-blue-soft);
            border-left: 5px solid var(--mantap-blue-main);
            padding: 18px;
            margin-bottom: 25px;
            border-radius: 8px;
            text-align: left;
        }
        .info-siswa p { margin: 6px 0; font-size: 13.5px; color: #334155; }
        .info-siswa strong { color: var(--mantap-blue-dark); font-weight: 700; display: inline-block; min-width: 140px; }

        .table-container-fixed { width: 100%; overflow-x: auto; box-sizing: border-box; border: none !important; }
        
        .kendali-table { 
            width: 100%; border-collapse: collapse; table-layout: fixed; border: 2px solid #1e40af; border-radius: 4px; overflow: hidden;
        }
        .kendali-table th { 
            background: #1e40af; color: white; padding: 14px 6px; font-size: 13px; text-transform: uppercase; font-weight: 700; text-align: center; border: 1px solid #cbd5e1; box-sizing: border-box;
        }
        
        .kendali-table th.w-no { width: 60px; }
        .kendali-table th.w-desc { width: 80%; text-align: left; padding-left: 15px; }
        .kendali-table th.w-status { width: 100px; }

        .kendali-table td { 
            padding: 14px 10px; font-size: 13px; vertical-align: middle; word-wrap: break-word; overflow-wrap: break-word; text-align: center; border: 1px solid #cbd5e1; color: #0f172a; background-color: white !important; box-sizing: border-box;
        }
        .kendali-table tr:hover td { background-color: #f8fafc !important; }

        .status-cell { text-align: center; position: relative; cursor: pointer; }
        .status-cell input[type="checkbox"] {
            opacity: 0; width: 28px; height: 28px; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); cursor: pointer; z-index: 10; margin: 0;
        }
        .checkmark {
            display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 6px; border: 2px solid #cbd5e1; color: transparent; font-size: 13px; transition: 0.2s; background-color: white; z-index: 5; box-sizing: border-box;
        }
        .status-cell input[type="checkbox"]:checked + .checkmark {
            background-color: #22c55e; border-color: #22c55e; color: white;
        }

        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 

            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; padding: 5px 0px !important; }
            .page-header-controls h1 { font-size: 1.4rem !important; }
            .btn-back { width: 100% !important; justify-content: center !important; padding: 11px !important; border-radius: 8px !important; }

            .glass-panel-table { padding: 16px 10px !important; border-radius: 12px !important; border: 2px solid #cbd5e1 !important; }
            .info-siswa { padding: 14px 12px !important; }
            .info-siswa p { font-size: 13px !important; }
            .info-siswa strong { min-width: 115px !important; }

            .table-container-fixed { display: block !important; width: 100% !important; overflow-x: auto !important; }
            .kendali-table { table-layout: auto !important; min-width: 650px !important; }
            .kendali-table th, .kendali-table td { padding: 12px 10px !important; }
            .kendali-table th.w-no, .kendali-table th.w-desc, .kendali-table th.w-status { width: auto !important; }
            .kendali-table td:nth-child(2) { text-align: left !important; padding-left: 10px !important; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; ?>

<div class="main-content-wrapper">

    <?php include 'panel/navbar.php'; ?>

    <div class="admin-main-content"> 
        
        <div class="page-header-controls">
            <h1>Kartu Kendali Progres PKL</h1>
            <a href="kartu-kendali.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali ke Daftar Siswa</a>
        </div>
        
        <?php echo $message; ?>

        <div class="glass-panel-table">
            <div class="info-siswa">
                <p><strong>Nama Murid:</strong> <?php echo htmlspecialchars($siswa_info['nama_siswa']); ?> (NISN: <?php echo htmlspecialchars($siswa_info['nisn']); ?>)</p>
                <p><strong>WhatsApp Aktif:</strong> <?php echo htmlspecialchars($siswa_info['no_hp_siswa'] ?? 'N/A'); ?></p> 
                <p><strong>Kelas / Jurusan:</strong> <?php echo htmlspecialchars($siswa_info['kelas']); ?></p>
                <p><strong>Lokasi Penempatan:</strong> <?php echo htmlspecialchars($siswa_info['nama_lokasi'] ?? 'Belum Ditetapkan'); ?></p>
                <p><strong>Guru Pembimbing:</strong> <?php echo htmlspecialchars($siswa_info['nama_guru'] ?? 'Belum Ditentukan'); ?></p>
            </div>
            
            <div class="table-container-fixed">
                <table class="kendali-table">
                    <thead>
                        <tr>
                            <th class="w-no">NO</th>
                            <th class="w-desc">KEGIATAN / TAHAPAN MONITORING</th>
                            <th class="w-status">STATUS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $row_num = 1; foreach ($kegiatan_def as $id => $data): 
                            $isChecked = $status_kendali[$id] ?? 0;
                        ?>
                        <tr>
                            <td style="font-weight: 700;"><?php echo $data['urutan'] ?? $row_num; ?></td>
                            <td style="text-align: left; padding-left: 15px; font-weight: 500; color: var(--mantap-blue-dark);"><?php echo htmlspecialchars($data['deskripsi']); ?></td>
                            <td class="status-cell">
                                <form method="POST" action="kendali-detail.php?siswa_id=<?php echo $siswa_id; ?>" style="margin: 0; padding: 0;">
                                    <input type="hidden" name="update_kendali" value="1">
                                    <input type="hidden" name="siswa_id" value="<?php echo $siswa_id; ?>"> 
                                    <input type="hidden" name="kegiatan_id" value="<?php echo $id; ?>">
                                    
                                    <input type="checkbox" 
                                           name="status" 
                                           id="kegiatan_<?php echo $id; ?>" 
                                           value="1" 
                                           <?php echo $isChecked ? 'checked' : ''; ?>
                                           onchange="this.form.submit()">
                                    
                                    <label for="kegiatan_<?php echo $id; ?>" class="checkmark">
                                        <?php if ($isChecked): ?>
                                            <i class="fas fa-check"></i>
                                        <?php endif; ?>
                                    </label>
                                </form>
                            </td>
                        </tr>
                        <?php $row_num++; endforeach; ?>
                        
                        <?php if (empty($kegiatan_def)): ?>
                             <tr>
                                 <td colspan="3" style="text-align: center; color: #ef4444; font-style: italic; padding: 25px;">
                                     Definisi kegiatan kosong. Silakan isi konfigurasi standar terlebih dahulu pada menu manajemen admin.
                                 </td>
                             </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php include 'panel/footer.php'; ?>
</body>
</html>