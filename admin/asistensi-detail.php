<?php
// admin/asistensi-detail.php
// Halaman untuk menambah dan melihat riwayat asistensi per siswa

include 'auth-check.php';
include '../config/db-koneksi.php';

$current_user = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin';
$siswa_id = isset($_GET['siswa_id']) ? (int)$_GET['siswa_id'] : 0;
$max_asistensi = 3;
$success_message = '';
$error_message = '';

if ($siswa_id === 0) {
    die("ID Siswa tidak ditemukan.");
}

// ---------------------------------------------------------------------
// A. PROSES SIMPAN DATA ASISTENSI BARU (JIKA ADA POST REQUEST)
// ---------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['tambah_asistensi'])) {
    $tanggal_asistensi = trim($_POST['tanggal_asistensi']);
    $catatan = trim($_POST['catatan']);
    $status = trim($_POST['status']);
    
    // Validasi sederhana
    if (empty($tanggal_asistensi) || empty($catatan) || empty($status)) {
        $error_message = "Semua field wajib diisi.";
    } 
    
    // Cek apakah kuota 5 kali sudah terpenuhi
    $count_query = $koneksi->query("SELECT COUNT(asistensi_id) FROM asistensi_pkl WHERE siswa_id = {$siswa_id}");
    $count = $count_query->fetch_array()[0];

    if ($count >= $max_asistensi) {
        $error_message = "Siswa ini telah mencapai batas maksimum {$max_asistensi} kali asistensi.";
    }

    if (!$error_message) {
        $stmt = $koneksi->prepare("INSERT INTO asistensi_pkl (siswa_id, tanggal_asistensi, catatan, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $siswa_id, $tanggal_asistensi, $catatan, $status);
        
        if ($stmt->execute()) {
            // Setelah sukses, kita redirect ke halaman yang sama untuk menghindari resubmit form
            header("Location: asistensi-detail.php?siswa_id={$siswa_id}&success=1");
            exit();
        } else {
            $error_message = "Gagal menambahkan asistensi: " . $koneksi->error;
        }
        $stmt->close();
    }
}

// Cek parameter success setelah redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = "Asistensi baru berhasil ditambahkan.";
}


// ---------------------------------------------------------------------
// B. QUERY DATA SISWA & RIWAYAT ASISTENSI
// ---------------------------------------------------------------------

