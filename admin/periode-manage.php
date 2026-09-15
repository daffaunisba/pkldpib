<?php 
// admin/periode-manage.php
include 'auth-check.php'; 
include '../config/db-koneksi.php';

// Inisialisasi session untuk PRG pattern jika belum aktif
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$current_user = isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin';
$current_user_id = $_SESSION['user_id'] ?? 0; // Ditambahkan untuk kebutuhan log

// Ambil pesan dari session (jika ada hasil redirect) atau dari variabel lokal
$message = '';
if (isset($_SESSION['msg_flash'])) {
    $message = $_SESSION['msg_flash'];
    unset($_SESSION['msg_flash']); // Hapus setelah dibaca
}

// --- AUTO-UPDATE STRUKTUR DATABASE UNTUK FITUR ARSIP (ANTI-CRASH) ---
try {
    // 1. Tambah kolom penanda gelombang selesai/arsip
    $cek_periode = $koneksi->query("SHOW COLUMNS FROM periode_pkl LIKE 'is_archived'");
    if ($cek_periode && $cek_periode->num_rows == 0) {
        $koneksi->query("ALTER TABLE periode_pkl ADD COLUMN is_archived INT(1) DEFAULT 0 AFTER is_active");
    }
    
    // 2. Tambah kolom untuk membackup lokasi siswa (agar kuota bisa dikosongkan)
    $cek_peserta = $koneksi->query("SHOW COLUMNS FROM peserta_didik LIKE 'lokasi_id_arsip'");
    if ($cek_peserta && $cek_peserta->num_rows == 0) {
        $koneksi->query("ALTER TABLE peserta_didik ADD COLUMN lokasi_id_arsip INT NULL AFTER lokasi_id");
    }
} catch (Exception $e) {}
// -------------------------------------------------------------------

// --- CRUD LOGIC ---

// Handle ADD
if (isset($_POST['add_periode'])) {
    $nama = trim($_POST['nama_periode']);
    $mulai = trim($_POST['tgl_mulai']);
    $akhir = trim($_POST['tgl_akhir']);
    $kuota = (int)$_POST['kuota_gelombang']; 
    $is_active = isset($_POST['is_active']) ? 1 : 0; 

    if (!empty($nama) && !empty($mulai) && !empty($akhir) && $kuota >= 0) {
        $stmt = $koneksi->prepare("INSERT INTO periode_pkl (nama_periode, tgl_mulai, tgl_akhir, kuota_gelombang, is_active) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiii", $nama, $mulai, $akhir, $kuota, $is_active);
        
        if ($stmt->execute()) {
            $status_text = $is_active ? 'Buka' : 'Tutup';
            $message = "<div class='alert success'>✅ Periode '{$nama}' berhasil ditambahkan (Pendaftaran: **{$status_text}**).</div>";
            
            // --- TRIGGER LOG AKTIVITAS (ADD) ---
            $pesan_log = "Menambahkan periode PKL baru: {$nama} (Rentang: {$mulai} s/d {$akhir}, Kuota Maksimal: {$kuota} Siswa, Status Akses: {$status_text})";
            catatLog($koneksi, $current_user_id, $pesan_log);

        } else {
            $message = "<div class='alert error'>Gagal menambahkan periode: " . $stmt->error . "</div>";
        }
        $stmt->close();
    } else {
        $message = "<div class='alert warning'>Semua field wajib diisi, dan Kuota Gelombang harus angka positif.</div>";
    }
}

