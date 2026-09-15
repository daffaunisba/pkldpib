<?php
// admin/siswa-add.php
include 'auth-check.php';
include '../config/db-koneksi.php';

$current_user = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin';
$current_user_id = $_SESSION['user_id'] ?? 0; // Ditambahkan untuk kebutuhan log
$message = '';

// --- 1. PROSES UPDATE/AKTIFKAN AKUN SISWA ---
if (isset($_POST['submit_akun'])) {
    $siswa_id = (int)$_POST['siswa_id'];
    $username = mysqli_real_escape_string($koneksi, trim($_POST['username']));
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    if ($siswa_id > 0 && !empty($username) && !empty($_POST['password'])) {
        
        // --- AMBIL NAMA SISWA UNTUK LOG ---
        $nama_siswa = "ID " . $siswa_id;
        $q_nama = $koneksi->query("SELECT nama FROM peserta_didik WHERE id = '$siswa_id'");
        if ($q_nama && $q_nama->num_rows > 0) {
            $nama_siswa = $q_nama->fetch_assoc()['nama'];
        }

        $query = "UPDATE peserta_didik SET username = '$username', password = '$password' WHERE id = '$siswa_id'";
        if ($koneksi->query($query)) {
            
            // --- TRIGGER LOG AKTIVITAS (AKTIFKAN AKUN) ---
            catatLog($koneksi, $current_user_id, "Mengaktifkan akun login untuk siswa: {$nama_siswa} (Username: {$username})");

            header("Location: siswa-add.php?status=success_activate");
            exit();
        } else {
            $message = "<div class='alert error'>Gagal mengaktifkan akun: " . $koneksi->error . "</div>";
        }
    } else {
        $message = "<div class='alert warning'>Semua kolom pengaktifan akun wajib diisi.</div>";
    }
}

// --- 2. PROSES UPDATE VIA MODAL ---
if (isset($_POST['update_akun'])) {
    $id_edit = (int)$_POST['id_edit'];
    $user_edit = mysqli_real_escape_string($koneksi, trim($_POST['username_edit']));
    
    if ($id_edit > 0 && !empty($user_edit)) {

        // --- AMBIL DATA LAMA UNTUK DETEKSI PERUBAHAN LOG ---
        $nama_siswa = "ID " . $id_edit;
        $old_username = '';
        $q_old = $koneksi->query("SELECT nama, username FROM peserta_didik WHERE id = '$id_edit'");
        if ($q_old && $q_old->num_rows > 0) {
            $dt_old = $q_old->fetch_assoc();
            $nama_siswa = $dt_old['nama'];
            $old_username = $dt_old['username'];
        }

        $perubahan = [];
        if ($old_username != $user_edit) $perubahan[] = "Username";
        if (!empty($_POST['password_edit'])) $perubahan[] = "Password Akun";
        // ----------------------------------------------------

        if (!empty($_POST['password_edit'])) {
            $pass_edit = password_hash($_POST['password_edit'], PASSWORD_DEFAULT);
            $sql = "UPDATE peserta_didik SET username = '$user_edit', password = '$pass_edit' WHERE id = '$id_edit'";
        } else {
            $sql = "UPDATE peserta_didik SET username = '$user_edit' WHERE id = '$id_edit'";
        }

        if ($koneksi->query($sql)) {
            
            // --- TRIGGER LOG AKTIVITAS (UPDATE KREDENSIAL) ---
            if (count($perubahan) > 0) {
                catatLog($koneksi, $current_user_id, "Memperbarui kredensial akun siswa: {$nama_siswa} (Detail yang diubah: " . implode(", ", $perubahan) . ")");
            }

            header("Location: siswa-add.php?status=success_update");
            exit();
        } else {
            $message = "<div class='alert error'>Gagal memperbarui data akun.</div>";
        }
    }
}

