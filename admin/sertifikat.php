<?php
// admin/sertifikat.php
include 'auth-check.php';
include '../config/db-koneksi.php';

$current_user_level = $_SESSION['level'] ?? 'user'; 
$current_user_id = $_SESSION['user_id'] ?? 0;

// Izinkan akses untuk Admin dan Pembimbing/Guru
if ($current_user_level !== 'admin' && $current_user_level !== 'pembimbing' && $current_user_level !== 'guru') {
    die("<div class='alert error'>Akses Ditolak. Anda tidak memiliki izin untuk halaman ini.</div>");
}

// ---------------------------------------------------------------------
// 0. SINKRONISASI LEVEL USER: Filter Siswa Berdasarkan Guru Pembimbing
// ---------------------------------------------------------------------
$guru_filter_id = 0;
$filter_clause = "";

if ($current_user_level == 'pembimbing' || $current_user_level == 'guru') {
    $stmt_get_guru_id = $koneksi->prepare("SELECT guru_id FROM guru WHERE user_id = ?");
    if ($stmt_get_guru_id) {
        $stmt_get_guru_id->bind_param("i", $current_user_id);
        $stmt_get_guru_id->execute();
        $result_guru_id = $stmt_get_guru_id->get_result();
        if ($result_guru_id->num_rows > 0) {
            $guru_filter_id = $result_guru_id->fetch_assoc()['guru_id'];
            $filter_clause = " WHERE l.guru_id = {$guru_filter_id} ";
        } else {
            $filter_clause = " WHERE 1 = 0 "; // Jika belum punya siswa bimbingan, tampilkan kosong
        }
        $stmt_get_guru_id->close();
    }
}

// ---------------------------------------------------------------------
// 1. HITUNG TOTAL KEGIATAN KENDALI YANG DIDEFINISIKAN
// ---------------------------------------------------------------------
$total_kegiatan_def = 0;
$kegiatan_def_detail = [];

$stmt_def = $koneksi->query("SELECT id, deskripsi FROM kendali_kegiatan_def WHERE is_active = TRUE ORDER BY urutan ASC");
if ($stmt_def) {
    $kegiatan_def_detail = $stmt_def->fetch_all(MYSQLI_ASSOC);
    $total_kegiatan_def = count($kegiatan_def_detail);
}

// ---------------------------------------------------------------------
// 2. QUERY DAFTAR SEMUA SISWA (Terfilter otomatis sesuai level user)
// ---------------------------------------------------------------------
$query_siswa_sertifikat = "
    SELECT 
        p.id, p.nisn, p.nama AS nama_siswa, p.kelas, 
        l.nama_lokasi, g.nama_guru,
        s.nomor_sertifikat,
        (
            SELECT COUNT(k.kegiatan_id) 
            FROM kartu_kendali k 
            WHERE k.siswa_id = p.id AND k.status = 1
        ) AS completed_count
    FROM peserta_didik p
    LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
    LEFT JOIN guru g ON l.guru_id = g.guru_id
    LEFT JOIN sertifikat_terbit s ON p.id = s.siswa_id
    {$filter_clause}
    ORDER BY s.nomor_sertifikat DESC, completed_count DESC, p.nama ASC
";

$siswa_data = $koneksi->query($query_siswa_sertifikat);

