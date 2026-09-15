<?php
// admin/persuratan.php
include 'auth-check.php';
include '../config/db-koneksi.php';

$current_user = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin';
$current_user_id = $_SESSION['user_id'] ?? 0; // Kebutuhan Log
$current_user_level = $_SESSION['level'] ?? 'admin'; 

// Pesan dari proses sebelumnya (misal dari form submit)
$message = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'success') {
        $message = "<div class='alert success'>✅ Surat Keluar berhasil dicatat.</div>";
    } elseif ($_GET['status'] == 'input_success') {
        $message = "<div class='alert success'>✅ Surat Masuk berhasil dicatat.</div>";
    } elseif ($_GET['status'] == 'edit_success') {
        $message = "<div class='alert success'>✅ Surat berhasil diperbarui.</div>";
    } elseif ($_GET['status'] == 'delete_success') {
        $message = "<div class='alert warning'>🗑️ Surat berhasil dihapus dari sistem arsip pendaftaran.</div>";
    }
}

// ---------------------------------------------------------------------
// LOGIKA DELETE SURAT KELUAR
// ---------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'delete_keluar' && isset($_GET['id'])) {
    $id_surat = (int)$_GET['id'];
    if ($id_surat > 0) {
        
        // --- AMBIL NOMOR SURAT SEBELUM DIHAPUS UNTUK LOG ---
        $no_surat_del = "ID " . $id_surat;
        $cek_surat = $koneksi->query("SELECT nomor_surat FROM surat_keluar_pkl WHERE surat_id = $id_surat");
        if ($cek_surat && $cek_surat->num_rows > 0) {
            $no_surat_del = $cek_surat->fetch_assoc()['nomor_surat'];
        }

        $delete_stmt = $koneksi->prepare("DELETE FROM surat_keluar_pkl WHERE surat_id = ?");
        $delete_stmt->bind_param("i", $id_surat);
        if ($delete_stmt->execute()) {
            
            // --- TRIGGER LOG AKTIVITAS (DELETE SURAT KELUAR) ---
            catatLog($koneksi, $current_user_id, "Menghapus data arsip Surat Keluar: " . $no_surat_del);

            header("Location: persuratan.php?status=delete_success&tab=keluar");
            exit();
        } else {
            $message = "<div class='alert error'>Gagal menghapus data: " . $delete_stmt->error . "</div>";
        }
        $delete_stmt->close();
    }
}

// ---------------------------------------------------------------------
// LOGIKA DELETE SURAT MASUK
// ---------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'delete_masuk' && isset($_GET['id'])) {
    $id_surat_masuk = (int)$_GET['id'];
    if ($id_surat_masuk > 0) {
        
        // --- AMBIL NOMOR & ASAL SURAT SEBELUM DIHAPUS UNTUK LOG ---
        $info_surat_del = "ID " . $id_surat_masuk;
        $cek_surat_m = $koneksi->query("SELECT nomor_surat_masuk, asal_perusahaan FROM surat_masuk_pkl WHERE masuk_id = $id_surat_masuk");
        if ($cek_surat_m && $cek_surat_m->num_rows > 0) {
            $dt = $cek_surat_m->fetch_assoc();
            $info_surat_del = "dari Instansi " . $dt['asal_perusahaan'] . " (Nomor: " . $dt['nomor_surat_masuk'] . ")";
        }

        $delete_stmt = $koneksi->prepare("DELETE FROM surat_masuk_pkl WHERE masuk_id = ?");
        $delete_stmt->bind_param("i", $id_surat_masuk);
        if ($delete_stmt->execute()) {
            
            // --- TRIGGER LOG AKTIVITAS (DELETE SURAT MASUK) ---
            catatLog($koneksi, $current_user_id, "Menghapus data arsip Surat Masuk " . $info_surat_del);

            header("Location: persuratan.php?status=delete_success&tab=masuk");
            exit();
        } else {
            $message = "<div class='alert error'>Gagal menghapus data: " . $delete_stmt->error . "</div>";
        }
        $delete_stmt->close();
    }
}

$upload_base_url = '../uploads/surat/'; // Base URL untuk dokumen

// ---------------------------------------------------------------------
// QUERY DATA
// ---------------------------------------------------------------------

// Query Surat Keluar
$query_keluar = "
    SELECT 
        surat_id, nomor_surat, tanggal_surat, tujuan_perusahaan, perihal, file_path
    FROM surat_keluar_pkl
    ORDER BY tanggal_surat DESC
