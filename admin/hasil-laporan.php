<?php
// admin/hasil-laporan.php
include 'auth-check.php';
include '../config/db-koneksi.php';

$current_user_level = strtolower($_SESSION['level'] ?? 'user'); 
$current_user_id = $_SESSION['user_id'] ?? 0;

// Keamanan: Hanya Admin dan Guru/Pembimbing yang bisa melihat
if ($current_user_level !== 'admin' && $current_user_level !== 'pembimbing' && $current_user_level !== 'guru') {
    die("<div class='alert error' style='margin:20px; font-family:sans-serif;'>Akses Ditolak. Anda tidak memiliki izin.</div>");
}

// =========================================================================================
// LOGIKA FILTER PEMBIMBING
// =========================================================================================
$guru_filter_id = 0;
$filter_pembimbing_clause = "";

// Jika yang login adalah pembimbing, cari guru_id miliknya dari tabel guru
if ($current_user_level === 'pembimbing' || $current_user_level === 'guru') {
    $stmt_get_guru = $koneksi->prepare("SELECT guru_id FROM guru WHERE user_id = ?");
    if ($stmt_get_guru) {
        $stmt_get_guru->bind_param("i", $current_user_id);
        $stmt_get_guru->execute();
        $res_guru = $stmt_get_guru->get_result();
        if ($res_guru->num_rows > 0) {
            $guru_filter_id = $res_guru->fetch_assoc()['guru_id'];
            $filter_pembimbing_clause = " WHERE l.guru_id = {$guru_filter_id} ";
        } else {
            // Jika akun pembimbing tidak ditemukan di tabel guru, paksa data kosong (pengamanan)
            $filter_pembimbing_clause = " WHERE l.guru_id = -1 "; 
        }
        $stmt_get_guru->close();
    }
}

