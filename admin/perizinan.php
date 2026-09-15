<?php
// admin/perizinan.php
include 'auth-check.php'; 
include '../config/db-koneksi.php'; 

$current_user = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin';
$current_user_id = $_SESSION['user_id'] ?? 0; // Ditambahkan untuk kebutuhan log
$current_user_level = isset($_SESSION['level']) ? strtolower($_SESSION['level']) : 'admin';

// ---------------------------------------------------------------------
// 1. AUTO-CREATE KOLOM LOG VALIDATOR (ANTI-ERROR)
// ---------------------------------------------------------------------
$check_col = $koneksi->query("SHOW COLUMNS FROM pengajuan_izin LIKE 'validator_name'");
if ($check_col && $check_col->num_rows == 0) {
    $koneksi->query("ALTER TABLE pengajuan_izin ADD COLUMN validator_name VARCHAR(100) NULL DEFAULT NULL");
    $koneksi->query("ALTER TABLE pengajuan_izin ADD COLUMN validation_time DATETIME NULL DEFAULT NULL");
}

// ---------------------------------------------------------------------
// 2. LOGIKA PROSES UPDATE STATUS IZIN & KIRIM WHATSAPP KE SISWA
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status_izin'])) {
    $id_izin = (int)$_POST['id_izin'];
    $status = $_POST['status'];
    
    // Ambil nama siswa dan jenis izin untuk Log
    $nama_siswa_log = "Siswa (ID: " . $id_izin . ")";
    $jenis_izin_log = "Izin";
    $q_info = $koneksi->query("SELECT p.nama, i.jenis_izin FROM pengajuan_izin i JOIN peserta_didik p ON i.siswa_id = p.id WHERE i.id = '$id_izin'");
    if ($q_info && $q_info->num_rows > 0) {
        $info = $q_info->fetch_assoc();
        $nama_siswa_log = $info['nama'];
        $jenis_izin_log = $info['jenis_izin'];
    }

    // Jika dikembalikan ke "Menunggu", kosongkan log validatornya
    $validator = ($status === 'Menunggu') ? NULL : $current_user;
    $val_time = ($status === 'Menunggu') ? NULL : date('Y-m-d H:i:s');

    $stmt = $koneksi->prepare("UPDATE pengajuan_izin SET status = ?, validator_name = ?, validation_time = ? WHERE id = ?");
    $stmt->bind_param("sssi", $status, $validator, $val_time, $id_izin);
    
    if ($stmt->execute()) {
        
        // --- TRIGGER LOG AKTIVITAS (UPDATE STATUS IZIN) ---
        $log_action = ($status === 'Menunggu') ? "Membatalkan proses verifikasi dan mengembalikan status" : "Memverifikasi dan mengubah status";
        catatLog($koneksi, $current_user_id, "{$log_action} pengajuan {$jenis_izin_log} siswa: {$nama_siswa_log} (Status Akhir: {$status})");

        // --- KIRIM NOTIFIKASI WHATSAPP KE SISWA JIKA DISETUJUI / DITOLAK ---
        if ($status === 'Disetujui' || $status === 'Ditolak') {
            $q_siswa = $koneksi->query("
                SELECT p.nama, p.no_hp, i.jenis_izin, i.tgl_mulai, i.tgl_selesai, i.alasan 
                FROM pengajuan_izin i
                JOIN peserta_didik p ON i.siswa_id = p.id
                WHERE i.id = '$id_izin'
            ");
            
            if ($q_siswa && $q_siswa->num_rows > 0) {
                $d_siswa = $q_siswa->fetch_assoc();
                $no_hp_siswa = $d_siswa['no_hp'];
                $nama_siswa = $d_siswa['nama'];
                
                // Format nomor WA
                $no_wa = preg_replace('/[^0-9]/', '', $no_hp_siswa);
                if (strpos($no_wa, '0') === 0) {
                    $no_wa = '62' . substr($no_wa, 1);
                }

                if (!empty($no_wa)) {
                    $token_fonnte = 'enmpN6YNngTwkYpWzzcf';
                    $tgl_m = date('d M Y', strtotime($d_siswa['tgl_mulai']));
                    $tgl_s = date('d M Y', strtotime($d_siswa['tgl_selesai']));
                    
                    $pesan = "Halo *$nama_siswa*,\n\n";
                    $pesan .= "Pengajuan *{$d_siswa['jenis_izin']}* Anda telah diproses oleh Pembimbing/Sekolah.\n\n";
                    $pesan .= "📅 *Tanggal:* $tgl_m s/d $tgl_s\n";
                    $pesan .= "📝 *Alasan:* {$d_siswa['alasan']}\n";
                    $pesan .= "📌 *Status Akhir:* *$status*\n\n";
                    
                    if ($status === 'Disetujui') {
                        $pesan .= "Izin Anda telah sah tercatat di sistem presensi. Terima kasih.";
                    } else {
                        $pesan .= "Mohon maaf, pengajuan Anda tidak dapat disetujui. Silakan hubungi guru pembimbing Anda untuk konfirmasi lebih rinci.";
                    }

                    $curl = curl_init();
                    curl_setopt_array($curl, array(
                      CURLOPT_URL => 'https://api.fonnte.com/send',
                      CURLOPT_RETURNTRANSFER => true,
                      CURLOPT_ENCODING => '',
                      CURLOPT_MAXREDIRS => 10,
                      CURLOPT_TIMEOUT => 0,
                      CURLOPT_FOLLOWLOCATION => true,
                      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                      CURLOPT_CUSTOMREQUEST => 'POST',
                      CURLOPT_POSTFIELDS => array(
                        'target' => $no_wa,
                        'message' => $pesan,
                        'countryCode' => '62', 
                      ),
                      CURLOPT_HTTPHEADER => array(
                        "Authorization: $token_fonnte"
                      ),
                    ));
                    
                    $response = curl_exec($curl);
                    $error = curl_error($curl);
                    curl_close($curl);
                    
                    // --- SIMPAN LOG WHATSAPP KE DATABASE ---
                    $status_kirim = ($error || strpos(strtolower($response), 'false') !== false) ? 'Gagal' : 'Terkirim';
                    $response_db = $error ? $error : $response;
                    $jenis_pesan = "Validasi Izin ($status)";

                    $stmt_log = $koneksi->prepare("INSERT INTO log_whatsapp (target_nomor, jenis_pesan, pesan, status, response_api) VALUES (?, ?, ?, ?, ?)");
                    if ($stmt_log) {
                        $stmt_log->bind_param("sssss", $no_wa, $jenis_pesan, $pesan, $status_kirim, $response_db);
                        $stmt_log->execute();
                        $stmt_log->close();
                    }
                }
            }
        }
        // -------------------------------------------------------------------

        header("Location: perizinan.php?status=success");
        exit;
    } else {
        header("Location: perizinan.php?status=error");
        exit;
    }
}

