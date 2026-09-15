<?php
// admin/log-wa.php
include 'auth-check.php';
include '../config/db-koneksi.php';

// --- PROTEKSI HAK AKSES MUTLAK: KHUSUS ADMIN ---
$current_user_level = $_SESSION['level'] ?? 'user'; 
if ($current_user_level !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$current_user = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin';

// ---------------------------------------------------------------------
// 1. AUTO-CREATE TABEL LOG WHATSAPP (ANTI-ERROR)
// ---------------------------------------------------------------------
$check_table = $koneksi->query("SHOW TABLES LIKE 'log_whatsapp'");
if ($check_table && $check_table->num_rows == 0) {
    $koneksi->query("
        CREATE TABLE log_whatsapp (
            id INT AUTO_INCREMENT PRIMARY KEY,
            waktu DATETIME DEFAULT CURRENT_TIMESTAMP,
            target_nomor VARCHAR(100),
            jenis_pesan VARCHAR(100),
            pesan TEXT,
            status VARCHAR(50),
            response_api TEXT
        )
    ");
}

// ---------------------------------------------------------------------
// 2. FUNGSI FORMAT TANGGAL INDONESIA
// ---------------------------------------------------------------------
function formatTanggalIndo($tanggal) {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $pecahkan = explode('-', date('Y-m-d', strtotime($tanggal)));
    return $pecahkan[2] . ' ' . $bulan[(int)$pecahkan[1]] . ' ' . $pecahkan[0];
}

// ---------------------------------------------------------------------
// 3. QUERY STATISTIK
// ---------------------------------------------------------------------
$stat_total = $koneksi->query("SELECT COUNT(*) as total FROM log_whatsapp")->fetch_assoc()['total'] ?? 0;
$stat_sukses = $koneksi->query("SELECT COUNT(*) as total FROM log_whatsapp WHERE status = 'Terkirim'")->fetch_assoc()['total'] ?? 0;
$stat_gagal = $koneksi->query("SELECT COUNT(*) as total FROM log_whatsapp WHERE status != 'Terkirim'")->fetch_assoc()['total'] ?? 0;

// ---------------------------------------------------------------------
// 4. QUERY DATA LOG WA
// ---------------------------------------------------------------------
$query_log = "SELECT * FROM log_whatsapp ORDER BY waktu DESC LIMIT 200";
$log_data = $koneksi->query($query_log);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Log Pengiriman WhatsApp | Si Mantap PKL</title>
    <link rel="stylesheet" href="../css/style.css"> 
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <style>
        :root {
            --mantap-blue-dark: #0f172a;
            --mantap-blue-main: #1e40af;
            --mantap-blue-light: #3b82f6;
            --mantap-blue-soft: #eff6ff;
            --mantap-green: #1e40af;
        }

        body { 
            font-family: 'Poppins', sans-serif; 
            background-color: #f8fafc; 
            color: #334155; 
            margin: 0;
            overflow-x: hidden !important;
        }

        .main-content-wrapper, .admin-main-content {
            max-width: 100% !important;
            width: 100% !important;
            box-sizing: border-box !important;
            overflow-x: hidden !important;
        }

        .admin-main-content { padding: 20px 25px 30px 25px !important; clear: both; }

        .dashboard-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 25px; width: 100%; flex-wrap: wrap; gap: 15px;
        }

        .dashboard-header h1 {
            font-weight: 700; color: #0f172a; font-size: 1.8rem; margin: 0; position: relative; white-space: nowrap;
        }
        .dashboard-header h1::after {
            content: ''; position: absolute; left: 0; bottom: -5px; width: 50px; height: 4px; background: var(--mantap-green); border-radius: 2px;
        }

        .header-actions-group { display: flex; gap: 10px; align-items: center; }
        
        .search-wrapper { position: relative; display: flex; align-items: center; }
        .search-wrapper i { position: absolute; left: 15px; color: #64748b; font-size: 14px; }
        .search-input { 
            padding: 9px 15px 9px 38px; border: 1px solid #cbd5e1; border-radius: 20px; 
            font-family: 'Poppins', sans-serif; font-size: 13px; width: 220px; transition: all 0.3s ease; 
            outline: none; 
        }
        .search-input:focus { border-color: var(--mantap-green); width: 260px; }

        /* KPI STATS */
        .kpi-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 25px; width: 100%; }
        .kpi-card-new {
            padding: 20px; border-radius: 12px; color: white; position: relative; overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); transition: 0.2s;
        }
        .kpi-card-new:hover { transform: translateY(-2px); }
        .kpi-card-new i { position: absolute; right: -10px; top: -10px; font-size: 3.5rem; opacity: 0.18; }
        .kpi-card-new h3 { font-size: 1.6rem; font-weight: 700; margin: 0; }
        .kpi-card-new p { font-size: 10.5px; text-transform: uppercase; letter-spacing: 1px; margin: 0; font-weight: 600; opacity: 0.9; }

        .glass-panel {
            background: white !important; padding: 25px !important; border-radius: 16px !important;
            border: 2px solid #e2e8f0 !important; box-sizing: border-box; width: 100%;
        }

        .table-container-fixed { width: 100%; overflow-x: auto; border: none !important; }
        
        /* OUTLINE TEBAL BERWARNA BIRU UTAMA & PERBAIKAN LEBAR KOLOM */
        .custom-table { width: 100%; table-layout: fixed; border-collapse: collapse; background: white; border-radius: 4px; overflow: hidden; border: 2px solid var(--mantap-blue-main); }
        .custom-table th { background: var(--mantap-blue-main); color: white; padding: 14px 10px; font-size: 13px; text-transform: uppercase; font-weight: 700; text-align: center; border: 1px solid #cbd5e1; }
        
        .custom-table th.col-no { width: 60px; }
        .custom-table th.col-waktu { width: 170px; text-align: left; padding-left: 15px; }
        .custom-table th.col-target { width: 170px; text-align: left; padding-left: 15px; }
        .custom-table th.col-jenis { width: auto; text-align: left; padding-left: 15px; }
        .custom-table th.col-status { width: 110px; }
        .custom-table th.col-detail { width: 90px; }

        .custom-table td { padding: 12px 10px; font-size: 13px; color: #0f172a; vertical-align: middle; border: 1px solid #cbd5e1; text-align: center; }
        .custom-table tbody tr:hover td { background-color: #f8fafc !important; }

        .status-badge { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 700; color: white; display: inline-block; }
        .status-sukses { background-color: #22c55e; }
        .status-gagal { background-color: #ef4444; }

        .btn-view-msg { 
            background: #0ea5e9; color: white; border: none; padding: 6px 12px; border-radius: 6px; 
            font-size: 11.5px; font-weight: 600; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 5px; 
        }
        .btn-view-msg:hover { background: #0284c7; }

        /* MODAL ENGINE */
        .mantap-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(15, 23, 42, 0.6); z-index: 999999 !important; backdrop-filter: blur(3px); }
        .mantap-modal-dialog { position: relative; width: 90%; max-width: 550px; margin: 4rem auto; box-sizing: border-box !important; }
        .mantap-modal-content { background-color: white; padding: 25px; border-radius: 12px; border: 2px solid #cbd5e1; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15); box-sizing: border-box !important; }
        .mantap-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
        .mantap-modal-header h2 { font-size: 1.3rem; margin: 0; color: #0f172a; font-weight: 700; }
        .close-modal-btn { background: none; border: none; font-size: 1.7rem; color: #64748b; cursor: pointer; line-height: 1; }
        
        .msg-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 15px; margin-bottom: 15px; white-space: pre-wrap; font-family: 'Poppins', sans-serif; font-size: 13px; color: #166534; line-height: 1.6; max-height: 300px; overflow-y: auto; text-align: left;}
        .api-box { background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px; font-family: monospace; font-size: 11px; color: #475569; word-break: break-all; max-height: 100px; overflow-y: auto; text-align: left;}

        /* =========================================================================
           RESPONSIF UNTUK HP (PENGUNCI TEKS NOWRAP)
        ========================================================================= */
        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 
            .dashboard-header { flex-direction: column; align-items: flex-start; gap: 15px; }
            .header-actions-group, .search-wrapper, .search-input { width: 100%; }
            
            /* Kartu Statistik 1 Kolom ke Bawah Agar Rapi */
            .kpi-row { grid-template-columns: 1fr !important; gap: 10px !important; padding: 5px 0 20px 0 !important; }
            .kpi-card-new { padding: 16px 15px !important; border-radius: 10px !important; display: block !important; text-align: left; }
            .kpi-card-new h3 { font-size: 1.5rem !important; margin-top: 4px !important; }
            .kpi-card-new p { font-size: 11px !important; margin: 0 !important; }
            .kpi-card-new i { display: block !important; font-size: 3.5rem !important; right: -5px !important; top: -5px !important; }

            .glass-panel { padding: 16px 10px !important; }
            
            /* PENGATURAN SCROLL TABEL HP ANTI-TABRAKAN */
            .table-container-fixed { display: block !important; width: 100% !important; overflow-x: auto !important; border: none !important; -webkit-overflow-scrolling: touch; }
            .custom-table { table-layout: auto !important; min-width: 900px !important; }
            .custom-table th, .custom-table td { padding: 12px 10px !important; font-size: 13px !important; white-space: nowrap !important; }
            .custom-table th.col-no, .custom-table th.col-waktu, .custom-table th.col-target, .custom-table th.col-jenis, .custom-table th.col-status, .custom-table th.col-detail { width: auto !important; }
            
            .mantap-modal-dialog { margin: 1.5rem auto !important; width: 95% !important; max-width: 100% !important; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; include 'panel/navbar.php'; ?>

<div class="main-content-wrapper">
    <div class="admin-main-content">
        
        <div class="dashboard-header">
            <h1><i class="fab fa-whatsapp" style="color: var(--mantap-green); margin-right: 8px;"></i> Log WhatsApp</h1>
            <div class="header-actions-group">
                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" class="search-input" placeholder="Cari target / pesan..." onkeyup="filterTable()">
                </div>
            </div>
        </div>

        <div class="kpi-row">
            <div class="kpi-card-new" style="background: linear-gradient(135deg, #1e40af 0%, #1d4ed8 100%);">
                <i class="fas fa-paper-plane"></i>
                <p>Total Pesan Keluar</p>
                <h3><?php echo $stat_total; ?></h3>
            </div>
            <div class="kpi-card-new" style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);">
                <i class="fas fa-check-circle"></i>
                <p>Berhasil Terkirim</p>
                <h3><?php echo $stat_sukses; ?></h3>
            </div>
            <div class="kpi-card-new" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                <i class="fas fa-exclamation-circle"></i>
                <p>Gagal Terkirim</p>
                <h3><?php echo $stat_gagal; ?></h3>
            </div>
        </div>

        <div class="glass-panel">
            <div class="table-container-fixed">
                <table class="custom-table" id="logTable">
                    <thead>
                        <tr>
                            <th class="col-no">NO</th>
                            <th class="col-waktu">WAKTU PENGIRIMAN</th>
                            <th class="col-target">TARGET NOMOR</th>
                            <th class="col-jenis">JENIS & PREVIEW PESAN</th>
                            <th class="col-status">STATUS</th>
                            <th class="col-detail">DETAIL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($log_data && $log_data->num_rows > 0): ?>
                            <?php $no = 1; while ($row = $log_data->fetch_assoc()): 
                                $is_success = ($row['status'] === 'Terkirim');
                                // Membuat preview pendek untuk tabel
                                $pesan_singkat = mb_strimwidth($row['pesan'], 0, 75, "...");
                            ?>
                            <tr>
                                <td style="font-weight: 700; color: #64748b;"><?php echo $no++; ?></td>
                                <td style="text-align: left; padding-left: 15px;">
                                    <div style="font-weight: 700; color: #0f172a; font-size: 13.5px;"><?php echo formatTanggalIndo($row['waktu']); ?></div>
                                    <div style="font-size: 11.5px; font-weight: 600; color: #64748b; margin-top: 2px;"><i class="fas fa-clock opacity-50"></i> <?php echo date('H:i:s', strtotime($row['waktu'])); ?> WIB</div>
                                </td>
                                <td style="text-align: left; padding-left: 15px;">
                                    <span class="badge-info-pill" style="font-size: 13.5px; background: var(--mantap-blue-soft); color: var(--mantap-blue-main); font-family: monospace;"><i class="fas fa-phone-alt opacity-50 me-1"></i><?php echo htmlspecialchars($row['target_nomor']); ?></span>
                                </td>
                                <td style="text-align: left; padding-left: 15px;">
                                    <div style="font-weight: 700; color: #3b82f6; font-size: 13px;">
                                        <?php echo htmlspecialchars($row['jenis_pesan']); ?>
                                    </div>
                                    <div style="font-size: 11px; color: #64748b; margin-top: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 320px;">
                                        <?php echo htmlspecialchars($pesan_singkat); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $is_success ? 'status-sukses' : 'status-gagal'; ?>">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn-view-msg" 
                                        data-msg="<?php echo htmlspecialchars($row['pesan'], ENT_QUOTES, 'UTF-8'); ?>" 
                                        data-api="<?php echo htmlspecialchars($row['response_api'], ENT_QUOTES, 'UTF-8'); ?>"
                                        onclick="showLogDetail(this)">
                                        <i class="fa fa-eye me-1"></i> Lihat
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="padding: 40px; text-align: center; color: #64748b; font-style: italic;">
                                    Belum ada log pengiriman pesan WhatsApp yang tercatat di sistem.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<div class="mantap-modal" id="modalLogDetail">
    <div class="mantap-modal-dialog">
        <div class="mantap-modal-content">
            <div class="mantap-modal-header">
                <h2 style="font-size: 1.3rem;"><i class="fab fa-whatsapp text-success me-2"></i> Detail Isi Pesan</h2>
                <button type="button" class="close-modal-btn" onclick="document.getElementById('modalLogDetail').style.display='none'">&times;</button>
            </div>
            
            <label style="display: block; margin-bottom: 6px; font-weight: 700; font-size: 13px; color: #0f172a; text-align: left;">Preview Pesan Terkirim:</label>
            <div class="msg-box" id="detail_pesan"></div>

            <label style="display: block; margin-top: 15px; margin-bottom: 6px; font-weight: 700; font-size: 13px; color: #0f172a; text-align: left;">Response Fonnte API (Server):</label>
            <div class="api-box" id="detail_api"></div>
        </div>
    </div>
</div>

<?php include 'panel/footer.php'; ?>

<script>
    function filterTable() {
        var input, filter, table, tr, tdTarget, tdJenis, i, txtValueTarget, txtValueJenis;
        input = document.getElementById("searchInput");
        filter = input.value.toUpperCase();
        table = document.getElementById("logTable");
        
        if (!table) return; 
        
        tr = table.getElementsByTagName("tr");

        for (i = 1; i < tr.length; i++) {
            tdTarget = tr[i].getElementsByTagName("td")[2]; 
            tdJenis = tr[i].getElementsByTagName("td")[3]; 
            
            if (tdTarget && tdJenis) {
                txtValueTarget = tdTarget.textContent || tdTarget.innerText;
                txtValueJenis = tdJenis.textContent || tdJenis.innerText;
                
                if (txtValueTarget.toUpperCase().indexOf(filter) > -1 || txtValueJenis.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }
    }

    function showLogDetail(button) {
        document.getElementById('detail_pesan').textContent = button.getAttribute('data-msg');
        document.getElementById('detail_api').textContent = button.getAttribute('data-api');
        document.getElementById('modalLogDetail').style.display = 'block';
    }

    // Global Window Click to close modals
    window.addEventListener('click', (e) => { 
        var modal = document.getElementById('modalLogDetail');
        if (e.target === modal) { modal.style.display = 'none'; }
    });
</script>
</body>
</html>