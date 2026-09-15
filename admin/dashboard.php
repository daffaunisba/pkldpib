<?php 
// admin/dashboard.php
include 'auth-check.php'; 
include '../config/db-koneksi.php';

// Ambil username dan level dari sesi
$current_user = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin';
$user_level   = isset($_SESSION['level']) ? strtolower($_SESSION['level']) : 'pembimbing'; 

// =================================================================================
// --- API CHATBOX (AJAX HANDLER - ANTI ERROR / BULLETPROOF) ---
// =================================================================================
if (isset($_POST['chat_action'])) {
    ob_start(); 
    header('Content-Type: application/json');
    
    // Auto-Create Tabel Chat
    try {
        $koneksi->query("CREATE TABLE IF NOT EXISTS bantuan_chat (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pengirim VARCHAR(100),
            penerima VARCHAR(100),
            role VARCHAR(50),
            pesan TEXT,
            waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status_baca INT DEFAULT 0
        )");
    } catch (Exception $e) {}

    try { $koneksi->query("ALTER TABLE bantuan_chat ADD COLUMN penerima VARCHAR(100) AFTER pengirim"); } catch(Exception $e){}
    try { $koneksi->query("ALTER TABLE bantuan_chat ADD COLUMN status_baca INT DEFAULT 0 AFTER waktu"); } catch(Exception $e){}
    try { $koneksi->query("UPDATE bantuan_chat SET penerima = 'admin' WHERE role != 'admin' AND penerima IS NULL"); } catch(Exception $e){}

    if ($_POST['chat_action'] == 'load_users') {
        try {
            $q = $koneksi->query("
                SELECT pengirim, SUM(CASE WHEN status_baca = 0 AND penerima = 'admin' THEN 1 ELSE 0 END) as unread_count 
                FROM bantuan_chat 
                WHERE role != 'admin' 
                GROUP BY pengirim 
                ORDER BY MAX(waktu) DESC
            ");
            $users = [];
            if ($q) {
                while($row = $q->fetch_assoc()) {
                    $users[] = [
                        'nama' => $row['pengirim'],
                        'unread' => (int)$row['unread_count']
                    ];
                }
            }
        } catch (Exception $e) { $users = []; } 

        while(ob_get_level()) ob_end_clean();
        echo json_encode($users); exit;
    }

    if ($_POST['chat_action'] == 'load') {
        $target = isset($_POST['target_user']) ? $koneksi->real_escape_string($_POST['target_user']) : '';
        $me = $koneksi->real_escape_string($current_user);
        $chats = [];
        
        try {
            if ($user_level === 'admin') {
                $koneksi->query("UPDATE bantuan_chat SET status_baca = 1 WHERE pengirim = '$target' AND penerima = 'admin' AND status_baca = 0");
                $q = $koneksi->query("SELECT * FROM bantuan_chat WHERE (pengirim = '$target' AND role != 'admin') OR (role = 'admin' AND penerima = '$target') ORDER BY waktu ASC");
            } else {
                $koneksi->query("UPDATE bantuan_chat SET status_baca = 1 WHERE pengirim = 'admin' AND penerima = '$me' AND status_baca = 0");
                $q = $koneksi->query("SELECT * FROM bantuan_chat WHERE (pengirim = '$me' AND role != 'admin') OR (role = 'admin' AND penerima = '$me') ORDER BY waktu ASC");
            }
            
            if ($q) {
                while($row = $q->fetch_assoc()) {
                    $chats[] = $row;
                }
            }
        } catch (Exception $e) {}

        while(ob_get_level()) ob_end_clean();
        echo json_encode($chats); exit;
    }

    if ($_POST['chat_action'] == 'send') {
        $pesan = $koneksi->real_escape_string($_POST['pesan']);
        $pengirim = $koneksi->real_escape_string($current_user);
        $role = $koneksi->real_escape_string($user_level);
        
        $penerima = 'admin'; 
        if ($role === 'admin') {
            $penerima = isset($_POST['target_user']) ? $koneksi->real_escape_string($_POST['target_user']) : '';
            $pengirim = 'admin'; 
        }
        
        if (!empty(trim($pesan)) && !empty(trim($penerima))) {
            try {
                $koneksi->query("INSERT INTO bantuan_chat (pengirim, penerima, role, pesan, status_baca) VALUES ('$pengirim', '$penerima', '$role', '$pesan', 0)");
            } catch (Exception $e) {
                try {
                    $koneksi->query("INSERT INTO bantuan_chat (pengirim, penerima, role, pesan) VALUES ('$pengirim', '$penerima', '$role', '$pesan')");
                } catch (Exception $e2) {
                    try {
                        $koneksi->query("INSERT INTO bantuan_chat (pengirim, role, pesan) VALUES ('$pengirim', '$role', '$pesan')");
                    } catch (Exception $e3) {}
                }
            }
        }
        
        while(ob_get_level()) ob_end_clean();
        echo json_encode(['status' => 'ok']); exit;
    }

    if ($_POST['chat_action'] == 'clear') {
        if ($user_level === 'admin') {
            try { $koneksi->query("TRUNCATE TABLE bantuan_chat"); } catch (Exception $e) {}
        }
        while(ob_get_level()) ob_end_clean();
        echo json_encode(['status' => 'ok']); exit;
    }

    if ($_POST['chat_action'] == 'check_unread') {
        $me = $koneksi->real_escape_string($current_user);
        $unread = 0;
        try {
            if ($user_level === 'admin') {
                $q = $koneksi->query("SELECT COUNT(*) as unread FROM bantuan_chat WHERE penerima = 'admin' AND status_baca = 0");
            } else {
                $q = $koneksi->query("SELECT COUNT(*) as unread FROM bantuan_chat WHERE penerima = '$me' AND status_baca = 0");
            }
            if ($q) {
                $unread = $q->fetch_assoc()['unread'];
            }
        } catch (Exception $e) {}

        while(ob_get_level()) ob_end_clean();
        echo json_encode(['unread' => $unread]); exit;
    }
}
// =================================================================================

// Generate CSRF Token dengan Fallback
if (empty($_SESSION['csrf_token'])) {
    if (function_exists('random_bytes')) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } else {
        $_SESSION['csrf_token'] = md5(uniqid(mt_rand(), true));
    }
}

// Fungsi bantuan ANTI-CRASH
function safeQueryCount($koneksi, $sql) {
    try {
        $result = @$koneksi->query($sql);
        if ($result) {
            $data = $result->fetch_array();
            return $data[0] ?? 0;
        }
        return 0;
    } catch (Exception $e) { return 0; }
}