// ---------------------------------------------------------------------
// 3. LOGIKA PROSES EDIT TANGGAL IZIN
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tanggal_izin'])) {
    $id_izin = (int)$_POST['id_izin'];
    $tgl_mulai = $_POST['tgl_mulai'];
    $tgl_selesai = $_POST['tgl_selesai'];
    
    // Ambil nama siswa untuk log
    $nama_siswa_log = "Siswa (ID: " . $id_izin . ")";
    $q_info = $koneksi->query("SELECT p.nama, i.jenis_izin FROM pengajuan_izin i JOIN peserta_didik p ON i.siswa_id = p.id WHERE i.id = '$id_izin'");
    if ($q_info && $q_info->num_rows > 0) {
        $info = $q_info->fetch_assoc();
        $nama_siswa_log = $info['nama'];
    }

    $stmt = $koneksi->prepare("UPDATE pengajuan_izin SET tgl_mulai = ?, tgl_selesai = ? WHERE id = ?");
    $stmt->bind_param("ssi", $tgl_mulai, $tgl_selesai, $id_izin);
    
    if ($stmt->execute()) {
        
        // --- TRIGGER LOG AKTIVITAS (UPDATE RENTANG WAKTU IZIN) ---
        catatLog($koneksi, $current_user_id, "Mengubah/mengedit rentang tanggal pengajuan izin siswa: {$nama_siswa_log} (Menjadi tanggal: {$tgl_mulai} s/d {$tgl_selesai})");

        header("Location: perizinan.php?status=success_edit_date");
        exit;
    } else {
        header("Location: perizinan.php?status=error_edit_date");
        exit;
    }
}

