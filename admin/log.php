<?php
// admin/log.php
include 'auth-check.php';
include '../config/db-koneksi.php';

// Generate CSRF Token untuk keamanan fitur hapus
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$current_user = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin';
$current_username_raw = isset($_SESSION['username']) ? strtolower($_SESSION['username']) : '';
$current_user_id = $_SESSION['user_id'] ?? 0;
$user_level   = isset($_SESSION['level']) ? strtolower($_SESSION['level']) : 'pembimbing';

// KUNCIAN MUTLAK: Hanya yang memiliki username secara spesifik "admin" yang boleh menghapus
$is_super_admin = ($current_username_raw === 'admin');

// ---------------------------------------------------------------------
// 1. FILTER TANGGAL (Default: Hari Ini)
// ---------------------------------------------------------------------
$filter_tgl = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
// Validasi format tanggal (YYYY-MM-DD) untuk keamanan
if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $filter_tgl)) {
    $filter_tgl = date('Y-m-d');
}

// ---------------------------------------------------------------------
// 2. LOGIKA HAPUS LOG (HANYA UNTUK USERNAME 'admin')
// ---------------------------------------------------------------------
if (isset($_GET['action']) && isset($_GET['id']) && isset($_GET['token'])) {
    if ($_GET['token'] === $_SESSION['csrf_token']) {
        if ($is_super_admin) {
            $id_hapus = (int)$_GET['id'];
            
            if ($_GET['action'] == 'delete_log_pengelola') {
                $stmt_del = $koneksi->prepare("DELETE FROM log_aktivitas WHERE id = ?");
                $stmt_del->bind_param("i", $id_hapus);
                $stmt_del->execute();
                $stmt_del->close();
                header("Location: log.php?status=success_delete&tanggal=" . $filter_tgl);
                exit;
            } elseif ($_GET['action'] == 'delete_log_siswa') {
                $stmt_del = $koneksi->prepare("DELETE FROM log_aktivitas_login_siswa WHERE id = ?");
                $stmt_del->bind_param("i", $id_hapus);
                $stmt_del->execute();
                $stmt_del->close();
                header("Location: log.php?status=success_delete&tanggal=" . $filter_tgl);
                exit;
            }
        } else {
            // Jika bukan username admin yang mencoba akses link delete
            header("Location: log.php?status=denied&tanggal=" . $filter_tgl);
            exit;
        }
    }
}

$guru_filter_id = 0;

// ---------------------------------------------------------------------
// 3. CEK RELASI GURU (Jika yang login pembimbing)
// ---------------------------------------------------------------------
if ($user_level == 'pembimbing' || $user_level == 'guru') {
    $stmt_get_guru_id = $koneksi->prepare("SELECT guru_id FROM guru WHERE user_id = ?");
    if ($stmt_get_guru_id) {
        $stmt_get_guru_id->bind_param("i", $current_user_id);
        $stmt_get_guru_id->execute();
        $result_guru_id = $stmt_get_guru_id->get_result();
        if ($result_guru_id->num_rows > 0) {
            $guru_filter_id = $result_guru_id->fetch_assoc()['guru_id'];
        }
        $stmt_get_guru_id->close();
    }
}

// ---------------------------------------------------------------------
// 4. QUERY DATA LOG KIRI: Aktivitas Sistem (Berdasarkan Filter)
// ---------------------------------------------------------------------
$query_sistem = "
    SELECT 
        l.*, u.full_name, u.role, u.profile_photo
    FROM log_aktivitas l
    JOIN users u ON l.user_id = u.id
    WHERE DATE(l.waktu) = '$filter_tgl'
    ORDER BY l.waktu DESC
";
$log_sistem_data = $koneksi->query($query_sistem);

// Statistik Sistem
$stats_today = $koneksi->query("SELECT COUNT(*) as total FROM log_aktivitas WHERE DATE(waktu) = '$filter_tgl'")->fetch_assoc();

