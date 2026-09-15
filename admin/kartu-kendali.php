<?php
// admin/kartu-kendali.php
// Halaman INDEX: Menampilkan daftar siswa yang dibimbing oleh Guru yang sedang login.

include 'auth-check.php';
include '../config/db-koneksi.php';

$current_user = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin';
$current_user_id = $_SESSION['user_id'] ?? 0; // ID user yang sedang login
$current_user_level = isset($_SESSION['level']) ? strtolower($_SESSION['level']) : 'admin'; 

$message = '';
$guru_filter_id = 0;
$filter_clause = "";

// ---------------------------------------------------------------------
// 1. TENTUKAN FILTER BERDASARKAN LEVEL USER
// ---------------------------------------------------------------------
if ($current_user_level == 'pembimbing' || $current_user_level == 'guru') {
    // A. Jika user adalah GURU/PEMBIMBING, cari guru_id mereka.
    $stmt_get_guru_id = $koneksi->prepare("SELECT guru_id FROM guru WHERE user_id = ?");
    if ($stmt_get_guru_id) {
        $stmt_get_guru_id->bind_param("i", $current_user_id);
        $stmt_get_guru_id->execute();
        $result_guru_id = $stmt_get_guru_id->get_result();
        if ($result_guru_id->num_rows > 0) {
            $guru_filter_id = $result_guru_id->fetch_assoc()['guru_id'];
            // Filter hanya siswa di lokasi yang dibimbing guru ini
            $filter_clause = "WHERE l.guru_id = {$guru_filter_id}";
        } else {
            $filter_clause = "WHERE 1 = 0"; 
        }
        $stmt_get_guru_id->close();
    }
} 
// Jika user adalah ADMIN, $filter_clause tetap kosong sehingga menampilkan SEMUA siswa.

// ---------------------------------------------------------------------
// 2. QUERY DATA SISWA
// ---------------------------------------------------------------------
$siswa_query_sql = "
    SELECT 
        p.id, p.nisn, p.nama AS nama_siswa, p.kelas,
        l.nama_lokasi,
        g.nama_guru
    FROM peserta_didik p
    LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
    LEFT JOIN guru g ON l.guru_id = g.guru_id
    {$filter_clause}
    ORDER BY l.nama_lokasi ASC, p.nama ASC
";

