<?php
// admin/profil.php
// Halaman katalog profil semua perusahaan mitra PKL.

include 'auth-check.php'; 
include '../config/db-koneksi.php'; 

$current_user = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin';
$current_user_id = $_SESSION['user_id'] ?? 0; // Kebutuhan Log
$current_user_level = $_SESSION['level'] ?? 'admin'; 

// ---------------------------------------------------------------------
// LOGIKA SIMPAN / UPDATE PROFIL (DARI MODAL POP-UP)
// ---------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_profil'])) {
    $lokasi_id_post = (int)$_POST['lokasi_id'];
    $bidang_usaha = trim($_POST['bidang_usaha']);
    $website = trim($_POST['website']);
    $telp_hrd = trim($_POST['telp_hrd']);
    $deskripsi_detail = trim($_POST['deskripsi_detail']);
    
    // Ambil nama perusahaan untuk log
    $nama_perusahaan = "ID Lokasi " . $lokasi_id_post;
    $cek_nama_lokasi = $koneksi->query("SELECT nama_lokasi FROM lokasi_pkl WHERE lokasi_id = {$lokasi_id_post}");
    if ($cek_nama_lokasi && $cek_nama_lokasi->num_rows > 0) {
        $nama_perusahaan = $cek_nama_lokasi->fetch_assoc()['nama_lokasi'];
    }

    // Cek apakah profil sudah ada (Untuk menentukan Insert atau Update)
    $check_profil = $koneksi->query("SELECT * FROM profil_perusahaan WHERE lokasi_id = {$lokasi_id_post}");
    
    if ($check_profil->num_rows > 0) {
        // --- PROSES UPDATE ---
        $old_data = $check_profil->fetch_assoc();

        $perubahan = [];
        if ($old_data['bidang_usaha'] != $bidang_usaha) $perubahan[] = "Bidang Usaha";
        if ($old_data['website'] != $website) $perubahan[] = "Website";
        if ($old_data['telp_hrd'] != $telp_hrd) $perubahan[] = "Kontak HRD";
        if ($old_data['deskripsi_detail'] != $deskripsi_detail) $perubahan[] = "Deskripsi Detail";

        $pesan_log = "";
        if (count($perubahan) > 0) {
            $detail_ubah = implode(", ", $perubahan);
            $pesan_log = "Memperbarui data profil perusahaan: " . $nama_perusahaan . " (Detail yang diubah: " . $detail_ubah . ")";
        } else {
            $pesan_log = "Menyimpan ulang data profil perusahaan: " . $nama_perusahaan . " (Tanpa perubahan)";
        }

        $stmt = $koneksi->prepare("UPDATE profil_perusahaan SET bidang_usaha = ?, website = ?, telp_hrd = ?, deskripsi_detail = ? WHERE lokasi_id = ?");
        $stmt->bind_param("ssssi", $bidang_usaha, $website, $telp_hrd, $deskripsi_detail, $lokasi_id_post);
        
        if ($stmt->execute()) {
            catatLog($koneksi, $current_user_id, $pesan_log); // Catat Log Update
            header("Location: profil.php?status=success"); 
            exit();
        } else {
            header("Location: profil.php?status=error"); 
            exit();
        }

    } else {
        // --- PROSES INSERT (BARU) ---
        $stmt = $koneksi->prepare("INSERT INTO profil_perusahaan (lokasi_id, bidang_usaha, website, telp_hrd, deskripsi_detail) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $lokasi_id_post, $bidang_usaha, $website, $telp_hrd, $deskripsi_detail);

        if ($stmt->execute()) {
            catatLog($koneksi, $current_user_id, "Menambahkan data detail profil baru untuk perusahaan mitra: " . $nama_perusahaan); // Catat Log Insert
            header("Location: profil.php?status=success"); 
            exit();
        } else {
            header("Location: profil.php?status=error"); 
            exit();
        }
    }
    $stmt->close();
}

$message = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'success') $message = "<div class='alert success'>✅ Detail Profil Perusahaan berhasil disimpan.</div>";
    if ($_GET['status'] == 'error') $message = "<div class='alert error'>❌ Terjadi kesalahan saat menyimpan data profil.</div>";
}