// === 1. QUERY UNTUK DATA CARDS (KPI KOMPREHENSIF) ===
$total_peserta = safeQueryCount($koneksi, "SELECT COUNT(*) FROM peserta_didik");
$total_lokasi = safeQueryCount($koneksi, "SELECT COUNT(*) FROM lokasi_pkl");
$total_guru = safeQueryCount($koneksi, "SELECT COUNT(*) FROM guru");
$total_monitoring = safeQueryCount($koneksi, "SELECT COUNT(*) FROM monitoring_kunjungan");
$total_laporan = safeQueryCount($koneksi, "SELECT COUNT(*) FROM laporan_akhir");
$total_sidang = safeQueryCount($koneksi, "SELECT COUNT(*) FROM sidang_pkl");
$total_sertifikat = safeQueryCount($koneksi, "SELECT COUNT(*) FROM sertifikat_terbit");
$total_surat = safeQueryCount($koneksi, "SELECT (SELECT COUNT(*) FROM surat_masuk_pkl) + (SELECT COUNT(*) FROM surat_keluar_pkl)");

// === 2. QUERY UNTUK GRAFIK ===
$presensi_labels = []; $presensi_values = [];
try {
    $grafik_presensi = @$koneksi->query("
        SELECT tanggal, COUNT(*) AS total_hadir 
        FROM presensi_pkl 
        WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
        GROUP BY tanggal 
        ORDER BY tanggal ASC
    ");
    if ($grafik_presensi) { 
        while ($row = $grafik_presensi->fetch_assoc()) { 
            $tgl_format = date('d/m', strtotime($row['tanggal']));
            $presensi_labels[] = $tgl_format; 
            $presensi_values[] = (int)$row['total_hadir']; 
        } 
    }
} catch (Exception $e) {}

$kelas_labels = []; $kelas_values = [];
try {
    $kelas_result = @$koneksi->query("SELECT kelas, COUNT(*) as jumlah FROM peserta_didik GROUP BY kelas");
    if ($kelas_result) { 
        while ($row = $kelas_result->fetch_assoc()) { 
            $kelas_labels[] = $row['kelas']; 
            $kelas_values[] = (int)$row['jumlah']; 
        } 
    }
} catch (Exception $e) {}

$top_lokasi_labels = []; $top_lokasi_values = [];
try {
    $top_lok_result = @$koneksi->query("
        SELECT l.nama_lokasi, COUNT(p.id) as jumlah 
        FROM lokasi_pkl l 
        LEFT JOIN peserta_didik p ON l.lokasi_id = p.lokasi_id 
        GROUP BY l.lokasi_id 
        ORDER BY jumlah DESC 
        LIMIT 5
    ");
    if ($top_lok_result) {
        while ($row = $top_lok_result->fetch_assoc()) {
            $nama = strlen($row['nama_lokasi']) > 15 ? substr($row['nama_lokasi'], 0, 15).'...' : $row['nama_lokasi'];
            $top_lokasi_labels[] = $nama;
            $top_lokasi_values[] = (int)$row['jumlah'];
        }
    }
} catch (Exception $e) {}

$js_presensi_labels = json_encode($presensi_labels);
$js_presensi_values = json_encode($presensi_values);
$js_kelas_labels = json_encode($kelas_labels);
$js_kelas_values = json_encode($kelas_values);
$js_top_lokasi_labels = json_encode($top_lokasi_labels);
$js_top_lokasi_values = json_encode($top_lokasi_values);

// AMBIL RELASI DATA LOKASI DAN DAFTAR SISWA
$lokasi_siswa_query = "
    SELECT 
        l.lokasi_id, l.nama_lokasi, l.kuota_max, l.alamat, l.guru_id,
        g.nama_guru, g.no_hp AS guru_hp,
        p.id AS siswa_id, p.nama AS nama_siswa, p.nisn, p.kelas
    FROM lokasi_pkl l
    LEFT JOIN guru g ON l.guru_id = g.guru_id
    LEFT JOIN peserta_didik p ON l.lokasi_id = p.lokasi_id
    ORDER BY l.nama_lokasi ASC, p.nama ASC
";
$lokasi_siswa_res = @$koneksi->query($lokasi_siswa_query);

$tabel_matrix = [];
if ($lokasi_siswa_res) {
    while ($row = $lokasi_siswa_res->fetch_assoc()) {
        $id_lokasi = $row['lokasi_id'];
        if (!isset($tabel_matrix[$id_lokasi])) {
            $tabel_matrix[$id_lokasi] = [
                'lokasi_id'   => $row['lokasi_id'],
                'nama_lokasi' => $row['nama_lokasi'],
                'alamat'      => $row['alamat'],
                'kuota_max'   => $row['kuota_max'],
                'guru_id'     => $row['guru_id'],
                'nama_guru'   => $row['nama_guru'],
                'guru_hp'     => $row['guru_hp'],
                'siswa_list'  => []
            ];
        }
        if (!empty($row['siswa_id'])) {
            $tabel_matrix[$id_lokasi]['siswa_list'][] = [
                'nama_siswa' => $row['nama_siswa'],
                'nisn'       => $row['nisn'],
                'kelas'      => $row['kelas']
            ];
        }
    }
}

$bulan_indo = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$tgl_sekarang = date('d') . ' ' . $bulan_indo[(int)date('m')] . ' ' . date('Y');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Dashboard | Si Mantap PKL</title>
    <link rel="stylesheet" href="../css/style.css"> 
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <style>
        :root {
            --mantap-blue-dark: #0f172a;
            --mantap-blue-main: #1e40af;
            --mantap-blue-light: #3b82f6;
            --mantap-blue-soft: #eff6ff;
        }

        body { font-family: 'Poppins', sans-serif; background-color: #f8fafc; color: #334155; margin: 0; overflow-x: hidden !important; }
        .main-content-wrapper, .admin-main-content, .container { max-width: 100% !important; width: 100% !important; box-sizing: border-box !important; }
        
        .admin-main-content { padding: 20px 25px 30px 25px !important; }

        /* --- 1. WELCOME PANEL HERO (RATA KIRI PRESISI) --- */
        .welcome-panel {
            background: linear-gradient(135deg, var(--mantap-blue-main) 0%, var(--mantap-blue-light) 100%);
            padding: 35px 40px;
            border-radius: 16px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 25px -5px rgba(30, 64, 175, 0.3);
            border: none;
        }
        
        .welcome-panel::before {
            content: ''; position: absolute; right: -50px; top: -80px; width: 250px; height: 250px;
            background: rgba(255, 255, 255, 0.1); border-radius: 50%; pointer-events: none;
        }
        .welcome-panel::after {
            content: ''; position: absolute; right: 120px; bottom: -60px; width: 150px; height: 150px;
            background: rgba(255, 255, 255, 0.1); border-radius: 50%; pointer-events: none;
        }

        .welcome-text { 
            position: relative; 
            z-index: 2; 
            flex: 1; 
            display: flex; 
            flex-direction: column; 
            align-items: flex-start;
        }
        .welcome-text h2 { 
            margin: 0 0 8px 0 !important; 
            padding: 0 !important; 
            font-size: 1.8rem; 
            font-weight: 800; 
            color: #ffffff; 
            letter-spacing: 0.5px; 
            text-align: left !important;
        }
        .welcome-text p { 
            margin: 0 !important; 
            padding: 0 !important; 
            font-size: 14px; 
            color: #e2e8f0; 
            font-weight: 400; 
            text-align: left !important;
        }
        
        .waving-hand { display: inline-block; animation: wave 2.5s infinite; transform-origin: 70% 70%; }
        @keyframes wave {
            0% { transform: rotate(0deg); }
            10% { transform: rotate(14deg); }
            20% { transform: rotate(-8deg); }
            30% { transform: rotate(14deg); }
            40% { transform: rotate(-4deg); }
            50% { transform: rotate(10deg); }
            60% { transform: rotate(0deg); }
            100% { transform: rotate(0deg); }
        }

        .welcome-date {
            position: relative; z-index: 2;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 10px 20px;
            border-radius: 50px;
            font-size: 13.5px;
            font-weight: 600;
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }

        /* --- 2. KPI CARDS --- */
        .kpi-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 25px; width: 100%; box-sizing: border-box; }
        .kpi-card-new { 
            padding: 20px 18px; border-radius: 12px; color: white; position: relative; overflow: hidden; 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05) !important; transition: transform 0.2s ease; 
        }
        .kpi-card-new:hover { transform: translateY(-4px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1) !important; }
        .kpi-card-new i { position: absolute; right: -5px; bottom: -10px; font-size: 4.5rem; opacity: 0.15; transform: rotate(-10deg); }
        .kpi-card-new h3 { font-size: 1.8rem; font-weight: 800; margin: 0; position: relative; z-index: 2; line-height: 1; }
        .kpi-card-new p { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 6px 0; font-weight: 700; opacity: 0.95; position: relative; z-index: 2; }

        .grad-1 { background: linear-gradient(135deg, #1e40af 0%, #1d4ed8 100%); }
        .grad-2 { background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); }
        .grad-3 { background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%); }
        .grad-4 { background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%); }
        .grad-5 { background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); }
        .grad-6 { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .grad-7 { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
        .grad-8 { background: linear-gradient(135deg, #64748b 0%, #475569 100%); }

        /* --- 3. LAYOUT GRAFIK --- */
        .content-grid { display: grid; grid-template-columns: 1.4fr 1fr 1.3fr; gap: 20px; margin-bottom: 25px; }
        .glass-panel { 
            background: white !important; padding: 20px 25px !important; border-radius: 12px !important; 
            border: 1px solid #e2e8f0 !important; box-sizing: border-box; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02) !important; width: 100%; 
        }
        .glass-panel h3 { font-size: 1.1rem; font-weight: 700; color: var(--mantap-blue-dark); margin-top: 0; margin-bottom: 20px; display: flex; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 12px; }

        /* --- 4. TABEL DATABASE --- */
        .table-card { 
            background: white !important; border-radius: 12px !important; padding: 25px !important; 
            border: 1px solid #e2e8f0 !important; box-sizing: border-box; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02) !important; width: 100%; min-width: 0; 
        }
        
        .table-header-flex {
            display: flex; justify-content: space-between; align-items: center; 
            border-bottom: 2px solid #f1f5f9; padding-bottom: 12px; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;
        }
        .table-header-flex h3 { margin: 0; border: none; padding: 0; font-size: 1.25rem; font-weight: 800; color: var(--mantap-blue-dark); }

        .search-wrapper { position: relative; display: flex; align-items: center; }
        .search-wrapper i { position: absolute; left: 14px; color: #64748b; font-size: 13px; z-index: 2; pointer-events: none;}
        .flat-oval-input { 
            appearance: none; background-color: #f8fafc !important; border: 1px solid #cbd5e1 !important; 
            border-radius: 50px !important; padding: 9px 15px 9px 36px !important; 
            font-family: 'Poppins', sans-serif; font-size: 13px !important; font-weight: 500; color: #0f172a; 
            outline: none; box-shadow: none !important; transition: 0.3s ease; margin: 0 !important; 
            width: 260px; display: inline-flex; align-items: center;
        }
        .flat-oval-input:focus { border-color: var(--mantap-blue-main) !important; background-color: white !important; width: 300px; box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1) !important;}

        .table-responsive { width: 100%; overflow-x: auto; box-sizing: border-box; border: none !important; }

        .custom-table { width: 100%; border-collapse: collapse; background: white; border-radius: 4px; overflow: hidden; border: 2px solid #1e40af; table-layout: fixed; min-width: 900px;}
        .custom-table th { background: #1e40af; color: white; padding: 14px 6px; font-size: 12.5px; text-transform: uppercase; font-weight: 700; text-align: center; border: 1px solid #cbd5e1; box-sizing: border-box; white-space: nowrap; }
        
        .custom-table th.col-no { width: 50px; text-align: center; }
        .custom-table th.col-tempat { width: 28%; text-align: left; padding-left: 12px; }
        .custom-table th.col-pembimbing { width: 22%; text-align: left; padding-left: 12px; }
        .custom-table th.col-siswa { width: 26%; text-align: left; padding-left: 12px; }
        .custom-table th.col-nisn { width: 110px; text-align: center; }
        .custom-table th.col-kelas { width: 90px; text-align: center; }

        .custom-table td { padding: 12px 10px; font-size: 13px; color: #0f172a; vertical-align: middle; border: 1px solid #e2e8f0; background-color: white !important; word-wrap: break-word; }
        .custom-table td.cell-no-center { text-align: center; font-weight: 700; }
        .custom-table td.text-left { text-align: left !important; padding-left: 12px; }
        .custom-table td.text-center { text-align: center !important; }
        .custom-table tbody tr:hover td { background-color: #f8fafc !important; }
        
        .sub-info-alamat { font-size: 11px; color: #64748b; margin-top: 4px; display: block; font-weight: 400; line-height: 1.4;}

        /* --- 5. CHATBOX WIDGET STYLES & MODIFICATIONS --- */
        .chat-widget-btn {
            position: fixed; bottom: 30px; right: 30px; background: var(--mantap-blue-main); color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; box-shadow: 0 4px 15px rgba(30, 64, 175, 0.4); cursor: pointer; z-index: 9999; transition: all 0.3s ease; border: none; animation: bounceIn 0.8s ease;
        }
        .chat-widget-btn:hover { transform: scale(1.1); background: var(--mantap-blue-dark); }
        
        /* Notifikasi Titik Merah (Badge) */
        .chat-badge {
            position: absolute; top: -2px; right: -2px; background: #ef4444; color: white; border-radius: 50%; width: 22px; height: 22px; font-size: 11px; font-weight: 800; display: flex; align-items: center; justify-content: center; border: 2px solid white; animation: pulseRed 2s infinite; font-family: 'Poppins', sans-serif;
        }
        .chat-badge.hidden { display: none !important; }
        
        @keyframes pulseRed {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }
        @keyframes bounceIn { 0% { transform: scale(0); opacity: 0; } 50% { transform: scale(1.1); opacity: 1; } 100% { transform: scale(1); } }

        /* Panel Size Adjusted */
        .chat-widget-panel {
            position: fixed; bottom: 100px; right: 30px; width: 350px; background: white; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); z-index: 9998; display: none; flex-direction: column; overflow: hidden; border: 1px solid #e2e8f0; opacity: 0; transform: translateY(20px); transition: opacity 0.3s ease, transform 0.3s ease;
        }
        .chat-widget-panel.show { display: flex; opacity: 1; transform: translateY(0); }
        
        .chat-header { background: var(--mantap-blue-main); color: white; padding: 15px; display: flex; justify-content: space-between; align-items: center; font-weight: 600; }
        
        .chat-body { padding: 15px; height: 320px; overflow-y: auto; background: #f8fafc; display: flex; flex-direction: column; gap: 12px; scroll-behavior: smooth; }
        
        /* QUICK REPLIES BAR (KHUSUS ADMIN) */
        .chat-quick-replies {
            display: none; 
            flex-direction: row; gap: 8px; padding: 10px 15px; background: white; 
            border-top: 1px solid #e2e8f0; overflow-x: auto; white-space: nowrap;
        }
        .chat-quick-replies::-webkit-scrollbar { height: 4px; }
        .chat-quick-replies::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .chat-quick-replies button {
            background: var(--mantap-blue-soft); color: var(--mantap-blue-main); border: 1px solid #bfdbfe;
            padding: 5px 12px; border-radius: 20px; font-size: 11px; cursor: pointer; transition: 0.2s; white-space: nowrap; font-weight: 600; font-family: 'Poppins', sans-serif;
        }
        .chat-quick-replies button:hover { background: var(--mantap-blue-main); color: white; border-color: var(--mantap-blue-main); }

        /* Message Layout with Timestamp */
        .chat-message { max-width: 82%; padding: 10px 14px; border-radius: 8px; font-size: 13px; line-height: 1.4; position: relative; }
        .chat-message.bot { background: white; border: 1px solid #e2e8f0; align-self: flex-start; border-bottom-left-radius: 0; }
        .chat-message.user { background: var(--mantap-blue-soft); color: var(--mantap-blue-dark); border: 1px solid #bfdbfe; align-self: flex-end; border-bottom-right-radius: 0; }
        
        .chat-time { display: block; font-size: 10px; color: #94a3b8; margin-top: 5px; text-align: right; }
        .chat-message.user .chat-time { color: #64748b; }
        
        .chat-footer { padding: 15px; background: white; border-top: 1px solid #e2e8f0; display: flex; gap: 10px; align-items: center; }
        .chat-footer input { flex: 1; padding: 10px 15px; border: 1px solid #cbd5e1; border-radius: 20px; outline: none; font-family: 'Poppins', sans-serif; font-size: 13px; }
        .chat-footer input:focus { border-color: var(--mantap-blue-main); }
        .chat-footer button { background: var(--mantap-blue-main); color: white; border: none; width: 42px; height: 42px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; flex-shrink: 0; }
        .chat-footer button:hover { background: var(--mantap-blue-dark); }

        /* --- TAMBAHAN KHUSUS ADMIN CHAT --- */
        .chat-widget-panel.admin-wide { width: 700px; max-width: 95vw; } 
        .chat-layout { display: flex; flex-direction: row; height: 400px; background: #f8fafc; width: 100%;}
        
        /* MENGUNCI SIDEBAR AGAR TIDAK MENGECIL & TULISAN TDK TERPOTONG */
        .chat-sidebar { width: 230px; min-width: 230px; flex-shrink: 0; background: white; border-right: 1px solid #e2e8f0; overflow-y: auto; display: flex; flex-direction: column; }
        
        .chat-sidebar-title { padding: 12px 15px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 1px solid #f1f5f9; background: #f8fafc; }
        
        .chat-user-item { padding: 12px 15px; border-bottom: 1px solid #f1f5f9; cursor: pointer; display: flex; align-items: center; gap: 12px; transition: 0.2s; position: relative;}
        .chat-user-item i.fa-user { background: var(--mantap-blue-soft); color: var(--mantap-blue-main); width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0;}
        
        /* FIX TEKS MENUMPUK KE BAWAH: Gunakan nowrap & ellipsis */
        .chat-user-item span.user-name { font-size: 13px; font-weight: 600; color: #334155; line-height: 1.3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; margin-right: 5px; display: block;}
        
        .chat-user-item .unread-dot { 
            background: #ef4444; color: white; font-size: 10px; font-weight: 700; 
            min-width: 20px; height: 20px; border-radius: 10px; 
            display: flex; align-items: center; justify-content: center; 
            padding: 0 6px; margin-left: auto; flex-shrink: 0; box-sizing: border-box;
        }
        .chat-user-item:hover { background: #f1f5f9; }
        .chat-user-item.active { background: var(--mantap-blue-main); }
        .chat-user-item.active span.user-name { color: white; }
        .chat-user-item.active i.fa-user { background: rgba(255,255,255,0.2); color: white; }

        .chat-main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .chat-header .chat-actions { display: flex; gap: 15px; align-items: center; }
        .chat-header .chat-actions i { cursor: pointer; font-size: 17px; transition: 0.2s; opacity: 0.85;}
        .chat-header .chat-actions i:hover { color: #fca5a5; opacity: 1; transform: scale(1.1); }
        .chat-sender-name { font-size: 11px; font-weight: 700; color: #64748b; margin-bottom: 4px; display: block; }
        .chat-footer input:disabled { background: #f1f5f9; cursor: not-allowed; }
        .chat-footer button:disabled { background: #cbd5e1; cursor: not-allowed; }

        /* =========================================================================
            RESPONSIVE VIEWPORT
        ========================================================================= */
        @media (max-width: 1024px) {
            .content-grid { grid-template-columns: 1fr 1fr; } 
            .glass-panel:last-child { grid-column: span 2; } 
        }

        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; overflow-x: hidden !important; width: 100% !important; box-sizing: border-box !important;}
            
            .welcome-panel { padding: 25px 20px; align-items: flex-start; flex-direction: column; gap: 15px;}
            
            .kpi-row { grid-template-columns: repeat(2, 1fr) !important; gap: 10px !important; } 
            .kpi-card-new { padding: 16px 14px !important; border-radius: 10px !important; }
            .kpi-card-new h3 { font-size: 1.5rem !important;}
            .kpi-card-new p { font-size: 10px !important; }
            .kpi-card-new i { font-size: 3.5rem; right: -5px; bottom: -5px; } 
            
            .content-grid { display: flex !important; flex-direction: column !important; gap: 15px !important; }
            .glass-panel { padding: 16px 14px !important; }
            
            .table-card { padding: 16px 10px 25px 10px !important; }
            .table-header-flex { flex-direction: column; align-items: stretch; gap: 10px; }
            .search-wrapper { width: 100%; }
            .flat-oval-input { width: 100% !important; box-sizing: border-box; }
            .flat-oval-input:focus { width: 100% !important; }

            .table-responsive { display: block !important; width: 100% !important; overflow-x: auto !important; border: none !important; -webkit-overflow-scrolling: touch; }
            .custom-table { table-layout: auto !important; min-width: 800px !important; } 
            .custom-table th.col-no, .custom-table th.col-tempat, .custom-table th.col-pembimbing, .custom-table th.col-siswa, .custom-table th.col-nisn, .custom-table th.col-kelas { width: auto !important; text-align: center; }
            .custom-table th, .custom-table td { padding: 10px 8px !important; font-size: 12.5px !important; text-align: center !important; }
            .custom-table td.text-left { text-align: left !important; }

            /* Chatbox Mobile Adjustments */
            .chat-widget-panel { width: calc(100% - 40px); right: 20px; bottom: 90px; }
            .chat-widget-panel.admin-wide { width: calc(100% - 40px); }
            
            .chat-layout { flex-direction: column; height: 420px; }
            .chat-sidebar { width: 100%; min-width: 100%; height: 85px; flex-direction: row; border-right: none; border-bottom: 1px solid #e2e8f0; flex-shrink: 0;}
            .chat-sidebar-title { display: none; }
            .chat-user-item { flex-direction: column; border-bottom: none; border-right: 1px solid #f1f5f9; padding: 10px; gap: 5px; width: 85px; text-align: center; justify-content: center;}
            .chat-user-item span.user-name { font-size: 11px; max-width: 75px; margin-right: 0;}
            .chat-user-item .unread-dot { position: absolute; top: 5px; right: 10px; margin: 0;}
            .chat-widget-btn { bottom: 20px; right: 20px; width: 55px; height: 55px; font-size: 20px; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; include 'panel/navbar.php'; ?>

<div class="main-content-wrapper">
    <div class="admin-main-content">
        
        <!-- PANEL SAPAAN HERO (SEJAJAR PRESISI) -->
        <div class="welcome-panel">
            <div class="welcome-text">
                <h2>Selamat Datang, <?php echo $current_user; ?>! <span class="waving-hand">👋</span></h2>
                <p>Berikut adalah ringkasan data operasional Praktek Kerja Lapangan hari ini.</p>
            </div>
            <div class="welcome-date">
                <i class="far fa-calendar-alt"></i> <?php echo $tgl_sekarang; ?>
            </div>
        </div>

        <!-- KPI CARDS -->
        <div class="kpi-row">
            <div class="kpi-card-new grad-1">
                <p>Peserta Aktif</p>
                <h3><?php echo $total_peserta; ?></h3>
                <i class="fas fa-users"></i>
            </div>
            <div class="kpi-card-new grad-2">
                <p>Lokasi PKL</p>
                <h3><?php echo $total_lokasi; ?></h3>
                <i class="fas fa-building"></i>
            </div>
            <div class="kpi-card-new grad-3">
                <p>Kunjungan Monitor</p>
                <h3><?php echo $total_monitoring; ?></h3>
                <i class="fas fa-map-marker-alt"></i>
            </div>
            <div class="kpi-card-new grad-4">
                <p>Laporan Terkumpul</p>
                <h3><?php echo $total_laporan; ?></h3>
                <i class="fas fa-file-pdf"></i>
            </div>
            <div class="kpi-card-new grad-5">
                <p>Jadwal Sidang</p>
                <h3><?php echo $total_sidang; ?></h3>
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="kpi-card-new grad-6">
                <p>Sertifikat Terbit</p>
                <h3><?php echo $total_sertifikat; ?></h3>
                <i class="fas fa-award"></i>
            </div>
            <div class="kpi-card-new grad-7">
                <p>Guru Pembimbing</p>
                <h3><?php echo $total_guru; ?></h3>
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <div class="kpi-card-new grad-8">
                <p>Arsip Persuratan</p>
                <h3><?php echo $total_surat; ?></h3>
                <i class="fas fa-envelope-open-text"></i>
            </div>
        </div>

        <!-- GRAFIK SECTION -->
        <div class="content-grid">
            <div class="glass-panel">
                <h3 style="margin-top:0;"><i class="fas fa-chart-line" style="color: var(--mantap-blue-main); margin-right: 8px;"></i>Tren Presensi (7 Hari Terakhir)</h3>
                <div style="height: 280px; position: relative;"><canvas id="presensiChart"></canvas></div>
            </div>
            <div class="glass-panel">
                <h3 style="margin-top:0;"><i class="fas fa-chart-pie" style="color: #3b82f6; margin-right: 8px;"></i>Sebaran Peserta PKL</h3>
                <div style="height: 280px; position: relative;"><canvas id="kelasChart"></canvas></div>
            </div>
            <div class="glass-panel">
                <h3 style="margin-top:0;"><i class="fas fa-chart-bar" style="color: #f59e0b; margin-right: 8px;"></i>Top 5 Lokasi Favorit</h3>
                <div style="height: 280px; position: relative;"><canvas id="topLokasiChart"></canvas></div>
            </div>
        </div>

        <!-- TABEL DATA PENEMPATAN -->
        <div class="table-card" id="tabel">
            <div class="table-header-flex">
                <h3><i class="fas fa-table" style="color: var(--mantap-blue-main); margin-right: 8px;"></i>Database Penempatan Siswa</h3>
                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchDatabase" class="flat-oval-input" placeholder="Cari Siswa / Lokasi / Pembimbing..." onkeyup="filterDatabase()">
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="custom-table" id="dataTable">
                    <thead>
                        <tr>
                            <th class="col-no">NO</th>
                            <th class="col-tempat">TEMPAT PKL & KUOTA</th>
                            <th class="col-pembimbing">PEMBIMBING</th>
                            <th class="col-siswa">NAMA SISWA</th>
                            <th class="col-nisn">NISN</th>
                            <th class="col-kelas">KELAS</th>
                        </tr>
                    </thead>
                    
                    <?php 
                    $row_counter = 1; 
                    if (count($tabel_matrix) > 0):
                        foreach ($tabel_matrix as $id_lok => $data_lok):
                            $siswa_count = count($data_lok['siswa_list']);
                            $rowspan_attr = $siswa_count > 0 ? $siswa_count : 1;
                    ?>
                    <tbody class="data-group">
                        <tr>
                            <td rowspan="<?php echo $rowspan_attr; ?>" class="cell-no-center" style="color: #64748b;"><?php echo $row_counter++; ?></td>
                            
                            <!-- TEMPAT PKL & KUOTA -->
                            <td class="text-left" rowspan="<?php echo $rowspan_attr; ?>">
                                <strong style="color: var(--mantap-blue-dark); font-size: 13.5px; display:block; text-transform:uppercase; margin-bottom:2px;"><?php echo htmlspecialchars($data_lok['nama_lokasi']); ?></strong>
                                <span class="sub-info-alamat"><i class="fas fa-map-marker-alt opacity-50 me-1"></i><?php echo htmlspecialchars($data_lok['alamat'] ?? '-'); ?></span>
                                
                                <?php 
                                    $kuota = $data_lok['kuota_max'];
                                    $terisi = $siswa_count;
                                    $kuota_color = ($terisi >= $kuota) ? '#ef4444' : '#10b981';
                                    $kuota_bg = ($terisi >= $kuota) ? '#fef2f2' : '#ecfdf5';
                                    $kuota_border = ($terisi >= $kuota) ? '#fca5a5' : '#bbf7d0';
                                ?>
                                <div style="margin-top: 8px; display: inline-flex; align-items: center; gap:4px; padding: 3px 8px; border-radius: 4px; background: <?php echo $kuota_bg; ?>; color: <?php echo $kuota_color; ?>; font-size: 10.5px; font-weight: 700; border: 1px solid <?php echo $kuota_border; ?>;">
                                    <i class="fas fa-users"></i> Kuota Terisi: <?php echo $terisi; ?> / <?php echo $kuota; ?>
                                </div>
                            </td>

                            <!-- PEMBIMBING & KONTAK -->
                            <td class="text-left" rowspan="<?php echo $rowspan_attr; ?>">
                                <?php if($data_lok['nama_guru']): ?>
                                    <strong style="color: #0f172a; font-size: 13px; display: block; text-transform:uppercase; margin-bottom:4px;"><?php echo htmlspecialchars($data_lok['nama_guru']); ?></strong>
                                    <span style="font-size: 11px; color: #64748b; font-weight: 500; display:flex; align-items:center; gap:4px;">
                                        <i class="fab fa-whatsapp text-success" style="font-size:13px;"></i> 
                                        <?php echo htmlspecialchars($data_lok['guru_hp'] ?? '-'); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:#94a3b8; font-weight:400; font-style:italic;">Belum Ditentukan</span>
                                <?php endif; ?>
                            </td>

                            <!-- SISWA 1 -->
                            <?php if ($siswa_count > 0): ?>
                                <td class="text-left" style="font-weight: 600; text-transform: uppercase;"><?php echo htmlspecialchars($data_lok['siswa_list'][0]['nama_siswa']); ?></td>
                                <td class="text-center" style="font-weight: 500; color: #475569;"><?php echo htmlspecialchars($data_lok['siswa_list'][0]['nisn']); ?></td>
                                <td class="text-center" style="font-weight: 600; color: #0f172a;"><?php echo htmlspecialchars($data_lok['siswa_list'][0]['kelas']); ?></td>
                            <?php else: ?>
                                <td colspan="3" style="color: #dc2626; font-style: italic; font-weight: 400; background-color: #fff5f5 !important;">(Belum ada siswa yang mendaftar di lokasi ini)</td>
                            <?php endif; ?>
                        </tr>

                        <!-- SISA SISWA (Jika > 1) -->
                        <?php 
                        if ($siswa_count > 1):
                            for ($i = 1; $i < $siswa_count; $i++):
                        ?>
                        <tr>
                            <td class="text-left" style="font-weight: 600; text-transform: uppercase;"><?php echo htmlspecialchars($data_lok['siswa_list'][$i]['nama_siswa']); ?></td>
                            <td class="text-center" style="font-weight: 500; color: #475569;"><?php echo htmlspecialchars($data_lok['siswa_list'][$i]['nisn']); ?></td>
                            <td class="text-center" style="font-weight: 600; color: #0f172a;"><?php echo htmlspecialchars($data_lok['siswa_list'][$i]['kelas']); ?></td>
                        </tr>
                        <?php 
                            endfor;
                        endif;
                        ?>
                    </tbody>
                    <?php 
                        endforeach;
                    else:
                    ?>
                    <tbody>
                        <tr>
                            <td colspan="6" class="text-center" style="color: #ef4444; font-style: italic; padding: 25px;">Tidak ada data lokasi PKL ditemukan.</td>
                        </tr>
                    </tbody>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- CHATBOX WIDGET -->
<button class="chat-widget-btn" id="chatBtn" onclick="toggleChat()" title="Bantuan Pembimbing">
    <i class="fas fa-headset"></i>
    <!-- BADGE NOTIFIKASI -->
    <span class="chat-badge hidden" id="chatBadge">0</span>
</button>

<div class="chat-widget-panel <?php echo $user_level === 'admin' ? 'admin-wide' : ''; ?>" id="chatPanel">
    <div class="chat-header">
        <span><i class="fas fa-robot me-2"></i> Bantuan Si Mantap</span>
        <div class="chat-actions">
            <?php if($user_level === 'admin'): ?>
                <i class="fas fa-trash" title="Bersihkan Seluruh Obrolan" onclick="clearChat()" style="color: #fca5a5;"></i>
            <?php endif; ?>
            <i class="fas fa-times close-chat" title="Tutup" onclick="toggleChat()"></i>
        </div>
    </div>
    
    <?php if($user_level === 'admin'): ?>
    <!-- TAMPILAN KHUSUS ADMIN -->
    <div class="chat-layout">
        <div class="chat-sidebar" id="chatSidebar">
            <div class="chat-sidebar-title">Pesan Masuk</div>
            <!-- User list loaded here via JS -->
        </div>
        <div class="chat-main">
            <div class="chat-body" id="chatBody">
                <div class="chat-placeholder" style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:#94a3b8; text-align:center;">
                    <i class="far fa-comments" style="font-size:40px; margin-bottom:10px;"></i>
                    <span>Pilih nama pembimbing di sebelah kiri<br>untuk melihat dan membalas pesan.</span>
                </div>
            </div>
            
            <!-- REKOMENDASI JAWABAN (KHUSUS ADMIN) -->
            <div class="chat-quick-replies" id="quickReplies">
                <button type="button" onclick="useQuickReply('Baik Bapak/Ibu, akan segera kami tindak lanjuti.')">Tindak Lanjut</button>
                <button type="button" onclick="useQuickReply('Bapak/Ibu dapat melihat Laporan Siswa pada menu di sebelah kiri.')">Cek Laporan</button>
                <button type="button" onclick="useQuickReply('Mohon sebutkan Nama atau NISN siswa terkait agar bisa kami cek.')">Tanya Nama/NISN</button>
                <button type="button" onclick="useQuickReply('Jadwal sidang PKL akan segera kami informasikan lebih lanjut melalui pengumuman.')">Jadwal Sidang</button>
                <button type="button" onclick="useQuickReply('Untuk sertifikat, baru bisa dicetak setelah siswa menyelesaikan semua administrasi.')">Info Sertifikat</button>
            </div>

            <div class="chat-footer">
                <input type="text" id="chatInput" placeholder="Pilih chat terlebih dahulu..." disabled onkeypress="handleChatEnter(event)">
                <button id="chatSendBtn" disabled onclick="sendChatMessage()"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- TAMPILAN NORMAL (PEMBIMBING) -->
    <div class="chat-body" id="chatBody">
        <div class="chat-message bot" id="defaultWelcomeChat">
            Halo <?php echo $current_user; ?>! Ada yang bisa kami bantu terkait data PKL hari ini?
        </div>
    </div>
    <div class="chat-footer">
        <input type="text" id="chatInput" placeholder="Ketik pesan..." onkeypress="handleChatEnter(event)">
        <button id="chatSendBtn" onclick="sendChatMessage()"><i class="fas fa-paper-plane"></i></button>
    </div>
    <?php endif; ?>
</div>

<?php include 'panel/footer.php'; ?>

<script>
    // --- VARIABEL GLOBAL CHATBOX ---
    const chatUserLevel = "<?php echo $user_level; ?>";
    const chatUserName = "<?php echo $current_user; ?>";
    let chatInterval = null;
    let activeChatTarget = ''; 

    // --- FITUR CHATBOX WIDGET ---
    function toggleChat() {
        const panel = document.getElementById('chatPanel');
        if (panel.classList.contains('show')) {
            panel.classList.remove('show');
            setTimeout(() => panel.style.display = 'none', 300);
            clearInterval(chatInterval);
        } else {
            panel.style.display = 'flex';
            setTimeout(() => panel.classList.add('show'), 10);
            
            document.getElementById('chatBadge').classList.add('hidden');

            if (chatUserLevel === 'admin') {
                loadChatUsers(); 
            } else {
                activeChatTarget = 'admin'; 
                loadChatMessages();
            }
            
            chatInterval = setInterval(() => {
                if (chatUserLevel === 'admin') loadChatUsers();
                if (activeChatTarget !== '') loadChatMessages();
            }, 3000); 
        }
    }

    // Rekomendasi Jawaban Action
    function useQuickReply(text) {
        const inputField = document.getElementById('chatInput');
        inputField.value = text;
        inputField.focus();
    }

    // --- POLLING NOTIFIKASI LONCENG UNREAD ---
    function checkUnreadMessages() {
        const formData = new FormData();
        formData.append('chat_action', 'check_unread');
        fetch(window.location.pathname, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                const badge = document.getElementById('chatBadge');
                const panel = document.getElementById('chatPanel');
                if (data.unread > 0 && !panel.classList.contains('show')) {
                    badge.textContent = data.unread;
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }
            }).catch(e => {}); 
    }

    setInterval(checkUnreadMessages, 4000);
    checkUnreadMessages();

    // --- FUNGSI LOAD USER (ADMIN) ---
    function loadChatUsers() {
        const formData = new FormData();
        formData.append('chat_action', 'load_users');
        
        fetch(window.location.pathname, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(users => {
                const sidebar = document.getElementById('chatSidebar');
                sidebar.innerHTML = '<div class="chat-sidebar-title">Pesan Masuk</div>';
                
                if (users.length === 0) {
                    sidebar.innerHTML += '<div style="padding:15px; font-size:12px; color:#94a3b8; text-align:center;">Belum ada pesan.</div>';
                    return;
                }

                users.forEach(userObj => {
                    const userName = userObj.nama;
                    const unreadCount = userObj.unread;
                    const isActive = (userName === activeChatTarget) ? 'active' : '';
                    
                    let unreadHtml = '';
                    if (unreadCount > 0) {
                        unreadHtml = `<span class="unread-dot">${unreadCount}</span>`;
                    }

                    const div = document.createElement('div');
                    div.className = `chat-user-item ${isActive}`;
                    div.innerHTML = `<i class="fas fa-user"></i> <span class="user-name">${userName}</span> ${unreadHtml}`;
                    
                    div.onclick = function() {
                        activeChatTarget = userName;
                        document.querySelectorAll('.chat-user-item').forEach(el => el.classList.remove('active'));
                        this.classList.add('active');
                        loadChatMessages();
                    };
                    
                    sidebar.appendChild(div);
                });
            }).catch(e => {});
    }

    function loadChatMessages() {
        const inputField = document.getElementById('chatInput');
        const sendBtn = document.getElementById('chatSendBtn');
        const chatBody = document.getElementById('chatBody');

        if (chatUserLevel === 'admin' && activeChatTarget === '') return;

        if (chatUserLevel === 'admin') {
            inputField.disabled = false;
            sendBtn.disabled = false;
            inputField.placeholder = "Ketik balasan untuk " + activeChatTarget + "...";
            const quickRep = document.getElementById('quickReplies');
            if(quickRep) quickRep.style.display = 'flex';
        }

        const formData = new FormData();
        formData.append('chat_action', 'load');
        formData.append('target_user', activeChatTarget);
        
        fetch(window.location.pathname, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                const wasScrolledToBottom = chatBody.scrollHeight - chatBody.clientHeight <= chatBody.scrollTop + 5;
                
                if(data.length === 0 && chatUserLevel !== 'admin') {
                    chatBody.innerHTML = `<div class="chat-message bot">Halo Bpk/Ibu ${chatUserName}! Ada yang bisa kami bantu terkait data PKL hari ini?</div>`;
                    return;
                }

                chatBody.innerHTML = ''; 

                data.forEach(msg => {
                    const div = document.createElement('div');
                    let isMe = (msg.pengirim === chatUserName || (chatUserLevel === 'admin' && msg.pengirim === 'admin'));
                    
                    div.className = isMe ? 'chat-message user' : 'chat-message bot';
                    
                    let senderLabel = '';
                    if (!isMe) {
                        if(msg.role === 'admin') {
                            senderLabel = `<span class="chat-sender-name"><i class="fas fa-crown text-warning"></i> Admin Si Mantap</span>`;
                        } else {
                            senderLabel = `<span class="chat-sender-name"><i class="fas fa-user text-primary"></i> ${msg.pengirim}</span>`;
                        }
                    }

                    // Format Timestamp (Tanggal & Jam)
                    const msgDate = new Date(msg.waktu.replace(/-/g, '/')); 
                    const timeStr = msgDate.toLocaleTimeString('id-ID', {hour: '2-digit', minute:'2-digit'});
                    const dateStr = msgDate.toLocaleDateString('id-ID', {day: '2-digit', month: 'short'});
                    const timeHtml = `<span class="chat-time">${dateStr}, ${timeStr}</span>`;

                    div.innerHTML = senderLabel + msg.pesan + timeHtml;
                    chatBody.appendChild(div);
                });

                if (wasScrolledToBottom) {
                    chatBody.scrollTop = chatBody.scrollHeight;
                }
            }).catch(e => {});
    }

    function handleChatEnter(e) {
        if (e.key === 'Enter') sendChatMessage();
    }

    function sendChatMessage() {
        if (chatUserLevel === 'admin' && activeChatTarget === '') return;

        const input = document.getElementById('chatInput');
        const msg = input.value.trim();
        if (msg === '') return;
        
        input.value = ''; 

        const formData = new FormData();
        formData.append('chat_action', 'send');
        formData.append('pesan', msg);
        formData.append('target_user', activeChatTarget);

        fetch(window.location.pathname, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'ok') {
                    loadChatMessages(); 
                    setTimeout(() => {
                        const chatBody = document.getElementById('chatBody');
                        chatBody.scrollTop = chatBody.scrollHeight;
                    }, 200);
                }
            }).catch(e => {});
    }

    // Modal Konfirmasi Hapus Menggunakan SweetAlert2
    function clearChat() {
        Swal.fire({
            title: 'Hapus Semua Obrolan?',
            text: "Apakah Anda yakin ingin menghapus seluruh riwayat obrolan dari semua pembimbing? Tindakan ini tidak bisa dibatalkan.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hapus Semua!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('chat_action', 'clear');
                fetch(window.location.pathname, { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'ok') {
                            activeChatTarget = '';
                            document.getElementById('chatInput').disabled = true;
                            document.getElementById('chatInput').placeholder = "Pilih chat terlebih dahulu...";
                            document.getElementById('chatSendBtn').disabled = true;
                            const quickRep = document.getElementById('quickReplies');
                            if(quickRep) quickRep.style.display = 'none';
                            
                            document.getElementById('chatBody').innerHTML = `
                                <div class="chat-placeholder" style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:#94a3b8; text-align:center;">
                                    <i class="far fa-comments" style="font-size:40px; margin-bottom:10px;"></i>
                                    <span>Pilih nama pembimbing di sebelah kiri<br>untuk melihat dan membalas pesan.</span>
                                </div>`;
                            loadChatUsers();
                            Swal.fire({
                                icon: 'success',
                                title: 'Terhapus!',
                                text: 'Semua riwayat pesan berhasil dibersihkan.',
                                confirmButtonColor: '#1e40af'
                            });
                        }
                    }).catch(e => {});
            }
        });
    }

    // --- FITUR PENCARIAN DATABASE MULTI-BARIS ---
    function filterDatabase() {
        let input = document.getElementById("searchDatabase");
        let filter = input.value.toUpperCase();
        let groups = document.querySelectorAll(".data-group");

        groups.forEach(group => {
            let textContext = group.textContent || group.innerText;
            if (textContext.toUpperCase().indexOf(filter) > -1) {
                group.style.display = "";
            } else {
                group.style.display = "none";
            }
        });
    }

    // CHART 1: TREN PRESENSI (LINE CHART)
    let ctxLine = document.getElementById('presensiChart').getContext('2d');
    
    let gradientBlue = ctxLine.createLinearGradient(0, 0, 0, 280);
    gradientBlue.addColorStop(0, 'rgba(59, 130, 246, 0.7)'); 
    gradientBlue.addColorStop(0.6, 'rgba(59, 130, 246, 0.15)');
    gradientBlue.addColorStop(1, 'rgba(59, 130, 246, 0.0)');

    const lineShadowPlugin = {
        id: 'lineShadow',
        beforeDatasetDraw: (chart, args) => {
            if (args.meta.type === 'line') {
                const ctx = chart.ctx;
                ctx.save();
                ctx.shadowColor = 'rgba(59, 130, 246, 0.6)';
                ctx.shadowBlur = 12;
                ctx.shadowOffsetX = 0;
                ctx.shadowOffsetY = 6;
            }
        },
        afterDatasetDraw: (chart, args) => {
            if (args.meta.type === 'line') {
                chart.ctx.restore();
            }
        }
    };

    new Chart(ctxLine, {
        type: 'line',
        data: { 
            labels: <?php echo $js_presensi_labels; ?>, 
            datasets: [{ 
                label: 'Siswa Hadir', 
                data: <?php echo $js_presensi_values; ?>, 
                borderColor: '#3b82f6', 
                backgroundColor: gradientBlue, 
                borderWidth: 4,
                pointBackgroundColor: '#f59e0b',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 8,
                pointHoverBackgroundColor: '#dc2626',
                pointHoverBorderColor: '#ffffff',
                pointHoverBorderWidth: 3,
                fill: true,
                tension: 0.55
            }] 
        },
        plugins: [lineShadowPlugin],
        options: { 
            maintainAspectRatio: false, 
            interaction: { mode: 'index', intersect: false },
            plugins: { 
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.9)',
                    titleFont: { family: 'Poppins', size: 13, weight: '500' },
                    bodyFont: { family: 'Poppins', size: 14, weight: 'bold' },
                    padding: 12, cornerRadius: 8, displayColors: false,
                    callbacks: { label: function(context) { return 'Hadir: ' + context.parsed.y + ' Siswa'; } }
                }
            }, 
            scales: { 
                y: { 
                    beginAtZero: true, 
                    grid: { color: '#f1f5f9', borderDash: [5, 5], drawBorder: false }, 
                    ticks: { stepSize: 1, font: { family: 'Poppins', size: 11, weight: '500' }, color: '#94a3b8' },
                    border: { display: false }
                }, 
                x: { 
                    grid: { display: false, drawBorder: false }, 
                    ticks: { font: { family: 'Poppins', size: 11, weight: '600' }, color: '#64748b' },
                    border: { display: false }
                } 
            },
            animations: {
                y: { duration: 2500, easing: 'easeOutElastic' },
                x: { duration: 1500, easing: 'easeOutExpo' }
            }
        }
    });

    // CHART 2: SEBARAN KELAS (DOUGHNUT CHART)
    new Chart(document.getElementById('kelasChart'), {
        type: 'doughnut',
        data: { 
            labels: <?php echo $js_kelas_labels; ?>, 
            datasets: [{ 
                data: <?php echo $js_kelas_values; ?>, 
                backgroundColor: ['#0f172a', '#1e40af', '#3b82f6', '#60a5fa', '#bfdbfe'], 
                borderWidth: 3, 
                borderColor: '#ffffff',
                hoverOffset: 8
            }] 
        },
        options: { 
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: { 
                legend: { 
                    position: 'bottom', 
                    labels: { 
                        boxWidth: 12, usePointStyle: true,
                        font: { family: 'Poppins', size: 12, weight: '500' },
                        color: '#475569', padding: 15
                    } 
                },
                tooltip: {
                    backgroundColor: '#0f172a', titleFont: { family: 'Poppins', size: 13 },
                    bodyFont: { family: 'Poppins', size: 14, weight: 'bold' }, padding: 12, cornerRadius: 8
                }
            } 
        }
    });

    // CHART 3: TOP 5 LOKASI FAVORIT (BAR CHART)
    new Chart(document.getElementById('topLokasiChart'), {
        type: 'bar',
        data: {
            labels: <?php echo $js_top_lokasi_labels; ?>,
            datasets: [{
                label: 'Jumlah Peserta',
                data: <?php echo $js_top_lokasi_values; ?>,
                backgroundColor: 'rgba(59, 130, 246, 0.85)',
                borderColor: '#1e40af',
                borderWidth: 1,
                borderRadius: 4,
                hoverBackgroundColor: '#f59e0b'
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0f172a', titleFont: { family: 'Poppins', size: 13 },
                    bodyFont: { family: 'Poppins', size: 14, weight: 'bold' }, padding: 12, cornerRadius: 8,
                    callbacks: { label: function(context) { return ' ' + context.parsed.y + ' Siswa'; } }
                }
            },
            scales: {
                y: { 
                    beginAtZero: true, 
                    grid: { color: '#f1f5f9', drawBorder: false },
                    ticks: { stepSize: 1, font: { family: 'Poppins', size: 10 }, color: '#94a3b8' },
                    border: { display: false }
                },
                x: { 
                    grid: { display: false, drawBorder: false },
                    ticks: { font: { family: 'Poppins', size: 10, weight: '500' }, color: '#475569' },
                    border: { display: false }
                }
            },
            animation: {
                duration: 2000,
                easing: 'easeOutQuart'
            }
        }
    });
</script>
</body>
</html>