";
$surat_keluar_data = $koneksi->query($query_keluar);

// Query Surat Masuk
$query_masuk = "
    SELECT 
        masuk_id, nomor_surat_masuk, tanggal_terima, asal_perusahaan, perihal, file_path
    FROM surat_masuk_pkl
    ORDER BY tanggal_terima DESC
";
$surat_masuk_data = $koneksi->query($query_masuk);

// Fungsi untuk format tanggal
function formatTanggal($tanggal) {
    if (empty($tanggal) || $tanggal == '0000-00-00') return '-';
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

// Menentukan tab yang aktif berdasarkan URL (Jika habis hapus Surat Masuk, akan kembali ke tab Surat Masuk)
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'keluar';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin - Persuratan PKL | Si Mantap PKL</title>
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

        /* SEARCH BAR STYLE */
        .search-wrapper { position: relative; display: flex; align-items: center; }
        .search-wrapper i { position: absolute; left: 15px; color: #64748b; font-size: 14px; }
        .search-input { 
            padding: 9px 15px 9px 38px; border: 1px solid #cbd5e1; border-radius: 20px; 
            font-family: 'Poppins', sans-serif; font-size: 13px; width: 280px; transition: all 0.3s ease; 
            outline: none; background: #f8fafc;
        }
        .search-input:focus { border-color: var(--mantap-blue-main); width: 320px; background: white; box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.15); }

        .header-actions-right {
            display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
        }

        .btn-add {
            background-color: #22c55e;
            color: white !important;
            padding: 9px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.15);
            font-family: 'Poppins', sans-serif;
            border: none;
            cursor: pointer;
        }
        .btn-add:hover { background-color: #16a34a; }
        .btn-add.secondary { background-color: #0ea5e9; box-shadow: 0 4px 6px -1px rgba(14, 165, 233, 0.15); }
        .btn-add.secondary:hover { background-color: #0284c7; }
        
        .alert { padding: 12px 15px; margin-bottom: 20px; border-radius: 8px; border: 1px solid; font-size: 14px; font-weight: 500; text-align: left; }
        .alert.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb;}
        .alert.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb;}
        .alert.warning { background-color: #fff3cd; color: #856404; border-color: #ffeeba; }

        /* TAB NAVIGATION STYLING */
        .tab-container {
            display: flex;
            gap: 10px;
            margin-bottom: -2px; /* Pull down to merge with border */
            position: relative;
            z-index: 2;
        }
        .tab-btn {
            background-color: #e2e8f0;
            color: #64748b;
            border: 2px solid #e2e8f0;
            border-bottom: none;
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 700;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            border-radius: 12px 12px 0 0;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .tab-btn:hover {
            background-color: #cbd5e1;
            color: #334155;
        }
        .tab-btn.active {
            background-color: white;
            color: var(--mantap-blue-main);
            border-color: #e2e8f0;
            border-bottom: 2px solid white; /* Hide the bottom border of the panel */
        }
        .tab-content {
            display: none;
            background: white !important; 
            padding: 25px !important; 
            border-radius: 0 16px 16px 16px !important; 
            border: 2px solid #e2e8f0 !important; 
            margin-bottom: 35px;
            box-sizing: border-box; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); 
            width: 100%;
        }
        .tab-content.active {
            display: block;
        }

        .table-container-fixed { width: 100%; overflow-x: auto; box-sizing: border-box; border: none !important; }

        /* OUTLINE KOTAK TEBAL BERWARNA BIRU UTAMA */
        .custom-table-core {
            width: 100%; table-layout: fixed; border-collapse: collapse; background: white; border-radius: 4px; overflow: hidden; border: 2px solid #1e40af; 
        }
        .custom-table-core th {
            background: #1e40af; color: white; padding: 14px 6px; font-size: 13px; text-transform: uppercase; font-weight: 700; text-align: center; border: 1px solid #cbd5e1; box-sizing: border-box;
        }

        /* DATA INTEGRASI COLUMNS WIDTH DESKTOP */
        .custom-table-core th.col-no { width: 60px; }
        .custom-table-core th.col-no-surat { width: 22%; text-align: left; padding-left: 12px; }
        .custom-table-core th.col-tgl { width: 15%; }
        .custom-table-core th.col-instansi { width: 23%; text-align: left; padding-left: 12px; }
        .custom-table-core th.col-perihal { width: 25%; text-align: left; padding-left: 12px; }
        .custom-table-core th.col-aksi { width: 195px; }

        .custom-table-core td {
            padding: 12px 10px; font-size: 13px; vertical-align: middle; word-wrap: break-word; overflow-wrap: break-word; text-align: center; border: 1px solid #cbd5e1; color: #0f172a; background-color: white !important; box-sizing: border-box;
        }
        .custom-table-core tbody tr:hover td { background-color: #f8fafc !important; }
        
        /* INLINE BUTTONS GROUPS */
        .dashboard-btn-group { display: flex; gap: 4px; justify-content: center; flex-wrap: wrap; }
        
        .file-link, .btn-edit-inline, .btn-preview-surat, .btn-delete-inline {
            padding: 5px 10px; border-radius: 4px; text-decoration: none; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; border: none; cursor: pointer; font-family: 'Poppins', sans-serif;
        }
        .file-link { background-color: #0ea5e9; color: white !important; box-shadow: 0 2px 4px rgba(14,165,233,0.15); }
        .file-link:hover { background-color: #0284c7; }
        .btn-edit-inline { background-color: #f59e0b; color: white !important; box-shadow: 0 2px 4px rgba(245,158,11,0.15); }
        .btn-edit-inline:hover { background-color: #d97706; }
        .btn-preview-surat { background-color: #8b5cf6; color: white !important; box-shadow: 0 2px 4px rgba(139,92,246,0.15); }
        .btn-preview-surat:hover { background-color: #7c3aed; }
        .btn-delete-inline { background-color: #dc3545; color: white !important; box-shadow: 0 2px 4px rgba(220,53,69,0.15); }
        .btn-delete-inline:hover { background-color: #ef4444; }

        .sidebar-nav ul, .sidebar-nav li, .dropdown-menu, .dropdown-menu li { list-style: none !important; margin: 0; padding: 0; }

        /* =========================================================================
           RESPONSIVE VIEWPORT SMARTPHONE (HP SCROLLABLE TABLE MODE)
        ========================================================================= */
        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 

            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; padding: 5px 0px !important; }
            .page-header-controls h1 { font-size: 1.4rem !important; }
            
            .search-wrapper { width: 100%; margin-bottom: 10px; }
            .search-input { width: 100%; box-sizing: border-box; }
            .search-input:focus { width: 100%; }

            .header-actions-right { width: 100% !important; flex-direction: column !important; gap: 8px !important; }
            .btn-add { width: 100% !important; justify-content: center !important; padding: 11px !important; border-radius: 8px !important; font-size: 13.5px !important; }

            .tab-btn { padding: 10px 15px; font-size: 12px; flex: 1; justify-content: center; }
            .tab-content { padding: 16px 10px !important; border-radius: 0 0 12px 12px !important; }

            .table-container-fixed { display: block !important; width: 100% !important; overflow-x: auto !important; }
            .custom-table-core { table-layout: auto !important; min-width: 860px !important; }
            .custom-table-core th, .custom-table-core td { padding: 12px 10px !important; font-size: 13px !important; }
            .custom-table-core th.col-no, .custom-table-core th.col-no-surat, .custom-table-core th.col-tgl, .custom-table-core th.col-instansi, .custom-table-core th.col-perihal, .custom-table-core th.col-aksi { width: auto !important; }
            
            .custom-table-core td:nth-child(2), .custom-table-core td:nth-child(4), .custom-table-core td:nth-child(5) { text-align: left !important; padding-left: 10px !important; }
            .file-link, .btn-edit-inline, .btn-preview-surat, .btn-delete-inline { padding: 6px 10px !important; font-size: 11.5px !important; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; ?>

<div class="main-content-wrapper">

    <?php include 'panel/navbar.php'; ?>

    <div class="admin-main-content"> 
        
        <div class="page-header-controls">
            <h1>Administrasi Persuratan PKL</h1>
            
            <div class="header-actions-right">
                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchSurat" class="search-input" placeholder="Cari surat, instansi, atau perihal..." onkeyup="filterSurat()">
                </div>
                <a href="surat-masuk-add.php" class="btn-add secondary">
                    <i class="fas fa-inbox"></i> Catat Surat Masuk
                </a>
                <a href="surat-keluar-add.php" class="btn-add">
                    <i class="fas fa-share-square"></i> Tambah Surat Keluar
                </a>
            </div>
        </div>

        <?php echo $message; ?>
        
        <div class="tab-container">
            <button class="tab-btn <?php echo ($active_tab == 'keluar') ? 'active' : ''; ?>" onclick="openTab(event, 'tabKeluar')">
                <i class="fas fa-paper-plane"></i> Arsip Surat Keluar
            </button>
            <button class="tab-btn <?php echo ($active_tab == 'masuk') ? 'active' : ''; ?>" onclick="openTab(event, 'tabMasuk')">
                <i class="fas fa-inbox"></i> Arsip Surat Masuk
            </button>
        </div>

        <div id="tabKeluar" class="tab-content <?php echo ($active_tab == 'keluar') ? 'active' : ''; ?>">
            <div class="table-container-fixed">
                <table class="custom-table-core searchable-table">
                    <thead>
                        <tr>
                            <th class="col-no">NO</th>
                            <th class="col-no-surat" style="text-align: left; padding-left: 12px;">Nomor Agenda Surat</th>
                            <th class="col-tgl">Tanggal Surat</th>
                            <th class="col-instansi" style="text-align: left; padding-left: 12px;">Tujuan Mitra Perusahaan</th>
                            <th class="col-perihal" style="text-align: left; padding-left: 12px;">Perihal / Isi Ringkas</th>
                            <th class="col-aksi">Aksi & Dokumen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; if ($surat_keluar_data && $surat_keluar_data->num_rows > 0): ?>
                            <?php while ($row = $surat_keluar_data->fetch_assoc()): ?>
                            <tr class="data-row">
                                <td style="font-weight: 700; color: #64748b; text-align: center;"><?php echo $no++; ?></td>
                                <td style="text-align: left; padding-left: 12px; font-weight: 600; color: var(--mantap-blue-dark);"><?php echo htmlspecialchars($row['nomor_surat']); ?></td>
                                <td style="font-weight: 500; color: #475569;"><?php echo formatTanggal($row['tanggal_surat']); ?></td>
                                <td style="text-align: left; padding-left: 12px; font-weight: 600; text-transform: uppercase;"><?php echo htmlspecialchars($row['tujuan_perusahaan']); ?></td>
                                <td style="text-align: left; padding-left: 12px; font-style: italic; color: #475569;"><?php echo htmlspecialchars($row['perihal']); ?></td>
                                <td>
                                    <div class="dashboard-btn-group">
                                        <a href="surat-keluar-otomatis.php?id=<?php echo $row['surat_id']; ?>" class="btn-preview-surat" title="Lihat Lembar Preview Cetak Surat">
                                            <i class="fas fa-file-pdf"></i> Cetak
                                        </a>
                                        <a href="surat-keluar-edit.php?id=<?php echo $row['surat_id']; ?>" class="btn-edit-inline">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <button type="button" class="btn-delete-inline" onclick="konfirmasiHapusKeluar('<?php echo $row['surat_id']; ?>')">
                                            <i class="fas fa-trash"></i> Hapus
                                        </button>
                                        <?php if ($row['file_path']): ?>
                                            <a href="<?php echo $upload_base_url . urlencode($row['file_path']); ?>" target="_blank" class="file-link">
                                                <i class="fas fa-eye"></i> BerkasScan
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr class="no-data"><td colspan="6" style="text-align: center; color: #ef4444; font-style: italic; padding: 25px;">Belum ada arsip berkas rekaman surat keluar pendaftaran yang terekam.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="tabMasuk" class="tab-content <?php echo ($active_tab == 'masuk') ? 'active' : ''; ?>">
            <div class="table-container-fixed">
                <table class="custom-table-core searchable-table">
                    <thead>
                        <tr>
                            <th class="col-no">NO</th>
                            <th class="col-no-surat" style="text-align: left; padding-left: 12px;">Nomor Surat Asal</th>
                            <th class="col-tgl">Tanggal Terima</th>
                            <th class="col-instansi" style="text-align: left; padding-left: 12px;">Asal Instansi Mitra Perusahaan</th>
                            <th class="col-perihal" style="text-align: left; padding-left: 12px;">Perihal / Isi Ringkas</th>
                            <th class="col-aksi">Aksi & Dokumen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; if ($surat_masuk_data && $surat_masuk_data->num_rows > 0): ?>
                            <?php while ($row = $surat_masuk_data->fetch_assoc()): ?>
                            <tr class="data-row">
                                <td style="font-weight: 700; color: #64748b; text-align: center;"><?php echo $no++; ?></td>
                                <td style="text-align: left; padding-left: 12px; font-weight: 600; color: var(--mantap-blue-dark);"><?php echo htmlspecialchars($row['nomor_surat_masuk']); ?></td>
                                <td style="font-weight: 500; color: #475569;"><?php echo formatTanggal($row['tanggal_terima']); ?></td>
                                <td style="text-align: left; padding-left: 12px; font-weight: 600; text-transform: uppercase;"><?php echo htmlspecialchars($row['asal_perusahaan']); ?></td>
                                <td style="text-align: left; padding-left: 12px; font-style: italic; color: #475569;"><?php echo htmlspecialchars($row['perihal']); ?></td>
                                <td>
                                    <div class="dashboard-btn-group">
                                        <a href="surat-masuk-edit.php?id=<?php echo $row['masuk_id']; ?>" class="btn-edit-inline">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <button type="button" class="btn-delete-inline" onclick="konfirmasiHapusMasuk('<?php echo $row['masuk_id']; ?>')">
                                            <i class="fas fa-trash"></i> Hapus
                                        </button>
                                        <?php if ($row['file_path']): ?>
                                            <a href="<?php echo $upload_base_url . urlencode($row['file_path']); ?>" target="_blank" class="file-link">
                                                <i class="fas fa-eye"></i> Lihat Scan
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr class="no-data"><td colspan="6" style="text-align: center; color: #ef4444; font-style: italic; padding: 25px;">Belum ada arsip berkas lembar jawaban konfirmasi surat masuk yang dicatat.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
    // FUNGSI PENCARIAN REAL-TIME UNTUK KEDUA TABEL
    function filterSurat() {
        let input = document.getElementById("searchSurat");
        let filter = input.value.toUpperCase();
        
        // Ambil semua baris data dari semua tabel yang memiliki class 'searchable-table'
        let rows = document.querySelectorAll(".searchable-table tbody tr.data-row");

        rows.forEach(row => {
            // Gabungkan teks dari kolom Nomor Surat (idx 1), Instansi (idx 3), dan Perihal (idx 4)
            let noSurat = row.cells[1] ? row.cells[1].textContent : "";
            let instansi = row.cells[3] ? row.cells[3].textContent : "";
            let perihal = row.cells[4] ? row.cells[4].textContent : "";
            let combinedText = (noSurat + " " + instansi + " " + perihal).toUpperCase();

            if (combinedText.indexOf(filter) > -1) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        });
    }

    // FUNGSI UNTUK GANTI TAB
    function openTab(evt, tabName) {
        var i, tabcontent, tablinks;
        
        // Hide all tab content
        tabcontent = document.getElementsByClassName("tab-content");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
            tabcontent[i].classList.remove("active");
        }
        
        // Remove active class from all buttons
        tablinks = document.getElementsByClassName("tab-btn");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].classList.remove("active");
        }
        
        // Show current tab and add active class to button
        document.getElementById(tabName).style.display = "block";
        document.getElementById(tabName).classList.add("active");
        evt.currentTarget.classList.add("active");
        
        // Update URL hash for tracking
        if(history.pushState) {
            history.pushState(null, null, '?tab=' + (tabName === 'tabKeluar' ? 'keluar' : 'masuk'));
        }
    }

    // Fungsi SweetAlert2 Terstandarisasi untuk Validasi Penghapusan Surat Keluar
    function konfirmasiHapusKeluar(id) {
        Swal.fire({
            title: 'Hapus Surat Keluar?',
            text: "PERINGATAN! Menghapus data ini akan menyebabkan nomor urut penomoran agenda bulan ini lowong/bolong.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `persuratan.php?action=delete_keluar&id=${id}`;
            }
        });
    }

    // Fungsi SweetAlert2 Terstandarisasi untuk Validasi Penghapusan Surat Masuk
    function konfirmasiHapusMasuk(id) {
        Swal.fire({
            title: 'Hapus Surat Masuk?',
            text: "Arsip surat masuk ini akan dihapus secara permanen dari sistem.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `persuratan.php?action=delete_masuk&id=${id}`;
            }
        });
    }
</script>

<?php include 'panel/footer.php'; ?>
</body>
</html>