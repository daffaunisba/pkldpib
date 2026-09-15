<?php
// admin/monitoring-guru.php
// Halaman untuk Guru Pembimbing memonitor lokasi dan siswa bimbingannya.

include 'auth-check.php'; 
include '../config/db-koneksi.php'; 

$current_user = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin';
$current_user_id = $_SESSION['user_id'] ?? 0; 
$current_user_level = isset($_SESSION['level']) ? strtolower($_SESSION['level']) : 'user'; 

$guru_filter_id = 0;
$query_lokasi_bimbingan = "";
$guru_info = null;
$target_monitoring = 3; // Target standar jumlah kunjungan per lokasi

// --- 1. LOGIKA PENENTUAN GURU ID DAN QUERY ---

// A. Jika user adalah GURU/PEMBIMBING, cari guru_id mereka.
if ($current_user_level == 'pembimbing' || $current_user_level == 'guru') {
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

    // Tentukan query hanya untuk lokasi yang dibimbing guru ini, beserta hitungan monitoring
    $query_lokasi_bimbingan = "
        SELECT 
            l.lokasi_id, l.nama_lokasi, g.nama_guru,
            (SELECT COUNT(p.id) FROM peserta_didik p WHERE p.lokasi_id = l.lokasi_id) AS total_siswa,
            (SELECT COUNT(k.kunjungan_id) FROM monitoring_kunjungan k WHERE k.lokasi_id = l.lokasi_id) AS total_kunjungan
        FROM lokasi_pkl l
        LEFT JOIN guru g ON l.guru_id = g.guru_id
        WHERE l.guru_id = ?
        ORDER BY total_kunjungan DESC, l.nama_lokasi ASC
    ";

    $stmt_lokasi = $koneksi->prepare($query_lokasi_bimbingan);
    if ($stmt_lokasi) {
        $stmt_lokasi->bind_param("i", $guru_filter_id);
    }

} 
// B. Jika user adalah ADMIN, tampilkan SEMUA lokasi bimbingan.
elseif ($current_user_level == 'admin') {
    $query_lokasi_bimbingan = "
        SELECT 
            l.lokasi_id, l.nama_lokasi, g.nama_guru,
            (SELECT COUNT(p.id) FROM peserta_didik p WHERE p.lokasi_id = l.lokasi_id) AS total_siswa,
            (SELECT COUNT(k.kunjungan_id) FROM monitoring_kunjungan k WHERE k.lokasi_id = l.lokasi_id) AS total_kunjungan
        FROM lokasi_pkl l
        LEFT JOIN guru g ON l.guru_id = g.guru_id
        ORDER BY total_kunjungan DESC, l.nama_lokasi ASC
    ";
    
    $stmt_lokasi = $koneksi->prepare($query_lokasi_bimbingan);
}


// --- 2. EKSEKUSI QUERY DAN PENGAMBILAN DATA GURU INFO ---

$lokasi_count = 0;
$total_siswa_terdaftar = 0;
$lokasi_data_array = [];