// ---------------------------------------------------------------------
// 4. SANITASI & VALIDASI INPUT TANGGAL FILTER
// ---------------------------------------------------------------------
$filter_tgl = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_tgl)) {
    $filter_tgl = date('Y-m-d');
}

$date_check = DateTime::createFromFormat('Y-m-d', $filter_tgl);
if (!$date_check || $date_check->format('Y-m-d') !== $filter_tgl) {
    $filter_tgl = date('Y-m-d');
}

// ---------------------------------------------------------------------
// 5. TENTUKAN FILTER BERDASARKAN LEVEL USER
// ---------------------------------------------------------------------
$guru_filter_id = 0;
$guru_filter_clause = "";

if ($current_user_level == 'pembimbing' || $current_user_level == 'guru') {
    $stmt_get_guru_id = $koneksi->prepare("SELECT guru_id FROM guru WHERE user_id = ?");
    if ($stmt_get_guru_id) {
        $stmt_get_guru_id->bind_param("i", $current_user_id);
        $stmt_get_guru_id->execute();
        $result_guru_id = $stmt_get_guru_id->get_result();
        if ($result_guru_id->num_rows > 0) {
            $guru_filter_id = $result_guru_id->fetch_assoc()['guru_id'];
            $guru_filter_clause = " AND l.guru_id = {$guru_filter_id} ";
        } else {
            $guru_filter_clause = " AND 1 = 0 "; 
        }
        $stmt_get_guru_id->close();
    }
} 

// 6. QUERY DATA PERIZINAN BERDASARKAN FILTER TANGGAL
$query_izin = "
    SELECT 
        i.id, i.tgl_mulai, i.tgl_selesai, i.jenis_izin, i.created_at, i.alasan, i.status, i.file_pendukung, i.validator_name, i.validation_time,
        p.nama AS nama_siswa, p.nisn, p.kelas,
        l.nama_lokasi
    FROM pengajuan_izin i
    JOIN peserta_didik p ON i.siswa_id = p.id
    LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
    WHERE ('$filter_tgl' BETWEEN i.tgl_mulai AND i.tgl_selesai OR DATE(i.created_at) = '$filter_tgl')
    {$guru_filter_clause}
    ORDER BY i.created_at DESC
";
$izin_result = $koneksi->query($query_izin);

// Ekstrak Data ke Array sekaligus Hitung Statistik Card (Otomatis berdasarkan hari yang di-filter)
$semua_data = [];
$stat_total = 0;
$stat_menunggu = 0;
$stat_disetujui = 0;
$stat_ditolak = 0;

if ($izin_result && $izin_result->num_rows > 0) {
    while ($row = $izin_result->fetch_assoc()) {
        
        // Cek fallback status default
        $status_asli = trim((string)$row['status']);
        if (empty($status_asli) || $status_asli === 'Pending') {
            $row['status'] = 'Menunggu'; 
        }
        
        $semua_data[] = $row;
        $stat_total++;
        
        if ($row['status'] == 'Menunggu') $stat_menunggu++;
        elseif ($row['status'] == 'Disetujui') $stat_disetujui++;
        elseif ($row['status'] == 'Ditolak') $stat_ditolak++;
    }
}