// --- 3. PROSES HAPUS AKSES ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_hapus = (int)$_GET['id'];
    if ($id_hapus > 0) {

        // --- AMBIL NAMA SISWA UNTUK LOG ---
        $nama_siswa = "ID " . $id_hapus;
        $q_nama = $koneksi->query("SELECT nama FROM peserta_didik WHERE id = '$id_hapus'");
        if ($q_nama && $q_nama->num_rows > 0) {
            $nama_siswa = $q_nama->fetch_assoc()['nama'];
        }

        $koneksi->query("UPDATE peserta_didik SET username = NULL, password = NULL WHERE id = '$id_hapus'");
        
        // --- TRIGGER LOG AKTIVITAS (HAPUS AKSES) ---
        catatLog($koneksi, $current_user_id, "Mencabut hak akses login untuk siswa: {$nama_siswa}");

        header("Location: siswa-add.php?status=success_delete");
        exit();
    }
}

// Ambil status pesan URL untuk notifikasi SweetAlert2
$status_msg = isset($_GET['status']) ? $_GET['status'] : '';

// Ambil data untuk pangkalan data pilihan form & tabel log
$siswa_baru = $koneksi->query("SELECT p.id, p.nama, p.kelas, l.nama_lokasi FROM peserta_didik p LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id WHERE p.username IS NULL OR p.username = '' ORDER BY p.nama ASC");
$akun_aktif = $koneksi->query("SELECT p.id, p.nama, p.username, p.kelas, l.nama_lokasi FROM peserta_didik p LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id WHERE p.username IS NOT NULL AND p.username != '' ORDER BY p.id DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Manajemen Akses Siswa | Si Mantap PKL</title>
    
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

        .alert { padding: 12px 15px; margin-bottom: 20px; border-radius: 8px; border: 1px solid; font-size: 14px; font-weight: 500; }
        .alert.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .alert.warning { background-color: #fff3cd; color: #856404; border-color: #ffeeba; }

        /* SEARCH BAR STYLE */
        .header-actions-group { display: flex; gap: 10px; align-items: center; }
        .search-wrapper { position: relative; display: flex; align-items: center; }
        .search-wrapper i { position: absolute; left: 15px; color: #64748b; font-size: 14px; }
        .search-input { 
            padding: 9px 15px 9px 38px; border: 1px solid #cbd5e1; border-radius: 20px; 
            font-family: 'Poppins', sans-serif; font-size: 13px; width: 280px; transition: all 0.3s ease; 
            outline: none; background: #f8fafc;
        }
        .search-input:focus { border-color: var(--mantap-blue-main); width: 320px; background: white; box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.15); }

        /* FORM INPUT PANEL BOX */
        .glass-panel-box {
            background: white !important; 
            padding: 25px !important; 
            border-radius: 16px !important; 
            border: 2px solid #e2e8f0 !important; 
            margin-bottom: 30px; 
            box-sizing: border-box;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
            width: 100%;
        }
        .glass-panel-box h2 {
            margin-top: 0;
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--mantap-blue-dark);
            margin-bottom: 20px;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 12px;
        }

        .glass-panel-box label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13.5px; color: #0f172a; text-align: left; }
        .glass-panel-box input[type="text"],
        .glass-panel-box input[type="password"],
        .glass-panel-box select {
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
        .glass-panel-box input:focus, .glass-panel-box select:focus { outline: none; border-color: #1e40af; background-color: white; }

        .form-grid-row { display: flex; gap: 15px; box-sizing: border-box; width: 100%; }
        .form-inner-group { flex: 1; text-align: left; }

        .input-dashed-container {
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            padding: 18px 15px;
            background-color: #f8fafc;
            box-sizing: border-box;
            margin-bottom: 15px;
        }
        .input-dashed-container label { font-size: 13px; font-weight: 700; }
        .input-dashed-container input { margin-bottom: 0 !important; }

        /* BUTTONS */
        .btn-primary-acc {
            background-color: var(--mantap-blue-main);
            color: white;
            padding: 10px 24px;
            border: none;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            box-shadow: 0 4px 6px -1px rgba(30, 64, 175, 0.15);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-primary-acc:hover { background-color: var(--mantap-blue-light); }

        /* TABEL DATA AKUN AKTIF */
        .table-container-fixed { width: 100%; overflow-x: auto; box-sizing: border-box; border: none !important; }
        
        .custom-table-core {
            width: 100%; table-layout: fixed; border-collapse: collapse; background: white; border-radius: 4px; overflow: hidden; border: 2px solid #1e40af; 
        }
        .custom-table-core th {
            background: #1e40af; color: white; padding: 14px 6px; font-size: 13px; text-transform: uppercase; font-weight: 700; text-align: center; border: 1px solid #cbd5e1; box-sizing: border-box;
        }

        .custom-table-core th.w-no { width: 60px; }
        .custom-table-core th.w-siswa { width: 45%; text-align: left; padding-left: 15px; }
        .custom-table-core th.w-kelas { width: 15%; }
        .custom-table-core th.w-user { width: 20%; }
        .custom-table-core th.w-aksi { width: 150px; }

        .custom-table-core td {
            padding: 12px 10px; font-size: 13px; vertical-align: middle; word-wrap: break-word; overflow-wrap: break-word; text-align: center; border: 1px solid #cbd5e1; color: #0f172a; background-color: white !important; box-sizing: border-box;
        }
        .custom-table-core tr:hover td { background-color: #f8fafc !important; }

        .badge-user { 
            background: #e2e8f0; 
            color: var(--mantap-blue-main); 
            font-weight: 700; 
            padding: 4px 10px; 
            border-radius: 4px; 
            font-size: 12px;
            display: inline-block;
        }

        .dashboard-btn-group { display: flex; gap: 6px; justify-content: center; }
        .btn-edit-inline { background-color: #f59e0b; color: white !important; padding: 5px 12px; border-radius: 4px; text-decoration: none; font-size: 11px; font-weight: 600; cursor: pointer; border: none; }
        .btn-edit-inline:hover { background-color: #d97706; }
        .btn-delete-inline { background-color: #dc3545; color: white !important; padding: 5px 12px; border-radius: 4px; text-decoration: none; font-size: 11px; font-weight: 600; display: inline-block; border: none; cursor: pointer; }
        .btn-delete-inline:hover { background-color: #ef4444; }

        /* NATIVE MODAL ENGINE FORM EDIT */
        .mantap-modal {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(15, 23, 42, 0.5); z-index: 999999 !important; backdrop-filter: blur(3px);
        }
        .mantap-modal-dialog { position: relative; width: 90%; max-width: 500px; margin: 5rem auto; box-sizing: border-box; }
        .mantap-modal-content { background-color: white; padding: 25px; border-radius: 16px; border: 2px solid #cbd5e1; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15); }
        .mantap-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; }
        .mantap-modal-header h5 { font-size: 1.3rem; margin: 0; color: #0f172a; font-weight: 700; }
        .close-modal-btn { background: none; border: none; font-size: 1.7rem; color: #64748b; cursor: pointer; line-height: 1; }
        
        .mantap-modal-body label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13.5px; color: #0f172a; text-align: left; }
        .mantap-modal-body input { width: 100%; padding: 11px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 13px; background-color: #f8fafc; margin-bottom: 15px; box-sizing: border-box; }
        .mantap-modal-body input:focus { outline: none; border-color: var(--mantap-blue-main); background-color: white; }
        .btn-modal-submit { background-color: var(--mantap-blue-main); color: white; padding: 11px; border: none; border-radius: 20px; font-weight: 600; font-size: 13.5px; cursor: pointer; font-family: 'Poppins', sans-serif; width: 100%; box-shadow: 0 4px 6px -1px rgba(30, 64, 175, 0.15); }

        .sidebar-nav ul, .sidebar-nav li, .dropdown-menu, .dropdown-menu li { list-style: none !important; margin: 0; padding: 0; }

        /* =========================================================================
           RESPONSIVE VIEWPORT SMARTPHONE (HP)
        ========================================================================= */
        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 

            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; padding: 5px 0px !important; }
            .page-header-controls h1 { font-size: 1.4rem !important; }
            
            .header-actions-group { width: 100%; flex-direction: column; align-items: stretch; gap: 10px; }
            .search-wrapper { width: 100%; }
            .search-input { width: 100%; box-sizing: border-box; }
            .search-input:focus { width: 100%; }

            .glass-panel-box { padding: 18px 14px !important; border-radius: 12px !important; border: 2px solid #cbd5e1 !important; }
            .glass-panel-box h2 { font-size: 1.2rem !important; }
            .form-grid-row { flex-direction: column !important; gap: 0px !important; }
            
            .glass-panel-box input, .glass-panel-box select { font-size: 13.5px !important; padding: 10px !important; }
            .input-dashed-container { padding: 12px 10px !important; }
            .btn-primary-acc { width: 100% !important; justify-content: center !important; padding: 12px !important; border-radius: 8px !important; font-size: 14px !important; }

            .table-container-fixed { display: block !important; width: 100% !important; overflow-x: auto !important; }
            .custom-table-core { table-layout: auto !important; min-width: 780px !important; }
            .custom-table-core th, .custom-table-core td { padding: 12px 10px !important; }
            .custom-table-core th.w-no, .custom-table-core th.w-siswa, .custom-table-core th.w-kelas, .custom-table-core th.w-user, .custom-table-core th.w-aksi { width: auto !important; }
            .custom-table-core td:nth-child(2) { text-align: left !important; }
            
            .dashboard-btn-group { gap: 4px; }
            .btn-edit-inline, .btn-delete-inline { padding: 6px 12px !important; font-size: 12px !important; }
            
            .mantap-modal-dialog { margin: 1.5rem auto; width: 95%; }
            .mantap-modal-content { padding: 16px; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; ?>

<div class="main-content-wrapper">

    <?php include 'panel/navbar.php'; ?>

    <div class="admin-main-content"> 
        
        <div class="page-header-controls">
            <h1>Manajemen Akses Siswa</h1>
            <div class="header-actions-group">
                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchSiswa" class="search-input" placeholder="Cari Siswa / Kelas / Username..." onkeyup="filterSiswa()">
                </div>
            </div>
        </div>

        <?php echo $message; ?>

        <div class="glass-panel-box">
            <h2><i class="fas fa-user-plus" style="color: var(--mantap-blue-main); margin-right: 4px;"></i> Aktifkan Akun Login Siswa Baru</h2>
            <form method="POST" action="siswa-add.php">
                <div class="form-grid-row">
                    <div class="form-inner-group">
                        <label for="siswa_select">Pilih Nama Siswa:</label>
                        <select name="siswa_id" id="siswa_select" required onchange="updateInfo()">
                            <option value="">-- Cari Nama Siswa --</option>
                            <?php while($s = $siswa_baru->fetch_assoc()): ?>
                                <option value="<?= $s['id']; ?>" data-kelas="<?= $s['kelas']; ?>" data-lokasi="<?= $s['nama_lokasi'] ?? 'Belum Ditentukan'; ?>">
                                    <?= htmlspecialchars($s['nama']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-inner-group">
                        <label for="view_kelas">Kelas / Jurusan:</label>
                        <input type="text" id="view_kelas" readonly style="background-color: #f8fafc; color: #64748b; font-weight: 500;">
                    </div>
                    <div class="form-inner-group">
                        <label for="view_lokasi">Lokasi Tempat PKL:</label>
                        <input type="text" id="view_lokasi" readonly style="background-color: #f8fafc; color: #64748b; font-weight: 500;">
                    </div>
                </div>

                <div class="form-grid-row" style="margin-top: 5px;">
                    <div class="form-inner-group">
                        <div class="input-dashed-container">
                            <label for="username" style="color: var(--mantap-blue-main);">Username Otorisasi:</label>
                            <input type="text" id="username" name="username" placeholder="Masukkan nama pengguna..." required>
                        </div>
                    </div>
                    <div class="form-inner-group">
                        <div class="input-dashed-container" style="border-color: #fecaca; background-color: #ffffff;">
                            <label for="password" style="color: #dc3545;">Kunci Password Default:</label>
                            <input type="password" id="password" name="password" placeholder="Masukkan sandi masuk rahasia..." required>
                        </div>
                    </div>
                </div>

                <div style="text-align: right; margin-top: 5px;">
                    <button type="submit" name="submit_akun" class="btn-primary-acc">
                        <i class="fas fa-check-circle"></i> Otorisasikan Akun Siswa
                    </button>
                </div>
            </form>
        </div>

        <div class="glass-panel-box">
            <h2>Daftar Akun Siswa Aktif</h2>
            
            <div class="table-container-fixed">
                <table class="custom-table-core" id="tabelSiswa">
                    <thead>
                        <tr>
                            <th class="w-no">NO</th>
                            <th class="w-siswa" style="text-align: left; padding-left: 15px;">Nama Lengkap Siswa</th>
                            <th class="w-kelas">Kelas</th>
                            <th class="w-user">Username</th>
                            <th class="w-aksi">Aksi Operasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no=1; if($akun_aktif->num_rows > 0): while($row = $akun_aktif->fetch_assoc()): ?>
                        <tr class="data-row">
                            <td style="font-weight: 700; text-align: center; color:#64748b;"><?= $no++; ?></td>
                            <td style="text-align: left; padding-left: 15px;">
                                <div style="font-weight: 700; color: var(--mantap-blue-dark); text-transform: uppercase;"><?= htmlspecialchars($row['nama']); ?></div>
                                <small style="color: #64748b; font-weight: 500; font-size: 11.5px;"><i class="fas fa-building me-1 opacity-50"></i><?= htmlspecialchars($row['nama_lokasi'] ?? 'Belum Pilih Industri'); ?></small>
                            </td>
                            <td style="font-weight: 600;"><?= htmlspecialchars($row['kelas']); ?></td>
                            <td><span class="badge-user"><?= htmlspecialchars($row['username']); ?></span></td>
                            <td>
                                <div class="dashboard-btn-group">
                                    <button type="button" class="btn-edit-inline" 
                                            onclick="openEditModal('<?= $row['id']; ?>', '<?= htmlspecialchars($row['username'], ENT_QUOTES); ?>', '<?= htmlspecialchars($row['nama'], ENT_QUOTES); ?>')">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button type="button" class="btn-delete-inline" onclick="konfirmasiHapusAkses('<?= $row['id']; ?>')">
                                        <i class="fas fa-unlink"></i> Putus
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="5" style="text-align: center; color: #ef4444; font-style: italic; padding: 25px;">Belum ada data kredensial akun siswa yang aktif di dalam sistem.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<div class="mantap-modal" id="modalEdit">
    <div class="mantap-modal-dialog">
        <div class="mantap-modal-content">
            <div class="mantap-modal-header">
                <h5><i class="fas fa-user-edit" style="color: #f59e0b; margin-right: 6px;"></i> Perbarui Kredensial Akun</h5>
                <button type="button" class="close-modal-btn" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" action="siswa-add.php">
                <div class="mantap-modal-body">
                    <p style="font-size: 13px; color: #64748b; margin-top: 0; margin-bottom: 15px; text-align: left; background-color: var(--mantap-blue-soft); padding: 10px; border-left: 4px solid var(--mantap-blue-main); border-radius: 4px;">Siswa: <span id="nama_siswa_modal" style="font-weight: 700; color: var(--mantap-blue-dark);"></span></p>
                    
                    <input type="hidden" name="id_edit" id="id_edit">
                    
                    <label for="username_edit">Username Pengguna Baru:</label>
                    <input type="text" name="username_edit" id="username_edit" required>
                    
                    <label for="password_edit" style="color:#ef4444;">Ganti Sandi Password (Opsional):</label>
                    <input type="password" name="password_edit" id="password_edit" placeholder="Kosongkan jika sandi lama tidak diubah">
                </div>
                <div style="padding-top: 5px;">
                    <button type="submit" name="update_akun" class="btn-modal-submit" style="background-color: #f59e0b; box-shadow: 0 4px 6px -1px rgba(245, 158, 11, 0.15);"><i class="fas fa-save"></i> Simpan Perubahan Akun</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'panel/footer.php'; ?>

<script>
function updateInfo() {
    const sel = document.getElementById('siswa_select');
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('view_kelas').value = opt.getAttribute('data-kelas') || "";
    document.getElementById('view_lokasi').value = opt.getAttribute('data-lokasi') || "";
}

const editModal = document.getElementById('modalEdit');

function openEditModal(id, user, nama) {
    document.getElementById('id_edit').value = id;
    document.getElementById('username_edit').value = user;
    document.getElementById('nama_siswa_modal').innerText = nama;
    editModal.style.display = 'block';
}

function closeEditModal() {
    editModal.style.display = 'none';
}

// Handler notifikasi terintegrasi SweetAlert2 berbasis URL status parameter (PRG Pattern)
document.addEventListener("DOMContentLoaded", function() {
    const statusMessage = "<?php echo $status_msg; ?>";
    if (statusMessage === "success_activate") {
        Swal.fire({ icon: 'success', title: 'Akun Aktif!', text: 'Kredensial login siswa berhasil dibuat.', confirmButtonColor: '#1e40af' });
    } else if (statusMessage === "success_update") {
        Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Username atau password siswa telah diperbarui.', confirmButtonColor: '#1e40af' });
    } else if (statusMessage === "success_delete") {
        Swal.fire({ icon: 'success', title: 'Akses Dicabut!', text: 'Hak login masuk siswa tersebut berhasil dinonaktifkan.', confirmButtonColor: '#1e40af' });
    }

    window.addEventListener('click', (e) => { if (e.target === editModal) { closeEditModal(); } });
});

// Fungsi SweetAlert2 Terstandarisasi untuk Validasi Pencabutan Akses
function konfirmasiHapusAkses(id) {
    Swal.fire({
        title: 'Cabut Hak Akses?',
        text: "Username dan password siswa ini akan dihapus. Murid tidak dapat masuk ke sistem absensi sampai diaktifkan kembali.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ya, Putuskan!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `siswa-add.php?action=delete&id=${id}`;
        }
    });
}

// --- FITUR PENCARIAN REAL-TIME ---
function filterSiswa() {
    let input = document.getElementById("searchSiswa");
    let filter = input.value.toUpperCase();
    let table = document.getElementById("tabelSiswa");
    
    if (!table) return; 
    let tr = table.getElementsByClassName("data-row");

    for (let i = 0; i < tr.length; i++) {
        let tdNamaLokasi = tr[i].getElementsByTagName("td")[1]; 
        let tdKelas = tr[i].getElementsByTagName("td")[2]; 
        let tdUser = tr[i].getElementsByTagName("td")[3]; 
        
        if (tdNamaLokasi || tdKelas || tdUser) {
            let txtValueNamaLokasi = tdNamaLokasi.textContent || tdNamaLokasi.innerText;
            let txtValueKelas = tdKelas.textContent || tdKelas.innerText;
            let txtValueUser = tdUser.textContent || tdUser.innerText;
            
            if (txtValueNamaLokasi.toUpperCase().indexOf(filter) > -1 || 
                txtValueKelas.toUpperCase().indexOf(filter) > -1 || 
                txtValueUser.toUpperCase().indexOf(filter) > -1) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }
}
</script>
</body>
</html>