if (isset($stmt_lokasi) && $stmt_lokasi) {
    $stmt_lokasi->execute();
    $lokasi_data = $stmt_lokasi->get_result();
    $lokasi_count = $lokasi_data->num_rows;
    
    // Hitung total siswa di semua lokasi yang ditampilkan
    while ($row = $lokasi_data->fetch_assoc()) {
        $total_siswa_terdaftar += $row['total_siswa'];
        $lokasi_data_array[] = $row;
    }
    
    // Tentukan guru_info untuk header banner
    if ($current_user_level == 'pembimbing' || $current_user_level == 'guru') {
        $guru_info = [
            'nama' => $lokasi_data_array[0]['nama_guru'] ?? $current_user,
            'lokasi_count' => $lokasi_count,
            'total_siswa' => $total_siswa_terdaftar
        ];
    } elseif ($current_user_level == 'admin') {
        $guru_info = [
            'nama' => $current_user,
            'lokasi_count' => $lokasi_count,
            'total_siswa' => $total_siswa_terdaftar
        ];
    }
    $stmt_lokasi->close();
} else {
    $guru_info = ['nama' => $current_user, 'lokasi_count' => 0, 'total_siswa' => 0];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin - Monitoring PKL | Si Mantap PKL</title>
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

        /* STRUKTUR GAP PADDING SINKRON */
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
            white-space: nowrap;
        }

        .page-header-controls h1::after {
            content: ''; position: absolute; left: 0; bottom: -5px; width: 50px; height: 4px; background: var(--mantap-blue-main); border-radius: 2px;
        }

        /* --- STYLING HEADER ACTIONS (SEARCH PENCARIAN) --- */
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
            width: 260px; 
            transition: all 0.3s ease; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.02); 
            outline: none; 
        }
        .search-input:focus { 
            border-color: var(--mantap-blue-light); 
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); 
            width: 300px; 
        }

        /* KPI INFO HEADER BANNER */
        .info-header {
            background-color: var(--mantap-blue-main);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: left;
            box-shadow: 0 4px 6px -1px rgba(30, 64, 175, 0.15);
        }
        .info-header h2 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 700;
            color: white !important;
            text-transform: uppercase;
        }
        .info-header p {
            margin: 6px 0 0 0;
            font-size: 13.5px;
            opacity: 0.9;
            font-weight: 500;
        }

        /* CARD EMBED TABEL */
        .glass-panel-table {
            background: white !important; 
            padding: 25px !important; 
            border-radius: 16px !important; 
            border: 2px solid #e2e8f0 !important; 
            box-sizing: border-box; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); 
            width: 100%;
        }

        .table-container-fixed { width: 100%; overflow-x: auto; box-sizing: border-box; border: none !important; -webkit-overflow-scrolling: touch; }

        /* OUTLINE TEBAL CORES & AUTO LAYOUT */
        .custom-table-monitor {
            width: 100%; table-layout: auto; border-collapse: collapse; background: white; border-radius: 4px; overflow: hidden; border: 2px solid #1e40af; min-width: 900px;
        }
        .custom-table-monitor th { 
            background: #1e40af; color: white; padding: 14px 10px; font-size: 13px; text-transform: uppercase; font-weight: 700; text-align: center; border: 1px solid #cbd5e1; box-sizing: border-box; white-space: nowrap;
        }

        .custom-table-monitor td { 
            padding: 12px 10px; font-size: 13px; vertical-align: middle; word-wrap: break-word; overflow-wrap: break-word; text-align: center; border: 1px solid #cbd5e1; color: #0f172a; background-color: white !important; box-sizing: border-box;
        }
        .custom-table-monitor tbody tr:hover td { background-color: #f8fafc !important; }

        /* PROGRESS BAR IN TABLE */
        .progress-text-table { display: flex; justify-content: space-between; font-size: 11px; font-weight: 700; color: #475569; margin-bottom: 4px; }
        .progress-wrapper-table { width: 100%; background: #e2e8f0; border-radius: 6px; height: 8px; overflow: hidden; }
        .progress-fill-table { height: 100%; border-radius: 6px; transition: width 0.5s ease-in-out; }

        .btn-aksi-view {
            background-color: var(--mantap-blue-main);
            color: white !important;
            padding: 6px 14px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 11px;
            font-weight: 600;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            box-shadow: 0 2px 4px rgba(30, 64, 175, 0.15);
            white-space: nowrap;
        }
        .btn-aksi-view:hover {
            background-color: var(--mantap-blue-light);
            transform: translateY(-1px);
        }
        
        .sidebar-nav ul, .sidebar-nav li, .dropdown-menu, .dropdown-menu li { list-style: none !important; margin: 0; padding: 0; }
        
        /* =========================================================================
           RESPONSIVE VIEWPORT SMARTPHONE (HP SCROLLABLE TABLE MODE)
        ========================================================================= */
        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 

            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; }
            .page-header-controls h1 { font-size: 1.4rem !important; margin-bottom: 5px; white-space: normal; }
            
            .header-actions-group { width: 100%; flex-direction: column !important; align-items: stretch !important; }
            .search-wrapper { width: 100%; }
            .search-input { width: 100%; box-sizing: border-box; }
            .search-input:focus { width: 100%; }

            .info-header { padding: 16px 14px !important; border-radius: 10px !important; }
            .info-header h2 { font-size: 1.2rem !important; }
            .info-header p { font-size: 12.5px !important; }

            .glass-panel-table { padding: 16px 10px !important; border-radius: 12px !important; border: 2px solid #cbd5e1 !important; }
            
            .table-container-fixed { display: block !important; width: 100% !important; overflow-x: auto !important; }
            .custom-table-monitor th, .custom-table-monitor td { padding: 12px 10px !important; }
            
            .btn-aksi-view { padding: 7px 12px !important; font-size: 11.5px !important; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; ?>

<div class="main-content-wrapper">

    <?php include 'panel/navbar.php'; ?>

    <div class="admin-main-content"> 
        <div class="page-header-controls">
            <h1>Monitoring Pembimbingan PKL</h1>
            <div class="header-actions-group">
                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" class="search-input" placeholder="Cari Mitra DUDI atau Pembimbing..." onkeyup="filterTable()">
                </div>
            </div>
        </div>
        
        <?php if ($guru_info): ?>
            <div class="info-header">
                <h2>
                    <i class="fas fa-chart-pie me-2"></i>
                    <?php 
                        if ($current_user_level == 'admin') {
                            echo "Administrator Monitoring";
                        } else {
                            echo htmlspecialchars($guru_info['nama']);
                        }
                    ?>
                </h2>
                <p>
                    <?php if ($current_user_level == 'admin'): ?>
                        Sistem mendeteksi total <strong><?php echo $lokasi_count; ?></strong> mitra industri aktif, dengan sebaran <strong><?php echo $total_siswa_terdaftar; ?></strong> peserta didik terdaftar.
                    <?php else: ?>
                        Anda ditugaskan mengawal pendaftaran di <strong><?php echo $lokasi_count; ?></strong> lokasi industri dengan total bimbingan aktif sebanyak <strong><?php echo $total_siswa_terdaftar; ?></strong> siswa.
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>

        <div class="glass-panel-table">
            <div class="table-container-fixed">
                <?php if (!empty($lokasi_data_array)): ?>
                    <table class="custom-table-monitor" id="monitoringTable">
                        <thead>
                            <tr>
                                <th style="width: 50px;">NO</th> 
                                <th style="text-align: left; padding-left: 15px; width: 35%;">Mitra Penempatan Industri (DUDI)</th>      
                                <th style="width: 25%; text-align: left; padding-left: 15px;">Progres Kunjungan Monitoring</th>
                                <th style="width: 15%;">Jumlah Siswa</th>
                                <th style="width: 120px;">Aksi Operasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $row_num = 1; foreach ($lokasi_data_array as $row): 
                                $kunjungan = (int)$row['total_kunjungan'];
                                $persen_progress = min(100, round(($kunjungan / $target_monitoring) * 100));
                                $warna_bar = ($persen_progress >= 100) ? '#22c55e' : 'var(--mantap-blue-main)';
                            ?>
                            <tr>
                                <td style="font-weight: 700; color:#64748b; text-align: center;"><?php echo $row_num++; ?></td>
                                
                                <td style="text-align: left; padding-left: 15px;">
                                    <div class="dudi-name" style="font-weight: 700; color: var(--mantap-blue-dark); text-transform: uppercase; font-size:13.5px;">
                                        <?php echo htmlspecialchars($row['nama_lokasi'] ?? 'N/A'); ?>
                                    </div>
                                    <div class="guru-name" style="font-weight: 600; color: #64748b; font-size:11.5px; margin-top: 4px;">
                                        <i class="fas fa-user-tie opacity-50 me-1"></i> <?php echo htmlspecialchars($row['nama_guru'] ?? 'Belum Ditentukan'); ?>
                                    </div>
                                </td>
                                
                                <td style="text-align: left; padding: 0 15px;">
                                    <div class="progress-text-table">
                                        <span>Target: <?php echo $target_monitoring; ?></span>
                                        <span style="color: <?php echo $warna_bar; ?>;"><?php echo $kunjungan; ?>/<?php echo $target_monitoring; ?> (<?php echo $persen_progress; ?>%)</span>
                                    </div>
                                    <div class="progress-wrapper-table">
                                        <div class="progress-fill-table" style="width: <?php echo $persen_progress; ?>%; background-color: <?php echo $warna_bar; ?>;"></div>
                                    </div>
                                </td>

                                <td style="font-weight: 700; color: var(--mantap-blue-main); font-size: 13.5px; text-align: center;">
                                    <?php echo (int)$row['total_siswa']; ?> Siswa
                                </td>
                                
                                <td style="text-align: center;">
                                    <a href="lokasi-siswa-detail.php?lokasi_id=<?php echo $row['lokasi_id']; ?>" class="btn-aksi-view">
                                        <i class="fas fa-search"></i> Buka Detail
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php elseif ($current_user_level == 'pembimbing' || $current_user_level == 'guru'): ?>
                    <div style="text-align: center; color: #ef4444; font-style: italic; padding: 25px; border: 2px dashed #cbd5e1; background: #fff; border-radius: 8px; font-weight: 500;">
                        <i class="fas fa-exclamation-triangle fa-lg mb-2" style="display: block;"></i> Akun Anda belum dikaitkan dengan skema bimbingan instansi mitra manapun.
                    </div>
                <?php else: ?>
                    <div style="text-align: center; color: #ef4444; font-style: italic; padding: 25px; border: 2px dashed #cbd5e1; background: #fff; border-radius: 8px; font-weight: 500;">
                        <i class="fas fa-folder-open fa-lg mb-2" style="display: block;"></i> Belum ada data lokasi instansi mitra PKL yang terdaftar dalam pangkalan data aktif.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const loginSuccessMessage = "<?php echo isset($_SESSION['login_success_message']) ? htmlspecialchars($_SESSION['login_success_message'], ENT_QUOTES) : ''; ?>";
    
    if (loginSuccessMessage) {
        Swal.fire({
            icon: 'success',
            title: 'Sesi Login Berhasil!',
            text: loginSuccessMessage,
            confirmButtonColor: '#1e40af'
        });
        <?php unset($_SESSION['login_success_message']); ?>
    }
});

// --- FITUR PENCARIAN REAL-TIME ---
function filterTable() {
    var input, filter, table, tr, tdLokasi, i, txtValue;
    input = document.getElementById("searchInput");
    filter = input.value.toUpperCase();
    table = document.getElementById("monitoringTable");
    
    if (!table) return; 
    
    tr = table.getElementsByTagName("tr");

    for (i = 1; i < tr.length; i++) {
        // Targetkan kolom index 1 (Nama Mitra DUDI & Guru Pembimbing)
        tdLokasi = tr[i].getElementsByTagName("td")[1]; 
        
        if (tdLokasi) {
            // Ambil seluruh text content yang ada di dalam <td> (Gabungan Dudi + Guru)
            txtValue = tdLokasi.textContent || tdLokasi.innerText;
            
            if (txtValue.toUpperCase().indexOf(filter) > -1) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }
}
</script>

<?php include 'panel/footer.php'; ?>
</body>
</html>