// --- QUERY DATA SISWA & LAPORAN ---
$query_laporan = $koneksi->query("
    SELECT 
        p.id AS siswa_id, 
        p.nama, 
        p.kelas, 
        l.nama_lokasi,
        la.file_laporan, 
        la.file_ppt, 
        la.tgl_upload
    FROM peserta_didik p
    LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
    LEFT JOIN laporan_akhir la ON p.id = la.siswa_id
    {$filter_pembimbing_clause}
    ORDER BY la.tgl_upload DESC, p.nama ASC
");

$data_laporan = [];
$stat_sudah = 0;
$stat_belum = 0;

if ($query_laporan) {
    while ($row = $query_laporan->fetch_assoc()) {
        $data_laporan[] = $row;
        if (!empty($row['file_laporan'])) {
            $stat_sudah++;
        } else {
            $stat_belum++;
        }
    }
}
$stat_total = count($data_laporan);

// --- TRIGGER LOG AKTIVITAS (MENGAKSES HALAMAN) ---
catatLog($koneksi, $current_user_id, "Membuka halaman pemantauan dan rekap Laporan Akhir PKL Siswa");

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Rekap Laporan Akhir PKL | Si Mantap</title>
    <link rel="stylesheet" href="../css/style.css"> 
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root { 
            --mantap-blue-dark: #0f172a; 
            --mantap-blue-main: #1e40af; 
            --mantap-blue-light: #3b82f6; 
            --mantap-blue-soft: #eff6ff;
            --mantap-success: #10b981;
            --mantap-danger: #ef4444;
        }
        body { font-family: 'Poppins', sans-serif; background-color: #f8fafc; color: #334155; margin: 0; overflow-x: hidden !important; }
        .main-content-wrapper { max-width: 100% !important; width: 100% !important; box-sizing: border-box !important; }
        .admin-main-content { padding: 20px 25px 30px 25px !important; box-sizing: border-box !important; }

        .page-header-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .page-header-controls h1 { font-weight: 700; color: #0f172a; font-size: 1.8rem; margin: 0; position: relative; }
        .page-header-controls h1::after { content: ''; position: absolute; left: 0; bottom: -5px; width: 50px; height: 4px; background: var(--mantap-blue-main); border-radius: 2px; }

        /* KOTAK STATISTIK */
        .stats-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-card { padding: 18px 20px; border-radius: 12px; color: white; position: relative; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: flex; flex-direction: column; justify-content: center; }
        .stat-card i { position: absolute; right: -15px; top: -5px; font-size: 4rem; opacity: 0.15; }
        .stat-card h3 { font-size: 2rem; font-weight: 700; margin: 0; line-height: 1; }
        .stat-card p { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin: 5px 0 0 0; font-weight: 600; opacity: 0.9; }

        /* SEARCH BAR */
        .search-wrapper { position: relative; display: flex; align-items: center; width: 100%; max-width: 350px; }
        .search-wrapper i { position: absolute; left: 15px; color: #64748b; font-size: 14px; }
        .search-input { width: 100%; padding: 10px 15px 10px 40px; border: 1px solid #cbd5e1; border-radius: 25px; font-family: 'Poppins', sans-serif; font-size: 13px; background: white; transition: 0.3s; outline: none; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .search-input:focus { border-color: var(--mantap-blue-main); box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.15); }

        /* TABEL */
        .glass-panel-table { background: white; padding: 25px; border-radius: 16px; border: 2px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); }
        .table-container-fixed { width: 100%; overflow-x: auto; border: none !important; }
        .custom-table-core { width: 100%; table-layout: auto; border-collapse: collapse; border: 2px solid var(--mantap-blue-main); border-radius: 4px; min-width: 950px; overflow: hidden; }
        .custom-table-core th { background: var(--mantap-blue-main); color: white; padding: 14px 10px; font-size: 12px; font-weight: 700; text-align: center; border: 1px solid #cbd5e1; white-space: nowrap; }
        .custom-table-core td { padding: 12px 10px; border: 1px solid #e2e8f0; font-size: 13px; vertical-align: middle; }
        .custom-table-core tbody tr:hover td { background-color: #f8fafc !important; }

        .col-no { width: 50px; text-align: center; }
        .col-siswa { width: 25%; text-align: left; padding-left: 15px; }
        .col-lokasi { width: 20%; text-align: left; padding-left: 15px; }
        .col-status { width: 15%; text-align: center; }
        .col-waktu { width: 15%; text-align: center; }
        .col-aksi { width: 150px; text-align: center; }

        /* STATUS BADGE */
        .status-badge { display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-sudah { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .status-belum { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        /* TOMBOL AKSI */
        .btn-action-group { display: flex; gap: 6px; justify-content: center; }
        .btn-view-pdf { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; transition: 0.2s; }
        .btn-view-pdf:hover { background: #dc2626; color: white; border-color: #dc2626; }
        
        .btn-view-ppt { background: #fef3c7; color: #d97706; border: 1px solid #fcd34d; padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; transition: 0.2s; }
        .btn-view-ppt:hover { background: #d97706; color: white; border-color: #d97706; }

        .btn-disabled { background: #f1f5f9; color: #94a3b8; border: 1px dashed #cbd5e1; padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 600; cursor: not-allowed; display: inline-flex; align-items: center; gap: 4px; }

        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 
            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; gap: 15px !important; padding: 5px 0px !important; }
            .search-wrapper { max-width: 100%; }
            .stats-container { grid-template-columns: 1fr; }
            .glass-panel-table { padding: 16px 10px !important; border-radius: 12px !important; }
            .custom-table-core { min-width: 800px !important; }
            .custom-table-core th, .custom-table-core td { padding: 12px 10px !important; font-size: 12.5px !important; }
            .btn-action-group { flex-direction: column; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; include 'panel/navbar.php'; ?>

<div class="main-content-wrapper">
    <div class="admin-main-content"> 
        
        <div class="page-header-controls">
            <h1>Rekap Laporan Akhir PKL</h1>
            <div class="search-wrapper">
                <i class="fas fa-search"></i>
                <input type="text" id="searchSiswa" class="search-input" placeholder="Cari nama siswa atau kelas..." onkeyup="filterTabel()">
            </div>
        </div>

        <div class="stats-container">
            <div class="stat-card" style="background: linear-gradient(135deg, #1e40af 0%, #1d4ed8 100%);">
                <i class="fas fa-users"></i>
                <h3><?= $stat_total; ?></h3>
                <p>Total Siswa <?php echo ($current_user_level !== 'admin') ? 'Bimbingan' : 'Terdaftar'; ?></p>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <i class="fas fa-check-circle"></i>
                <h3><?= $stat_sudah; ?></h3>
                <p>Selesai Mengumpulkan</p>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                <i class="fas fa-exclamation-circle"></i>
                <h3><?= $stat_belum; ?></h3>
                <p>Belum Mengumpulkan</p>
            </div>
        </div>
        
        <div class="glass-panel-table">
            <div class="table-container-fixed">
                <table class="custom-table-core" id="tabelLaporan">
                    <thead>
                        <tr>
                            <th class="col-no">NO</th>
                            <th class="col-siswa">NAMA SISWA / KELAS</th>
                            <th class="col-lokasi">LOKASI PKL</th>
                            <th class="col-status">STATUS</th>
                            <th class="col-waktu">WAKTU UPLOAD</th>
                            <th class="col-aksi">BERKAS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($stat_total > 0): ?>
                            <?php $no = 1; foreach ($data_laporan as $row): 
                                $is_submitted = !empty($row['file_laporan']);
                            ?>
                            <tr class="data-row">
                                <td class="col-no" style="font-weight: 700; color: #64748b;"><?= $no++; ?></td>
                                
                                <td class="col-siswa">
                                    <strong style="color: var(--mantap-blue-dark); font-size: 13.5px; display:block; text-transform: uppercase;">
                                        <?= htmlspecialchars($row['nama']); ?>
                                    </strong>
                                    <span style="font-size: 11px; font-weight: 600; color: #64748b; background: #f1f5f9; padding: 2px 8px; border-radius: 4px; display:inline-block; margin-top:4px;">
                                        Kelas: <?= htmlspecialchars($row['kelas']); ?>
                                    </span>
                                </td>
                                
                                <td class="col-lokasi">
                                    <div style="font-size: 12.5px; font-weight: 600; color: var(--mantap-blue-main);">
                                        <i class="fas fa-building me-1 opacity-50"></i> <?= htmlspecialchars($row['nama_lokasi'] ?? 'Belum Ditentukan'); ?>
                                    </div>
                                </td>
                                
                                <td class="col-status">
                                    <?php if($is_submitted): ?>
                                        <span class="status-badge status-sudah"><i class="fas fa-check"></i> Sudah Kumpul</span>
                                    <?php else: ?>
                                        <span class="status-badge status-belum"><i class="fas fa-times"></i> Belum</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="col-waktu">
                                    <?php if($is_submitted): ?>
                                        <strong style="font-size: 12px; color: #334155;"><?= date('d M Y', strtotime($row['tgl_upload'])); ?></strong><br>
                                        <small style="font-weight: 600; color: #94a3b8;"><i class="far fa-clock"></i> <?= date('H:i', strtotime($row['tgl_upload'])); ?> WIB</small>
                                    <?php else: ?>
                                        <span style="color: #cbd5e1; font-style: italic;">-</span>
                                    <?php endif; ?>
                                </td>

                                <td class="col-aksi">
                                    <div class="btn-action-group">
                                        <?php if($is_submitted): ?>
                                            <a href="../uploads/laporan_akhir/<?= $row['file_laporan']; ?>" target="_blank" class="btn-view-pdf" title="Lihat PDF Laporan">
                                                <i class="fas fa-file-pdf"></i> PDF
                                            </a>
                                            <a href="../uploads/laporan_akhir/<?= $row['file_ppt']; ?>" target="_blank" class="btn-view-ppt" title="Unduh Slide Presentasi">
                                                <i class="fas fa-file-powerpoint"></i> PPT
                                            </a>
                                        <?php else: ?>
                                            <span class="btn-disabled" title="Berkas Belum Tersedia"><i class="fas fa-ban"></i> PDF</span>
                                            <span class="btn-disabled" title="Berkas Belum Tersedia"><i class="fas fa-ban"></i> PPT</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align: center; color: #ef4444; font-style: italic; padding: 25px; font-weight: 500;"><i class="fas fa-folder-open mb-2 fa-lg" style="display:block;"></i> Tidak ada data murid bimbingan yang ditemukan.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    function filterTabel() {
        let input = document.getElementById("searchSiswa");
        let filter = input.value.toUpperCase();
        let table = document.getElementById("tabelLaporan");
        let tr = table.getElementsByClassName("data-row");

        for (let i = 0; i < tr.length; i++) {
            let tdNama = tr[i].getElementsByTagName("td")[1]; // Kolom index 1 (Nama/Kelas)
            if (tdNama) {
                let textValue = tdNama.textContent || tdNama.innerText;
                if (textValue.toUpperCase().indexOf(filter) > -1) {
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