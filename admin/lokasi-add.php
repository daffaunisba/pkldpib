<?php
// admin/lokasi-add.php
include 'auth-check.php';
include '../config/db-koneksi.php';

// --- PROTEKSI KEAMANAN: Hanya Admin yang boleh mengakses halaman ini ---
$user_level = isset($_SESSION['level']) ? strtolower($_SESSION['level']) : '';
if ($user_level !== 'admin') {
    header("Location: dashboard.php?status=restricted");
    exit();
}

$current_user = isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin';
$current_user_id = $_SESSION['user_id'] ?? 0; // Ditambahkan untuk trigger log
$message = '';

// ====================================================================================
// AUTO-CREATE KOLOM HARI MULAI & SELESAI DI DATABASE (ANTI-ERROR)
// ====================================================================================
$check_col = $koneksi->query("SHOW COLUMNS FROM lokasi_pkl LIKE 'hari_mulai'");
if ($check_col && $check_col->num_rows == 0) {
    // Tambah 2 kolom baru
    $koneksi->query("ALTER TABLE lokasi_pkl ADD COLUMN hari_mulai VARCHAR(20) NULL DEFAULT 'Senin' AFTER nama_lokasi");
    $koneksi->query("ALTER TABLE lokasi_pkl ADD COLUMN hari_selesai VARCHAR(20) NULL DEFAULT 'Jumat' AFTER hari_mulai");
    
    // Hapus kolom gabungan yang lama jika ada
    $check_lama = $koneksi->query("SHOW COLUMNS FROM lokasi_pkl LIKE 'hari_kerja'");
    if($check_lama && $check_lama->num_rows > 0){
        $koneksi->query("ALTER TABLE lokasi_pkl DROP COLUMN hari_kerja");
    }
}

// --- FUNGSI UTAMA: MENGUBAH CSV JADI DATA ---
// Ditambahkan parameter $current_user_id untuk pencatatan log per baris (opsional)
function processCsvUpload($koneksi, $file_tmp_path, $current_user_id)
{
    $inserted_count = 0;
    $failed_count = 0;
    $errors = [];

    if (($handle = fopen($file_tmp_path, "r")) !== FALSE) {
        fgetcsv($handle, 1000, ";"); // Skip Header
        $insert_stmt = $koneksi->prepare("INSERT INTO lokasi_pkl (nama_lokasi, hari_mulai, hari_selesai, jam_kerja, alamat, kuota_max, latitude, longitude, radius) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
            // Bersihkan elemen kosong
            $data = array_map('trim', $data);

            // Kolom CSV sekarang butuh 9 data
            if (count($data) < 9) {
                if (trim(implode('', $data)) !== '') {
                    $failed_count++;
                    $errors[] = "Baris gagal: Data kurang dari 9 kolom.";
                }
                continue;
            }

            $nama_lokasi = $data[0];
            $hari_mulai = $data[1];
            $hari_selesai = $data[2];
            $jam_kerja = $data[3];
            $alamat = $data[4];
            $kuota_max = (int)$data[5];
            $latitude = $data[6];
            $longitude = $data[7];
            $radius = $data[8];

            if (empty($nama_lokasi) || empty($alamat) || $kuota_max < 1 || empty($latitude) || empty($longitude)) {
                $failed_count++;
                $errors[] = "Baris gagal (Nama: {$nama_lokasi}): Data tidak lengkap.";
                continue;
            }

            $insert_stmt->bind_param("sssssisss", $nama_lokasi, $hari_mulai, $hari_selesai, $jam_kerja, $alamat, $kuota_max, $latitude, $longitude, $radius);

            if ($insert_stmt->execute()) {
                $inserted_count++;
            } else {
                $failed_count++;
                $errors[] = "Baris gagal (Nama: {$nama_lokasi}): " . $insert_stmt->error;
            }
        }
        $insert_stmt->close();
        fclose($handle);
    }
    return ['inserted' => $inserted_count, 'failed' => $failed_count, 'errors' => $errors];
}

