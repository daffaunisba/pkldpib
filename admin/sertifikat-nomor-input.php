<?php
// admin/sertifikat-nomor-input.php
include 'auth-check.php';
include '../config/db-koneksi.php';

$current_user_level = $_SESSION['level'] ?? 'user'; 
$message = '';
$siswa_terpilih_id = isset($_GET['siswa_id']) ? (int)$_GET['siswa_id'] : 0;
$siswa_info = null;

if ($current_user_level !== 'admin') {
    die("<div class='alert error'>Akses Ditolak. Hanya Administrator yang diizinkan.</div>");
}

// ---------------------------------------------------------------------
// LOGIKA INPUT NOMOR SERTIFIKAT (POST) - Dipertahankan
// ---------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['set_nomor'])) {
    $siswa_id_post = (int)$_POST['siswa_id_target'];
    $nomor_sertifikat = trim($_POST['nomor_sertifikat']);
    $tanggal_terbit = trim($_POST['tanggal_terbit']);

    if (empty($nomor_sertifikat) || $siswa_id_post == 0) {
        $message = "<div class='alert error'>Nomor Sertifikat dan ID Siswa wajib diisi.</div>";
    } else {
        $stmt = $koneksi->prepare("
            INSERT INTO sertifikat_terbit (siswa_id, nomor_sertifikat, tanggal_terbit)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                nomor_sertifikat = VALUES(nomor_sertifikat),
                tanggal_terbit = VALUES(tanggal_terbit)
        ");
        $stmt->bind_param("iss", $siswa_id_post, $nomor_sertifikat, $tanggal_terbit);

        if ($stmt->execute()) {
            $message = "<div class='alert success'>✅ Nomor Sertifikat berhasil diatur/diperbarui: **{$nomor_sertifikat}**</div>";
            header("Location: sertifikat-nomor-input.php?status=success"); 
            exit();
        } else {
            $message = "<div class='alert error'>Gagal menyimpan nomor sertifikat: " . $koneksi->error . "</div>";
        }
        $stmt->close();
    }
}
if (isset($_GET['status']) && $_GET['status'] == 'success') {
    $message = "<div class='alert success'>✅ Nomor Sertifikat berhasil diatur.</div>";
}

// ---------------------------------------------------------------------
// 3. QUERY DAFTAR SEMUA SISWA & NOMOR SERTIFIKAT TERBIT
// ---------------------------------------------------------------------
$siswa_data_query = "
    SELECT 
        p.id, p.nisn, p.nama, p.kelas,
        l.nama_lokasi,
        s.nomor_sertifikat, s.tanggal_terbit
    FROM peserta_didik p
    LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
    LEFT JOIN sertifikat_terbit s ON p.id = s.siswa_id
    ORDER BY p.nama ASC
";
$siswa_data = $koneksi->query($siswa_data_query);

// ---------------------------------------------------------------------
// 4. AMBIL INFO SISWA TERPILIH (JIKA ADA) - Dipertahankan
// ---------------------------------------------------------------------
if ($siswa_terpilih_id > 0) {
    $query_info = "
        SELECT p.id, p.nisn, p.nama, p.kelas, s.nomor_sertifikat, s.tanggal_terbit
        FROM peserta_didik p
        LEFT JOIN sertifikat_terbit s ON p.id = s.siswa_id
        WHERE p.id = ?
    ";
    $stmt_info = $koneksi->prepare($query_info);
    $stmt_info->bind_param("i", $siswa_terpilih_id);
    $stmt_info->execute();
    $siswa_info = $stmt_info->get_result()->fetch_assoc();
    $stmt_info->close();
}