// ---------------------------------------------------------------------
// 5. QUERY DATA LOG KANAN: Aktivitas Masuk Siswa (Berdasarkan Filter)
// ---------------------------------------------------------------------
$query_siswa = "
    SELECT 
        log_s.*, p.kelas, l_pkl.nama_lokasi
    FROM log_aktivitas_login_siswa log_s
    LEFT JOIN peserta_didik p ON log_s.siswa_id = p.id
    LEFT JOIN lokasi_pkl l_pkl ON p.lokasi_id = l_pkl.lokasi_id
    WHERE DATE(log_s.waktu_login) = '$filter_tgl'
    ORDER BY log_s.waktu_login DESC
";
$log_siswa_data = $koneksi->query($query_siswa);

// Statistik Siswa
$stats_siswa_today = $koneksi->query("SELECT COUNT(*) as total FROM log_aktivitas_login_siswa WHERE DATE(waktu_login) = '$filter_tgl'")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pusat Log Sistem - Si Mantap PKL</title>
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

        body { font-family: 'Poppins', sans-serif; background-color: #f8fafc; color: #334155; margin: 0; }
        .main-content-wrapper { max-width: 100% !important; width: 100% !important; box-sizing: border-box !important; box-shadow: none !important; }
        .admin-main-content { padding: 20px 25px 30px 25px !important; box-sizing: border-box !important; clear: both; width: 100% !important; }

        /* HEADER LAYOUT: KIRI JUDUL, KANAN FILTER (Inline & Clean) */
        .dashboard-header {
            display: flex; justify-content: space-between; align-items: flex-end; 
            margin-bottom: 25px; width: 100%; flex-wrap: wrap; gap: 15px;
        }

        .header-title-wrapper h1 { 
            font-weight: 800; color: var(--mantap-blue-dark); font-size: 1.8rem; margin: 0; 
            position: relative; padding-bottom: 8px; display: inline-block; white-space: nowrap;
        }
        .header-title-wrapper h1::after { 
            content: ''; position: absolute; left: 0; bottom: 0; width: 50px; height: 4px; 
            background: var(--mantap-blue-main); border-radius: 2px; 
        }
        .header-title-wrapper p { color: #64748b; margin: 8px 0 0; font-size: 13.5px; font-weight: 500;}

        /* FILTER AREA KANAN */
        .header-actions-group { 
            display: flex; flex-direction: row; gap: 10px; align-items: center; flex-wrap: nowrap; 
        }
        
        .inline-filter-form { 
            display: inline-flex; align-items: center; gap: 8px; margin: 0; padding: 0; 
            background: transparent; border: none; box-shadow: none; flex-wrap: nowrap;
        }

        .inline-filter-form input[type="date"] { 
            padding: 9px 15px; border: 1px solid #cbd5e1; border-radius: 20px; 
            font-family: 'Poppins', sans-serif; font-size: 13px; font-weight: 600; 
            color: #334155; background-color: white; box-shadow: 0 2px 4px rgba(0,0,0,0.02); 
            outline: none; transition: 0.2s; min-width: 140px;
        }
        .inline-filter-form input[type="date"]:focus { 
            border-color: var(--mantap-blue-main); box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.15); 
        }

        .btn-filter-acc { 
            background-color: #2563eb; color: white; padding: 9px 18px; border: none; 
            border-radius: 20px; font-weight: 600; font-size: 12.5px; font-family: 'Poppins', sans-serif; 
            cursor: pointer; display: flex; align-items: center; gap: 6px; 
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.15); transition: 0.2s; white-space: nowrap;
        }
        .btn-filter-acc:hover { background-color: #1d4ed8; transform: translateY(-1px); }

        .btn-reset-filter { 
            background-color: #64748b; color: white !important; padding: 9px 18px; border: none; 
            border-radius: 20px; font-weight: 600; font-size: 12.5px; font-family: 'Poppins', sans-serif; 
            cursor: pointer; display: flex; align-items: center; gap: 6px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: 0.2s; white-space: nowrap; text-decoration: none;
        }
        .btn-reset-filter:hover { background-color: #475569; transform: translateY(-1px); }

        /* KPI GRID STATS */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 25px; width: 100%; box-sizing: border-box; }
        .stat-card {
            background: white; padding: 20px; border-radius: 12px; display: flex; align-items: center; gap: 15px; box-sizing: border-box; border: 2px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); transition: 0.3s;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 15px rgba(0,0,0,0.05); }
        .stat-icon { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.4rem; flex-shrink: 0; }

        /* SPLIT LAYOUT DESKTOP */
        .log-split-layout { display: grid; grid-template-columns: 1.2fr 1fr; gap: 25px; width: 100%; box-sizing: border-box; }
        .table-card {
            background: white !important; border-radius: 16px !important; padding: 25px !important; border: 2px solid #e2e8f0 !important; box-sizing: border-box; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); width: 100%; min-width: 0; overflow: hidden;
        }
        .table-card h3 { margin-top: 0; margin-bottom: 20px; font-weight: 700; color: var(--mantap-blue-dark); display: flex; align-items: center; gap: 8px; font-size: 1.2rem; border-bottom: 2px solid #f1f5f9; padding-bottom: 12px; }
        .table-container-fixed { width: 100%; overflow-x: auto; box-sizing: border-box; border: none !important; max-height: 600px; overflow-y: auto;}

        /* OUTLINE TEBAL BERWARNA BIRU UTAMA */
        .table-card table { width: 100%; border-collapse: collapse; background: white; border-radius: 4px; overflow: hidden; border: 2px solid #1e40af; }
        .table-card table th { background: #1e40af; color: white; padding: 12px 8px; font-size: 12px; text-transform: uppercase; font-weight: 700; text-align: center; border: 1px solid #cbd5e1; box-sizing: border-box; white-space: nowrap; position: sticky; top: 0; z-index: 2;}
        .table-card table td { padding: 12px 8px; border: 1px solid #cbd5e1; font-size: 13px; vertical-align: middle; text-align: center; background-color: white !important; }
        .table-card tbody tr:hover td { background-color: #f8fafc !important; }

        .user-pill { display: flex; align-items: center; gap: 10px; text-align: left; }
        .user-pill img { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; background: #eee; flex-shrink: 0; border: 2px solid #e2e8f0; }
        
        .role-badge { padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 700; text-transform: uppercase; display: inline-block; margin-top: 2px; }
        .role-admin { background: var(--mantap-blue-soft); color: var(--mantap-blue-main); }
        .role-guru { background: #dcfce7; color: #15803d; }

        .time-text { color: #94a3b8; font-size: 11px; margin-top: 2px; white-space: nowrap; }
        .action-text { font-weight: 700; color: var(--mantap-blue-dark); white-space: nowrap; }
        .device-text { font-size: 11px; color: #64748b; max-width: 150px; display: inline-block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; vertical-align: middle; }
        .badge-info-pill { background-color: #f1f5f9; color: #475569; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block; white-space: nowrap; }

        /* Tombol Hapus Log Eksklusif Admin */
        .btn-delete-log {
            background-color: #fee2e2; color: #ef4444; border: 1px solid #fca5a5;
            padding: 6px 10px; border-radius: 6px; cursor: pointer; transition: 0.2s;
        }
        .btn-delete-log:hover { background-color: #ef4444; color: white; border-color: #ef4444; }

        @media (max-width: 768px) {
            body { padding-top: 60px !important; }
            .admin-main-content { padding: 15px 12px 25px 12px !important; }
            
            .dashboard-header { flex-direction: column !important; align-items: flex-start !important; gap: 15px; }
            .header-title-wrapper h1 { font-size: 1.4rem !important; white-space: normal;}
            
            .header-actions-group { width: 100%; flex-direction: column !important; align-items: stretch !important; gap: 10px !important; }
            .inline-filter-form { width: 100% !important; display: flex !important; flex-direction: row !important; gap: 8px !important; flex-wrap: wrap !important;}
            .inline-filter-form input[type="date"] { flex: 1 1 100% !important; width: 100% !important; box-sizing: border-box !important; }
            .btn-filter-acc, .btn-reset-filter { flex: 1; justify-content: center !important; }

            .stats-grid { grid-template-columns: 1fr !important; gap: 10px !important; }
            .stat-card { padding: 14px !important; border-radius: 10px !important; }
            .log-split-layout { grid-template-columns: 1fr !important; gap: 20px !important; }
            .table-card { padding: 16px 10px !important; border-radius: 12px !important; border: 2px solid #cbd5e1 !important; }
            .table-card h3 { font-size: 1.1rem !important; margin-bottom: 15px; }
            .table-container-fixed { display: block !important; width: 100% !important; overflow-x: auto !important; -webkit-overflow-scrolling: touch; max-height: 400px;}
            .table-card table { table-layout: auto !important; min-width: 580px !important; }
            .table-card table th, .table-card table td { padding: 10px 8px !important; font-size: 12px !important;}
            .table-card table td:nth-child(2), .table-card table td:nth-child(3) { text-align: left !important; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; ?>

<div class="main-content-wrapper">
    
    <?php include 'panel/navbar.php'; ?>

    <div class="admin-main-content">
        
        <!-- HEADER KONTROL: KIRI JUDUL, KANAN FILTER INLINE -->
        <div class="dashboard-header">
            <div class="header-title-wrapper">
                <h1>Pusat Monitor Aktivitas</h1>
                <p>Memantau riwayat tindakan pengelola sistem dan akses masuk siswa.</p>
            </div>
            
            <div class="header-actions-group">
                <form method="GET" action="log.php" class="inline-filter-form">
                    <input type="date" name="tanggal" value="<?php echo htmlspecialchars($filter_tgl); ?>" required>
                    <button type="submit" class="btn-filter-acc">
                        <i class="fas fa-filter"></i> Saring Data
                    </button>
                    <a href="log.php" class="btn-reset-filter" title="Reset ke hari ini">
                        <i class="fas fa-sync-alt"></i> Reset
                    </a>
                </form>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--mantap-blue-main);"><i class="fas fa-user-shield"></i></div>
                <div>
                    <small style="color: #64748b; display: block; font-size: 12px; font-weight: 500;">Aksi Pengelola (<?= date('d M Y', strtotime($filter_tgl)) ?>)</small>
                    <strong style="font-size: 1.3rem; color: var(--mantap-blue-dark);"><?php echo $stats_today['total']; ?></strong>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #22c55e;"><i class="fas fa-user-graduate"></i></div>
                <div>
                    <small style="color: #64748b; display: block; font-size: 12px; font-weight: 500;">Aksi Murid (<?= date('d M Y', strtotime($filter_tgl)) ?>)</small>
                    <strong style="font-size: 1.3rem; color: var(--mantap-blue-dark);"><?php echo $stats_siswa_today['total']; ?></strong>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #0ea5e9;"><i class="fas fa-server"></i></div>
                <div>
                    <small style="color: #64748b; display: block; font-size: 12px; font-weight: 500;">Status Server</small>
                    <strong style="font-size: 1.3rem; color: #22c55e;">ONLINE</strong>
                </div>
            </div>
        </div>

        <div class="log-split-layout">
            
            <div class="table-card">
                <h3><i class="fas fa-user-cog" style="color: var(--mantap-blue-main);"></i> Log Aktivitas Pengelola</h3>
                <div class="table-container-fixed">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 20%;">Waktu</th>
                                <th style="width: 40%; text-align: left; padding-left: 10px;">Staf / Pengguna</th>
                                <th style="width: <?php echo ($is_super_admin) ? '30%' : '40%'; ?>; text-align: left; padding-left: 10px;">Tindakan Operasi</th>
                                <?php if ($is_super_admin): ?>
                                    <th style="width: 10%;">Aksi</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($log_sistem_data && $log_sistem_data->num_rows > 0): ?>
                                <?php while ($row = $log_sistem_data->fetch_assoc()): 
                                    $role_class = ($row['role'] == 'admin') ? 'role-admin' : 'role-guru';
                                    $foto = !empty($row['profile_photo']) ? '../uploads/profiles/'.$row['profile_photo'] : '../img/default-profile.png';
                                ?>
                                <tr>
                                    <td>
                                        <div class="action-text"><?php echo date('d M Y', strtotime($row['waktu'])); ?></div>
                                        <div class="time-text"><?php echo date('H:i:s', strtotime($row['waktu'])); ?> WIB</div>
                                    </td>
                                    <td>
                                        <div class="user-pill">
                                            <img src="<?php echo $foto; ?>" alt="User">
                                            <div>
                                                <div style="font-weight: 700; font-size:12.5px; color: var(--mantap-blue-dark);"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                                <span class="role-badge <?php echo $role_class; ?>"><?php echo strtoupper($row['role']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="text-align: left; padding-left: 10px;">
                                        <div style="background: #f8fafc; padding: 8px 10px; border-radius: 6px; border-left: 3px solid var(--mantap-blue-main); font-size:12px; font-weight: 500; line-height: 1.4; color: #334155;">
                                            <?php echo htmlspecialchars($row['aksi']); ?>
                                        </div>
                                        <small style="font-family: monospace; color: #94a3b8; font-size:10px; display: block; margin-top: 4px;">IP: <?php echo $row['ip_address'] ?? '127.0.0.1'; ?></small>
                                    </td>
                                    <?php if ($is_super_admin): ?>
                                        <td>
                                            <button type="button" class="btn-delete-log" title="Hapus Log" onclick="konfirmasiHapusLog('pengelola', <?php echo $row['id']; ?>, '<?php echo $_SESSION['csrf_token']; ?>')">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo ($is_super_admin) ? '4' : '3'; ?>" style="text-align: center; padding: 40px; color: #64748b; font-style: italic;">
                                        <i class="fas fa-folder-open mb-2 opacity-50" style="font-size: 2rem; display: block;"></i>
                                        Belum ada riwayat aktivitas pengelola yang terekam pada tanggal ini.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="table-card">
                <h3><i class="fas fa-history" style="color: #22c55e;"></i> Log Akses Masuk Murid</h3>
                <div class="table-container-fixed">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 25%;">Waktu Akses</th>
                                <th style="width: <?php echo ($is_super_admin) ? '35%' : '45%'; ?>; text-align: left; padding-left: 10px;">Identitas Siswa</th>
                                <th style="width: 30%; text-align: left; padding-left: 10px;">Keterangan Alat</th>
                                <?php if ($is_super_admin): ?>
                                    <th style="width: 10%;">Aksi</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($log_siswa_data && $log_siswa_data->num_rows > 0): ?>
                                <?php while ($siswa_row = $log_siswa_data->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="action-text" style="color:#10b981; font-weight: 700;"><?php echo date('d M Y', strtotime($siswa_row['waktu_login'])); ?></div>
                                        <div class="time-text"><?php echo date('H:i:s', strtotime($siswa_row['waktu_login'])); ?> WIB</div>
                                        <span style="font-size:9.5px; background:var(--mantap-blue-soft); color:var(--mantap-blue-main); padding:2px 6px; border-radius:4px; font-weight:700; display: inline-block; margin-top: 4px;"><?php echo strtoupper($siswa_row['metode_login']); ?></span>
                                    </td>
                                    <td style="text-align: left; padding-left: 10px;">
                                        <div style="font-weight: 700; font-size: 12.5px; color: var(--mantap-blue-dark);"><?php echo htmlspecialchars($siswa_row['nama_siswa'] ?? 'Siswa Terhapus'); ?></div>
                                        <span class="badge-info-pill" style="margin-top: 2px; margin-bottom: 3px;"><?php echo htmlspecialchars($siswa_row['kelas'] ?? '-'); ?></span>
                                        <div style="font-size:11px; color:#64748b; text-overflow:ellipsis; white-space:nowrap; overflow:hidden; max-width:160px;" title="<?php echo htmlspecialchars($siswa_row['nama_lokasi'] ?? ''); ?>">
                                            <i class="fas fa-map-marker-alt" style="font-size:10px; color: #94a3b8;"></i> <?php echo htmlspecialchars($siswa_row['nama_lokasi'] ?? 'Belum Pilih Lokasi'); ?>
                                        </div>
                                    </td>
                                    <td style="text-align: left; padding-left: 10px;">
                                        <span class="device-text" title="<?php echo htmlspecialchars($siswa_row['user_agent']); ?>">
                                            <?php echo htmlspecialchars($siswa_row['user_agent']); ?>
                                        </span>
                                        <small style="font-family: monospace; color:#94a3b8; display: block; margin-top: 2px;">IP: <?php echo htmlspecialchars($siswa_row['ip_address']); ?></small>
                                    </td>
                                    <?php if ($is_super_admin): ?>
                                        <td>
                                            <button type="button" class="btn-delete-log" title="Hapus Log" onclick="konfirmasiHapusLog('siswa', <?php echo $siswa_row['id']; ?>, '<?php echo $_SESSION['csrf_token']; ?>')">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo ($is_super_admin) ? '4' : '3'; ?>" style="text-align: center; padding: 40px; color: #64748b; font-style: italic;">
                                        <i class="fas fa-folder-open mb-2 opacity-50" style="font-size: 2rem; display: block;"></i>
                                        Belum ada aktivitas login murid yang terekam pada tanggal ini.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    <?php if (isset($_GET['status'])): ?>
    document.addEventListener("DOMContentLoaded", function() {
        const statusMsg = "<?php echo htmlspecialchars($_GET['status']); ?>";

        if (statusMsg === 'success_delete') {
            Swal.fire({
                icon: 'success',
                title: 'Berhasil Dihapus!',
                text: 'Data log telah dihapus permanen.',
                confirmButtonColor: '#1e40af',
                timer: 2000,
                showConfirmButton: false
            });
            // Bersihkan URL dari parameter status
            window.history.replaceState(null, null, window.location.pathname + "?tanggal=<?php echo htmlspecialchars($filter_tgl); ?>");
        } else if (statusMsg === 'denied') {
            Swal.fire({
                icon: 'error',
                title: 'Akses Ditolak!',
                text: 'Hanya pengguna dengan username "admin" (Admin Utama) yang berhak menghapus log.',
                confirmButtonColor: '#dc3545'
            });
            window.history.replaceState(null, null, window.location.pathname + "?tanggal=<?php echo htmlspecialchars($filter_tgl); ?>");
        }
    });
    <?php endif; ?>

    // Fungsi konfirmasi hapus menggunakan SweetAlert2
    function konfirmasiHapusLog(jenis, id, token) {
        let actionTxt = jenis === 'pengelola' ? 'delete_log_pengelola' : 'delete_log_siswa';
        Swal.fire({
            title: 'Hapus Log Ini?',
            text: "Data riwayat ini akan dihapus permanen dari sistem dan tidak dapat dipulihkan.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `log.php?action=${actionTxt}&id=${id}&token=${token}&tanggal=<?php echo htmlspecialchars($filter_tgl); ?>`;
            }
        });
    }
</script>

<?php include 'panel/footer.php'; ?>