function formatTanggal($tanggal) {
    if (!$tanggal || $tanggal == '0000-00-00') return '-';
    $timestamp = strtotime($tanggal);
    $bulan_indo = [
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 
        'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'
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
    <title>Manajemen Perizinan PKL | Si Mantap PKL</title>
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
            flex-wrap: nowrap; /* Kunci Sejajar di Desktop */
            gap: 15px;
        }

        .page-header-controls h1 {
            font-weight: 700;
            color: #0f172a;
            font-size: 1.8rem; 
            margin: 0;
            position: relative;
            white-space: nowrap;
        }

        .page-header-controls h1::after {
            content: ''; position: absolute; left: 0; bottom: -5px; width: 50px; height: 4px; background: var(--mantap-blue-main); border-radius: 2px;
        }

        /* --- STYLING HEADER ACTIONS (SEARCH, FILTER TANGGAL, EXPORT) --- */
        .header-actions-group { 
            display: flex; 
            flex-direction: row;
            gap: 10px; 
            align-items: center; 
            flex-wrap: nowrap; /* Mencegah tombol turun ke bawah */
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

        /* PERBAIKAN FORM SARING DATA (Tanpa Border Putih, Transparent) */
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

        /* --- STATISTIC CARDS STYLING --- */
        .kpi-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 25px;
            width: 100%;
        }

        .kpi-card-new {
            padding: 20px;
            border-radius: 12px;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
            transition: 0.2s;
        }
        
        .kpi-card-new:hover { transform: translateY(-2px); }
        .kpi-card-new i {
            position: absolute;
            right: -10px;
            top: -10px;
            font-size: 3.5rem;
            opacity: 0.18;
        }
        
        .kpi-card-new h3 { font-size: 1.6rem; font-weight: 700; margin: 0; line-height: 1; }
        .kpi-card-new p { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; margin: 0; font-weight: 600; opacity: 0.9; }

        .glass-panel { 
            background: white !important; 
            padding: 25px !important; 
            border-radius: 16px !important; 
            border: 2px solid #e2e8f0 !important; 
            box-sizing: border-box; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); 
            width: 100%;
        }
        
        .table-container-fixed { 
            width: 100%; 
            overflow-x: auto; 
            box-sizing: border-box; 
            border: none !important; 
        }

        /* OUTLINE TEBAL BERWARNA BIRU UTAMA & PERBAIKAN LEBAR KOLOM */
        .custom-table { 
            width: 100%; 
            table-layout: auto;
            border-collapse: collapse; 
            background: white; 
            border-radius: 4px; 
            overflow: hidden; 
            border: 2px solid #1e40af; 
        }
        .custom-table th { 
            background: #1e40af; 
            color: white; 
            padding: 14px 12px; 
            font-size: 13px; 
            text-transform: uppercase; 
            font-weight: 700; 
            text-align: center; 
            border: 1px solid #cbd5e1; 
            box-sizing: border-box;
            white-space: nowrap; 
        }

        .custom-table td { 
            padding: 12px; 
            font-size: 13px; 
            vertical-align: middle; 
            text-align: center; 
            border: 1px solid #cbd5e1; 
            color: #0f172a; 
            background-color: white !important; 
            box-sizing: border-box;
        }
        .custom-table tbody tr:hover td { background-color: #f8fafc !important; }

        /* UTILITAS TEXT & BADGE */
        .text-nowrap { white-space: nowrap !important; }
        .student-name-title { display: block; font-weight: 700; color: #0f172a; font-size: 13.5px; margin-bottom: 3px; text-transform: uppercase; }
        .badge-info-pill { background-color: #f1f5f9; color: #475569; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block; }
        
        .status-badge { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 700; color: white; display: inline-block; }
        .status-badge.status-sakit { background-color: #64748b; } 
        .status-badge.status-izin { background-color: #ea580c; }
        
        .app-badge { padding: 6px 12px; border-radius: 6px; font-size: 11.5px; font-weight: 700; color: white; display: inline-block; white-space: nowrap; }
        .app-menunggu { background-color: #f59e0b; }
        .app-disetujui { background-color: #22c55e; }
        .app-ditolak { background-color: #ef4444; }

        .alasan-cell { color: #475569; font-style: italic; text-align: left !important; min-width: 150px; }
        
        /* TOMBOL LIHAT BERKAS */
        .btn-view-doc { padding: 6px 12px; background-color: #0ea5e9; color: white !important; border-radius: 6px; text-decoration: none; font-size: 11.5px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; border: none; cursor: pointer; transition: 0.2s;}
        .btn-view-doc:hover { background-color: #0284c7; transform: translateY(-1px); }
        
        /* GAYA TOMBOL PROSES (DISETUJUI/DITOLAK) YANG BARU */
        .dashboard-btn-group { display: flex; gap: 6px; justify-content: center; align-items: center; margin: 0; flex-wrap: nowrap; }
        .btn-action-rect { border: none; padding: 6px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; color: white !important; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: 0.2s; white-space: nowrap; }
        .btn-check-success { background-color: #22c55e; }
        .btn-check-success:hover { background-color: #16a34a; transform: translateY(-1px); box-shadow: 0 4px 6px rgba(34,197,94,0.2); }
        .btn-cross-danger { background-color: #ef4444; }
        .btn-cross-danger:hover { background-color: #dc2626; transform: translateY(-1px); box-shadow: 0 4px 6px rgba(239,68,68,0.2); }
        .btn-undo-primary { background-color: var(--mantap-blue-main); }
        .btn-undo-primary:hover { background-color: #1e3a8a; transform: translateY(-1px); box-shadow: 0 4px 6px rgba(30,64,175,0.2); }

        /* ENGINE MODALS */
        .mantap-modal {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(15, 23, 42, 0.6); z-index: 999999 !important; overflow-x: hidden; overflow-y: auto; box-sizing: border-box !important; backdrop-filter: blur(3px);
        }
        .mantap-modal-dialog { position: relative; width: 90%; max-width: 650px; margin: 3rem auto; box-sizing: border-box !important; }
        .mantap-modal-content { 
            background-color: white; padding: 22px; border-radius: 12px; border: 2px solid #cbd5e1; width: 100%; height: auto !important; 
            box-sizing: border-box !important; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15);
        }
        .mantap-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; box-sizing: border-box !important; }
        .mantap-modal-header h2 { font-size: 1.4rem; margin: 0; color: #0f172a; font-weight: 700; }
        .close-modal-btn { background: none; border: none; font-size: 1.7rem; color: #64748b; cursor: pointer; line-height: 1; }
        .preview-body { width: 100%; text-align: center; box-sizing: border-box; }
        .preview-body img { max-width: 100%; height: auto; border-radius: 6px; border: 1px solid #cbd5e1; }
        .preview-body iframe { width: 100%; height: 450px; border-radius: 6px; border: 1px solid #cbd5e1; }

        /* Edit Date Specific */
        .modal-form-group { text-align: left; margin-bottom: 15px; }
        .modal-form-group label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; color: #0f172a; }

        /* =========================================================================
           SINKRONISASI TOTAL: MODAL & TABEL TETAP UTUH DI HP
        ========================================================================= */
        @media (max-width: 768px) {
            body { padding-top: 60px !important; }
            .admin-main-content { padding: 15px 12px 25px 12px !important; }
            
            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; flex-wrap: wrap !important; gap: 15px !important; padding: 5px 0px !important; }
            .page-header-controls h1 { font-size: 1.4rem !important; margin-bottom: 5px; white-space: normal; }
            
            /* Pada HP, menu pencarian & filter diturunkan ke bawah dan dibuat lebar penuh */
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
            
            .table-container-fixed { display: block !important; width: 100% !important; overflow-x: auto !important; border: none !important; }
            .custom-table { min-width: 900px !important; }
            
            .dashboard-btn-group { flex-direction: column; gap: 5px; }
            .btn-action-rect { width: 100%; justify-content: center; }
            
            .mantap-modal-dialog { margin: 1.5rem auto; width: 95%; max-width: 100%; }
            .mantap-modal-content { padding: 16px; }
            .preview-body iframe { height: 360px; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; include 'panel/navbar.php'; ?>

<div class="main-content-wrapper">
    <div class="admin-main-content"> 
        
        <div class="page-header-controls">
            <h1><i class="fas fa-file-signature" style="color: var(--mantap-blue-main); margin-right: 4px;"></i>Validasi Perizinan Siswa</h1>
            
            <div class="header-actions-group">
                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" class="search-input" placeholder="Cari nama siswa..." onkeyup="filterTable()">
                </div>
                
                <form class="inline-filter-form" method="GET" action="">
                    <input type="date" name="tanggal" value="<?php echo htmlspecialchars($filter_tgl); ?>" required>
                    <button type="submit" class="btn-filter-acc"><i class="fas fa-filter"></i> Saring Data</button>
                </form>

                <button type="button" class="btn-aksi-top" onclick="alert('Fitur Unduh Rekap Izin sedang dalam penyempurnaan/tersedia di halaman lain.')">
                    <i class="fas fa-file-export"></i> Unduh Rekap Perizinan
                </button>
            </div>
        </div>

        <div class="kpi-row">
            <div class="kpi-card-new" style="background: linear-gradient(135deg, #1e40af 0%, #1d4ed8 100%);">
                <i class="fas fa-envelope-open-text"></i>
                <p>Total Pengajuan</p>
                <h3><?php echo $stat_total; ?></h3>
            </div>
            <div class="kpi-card-new" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                <i class="fas fa-clock"></i>
                <p>Perlu Proses</p>
                <h3><?php echo $stat_menunggu; ?></h3>
            </div>
            <div class="kpi-card-new" style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);">
                <i class="fas fa-check-circle"></i>
                <p>Disetujui</p>
                <h3><?php echo $stat_disetujui; ?></h3>
            </div>
            <div class="kpi-card-new" style="background: linear-gradient(135deg, #ef4444 0%, #dc3545 100%);">
                <i class="fas fa-times-circle"></i>
                <p>Ditolak</p>
                <h3><?php echo $stat_ditolak; ?></h3>
            </div>
        </div>

        <div class="glass-panel">
            <div class="table-container-fixed">
                <?php if (count($semua_data) > 0): ?>
                <table class="custom-table" id="izinTable">
                    <thead>
                        <tr>
                            <th style="width: 50px;">NO</th>
                            <th style="text-align: left; padding-left: 12px;">Siswa / Kelas</th> 
                            <th style="text-align: left;">Mitra Penempatan</th> 
                            <th>Kategori</th> 
                            <th>Rentang Tanggal</th>
                            <th style="text-align: left;">Alasan Dokumen</th> 
                            <th>Berkas</th> 
                            <th>Status & Log Validasi</th>
                            <th>Proses Verifikasi</th> 
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($semua_data as $row): ?>
                        <tr>
                            <td style="font-weight: 700; text-align: center;"><?php echo $no++; ?></td>
                            <td style="text-align: left; padding-left: 12px; min-width: 180px;">
                                <span class="student-name-title"><?php echo htmlspecialchars($row['nama_siswa']); ?></span>
                                <span class="badge-info-pill mt-1"><?php echo htmlspecialchars($row['kelas']); ?></span>
                            </td>
                            <td style="text-align: left; min-width: 160px;">
                                <small class="fw-semibold text-secondary"><i class="fas fa-building me-1 opacity-50"></i><?php echo htmlspecialchars($row['nama_lokasi'] ?? 'Belum Ditentukan'); ?></small>
                            </td>
                            <td class="text-nowrap">
                                <span class="status-badge status-<?php echo strtolower($row['jenis_izin']); ?>">
                                    <?php echo htmlspecialchars($row['jenis_izin']); ?>
                                </span>
                            </td>
                            <td class="text-nowrap">
                                <b class="text-dark" style="font-size:12.5px;"><?php echo formatTanggal($row['tgl_mulai']); ?></b><br>
                                <small class="text-muted" style="font-size: 11px;">s.d. <?php echo formatTanggal($row['tgl_selesai']); ?></small><br>
                                
                                <button type="button" class="btn btn-sm border mt-1 open-edit-date-btn" 
                                        style="font-size: 10px; padding: 2px 6px; border-radius: 4px; background: white; color: #475569; cursor: pointer;"
                                        data-id="<?php echo $row['id']; ?>"
                                        data-mulai="<?php echo $row['tgl_mulai']; ?>"
                                        data-selesai="<?php echo $row['tgl_selesai']; ?>"
                                        data-nama="<?php echo htmlspecialchars($row['nama_siswa']); ?>">
                                    <i class="fas fa-edit text-primary"></i> Edit Tgl
                                </button>
                            </td>
                            <td class="alasan-cell"><?php echo htmlspecialchars($row['alasan']); ?></td>
                            <td class="text-nowrap">
                                <?php if($row['file_pendukung']): ?>
                                    <button type="button" class="btn-view-doc open-preview-btn" 
                                            data-siswa="<?php echo htmlspecialchars($row['nama_siswa']); ?>"
                                            data-file="../uploads/izin/<?php echo $row['file_pendukung']; ?>">
                                        <i class="fa-solid fa-file-image"></i> Lihat
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted font-monospace small">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap">
                                <span class="app-badge app-<?php echo strtolower($row['status']); ?> mb-1">
                                    <?php echo $row['status']; ?>
                                </span>
                                <?php if (!empty($row['validator_name'])): ?>
                                    <div style="font-size: 10px; color: #475569; background: #f1f5f9; padding: 4px 6px; border-radius: 4px; border: 1px solid #cbd5e1; display: inline-block; margin-top: 2px;">
                                        <i class="fas fa-user-check" style="color: #3b82f6;"></i> <?php echo htmlspecialchars($row['validator_name']); ?><br>
                                        <span style="font-size: 9px; color: #94a3b8;"><?php echo date('d/m/y H:i', strtotime($row['validation_time'])); ?></span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap">
                                <form action="" method="POST" id="form_izin_<?php echo $row['id']; ?>" class="dashboard-btn-group">
                                    <input type="hidden" name="update_status_izin" value="1">
                                    <input type="hidden" name="id_izin" value="<?php echo $row['id']; ?>">
                                    
                                    <?php if($row['status'] == 'Menunggu'): ?>
                                        <button type="button" class="btn-action-rect btn-check-success" onclick="verifikasiIzin('<?php echo $row['id']; ?>', 'Disetujui')" title="Setujui Izin">
                                            <i class="fa fa-check"></i> Setujui
                                        </button>
                                        <button type="button" class="btn-action-rect btn-cross-danger" onclick="verifikasiIzin('<?php echo $row['id']; ?>', 'Ditolak')" title="Tolak Izin">
                                            <i class="fa fa-times"></i> Tolak
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn-action-rect btn-undo-primary" onclick="verifikasiIzin('<?php echo $row['id']; ?>', 'Menunggu')" title="Batalkan & Kembalikan ke Menunggu">
                                            <i class="fa fa-undo"></i> Batal Proses
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px 20px; border: 2px dashed #cbd5e1; background: #fff; border-radius: 8px;">
                        <i class="fas fa-folder-open fa-2x text-muted mb-2"></i>
                        <p class="text-muted small m-0" style="font-style: italic;">Belum ada rekam pengajuan izin yang masuk untuk tanggal ini.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div> 

    </div>
</div>

<div class="mantap-modal" id="previewBerkasModal">
    <div class="mantap-modal-dialog">
        <div class="mantap-modal-content">
            <div class="mantap-modal-header">
                <h2 id="modalSiswaTitle" style="font-size: 1.2rem;">Berkas Lampiran Izin</h2>
                <button type="button" class="close-modal-btn" id="closePreviewModalBtn">&times;</button>
            </div>
            <div class="preview-body" id="modalPreviewBody"></div>
        </div>
    </div>
</div>

<div class="mantap-modal" id="editDateModal">
    <div class="mantap-modal-dialog" style="max-width: 420px;">
        <div class="mantap-modal-content">
            <div class="mantap-modal-header">
                <h2 style="font-size: 1.2rem;"><i class="fas fa-calendar-alt text-primary me-2"></i> Edit Tanggal Izin</h2>
                <button type="button" class="close-modal-btn" id="closeEditDateModalBtn">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="update_tanggal_izin" value="1">
                <input type="hidden" name="id_izin" id="edit_date_id">
                <p class="mb-3 text-muted" style="font-size: 13px;">Siswa: <strong id="edit_date_nama" class="text-dark"></strong></p>
                
                <div class="modal-form-group">
                    <label>Tanggal Mulai</label>
                    <input type="date" name="tgl_mulai" id="edit_tgl_mulai" required style="width:100%; padding:10px; border-radius:6px; border:1px solid #cbd5e1;">
                </div>
                <div class="modal-form-group">
                    <label>Tanggal Selesai</label>
                    <input type="date" name="tgl_selesai" id="edit_tgl_selesai" required style="width:100%; padding:10px; border-radius:6px; border:1px solid #cbd5e1;">
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="submit" class="btn-action-rect btn-undo-primary" style="padding: 10px 20px; font-size: 13px;"><i class="fas fa-save me-1"></i> Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>
            
<script>
    // --- FITUR PENCARIAN REAL-TIME ---
    function filterTable() {
        var input, filter, table, tr, td, i, txtValue;
        input = document.getElementById("searchInput");
        filter = input.value.toUpperCase();
        table = document.getElementById("izinTable");
        
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

    document.addEventListener("DOMContentLoaded", function() {
        // Tampilkan SweetAlert Jika Ada Notifikasi Perubahan Status
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('status') === 'success') {
            Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Status perizinan berhasil diperbarui dan dicatat.', confirmButtonColor: '#1e40af' });
            window.history.replaceState({}, document.title, window.location.pathname); 
        } else if (urlParams.get('status') === 'success_edit_date') {
            Swal.fire({ icon: 'success', title: 'Tanggal Diubah!', text: 'Rentang tanggal izin berhasil diperbarui.', confirmButtonColor: '#1e40af' });
            window.history.replaceState({}, document.title, window.location.pathname);
        } else if (urlParams.get('status') === 'error' || urlParams.get('status') === 'error_edit_date') {
            Swal.fire({ icon: 'error', title: 'Gagal!', text: 'Terjadi kesalahan sistem saat memproses data.', confirmButtonColor: '#1e40af' });
            window.history.replaceState({}, document.title, window.location.pathname);
        }

        // Logic Modal Preview Berkas
        const modalPreview = document.getElementById('previewBerkasModal');
        const closePreviewBtn = document.getElementById('closePreviewModalBtn');
        const titleContainer = document.getElementById('modalSiswaTitle');
        const bodyContainer = document.getElementById('modalPreviewBody');
        
        document.querySelectorAll('.open-preview-btn').forEach(button => {
            button.addEventListener('click', function() {
                const namaSiswa = this.getAttribute('data-siswa');
                const fileUrl = this.getAttribute('data-file');
                const fileExtension = fileUrl.split('.').pop().toLowerCase();
                
                titleContainer.innerHTML = `<i class="fa-solid fa-file-lines me-2" style="color:var(--mantap-blue-main);"></i> Berkas: ${namaSiswa}`;
                bodyContainer.innerHTML = ''; 

                if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExtension)) {
                    bodyContainer.innerHTML = `<img src="${fileUrl}" alt="Surat Lampiran Izin">`;
                } else if (fileExtension === 'pdf') {
                    bodyContainer.innerHTML = `<iframe src="${fileUrl}"></iframe>`;
                } else {
                    bodyContainer.innerHTML = `<p class="text-muted font-monospace">Ekstensi file (.${fileExtension}) tidak mendukung pratinjau langsung.<br><a href="${fileUrl}" download class="btn-view-doc mt-2"><i class="fa-solid fa-download"></i> Unduh Berkas</a></p>`;
                }
                
                modalPreview.style.display = 'block';
            });
        });

        if(closePreviewBtn) closePreviewBtn.addEventListener('click', () => { modalPreview.style.display = 'none'; bodyContainer.innerHTML = ''; });

        // Logic Modal Edit Tanggal
        const modalEditDate = document.getElementById('editDateModal');
        const closeEditDateBtn = document.getElementById('closeEditDateModalBtn');
        
        document.querySelectorAll('.open-edit-date-btn').forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('edit_date_id').value = this.getAttribute('data-id');
                document.getElementById('edit_tgl_mulai').value = this.getAttribute('data-mulai');
                document.getElementById('edit_tgl_selesai').value = this.getAttribute('data-selesai');
                document.getElementById('edit_date_nama').textContent = this.getAttribute('data-nama');
                
                modalEditDate.style.display = 'block';
            });
        });

        if(closeEditDateBtn) closeEditDateBtn.addEventListener('click', () => { modalEditDate.style.display = 'none'; });

        // Global Window Click to close modals
        window.addEventListener('click', (e) => { 
            if (e.target === modalPreview) { modalPreview.style.display = 'none'; bodyContainer.innerHTML = ''; }
            if (e.target === modalEditDate) { modalEditDate.style.display = 'none'; }
        });
    });

    function verifikasiIzin(idForm, statusTarget) {
        let titleTxt = "Ubah Status?";
        let textTxt = `Ubah status pengajuan ini menjadi '${statusTarget}'?`;
        let confirmColor = '#1e40af';

        if (statusTarget === 'Disetujui') {
            titleTxt = "Setujui Perizinan?";
            textTxt = "Apakah Anda yakin? Tindakan ini otomatis mengubah rekap absensi kehadiran harian siswa bersangkutan dan mencatat nama Anda sebagai validator.";
            confirmColor = '#22c55e';
        } else if (statusTarget === 'Ditolak') {
            titleTxt = "Tolak Perizinan?";
            textTxt = "Berkas pendaftaran perizinan siswa ini akan ditolak oleh sistem dan mencatat nama Anda sebagai validator.";
            confirmColor = '#ef4444';
        }

        Swal.fire({
            title: titleTxt,
            text: textTxt,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: confirmColor,
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Proses!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                let form = document.getElementById('form_izin_' + idForm);
                let hiddenInput = document.createElement('input');
                hiddenInput.setAttribute('type', 'hidden');
                hiddenInput.setAttribute('name', 'status');
                hiddenInput.setAttribute('value', statusTarget);
                form.appendChild(hiddenInput);
                form.submit();
            }
        });
    }
</script>

<?php include 'panel/footer.php'; ?>
</body>
</html>