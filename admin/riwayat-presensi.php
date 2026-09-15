<?php
// admin/riwayat-presensi.php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

include 'auth-check.php';
include '../config/db-koneksi.php';

$user_level = strtolower($_SESSION['level'] ?? '');
$current_user_id = $_SESSION['user_id'] ?? 0;

$siswa_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
$error_akses = "";

// 1. Ambil ID Guru jika yang login adalah pembimbing
$guru_id = 0;
if ($user_level == 'guru' || $user_level == 'pembimbing') {
    $stmt = $koneksi->prepare("SELECT guru_id FROM guru WHERE user_id = ?");
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $guru_id = $res->fetch_assoc()['guru_id'];
    }
    $stmt->close();
}

// 2. Ambil Daftar Siswa untuk Dropdown Pilihan
$q_list = "SELECT p.id, p.nama, p.nisn, p.kelas FROM peserta_didik p LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id";
if ($user_level !== 'admin') {
    $q_list .= " WHERE l.guru_id = '$guru_id'";
}
$q_list .= " ORDER BY p.nama ASC";
$list_siswa = $koneksi->query($q_list);

// =========================================================================================
// LOGIKA JIKA ADA SISWA YANG DIPILIH DARI DROPDOWN
// =========================================================================================
if ($siswa_id > 0) {
    // Ambil Profil Siswa
    $q_siswa = $koneksi->query("
        SELECT p.*, l.guru_id as loc_guru_id, l.nama_lokasi, l.jam_kerja, l.hari_mulai, l.hari_selesai, per.tgl_mulai, per.tgl_akhir, g.nama_guru
        FROM peserta_didik p
        LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
        LEFT JOIN guru g ON l.guru_id = g.guru_id
        LEFT JOIN periode_pkl per ON p.periode_id = per.periode_id
        WHERE p.id = $siswa_id
    ");

    if ($q_siswa->num_rows === 0) {
        $error_akses = "Data siswa tidak ditemukan dalam pangkalan data.";
    } else {
        $dataSiswa = $q_siswa->fetch_assoc();

        // Validasi Akses Guru
        if ($user_level !== 'admin' && $dataSiswa['loc_guru_id'] != $guru_id) {
            $error_akses = "Maaf, Anda tidak memiliki izin untuk melihat riwayat presensi <b>" . htmlspecialchars($dataSiswa['nama']) . "</b> karena siswa tersebut bukan berada di bawah bimbingan Anda.";
        } else {
            
            // --- TRIGGER LOG AKTIVITAS (Melihat Riwayat) ---
            catatLog($koneksi, $current_user_id, "Melihat rincian riwayat presensi bulanan untuk siswa: " . $dataSiswa['nama'] . " (Periode: {$bulan}/{$tahun})");

            // PROSES DATA RIWAYAT JIKA AKSES DIIZINKAN
            $bulan_indo = [
                '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
                '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
                '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
            ];
            $bulan_indo_short = [
                '01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr',
                '05' => 'Mei', '06' => 'Jun', '07' => 'Jul', '08' => 'Agu',
                '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'
            ];
            $hari_indo = [
                'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
                'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'
            ];
            $map_hari_idx = ['Senin' => 1, 'Selasa' => 2, 'Rabu' => 3, 'Kamis' => 4, 'Jumat' => 5, 'Sabtu' => 6, 'Minggu' => 7];

            $h_mulai = $dataSiswa['hari_mulai'] ?? 'Senin';
            $h_selesai = $dataSiswa['hari_selesai'] ?? 'Jumat';
            $idx_mulai = $map_hari_idx[$h_mulai] ?? 1;
            $idx_selesai = $map_hari_idx[$h_selesai] ?? 5;

            // Query Kehadiran
            $query = $koneksi->query("
                SELECT pr.*, l.jam_kerja 
                FROM presensi_pkl pr
                JOIN peserta_didik p ON pr.siswa_id = p.id
                LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
                WHERE pr.siswa_id = '$siswa_id' 
                AND MONTH(pr.tanggal) = '$bulan' AND YEAR(pr.tanggal) = '$tahun'
            ");
            $riwayat_db = [];
            if ($query) {
                while ($row = $query->fetch_assoc()) {
                    $riwayat_db[date('Y-m-d', strtotime($row['tanggal']))] = $row;
                }
            }

            // Query Izin/Sakit
            $query_izin = $koneksi->query("SELECT jenis_izin, tgl_mulai, tgl_selesai FROM pengajuan_izin WHERE siswa_id = '$siswa_id' AND status = 'Disetujui'");
            $data_izin = [];
            if ($query_izin) {
                while ($row = $query_izin->fetch_assoc()) {
                    for ($currentDate = strtotime($row['tgl_mulai']); $currentDate <= strtotime($row['tgl_selesai']); $currentDate += 86400) {
                        $data_izin[date('Y-m-d', $currentDate)] = $row['jenis_izin'];
                    }
                }
            }

            // Query Libur Nasional
            $query_libur = $koneksi->query("SELECT tanggal_libur, keterangan FROM hari_libur WHERE MONTH(tanggal_libur) = '$bulan' AND YEAR(tanggal_libur) = '$tahun'");
            $data_libur_nasional = [];
            if ($query_libur) {
                while ($row = $query_libur->fetch_assoc()) {
                    $data_libur_nasional[$row['tanggal_libur']] = $row['keterangan'];
                }
            }

            $riwayat_table = [];
            $stat_hadir = 0; $stat_telat = 0; $stat_alpa = 0; $stat_sakit = 0; $stat_izin = 0;
            $days_in_month = cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);
            $current_date = date('Y-m-d');
            
            for ($i = 1; $i <= $days_in_month; $i++) {
                $date_str = sprintf("%04d-%02d-%02d", $tahun, $bulan, $i);
                $day_of_week = date('N', strtotime($date_str)); 
                $is_past_or_today = ($date_str <= $current_date);
                
                if (!empty($dataSiswa['tgl_mulai']) && $date_str < $dataSiswa['tgl_mulai']) {
                    $riwayat_table[] = ['tanggal' => $date_str, 'status' => 'Belum Mulai', 'data' => null, 'ket_waktu' => '', 'ket_pulang' => '', 'ket_libur' => '']; continue;
                }
                if (!empty($dataSiswa['tgl_akhir']) && $date_str > $dataSiswa['tgl_akhir']) {
                    $riwayat_table[] = ['tanggal' => $date_str, 'status' => 'Selesai', 'data' => null, 'ket_waktu' => '', 'ket_pulang' => '', 'ket_libur' => '']; continue;
                }
                
                if (isset($riwayat_db[$date_str])) {
                    $row = $riwayat_db[$date_str];
                    $is_telat_row = false; $keterangan_waktu = ""; $keterangan_pulang = "";
                    
                    if (!empty($row['jam_masuk']) && !empty($row['jam_kerja'])) {
                        $time_target = strtotime(str_replace('.', ':', trim(explode('s.d.', $row['jam_kerja'])[0])));
                        $time_siswa  = strtotime(str_replace('.', ':', trim($row['jam_masuk'])));
                        if ($time_target && $time_siswa) {
                            $selisih_menit = round(($time_siswa - $time_target) / 60);
                            if ($selisih_menit > 0) {
                                $is_telat_row = true; $stat_telat++;
                                $keterangan_waktu = "<span class='time-badge-inline inline-telat'>Telat " . abs($selisih_menit) . "m</span>";
                            } elseif ($selisih_menit < 0) {
                                $stat_hadir++;
                                $keterangan_waktu = "<span class='time-badge-inline inline-awal'>Awal " . abs($selisih_menit) . "m</span>";
                            } else { $stat_hadir++; }
                        } else { $stat_hadir++; }
                    } else { $stat_hadir++; }

                    if (!empty($row['jam_pulang']) && !empty($row['jam_kerja'])) {
                        $parts_kerja = explode('s.d.', $row['jam_kerja']);
                        if (isset($parts_kerja[1])) {
                            $time_pulang_target = strtotime(str_replace('.', ':', trim(str_replace('WIB', '', $parts_kerja[1]))));
                            $time_pulang_siswa  = strtotime(str_replace('.', ':', trim($row['jam_pulang'])));
                            if ($time_pulang_target && $time_pulang_siswa) {
                                $selisih_pulang_menit = round(($time_pulang_siswa - $time_pulang_target) / 60);
                                if ($selisih_pulang_menit < 0) {
                                    $keterangan_pulang = "<span class='time-badge-inline inline-cepat'>Cepat " . abs($selisih_pulang_menit) . "m</span>";
                                } elseif ($selisih_pulang_menit > 0) {
                                    $keterangan_pulang = "<span class='time-badge-inline inline-lembur'>Lembur " . abs($selisih_pulang_menit) . "m</span>";
                                }
                            }
                        }
                    }
                    $riwayat_table[] = ['tanggal' => $date_str, 'status' => $is_telat_row ? 'Telat' : 'Hadir', 'data' => $row, 'ket_waktu' => $keterangan_waktu, 'ket_pulang' => $keterangan_pulang, 'ket_libur' => ''];
                } else {
                    $status = ''; $keterangan_khusus = '';
                    $is_libur = true;
                    if ($idx_mulai <= $idx_selesai) {
                        if ($day_of_week >= $idx_mulai && $day_of_week <= $idx_selesai) { $is_libur = false; }
                    } else {
                        if ($day_of_week >= $idx_mulai || $day_of_week <= $idx_selesai) { $is_libur = false; }
                    }

                    if (isset($data_libur_nasional[$date_str])) {
                        $status = 'Libur Nasional'; $keterangan_khusus = $data_libur_nasional[$date_str];
                    } elseif ($is_libur) { 
                        $status = 'Libur Akhir Pekan';
                    } else {
                        if (isset($data_izin[$date_str])) {
                            $status = $data_izin[$date_str]; 
                            if ($status == 'Sakit') $stat_sakit++;
                            if ($status == 'Izin') $stat_izin++;
                        } else {
                            if ($is_past_or_today) { $status = 'Alpa'; $stat_alpa++; } 
                            else { $status = 'Belum'; }
                        }
                    }
                    $riwayat_table[] = ['tanggal' => $date_str, 'status' => $status, 'data' => null, 'ket_waktu' => '', 'ket_pulang' => '', 'ket_libur' => $keterangan_khusus];
                }
            }
            usort($riwayat_table, function($a, $b) { return strtotime($a['tanggal']) - strtotime($b['tanggal']); });
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Riwayat Presensi Siswa | Si Mantap PKL</title>
    <link rel="stylesheet" href="../css/style.css"> 
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
        }

        .admin-main-content {
            padding: 20px 25px 30px 25px !important; 
            box-sizing: border-box !important;
            clear: both;
            width: 100% !important;
        }

        /* 1. Header Control (SAMA PERSIS DENGAN PESERTA-LIST) */
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

        .header-actions-group {
            display: flex;
            gap: 10px;
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
            left: 15px;
            color: #64748b;
            font-size: 14px;
            pointer-events: none;
        }

        .search-input {
            padding: 8px 15px 8px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 20px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            outline: none;
            background-color: white;
        }
        
        select.search-input {
            cursor: pointer;
        }

        .search-input:focus {
            border-color: var(--mantap-blue-light);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        /* 2. Info Kartu Identitas Siswa */
        .info-card-panel {
            background-color: var(--mantap-blue-soft); /* Biru terang background */
            border-left: 4px solid var(--mantap-blue-main); /* Garis biru tebal */
            padding: 16px 20px;
            border-radius: 6px;
            margin-bottom: 25px;
            text-align: left;
        }
        .info-card-panel p {
            margin: 6px 0;
            font-size: 13px;
            color: #334155;
            line-height: 1.5;
        }
        .info-card-panel p:first-child { margin-top: 0; }
        .info-card-panel p:last-child { margin-bottom: 0; }
        .info-card-panel strong {
            color: #0f172a;
            font-weight: 700;
        }

        /* 3. KPI / Stats Row - Solid Colors */
        .kpi-row {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
            margin-bottom: 25px;
            width: 100%;
        }
        .kpi-card-new {
            padding: 18px 15px;
            border-radius: 8px;
            color: white;
            position: relative;
            overflow: hidden;
            transition: 0.2s;
        }
        .kpi-card-new:hover { transform: translateY(-2px); }
        .kpi-card-new h3 { font-size: 1.6rem; font-weight: 800; margin: 0; position: relative; z-index: 2; }
        .kpi-card-new p { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 4px 0; font-weight: 700; opacity: 0.95; position: relative; z-index: 2; }

        /* Custom Solid Colors matching reference */
        .kpi-hadir { background-color: #10b981; }
        .kpi-telat { background-color: #ef4444; }
        .kpi-alpa  { background-color: #64748b; }
        .kpi-sakit { background-color: #3b82f6; }
        .kpi-izin  { background-color: #f59e0b; }

        /* 4. Glass Panel Base & Table Container */
        .glass-panel {
            background: white !important;
            padding: 20px 25px !important;
            border-radius: 8px !important;
            border: 1px solid #e2e8f0 !important;
            box-sizing: border-box;
            width: 100%;
            margin-bottom: 20px;
        }

        .panel-header-inline-wrapper {
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
            width: 100%; table-layout: fixed; border-collapse: collapse; background: white; border-radius: 4px; overflow: hidden; border: 2px solid #1e40af; min-width: 800px;
        }

        /* WARNA HEADER BIRU SESUAI INSTRUKSI */
        .custom-table th {
            background-color: var(--mantap-blue-main) !important; 
            color: white !important; 
            padding: 14px 6px; 
            font-size: 12px; 
            text-transform: uppercase; 
            font-weight: 700; 
            text-align: center; 
            border: 1px solid #cbd5e1; 
            box-sizing: border-box;
        }

        .custom-table th.col-tgl { width: 18%; }
        .custom-table th.col-status { width: 14%; }
        .custom-table th.col-jam-m { width: 18%; }
        .custom-table th.col-foto-m { width: 16%; }
        .custom-table th.col-jam-p { width: 18%; }
        .custom-table th.col-foto-p { width: 16%; }

        .custom-table td {
            padding: 12px 10px; font-size: 13px; vertical-align: middle; word-wrap: break-word; overflow-wrap: break-word; text-align: center; border: 1px solid #e2e8f0; color: #0f172a; background-color: white !important; box-sizing: border-box;
        }
        .custom-table tbody tr:hover td { background-color: #f8fafc !important; }

        .time-flex-container { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; }
        .time-text-main { font-weight: 700; color: #0f172a; font-size: 13.5px; }

        /* Badge Waktu & Telat */
        .time-badge-inline { font-size: 10.5px; font-weight: 700; padding: 2px 6px; border-radius: 4px; display: inline-flex; align-items: center; gap: 2px; }
        .inline-telat { background-color: #fff; color: #ef4444; border: 1px solid #fca5a5; }
        .inline-awal  { background-color: #fff; color: #22c55e; border: 1px solid #86efac; }
        .inline-cepat { background-color: #fff; color: #ea580c; border: 1px solid #fdba74; }
        .inline-lembur{ background-color: #fff; color: #3b82f6; border: 1px solid #93c5fd; }

        /* Foto Thumbnail Clean */
        .foto-thumbnail {
            width: 44px; height: 44px; border-radius: 6px; cursor: pointer; overflow: hidden; margin: 0 auto; border: 1px solid #cbd5e1; transition: 0.2s;
        }
        .foto-thumbnail:hover { border-color: var(--mantap-blue-main); transform: scale(1.05); }
        .foto-thumbnail img { width: 100%; height: 100%; object-fit: cover; }
        .foto-not-available { 
            width: 44px; height: 44px; border-radius: 6px; background-color: #f8fafc; color: #cbd5e1; display: flex; align-items: center; justify-content: center; margin: 0 auto; font-size: 13px; border: 1px dashed #cbd5e1; 
        }

        /* Status Pills */
        .status-pill { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; display: inline-block; min-width: 65px; text-align: center; }
        .status-pill-hadir { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0;}
        .status-pill-telat { background-color: #fef2f2; color: #991b1b; border: 1px solid #fecaca;}

        /* Error Box */
        .error-alert-box { background: white; border: 2px dashed #fca5a5; color: #ef4444; padding: 20px; border-radius: 12px; margin-bottom: 20px; text-align: center; }

        /* Modal */
        .foto-modal {
            display: none; position: fixed; z-index: 999999 !important; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(15, 23, 42, 0.6); backdrop-filter: blur(3px);
        }
        .foto-modal-content {
            background-color: #fff; margin: 5% auto; border-radius: 12px; width: 90%; max-width: 420px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15); overflow: hidden; border: 2px solid #cbd5e1;
        }
        .foto-modal-header { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; background: white; border-bottom: 2px solid #f1f5f9; }
        .foto-modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #64748b; line-height: 1; transition: 0.2s; }
        .foto-modal-close:hover { color: #ef4444; }

        @media (max-width: 768px) {
            body { padding-top: 60px !important; }
            .admin-main-content { padding: 15px 12px 25px 12px !important; }

            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; padding: 5px 0px !important; }
            .page-header-controls h1 { font-size: 1.4rem !important; margin-bottom: 10px; white-space: normal; }
            
            .header-actions-group { width: 100%; flex-direction: column; align-items: stretch; gap: 12px; }
            .header-actions-group form { flex-direction: column; align-items: stretch; width: 100%; }
            .search-wrapper { width: 100%; }
            .search-input { width: 100% !important; max-width: 100% !important; box-sizing: border-box; }
            
            .kpi-row { grid-template-columns: repeat(2, 1fr) !important; gap: 10px !important; }
            .kpi-row > div:last-child { grid-column: span 2; } 
            
            .kpi-card-new { padding: 14px 12px !important; border-radius: 8px !important; }
            .kpi-card-new h3 { font-size: 1.35rem !important; margin-top: 2px; }

            .info-card-panel { padding: 15px !important; margin-bottom: 15px !important; }
            .info-card-panel p { font-size: 12px !important; }

            .glass-panel { padding: 16px 10px !important; border-radius: 8px !important; margin-bottom: 15px !important;}
            .panel-header-inline-wrapper { padding-bottom: 10px !important; margin-bottom: 15px !important; }

            .table-container-fixed { display: block !important; width: 100% !important; overflow-x: auto !important; border: none !important; }
            .custom-table { table-layout: auto !important; min-width: 600px !important; }
            .custom-table th, .custom-table td { padding: 10px !important; font-size: 12.5px !important; }
            .custom-table th.col-tgl, .custom-table th.col-status, .custom-table th.col-jam-m, .custom-table th.col-foto-m, .custom-table th.col-jam-p, .custom-table th.col-foto-p { width: auto !important; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; include 'panel/navbar.php'; ?>

<div class="main-content-wrapper">
    <div class="admin-main-content">
        
        <!-- HEADER KONTROL & FILTER (SAMA PERSIS DENGAN PESERTA-LIST) -->
        <div class="page-header-controls">
            <h1>Riwayat Presensi</h1>
            
            <div class="header-actions-group">
                <form method="GET" action="" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin: 0; width: 100%;">
                    
                    <div class="search-wrapper">
                        <i class="fas fa-user-graduate" style="left: 15px;"></i>
                        <select name="id" class="search-input" style="padding-left: 38px; width: auto; min-width: 220px;" onchange="this.form.submit()" required>
                            <option value="">-- Cari nama peserta... --</option>
                            <?php if ($list_siswa && $list_siswa->num_rows > 0): ?>
                                <?php while ($row = $list_siswa->fetch_assoc()): ?>
                                    <option value="<?= $row['id'] ?>" <?= ($siswa_id == $row['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($row['nama']) ?> (<?= htmlspecialchars($row['kelas']) ?>)
                                    </option>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <option value="" disabled>Belum ada siswa bimbingan</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="search-wrapper">
                        <select name="bulan" class="search-input" style="width: auto; min-width: 140px;" onchange="this.form.submit()">
                            <?php 
                            $bulan_opsi = [
                                '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
                                '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
                                '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
                            ];
                            foreach ($bulan_opsi as $k => $v): ?>
                                <option value="<?= $k ?>" <?= ($k == $bulan) ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="search-wrapper">
                        <select name="tahun" class="search-input" style="width: auto; min-width: 100px;" onchange="this.form.submit()">
                            <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                                <option value="<?= $y ?>" <?= ($y == $tahun) ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                </form>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- AREA DETAIL DATA / EMPTY STATE -->
        <!-- ========================================================================= -->
        <?php if ($siswa_id > 0): ?>
            
            <?php if (!empty($error_akses)): ?>
                <div class="error-alert-box">
                    <i class="fas fa-exclamation-triangle fa-2x mb-2" style="display:block;"></i>
                    <h3 style="margin-top:0; font-size: 1.2rem; color:#0f172a;">Akses Ditolak</h3>
                    <p style="margin-bottom:0; color:#475569;"><?= $error_akses ?></p>
                </div>
            <?php else: ?>

                <!-- IDENTITAS CARD (Sesuai Referensi Terakhir) -->
                <div class="info-card-panel">
                    <p><strong>Siswa:</strong> <?php echo htmlspecialchars($dataSiswa['nama']); ?> (NISN: <?php echo htmlspecialchars($dataSiswa['nisn']); ?>)</p>
                    <p><strong>Kelas:</strong> <?php echo htmlspecialchars($dataSiswa['kelas']); ?></p>
                    <p><strong>Lokasi Tempat PKL:</strong> <?php echo htmlspecialchars($dataSiswa['nama_lokasi'] ?? 'Belum ada lokasi'); ?></p>
                    <p><strong>Guru Pembimbing:</strong> <?php echo htmlspecialchars($dataSiswa['nama_guru'] ?? 'Belum Ditentukan'); ?></p>
                </div>

                <!-- KPI Cards (Solid Colors) -->
                <div class="kpi-row">
                    <div class="kpi-card-new kpi-hadir">
                        <p>Hadir Tepat</p>
                        <h3><?= $stat_hadir; ?> Hari</h3>
                    </div>
                    <div class="kpi-card-new kpi-telat">
                        <p>Terlambat</p>
                        <h3><?= $stat_telat; ?> Hari</h3>
                    </div>
                    <div class="kpi-card-new kpi-alpa">
                        <p>Alpa (Tanpa Keterangan)</p>
                        <h3><?= $stat_alpa; ?> Hari</h3>
                    </div>
                    <div class="kpi-card-new kpi-sakit">
                        <p>Sakit</p>
                        <h3><?= $stat_sakit; ?> Hari</h3>
                    </div>
                    <div class="kpi-card-new kpi-izin">
                        <p>Izin Resmi</p>
                        <h3><?= $stat_izin; ?> Hari</h3>
                    </div>
                </div>

                <!-- Tabel Data & Filter Section -->
                <div class="glass-panel">
                    <div class="panel-header-inline-wrapper">
                        <div class="panel-title">
                            Rincian Log Kehadiran
                        </div>
                    </div>

                    <div class="table-container-fixed">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th class="col-tgl">Tanggal</th>
                                    <th class="col-status">Status</th>
                                    <th class="col-jam-m">Jam Masuk</th>
                                    <th class="col-foto-m">Foto M</th>
                                    <th class="col-jam-p">Jam Pulang</th>
                                    <th class="col-foto-p">Foto P</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($riwayat_table as $item): 
                                    $tgl_f = date('d M Y', strtotime($item['tanggal'])); // Format sesuai referensi
                                    $hari_f = $hari_indo[date('l', strtotime($item['tanggal']))];
                                ?>
                                    <tr>
                                        <td class="date-cell">
                                            <strong style="color: #0f172a; font-size: 13px;"><?= $tgl_f ?></strong><br>
                                            <span style="font-size: 11px; color:#64748b;"><?= $hari_f ?></span>
                                        </td>

                                        <?php if (in_array($item['status'], ['Hadir', 'Telat'])): ?>
                                            <td>
                                                <span class="status-pill <?= ($item['status'] == 'Hadir') ? 'status-pill-hadir' : 'status-pill-telat' ?>"><?= $item['status'] ?></span>
                                            </td>
                                            <td>
                                                <?php if($item['data'] && !empty($item['data']['jam_masuk'])): ?>
                                                    <div class="time-flex-container">
                                                        <span class="time-text-main"><?= htmlspecialchars($item['data']['jam_masuk']) ?></span>
                                                        <?= $item['ket_waktu'] ?>
                                                    </div>
                                                <?php else: ?><span style="color:#cbd5e1;">—</span><?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if($item['data'] && !empty($item['data']['keterangan'])): 
                                                    $nama_aman = htmlspecialchars($dataSiswa['nama'], ENT_QUOTES, 'UTF-8');
                                                ?>
                                                    <div class="foto-thumbnail" onclick="openFotoModal('../uploads/absensi/<?= htmlspecialchars($item['data']['keterangan']) ?>', '<?= $nama_aman ?> (Masuk)')">
                                                        <img src="../uploads/absensi/<?= htmlspecialchars($item['data']['keterangan']) ?>" loading="lazy" alt="M">
                                                    </div>
                                                <?php else: ?><div class="foto-not-available"></div><?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if($item['data'] && !empty($item['data']['jam_pulang'])): ?>
                                                    <div class="time-flex-container">
                                                        <span class="time-text-main"><?= htmlspecialchars($item['data']['jam_pulang']) ?></span>
                                                        <?= $item['ket_pulang'] ?>
                                                    </div>
                                                <?php else: ?><span style="color: #94a3b8; font-size:11px;">Belum Pulang</span><?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if($item['data'] && !empty($item['data']['keterangan2'])): ?>
                                                    <div class="foto-thumbnail" onclick="openFotoModal('../uploads/absensi/<?= htmlspecialchars($item['data']['keterangan2']) ?>', '<?= $nama_aman ?> (Pulang)')">
                                                        <img src="../uploads/absensi/<?= htmlspecialchars($item['data']['keterangan2']) ?>" loading="lazy" alt="P">
                                                    </div>
                                                <?php else: ?><div class="foto-not-available"></div><?php endif; ?>
                                            </td>

                                        <?php else: 
                                            // Tampilan Khusus Untuk Baris Libur / Belum Mulai / Selesai dll. (Bersih, CENTERED)
                                            $icon = "fa-info-circle";
                                            $text_display = strtoupper($item['status'] ?: '-');
                                            
                                            if ($item['status'] == 'Alpa') { $icon = "fa-times-circle"; $text_display = "ALPA T/K"; }
                                            elseif ($item['status'] == 'Sakit') { $icon = "fa-procedures"; }
                                            elseif ($item['status'] == 'Izin') { $icon = "fa-envelope-open-text"; }
                                            elseif ($item['status'] == 'Libur Nasional') { $icon = "fa-calendar-times"; $text_display = "LIBUR NASIONAL: " . strtoupper($item['ket_libur']); }
                                            elseif ($item['status'] == 'Libur Akhir Pekan') { $icon = "fa-info-circle"; }
                                            elseif ($item['status'] == 'Belum Mulai') { $icon = "fa-info-circle"; $text_display = "BELUM"; }
                                            elseif ($item['status'] == 'Selesai') { $icon = "fa-flag-checkered"; $text_display = "PKL BERAKHIR"; }
                                        ?>
                                            <td colspan="5" style="background-color: #f8fafc !important; color: #334155; text-align: center !important; vertical-align: middle;">
                                                <div style="display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                                                    <i class="fas <?= $icon ?>" style="color: #64748b;"></i>
                                                    <span style="font-weight: 700; font-size:12.5px; letter-spacing: 0.5px;"><?= $text_display ?></span>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php endif; ?> <!-- End If Error Akses -->
            
        <?php else: ?>
            <!-- EMPTY STATE: JIKA BELUM ADA SISWA YANG DIPILIH -->
            <div class="glass-panel" style="text-align: center; padding: 60px 20px !important;">
                <i class="fas fa-hand-pointer fa-3x" style="color: #cbd5e1; margin-bottom: 20px;"></i>
                <h2 style="color: #0f172a; margin: 0; font-size: 1.3rem;">Tidak ada data yang ditampilkan</h2>
                <p style="color: #64748b; font-size: 13px; margin-top: 8px;">Silakan pilih nama siswa pada kotak pencarian di atas untuk melihat rekam jejak presensinya.</p>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- Modal Foto Preview -->
<div id="fotoModal" class="foto-modal">
    <div class="foto-modal-content">
        <div class="foto-modal-header">
            <h5 style="margin:0; font-weight:700; color:var(--mantap-blue-dark); font-size:1.1rem;">Preview Foto Presensi</h5>
            <button class="foto-modal-close" onclick="closeFotoModal()">&times;</button>
        </div>
        <div style="text-align: center; padding: 20px; box-sizing: border-box;">
            <img id="modalFotoImg" src="" alt="Foto Absen" style="max-width: 100%; height: auto; border-radius: 8px; border: 1px solid #cbd5e1; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <div style="margin-top: 15px; padding: 10px; background: #f8fafc; border-radius: 6px; border: 1px solid #cbd5e1; font-weight: 700; color: var(--mantap-blue-dark); text-transform: uppercase; font-size: 12.5px;" id="modalFotoName"></div>
        </div>
    </div>
</div>

<?php include 'panel/footer.php'; ?>

<script>
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
        if (modal) {
            modal.addEventListener('click', function(event) {
                if (event.target === modal) closeFotoModal();
            });
        }
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && modal.style.display === 'block') closeFotoModal();
        });
    });
</script>
</body>
</html>