// ---------------------------------------------------------------------
// AMBIL DATA KATALOG
// ---------------------------------------------------------------------
$query_profil = "
    SELECT 
        l.lokasi_id, l.nama_lokasi, l.alamat, l.kuota_max, l.jam_kerja,
        g.nama_guru,
        (SELECT COUNT(p.id) FROM peserta_didik p WHERE p.lokasi_id = l.lokasi_id) AS terisi, 
        p.bidang_usaha, p.website, p.telp_hrd, p.deskripsi_detail
    FROM lokasi_pkl l
    LEFT JOIN guru g ON l.guru_id = g.guru_id
    LEFT JOIN profil_perusahaan p ON l.lokasi_id = p.lokasi_id
    ORDER BY l.nama_lokasi ASC
";

$profil_data = $koneksi->query($query_profil);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin - Profil Perusahaan Mitra PKL | Si Mantap PKL</title>
    <link rel="stylesheet" href="../css/style.css"> 
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --mantap-blue-dark: #0f172a;
            --mantap-blue-main: #1e40af;
            --mantap-blue-light: #3b82f6;
            --mantap-blue-soft: #eff6ff;
            --mantap-warning: #f59e0b;
            --mantap-success: #10b981;
            --mantap-danger: #ef4444;
        }

        body { font-family: 'Poppins', sans-serif; background-color: #f8fafc; color: #334155; margin: 0; overflow-x: hidden !important; }
        .main-content-wrapper { max-width: 100% !important; width: 100% !important; box-sizing: border-box !important; box-shadow: none !important;}
        .admin-main-content { padding: 20px 25px 30px 25px !important; box-sizing: border-box !important; width: 100% !important; clear: both; }

        .page-header-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .page-header-controls h1 { font-weight: 700; color: #0f172a; font-size: 1.8rem; margin: 0; position: relative; }
        .page-header-controls h1::after { content: ''; position: absolute; left: 0; bottom: -5px; width: 50px; height: 4px; background: var(--mantap-blue-main); border-radius: 2px; }

        .header-actions-group { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .search-wrapper { position: relative; display: flex; align-items: center; }
        .search-wrapper i { position: absolute; left: 15px; color: #64748b; font-size: 14px; }
        .search-input { 
            padding: 9px 15px 9px 38px; border: 1px solid #cbd5e1; border-radius: 20px; 
            font-family: 'Poppins', sans-serif; font-size: 13px; width: 260px; transition: all 0.3s ease; 
            outline: none; background: #f8fafc;
        }
        .search-input:focus { border-color: var(--mantap-blue-main); width: 300px; background: white; box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.15); }

        .alert { padding: 12px 15px; margin-bottom: 20px; border-radius: 8px; border: 1px solid; font-size: 13.5px; font-weight: 500; text-align: left; }
        .alert.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }

        .glass-panel-table { background: white !important; padding: 25px !important; border-radius: 16px !important; border: 2px solid #e2e8f0 !important; box-sizing: border-box; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); width: 100%; }
        .table-container-fixed { width: 100%; overflow-x: auto; box-sizing: border-box; border: none !important; }

        .custom-table-core { width: 100%; table-layout: fixed; border-collapse: collapse; background: white; border-radius: 4px; overflow: hidden; border: 2px solid var(--mantap-blue-main); min-width: 1100px; }
        .custom-table-core th { background: var(--mantap-blue-main); color: white; padding: 14px 10px; font-size: 12px; text-transform: uppercase; font-weight: 700; text-align: center; border: 1px solid #cbd5e1; box-sizing: border-box; }
        .custom-table-core td { padding: 12px 10px; border: 1px solid #e2e8f0; font-size: 13px; vertical-align: top; background-color: white !important; box-sizing: border-box; line-height: 1.4; }
        .custom-table-core tbody tr:hover td { background-color: #f8fafc !important; }

        .custom-table-core th.col-no { width: 40px; }
        .custom-table-core th.col-mitra { width: 22%; text-align: left; padding-left: 15px; } 
        .custom-table-core th.col-info { width: 20%; text-align: left; padding-left: 15px; }
        .custom-table-core th.col-detail { width: 42%; text-align: left; padding-left: 15px; } 
        .custom-table-core th.col-aksi { width: 100px; }

        .badge-status { padding: 3px 8px; border-radius: 12px; font-size: 10.5px; font-weight: 700; color: white; background-color: var(--mantap-success); display: inline-block; white-space: nowrap; margin-top: 4px;}
        .badge-status.penuh { background-color: var(--mantap-danger); }

        .detail-grid { display: grid; grid-template-columns: 100px 1fr; gap: 4px; font-size: 12px; color: #475569; margin-bottom: 6px; }
        .detail-grid strong { color: var(--mantap-blue-dark); font-weight: 600; }
        .detail-link { color: var(--mantap-blue-light); text-decoration: none; font-weight: 500; }
        .detail-link:hover { text-decoration: underline; }

        .deskripsi-mini { background: #f1f5f9; padding: 8px; border-radius: 6px; font-size: 11.5px; color: #334155; font-style: italic; border: 1px solid #e2e8f0; margin-top: 6px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;}

        .btn-edit-inline { background-color: var(--mantap-warning); color: white !important; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 600; cursor: pointer; border: none; font-family: 'Poppins'; display: inline-flex; align-items: center; justify-content: center; gap: 5px; width: 100%; box-sizing: border-box;}
        .btn-edit-inline:hover { background-color: #d97706; }

        /* MODAL STYLE */
        .mantap-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.6); z-index: 9999; backdrop-filter: blur(4px); overflow-y: auto; }
        .mantap-modal-dialog { width: 90%; max-width: 650px; margin: 2rem auto; background: white; border-radius: 16px; padding: 25px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .mantap-modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px; margin-bottom: 20px; }
        .mantap-modal-header h5 { margin: 0; font-size: 1.3rem; font-weight: 700; color: var(--mantap-blue-dark); }
        .close-modal-btn { background: none; border: none; font-size: 1.8rem; cursor: pointer; color: #64748b; }

        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px; color: #334155; }
        .form-control { width: 100%; padding: 11px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Poppins'; font-size: 13px; box-sizing: border-box; background: #f8fafc; }
        .form-control:focus { outline: none; border-color: var(--mantap-blue-main); background: white; }
        
        .btn-submit-modal { width: 100%; background: var(--mantap-blue-main); color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; margin-top: 10px; font-family: 'Poppins'; font-size: 14px; display: flex; align-items: center; justify-content: center; gap: 8px;}
        .btn-submit-modal:hover { background: var(--mantap-blue-dark); }

        .sidebar-nav ul, .sidebar-nav li, .dropdown-menu, .dropdown-menu li { list-style: none !important; margin: 0; padding: 0; }

        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 
            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; }
            .header-actions-group { width: 100%; flex-direction: column; align-items: stretch; gap: 10px; }
            .search-wrapper { width: 100%; margin-top: 5px; }
            .search-input { width: 100%; box-sizing: border-box; }
            .search-input:focus { width: 100%; }
            .glass-panel-table { padding: 16px 10px !important; border-radius: 12px !important; border: 2px solid #cbd5e1 !important; }
            .table-container-fixed { display: block !important; width: 100% !important; overflow-x: auto !important; -webkit-overflow-scrolling: touch;}
            .custom-table-core { min-width: 900px !important; }
            .mantap-modal-dialog { margin: 1.5rem auto; width: 95%; padding: 20px; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; ?>

<div class="main-content-wrapper">
    <?php include 'panel/navbar.php'; ?>

    <div class="admin-main-content"> 
        <div class="page-header-controls">
            <h1><i class="fas fa-building" style="color: var(--mantap-blue-main); margin-right: 6px;"></i> Katalog Profil Perusahaan Mitra</h1>
            <div class="header-actions-group">
                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchProfil" class="search-input" placeholder="Cari Nama / Bidang Usaha..." onkeyup="filterTabelProfil()">
                </div>
            </div>
        </div>
        
        <?php echo $message; ?>
        
        <div class="glass-panel-table">
            <div class="table-container-fixed">
                <table class="custom-table-core" id="tabelProfil">
                    <thead>
                        <tr>
                            <th class="col-no">NO</th>
                            <th class="col-mitra">NAMA MITRA & ALAMAT</th>
                            <th class="col-info">INFO PEMBIMBING & KUOTA</th>
                            <th class="col-detail">DETAIL PROFIL PERUSAHAAN</th>
                            <th class="col-aksi">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($profil_data && $profil_data->num_rows > 0): ?>
                            <?php $no = 1; while ($row = $profil_data->fetch_assoc()): 
                                $kuota_terisi = $row['terisi'] ?? 0;
                                $sisa = $row['kuota_max'] - $kuota_terisi;
                                $status_class = ($sisa <= 0) ? 'penuh' : '';
                            ?>
                            <tr class="data-row-profil">
                                <td style="text-align: center; font-weight: bold; color: #64748b;"><?php echo $no++; ?></td>
                                
                                <td style="text-align: left; padding-left: 15px;">
                                    <strong style="color: var(--mantap-blue-main); font-size: 14px;"><?php echo htmlspecialchars($row['nama_lokasi']); ?></strong><br>
                                    <div style="font-size: 11.5px; color: #64748b; margin-top: 4px; line-height: 1.4;">
                                        <i class="fas fa-map-marker-alt opacity-50 me-1"></i> <?php echo nl2br(htmlspecialchars($row['alamat'])); ?>
                                    </div>
                                </td>
                                
                                <td style="text-align: left; padding-left: 15px;">
                                    <div style="font-size: 12px; color: #475569; margin-bottom: 4px;">
                                        <strong>Pembimbing:</strong><br>
                                        <?php echo htmlspecialchars($row['nama_guru'] ?? 'Belum Ditentukan'); ?>
                                    </div>
                                    <span class="badge-status <?php echo $status_class; ?>">
                                        <i class="fas fa-users"></i> Kuota: <?php echo "{$kuota_terisi} / {$row['kuota_max']} Siswa"; ?>
                                    </span>
                                </td>
                                
                                <td style="text-align: left; padding-left: 15px;">
                                    <div class="detail-grid">
                                        <strong>Bidang Usaha</strong> <span>: <?php echo htmlspecialchars($row['bidang_usaha'] ?? '-'); ?></span>
                                        <strong>Website</strong>      <span>: <?php echo $row['website'] ? "<a href='".htmlspecialchars($row['website'])."' target='_blank' class='detail-link'>".htmlspecialchars($row['website'])."</a>" : '-'; ?></span>
                                        <strong>Kontak HRD</strong>   <span>: <?php echo htmlspecialchars($row['telp_hrd'] ?? '-'); ?></span>
                                        <strong>Jam Kerja</strong>    <span>: <?php echo htmlspecialchars($row['jam_kerja'] ?? '-'); ?></span>
                                    </div>
                                    <?php if (!empty($row['deskripsi_detail'])): ?>
                                        <div class="deskripsi-mini" title="Klik Edit untuk membaca selengkapnya">
                                            <strong><i class="fas fa-info-circle"></i> Catatan/Kualifikasi:</strong><br>
                                            <?php echo htmlspecialchars($row['deskripsi_detail']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                
                                <td style="text-align: center; padding: 10px;">
                                    <button class="btn-edit-inline" 
                                        data-id="<?php echo $row['lokasi_id']; ?>"
                                        data-nama="<?php echo htmlspecialchars($row['nama_lokasi'], ENT_QUOTES); ?>"
                                        data-bidang="<?php echo htmlspecialchars($row['bidang_usaha'] ?? '', ENT_QUOTES); ?>"
                                        data-web="<?php echo htmlspecialchars($row['website'] ?? '', ENT_QUOTES); ?>"
                                        data-telp="<?php echo htmlspecialchars($row['telp_hrd'] ?? '', ENT_QUOTES); ?>"
                                        data-desc="<?php echo htmlspecialchars($row['deskripsi_detail'] ?? '', ENT_QUOTES); ?>"
                                        onclick="bukaModalProfil(this)">
                                        <i class="fas fa-edit"></i> Edit Profil
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align: center; color: #ef4444; font-style: italic; padding: 25px;">Belum ada rekam data lokasi mitra PKL yang terdaftar.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="mantap-modal" id="modalProfil">
    <div class="mantap-modal-dialog">
        <div class="mantap-modal-header">
            <h5 id="modalTitleText"><i class="fas fa-edit text-warning"></i> Edit Profil Perusahaan</h5>
            <button class="close-modal-btn" onclick="tutupModal()">&times;</button>
        </div>
        <form method="POST" action="profil.php">
            <input type="hidden" name="submit_profil" value="1">
            <input type="hidden" name="lokasi_id" id="lokasi_id" value="">
            
            <div style="margin-bottom: 20px; padding: 10px 15px; background: #eff6ff; border-radius: 8px; border-left: 4px solid var(--mantap-blue-main);">
                <span style="font-size: 12px; color: #64748b;">Nama Mitra Perusahaan:</span><br>
                <strong id="display_nama_mitra" style="font-size: 15px; color: var(--mantap-blue-dark);"></strong>
            </div>

            <div class="form-group">
                <label for="bidang_usaha">Bidang Usaha Core Bisnis (Contoh: IT Consultant, Jasa Konstruksi):</label>
                <input type="text" id="bidang_usaha" name="bidang_usaha" class="form-control" placeholder="Masukkan bidang usaha..." required>
            </div>
            
            <div class="form-group">
                <label for="website">Alamat URL Website Perusahaan (Opsional):</label>
                <input type="url" id="website" name="website" class="form-control" placeholder="Contoh: https://www.nama-industri.com">
            </div>
            
            <div class="form-group">
                <label for="telp_hrd">Nomor Telepon Kontak HRD / Supervisor Lapangan:</label>
                <input type="text" id="telp_hrd" name="telp_hrd" class="form-control" placeholder="Contoh: 08123456xxx atau (0342) xxxxxx">
            </div>

            <div class="form-group">
                <label for="deskripsi_detail">Uraian Deskripsi Perusahaan / Persyaratan & Kompetensi Khusus PKL:</label>
                <textarea id="deskripsi_detail" name="deskripsi_detail" class="form-control" rows="5" placeholder="Tuliskan profil singkat industri, aturan seragam, atau kualifikasi khusus..."></textarea>
            </div>

            <button type="submit" class="btn-submit-modal"><i class="fas fa-save"></i> Simpan Profil Perusahaan</button>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('modalProfil');

    // FUNGSI PENCARIAN REAL-TIME
    function filterTabelProfil() {
        let input = document.getElementById("searchProfil");
        let filter = input.value.toUpperCase();
        let table = document.getElementById("tabelProfil");
        let tr = table.getElementsByClassName("data-row-profil");

        for (let i = 0; i < tr.length; i++) {
            let textValue = tr[i].textContent || tr[i].innerText;
            if (textValue.toUpperCase().indexOf(filter) > -1) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }

    // BUKA MODAL POP-UP
    function bukaModalProfil(btnElement) {
        // Ambil data dari atribut HTML tombol
        const id = btnElement.getAttribute('data-id');
        const nama = btnElement.getAttribute('data-nama');
        const bidang = btnElement.getAttribute('data-bidang');
        const web = btnElement.getAttribute('data-web');
        const telp = btnElement.getAttribute('data-telp');
        const desc = btnElement.getAttribute('data-desc');

        // Isi ke dalam form
        document.getElementById('lokasi_id').value = id;
        document.getElementById('display_nama_mitra').innerText = nama;
        document.getElementById('bidang_usaha').value = bidang;
        document.getElementById('website').value = web;
        document.getElementById('telp_hrd').value = telp;
        document.getElementById('deskripsi_detail').value = desc;

        modal.style.display = 'block';
    }

    function tutupModal() {
        modal.style.display = 'none';
    }

    // Klik di luar kotak untuk menutup modal
    window.onclick = function(event) {
        if (event.target == modal) {
            tutupModal();
        }
    }
</script>

<?php include 'panel/footer.php'; ?>
</body>
</html>