$siswa_query = $koneksi->query($siswa_query_sql);
$siswa_data = [];
if ($siswa_query) {
    while ($row = $siswa_query->fetch_assoc()) {
        $siswa_data[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin - Kartu Kendali Progres PKL | Si Mantap PKL</title>
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

        /* STRUKTUR UTAMA KONSISTEN */
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

        .btn-edit-def {
            background-color: #f59e0b;
            color: white !important;
            padding: 9px 18px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            font-size: 12px;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 6px -1px rgba(245, 158, 11, 0.15);
        }
        .btn-edit-def:hover { background-color: #d97706; transform: translateY(-1px); }

        /* TABEL LAYOUT KONSISTEN */
        .glass-panel-content {
            background: white !important; 
            padding: 25px !important; 
            border-radius: 16px !important; 
            border: 2px solid #e2e8f0 !important; 
            box-sizing: border-box; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); 
            width: 100%;
        }

        .table-container-fixed { width: 100%; overflow-x: auto; box-sizing: border-box; border: none !important; }

        .data-table {
            width: 100%; table-layout: fixed; border-collapse: collapse; background: white; border-radius: 4px; overflow: hidden; border: 2px solid #1e40af; 
        }

        .data-table th { 
            background: #1e40af; color: white; padding: 14px 6px; font-size: 13px; text-transform: uppercase; font-weight: 700; text-align: center; border: 1px solid #cbd5e1; box-sizing: border-box;
        }

        .data-table th:nth-child(1) { width: 60px; } 
        .data-table th:nth-child(2) { width: 35%; text-align: left; padding-left: 15px; } 
        .data-table th:nth-child(3) { width: 35%; text-align: left; padding-left: 15px; } 
        .data-table th:nth-child(4) { width: 130px; }

        .data-table td {
            padding: 12px 10px; font-size: 13px; vertical-align: middle; word-wrap: break-word; overflow-wrap: break-word; text-align: center; border: 1px solid #cbd5e1; color: #0f172a; background-color: white !important; box-sizing: border-box;
        }
        .data-table tr:hover td { background-color: #f8fafc !important; }

        .data-table td:nth-child(2), .data-table td:nth-child(3) { text-align: left; padding-left: 15px; }

        .siswa-name-title { font-weight: 700; color: #0f172a; font-size: 13.5px; text-transform: uppercase; }
        .location-title { font-weight: 700; color: var(--mantap-blue-main); }

        .btn-kelola-kendali {
            background-color: var(--mantap-blue-main);
            color: white !important;
            padding: 6px 14px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: 600;
            font-size: 11.5px;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            box-shadow: 0 2px 4px rgba(30, 64, 175, 0.15);
            transition: 0.2s;
        }
        .btn-kelola-kendali:hover { background-color: var(--mantap-blue-light); transform: translateY(-1px); }
        
        .sidebar-nav ul, .sidebar-nav li, .dropdown-menu, .dropdown-menu li { list-style: none !important; margin: 0; padding: 0; }

        /* =========================================================================
           RESPONSIVE VIEWPORT SMARTPHONE (HP)
        ======================================================================== */
        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 

            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; padding: 5px 0px !important; }
            .page-header-controls h1 { font-size: 1.4rem !important; }
            
            .header-actions-group { width: 100%; flex-direction: column; align-items: stretch; gap: 10px; }
            .btn-edit-def { width: 100% !important; justify-content: center !important; padding: 10px !important; border-radius: 8px !important; font-size: 13px !important; }
            .search-wrapper { width: 100%; }
            .search-input { width: 100%; box-sizing: border-box; }
            .search-input:focus { width: 100%; }

            .glass-panel-content { padding: 16px 10px !important; border-radius: 12px !important; border: 2px solid #cbd5e1 !important; }

            .table-container-fixed { display: block !important; width: 100% !important; overflow-x: auto !important; }

            /* Cegah tabel hancur akibat colspan kaku di HP dengan memaksa scroll horizontal */
            .data-table { table-layout: auto !important; min-width: 800px !important; }
            .data-table th, .data-table td { padding: 12px 10px !important; font-size: 13px !important; }
            
            .data-table th:nth-child(1), .data-table th:nth-child(2), .data-table th:nth-child(3), .data-table th:nth-child(4) { width: auto !important; }
            
            .btn-kelola-kendali { padding: 7px 12px !important; font-size: 11px !important; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; ?>

<div class="main-content-wrapper">

    <?php include 'panel/navbar.php'; ?>

    <div class="admin-main-content"> 
        
        <div class="page-header-controls">
            <h1>Kartu Kendali Progres PKL</h1>
            
            <div class="header-actions-group">
                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchKendali" class="search-input" placeholder="Cari Siswa / Lokasi / Pembimbing..." onkeyup="filterTabelKendali()">
                </div>
                <?php if ($current_user_level == 'admin'): ?>
                    <a href="kendali-kegiatan-edit.php" class="btn-edit-def">
                        <i class="fas fa-edit"></i> Kelola Standar Kegiatan
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php echo $message; ?>

        <?php if (($current_user_level == 'pembimbing' || $current_user_level == 'guru') && $guru_filter_id == 0): ?>
            <div class="alert error"><i class="fas fa-exclamation-circle me-2"></i> Akun Anda belum terdaftar di basis data pendidik atau belum dikaitkan dengan lokasi bimbingan aktif.</div>
        <?php endif; ?>
        
        <div class="glass-panel-content">
            <div class="table-container-fixed">
                <table class="data-table" id="tabelKendali">
                    <thead>
                        <tr>
                            <th>NO</th>
                            <th>NAMA SISWA (KELAS)</th>
                            <th>LOKASI PKL & PEMBIMBING</th>
                            <th>AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($siswa_data)): ?>
                            <?php $no = 1; foreach ($siswa_data as $siswa): ?>
                                <tr class="data-row">
                                    <td style="font-weight: 700; color: #64748b;"><?php echo $no++; ?></td>
                                    <td>
                                        <div class="siswa-name-title"><?php echo htmlspecialchars($siswa['nama_siswa']); ?></div>
                                        <small style="color: #64748b; font-weight: 500;"><i class="fas fa-graduation-cap fa-fw opacity-50"></i> Kelas: <?php echo htmlspecialchars($siswa['kelas']); ?></small>
                                    </td>
                                    <td>
                                        <div class="location-title"><?php echo htmlspecialchars($siswa['nama_lokasi'] ?? 'Belum Pilih Lokasi'); ?></div>
                                        <small style="color: #475569; font-weight: 500;"><i class="fas fa-user-tie fa-fw opacity-50"></i> <?php echo htmlspecialchars($siswa['nama_guru'] ?? 'N/A'); ?></small>
                                    </td>
                                    <td>
                                        <a href="kendali-detail.php?siswa_id=<?php echo $siswa['id']; ?>" class="btn-kelola-kendali">
                                            <i class="fas fa-clipboard-check"></i> Kelola Kendali
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #ef4444; font-style: italic; padding: 35px 20px; font-weight: 500;">
                                    <?php if ($current_user_level == 'admin'): ?>
                                        <i class="fas fa-folder-open fa-lg mb-2" style="display: block;"></i> Belum ada peserta didik terdaftar bimbingan.
                                    <?php else: ?>
                                        <i class="fas fa-user-slash fa-lg mb-2" style="display: block;"></i> Anda belum ditetapkan sebagai Guru Pembimbing untuk lokasi manapun atau belum ada siswa di bimbingan Anda.
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php include 'panel/footer.php'; ?>

<script>
    // --- FITUR PENCARIAN REAL-TIME TABEL KENDALI ---
    function filterTabelKendali() {
        let input = document.getElementById("searchKendali");
        let filter = input.value.toUpperCase();
        let table = document.getElementById("tabelKendali");
        let tr = table.getElementsByClassName("data-row");

        for (let i = 0; i < tr.length; i++) {
            // Gabungkan teks dari kolom Nama Siswa (index 1) dan Kolom Lokasi (index 2)
            let tdNama = tr[i].getElementsByTagName("td")[1];
            let tdLokasi = tr[i].getElementsByTagName("td")[2];
            
            if (tdNama || tdLokasi) {
                let textValueNama = tdNama.textContent || tdNama.innerText;
                let textValueLokasi = tdLokasi.textContent || tdLokasi.innerText;
                
                // Cari apakah ada kecocokan di salah satu kolom
                if (textValueNama.toUpperCase().indexOf(filter) > -1 || textValueLokasi.toUpperCase().indexOf(filter) > -1) {
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