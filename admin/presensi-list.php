<?php
// admin/presensi-list.php
include 'auth-check.php';
include '../config/db-koneksi.php';

$current_user = isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin';
$current_page = basename(__FILE__);
$user_level   = isset($_SESSION['level']) ? strtolower($_SESSION['level']) : 'pembimbing';
$current_user_id = $_SESSION['user_id'] ?? 0;

// --- SINKRONISASI LEVEL USER: Dapatkan guru_id jika pembimbing ---
$guru_filter_id = 0;
$filter_pembimbing_clause = "";

if ($user_level == 'pembimbing' || $user_level == 'guru') {
    $stmt_get_guru_id = $koneksi->prepare("SELECT guru_id FROM guru WHERE user_id = ?");
    if ($stmt_get_guru_id) {
        $stmt_get_guru_id->bind_param("i", $current_user_id);
        $stmt_get_guru_id->execute();
        $result_guru_id = $stmt_get_guru_id->get_result();
        if ($result_guru_id->num_rows > 0) {
            $guru_filter_id = $result_guru_id->fetch_assoc()['guru_id'];
            $filter_pembimbing_clause = " AND l.guru_id = {$guru_filter_id} ";
        }
        $stmt_get_guru_id->close();
    }
}

// --- Sanitasi & Validasi Input ---
$filter_tgl = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_tgl)) {
    $filter_tgl = date('Y-m-d');
}

$date_check = DateTime::createFromFormat('Y-m-d', $filter_tgl);
if (!$date_check || $date_check->format('Y-m-d') !== $filter_tgl) {
    $filter_tgl = date('Y-m-d');
}

// --- CEK TANGGAL MERAH DARI TABEL HARI LIBUR ---
$cek_libur = $koneksi->query("SELECT keterangan FROM hari_libur WHERE tanggal_libur = '$filter_tgl'");
$ket_libur_nasional = ($cek_libur && $cek_libur->num_rows > 0) ? $cek_libur->fetch_assoc()['keterangan'] : null;
$is_libur_nasional = !empty($ket_libur_nasional);

// --- Pagination Setup ---
$limit = 150;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// --- Query Data Presensi Utama ---
$query = "
    SELECT 
        pr.*, 
        p.nama AS nama_siswa, 
        p.kelas,
        l.nama_lokasi, 
        l.jam_kerja,
        l.latitude AS lat_target, 
        l.longitude AS lng_target,
        (SELECT jenis_izin FROM pengajuan_izin 
         WHERE siswa_id = p.id AND status = 'Disetujui' 
         AND ? BETWEEN tgl_mulai AND tgl_selesai LIMIT 1) AS status_perizinan,
        (SELECT file_pendukung FROM pengajuan_izin 
         WHERE siswa_id = p.id AND status = 'Disetujui' 
         AND ? BETWEEN tgl_mulai AND tgl_selesai LIMIT 1) AS berkas_perizinan
    FROM peserta_didik p
    JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
    LEFT JOIN presensi_pkl pr ON p.id = pr.siswa_id AND pr.tanggal = ?
    WHERE (
        pr.siswa_id IS NOT NULL 
        OR EXISTS (
            SELECT 1 FROM pengajuan_izin 
            WHERE siswa_id = p.id AND status = 'Disetujui' 
            AND ? BETWEEN tgl_mulai AND tgl_selesai
        )
    ) {$filter_pembimbing_clause}
    ORDER BY COALESCE(pr.jam_masuk, '00:00:00') DESC
    LIMIT ? OFFSET ?
";

$stmt = $koneksi->prepare($query);
if (!$stmt) {
    die("Prepare failed: " . $koneksi->error);
}