// Handle DELETE
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    $check_stmt = $koneksi->prepare("SELECT COUNT(*) FROM peserta_didik WHERE periode_id = ?");
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_stmt->bind_result($count);
    $check_stmt->fetch();
    $check_stmt->close();

    if ($count > 0) {
        $message = "<div class='alert error'>Gagal menghapus: Ada {$count} peserta yang masih terdaftar di periode ini. Gunakan fitur <b>Arsipkan</b> jika ingin menyelesaikannya.</div>";
    } else {
        // Ambil nama periode sebelum dihapus untuk log
        $nama_dihapus = "ID " . $id;
        $get_nama_stmt = $koneksi->query("SELECT nama_periode FROM periode_pkl WHERE periode_id = $id");
        if ($get_nama_stmt && $get_nama_stmt->num_rows > 0) {
            $nama_dihapus = $get_nama_stmt->fetch_assoc()['nama_periode'];
        }

        $delete_stmt = $koneksi->prepare("DELETE FROM periode_pkl WHERE periode_id = ?");
        $delete_stmt->bind_param("i", $id);
        if ($delete_stmt->execute()) {
            $message = "<div class='alert success'>🗑️ Periode berhasil dihapus permanen.</div>";
            
            // --- TRIGGER LOG AKTIVITAS (DELETE) ---
            catatLog($koneksi, $current_user_id, "Menghapus permanen periode PKL: " . $nama_dihapus);

        } else {
            $message = "<div class='alert error'>Gagal menghapus periode.</div>";
        }
        $delete_stmt->close();
    }
}

// Handle TOGGLE Status Buka/Tutup PENDAFTARAN
if (isset($_GET['action']) && $_GET['action'] == 'toggle' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Tambahkan field nama_periode di SELECT untuk kebutuhan log
    $status_stmt = $koneksi->prepare("SELECT is_active, is_archived, nama_periode FROM periode_pkl WHERE periode_id = ?");
    $status_stmt->bind_param("i", $id);
    $status_stmt->execute();
    $status_stmt->bind_result($current_status, $is_arch, $nama_per);
    $status_stmt->fetch();
    $status_stmt->close();
    
    if ($is_arch == 1) {
        $_SESSION['msg_flash'] = "<div class='alert error'>Akses ditolak: Gelombang yang sudah 'Selesai/Diarsipkan' tidak bisa dibuka pendaftarannya. Aktifkan kembali gelombang terlebih dahulu.</div>";
    } else {
        $new_status = $current_status == 1 ? 0 : 1;
        $new_status_text = $new_status == 1 ? 'DIBUKA' : 'DITUTUP';
        
        $toggle_stmt = $koneksi->prepare("UPDATE periode_pkl SET is_active = ? WHERE periode_id = ?");
        $toggle_stmt->bind_param("ii", $new_status, $id);
        
        if ($toggle_stmt->execute()) {
            $_SESSION['msg_flash'] = "<div class='alert success'>⚙️ Status pendaftaran periode berhasil diubah menjadi **{$new_status_text}**!</div>";
            
            // --- TRIGGER LOG AKTIVITAS (TOGGLE PENDAFTARAN) ---
            catatLog($koneksi, $current_user_id, "Mengubah akses pendaftaran siswa untuk periode '{$nama_per}' menjadi: {$new_status_text}");

        } else {
            $_SESSION['msg_flash'] = "<div class='alert error'>Gagal mengubah status periode.</div>";
        }
        $toggle_stmt->close();
    }
    header("Location: periode-manage.php");
    exit();
}

