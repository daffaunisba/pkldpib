<?php
// admin/asistensi.php
include 'auth-check.php'; 
include '../config/db-koneksi.php'; 

$current_user = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin';
$current_user_id = $_SESSION['user_id'] ?? 0; 
$current_user_level = isset($_SESSION['level']) ? strtolower($_SESSION['level']) : 'admin'; 
$max_asistensi = 3; 

$guru_filter_id = 0;
$filter_clause = "";

// ---------------------------------------------------------------------
// 1. TENTUKAN FILTER BERDASARKAN LEVEL USER
// ---------------------------------------------------------------------
if ($current_user_level == 'pembimbing' || $current_user_level == 'guru') {
    $stmt_get_guru_id = $koneksi->prepare("SELECT guru_id FROM guru WHERE user_id = ?");
    if ($stmt_get_guru_id) {
        $stmt_get_guru_id->bind_param("i", $current_user_id);
        $stmt_get_guru_id->execute();
        $result_guru_id = $stmt_get_guru_id->get_result();
        if ($result_guru_id->num_rows > 0) {
            $guru_filter_id = $result_guru_id->fetch_assoc()['guru_id'];
            $filter_clause = "WHERE l.guru_id = {$guru_filter_id}";
        } else {
            $filter_clause = "WHERE 1 = 0"; 
        }
        $stmt_get_guru_id->close();
    }
}

// ---------------------------------------------------------------------
// 2. QUERY DATA PESERTA DENGAN FILTER
// ---------------------------------------------------------------------
$query_peserta = "
    SELECT 
        p.id, p.nama AS nama_siswa, p.kelas,
        l.nama_lokasi, l.lokasi_id, l.kuota_max,
        g.nama_guru, g.guru_id,
        (
            SELECT COUNT(asistensi_id) 
            FROM asistensi_pkl 
            WHERE siswa_id = p.id
        ) AS asistensi_count
    FROM peserta_didik p
    LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
    LEFT JOIN guru g ON l.guru_id = g.guru_id
    {$filter_clause}
    ORDER BY l.nama_lokasi ASC, g.nama_guru ASC, p.nama ASC
";

$peserta_data = $koneksi->query($query_peserta);

$grouped_data = [];
$total_siswa_terdaftar = 0;

if ($peserta_data) {
    while ($row = $peserta_data->fetch_assoc()) {
        $lokasi_key = $row['lokasi_id'] ?? 'NULL_LOKASI';
        $guru_key = $row['guru_id'] ?? 'NULL_GURU';
        $key = $lokasi_key . '|' . $guru_key;
        
        if (!isset($grouped_data[$key])) {
            $grouped_data[$key] = [
                'lokasi_info' => htmlspecialchars($row['nama_lokasi'] ?? 'BELUM PILIH LOKASI'),
                'guru_info' => htmlspecialchars($row['nama_guru'] ?? 'BELUM DITENTUKAN'),
                'kuota_max' => $row['kuota_max'] ?? 0,
                'siswa_list' => [],
            ];
        }
        $grouped_data[$key]['siswa_list'][] = $row;
        $total_siswa_terdaftar++;
    }
}