// Fungsi untuk format tanggal
function formatTanggal($tanggal) {
    if (empty($tanggal)) return '-';
    setlocale(LC_TIME, 'id_ID.utf8');
    return strftime('%d %B %Y', strtotime($tanggal));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin - Input Nomor Sertifikat | Si Mantap PKL</title>
    <link rel="stylesheet" href="../css/style.css"> 
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --mantap-blue-dark: #0f172a;
            --mantap-blue-main: #1e40af;
            --mantap-blue-light: #3b82f6;
            --mantap-blue-soft: #eff6ff;
            --mantap-green: #28a745;
            --mantap-green-hover: #218838;
            --mantap-orange: #ff8c00;
            --mantap-orange-hover: #ffa534;
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

        .btn-back { 
            color: var(--mantap-blue-main); 
            text-decoration: none; 
            font-weight: 600; 
            display: inline-flex; 
            align-items: center;
            gap: 8px;
            margin-bottom: 20px; 
            font-size: 14px;
            transition: color 0.2s;
        }
        .btn-back:hover { color: var(--mantap-blue-light); }

        .admin-main-content h1 {
            font-weight: 700;
            color: var(--mantap-blue-dark);
            font-size: 1.8rem; 
            margin: 0 0 25px 0;
            position: relative;
        }
        .admin-main-content h1::after {
            content: ''; position: absolute; left: 0; bottom: -5px; width: 50px; height: 4px; background: var(--mantap-blue-main); border-radius: 2px;
        }

        /* Message Alerts */
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; border: 1px solid; font-size: 14px; }
        .alert.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }

        /* Form Box */
        .input-form-box { 
            padding: 25px; 
            background: white; 
            border-radius: 16px; 
            border: 2px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); 
            margin-bottom: 30px; 
        }
        .input-form-box h2 { font-weight: 700; color: var(--mantap-blue-dark); margin-top: 0; margin-bottom: 20px; }
        .input-form-box label { display: block; margin-top: 15px; margin-bottom: 6px; font-weight: 600; color: #475569; font-size: 14px; }
        .input-form-box input[type="text"], .input-form-box input[type="date"] { 
            width: 100%; 
            padding: 11px 14px; 
            margin-top: 4px; 
            margin-bottom: 10px; 
            border-radius: 8px; 
            border: 2px solid #cbd5e1; 
            box-sizing: border-box; 
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            color: var(--mantap-blue-dark);
            transition: border-color 0.2s;
        }
        .input-form-box input[type="text"]:focus, .input-form-box input[type="date"]:focus {
            border-color: var(--mantap-blue-light);
            outline: none;
        }
        .btn-submit { 
            background-color: var(--mantap-orange); 
            color: white; 
            padding: 12px 24px; 
            border: none; 
            border-radius: 25px; 
            cursor: pointer; 
            margin-top: 15px; 
            font-weight: 600; 
            font-size: 13px;
            font-family: 'Poppins', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08);
            transition: background-color 0.2s;
        }
        .btn-submit:hover { background-color: var(--mantap-orange-hover); }

        /* Main Table Layout */
        .glass-panel-table {
            background: white !important;
            padding: 25px !important;
            border-radius: 16px !important;
            border: 2px solid #e2e8f0 !important; 
            box-sizing: border-box !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
            width: 100%;
        }
        .glass-panel-table h2 { margin-top: 0; margin-bottom: 20px; font-size: 1.4rem; font-weight: 700; color: var(--mantap-blue-dark); }

        .table-container-fixed {
            width: 100%;
            overflow-x: auto; 
            box-sizing: border-box !important;
        }

        .siswa-table-main { 
            width: 100%; 
            table-layout: fixed;
            border-collapse: collapse; 
            background: white;
            border-radius: 4px;
            overflow: hidden;
            border: 2px solid var(--mantap-blue-main);
        } 
        .siswa-table-main th { 
            background-color: var(--mantap-blue-main); 
            color: white; 
            padding: 14px 6px; 
            text-align: center; 
            font-size: 13px; 
            font-weight: 700; 
            text-transform: uppercase;
            border: 1px solid #cbd5e1;
            box-sizing: border-box;
        } 
        .siswa-table-main td { 
            padding: 12px 10px; 
            border: 1px solid #cbd5e1; 
            font-size: 13px; 
            vertical-align: middle; 
            text-align: center;
            word-wrap: break-word;
            overflow-wrap: break-word;
            color: var(--mantap-blue-dark);
            background-color: white !important;
            box-sizing: border-box;
        } 
        .siswa-table-main tr:hover td { background-color: #f8fafc !important; }

        /* Column Specific Alignments & Widths */
        .siswa-table-main th:nth-child(1) { width: 55px; }
        .siswa-table-main th:nth-child(2) { width: 20%; text-align: left; padding-left: 12px; }
        .siswa-table-main th:nth-child(3) { width: 10%; }
        .siswa-table-main th:nth-child(4) { width: 12%; }
        .siswa-table-main th:nth-child(5) { width: 23%; text-align: left; padding-left: 12px; }
        .siswa-table-main th:nth-child(6) { width: 20%; }
        .siswa-table-main th:nth-child(7) { width: 105px; }

        .siswa-table-main td:nth-child(2), .siswa-table-main td:nth-child(5) { text-align: left; }

        /* Typography Inside Cells */
        .student-name-title { font-weight: 700; color: var(--mantap-blue-dark); font-size: 13.5px; text-transform: uppercase; }
        .location-title { font-weight: 700; color: var(--mantap-blue-main); }
        .nomor-terbit-text { font-weight: 700; color: var(--mantap-blue-main); display: block; font-size: 13px; }
        .nomor-belum-text { color: #dc3545; font-weight: 700; display: block; font-size: 12px; text-transform: uppercase; } 

        /* Action Buttons */
        .btn-input-nomor-inline { 
            color: white !important; 
            padding: 6px 0; 
            border-radius: 4px; 
            text-decoration: none; 
            font-weight: 600; 
            font-size: 12px; 
            display: inline-flex;
            align-items: center;
            justify-content: center; 
            gap: 4px;
            width: 85px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.06);
            box-sizing: border-box;
            transition: opacity 0.2s;
        }
        .btn-input-nomor-inline:hover { opacity: 0.9; }
        
        /* Dropdown Navigation Menu */
        .dropdown-menu { display: none; list-style: none; padding: 0; margin-top: 0; background-color: #2c3e50; }
        .dropdown-menu.active { display: block; }
        .dropdown-menu li a { padding: 10px 20px 10px 50px; display: block; color: #bdc3c7; font-size: 14px; border-left: 3px solid transparent; transition: all 0.3s; text-decoration: none; }
        .dropdown-menu li a:hover { background-color: #34495e; border-left-color: #3498db; color: white; }
        .dropdown-toggle { cursor: pointer; display: flex; justify-content: space-between; align-items: center; padding: 12px 20px; color: #bdc3c7; font-weight: 600; text-decoration: none; }
        .dropdown-toggle:hover { background-color: #34495e; color: white; }
        .dropdown-toggle i.fa-caret-down { margin-right: 0; transition: transform 0.3s; }
        .dropdown-toggle.active i.fa-caret-down { transform: rotate(180deg); }
        .sidebar-nav .monitoring-menu a { background-color: var(--mantap-blue-main) !important; color: white !important; font-weight: 600; margin-top: 5px; }

        /* RESPONSIVITAS MOBILE & TABLET KECIL */
        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 
            .admin-main-content h1 { font-size: 1.4rem !important; }
            
            .input-form-box { padding: 18px 15px !important; border-radius: 12px !important; }
            .input-form-box h2 { font-size: 1.25rem !important; }
            .btn-submit { width: 100% !important; justify-content: center !important; border-radius: 8px !important; }

            .glass-panel-table { padding: 16px 10px !important; border-radius: 12px !important; border: 2px solid #cbd5e1 !important; }
            .table-container-fixed { display: block !important; width: 100% !important; overflow-x: auto !important; }

            .siswa-table-main { table-layout: auto !important; min-width: 950px !important; }
            .siswa-table-main th, .siswa-table-main td { padding: 12px 10px !important; font-size: 13px !important; }
            
            .siswa-table-main th:nth-child(2), .siswa-table-main td:nth-child(2),
            .siswa-table-main th:nth-child(5), .siswa-table-main td:nth-child(5) { text-align: center !important; padding-left: 10px !important; }
            .btn-input-nomor-inline { width: 80px !important; }
        }
    </style>
</head>
<body class="admin-body">

<?php 
include 'panel/sidebar.php'; 
?>

<div class="main-content-wrapper">

    <?php include 'panel/navbar.php'; ?>

    <div class="admin-main-content">
        <a href="sertifikat.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali ke Penerbitan Sertifikat</a>
        
        <h1>Input Nomor Sertifikat Siswa</h1>
        
        <?php echo $message; ?>

        <?php if ($siswa_info): ?>
        <div class="input-form-box">
            <h2>Atur Nomor Sertifikat untuk: <?php echo htmlspecialchars($siswa_info['nama']); ?></h2>
            
            <form method="POST" action="sertifikat-nomor-input.php">
                <input type="hidden" name="set_nomor" value="1">
                <input type="hidden" name="siswa_id_target" value="<?php echo $siswa_info['id']; ?>">
                
                <label>NISN / Kelas:</label>
                <p style="font-size: 1.05rem; font-weight: 700; margin: 4px 0 0 0; color: var(--mantap-blue-dark);"><?php echo htmlspecialchars($siswa_info['nisn']); ?> / <?php echo htmlspecialchars($siswa_info['kelas']); ?></p>

                <label for="nomor_sertifikat">Nomor Sertifikat:</label>
                <input type="text" name="nomor_sertifikat" id="nomor_sertifikat" 
                       value="<?php echo htmlspecialchars($siswa_info['nomor_sertifikat'] ?? ''); ?>" 
                       placeholder="Contoh: 421.2/PKL/001/2026" required>
                       
                <label for="tanggal_terbit">Tanggal Terbit:</label>
                <input type="date" name="tanggal_terbit" id="tanggal_terbit" 
                       value="<?php echo htmlspecialchars($siswa_info['tanggal_terbit'] ?? date('Y-m-d')); ?>" required>
                
                <?php if ($siswa_info['nomor_sertifikat']): ?>
                    <p style="color: #dc3545; font-size: 0.85rem; margin-top: 5px; font-weight: 500;">*Nomor sebelumnya akan ditimpa (diupdate).</p>
                <?php endif; ?>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Simpan Nomor Sertifikat
                </button>
            </form>
        </div>
        <?php endif; ?>
        
        <div class="glass-panel-table">
            <h2>Daftar Siswa Peserta PKL</h2>
            
            <div class="table-container-fixed">
                <?php if ($siswa_data && $siswa_data->num_rows > 0): ?>
                <table class="siswa-table-main">
                    <thead>
                        <tr>
                            <th>NO</th>
                            <th>NAMA SISWA</th>
                            <th>KELAS</th>
                            <th>NISN</th>
                            <th>LOKASI PKL</th>
                            <th>NOMOR SERTIFIKAT</th>
                            <th>AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $siswa_no = 1; while($row = $siswa_data->fetch_assoc()): 
                            $is_terbit = !empty($row['nomor_sertifikat']);
                        ?>
                        <tr>
                            <td style="font-weight: 700;"><?php echo $siswa_no++; ?></td>
                            <td>
                                <div class="student-name-title"><?php echo htmlspecialchars($row['nama']); ?></div>
                            </td>
                            <td style="font-weight: 600; color: #475569;"><?php echo htmlspecialchars($row['kelas']); ?></td>
                            <td style="font-weight: 500; color: #64748b;"><?php echo htmlspecialchars($row['nisn']); ?></td>
                            <td>
                                <div class="location-title"><?php echo htmlspecialchars($row['nama_lokasi'] ?? 'Belum Pilih Lokasi'); ?></div>
                            </td>
                            <td>
                                <?php if ($is_terbit): ?>
                                    <span class="nomor-terbit-text"><?php echo htmlspecialchars($row['nomor_sertifikat']); ?></span>
                                    <span style="font-size: 11px; color: var(--mantap-green); display: block; font-weight: 500; margin-top: 2px;"><i class="far fa-calendar-alt"></i> <?php echo formatTanggal($row['tanggal_terbit']); ?></span>
                                <?php else: ?>
                                    <span class="nomor-belum-text">Belum Terbit</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="sertifikat-nomor-input.php?siswa_id=<?php echo $row['id']; ?>" class="btn-input-nomor-inline" style="background-color: <?php echo $is_terbit ? '#ffc107' : 'var(--mantap-green)'; ?>; color: <?php echo $is_terbit ? '#0f172a' : 'white'; ?> !important;">
                                    <i class="fas fa-keyboard"></i> <?php echo $is_terbit ? 'Edit' : 'Input'; ?>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                     <div class="alert error" style="margin: 0; text-align: center;">Tidak ada peserta didik terdaftar.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php 
include 'panel/footer.php'; 
?>
</body>
</html>