// Handle ARCHIVE / UNARCHIVE (Penyelesaian Gelombang)
if (isset($_GET['action']) && $_GET['action'] == 'archive' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    $stmt_cek = $koneksi->prepare("SELECT is_archived, nama_periode FROM periode_pkl WHERE periode_id = ?");
    $stmt_cek->bind_param("i", $id);
    $stmt_cek->execute();
    $stmt_cek->bind_result($is_archived, $nama_per);
    $stmt_cek->fetch();
    $stmt_cek->close();

    if ($is_archived == 0) {
        // PROSES ARSIP: Tutup Pendaftaran + Pindahkan Lokasi Siswa ke Arsip agar Kuota Kembali
        $koneksi->query("UPDATE periode_pkl SET is_archived = 1, is_active = 0 WHERE periode_id = $id");
        $koneksi->query("UPDATE peserta_didik SET lokasi_id_arsip = lokasi_id, lokasi_id = NULL WHERE periode_id = $id AND lokasi_id IS NOT NULL");
        
        $_SESSION['msg_flash'] = "<div class='alert success'>📦 Gelombang <b>{$nama_per}</b> telah ditandai SELESAI. Kuota lokasi berhasil dikembalikan, dan data siswa telah diarsipkan!</div>";
        
        // --- TRIGGER LOG AKTIVITAS (ARSIPKAN) ---
        catatLog($koneksi, $current_user_id, "Menandai SELESAI & mengarsipkan gelombang PKL: {$nama_per} (Status: Data riwayat diamankan, Kuota Mitra Industri berhasil dikembalikan)");

    } else {
        // PROSES AKTIFKAN: Kembalikan Gelombang + Kembalikan Siswa ke Lokasinya
        $koneksi->query("UPDATE periode_pkl SET is_archived = 0 WHERE periode_id = $id");
        $koneksi->query("UPDATE peserta_didik SET lokasi_id = lokasi_id_arsip, lokasi_id_arsip = NULL WHERE periode_id = $id AND lokasi_id_arsip IS NOT NULL");
        
        $_SESSION['msg_flash'] = "<div class='alert success'>🔄 Gelombang <b>{$nama_per}</b> BERJALAN kembali. Siswa telah dikembalikan ke lokasi PKL masing-masing!</div>";

        // --- TRIGGER LOG AKTIVITAS (UNARCHIVE) ---
        catatLog($koneksi, $current_user_id, "Mengaktifkan kembali gelombang PKL dari arsip: {$nama_per} (Status: Siswa dikembalikan ke Lokasi Industri masing-masing)");
    }
    header("Location: periode-manage.php");
    exit();
}

// Ambil Data: Urutkan yang aktif/berjalan di atas, yang sudah selesai di bawah
$periode_data = $koneksi->query("SELECT p.*, (SELECT COUNT(*) FROM peserta_didik WHERE periode_id = p.periode_id) AS kuota_terisi FROM periode_pkl p ORDER BY is_archived ASC, tgl_mulai DESC");