function formatTanggal($tanggal) {
    if (empty($tanggal)) return '-';
    return date('d F Y', strtotime($tanggal));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin - Asistensi PKL | Si Mantap PKL</title>
    <link rel="stylesheet" href="../css/style.css"> 
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            box-shadow: none !important;
        }

        /* STRUKTUR UTAMA KONSISTEN DENGAN PESERTA_LIST & PERIODE */
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

        /* SEARCH BAR STYLE */
        .search-wrapper { position: relative; display: flex; align-items: center; }
        .search-wrapper i { position: absolute; left: 15px; color: #64748b; font-size: 14px; }
        .search-input { 
            padding: 9px 15px 9px 38px; border: 1px solid #cbd5e1; border-radius: 20px; 
            font-family: 'Poppins', sans-serif; font-size: 13px; width: 280px; transition: all 0.3s ease; 
            outline: none; background: #f8fafc;
        }
        .search-input:focus { border-color: var(--mantap-blue-main); width: 320px; background: white; box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.15); }

        .glass-panel-table {
            background: white !important; 
            padding: 25px !important; 
            border-radius: 16px !important; 
            border: 2px solid #e2e8f0 !important; 
            box-sizing: border-box; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); 
            width: 100%;
        }

        .table-container-fixed { width: 100%; overflow-x: auto; box-sizing: border-box; border: none !important; }

        /* OUTLINE TEBAL & STRUKTUR TABEL GELOMBANG */
        .admin-main-content table {
            width: 100%; table-layout: fixed; border-collapse: collapse; background: white; border-radius: 4px; overflow: hidden; border: 2px solid #1e40af; 
        }

        .admin-main-content table th { 
            background: #1e40af; color: white; padding: 14px 6px; font-size: 13px; text-transform: uppercase; font-weight: 700; text-align: center; border: 1px solid #cbd5e1; box-sizing: border-box;
        }

        /* FIXED COLUMNS DESIGN */
        .admin-main-content table th.col-no { width: 60px; } 
        .admin-main-content table th.col-lokasi { width: 28%; text-align: left; } 
        .admin-main-content table th.col-pembimbing { width: 22%; text-align: left; } 
        .admin-main-content table th.col-status { width: 37%; text-align: left; } 
        .admin-main-content table th.col-aksi { width: 110px; }

        .admin-main-content table td {
            padding: 12px 10px; font-size: 13px; vertical-align: middle; word-wrap: break-word; overflow-wrap: break-word; text-align: center; border: 1px solid #cbd5e1; color: #0f172a; background-color: white !important; box-sizing: border-box;
        }
        .admin-main-content tr:hover td { background-color: #f8fafc !important; }

        /* BADGES & SISWA ITEM */
        .siswa-item { padding: 2px 0; text-align: left; }
        
        /* Flex wrapper agar badge ada di samping nama */
        .siswa-name-wrapper { 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            flex-wrap: wrap; 
            margin-bottom: 4px;
        }
        
        .siswa-name { font-weight: 700; color: #0f172a; text-transform: uppercase; font-size: 13px; }
        
        .status-badge { padding: 3px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; color: white; display: inline-flex; align-items: center; gap: 4px; }
        
        /* WARNA STATUS BARU */
        .status-badge.Lengkap { background-color: #22c55e; box-shadow: 0 2px 4px rgba(34,197,94,0.15); } /* Hijau */
        .status-badge.Proses { background-color: #f59e0b; box-shadow: 0 2px 4px rgba(245,158,11,0.15); } /* Kuning */
        .status-badge.Belum { background-color: #ef4444; box-shadow: 0 2px 4px rgba(239,68,68,0.15); } /* Merah */

        .btn-aksi { 
            background-color: var(--mantap-blue-main); color: white !important; padding: 6px 0; border-radius: 4px; text-decoration: none; font-size: 11px; font-weight: 600; text-align: center; display: block; width: 85px; margin: 0 auto; box-shadow: 0 2px 4px rgba(30,64,175,0.15); 
        }
        .btn-aksi:hover { background-color: var(--mantap-blue-light); }
        
        .sidebar-nav ul, .sidebar-nav li, .dropdown-menu, .dropdown-menu li { list-style: none !important; margin: 0; padding: 0; }

        /* =========================================================================
           FIX TOTAL RESPONSIVE MOBILE VIEWPORT (HP)
        ========================================================================= */
        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 

            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; padding: 5px 0px !important; }
            .page-header-controls h1 { font-size: 1.4rem !important; }
            
            .search-wrapper { width: 100%; margin-top: 5px; }
            .search-input { width: 100%; box-sizing: border-box; }
            .search-input:focus { width: 100%; }

            .glass-panel-table { padding: 16px 10px !important; border-radius: 12px !important; border: 2px solid #cbd5e1 !important; }
            .table-container-fixed { display: block !important; width: 100% !important; overflow-x: auto !important; }

            /* Cegah tabel hancur akibat rowspan kaku di HP dengan memaksa scroll minimum horizontal */
            .admin-main-content table { table-layout: auto !important; min-width: 950px !important; }
            .admin-main-content table th, .admin-main-content table td { padding: 12px 10px !important; font-size: 13px !important; }
            
            .admin-main-content table th.col-no, .admin-main-content table th.col-lokasi, .admin-main-content table th.col-pembimbing, .admin-main-content table th.col-status, .admin-main-content table th.col-aksi { width: auto !important; }
            .admin-main-content table td[style*="text-align: left"] { text-align: left !important; }
            
            .btn-aksi { width: 80px !important; padding: 7px 0 !important; font-size: 11.5px !important; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; ?>

<div class="main-content-wrapper">

    <?php include 'panel/navbar.php'; ?>

    <div class="admin-main-content"> 
        <div class="page-header-controls">
            <h1>Rekap Asistensi Laporan PKL</h1>
            
            <div class="search-wrapper">
                <i class="fas fa-search"></i>
                <input type="text" id="searchAsistensi" class="search-input" placeholder="Cari Siswa / Lokasi / Pembimbing..." onkeyup="filterAsistensi()">
            </div>
        </div>
        
        <div class="glass-panel-table">
            <div class="table-container-fixed">
                <?php if (!empty($grouped_data)): ?>
                <table id="tabelAsistensi">
                    <thead>
                        <tr>
                            <th class="col-no">NO</th> 
                            <th class="col-lokasi" style="text-align: left;">LOKASI TEMPAT PKL</th> 
                            <th class="col-pembimbing" style="text-align: left;">GURU PEMBIMBING</th> 
                            <th class="col-status" style="text-align: left;">STATUS ASISTENSI SISWA (MAX: <?php echo $max_asistensi; ?>)</th> 
                            <th class="col-aksi">AKSI</th>
                        </tr>
                    </thead>
                    <?php 
                    $global_row_num = 1;
                    
                    foreach ($grouped_data as $group) {
                        $rowspan_count = count($group['siswa_list']);
                        $first_row = true;
                        
                        echo '<tbody class="data-group">'; // Bungkus setiap grup lokasi agar mudah di-filter (Hide/Show)
                        
                        foreach ($group['siswa_list'] as $row) {
                            $asistensi_count = (int)$row['asistensi_count'];
                            
                            // LOGIKA STATUS BARU
                            if ($asistensi_count >= $max_asistensi) {
                                $status_text = 'LENGKAP';
                                $status_class = 'Lengkap'; // Hijau
                                $status_icon = 'fa-check-circle';
                            } elseif ($asistensi_count > 0) {
                                $status_text = 'PROSES';
                                $status_class = 'Proses'; // Kuning
                                $status_icon = 'fa-sync-alt'; 
                            } else {
                                $status_text = 'BELUM LENGKAP';
                                $status_class = 'Belum'; // Merah
                                $status_icon = 'fa-times-circle';
                            }

                            echo '<tr>';
                            
                            // Render Kolom Gabungan Berbasis Rowspan
                            if ($first_row) {
                                echo '<td rowspan="' . $rowspan_count . '" style="font-weight: 700; vertical-align: middle;">' . $global_row_num++ . '</td>';
                                echo '<td rowspan="' . $rowspan_count . '" style="text-align: left; font-weight: 700; color: var(--mantap-blue-dark); vertical-align: middle;">' . htmlspecialchars($group['lokasi_info']) . '</td>';
                                echo '<td rowspan="' . $rowspan_count . '" style="text-align: left; font-weight: 600; color: #475569; vertical-align: middle;"><i class="fas fa-user-tie fa-fw" style="color:#94a3b8; margin-right:4px;"></i> ' . htmlspecialchars($group['guru_info']) . '</td>';
                            }
                            
                            // Kolom Identitas & Progress Bar Siswa
                            echo '<td style="text-align: left; vertical-align: middle;">';
                            echo '<div class="siswa-item">';
                            
                            // Wrapper Flexbox untuk mensejajarkan Nama dan Status
                            echo '<div class="siswa-name-wrapper">';
                            echo '<span class="siswa-name">' . htmlspecialchars($row['nama_siswa']) . '</span>';
                            echo '<span class="status-badge ' . $status_class . '"><i class="fas ' . $status_icon . '"></i> ' . $status_text . ' (' . $asistensi_count . '/' . $max_asistensi . ')</span>';
                            echo '</div>';
                            
                            echo '<span class="siswa-info" style="display:block; font-size:12px; color:#64748b; margin-top:2px;"><i class="fas fa-graduation-cap fa-fw" style="color:#cbd5e1; margin-right:2px;"></i> Kelas: ' . htmlspecialchars($row['kelas']) . '</span>';
                            echo '</div>';
                            echo '</td>';

                            // Tombol Kelola Lembar Kendali Asistensi
                            echo '<td style="vertical-align: middle;">';
                            echo '<a href="asistensi-detail.php?siswa_id=' . $row['id'] . '" class="btn-aksi">';
                            echo '<i class="fas fa-tasks"></i> Kelola';
                            echo '</a>';
                            echo '</td>';
                            
                            echo '</tr>';
                            
                            $first_row = false;
                        }
                        
                        echo '</tbody>'; 
                    }
                    ?>
                </table>
                <?php else: ?>
                    <div style="text-align: center; color: #ef4444; font-style: italic; padding: 25px; border: 2px dashed #cbd5e1; background: #fff;">
                        <?php if ($current_user_level == 'admin'): ?>
                            <i class="fas fa-folder-open fa-lg"></i> Belum ada peserta didik terdaftar atau data tidak ditemukan.
                        <?php else: ?>
                            <i class="fas fa-user-slash fa-lg"></i> Anda belum ditetapkan sebagai Guru Pembimbing untuk lokasi manapun atau belum ada siswa di bimbingan Anda.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'panel/footer.php'; ?>

<script>
    // --- FITUR PENCARIAN DATABASE MULTI-BARIS ---
    function filterAsistensi() {
        let input = document.getElementById("searchAsistensi");
        let filter = input.value.toUpperCase();
        let groups = document.querySelectorAll(".data-group");

        groups.forEach(group => {
            // Ambil semua teks di dalam satu grup (termasuk lokasi, pembimbing, dan seluruh siswa di sana)
            let textContext = group.textContent || group.innerText;
            if (textContext.toUpperCase().indexOf(filter) > -1) {
                group.style.display = "";
            } else {
                group.style.display = "none";
            }
        });
    }
</script>

</body>
</html>