$stmt->bind_param("ssssii", $filter_tgl, $filter_tgl, $filter_tgl, $filter_tgl, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

// --- Hitung Total Records ---
$count_query = "
    SELECT COUNT(*) as total 
    FROM peserta_didik p
    JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
    LEFT JOIN presensi_pkl pr ON p.id = pr.siswa_id AND pr.tanggal = ?
    WHERE (
        pr.siswa_id IS NOT NULL 
        OR EXISTS (
            SELECT 1 FROM pengajuan_izin 
            WHERE siswa_id = p.id AND status = 'Disetujui' 
            AND ? BETWEEN tgl_mulai AND tgl_selesai
        )
    ) {$filter_pembimbing_clause}
";
$count_stmt = $koneksi->prepare($count_query);
$count_stmt->bind_param("ss", $filter_tgl, $filter_tgl);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$count_row = $count_result->fetch_assoc();
$total_records = $count_row['total'] ?? 0;
$total_pages = ceil($total_records / $limit);

// --- PERBAIKAN LOGIKA HITUNG STATISTIK REAL-TIME ---
$stat_hadir = 0;
$stat_telat = 0;

$stat_total_hari_ini = $koneksi->query("
    SELECT pr.jam_masuk, l.jam_kerja 
    FROM presensi_pkl pr 
    JOIN peserta_didik p ON pr.siswa_id = p.id 
    JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id 
    WHERE pr.tanggal = '$filter_tgl' {$filter_pembimbing_clause}
");

if ($stat_total_hari_ini) {
    while ($st_row = $stat_total_hari_ini->fetch_assoc()) {
        if (!empty($st_row['jam_masuk']) && !empty($st_row['jam_kerja'])) {
            $raw_target = trim(explode('s.d.', $st_row['jam_kerja'])[0]);
            $time_t = strtotime(str_replace('.', ':', $raw_target));
            $time_s = strtotime(str_replace('.', ':', trim($st_row['jam_masuk'])));
            
            if ($time_t && $time_s) {
                if (($time_s - $time_t) > 0) {
                    $stat_telat++;
                } else {
                    $stat_hadir++;
                }
            }
        }
    }
}

// Hitung Izin/Sakit Mandiri Langsung Dari Tabel pengajuan_izin
$filter_izin_pembimbing = "";
if ($user_level == 'pembimbing' || $user_level == 'guru') {
    $filter_izin_pembimbing = " AND p.lokasi_id IN (SELECT lokasi_id FROM lokasi_pkl WHERE guru_id = {$guru_filter_id}) ";
}

$query_hitung_izin = "
    SELECT COUNT(*) as total 
    FROM pengajuan_izin i
    JOIN peserta_didik p ON i.siswa_id = p.id
    WHERE i.status = 'Disetujui' 
    AND '$filter_tgl' BETWEEN i.tgl_mulai AND i.tgl_selesai
    {$filter_izin_pembimbing}
";
$stat_izin = $koneksi->query($query_hitung_izin)->fetch_assoc()['total'] ?? 0;

// Hitung Siswa Belum Pulang
$stat_belum_pulang = $koneksi->query("SELECT COUNT(*) as total FROM presensi_pkl pr JOIN peserta_didik p ON pr.siswa_id = p.id JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id WHERE pr.tanggal = '$filter_tgl' AND (pr.jam_pulang IS NULL OR pr.jam_pulang = '') {$filter_pembimbing_clause}")->fetch_assoc()['total'] ?? 0;

// --- LOGIKA SISWA TIDAK HADIR DENGAN SINKRONISASI HARI LIBUR NASIONAL ---
$map_hari_idx = [
    'Senin' => 1, 'Selasa' => 2, 'Rabu' => 3, 'Kamis' => 4, 
    'Jumat' => 5, 'Sabtu' => 6, 'Minggu' => 7
];
$day_of_week_filter = date('N', strtotime($filter_tgl)); // 1 (Senin) - 7 (Minggu)

$query_absen = "
    SELECT 
        p.nama, 
        p.kelas, 
        l.nama_lokasi, 
        l.jam_kerja,
        l.hari_mulai,
        l.hari_selesai,
        (SELECT COUNT(*) FROM presensi_pkl pr WHERE pr.siswa_id = p.id AND pr.tanggal = '$filter_tgl') as is_hadir,
        (SELECT COUNT(*) FROM pengajuan_izin i WHERE i.siswa_id = p.id AND i.status = 'Disetujui' AND '$filter_tgl' BETWEEN i.tgl_mulai AND i.tgl_selesai) as is_izin
    FROM peserta_didik p
    JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
    WHERE 1=1 {$filter_pembimbing_clause}
    ORDER BY p.nama ASC
";
$res_absen = $koneksi->query($query_absen);

$list_tidak_hadir = [];
$current_time = time();
$is_today = ($filter_tgl == date('Y-m-d'));

if ($res_absen) {
    while($row_abs = $res_absen->fetch_assoc()) {
        $is_absent = true;
        
        // --- 1. CEK HARI KERJA (Berdasarkan Lokasi PKL Siswa) ---
        $h_mulai = $row_abs['hari_mulai'] ?? 'Senin';
        $h_selesai = $row_abs['hari_selesai'] ?? 'Jumat';
        $idx_mulai = $map_hari_idx[$h_mulai] ?? 1;
        $idx_selesai = $map_hari_idx[$h_selesai] ?? 5;
        
        $is_libur = true;
        if ($idx_mulai <= $idx_selesai) {
            if ($day_of_week_filter >= $idx_mulai && $day_of_week_filter <= $idx_selesai) {
                $is_libur = false;
            }
        } else {
            // Untuk jadwal yang melintasi minggu (contoh: Jumat - Selasa)
            if ($day_of_week_filter >= $idx_mulai || $day_of_week_filter <= $idx_selesai) {
                $is_libur = false;
            }
        }
        
        // JIKA HARI TERSEBUT LIBUR AKHIR PEKAN ATAU LIBUR NASIONAL
        if ($is_libur || $is_libur_nasional) {
            $is_absent = false; // Bypass status Alpa
        }
        
        // --- 2. CEK JAM MASUK (Hanya dihitung absen bolos jika jam masuk target sudah terlewati) ---
        if ($is_absent && $is_today && !empty($row_abs['jam_kerja'])) {
            $raw_start = trim(explode('s.d.', $row_abs['jam_kerja'])[0]);
            $start_time_str = str_replace('.', ':', $raw_start);
            $start_timestamp = strtotime(date('Y-m-d') . ' ' . $start_time_str);
            
            if ($start_timestamp && $current_time < $start_timestamp) {
                $is_absent = false; 
            }
        }
        
        // --- 3. KESIMPULAN (Jika bukan libur, jam terlewat, dan is_hadir 0 serta is_izin 0) ---
        if ($is_absent && $row_abs['is_hadir'] == 0 && $row_abs['is_izin'] == 0) {
            $list_tidak_hadir[] = $row_abs;
        }
    }
}
$stat_tidak_hadir = count($list_tidak_hadir);

// --- AMBIL DATA LOKASI PKL UNTUK OPSI DOWNLOAD REKAPAN ---
$list_lokasi = $koneksi->query("SELECT lokasi_id, nama_lokasi FROM lokasi_pkl ORDER BY nama_lokasi ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Manajemen Presensi | Si Mantap PKL</title>
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

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            width: 100%;
            flex-wrap: nowrap; 
            gap: 15px;
        }

        .dashboard-header h1 {
            font-weight: 700;
            color: #0f172a;
            font-size: 1.8rem;
            margin: 0;
            position: relative;
            white-space: nowrap;
        }
        .dashboard-header h1::after {
            content: ''; position: absolute; left: 0; bottom: -5px; width: 50px; height: 4px; background: var(--mantap-blue-main); border-radius: 2px;
        }

        .header-actions-group { 
            display: flex; 
            flex-direction: row;
            gap: 10px; 
            align-items: center; 
            flex-wrap: nowrap; 
        }
        
        .search-wrapper { 
            position: relative; 
            display: flex; 
            align-items: center; 
        }
        .search-wrapper i { 
            position: absolute; 
            left: 15px; 
            color: #64748b; 
            font-size: 14px; 
        }
        .search-input { 
            padding: 9px 15px 9px 38px; 
            border: 1px solid #cbd5e1; 
            border-radius: 20px; 
            font-family: 'Poppins', sans-serif; 
            font-size: 13px; 
            width: 180px; 
            transition: all 0.3s ease; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.02); 
            outline: none; 
        }
        .search-input:focus { 
            border-color: var(--mantap-blue-light); 
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); 
            width: 220px; 
        }

        .inline-filter-form { 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            margin: 0;
            padding: 0 !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            flex-wrap: nowrap;
        }
        .inline-filter-form input[type="date"] { 
            padding: 8px 12px; 
            border: 1px solid #cbd5e1; 
            border-radius: 20px; 
            font-family: 'Poppins', sans-serif; 
            font-size: 13px; 
            font-weight: 600; 
            color: var(--mantap-blue-main); 
            background-color: white; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            outline: none;
        }
        .inline-filter-form input[type="date"]:focus { 
            border-color: var(--mantap-blue-main); 
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.15);
        }
        .btn-filter-acc { 
            background-color: var(--mantap-blue-main); 
            color: white; 
            padding: 9px 16px; 
            border: none; 
            border-radius: 20px; 
            font-weight: 600; 
            font-size: 12.5px; 
            font-family: 'Poppins', sans-serif; 
            cursor: pointer; 
            display: flex; 
            align-items: center; 
            gap: 6px;
            box-shadow: 0 2px 4px rgba(30, 64, 175, 0.15);
            transition: 0.2s;
            white-space: nowrap;
        }
        .btn-filter-acc:hover { background-color: var(--mantap-blue-light); transform: translateY(-1px); }

        .btn-aksi-top { 
            background-color: #22c55e; 
            color: white !important; 
            padding: 9px 16px; 
            border: none; 
            border-radius: 20px; 
            cursor: pointer; 
            font-size: 12.5px; 
            font-weight: 600; 
            font-family: 'Poppins', sans-serif; 
            display: inline-flex; 
            align-items: center; 
            gap: 6px; 
            box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.15); 
            text-decoration: none; 
            transition: 0.2s;
            white-space: nowrap;
        }
        .btn-aksi-top:hover { background-color: #16a34a; transform: translateY(-1px); }

        .kpi-row {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 15px;
            margin-bottom: 25px;
            width: 100%;
        }

        .kpi-card-new {
            padding: 16px 15px;
            border-radius: 12px;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
            transition: 0.2s;
        }
        .kpi-card-new.kpi-card-link { cursor: pointer; }
        .kpi-card-new:hover { transform: translateY(-2px); box-shadow: 0 8px 15px rgba(0,0,0,0.1); }
        .kpi-card-new i {
            position: absolute;
            right: -10px;
            top: -10px;
            font-size: 3.5rem;
            opacity: 0.18;
        }
        .kpi-card-new h3 { font-size: 1.6rem; font-weight: 700; margin: 0; }
        .kpi-card-new p { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; margin: 0; font-weight: 600; opacity: 0.9; white-space: nowrap; }

        .glass-panel {
            background: white !important;
            padding: 25px !important;
            border-radius: 16px !important;
            border: 2px solid #e2e8f0 !important;
            box-sizing: border-box;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
            width: 100%;
        }

        .panel-header-inline-wrapper {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 15px;
        }
        .panel-header-inline-wrapper .panel-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--mantap-blue-dark);
        }

        .table-container-fixed { width: 100%; overflow-x: auto; box-sizing: border-box; border: none !important; }
        
        .custom-table {
            width: 100%; table-layout: fixed; border-collapse: collapse; background: white; border-radius: 4px; overflow: hidden; border: 2px solid #1e40af; 
        }

        .custom-table th {
            background: #1e40af; color: white; padding: 14px 6px; font-size: 13px; text-transform: uppercase; font-weight: 700; text-align: center; border: 1px solid #cbd5e1; box-sizing: border-box;
        }

        .custom-table th.col-no { width: 60px; }
        .custom-table th.col-siswa { width: 25%; text-align: left; padding-left: 15px; }
        .custom-table th.col-lokasi { width: 22%; text-align: left; padding-left: 15px; }
        .custom-table th.col-jam-m { width: 15%; }
        .custom-table th.col-foto-m { width: 75px; }
        .custom-table th.col-jam-p { width: 15%; }
        .custom-table th.col-foto-p { width: 75px; }
        .custom-table th.col-peta { width: 180px; }

        .custom-table td {
            padding: 12px 10px; font-size: 13px; vertical-align: middle; word-wrap: break-word; overflow-wrap: break-word; text-align: center; border: 1px solid #cbd5e1; color: #0f172a; background-color: white !important; box-sizing: border-box;
        }
        .custom-table td.cell-no-center { text-align: center; font-weight: 700; }
        .custom-table tbody tr:hover td { background-color: #f8fafc !important; }

        .time-flex-container { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; }
        .time-text-main { font-weight: 600; color: var(--mantap-blue-dark); }

        .time-badge-inline {
            font-size: 10.5px; font-weight: 700; padding: 2px 6px; border-radius: 4px; display: inline-flex; align-items: center; gap: 2px;
        }
        .inline-telat { background-color: #fef2f2; color: #ef4444; }
        .inline-awal { background-color: #f0fdf4; color: #22c55e; }
        .inline-cepat { background-color: #fff7ed; color: #ea580c; }
        .inline-lembur { background-color: #eff6ff; color: #3b82f6; }

        .foto-thumbnail {
            width: 44px; height: 44px; border-radius: 6px; cursor: pointer; overflow: hidden; margin: 0 auto; border: 2px solid #e2e8f0; transition: 0.2s;
        }
        .foto-thumbnail:hover { border-color: var(--mantap-blue-light); transform: scale(1.05); }
        .foto-thumbnail img { width: 100%; height: 100%; object-fit: cover; }
        .foto-not-available { width: 44px; height: 44px; border-radius: 6px; background-color: #f1f5f9; color: #94a3b8; display: flex; align-items: center; justify-content: center; margin: 0 auto; font-size: 13px; border: 1px dashed #cbd5e1; }

        .badge-info-pill { background-color: #f1f5f9; color: #475569; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block; }

        .status-pill { padding: 3px 10px; border-radius: 4px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; display: inline-block; }
        .status-pill-hadir { background-color: #22c55e; color: white; }
        .status-pill-telat { background-color: #ef4444; color: white; }
        .status-pill-izin { background-color: #ea580c; color: white; }
        .status-pill-sakit { background-color: #64748b; color: white; }

        .btn-map-action {
            padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; border: none; color: #fff !important; display: inline-flex; align-items: center; gap: 4px; transition: 0.2s; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.05); font-family: 'Poppins', sans-serif;
        }
        .btn-map-masuk { background-color: var(--mantap-blue-main); }
        .btn-map-masuk:hover { background-color: var(--mantap-blue-light); }
        .btn-map-pulang { background-color: #dc3545; }
        .btn-map-pulang:hover { background-color: #ef4444; }

        .foto-modal {
            display: none; position: fixed; z-index: 999999 !important; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(15, 23, 42, 0.6); backdrop-filter: blur(3px);
        }
        .foto-modal-content {
            background-color: #fff; margin: 5% auto; border-radius: 12px; width: 90%; max-width: 420px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15); overflow: hidden; border: 2px solid #cbd5e1;
        }
        .foto-modal-header { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
        .foto-modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #64748b; line-height: 1; }

        .mantap-modal {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(15, 23, 42, 0.5); z-index: 999999 !important; backdrop-filter: blur(3px);
        }
        .mantap-modal-dialog { position: relative; width: 90%; max-width: 520px; margin: 5rem auto; box-sizing: border-box; }
        .mantap-modal-content { background-color: white; padding: 25px; border-radius: 16px; border: 2px solid #cbd5e1; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15); }
        .mantap-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; }
        .mantap-modal-header h5 { font-size: 1.3rem; margin: 0; color: #0f172a; font-weight: 700; }
        .mantap-modal-body label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13.5px; color: #0f172a; text-align: left; }
        .mantap-modal-body select, .mantap-modal-body input[type="date"], .mantap-modal-body input[type="month"] { width: 100%; padding: 11px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 13px; background-color: #f8fafc; margin-bottom: 15px; box-sizing: border-box; }
        .mantap-modal-footer { display: flex; justify-content: flex-end; gap: 10px; border-top: 2px solid #e2e8f0; padding-top: 15px; margin-top: 10px; }
        .btn-modal-close { background-color: #64748b; color: white; padding: 9px 18px; border: none; border-radius: 20px; font-weight: 600; font-size: 12.5px; cursor: pointer; font-family: 'Poppins', sans-serif; }
        .btn-modal-submit { background-color: #22c55e; color: white; padding: 9px 22px; border: none; border-radius: 20px; font-weight: 600; font-size: 12.5px; cursor: pointer; font-family: 'Poppins', sans-serif; box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.15); }
        .d-none { display: none !important; }

        #modalTidakHadir .mantap-modal-dialog { max-width: 850px; margin: 3rem auto; }
        #modalTidakHadir .modal-scroll-body { max-height: 60vh; overflow-y: auto; overflow-x: auto; padding: 20px; }
        #modalTidakHadir .custom-table { table-layout: auto !important; width: 100% !important; min-width: 100% !important; }
        #modalTidakHadir .custom-table th, #modalTidakHadir .custom-table td { padding: 12px 10px; white-space: nowrap !important; }

        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 

            .dashboard-header { flex-direction: column !important; align-items: flex-start !important; flex-wrap: wrap !important; gap: 15px !important; padding: 5px 0px !important; }
            .dashboard-header h1 { font-size: 1.4rem !important; margin-bottom: 5px; white-space: normal; }
            
            .header-actions-group { width: 100%; flex-direction: column !important; align-items: stretch !important; gap: 10px !important; }
            .search-wrapper { width: 100%; }
            .search-input { width: 100%; box-sizing: border-box; }
            .search-input:focus { width: 100%; }
            
            .inline-filter-form { width: 100% !important; display: flex !important; flex-direction: row !important; gap: 8px !important; }
            .inline-filter-form input[type="date"] { flex: 1 !important; width: 100% !important; box-sizing: border-box !important; font-size: 13.5px !important; padding: 9px !important; }
            .btn-filter-acc { justify-content: center !important; font-size: 13.5px !important; padding: 10px 15px !important; }
            
            .btn-aksi-top { width: 100% !important; justify-content: center !important; font-size: 13.5px !important; padding: 11px !important; }

            .kpi-row { grid-template-columns: repeat(2, 1fr) !important; gap: 10px !important; padding: 5px 0 20px 0 !important; }
            .kpi-card-new { padding: 14px 12px !important; border-radius: 10px !important; }
            .kpi-card-new h3 { font-size: 1.35rem !important; margin-top: 2px; }
            .kpi-card-new p { font-size: 9.5px !important; }
            .kpi-card-new i { font-size: 2.6rem !important; right: -5px; top: -5px; }

            .glass-panel { padding: 16px 10px !important; border-radius: 12px !important; border: 2px solid #cbd5e1 !important; margin: 0 !important; }
            .panel-header-inline-wrapper { padding-bottom: 10px !important; margin-bottom: 15px !important; }

            .table-container-fixed { display: block !important; width: 100% !important; overflow-x: auto !important; border: none !important; }
            .custom-table { table-layout: auto !important; min-width: 1020px !important; }
            .custom-table th, .custom-table td { padding: 12px 10px !important; font-size: 13px !important; text-align: center !important; }
            .custom-table th.col-no, .custom-table th.col-siswa, .custom-table th.col-lokasi, .custom-table th.col-jam-m, .custom-table th.col-foto-m, .custom-table th.col-jam-p, .custom-table th.col-foto-p, .custom-table th.col-peta { width: auto !important; }
            
            .custom-table td:nth-child(2), .custom-table td:nth-child(3) { text-align: left !important; }
            .btn-map-action { padding: 6px 10px !important; font-size: 11.5px !important; }
            
            .mantap-modal-dialog { margin: 1.5rem auto; width: 95%; }
            .mantap-modal-content { padding: 16px; }
            .btn-modal-close, .btn-modal-submit { width: 100% !important; padding: 11px !important; border-radius: 8px !important; font-size: 14px !important; }
            .mantap-modal-footer { flex-direction: column-reverse !important; gap: 8px !important; }

            #modalTidakHadir .mantap-modal-dialog { width: 95%; max-width: 100%; margin: 1.5rem auto; }
            #modalTidakHadir .modal-scroll-body { overflow-x: auto !important; padding: 15px !important; }
            #modalTidakHadir .custom-table { min-width: 600px !important; } 
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; ?>

<div class="main-content-wrapper">

    <?php include 'panel/navbar.php'; ?>

    <div class="admin-main-content"> 
        
        <div class="dashboard-header">
            <h1>Presensi Harian</h1>
            
            <div class="header-actions-group">
                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" class="search-input" placeholder="Cari nama siswa..." onkeyup="filterTable()">
                </div>
                
                <form class="inline-filter-form" method="GET" action="">
                    <input type="date" name="tanggal" value="<?php echo htmlspecialchars($filter_tgl); ?>" required>
                    <button type="submit" class="btn-filter-acc"><i class="fas fa-filter"></i> Saring Data</button>
                </form>

                <button type="button" class="btn-aksi-top" id="openExportModalBtn">
                    <i class="fas fa-file-export"></i> Unduh Rekap Data Absen
                </button>
            </div>
        </div>

        <div class="kpi-row">
            <div class="kpi-card-new" style="background: linear-gradient(135deg, #1e40af 0%, #1d4ed8 100%);">
                <i class="fas fa-users"></i>
                <p>Total Log Tercatat</p>
                <h3><?php echo $total_records; ?></h3>
            </div>
            <div class="kpi-card-new" style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);">
                <i class="fas fa-user-check"></i>
                <p>Tepat Waktu</p>
                <h3><?php echo $stat_hadir; ?></h3>
            </div>
            <div class="kpi-card-new" style="background: linear-gradient(135deg, #ef4444 0%, #dc3545 100%);">
                <i class="fas fa-user-clock"></i>
                <p>Terlambat</p>
                <h3><?php echo $stat_telat; ?></h3>
            </div>
            <div class="kpi-card-new" style="background: linear-gradient(135deg, #ea580c 0%, #f97316 100%);">
                <i class="fas fa-plane-departure"></i>
                <p>Izin / Sakit</p>
                <h3><?php echo $stat_izin; ?></h3>
            </div>
            <div class="kpi-card-new" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                <i class="fas fa-clock"></i>
                <p>Belum Pulang</p>
                <h3><?php echo $stat_belum_pulang; ?></h3>
            </div>
            <div class="kpi-card-new kpi-card-link" style="background: linear-gradient(135deg, #64748b 0%, #334155 100%);" onclick="document.getElementById('modalTidakHadir').style.display='block'">
                <i class="fas fa-user-times"></i>
                <p>Tidak Hadir</p>
                <h3><?php echo $stat_tidak_hadir; ?></h3>
            </div>
        </div>

        <div class="glass-panel">
            <div class="panel-header-inline-wrapper">
                <div class="panel-title">
                    <i class="fas fa-table text-primary me-1"></i> Log Kehadiran Murid Aktif
                </div>
            </div>

            <?php if ($is_libur_nasional): ?>
            <div style="background-color: #fef2f2; border: 1px dashed #fca5a5; color: #dc2626; padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; font-size: 13px;">
                <i class="fas fa-calendar-times me-2"></i><b>Info Sistem:</b> Tanggal ini tercatat sebagai Hari Libur <b>(<?php echo htmlspecialchars($ket_libur_nasional); ?>)</b>. Angka ketidakhadiran otomatis diabaikan, namun rekam jejak presensi siswa yang tetap masuk akan ditangkap oleh sistem di bawah ini.
            </div>
            <?php endif; ?>
            
            <div class="table-container-fixed">
                <table class="custom-table" id="presensiTable">
                    <thead>
                        <tr>
                            <th class="col-no">NO</th>
                            <th class="col-siswa" style="text-align: left; padding-left: 15px;">Siswa / Kelas</th>
                            <th class="col-lokasi" style="text-align: left; padding-left: 15px;">Mitra Industri Penempatan</th>
                            <th class="col-jam-m">Jam Masuk</th>
                            <th class="col-foto-m">Foto M</th>
                            <th class="col-jam-p">Jam Pulang</th>
                            <th class="col-foto-p">Foto P</th>
                            <th class="col-peta">Pelacakan Peta GPS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php
                            $no = $offset + 1;
                            while ($row = $result->fetch_assoc()):
                                $nama_aman = htmlspecialchars($row['nama_siswa'], ENT_QUOTES, 'UTF-8');
                                $is_perizinan = !empty($row['status_perizinan']);
                                
                                $is_telat_row = false;
                                $keterangan_waktu = "";
                                
                                if (!$is_perizinan && !empty($row['jam_masuk']) && !empty($row['jam_kerja'])) {
                                    $raw_jam_kerja = trim(explode('s.d.', $row['jam_kerja'])[0]);
                                    $time_target = strtotime(str_replace('.', ':', $raw_jam_kerja));
                                    $time_siswa  = strtotime(str_replace('.', ':', trim($row['jam_masuk'])));

                                    if ($time_target && $time_siswa) {
                                        $selisih_detik = $time_siswa - $time_target;
                                        $selisih_menit = round(abs($selisih_detik) / 60);

                                        if ($selisih_detik > 0 && $selisih_menit > 0) {
                                            $is_telat_row = true;
                                            $keterangan_waktu = "<span class='time-badge-inline inline-telat'><i class='fas fa-caret-up'></i>Telat " . $selisih_menit . "m</span>";
                                        } elseif ($selisih_detik < 0 && $selisih_menit > 0) {
                                            $keterangan_waktu = "<span class='time-badge-inline inline-awal'><i class='fas fa-caret-down'></i>Awal " . $selisih_menit . "m</span>";
                                        }
                                    }
                                }

                                $keterangan_pulang = "";
                                if (!$is_perizinan && !empty($row['jam_pulang']) && !empty($row['jam_kerja'])) {
                                    $parts_kerja = explode('s.d.', $row['jam_kerja']);
                                    if (isset($parts_kerja[1])) {
                                        $raw_pulang_target = trim(str_replace('WIB', '', $parts_kerja[1]));
                                        $time_pulang_target = strtotime(str_replace('.', ':', $raw_pulang_target));
                                        $time_pulang_siswa  = strtotime(str_replace('.', ':', trim($row['jam_pulang'])));

                                        if ($time_pulang_target && $time_pulang_siswa) {
                                            $selisih_pulang_detik = $time_pulang_siswa - $time_pulang_target;
                                            $selisih_pulang_menit = round(abs($selisih_pulang_detik) / 60);

                                            if ($selisih_pulang_detik < 0 && $selisih_pulang_menit > 0) {
                                                $keterangan_pulang = "<span class='time-badge-inline inline-cepat'><i class='fas fa-person-running'></i>Cepat " . $selisih_pulang_menit . "m</span>";
                                            } elseif ($selisih_pulang_detik > 0 && $selisih_pulang_menit > 0) {
                                                $keterangan_pulang = "<span class='time-badge-inline inline-lembur'><i class='fas fa-business-time'></i>Lembur " . $selisih_pulang_menit . "m</span>";
                                            }
                                        }
                                    }
                                }
                            ?>
                                <tr>
                                    <td class="cell-no-center" style="color: #64748b;"><?php echo $no++; ?></td>
                                    <td style="text-align: left; padding-left: 15px;">
                                        <div style="display: flex; flex-direction: column; gap: 3px;">
                                            <strong class="student-name-title" style="color: var(--mantap-blue-dark); font-size:13.5px;"><?php echo htmlspecialchars($row['nama_siswa']); ?></strong>
                                            <div style="display: flex; gap: 5px; align-items: center; margin-top: 2px;">
                                                <span class="badge-info-pill"><?php echo htmlspecialchars($row['kelas']); ?></span>
                                                <?php if ($is_perizinan): ?>
                                                    <span class="status-pill <?php echo (strtolower($row['status_perizinan']) == 'sakit') ? 'status-pill-sakit' : 'status-pill-izin'; ?>">
                                                        <?php echo htmlspecialchars($row['status_perizinan']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="status-pill <?php echo (!$is_telat_row) ? 'status-pill-hadir' : 'status-pill-telat'; ?>">
                                                        <?php echo (!$is_telat_row) ? 'Hadir' : 'Telat'; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="text-align: left; padding-left: 15px;">
                                        <div style="color: #475569; font-weight: 600; font-size: 12.5px;">
                                            <i class="fas fa-building opacity-50 me-1"></i><?php echo htmlspecialchars($row['nama_lokasi']); ?>
                                        </div>
                                    </td>
                                    
                                    <?php if ($is_perizinan): ?>
                                        <td colspan="4" style="text-align: center; font-style: italic; color: #475569; font-weight: 600; background-color: #f8fafc !important;">
                                            <i class="fas fa-user-clock me-1"></i> Dispensasi Izin Terkonfirmasi (<?php echo htmlspecialchars($row['status_perizinan']); ?>)
                                        </td>
                                        <td>
                                            <?php if (!empty($row['berkas_perizinan'])): ?>
                                                <button type="button" class="btn-view-doc" onclick="openFotoModal('../uploads/izin/<?php echo htmlspecialchars($row['berkas_perizinan']); ?>', '<?php echo $nama_aman; ?> (Bukti <?php echo htmlspecialchars($row['status_perizinan']); ?>)')" style="padding: 5px 12px; border-radius: 15px; font-size: 11px; display:inline-block; background-color: var(--mantap-blue-soft); border: 1px solid var(--mantap-blue-main); color: var(--mantap-blue-main); cursor: pointer; font-family: 'Poppins', sans-serif; font-weight: 600;">
                                                    <i class="fa fa-image me-1"></i> Bukti Lampiran
                                                </button>
                                            <?php else: ?> - <?php endif; ?>
                                        </td>
                                    <?php else: ?>
                                        <td>
                                            <div class="time-flex-container">
                                                <span class="time-text-main"><?php echo htmlspecialchars($row['jam_masuk']); ?></span>
                                                <?php echo $keterangan_waktu; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php 
                                            $foto_masuk = !empty($row['keterangan']) ? $row['keterangan'] : null;
                                            if ($foto_masuk) {
                                                $foto_masuk_url = '../uploads/absensi/' . htmlspecialchars($foto_masuk);
                                                echo '<div class="foto-thumbnail" onclick="openFotoModal(\'' . $foto_masuk_url . '\', \'' . $nama_aman . ' (Masuk)\')">';
                                                echo '<img src="' . $foto_masuk_url . '" alt="M" loading="lazy">';
                                                echo '</div>';
                                            } else {
                                                echo '<div class="foto-not-available"><i class="fas fa-camera"></i></div>';
                                            }
                                            ?>
                                        </td>

                                        <td>
                                            <div class="time-flex-container">
                                                <?php if (!empty($row['jam_pulang'])): ?>
                                                    <span class="time-text-main"><?php echo htmlspecialchars($row['jam_pulang']); ?></span>
                                                    <?php echo $keterangan_pulang; ?>
                                                <?php else: ?>
                                                    <span class="text-muted font-monospace small" style="opacity: 0.6; font-style: italic;">Belum Pulang</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php 
                                            $foto_pulang = !empty($row['keterangan2']) ? $row['keterangan2'] : null;
                                            if ($foto_pulang) {
                                                $foto_pulang_url = '../uploads/absensi/' . htmlspecialchars($foto_pulang);
                                                echo '<div class="foto-thumbnail" onclick="openFotoModal(\'' . $foto_pulang_url . '\', \'' . $nama_aman . ' (Pulang)\')">';
                                                echo '<img src="' . $foto_pulang_url . '" alt="P" loading="lazy">';
                                                echo '</div>';
                                            } else {
                                                echo '<div class="foto-not-available"><i class="fas fa-camera"></i></div>';
                                            }
                                            ?>
                                        </td>

                                        <td>
                                            <div class="dashboard-btn-group">
                                                <button type="button" class="btn-map-action btn-map-masuk" data-lat="<?php echo htmlspecialchars($row['lat_absen'] ?? ''); ?>" data-lng="<?php echo htmlspecialchars($row['lng_absen'] ?? ''); ?>" data-name="<?php echo htmlspecialchars($row['nama_siswa']); ?>" onclick="viewLocation(this, 'Masuk')">
                                                    <i class="fas fa-street-view"></i> Masuk
                                                </button>
                                                
                                                <?php if (!empty($row['jam_pulang'])): ?>
                                                    <button type="button" class="btn-map-action btn-map-pulang" data-lat="<?php echo htmlspecialchars($row['lat_absen'] ?? ''); ?>" data-lng="<?php echo htmlspecialchars($row['lng_absen'] ?? ''); ?>" data-name="<?php echo htmlspecialchars($row['nama_siswa']); ?>" onclick="viewLocation(this, 'Pulang')">
                                                        <i class="fas fa-map-location-dot"></i> Pulang
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-light border" style="font-size:11px; border-radius:4px; padding: 4px 10px; cursor:not-allowed; opacity: 0.5;" disabled>
                                                        <i class="fas fa-ban"></i> Pulang
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: #ef4444; font-style: italic; padding: 25px;">Tidak ada rekam jejak presensi pendaftaran yang ditemukan pada tanggal ini.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="glass-panel" id="map-panel" style="display: none; padding: 20px; margin-top: 25px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h5 style="margin: 0; color: var(--mantap-blue-dark); font-weight: 700;"><i class="fas fa-map-marked-alt text-primary" style="margin-right: 6px;"></i>Titik Koordinat: <span id="siswa-name" style="color: var(--mantap-blue-main);"></span> (<span id="absen-type"></span>)</h5>
                <button type="button" style="background:none; border:none; font-size:1.4rem; color:#94a3b8; cursor:pointer;" onclick="closeMap()">&times;</button>
            </div>
            <div id="map-preview" style="height: 380px; width: 100%; border-radius: 12px; border: 2px solid #cbd5e1;"></div>
        </div>
    </div>
</div>

<div class="mantap-modal" id="downloadRekapModal">
    <div class="mantap-modal-dialog">
        <div class="mantap-modal-content">
            <div class="mantap-modal-header">
                <h5><i class="fas fa-download" style="color: var(--mantap-blue-main); margin-right: 6px;"></i> Parameter Ekspor Presensi</h5>
                <button type="button" class="close-modal-btn" id="closeExportModalBtn">&times;</button>
            </div>
            <form action="presensi-ekspor.php" method="POST">
                <div class="mantap-modal-body">
                    <label for="modal_lokasi_id">Filter Mitra Penempatan PKL:</label>
                    <select id="modal_lokasi_id" name="lokasi_id">
                        <option value="all">-- Semua Lokasi PKL (Seluruh Mitra) --</option>
                        <?php 
                        if($list_lokasi) {
                            $list_lokasi->data_seek(0); // Reset pointer query
                            while($lok = $list_lokasi->fetch_assoc()){
                                echo '<option value="'.$lok['lokasi_id'].'">'.htmlspecialchars($lok['nama_lokasi']).'</option>';
                            }
                        }
                        ?>
                    </select>
                    
                    <label for="rentang_tipe">Jenis Rentang Waktu:</label>
                    <select name="rentang_tipe" id="rentang_tipe">
                        <option value="harian">Rekap Harian</option>
                        <option value="bulanan">Rekap Bulanan (1 Bulan Penuh)</option>
                    </select>
                    
                    <div id="group_harian">
                        <label for="tanggal_rekap">Pilih Tanggal Hari:</label>
                        <input type="date" id="tanggal_rekap" name="tanggal_rekap" value="<?php echo htmlspecialchars($filter_tgl); ?>">
                    </div>
                    
                    <div id="group_bulanan" class="d-none">
                        <label for="bulan_rekap">Pilih Bulan & Tahun:</label>
                        <input type="month" id="bulan_rekap" name="bulan_rekap" value="<?php echo date('Y-m', strtotime($filter_tgl)); ?>">
                    </div>
                    
                    <label style="margin-bottom: 8px;">Format Output Unduhan Berkas:</label>
                    <div style="display: flex; gap: 20px; background-color: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #cbd5e1; margin-bottom: 5px;">
                        <label style="margin:0; font-weight:500; cursor:pointer; display:flex; align-items:center; gap:6px;">
                            <input type="radio" name="format_file" value="pdf" checked style="margin:0;"> <i class="fas fa-file-pdf text-danger"></i> Dokumen PDF
                        </label>
                        <label style="margin:0; font-weight:500; cursor:pointer; display:flex; align-items:center; gap:6px;">
                            <input type="radio" name="format_file" value="excel" style="margin:0;"> <i class="fas fa-file-excel text-success"></i> Spreadsheet Excel
                        </label>
                    </div>
                </div>
                <div class="mantap-modal-footer">
                    <button type="button" class="btn-modal-close" id="closeExportModalBtn2">Batal</button>
                    <button type="submit" class="btn-modal-submit"><i class="fas fa-cloud-download-alt"></i> Proses Unduh</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="fotoModal" class="foto-modal">
    <div class="foto-modal-content">
        <div class="foto-modal-header">
            <h5 style="margin:0; font-weight:700; color:var(--mantap-blue-dark); font-size:1.1rem;"><i class="fas fa-camera text-primary" style="margin-right: 6px;"></i>Preview Foto Lampiran</h5>
            <button class="foto-modal-close" onclick="closeFotoModal()">&times;</button>
        </div>
        <div style="text-align: center; padding: 20px; box-sizing: border-box;">
            <img id="modalFotoImg" src="" alt="Foto Lampiran" style="max-width: 100%; height: auto; border-radius: 8px; border: 1px solid #cbd5e1; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <div style="margin-top: 15px; padding: 10px; background: #f8fafc; border-radius: 6px; border: 1px solid #cbd5e1; font-weight: 700; color: var(--mantap-blue-dark); text-transform: uppercase; font-size: 12.5px;" id="modalFotoName"></div>
        </div>
    </div>
</div>

<div class="mantap-modal" id="modalTidakHadir">
    <div class="mantap-modal-dialog">
        <div class="mantap-modal-content" style="padding: 0; overflow: hidden;">
            <div class="mantap-modal-header" style="background: #f8fafc; margin: 0; padding: 20px 25px; border-bottom: 2px solid #e2e8f0;">
                <h5 style="margin:0; font-weight:700; font-size:1.2rem; color: #0f172a;"><i class="fas fa-user-times text-danger me-2"></i> Daftar Siswa Tidak Hadir</h5>
                <button type="button" class="close-modal-btn" onclick="document.getElementById('modalTidakHadir').style.display='none'">&times;</button>
            </div>
            <div class="modal-scroll-body" style="padding: 20px;">
                <?php if($stat_tidak_hadir > 0): ?>
                    <table class="custom-table" style="margin-bottom: 0;">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th style="text-align: left;">Nama Siswa</th>
                                <th style="width: 80px;">Kelas</th>
                                <th style="text-align: left;">Lokasi PKL</th>
                                <th style="width: 120px;">Jadwal Masuk</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no_absen = 1; foreach($list_tidak_hadir as $absen): 
                                $jam_masuk_teks = "08:00"; // default
                                if (!empty($absen['jam_kerja'])) {
                                    $raw_start = trim(explode('s.d.', $absen['jam_kerja'])[0]);
                                    $jam_masuk_teks = str_replace('.', ':', $raw_start);
                                }
                            ?>
                            <tr>
                                <td class="cell-no-center" style="color: #64748b;"><?= $no_absen++ ?></td>
                                <td style="text-align: left; font-weight: 700; text-transform: uppercase;"><?= htmlspecialchars($absen['nama']) ?></td>
                                <td style="font-weight: 600; color: #475569;"><?= htmlspecialchars($absen['kelas']) ?></td>
                                <td style="text-align: left;"><small style="font-weight: 600; color: #1e40af;"><i class="fas fa-building opacity-50 me-1"></i><?= htmlspecialchars($absen['nama_lokasi'] ?? 'Belum Ditentukan') ?></small></td>
                                <td><span style="background: #fff5f5; color: #dc2626; padding: 4px 8px; border-radius: 4px; font-weight: 700; font-size: 11px;"><i class="fas fa-clock me-1"></i><?= $jam_masuk_teks ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px 20px; color: #64748b; font-style: italic; border: 2px dashed #cbd5e1; border-radius: 8px; background: #f8fafc; margin: 20px;">
                        <i class="fas fa-check-circle text-success mb-2" style="font-size: 2.5rem; display: block;"></i>
                        <h5 style="color: #0f172a; font-weight: 700; margin-bottom: 5px;">Mantap!</h5>
                        <p style="margin:0; font-size: 13px;">Seluruh siswa telah hadir, mengajukan izin, atau libur pada hari ini.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'panel/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // --- FITUR PENCARIAN REAL-TIME ---
    function filterTable() {
        var input, filter, table, tr, td, i, txtValue;
        input = document.getElementById("searchInput");
        filter = input.value.toUpperCase();
        table = document.getElementById("presensiTable");
        
        if (!table) return; 
        
        tr = table.getElementsByTagName("tr");

        for (i = 1; i < tr.length; i++) {
            td = tr[i].getElementsByTagName("td")[1]; // Kolom "Siswa / Kelas"
            if (td) {
                let nameElement = td.querySelector('.student-name-title'); 
                txtValue = nameElement ? nameElement.textContent || nameElement.innerText : td.textContent || td.innerText;
                
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }
    }

    let mapInstance = null;
    let currentMarker = null;
    let leafletLoaded = false;

    const expModal = document.getElementById('downloadRekapModal');
    const modalTidakHadir = document.getElementById('modalTidakHadir');
    const openExpBtn = document.getElementById('openExportModalBtn');
    const closeExpBtn = document.getElementById('closeExportModalBtn');
    const closeExpBtn2 = document.getElementById('closeExportModalBtn2');

    if(openExpBtn) { openExpBtn.addEventListener('click', () => { expModal.style.display = 'block'; }); }
    if(closeExpBtn) { closeExpBtn.addEventListener('click', () => { expModal.style.display = 'none'; }); }
    if(closeExpBtn2) { closeExpBtn2.addEventListener('click', () => { expModal.style.display = 'none'; }); }

    document.getElementById('rentang_tipe').addEventListener('change', function() {
        const tipe = this.value;
        const groupHarian = document.getElementById('group_harian');
        const groupBulanan = document.getElementById('group_bulanan');
        
        if(tipe === 'harian') {
            groupHarian.classList.remove('d-none');
            groupBulanan.classList.add('d-none');
        } else {
            groupHarian.classList.add('d-none');
            groupBulanan.classList.remove('d-none');
        }
    });

    function loadLeaflet() {
        return new Promise((resolve, reject) => {
            if (leafletLoaded) { resolve(); return; }
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
            document.head.appendChild(link);
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
            script.onload = () => { leafletLoaded = true; resolve(); };
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    async function viewLocation(button, type) {
        const lat = parseFloat(button.getAttribute('data-lat'));
        const lng = parseFloat(button.getAttribute('data-lng'));
        const name = button.getAttribute('data-name');

        if (isNaN(lat) || isNaN(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
            Swal.fire({ icon: 'warning', title: 'Koordinat Kosong', text: 'Siswa belum memiliki rekaman koordinat GPS untuk absen ' + type, confirmButtonColor: '#1e40af' });
            return;
        }

        try {
            await loadLeaflet();
            const mapPanel = document.getElementById('map-panel');
            mapPanel.style.display = 'block';
            document.getElementById('siswa-name').textContent = name;
            document.getElementById('absen-type').textContent = "Absen " + type;

            if (!mapInstance) {
                mapInstance = L.map('map-preview').setView([lat, lng], 17);
                L.tileLayer('https://{s}.google.com/vt/lyrs=s,h&x={x}&y={y}&z={z}', {
                    maxZoom: 20, subdomains: ['mt0', 'mt1', 'mt2', 'mt3']
                }).addTo(mapInstance);
            }

            mapInstance.setView([lat, lng], 17);
            setTimeout(function() { mapInstance.invalidateSize(); }, 300);

            if (currentMarker) { mapInstance.removeLayer(currentMarker); }

            const popupColor = (type === 'Masuk') ? '#1e40af' : '#dc3545';
            currentMarker = L.marker([lat, lng]).addTo(mapInstance)
                .bindPopup(`<strong>${name}</strong><br><span style='color:${popupColor};font-weight:bold;'>Lokasi Absen ${type}</span>`)
                .openPopup();

            window.scrollTo({ top: mapPanel.offsetTop - 80, behavior: 'smooth' });
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Gagal', text: 'Gagal memuat peta interaktif Leaflet.', confirmButtonColor: '#1e40af' });
        }
    }

    function closeMap() { document.getElementById('map-panel').style.display = 'none'; }
    
    function openFotoModal(fotoUrl, namasiswa) {
        const modal = document.getElementById('fotoModal');
        document.getElementById('modalFotoImg').src = fotoUrl;
        document.getElementById('modalFotoName').textContent = namasiswa;
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
    
    function closeFotoModal() {
        document.getElementById('fotoModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('fotoModal');
        if (modal) { modal.addEventListener('click', function(event) { if (event.target === modal) closeFotoModal(); }); }
        document.addEventListener('keydown', function(event) { if (event.key === 'Escape' && modal.style.display === 'block') closeFotoModal(); });
        
        window.addEventListener('click', (e) => { 
            if (e.target === expModal) { expModal.style.display = 'none'; }
            if (e.target === modalTidakHadir) { modalTidakHadir.style.display = 'none'; }
        });
    });
</script>
</body>
</html>