// Fungsi untuk format status kelayakan
function getKelayakanStatus($completed, $total_required) {
    if ($total_required == 0) return ['TIDAK ADA DEFINISI', 'status-danger'];
    
    if ($completed >= $total_required) {
        return ['LAYAK (Lulus Kendali)', 'status-success'];
    } elseif ($completed > 0) {
        return ['PROSES', 'status-warning'];
    } else {
        return ['BELUM MEMULAI', 'status-danger'];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Sertifikat & Penilaian PKL | Si Mantap PKL</title>
    <link rel="stylesheet" href="../css/style.css"> 
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <style>
        :root {
            --mantap-blue-dark: #0f172a;
            --mantap-blue-main: #1e40af;
            --mantap-blue-light: #3b82f6;
            --mantap-blue-soft: #eff6ff;
            --mantap-purple: #6f42c1;
            --mantap-purple-light: #8250df;
            --mantap-teal: #0d9488;
            --mantap-teal-light: #14b8a6;
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

        .alert.error { 
            background-color: #f8d7da; 
            color: #721c24; 
            padding: 15px; 
            border-radius: 8px; 
            margin-bottom: 15px; 
            border: 1px solid #f5c6cb;
            font-size: 14px;
        }
        
        .keterangan-syarat { 
            padding: 20px; 
            margin-bottom: 25px; 
            border-radius: 12px; 
            background-color: var(--mantap-blue-soft); 
            border: 2px solid #e2e8f0; 
            text-align: left; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        }
        .keterangan-syarat p { margin: 0 0 8px 0; font-size: 14px; color: #334155; }
        .keterangan-syarat strong { color: var(--mantap-blue-main); font-weight: 700; }
        .keterangan-syarat ul { list-style: disc; margin: 12px 0 0 20px; padding: 0; line-height: 1.6; }
        .keterangan-syarat ul li { font-size: 13.5px; color: #475569; }

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

        /* SEARCH BAR & BUTTONS HEADER STYLE (Ala Kartu Kendali) */
        .header-actions-group { display: flex; gap: 10px; align-items: center; }
        .search-wrapper { position: relative; display: flex; align-items: center; }
        .search-wrapper i { position: absolute; left: 15px; color: #64748b; font-size: 14px; }
        .search-input { 
            padding: 9px 15px 9px 38px; border: 1px solid #cbd5e1; border-radius: 20px; 
            font-family: 'Poppins', sans-serif; font-size: 13px; width: 280px; transition: all 0.3s ease; 
            outline: none; background: #f8fafc;
        }
        .search-input:focus { border-color: var(--mantap-blue-main); width: 320px; background: white; box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.15); }

        .btn-manage-penilaian {
            color: white !important;
            padding: 9px 18px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08);
            transition: all 0.2s ease;
            background-color: #ff8c00;
        }
        .btn-manage-penilaian:hover { background-color: #ffa534; transform: translateY(-1px); }

        .glass-panel-table {
            background: white !important;
            padding: 25px !important;
            border-radius: 16px !important;
            border: 2px solid #e2e8f0 !important; 
            box-sizing: border-box !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -2px rgba(0, 0, 0, 0.02);
            width: 100%;
        }

        .table-container-fixed {
            width: 100%;
            overflow-x: auto; 
            box-sizing: border-box !important;
            border: none !important;
        }

        .admin-main-content table.data-table {
            width: 100%;
            table-layout: fixed; 
            border-collapse: collapse;
            background: white;
            border-radius: 4px;
            overflow: hidden;
            border: 2px solid var(--mantap-blue-main); 
            min-width: 950px;
        }

        .admin-main-content table.data-table th { 
            background: var(--mantap-blue-main); 
            color: white; 
            padding: 14px 6px; 
            font-size: 13px; 
            text-transform: uppercase; 
            font-weight: 700;
            text-align: center;
            border: 1px solid #cbd5e1;
            box-sizing: border-box;
        }

        .admin-main-content table.data-table th:nth-child(1) { width: 50px; }
        .admin-main-content table.data-table th:nth-child(2) { width: 22%; text-align: left; padding-left: 12px; }
        .admin-main-content table.data-table th:nth-child(3) { width: 23%; text-align: left; padding-left: 12px; }
        .admin-main-content table.data-table th:nth-child(4) { width: 20%; }
        .admin-main-content table.data-table th:nth-child(5) { width: 22%; } 
        .admin-main-content table.data-table th:nth-child(6) { width: 140px; } 

        .admin-main-content table.data-table td {
            padding: 12px 10px;
            font-size: 13px;
            vertical-align: middle;
            word-wrap: break-word;
            overflow-wrap: break-word;
            text-align: center;
            border: 1px solid #cbd5e1;
            color: #0f172a;
            background-color: white !important; 
            box-sizing: border-box;
        }

        .admin-main-content table.data-table tr:hover td { 
            background-color: #f8fafc !important; 
        }

        .admin-main-content table.data-table td:nth-child(2),
        .admin-main-content table.data-table td:nth-child(3) {
            text-align: left;
        }

        .student-name-title { font-weight: 700; color: #0f172a; font-size: 13.5px; text-transform: uppercase; }
        .location-title { font-weight: 700; color: var(--mantap-blue-main); display: block; margin-bottom: 2px;}

        .status-badge { padding: 5px 10px; border-radius: 4px; font-size: 11px; font-weight: 700; color: white; display: inline-block; white-space: nowrap; text-transform: uppercase; text-align: center; margin-top: 4px; }
        .status-success { background-color: #28a745; }
        .status-warning { background-color: #ffc107; color: #0f172a; }
        .status-danger { background-color: #dc3545; }
        
        .dashboard-btn-group { display: flex; flex-direction: column; gap: 5px; align-items: center; justify-content: center; }
        
        .btn-action-small { 
            color: white !important; padding: 5px 0; border-radius: 4px; 
            text-decoration: none; font-size: 11px; font-weight: 600; border: none; cursor: pointer; 
            width: 120px; text-align: center; display: inline-flex; align-items: center; justify-content: center; gap: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08); transition: all 0.2s ease;
        }
        
        .btn-input-nilai { background-color: var(--mantap-blue-light); }
        .btn-input-nilai:hover:not(.disabled) { background-color: var(--mantap-blue-main); }
        
        .btn-sertif-depan { background-color: var(--mantap-purple); }
        .btn-sertif-depan:hover:not(.disabled) { background-color: var(--mantap-purple-light); }

        .btn-sertif-belakang { background-color: var(--mantap-teal); }
        .btn-sertif-belakang:hover:not(.disabled) { background-color: var(--mantap-teal-light); }

        .btn-action-small.disabled { 
            background-color: #e2e8f0 !important; 
            color: #94a3b8 !important; 
            box-shadow: none !important;
            cursor: not-allowed; 
            border: 1px solid #cbd5e1;
        }

        /* CSS DROPDOWN SIDEBAR */
        .dropdown-menu { display: none; list-style: none; padding: 0; margin-top: 0; background-color: #2c3e50; }
        .dropdown-menu.active { display: block; }
        .dropdown-menu li a { padding: 10px 20px 10px 50px; display: block; color: #bdc3c7; font-size: 14px; border-left: 3px solid transparent; transition: all 0.3s; text-decoration: none; }
        .dropdown-menu li a:hover { background-color: #34495e; border-left-color: #3498db; color: white; }
        .dropdown-toggle { cursor: pointer; display: flex; justify-content: space-between; align-items: center; padding: 12px 20px; color: #bdc3c7; font-weight: 600; text-decoration: none; }
        .dropdown-toggle.active i.fa-caret-down { transform: rotate(180deg); }
        .dropdown-toggle i.fa-caret-down { transition: transform 0.3s; }
        .dropdown-menu li a.active { background-color: #1a293a; border-left-color: #3498db; color: white; font-weight: 600; }
        .dropdown-parent a.dropdown-toggle.active { background-color: #34495e; border-left-color: #3498db; color: white; }
        .sidebar-nav .monitoring-menu a { background-color: var(--mantap-blue-main) !important; color: white !important; font-weight: 600; margin-top: 5px; }

        /* RESPONSIVITAS MOBILE & LAPTOP KECIL */
        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 
            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; padding: 5px 0px !important; }
            .page-header-controls h1 { font-size: 1.4rem !important; }
            
            .header-actions-group { width: 100%; flex-direction: column; align-items: stretch; gap: 10px; }
            .search-wrapper { width: 100%; }
            .search-input { width: 100%; box-sizing: border-box; }
            .search-input:focus { width: 100%; }
            .btn-manage-penilaian { justify-content: center; }

            .glass-panel-table { padding: 16px 10px !important; border-radius: 12px !important; border: 2px solid #cbd5e1 !important; }
            .table-container-fixed { display: block !important; width: 100% !important; overflow-x: auto !important; border: none !important; }

            .admin-main-content table.data-table { table-layout: auto !important; min-width: 1050px !important; }
            .admin-main-content table.data-table th, .admin-main-content table.data-table td { padding: 12px 10px !important; font-size: 13px !important; }
            .admin-main-content table.data-table th:nth-child(2), .admin-main-content table.data-table td:nth-child(2),
            .admin-main-content table.data-table th:nth-child(3), .admin-main-content table.data-table td:nth-child(3) { text-align: left !important; padding-left: 10px !important; }
            
            .dashboard-btn-group { flex-direction: row !important; gap: 6px !important; flex-wrap: wrap; justify-content: center; }
            .btn-action-small { width: 110px !important; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; ?>

<div class="main-content-wrapper">

    <?php include 'panel/navbar.php'; ?>

    <div class="admin-main-content"> 
        <div class="page-header-controls">
            <h1><i class="fas fa-award"></i> Penerbitan Sertifikat Kelulusan PKL</h1>
            
            <div class="header-actions-group">
                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchSertifikat" class="search-input" placeholder="Cari Nama Siswa atau Lokasi..." onkeyup="filterTabelSertifikat()">
                </div>
                
                <?php if ($current_user_level === 'admin'): ?>
                    <a href="penilaian-format.php" class="btn-manage-penilaian">
                        <i class="fas fa-list-ol"></i> Format Penilaian PKL
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="keterangan-syarat">
            <p>Syarat kelulusan sertifikat adalah menyelesaikan semua kegiatan yang terdaftar di Kartu Kendali.</p>
            <p>Total Kegiatan Kendali yang Didefinisikan: <strong><?php echo $total_kegiatan_def; ?></strong></p>
            
            <?php if ($total_kegiatan_def > 0): ?>
                <p style="margin-top: 15px; font-weight: 600; color: #34495e;">Daftar Kegiatan Kendali:</p>
                <ul>
                    <?php foreach ($kegiatan_def_detail as $item): ?>
                        <li><?php echo htmlspecialchars($item['deskripsi']); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        
        <div class="glass-panel-table">
            <div class="table-container-fixed">
                <?php if ($siswa_data && $siswa_data->num_rows > 0): ?>
                    <table class="data-table" id="tabelSertifikat">
                        <thead>
                            <tr>
                                <th>NO</th>
                                <th>Nama Siswa (Kelas)</th>
                                <th>Lokasi PKL / Pembimbing</th>
                                <th>Status Kelayakan Kendali</th>
                                <th>Nomor Sertifikat</th>
                                <th>Aksi Operasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; while ($row = $siswa_data->fetch_assoc()): 
                                $status = getKelayakanStatus($row['completed_count'], $total_kegiatan_def);
                                $is_eligible = $status[0] === 'LAYAK (Lulus Kendali)';
                                $is_sertif_terbit = !empty($row['nomor_sertifikat']);
                            ?>
                            <tr class="data-row">
                                <td style="font-weight: 700; color: #64748b;"><?php echo $no++; ?></td>
                                <td>
                                    <div class="student-name-title"><?php echo htmlspecialchars($row['nama_siswa']); ?></div>
                                    <small style="color: #64748b; font-weight: 500;"><?php echo htmlspecialchars($row['kelas']); ?></small>
                                </td>
                                <td>
                                    <span class="location-title"><?php echo htmlspecialchars($row['nama_lokasi'] ?? 'Belum Pilih Lokasi'); ?></span>
                                    <small style="color: #64748b;"><i class="fas fa-user-tie fa-fw opacity-50"></i> <?php echo htmlspecialchars($row['nama_guru'] ?? 'N/A'); ?></small>
                                </td>
                                <td>
                                    <span style="font-weight: 700; color: #0f172a; display: block; font-size: 14px;"><?php echo "{$row['completed_count']} / {$total_kegiatan_def}"; ?> <span style="font-size:11px; font-weight:500; color:#64748b;">Keg.</span></span>
                                    <span class="status-badge <?php echo $status[1]; ?>">
                                        <?php echo $status[0]; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($is_sertif_terbit): ?>
                                        <span style="font-weight: 700; color: #10b981; display: block; font-size: 13.5px;"><i class="fas fa-check-circle me-1"></i><?php echo htmlspecialchars($row['nomor_sertifikat']); ?></span>
                                    <?php elseif ($is_eligible): ?>
                                        <span style="color: #f59e0b; font-style: italic; font-size: 12.5px; font-weight: 600;"><i class="fas fa-clock me-1"></i>Menunggu Penilaian</span>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-style: italic; font-size: 12.5px;"><i class="fas fa-times-circle me-1"></i>Belum Lulus</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="dashboard-btn-group">
                                        
                                        <a <?php echo $is_eligible ? 'href="penilaian-akhir.php?siswa_id=' . $row['id'] . '"' : ''; ?> 
                                           class="btn-action-small btn-input-nilai <?php echo $is_eligible ? '' : 'disabled'; ?>">
                                            <i class="fas fa-calculator"></i> Input Nilai
                                        </a>
                                        
                                        <a <?php echo ($is_eligible && $is_sertif_terbit) ? 'href="sertifikat-cetak-depan.php?siswa_id=' . $row['id'] . '" target="_blank"' : ''; ?> 
                                           class="btn-action-small btn-sertif-depan <?php echo ($is_eligible && $is_sertif_terbit) ? '' : 'disabled'; ?>">
                                            <i class="fas fa-file-invoice"></i> Cetak Depan
                                        </a>

                                        <a <?php echo ($is_eligible && $is_sertif_terbit) ? 'href="sertifikat-cetak-belakang.php?siswa_id=' . $row['id'] . '" target="_blank"' : ''; ?> 
                                           class="btn-action-small btn-sertif-belakang <?php echo ($is_eligible && $is_sertif_terbit) ? '' : 'disabled'; ?>">
                                            <i class="fas fa-list-alt"></i> Cetak Belakang
                                        </a>
                                        
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
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
    function filterTabelSertifikat() {
        let input = document.getElementById("searchSertifikat");
        let filter = input.value.toUpperCase();
        let table = document.getElementById("tabelSertifikat");
        
        if (!table) return; // Jika tabel kosong/tidak ada
        
        let tr = table.getElementsByClassName("data-row");

        for (let i = 0; i < tr.length; i++) {
            // Index 1 = Nama Siswa, Index 2 = Lokasi PKL
            let tdNama = tr[i].getElementsByTagName("td")[1];
            let tdLokasi = tr[i].getElementsByTagName("td")[2];
            
            if (tdNama || tdLokasi) {
                let textValueNama = tdNama.textContent || tdNama.innerText;
                let textValueLokasi = tdLokasi.textContent || tdLokasi.innerText;
                
                // Cek kecocokan di nama ATAU lokasi
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