// 1. Ambil Data Siswa (Nama, Kelas, Lokasi, Guru, dan LOKASI_ID)
$siswa_query = $koneksi->query("
    SELECT 
        p.id, p.nama AS nama_siswa, p.kelas, p.nisn, p.lokasi_id,
        l.nama_lokasi,
        g.nama_guru
    FROM peserta_didik p
    LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
    LEFT JOIN guru g ON l.guru_id = g.guru_id
    WHERE p.id = {$siswa_id}
");

$siswa_info = $siswa_query->fetch_assoc();

if (!$siswa_info) {
    die("Data siswa tidak ditemukan.");
}

$lokasi_id_target = $siswa_info['lokasi_id'];
$nama_lokasi_target = $siswa_info['nama_lokasi'] ?? 'N/A';

// 2. Ambil Riwayat Asistensi (maksimal 5)
$riwayat_query = $koneksi->query("
    SELECT 
        asistensi_id, tanggal_asistensi, catatan, status
    FROM asistensi_pkl
    WHERE siswa_id = {$siswa_id}
    ORDER BY tanggal_asistensi DESC
    LIMIT {$max_asistensi}
");

$riwayat_asistensi = [];
if ($riwayat_query) {
    while ($row = $riwayat_query->fetch_assoc()) {
        $riwayat_asistensi[] = $row;
    }
}
$asistensi_count = count($riwayat_asistensi);


// 3. Ambil Siswa Lain di Lokasi yang Sama
$siswa_lokasi_sama = [];
if (!empty($lokasi_id_target)) {
    $siswa_sama_query = $koneksi->query("
        SELECT 
            id, nama, kelas
        FROM peserta_didik
        WHERE lokasi_id = {$lokasi_id_target}
        ORDER BY nama ASC
    ");

    if ($siswa_sama_query) {
        while ($row = $siswa_sama_query->fetch_assoc()) {
            $siswa_lokasi_sama[] = $row;
        }
    }
}

// Fungsi untuk format tanggal
function formatTanggal($tanggal) {
    if (empty($tanggal)) return '-';
    $timestamp = strtotime($tanggal);
    $bulan_indo = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $tgl = date('d', $timestamp);
    $bln = $bulan_indo[(int)date('m', $timestamp)];
    $thn = date('Y', $timestamp);
    
    return "{$tgl} {$bln} {$thn}"; 
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Detail Asistensi PKL - <?php echo htmlspecialchars($siswa_info['nama_siswa']); ?> | Si Mantap PKL</title>
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

        /* STRUKTUR DUA KOLOM FLEXBOX DESKTOP */
        .detail-layout-container {
            display: flex;
            gap: 25px;
            width: 100%;
            align-items: flex-start;
            box-sizing: border-box;
        }

        .main-content-detail {
            flex: 3; 
            box-sizing: border-box;
        }

        .sidebar-detail {
            flex: 1; 
            background-color: white;
            padding: 22px;
            border-radius: 16px;
            height: fit-content;
            border: 2px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
            box-sizing: border-box;
        }

        .sidebar-detail h3 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--mantap-blue-main);
            font-size: 13.5px;
            font-weight: 700;
            color: var(--mantap-blue-dark);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .sidebar-detail ul { list-style: none; padding: 0; margin: 0; }
        .sidebar-detail li a {
            display: block;
            padding: 10px 0;
            color: #475569;
            font-size: 13px;
            text-decoration: none;
            border-bottom: 1px dashed #e2e8f0;
            transition: 0.2s;
        }
        .sidebar-detail li a:hover { color: var(--mantap-blue-light); padding-left: 4px; }
        .sidebar-detail li a.active-siswa { font-weight: 700; color: #dc3545; }

        /* CARDS IDENTITAS SISWA */
        .info-card {
            background-color: var(--mantap-blue-soft);
            border-left: 5px solid var(--mantap-blue-main);
            padding: 18px;
            margin-bottom: 25px;
            border-radius: 8px;
        }
        .info-card p { margin: 6px 0; font-size: 13.5px; color: #334155; }
        .info-card strong { color: var(--mantap-blue-dark); font-weight: 700; }

        /* FORMS & PANELS STYLING */
        .form-asistensi, .riwayat-asistensi {
            background: white;
            padding: 25px;
            margin-bottom: 25px;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
            border: 2px solid #e2e8f0;
            box-sizing: border-box;
        }

        .form-asistensi h2, .riwayat-asistensi h2 {
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 12px;
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--mantap-blue-dark);
        }

        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13.5px; color: #0f172a; }
        .form-group input[type="date"], .form-group textarea, .form-group select {
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
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: #1e40af; background-color: white; }
        
        /* ACTION BUTTONS */
        .btn-submit {
            background-color: #22c55e;
            color: white;
            padding: 10px 24px;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            font-family: 'Poppins', sans-serif;
            box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.2);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-submit:hover { background-color: #16a34a; }

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

        .alert-success, .alert-error { padding: 12px 15px; margin-bottom: 20px; border-radius: 8px; border: 1px solid; font-size: 14px; font-weight: 500; }
        .alert-success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }

        /* RIWAYAT TABEL OUTLINE TEBAL */
        .table-container-fixed { width: 100%; overflow-x: auto; box-sizing: border-box; border: none !important; }
        .riwayat-asistensi table {
            width: 100%; table-layout: fixed; border-collapse: collapse; background: white; border-radius: 4px; overflow: hidden; border: 2px solid #1e40af; 
        }
        .riwayat-asistensi table th { 
            background: #1e40af; color: white; padding: 14px 6px; font-size: 13px; text-transform: uppercase; font-weight: 700; text-align: center; border: 1px solid #cbd5e1; box-sizing: border-box;
        }
        .riwayat-asistensi table td {
            padding: 12px 10px; font-size: 13px; vertical-align: middle; word-wrap: break-word; overflow-wrap: break-word; text-align: center; border: 1px solid #cbd5e1; color: #0f172a; background-color: white !important; box-sizing: border-box;
        }
        .riwayat-asistensi tr:hover td { background-color: #f8fafc !important; }

        /* BADGES STATUS */
        .status-badge { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 700; color: white; display: inline-block; white-space: nowrap; }
        .status-badge.Selesai { background-color: #22c55e; }
        .status-badge.Revisi { background-color: #f59e0b; }
        .status-badge.Disetujui { background-color: #0ea5e9; }
        .status-badge.Ditolak { background-color: #ef4444; }

        .sidebar-nav ul, .sidebar-nav li, .dropdown-menu, .dropdown-menu li { list-style: none !important; margin: 0; padding: 0; }

        /* =========================================================================
           RESPONSIVE HANDPHONE VIEW (MOBILE SMARTPHONE)
        ========================================================================= */
        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 

            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; padding: 5px 0px !important; }
            .page-header-controls h1 { font-size: 1.4rem !important; }
            
            /* Konversi dua kolom menjadi bertumpuk vertikal di HP */
            .detail-layout-container { flex-direction: column !important; gap: 20px !important; }
            .main-content-detail, .sidebar-detail { width: 100% !important; flex: none !important; }

            .form-asistensi, .riwayat-asistensi { padding: 18px 14px !important; border-radius: 12px !important; }
            .form-group input, .form-group textarea, .form-group select { font-size: 13.5px !important; padding: 10px !important; }
            
            .form-asistensi button.btn-submit { width: 100% !important; justify-content: center !important; padding: 12px !important; border-radius: 8px !important; font-size: 14px !important; }

            .riwayat-asistensi table { table-layout: auto !important; min-width: 680px !important; }
            .riwayat-asistensi table th, .riwayat-asistensi table td { padding: 12px 10px !important; }
            .riwayat-asistensi table td:nth-child(3) { text-align: left !important; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; ?>

<div class="main-content-wrapper">

    <?php include 'panel/navbar.php'; ?>

    <div class="admin-main-content"> 
        <div class="page-header-controls">
            <h1>Lembar Asistensi PKL</h1>
            <a href="asistensi.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali ke Rekap</a>
        </div>
        
        <div class="detail-layout-container">
            
            <div class="main-content-detail">
                
                <div class="info-card">
                    <p><strong>Siswa:</strong> <?php echo htmlspecialchars($siswa_info['nama_siswa']); ?> (NISN: <?php echo htmlspecialchars($siswa_info['nisn']); ?>)</p>
                    <p><strong>Kelas:</strong> <?php echo htmlspecialchars($siswa_info['kelas']); ?></p>
                    <p><strong>Lokasi Tempat PKL:</strong> <?php echo htmlspecialchars($siswa_info['nama_lokasi'] ?? 'N/A'); ?></p>
                    <p><strong>Guru Pembimbing:</strong> <?php echo htmlspecialchars($siswa_info['nama_guru'] ?? 'Belum Ditentukan'); ?></p>
                </div>
                
                <?php if ($success_message): ?>
                    <div class="alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert-error"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <div class="form-asistensi">
                    <h2>Tambah Asistensi Baru (Sudah: <?php echo $asistensi_count; ?>/<?php echo $max_asistensi; ?>)</h2>
                    
                    <?php if ($asistensi_count < $max_asistensi): ?>
                        <form method="POST" action="asistensi-detail.php?siswa_id=<?php echo $siswa_id; ?>">
                            <input type="hidden" name="tambah_asistensi" value="1">
                            
                            <div class="form-group">
                                <label for="tanggal_asistensi">Tanggal Asistensi:</label>
                                <input type="date" id="tanggal_asistensi" name="tanggal_asistensi" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="catatan">Uraian / Catatan Asistensi:</label>
                                <textarea id="catatan" name="catatan" rows="4" placeholder="Masukkan poin-poin yang dibahas atau revisi yang diminta pembimbing." required></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="status">Status Kemajuan Laporan:</label>
                                <select id="status" name="status" required>
                                    <option value="">-- Pilih Status --</option>
                                    <option value="Revisi">Revisi</option>
                                    <option value="Selesai">Selesai</option>
                                    <option value="Disetujui">Disetujui</option>
                                    <option value="Ditolak">Ditolak</option>
                                </select>
                            </div>

                            <button type="submit" class="btn-submit"><i class="fas fa-plus-circle"></i> Simpan Record Asistensi</button>
                        </form>
                    <?php else: ?>
                        <div class="alert-error" style="background-color: #fef2f2; color: #dc2626; border-color: #fecaca; margin-bottom: 0;">
                            <i class="fas fa-exclamation-triangle"></i> <strong>Kuota Penuh:</strong> Siswa ini telah menyelesaikan batas maksimum pengerjaan <?php echo $max_asistensi; ?> kali asistensi laporan.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="riwayat-asistensi">
                    <h2>Riwayat Lembar Kendali Asistensi</h2>
                    <div class="table-container-fixed">
                        <?php if ($asistensi_count > 0): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width: 60px;">NO</th>
                                        <th style="width: 160px;">TANGGAL BERSANGKUTAN</th>
                                        <th style="text-align: left; padding-left: 15px;">URAIAN & CATATAN MATERI GURU</th>
                                        <th style="width: 140px;">STATUS HASIL</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $riwayat_num = 1; foreach (array_reverse($riwayat_asistensi) as $riwayat): ?>
                                    <tr>
                                        <td style="font-weight: 700;"><?php echo $riwayat_num++; ?></td>
                                        <td style="font-weight: 500; color: #475569;"><?php echo formatTanggal($riwayat['tanggal_asistensi']); ?></td>
                                        <td style="text-align: left; padding-left: 15px; line-height: 1.5;"><?php echo nl2br(htmlspecialchars($riwayat['catatan'])); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo htmlspecialchars($riwayat['status']); ?>">
                                                <?php echo htmlspecialchars($riwayat['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p style="text-align: center; color: #64748b; font-style: italic; margin: 10px 0;">Belum ada riwayat asistensi pengerjaan laporan yang tercatat untuk siswa ini.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($siswa_lokasi_sama)): ?>
            <div class="sidebar-detail">
                <h3>Rekan Satu Instansi</h3>
                <ul>
                    <?php foreach ($siswa_lokasi_sama as $siswa_samar): 
                        $isActive = $siswa_samar['id'] == $siswa_id;
                    ?>
                    <li>
                        <a href="asistensi-detail.php?siswa_id=<?php echo $siswa_samar['id']; ?>" class="<?php echo $isActive ? 'active-siswa' : ''; ?>">
                            <i class="fas fa-user-circle" style="color: <?php echo $isActive ? '#dc3545' : '#94a3b8'; ?>; margin-right: 4px;"></i>
                            <?php echo htmlspecialchars($siswa_samar['nama']); ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include 'panel/footer.php'; ?>
</body>
</html>