// --- HANDLE POST 1: ADD MANUAL ---
if (isset($_POST['add_manual'])) {
    $nama_lokasi = trim($_POST['nama_lokasi']);
    $hari_mulai = trim($_POST['hari_mulai']);
    $hari_selesai = trim($_POST['hari_selesai']);
    $alamat = trim($_POST['alamat']);
    $kuota_max = (int)$_POST['kuota_max'];
    $latitude = trim($_POST['latitude']);
    $longitude = trim($_POST['longitude']);
    $radius = trim($_POST['radius']);
    $jam_masuk = trim($_POST['jam_masuk']);
    $jam_pulang = trim($_POST['jam_pulang']);
    
    if (empty($nama_lokasi) || empty($hari_mulai) || empty($hari_selesai) || empty($jam_masuk) || empty($jam_pulang) || empty($alamat) || $kuota_max < 1 || empty($latitude) || empty($longitude) || empty($radius)) {
        $message = "<div class='alert error'><i class='fas fa-exclamation-circle me-2'></i> Semua kolom harus diisi dengan lengkap.</div>";
    } else {
        $jam_kerja_gabung = str_replace(':', '.', $jam_masuk) . " s.d. " . str_replace(':', '.', $jam_pulang) . " WIB";

        $insert_stmt = $koneksi->prepare("INSERT INTO lokasi_pkl (nama_lokasi, hari_mulai, hari_selesai, jam_kerja, alamat, kuota_max, latitude, longitude, radius) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("sssssisss", $nama_lokasi, $hari_mulai, $hari_selesai, $jam_kerja_gabung, $alamat, $kuota_max, $latitude, $longitude, $radius);

        if ($insert_stmt->execute()) {
            // --- TRIGGER LOG AKTIVITAS (ADD MANUAL) ---
            catatLog($koneksi, $current_user_id, "Menambahkan lokasi PKL baru: {$nama_lokasi} (Kuota: {$kuota_max} Siswa)");

            $message = "<div class='alert success'><i class='fas fa-check-circle me-2'></i> Lokasi PKL berhasil ditambahkan!</div>";
        } else {
            $message = "<div class='alert error'><i class='fas fa-exclamation-circle me-2'></i> Gagal menambahkan lokasi: " . $insert_stmt->error . "</div>";
        }
        $insert_stmt->close();
    }
}

// --- HANDLE POST 2: UPDATE MANUAL ---
if (isset($_POST['update_manual'])) {
    $lokasi_id = (int)$_POST['lokasi_id']; 
    $nama_lokasi = trim($_POST['nama_lokasi']);
    $hari_mulai = trim($_POST['hari_mulai']);
    $hari_selesai = trim($_POST['hari_selesai']);
    $alamat = trim($_POST['alamat']);
    $kuota_max = (int)$_POST['kuota_max'];
    $latitude = trim($_POST['latitude']);
    $longitude = trim($_POST['longitude']);
    $radius = trim($_POST['radius']);
    $jam_masuk = trim($_POST['jam_masuk']);
    $jam_pulang = trim($_POST['jam_pulang']);

    if (empty($lokasi_id) || empty($nama_lokasi) || empty($hari_mulai) || empty($hari_selesai) || empty($jam_masuk) || empty($jam_pulang) || empty($alamat) || $kuota_max < 1 || empty($latitude) || empty($longitude) || empty($radius)) {
        $message = "<div class='alert error'><i class='fas fa-exclamation-circle me-2'></i> Gagal update: Data tidak lengkap.</div>";
    } else {
        $jam_kerja_gabung = str_replace(':', '.', $jam_masuk) . " s.d. " . str_replace(':', '.', $jam_pulang) . " WIB";

        // --- DETEKSI PERUBAHAN DATA UNTUK LOG SUPER DETAIL ---
        $stmt_old = $koneksi->prepare("SELECT nama_lokasi, hari_mulai, hari_selesai, jam_kerja, alamat, kuota_max, latitude, longitude, radius FROM lokasi_pkl WHERE lokasi_id = ?");
        $stmt_old->bind_param("i", $lokasi_id);
        $stmt_old->execute();
        $old_data = $stmt_old->get_result()->fetch_assoc();
        $stmt_old->close();

        $perubahan = [];
        if ($old_data['nama_lokasi'] != $nama_lokasi) $perubahan[] = "Nama Lokasi";
        if ($old_data['hari_mulai'] != $hari_mulai) $perubahan[] = "Hari Mulai";
        if ($old_data['hari_selesai'] != $hari_selesai) $perubahan[] = "Hari Selesai";
        if ($old_data['jam_kerja'] != $jam_kerja_gabung) $perubahan[] = "Jam Kerja";
        if ($old_data['alamat'] != $alamat) $perubahan[] = "Alamat";
        if ($old_data['kuota_max'] != $kuota_max) $perubahan[] = "Kuota Max";
        if ($old_data['latitude'] != $latitude) $perubahan[] = "Latitude";
        if ($old_data['longitude'] != $longitude) $perubahan[] = "Longitude";
        if ($old_data['radius'] != $radius) $perubahan[] = "Radius";

        $pesan_log = "";
        if (count($perubahan) > 0) {
            $detail_ubah = implode(", ", $perubahan);
            // Contoh Hasil: Memperbarui data lokasi PKL: PT. XYZ (Detail yang diubah: Jam Kerja, Kuota Max)
            $pesan_log = "Memperbarui data lokasi PKL: " . $old_data['nama_lokasi'] . " (Detail yang diubah: " . $detail_ubah . ")";
        } else {
            $pesan_log = "Menyimpan ulang data lokasi PKL: " . $old_data['nama_lokasi'] . " (Tanpa perubahan data)";
        }
        // ------------------------------------------

        $update_stmt = $koneksi->prepare("UPDATE lokasi_pkl SET nama_lokasi=?, hari_mulai=?, hari_selesai=?, jam_kerja=?, alamat=?, kuota_max=?, latitude=?, longitude=?, radius=? WHERE lokasi_id=?");
        $update_stmt->bind_param("sssssisssi", $nama_lokasi, $hari_mulai, $hari_selesai, $jam_kerja_gabung, $alamat, $kuota_max, $latitude, $longitude, $radius, $lokasi_id);

        if ($update_stmt->execute()) {
            // --- TRIGGER LOG AKTIVITAS (UPDATE DETAIL) ---
            catatLog($koneksi, $current_user_id, $pesan_log);

            $message = "<div class='alert success'><i class='fas fa-check-circle me-2'></i> Lokasi PKL " . htmlspecialchars($nama_lokasi) . " berhasil diperbarui!</div>";
        } else {
            $message = "<div class='alert error'><i class='fas fa-exclamation-circle me-2'></i> Gagal update lokasi: " . $update_stmt->error . "</div>";
        }
        $update_stmt->close();
    }
}

// --- HANDLE POST 3: CSV IMPORT ---
if (isset($_POST['import_csv']) && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = "<div class='alert error'><i class='fas fa-exclamation-circle me-2'></i> Gagal mengunggah file.</div>";
    } else {
        $result = processCsvUpload($koneksi, $file['tmp_name'], $current_user_id);
        $msg_html = "Berhasil mengimpor <b>{$result['inserted']}</b> baris data.<br>";
        
        // --- TRIGGER LOG AKTIVITAS (BULK IMPORT) ---
        if ($result['inserted'] > 0) {
            catatLog($koneksi, $current_user_id, "Mengimpor masal " . $result['inserted'] . " data lokasi PKL baru via CSV");
        }

        if ($result['failed'] > 0) {
            $msg_html .= "⚠️ <b>{$result['failed']}</b> baris gagal diproses.";
            $message = "<div class='alert warning'><i class='fas fa-exclamation-triangle me-2'></i> {$msg_html}</div>";
        } else {
            $message = "<div class='alert success'><i class='fas fa-check-circle me-2'></i> {$msg_html}</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin - Data Lokasi PKL | Si Mantap PKL</title>
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

        .alert { 
            padding: 14px 20px; 
            margin-bottom: 25px; 
            border-radius: 10px; 
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
        }
        .alert.success { color: #16a34a; background-color: #f0fdf4; border: 1px solid #bbf7d0; }
        .alert.error { color: #dc2626; background-color: #fef2f2; border: 1px solid #fecaca; }
        .alert.warning { color: #d97706; background-color: #fffbec; border: 1px solid #fde68a; }

        .page-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 25px; 
            flex-wrap: wrap; 
            gap: 15px; 
            width: 100%;
        }
        
        .page-header h1 {
            font-weight: 700; 
            color: var(--mantap-blue-dark); 
            font-size: 1.8rem; 
            margin: 0; 
            position: relative;
        }
        .page-header h1::after {
            content: ''; position: absolute; left: 0; bottom: -5px; width: 50px; height: 4px; background: var(--mantap-blue-main); border-radius: 2px;
        }

        .header-actions-group {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-wrapper i {
            position: absolute;
            left: 12px;
            color: #64748b;
            font-size: 14px;
        }

        .search-input {
            padding: 8px 15px 8px 35px;
            border: 1px solid #cbd5e1;
            border-radius: 20px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            width: 220px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            outline: none;
            background: white;
            color: #334155;
        }

        .search-input:focus {
            border-color: var(--mantap-blue-light);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
            width: 260px;
        }

        .btn-add { 
            background-color: var(--mantap-blue-main); color: white; padding: 9px 20px; border: none; border-radius: 25px; cursor: pointer; font-size: 12px; font-weight: 600; font-family: 'Poppins', sans-serif; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 6px -1px rgba(30, 64, 175, 0.15); transition: 0.2s;
        }
        .btn-add:hover { background-color: var(--mantap-blue-light); transform: translateY(-2px); box-shadow: 0 6px 10px rgba(0,0,0,0.15); }

        .table-panel {
            background: white !important; padding: 25px !important; border-radius: 16px !important; border: 2px solid #e2e8f0 !important; box-sizing: border-box; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); width: 100%;
        }

        .table-container-fixed { width: 100%; overflow-x: auto; box-sizing: border-box; border: none !important; }

        .data-table { width: 100%; border-collapse: collapse; table-layout: fixed; border: 2px solid #1e40af; border-radius: 4px; overflow: hidden; }
        .data-table th { 
            background: #1e40af; color: white; padding: 14px 6px; font-size: 13px; text-transform: uppercase; font-weight: 700; text-align: center; border: 1px solid #cbd5e1; box-sizing: border-box;
        }
        
        .data-table td { padding: 12px 10px; font-size: 13px; vertical-align: middle; word-wrap: break-word; overflow-wrap: break-word; text-align: center; border: 1px solid #cbd5e1; color: #0f172a; background-color: white !important; box-sizing: border-box; text-overflow: unset; white-space: normal; }
        .data-table tr:hover td { background-color: #f8fafc !important; }
        
        .btn-action { padding: 5px 0; border-radius: 4px; color: white !important; text-decoration: none; font-size: 11px; font-weight: 600; width: 50px; text-align: center; display: inline-block; border: none; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn-edit { background-color: #f59e0b; }
        .btn-edit:hover { background-color: #d97706; }
        .btn-delete { background-color: #dc3545; }
        .btn-delete:hover { background-color: #ef4444; }
        
        /* MODAL */
        .modal { display: none; position: fixed; z-index: 999999 !important; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(15, 23, 42, 0.5); overflow-y: auto; box-sizing: border-box !important; }
        .modal-content { background-color: #fff; margin: 4.5rem auto; padding: 28px; border-radius: 12px; width: 90%; max-width: 650px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15); position: relative; border: 2px solid #cbd5e1; box-sizing: border-box !important; }
        .close { color: #64748b; float: right; font-size: 1.7rem; font-weight: bold; cursor: pointer; line-height: 1; }
        
        .modal-content label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; color: #0f172a; text-align: left; }
        
        .modal-content input[type="text"], 
        .modal-content input[type="number"], 
        .modal-content input[type="time"],
        .modal-content select { 
            width: 100%; 
            padding: 11px 12px; 
            border: 1px solid #cbd5e1; 
            border-radius: 8px; 
            box-sizing: border-box !important; 
            font-family: 'Poppins', sans-serif; 
            font-size: 13px; 
            background-color: #f8fafc; 
            color: #334155; 
            margin-bottom: 14px; 
            outline: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
        .modal-content select {
            background-image: url('data:image/svg+xml;utf8,<svg fill="%2364748b" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/><path d="M0 0h24v24H0z" fill="none"/></svg>');
            background-repeat: no-repeat;
            background-position: right 10px top 50%;
            cursor: pointer;
        }
        .modal-content input:focus, .modal-content select:focus { border-color: var(--mantap-blue-main); background-color: white; }
        
        .form-row-group { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; box-sizing: border-box !important; }
        .form-row-group>div { text-align: left; }
        
        .modal-content button[type="submit"] { width: 100%; color: white; padding: 11px; border: none; border-radius: 8px; cursor: pointer; margin-top: 10px; font-size: 14px; font-weight: 600; font-family: 'Poppins', sans-serif; display: flex; align-items: center; justify-content: center; gap: 8px; box-sizing: border-box !important; }
        
        .modal-title-add { margin-top:0; border-bottom: 2px solid #cbd5e1; padding-bottom: 12px; color: #0f172a; font-weight: 700; font-size: 1.4rem; text-align: left; }
        .modal-title-edit { margin-top:0; border-bottom: 2px solid #cbd5e1; padding-bottom: 12px; color: #0f172a; font-weight: 700; font-size: 1.4rem; text-align: left; }
        
        hr.divider { border: 0; border-top: 2px dashed #e2e8f0; margin: 20px 0; }
        
        .csv-title { font-size: 13px; font-weight: 700; color: var(--mantap-blue-dark); margin: 0 0 10px 0; text-align: left; }
        .csv-import-box { display: flex; gap: 10px; align-items: center; background: #f8fafc; padding: 12px; border-radius: 10px; border: 1px dashed #cbd5e1; box-sizing: border-box !important; }
        .csv-import-box input[type="file"] { font-family: 'Poppins', sans-serif; font-size: 13px; color: #64748b; }
        .btn-csv-submit { background-color: #0ea5e9; color: white; border: none; padding: 9px 20px; border-radius: 6px; cursor: pointer; font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 13px; box-shadow: 0 2px 4px rgba(14, 165, 233, 0.15); }

        .sidebar-nav ul, .sidebar-nav li, .dropdown-menu, .dropdown-menu li { list-style: none !important; margin: 0; padding: 0; }

        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 

            .page-header { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; padding: 5px 0px !important; }
            .page-header h1 { font-size: 1.4rem !important; }
            
            .header-actions-group { width: 100%; flex-direction: column; align-items: stretch; gap: 12px; }
            .search-wrapper { width: 100%; }
            .search-input { width: 100%; }
            .search-input:focus { width: 100%; }
            .btn-add { width: 100% !important; justify-content: center !important; padding: 11px !important; border-radius: 6px !important; font-size: 13px !important; }

            .table-panel { padding: 16px 10px !important; border-radius: 12px !important; border: 2px solid #cbd5e1 !important; }
            .table-container-fixed { display: block !important; width: 100% !important; overflow-x: auto !important; }

            .data-table { table-layout: auto !important; min-width: 980px !important; }
            .data-table th, .data-table td { padding: 12px 10px !important; font-size: 13px !important; text-align: center !important; }
            .data-table td:nth-child(2), .data-table td:nth-child(3) { text-align: left !important; }
            
            .btn-action { width: 55px !important; padding: 6px 0 !important; font-size: 12px !important; }

            .modal { padding: 12px !important; }
            .modal-content { margin: 1.0rem auto !important; width: 100% !important; padding: 16px 14px !important; border-radius: 10px !important; max-height: calc(100vh - 40px) !important; overflow-y: auto !important; }
            .form-row-group { display: flex !important; flex-direction: column !important; gap: 0px !important; }
            .modal-content input, .modal-content select { font-size: 13.5px !important; padding: 10px !important; }
            .modal-content button[type="submit"] { padding: 12px !important; font-size: 14.5px !important; border-radius: 6px !important; }
            .csv-import-box { flex-direction: column !important; align-items: stretch !important; gap: 12px !important; }
            .btn-csv-submit { width: 100% !important; padding: 11px !important; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; ?>

<div class="main-content-wrapper">

    <?php include 'panel/navbar.php'; ?>

    <div class="admin-main-content"> 
        <div class="page-header">
            <h1><i class="fas fa-map-marker-alt" style="color: var(--mantap-blue-main); margin-right: 4px;"></i> Data Lokasi PKL</h1>
            
            <div class="header-actions-group">
                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" class="search-input" placeholder="Cari nama lokasi atau alamat..." onkeyup="filterTable()">
                </div>
                
                <button class="btn-add" id="openAddModalBtn"><i class="fas fa-plus"></i> Tambah Lokasi</button>
            </div>
        </div>

        <?php echo $message; ?>

        <div class="table-panel">
            <div class="table-container-fixed">
                <table class="data-table" id="lokasiTableData">
                    <thead>
                        <tr>
                            <th style="width: 60px; text-align: center;">No</th>
                            <th style="width: 240px; text-align: left;">Nama Lokasi</th>
                            <th style="text-align: left;">Alamat</th>
                            <th style="width: 110px; text-align: center;">Kuota</th>
                            <th style="width: 220px; text-align: left;">Koordinat & Radius</th>
                            <th style="width: 125px; text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        $result = $koneksi->query("SELECT * FROM lokasi_pkl ORDER BY lokasi_id DESC");
                        
                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                
                                // Parse Jam Kerja
                                $raw_time_string = $row['jam_kerja'];
                                $js_jam_masuk = "08:00";
                                $js_jam_pulang = "16:00";

                                if (strpos($raw_time_string, 's.d.') !== false) {
                                    $parts = explode('s.d.', $raw_time_string);
                                    $js_jam_masuk = str_replace('.', ':', trim($parts[0]));
                                    $js_jam_pulang = str_replace('.', ':', trim(str_replace('WIB', '', $parts[1])));
                                    
                                    $js_jam_masuk = date("H:i", strtotime($js_jam_masuk));
                                    $js_jam_pulang = date("H:i", strtotime($js_jam_pulang));
                                }

                                // Ambil Hari Mulai dan Hari Selesai langsung dari database
                                $js_hari_mulai = htmlspecialchars($row['hari_mulai'] ?? 'Senin', ENT_QUOTES);
                                $js_hari_selesai = htmlspecialchars($row['hari_selesai'] ?? 'Jumat', ENT_QUOTES);
                                ?>
                                <tr>
                                    <td style="text-align: center; font-weight: 700;"><?php echo $no++; ?></td>
                                    <td style="text-align: left;">
                                        <strong style="color: var(--mantap-blue-dark); text-transform: uppercase; font-size: 13px;"><?php echo htmlspecialchars($row['nama_lokasi']); ?></strong><br>
                                        <small style="color: #475569; font-weight: 600;"><i class="fas fa-calendar-alt me-1"></i><?php echo $js_hari_mulai . ' - ' . $js_hari_selesai; ?></small><br>
                                        <small style="color: var(--mantap-blue-main); font-weight: 600;"><i class="fas fa-clock me-1"></i><?php echo htmlspecialchars($row['jam_kerja']); ?></small>
                                    </td>
                                    <td style="text-align: left;"><?php echo htmlspecialchars($row['alamat']); ?></td>
                                    <td style="text-align: center; font-weight: 700; color: #1e40af;"><?php echo htmlspecialchars($row['kuota_max']); ?> Siswa</td>
                                    <td style="text-align: left;">
                                        <small style="color: #475569; display: block; font-family: monospace;">Lat: <?php echo htmlspecialchars($row['latitude']); ?></small>
                                        <small style="color: #475569; display: block; font-family: monospace;">Lng: <?php echo htmlspecialchars($row['longitude']); ?></small>
                                        <span style="background:#eff6ff; color:#1e40af; padding: 2px 6px; border-radius:4px; font-weight:700; font-size:11px; display:inline-block; margin-top:2px;">Radius: <?php echo htmlspecialchars($row['radius']); ?>m</span>
                                    </td>
                                    <td style="text-align: center;">
                                        <button class="btn-action btn-edit btn-edit-trigger" 
                                                data-id="<?php echo $row['lokasi_id']; ?>"
                                                data-nama="<?php echo htmlspecialchars($row['nama_lokasi'], ENT_QUOTES); ?>"
                                                data-harimulai="<?php echo $js_hari_mulai; ?>"
                                                data-hariselesai="<?php echo $js_hari_selesai; ?>"
                                                data-jamins="<?php echo $js_jam_masuk; ?>"
                                                data-jamout="<?php echo $js_jam_pulang; ?>"
                                                data-alamat="<?php echo htmlspecialchars($row['alamat'], ENT_QUOTES); ?>"
                                                data-kuota="<?php echo $row['kuota_max']; ?>"
                                                data-lat="<?php echo $row['latitude']; ?>"
                                                data-lng="<?php echo $row['longitude']; ?>"
                                                data-radius="<?php echo $row['radius']; ?>"
                                                title="Edit Lokasi"><i class="fas fa-edit"></i></button>
                                        <button type="button" class="btn-action btn-delete" onclick="konfirmasiHapusLokasi('<?php echo $row['lokasi_id']; ?>')"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding: 30px; color:#ef4444; font-weight:500; font-style: italic;">
                                    Belum ada data lokasi PKL.
                                </td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="addModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeFormModal('addModal')">&times;</span>
        <h2 class="modal-title-add"><i class="fas fa-plus-circle" style="color: var(--mantap-blue-main); margin-right: 6px;"></i> Tambah Lokasi PKL</h2>
        <form method="POST" action="">
            <input type="hidden" name="add_manual" value="1">
            <label>Nama Lokasi PKL</label>
            <input type="text" name="nama_lokasi" placeholder="Contoh: PT. Adhikari Teknik Blitar" required>
            
            <div class="form-row-group">
                <div>
                    <label><i class="fas fa-calendar-day text-success" style="margin-right: 4px;"></i>Hari Mulai</label>
                    <select name="hari_mulai" required>
                        <option value="Senin">Senin</option>
                        <option value="Selasa">Selasa</option>
                        <option value="Rabu">Rabu</option>
                        <option value="Kamis">Kamis</option>
                        <option value="Jumat">Jumat</option>
                        <option value="Sabtu">Sabtu</option>
                        <option value="Minggu">Minggu</option>
                    </select>
                </div>
                <div>
                    <label><i class="fas fa-calendar-check text-danger" style="margin-right: 4px;"></i>Hari Selesai</label>
                    <select name="hari_selesai" required>
                        <option value="Senin">Senin</option>
                        <option value="Selasa">Selasa</option>
                        <option value="Rabu">Rabu</option>
                        <option value="Kamis">Kamis</option>
                        <option value="Jumat" selected>Jumat</option>
                        <option value="Sabtu">Sabtu</option>
                        <option value="Minggu">Minggu</option>
                    </select>
                </div>
            </div>

            <div class="form-row-group">
                <div>
                    <label><i class="fas fa-sign-in-alt text-success" style="margin-right: 4px;"></i>Jam Masuk</label>
                    <input type="time" name="jam_masuk" value="08:00" required>
                </div>
                <div>
                    <label><i class="fas fa-sign-out-alt text-danger" style="margin-right: 4px;"></i>Jam Pulang</label>
                    <input type="time" name="jam_pulang" value="16:00" required>
                </div>
            </div>

            <label>Alamat Lengkap</label>
            <input type="text" name="alamat" placeholder="Jl. Sudanco Supriadi No. 15 Blitar" required>
            
            <div class="form-row-group">
                <div><label>Latitude</label><input type="text" name="latitude" placeholder="-8.102345" required></div>
                <div><label>Longitude</label><input type="text" name="longitude" placeholder="112.164532" required></div>
            </div>
            <div class="form-row-group">
                <div><label>Radius Absensi (Meter)</label><input type="number" name="radius" value="50" min="1" required></div>
                <div><label>Kuota Max (Siswa)</label><input type="number" name="kuota_max" value="5" min="1" required></div>
            </div>
            <button type="submit" style="background-color: #22c55e; box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.2);"><i class="fas fa-save"></i> Simpan Lokasi</button>
        </form>
        
        <hr class="divider">
        
        <h3 class="csv-title"><i class="fas fa-file-csv text-info" style="margin-right: 4px;"></i> Import via CSV</h3>
        <form method="POST" action="" enctype="multipart/form-data" class="csv-import-box">
            <input type="file" name="csv_file" accept=".csv" required style="flex: 1;">
            <button type="submit" name="import_csv" class="btn-csv-submit">Import</button>
        </form>
    </div>
</div>

<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeFormModal('editModal')">&times;</span>
        <h2 class="modal-title-edit"><i class="fas fa-edit" style="color: #f59e0b; margin-right: 6px;"></i> Edit Lokasi PKL</h2>
        <form method="POST" action="">
            <input type="hidden" name="update_manual" value="1">
            <input type="hidden" name="lokasi_id" id="edit_lokasi_id">
            
            <label>Nama Lokasi PKL</label>
            <input type="text" name="nama_lokasi" id="edit_nama_lokasi" required>
            
            <div class="form-row-group">
                <div>
                    <label><i class="fas fa-calendar-day text-success" style="margin-right: 4px;"></i>Hari Mulai</label>
                    <select name="hari_mulai" id="edit_hari_mulai" required>
                        <option value="Senin">Senin</option>
                        <option value="Selasa">Selasa</option>
                        <option value="Rabu">Rabu</option>
                        <option value="Kamis">Kamis</option>
                        <option value="Jumat">Jumat</option>
                        <option value="Sabtu">Sabtu</option>
                        <option value="Minggu">Minggu</option>
                    </select>
                </div>
                <div>
                    <label><i class="fas fa-calendar-check text-danger" style="margin-right: 4px;"></i>Hari Selesai</label>
                    <select name="hari_selesai" id="edit_hari_selesai" required>
                        <option value="Senin">Senin</option>
                        <option value="Selasa">Selasa</option>
                        <option value="Rabu">Rabu</option>
                        <option value="Kamis">Kamis</option>
                        <option value="Jumat">Jumat</option>
                        <option value="Sabtu">Sabtu</option>
                        <option value="Minggu">Minggu</option>
                    </select>
                </div>
            </div>

            <div class="form-row-group">
                <div>
                    <label><i class="fas fa-sign-in-alt text-success" style="margin-right: 4px;"></i>Jam Masuk</label>
                    <input type="time" name="jam_masuk" id="edit_jam_masuk" required>
                </div>
                <div>
                    <label><i class="fas fa-sign-out-alt text-danger" style="margin-right: 4px;"></i>Jam Pulang</label>
                    <input type="time" name="jam_pulang" id="edit_jam_pulang" required>
                </div>
            </div>

            <label>Alamat Lengkap</label>
            <input type="text" name="alamat" id="edit_alamat" required>
            
            <div class="form-row-group">
                <div><label>Latitude</label><input type="text" name="latitude" id="edit_latitude" required></div>
                <div><label>Longitude</label><input type="text" name="longitude" id="edit_longitude" required></div>
            </div>
            <div class="form-row-group">
                <div><label>Radius Absensi (Meter)</label><input type="number" name="radius" id="edit_radius" min="1" required></div>
                <div><label>Kuota Max (Siswa)</label><input type="number" name="kuota_max" id="edit_kuota_max" min="1" required></div>
            </div>
            <button type="submit" style="background-color: #f59e0b; box-shadow: 0 4px 6px -1px rgba(245, 158, 11, 0.2);"><i class="fas fa-sync-alt"></i> Update Lokasi</button>
        </form>
    </div>
</div>

<script>
    // --- FITUR PENCARIAN REAL-TIME JS ---
    function filterTable() {
        var input, filter, table, tr, tdNama, tdAlamat, i, txtValueNama, txtValueAlamat;
        input = document.getElementById("searchInput");
        filter = input.value.toUpperCase();
        table = document.getElementById("lokasiTableData");
        tr = table.getElementsByTagName("tr");

        for (i = 1; i < tr.length; i++) {
            tdNama = tr[i].getElementsByTagName("td")[1]; // Kolom ke-2 (Nama Lokasi)
            tdAlamat = tr[i].getElementsByTagName("td")[2]; // Kolom ke-3 (Alamat)
            
            if (tdNama || tdAlamat) {
                txtValueNama = tdNama ? tdNama.textContent || tdNama.innerText : "";
                txtValueAlamat = tdAlamat ? tdAlamat.textContent || tdAlamat.innerText : "";
                
                if (txtValueNama.toUpperCase().indexOf(filter) > -1 || txtValueAlamat.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }
    }

    var addModal = document.getElementById("addModal");
    var editModal = document.getElementById("editModal");
    var btnAdd = document.getElementById("openAddModalBtn");

    btnAdd.onclick = function() { addModal.style.display = "block"; }

    document.querySelectorAll('.btn-edit-trigger').forEach(function(trigger) {
        trigger.onclick = function() {
            document.getElementById('edit_lokasi_id').value = this.getAttribute('data-id');
            document.getElementById('edit_nama_lokasi').value = this.getAttribute('data-nama');
            document.getElementById('edit_hari_mulai').value = this.getAttribute('data-harimulai');
            document.getElementById('edit_hari_selesai').value = this.getAttribute('data-hariselesai');
            document.getElementById('edit_jam_masuk').value = this.getAttribute('data-jamins');
            document.getElementById('edit_jam_pulang').value = this.getAttribute('data-jamout');
            document.getElementById('edit_alamat').value = this.getAttribute('data-alamat');
            document.getElementById('edit_kuota_max').value = this.getAttribute('data-kuota');
            document.getElementById('edit_latitude').value = this.getAttribute('data-lat');
            document.getElementById('edit_longitude').value = this.getAttribute('data-lng');
            document.getElementById('edit_radius').value = this.getAttribute('data-radius');
            editModal.style.display = "block";
        }
    });

    function closeFormModal(modalId) { document.getElementById(modalId).style.display = "none"; }
    
    window.onclick = function(event) {
        if (event.target == addModal) addModal.style.display = "none";
        if (event.target == editModal) editModal.style.display = "none";
    }

    function konfirmasiHapusLokasi(id) {
        Swal.fire({
            title: 'Yakin Ingin Menghapus?',
            text: "Data lokasi yang terhapus tidak dapat dikembalikan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `lokasi-delete.php?id=${id}`;
            }
        });
    }
</script>

<?php include 'panel/footer.php'; ?>
</body>
</html>