function formatTanggalIndo($date_str) {
    if (empty($date_str)) return '-';
    $timestamp = strtotime($date_str);
    $bulan_indo = [
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
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
    <title>Admin - Kelola Periode PKL | Si Mantap PKL</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --mantap-blue-dark: #0f172a;
            --mantap-blue-main: #1e40af;
            --mantap-blue-light: #3b82f6;
            --mantap-blue-soft: #eff6ff;
        }

        body { font-family: 'Poppins', sans-serif; background-color: #f8fafc; color: #334155; margin: 0; overflow-x: hidden !important; }
        .main-content-wrapper { max-width: 100% !important; width: 100% !important; box-sizing: border-box !important; box-shadow: none !important; }
        .admin-main-content { padding: 20px 25px 30px 25px !important; box-sizing: border-box !important; clear: both; width: 100% !important; }

        .page-header-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; width: 100%; flex-wrap: wrap; gap: 15px; }
        .page-header-controls h1 { font-weight: 700; color: #0f172a; font-size: 1.8rem; margin: 0; position: relative; }
        .page-header-controls h1::after { content: ''; position: absolute; left: 0; bottom: -5px; width: 50px; height: 4px; background: var(--mantap-blue-main); border-radius: 2px; }

        .alert.success, .alert.error, .alert.warning { padding: 12px 15px; margin-bottom: 20px; border-radius: 8px; border: 1px solid; font-size: 14px; font-weight: 500; }
        .alert.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .alert.warning { background-color: #fff3cd; color: #856404; border-color: #ffeeba; }
        
        .form-add-periode { background-color: white; padding: 25px; border-radius: 16px; margin-bottom: 30px; border: 2px solid #e2e8f0; box-sizing: border-box; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); }
        .form-add-periode h2 { margin-top: 0; font-weight: 700; color: var(--mantap-blue-dark); font-size: 1.4rem; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 20px; }
        .form-add-periode label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13.5px; color: #0f172a; }
        
        .form-add-periode input[type="text"], .form-add-periode input[type="date"], .form-add-periode input[type="number"] {
            width: 100%; padding: 11px 14px; margin-bottom: 16px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 13.5px; box-sizing: border-box; background-color: #f8fafc;
        }
        .form-add-periode input:focus { outline: none; border-color: #1e40af; background-color: white; }

        .form-grid-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; box-sizing: border-box; }
        .form-inner-group { text-align: left; box-sizing: border-box; }

        .toggle-switch { position: relative; display: inline-block; width: 54px; height: 28px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 4px; bottom: 4px; background-color: white; transition: .3s; border-radius: 50%; }
        input:checked + .slider { background-color: #22c55e; }
        input:checked + .slider:before { transform: translateX(26px); }

        .btn-aksi { padding: 10px 24px !important; font-weight: 600 !important; background-color: #22c55e; color: white; border: none; border-radius: 20px; font-size: 13px; cursor: pointer; font-family: 'Poppins', sans-serif; box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.2); display: inline-flex; align-items: center; gap: 6px; text-decoration: none; }
        .btn-aksi:hover { background-color: #16a34a; }
        
        .dashboard-btn-group { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; justify-content: center; width: 100%;}
        .dashboard-btn-group a { flex: 1 1 45%; box-sizing: border-box; padding: 6px 0; border-radius: 4px; text-decoration: none; font-size: 10.5px; font-weight: 700; text-align: center; color: white !important; transition: 0.2s;}

        .btn-toggle-open { background-color: #22c55e; box-shadow: 0 2px 4px rgba(34,197,94,0.15); }
        .btn-toggle-open:hover { background-color: #16a34a; }
        .btn-toggle-close { background-color: #64748b; box-shadow: 0 2px 4px rgba(100,116,139,0.15); }
        .btn-toggle-close:hover { background-color: #475569; }
        .btn-edit-periode { background-color: var(--mantap-blue-main); box-shadow: 0 2px 4px rgba(30,64,175,0.15); }
        .btn-edit-periode:hover { background-color: var(--mantap-blue-light); }
        .btn-delete { background-color: #dc3545; box-shadow: 0 2px 4px rgba(220,53,69,0.15); }
        .btn-delete:hover { background-color: #ef4444; }
        
        /* Tombol Arsip */
        .btn-archive { background-color: #f59e0b; box-shadow: 0 2px 4px rgba(245,158,11,0.15); }
        .btn-archive:hover { background-color: #d97706; }
        .btn-unarchive { background-color: #8b5cf6; box-shadow: 0 2px 4px rgba(139,92,246,0.15); }
        .btn-unarchive:hover { background-color: #7c3aed; }

        /* Badge Status */
        .badge-status { padding: 4px 10px; border-radius: 20px; font-size: 10.5px; font-weight: 700; display: inline-block; width: max-content; }
        .badge-berjalan { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .badge-selesai { background: #f1f5f9; color: #475569; border: 1px dashed #cbd5e1; }

        .glass-panel-table { background: white !important; padding: 25px !important; border-radius: 16px !important; border: 2px solid #e2e8f0 !important; box-sizing: border-box; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); width: 100%; }
        .glass-panel-table h2 { margin-top: 0; font-weight: 700; color: var(--mantap-blue-dark); font-size: 1.4rem; margin-bottom: 20px; }

        .table-container-fixed { width: 100%; overflow-x: auto; box-sizing: border-box; border: none !important; }

        .admin-main-content table { width: 100%; table-layout: fixed; border-collapse: collapse; background: white; border-radius: 4px; overflow: hidden; border: 2px solid #1e40af; }
        .admin-main-content table th { background: #1e40af; color: white; padding: 14px 6px; font-size: 12px; text-transform: uppercase; font-weight: 700; text-align: center; border: 1px solid #cbd5e1; box-sizing: border-box; }

        .admin-main-content table th.col-no { width: 45px; } 
        .admin-main-content table th.col-nama { width: 22%; text-align: left; } 
        .admin-main-content table th.col-waktu { width: 16%; } 
        .admin-main-content table th.col-kuota { width: 12%; } 
        .admin-main-content table th.col-status-daftar { width: 13%; } 
        .admin-main-content table th.col-status-jalan { width: 14%; } 
        .admin-main-content table th.col-aksi { width: 190px; }

        .admin-main-content table td { padding: 12px 10px; font-size: 13px; vertical-align: middle; word-wrap: break-word; overflow-wrap: break-word; text-align: center; border: 1px solid #cbd5e1; color: #0f172a; background-color: white !important; box-sizing: border-box; }
        .admin-main-content tr:hover td { background-color: #f8fafc !important; }
        
        /* Baris yang diarsipkan menjadi agar pudar */
        .row-archived td { background-color: #f8fafc !important; color: #64748b; }
        .row-archived .title-periode { color: #64748b !important; text-decoration: line-through; opacity: 0.7;}

        .sidebar-nav ul, .sidebar-nav li, .dropdown-menu, .dropdown-menu li { list-style: none !important; list-style-type: none !important; margin: 0; padding: 0; }

        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 
            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; padding: 5px 0px !important; }
            .form-add-periode { padding: 18px 14px !important; border-radius: 12px !important; }
            .form-grid-row { display: flex !important; flex-direction: column !important; gap: 0px !important; }
            .form-add-periode button.btn-aksi { width: 100% !important; justify-content: center !important; padding: 12px !important; border-radius: 8px !important; font-size: 14px !important; }
            .glass-panel-table { padding: 16px 10px !important; border-radius: 12px !important; border: 2px solid #cbd5e1 !important; }
            .table-container-fixed { display: block !important; width: 100% !important; overflow-x: auto !important; border: none !important; }
            .admin-main-content table { table-layout: auto !important; min-width: 950px !important; }
            .admin-main-content table th, .admin-main-content table td { padding: 12px 10px !important; font-size: 13px !important; text-align: center !important; }
            .admin-main-content table th.col-no, .admin-main-content table th.col-nama, .admin-main-content table th.col-waktu, .admin-main-content table th.col-kuota, .admin-main-content table th.col-status-daftar, .admin-main-content table th.col-status-jalan, .admin-main-content table th.col-aksi { width: auto !important; }
            .admin-main-content table td:nth-child(2) { text-align: left !important; }
            .dashboard-btn-group a { flex: 1 1 100%; } /* Di HP Tombol berjejer turun */
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; ?>

<div class="main-content-wrapper">
    <?php include 'panel/navbar.php'; ?>

    <div class="admin-main-content"> 
        <div class="page-header-controls">
            <h1>Kelola Periode (Gelombang) PKL</h1>
        </div>
        
        <?php echo $message; ?>

        <div class="form-add-periode">
            <h2><i class="fas fa-plus-circle" style="color: var(--mantap-blue-main); margin-right:6px;"></i> Tambah Periode Baru</h2>
            <form method="POST" action="periode-manage.php">
                <label for="nama_periode">Nama Gelombang/Periode:</label>
                <input type="text" id="nama_periode" name="nama_periode" placeholder="Contoh: Gelombang 3" required>
                
                <div class="form-grid-row">
                    <div class="form-inner-group">
                        <label for="tgl_mulai">Tanggal Mulai PKL:</label>
                        <input type="date" id="tgl_mulai" name="tgl_mulai" required>
                    </div>
                    <div class="form-inner-group">
                        <label for="tgl_akhir">Tanggal Berakhir PKL:</label>
                        <input type="date" id="tgl_akhir" name="tgl_akhir" required>
                    </div>
                </div>
                
                <label for="kuota_gelombang">Kuota Maksimum Siswa untuk Gelombang Ini:</label>
                <input type="number" id="kuota_gelombang" name="kuota_gelombang" placeholder="Contoh: 100" min="0" required value="100">

                <div style="margin-top: 5px; margin-bottom: 5px; display: flex; align-items: center; justify-content: space-between; border: 1px solid #ced4da; padding: 12px; border-radius: 8px; background-color: #f8fafc;">
                    <label for="is_active" style="margin: 0; font-weight: 700;">Status Akses Pendaftaran:</label>
                    <label class="toggle-switch" style="margin-bottom: 0;">
                        <input type="checkbox" id="is_active" name="is_active" value="1"> 
                        <span class="slider"></span>
                    </label>
                </div>
                <p style="font-size: 11.5px; margin-top: 5px; color: #64748b; margin-bottom: 20px;">*Jika status aktif dicentang hijau, periode ini akan langsung dibuka dan muncul otomatis di form pendaftaran siswa.</p>
                
                <button type="submit" name="add_periode" class="btn-aksi">
                    <i class="fas fa-save"></i> Simpan Periode Baru
                </button>
            </form>
        </div>

        <div class="glass-panel-table">
            <h2>Status & Logika Gelombang Pendaftaran</h2>
            <div class="table-container-fixed">
                <table>
                    <thead>
                        <tr>
                            <th class="col-no">NO</th>
                            <th class="col-nama">NAMA PERIODE</th>
                            <th class="col-waktu">MASA PELAKSANAAN</th>
                            <th class="col-kuota">KUOTA</th>
                            <th class="col-status-daftar">PENDAFTARAN</th> 
                            <th class="col-status-jalan">STATUS</th> 
                            <th class="col-aksi">AKSI OPERASI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; if ($periode_data && $periode_data->num_rows > 0): ?>
                            <?php while ($row = $periode_data->fetch_assoc()): 
                                $is_active_status = $row['is_active'];
                                $is_archived = $row['is_archived'];
                                $row_class = $is_archived ? 'row-archived' : '';
                                
                                // Daftar Logika
                                $daftar_text = $is_active_status ? '<span style="color: #22c55e; font-weight: 700;"><i class="fas fa-check-circle"></i> BUKA</span>' : '<span style="color: #ef4444; font-weight: 700;"><i class="fas fa-lock"></i> TUTUP</span>';
                                $toggle_btn_class = $is_active_status ? 'btn-toggle-close' : 'btn-toggle-open';
                                $toggle_btn_text = $is_active_status ? 'Tutup Pendaftaran' : 'Buka Pendaftaran';
                                $toggle_btn_icon = $is_active_status ? 'fa-lock' : 'fa-lock-open';

                                // Arsip Logika
                                $jalan_badge = $is_archived ? '<span class="badge-status badge-selesai">SELESAI (ARSIP)</span>' : '<span class="badge-status badge-berjalan">BERJALAN</span>';
                                $arsip_btn_class = $is_archived ? 'btn-unarchive' : 'btn-archive';
                                $arsip_btn_text = $is_archived ? 'Buka Arsip / Lanjut' : 'Tandai Selesai';
                                $arsip_btn_icon = $is_archived ? 'fa-box-open' : 'fa-archive';
                            ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td style="font-weight: 700;"><?php echo $no++; ?></td>
                                <td style="text-align: left;">
                                    <strong class="title-periode" style="color: var(--mantap-blue-dark); font-size:14px; display:block;"><?php echo htmlspecialchars($row['nama_periode']); ?></strong>
                                </td>
                                <td>
                                    <div style="font-size: 11px; font-weight: 600;">
                                        <span style="color: #10b981;">Mulai:</span> <?php echo formatTanggalIndo($row['tgl_mulai']); ?><br>
                                        <span style="color: #ef4444;">Akhir:</span> <?php echo formatTanggalIndo($row['tgl_akhir']); ?>
                                    </div>
                                </td>
                                <td style="font-weight: 600;">
                                    <span style="color: <?php echo ($row['kuota_terisi'] >= $row['kuota_gelombang']) ? '#ef4444' : '#1e40af'; ?>">
                                        <?php echo $row['kuota_terisi']; ?>
                                    </span>
                                    <span style="color: #64748b; font-weight: 400;"> / <?php echo $row['kuota_gelombang']; ?></span>
                                </td>
                                <td><?php echo $daftar_text; ?></td> 
                                <td><?php echo $jalan_badge; ?></td> 
                                <td>
                                    <div class="dashboard-btn-group">
                                        <!-- Tombol Pendaftaran -->
                                        <?php if (!$is_archived): ?>
                                            <a href="periode-manage.php?action=toggle&id=<?php echo $row['periode_id']; ?>" class="<?php echo $toggle_btn_class; ?>" title="Ubah Status Akses">
                                                <i class="fas <?php echo $toggle_btn_icon; ?>"></i> <?php echo $toggle_btn_text; ?>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <!-- Tombol Arsip (Selesai/Lanjut) -->
                                        <a href="#" onclick="konfirmasiArsip(event, <?php echo $row['periode_id']; ?>, <?php echo $is_archived; ?>)" class="<?php echo $arsip_btn_class; ?>" title="Tandai Penyelesaian">
                                            <i class="fas <?php echo $arsip_btn_icon; ?>"></i> <?php echo $arsip_btn_text; ?>
                                        </a>
                                        
                                        <a href="periode-edit.php?id=<?php echo $row['periode_id']; ?>" class="btn-edit-periode" title="Edit Data">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        
                                        <a href="#" onclick="konfirmasiHapus(event, <?php echo $row['periode_id']; ?>)" class="btn-delete" title="Hapus Permanen">
                                            <i class="fas fa-trash"></i> Hapus
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #ef4444; font-style: italic; padding: 25px;">Tidak ada periode gelombang PKL yang terdaftar dalam pangkalan data.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    function konfirmasiArsip(e, id, isArchived) {
        e.preventDefault();
        
        let titleTxt = isArchived == 0 ? 'Selesaikan Gelombang?' : 'Buka Kembali Gelombang?';
        let textTxt = isArchived == 0 
            ? 'Pendaftaran akan ditutup otomatis. Kuota lokasi PKL akan dikosongkan (dikembalikan), dan data siswa pada gelombang ini akan dipindahkan ke riwayat Arsip.' 
            : 'Gelombang akan diaktifkan lagi. Data siswa akan dikembalikan ke lokasi PKL asalnya dan otomatis memakan kuota lokasi kembali.';
        let confirmTxt = isArchived == 0 ? 'Ya, Selesaikan & Arsipkan!' : 'Ya, Aktifkan Kembali!';
        let btnColor = isArchived == 0 ? '#f59e0b' : '#8b5cf6'; // Amber / Violet
        
        Swal.fire({
            title: titleTxt,
            text: textTxt,
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: btnColor,
            cancelButtonColor: '#64748b',
            confirmButtonText: confirmTxt,
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `?action=archive&id=${id}`;
            }
        });
    }

    function konfirmasiHapus(e, id) {
        e.preventDefault();
        Swal.fire({
            title: 'PERINGATAN MUTLAK!',
            text: "Yakin ingin menghapus periode gelombang ini? Pastikan tidak ada data siswa aktif yang menempati periode ini.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hapus Permanen!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `?action=delete&id=${id}`;
            }
        });
    }
</script>

<?php include 'panel/footer.php'; ?>
</body>
</html>