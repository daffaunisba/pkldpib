<?php
// admin/penilaian-akhir.php
// Halaman untuk input nilai PKL siswa dan penerbitan nomor sertifikat.

include 'auth-check.php';
include '../config/db-koneksi.php';

$current_user_level = $_SESSION['level'] ?? 'user'; 
$message = '';
$siswa_id = isset($_GET['siswa_id']) ? (int)$_GET['siswa_id'] : 0;
$siswa_info = null;

if ($current_user_level !== 'admin' && $current_user_level !== 'pembimbing') {
    die("<div class='alert error'>Akses Ditolak. Hanya Administrator/Pembimbing yang diizinkan.</div>");
}

if ($siswa_id === 0) {
     header("Location: sertifikat.php"); 
     exit();
}

// ---------------------------------------------------------------------
// 1. QUERY JUMLAH KEGIATAN KENDALI DEF (Untuk Cek Kelulusan)
// ---------------------------------------------------------------------
$total_kegiatan_def = 0;
$stmt_def = $koneksi->query("SELECT id FROM kendali_kegiatan_def WHERE is_active = TRUE");
if ($stmt_def) {
    $total_kegiatan_def = $stmt_def->num_rows;
}

// ---------------------------------------------------------------------
// 2. QUERY DATA SISWA LENGKAP & NOMOR SERTIFIKAT (Ditambah logo_dudi)
// ---------------------------------------------------------------------
$stmt_info = $koneksi->prepare("
    SELECT 
        p.id, p.nisn, p.nama, p.kelas, 
        l.nama_lokasi, 
        s.nomor_sertifikat, s.tanggal_terbit, s.nama_ttd_dudi, s.jabatan_ttd_dudi, s.nip_ttd_dudi, s.logo_dudi,
        (SELECT COUNT(k.kegiatan_id) FROM kartu_kendali k WHERE k.siswa_id = p.id AND k.status = 1) AS completed_count
    FROM peserta_didik p
    LEFT JOIN sertifikat_terbit s ON p.id = s.siswa_id
    LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
    WHERE p.id = ?
");
$stmt_info->bind_param("i", $siswa_id);
$stmt_info->execute();
$siswa_info = $stmt_info->get_result()->fetch_assoc();
$stmt_info->close();

if (!$siswa_info) {
    die("<div class='alert error'>Data siswa tidak ditemukan.</div>");
}

$is_sertifikat_terbit = !empty($siswa_info['nomor_sertifikat']);

// Status Kelayakan: Harus menyelesaikan SEMUA kartu kendali
$is_eligible = ($total_kegiatan_def > 0 && $siswa_info['completed_count'] >= $total_kegiatan_def);

// ---------------------------------------------------------------------
// 3. FUNGSI GENERATOR NOMOR SERTIFIKAT BAKU (REGEX ANTI-TABRAKAN)
// ---------------------------------------------------------------------
function getNextNomorSertifikat($koneksi) {
    $current_year = date('Y');
    $map_bulan = ['01'=>'I','02'=>'II','03'=>'III','04'=>'IV','05'=>'V','06'=>'VI','07'=>'VII','08'=>'VIII','09'=>'IX','10'=>'X','11'=>'XI','12'=>'XII'];
    $current_month_roman = $map_bulan[date('m')];
    
    // Tarik semua nomor yang diawali 421.2/ dari pangkalan data
    $q_max = $koneksi->query("SELECT nomor_sertifikat FROM sertifikat_terbit WHERE nomor_sertifikat LIKE '421.2/%'");
    $max_urut = 0;
    
    if ($q_max) {
        while($r = $q_max->fetch_assoc()) {
            $ns = $r['nomor_sertifikat'];
            // EKSTRAK ANGKA: Cari pola 421.2/ diikuti angka (/001/, /002/, dst)
            if (preg_match('/^421\.2\/(\d+)\//', $ns, $matches)) {
                $urut = (int)$matches[1];
                if ($urut > $max_urut) {
                    $max_urut = $urut;
                }
            }
        }
    }
    
    // Tambah 1 dari urutan tertinggi, format jadi 3 digit angka (Contoh: 001, 002)
    $next_urut = str_pad($max_urut + 1, 3, "0", STR_PAD_LEFT);
    return "421.2/{$next_urut}/{$current_month_roman}/SMEKISA.PKL-DPIB/{$current_year}";
}

// Persiapan untuk Tampilan UI
$default_nomor = $siswa_info['nomor_sertifikat'] ?? '';
$is_format_baku = (strpos($default_nomor, '421.2/') === 0 && strpos($default_nomor, 'SMEKISA.PKL-DPIB') !== false);

// Jika siswa LAYAK, tapi nomor kosong ATAU format lamanya salah (Manual), timpa dengan yang asli!
if ($is_eligible) {
    if (empty($default_nomor) || !$is_format_baku) {
        $default_nomor = getNextNomorSertifikat($koneksi);
    }
} else {
    $default_nomor = ""; // Belum lulus = Kosongkan
}


// ---------------------------------------------------------------------
// 4. QUERY KOMPONEN PENILAIAN HIERARKI DENGAN NILAI SAAT INI
// ---------------------------------------------------------------------
$komponen_nilai_query = "
    SELECT 
        pa.nama_aspek, pa.urutan AS aspek_urutan,
        pk.komponen_id, pk.nama_komponen, pk.bobot,
        ns.nilai_angka, ns.predikat
    FROM penilaian_komponen pk
    JOIN penilaian_aspek pa ON pk.aspek_id = pa.aspek_id
    LEFT JOIN nilai_siswa_pkl ns ON pk.komponen_id = ns.komponen_id AND ns.siswa_id = ?
    ORDER BY pa.urutan ASC, pk.urutan ASC
";
$stmt_komponen = $koneksi->prepare($komponen_nilai_query);
$stmt_komponen->bind_param("i", $siswa_id);
$stmt_komponen->execute();
$komponen_data = $stmt_komponen->get_result();
$stmt_komponen->close();

$grouped_components = [];
$total_nilai_terbobot = 0;
$total_bobot = 0;

while ($row = $komponen_data->fetch_assoc()) {
    $grouped_components[$row['nama_aspek']][] = $row;
    $total_bobot += $row['bobot'];

    // Hitung total nilai tertimbang (jika nilai sudah ada)
    if (!empty($row['nilai_angka'])) {
        $total_nilai_terbobot += ($row['nilai_angka'] * ($row['bobot'] / 100));
    }
}
$nilai_rata_rata = $total_bobot > 0 ? round($total_nilai_terbobot / ($total_bobot / 100), 2) : 0;
// ---------------------------------------------------------------------


// ---------------------------------------------------------------------
// 5. AMBIL KONFIGURASI TANDA TANGAN DU/DI (Untuk Form Input)
// ---------------------------------------------------------------------
$default_jabatan_ttd_dudi = $siswa_info['nama_lokasi'] ? "Pimpinan " . htmlspecialchars($siswa_info['nama_lokasi']) : 'Pimpinan DU/DI';

// Data yang masuk ke form input
$input_jabatan = $siswa_info['jabatan_ttd_dudi'] ?? $default_jabatan_ttd_dudi;
$input_nama = $siswa_info['nama_ttd_dudi'] ?? 'Nama Lengkap Pimpinan';
$input_nip = $siswa_info['nip_ttd_dudi'] ?? '';


// ---------------------------------------------------------------------
// 6. LOGIKA POST: SIMPAN NILAI, NOMOR SERTIFIKAT, DAN UPLOAD LOGO
// ---------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_nilai'])) {
    $koneksi->begin_transaction();
    $post_success = true;

    // A. Simpan Nilai Komponen
    foreach ($_POST['komponen_id'] as $k_id => $nilai_data) {
        $nilai_angka = (float)$nilai_data['nilai_angka'];
        $predikat = trim($nilai_data['predikat']);

        if ($nilai_angka > 0) {
            $stmt_nilai = $koneksi->prepare("
                INSERT INTO nilai_siswa_pkl (siswa_id, komponen_id, nilai_angka, predikat)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE nilai_angka = VALUES(nilai_angka), predikat = VALUES(predikat)
            ");
            $stmt_nilai->bind_param("iiss", $siswa_id, $k_id, $nilai_angka, $predikat);
            if (!$stmt_nilai->execute()) {
                $post_success = false;
                break;
            }
            $stmt_nilai->close();
        }
    }
    
    // B. Upload Logo DU/DI (Opsional)
    $logo_path = $siswa_info['logo_dudi'] ?? null; // Pertahankan logo lama secara default
    
    if ($post_success && $is_eligible && isset($_FILES['logo_dudi']) && $_FILES['logo_dudi']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/logos/';
        
        // Buat folder jika belum ada
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = strtolower(pathinfo($_FILES["logo_dudi"]["name"], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png'];

        if (in_array($file_extension, $allowed_ext)) {
            // Beri nama unik agar tidak tertimpa
            $new_filename = 'logo_dudi_' . $siswa_id . '_' . time() . '.' . $file_extension;
            $target_file = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES["logo_dudi"]["tmp_name"], $target_file)) {
                // Hapus logo lama jika ada untuk menghemat ruang
                if (!empty($logo_path) && file_exists($logo_path)) {
                    unlink($logo_path);
                }
                $logo_path = $target_file;
            }
        } else {
            $post_success = false;
            $message = "<div class='alert error'><i class='fas fa-exclamation-triangle me-2'></i> Gagal: Format file logo harus JPG, JPEG, atau PNG.</div>";
        }
    }
    
    // C. Simpan Nomor Sertifikat dan TTD DU/DI (Hanya dieksekusi jika siswa LAYAK)
    if ($post_success && $is_eligible) {
        $nomor_final = $siswa_info['nomor_sertifikat'] ?? '';
        $is_format_baku_post = (strpos($nomor_final, '421.2/') === 0 && strpos($nomor_final, 'SMEKISA.PKL-DPIB') !== false);
        
        // Pengecekan ulang di server sesaat sebelum input ke database
        if (empty($nomor_final) || !$is_format_baku_post) {
            $nomor_final = getNextNomorSertifikat($koneksi); 
        }

        $tanggal_terbit = trim($_POST['tanggal_terbit'] ?? date('Y-m-d'));
        $nama_ttd_dudi_post = trim($_POST['nama_ttd_dudi']);
        $jabatan_ttd_dudi_post = trim($_POST['jabatan_ttd_dudi']);
        $nip_ttd_dudi_post = trim($_POST['nip_ttd_dudi']);

        $stmt_sertif = $koneksi->prepare("
            INSERT INTO sertifikat_terbit (siswa_id, nomor_sertifikat, tanggal_terbit, nilai_rata_rata, nama_ttd_dudi, jabatan_ttd_dudi, nip_ttd_dudi, logo_dudi)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                nomor_sertifikat = VALUES(nomor_sertifikat), 
                tanggal_terbit = VALUES(tanggal_terbit), 
                nilai_rata_rata = VALUES(nilai_rata_rata),
                nama_ttd_dudi = VALUES(nama_ttd_dudi),
                jabatan_ttd_dudi = VALUES(jabatan_ttd_dudi),
                nip_ttd_dudi = VALUES(nip_ttd_dudi),
                logo_dudi = IF(VALUES(logo_dudi) IS NOT NULL, VALUES(logo_dudi), logo_dudi)
        ");
        $stmt_sertif->bind_param("issdssss", $siswa_id, $nomor_final, $tanggal_terbit, $nilai_rata_rata, $nama_ttd_dudi_post, $jabatan_ttd_dudi_post, $nip_ttd_dudi_post, $logo_path);
        
        if (!$stmt_sertif->execute()) {
            $post_success = false;
        }
        $stmt_sertif->close();
    }

    if ($post_success && empty($message)) { // Pastikan tidak ada pesan error dari proses upload
        $koneksi->commit();
        $message = "<div class='alert success'><i class='fas fa-check-circle me-2'></i> Nilai dan Status Sertifikat berhasil diperbarui!</div>";
        header("Location: penilaian-akhir.php?siswa_id={$siswa_id}&status=success");
        exit();
    } else {
        $koneksi->rollback();
        if (empty($message)) {
            $message = "<div class='alert error'><i class='fas fa-exclamation-triangle me-2'></i> Gagal menyimpan data penilaian. Silakan coba lagi.</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Penilaian Akhir Sertifikat | Si Mantap PKL</title>
    <link rel="stylesheet" href="../css/style.css"> 
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --mantap-blue-dark: #0f172a;
            --mantap-blue-main: #1e40af;
            --mantap-blue-light: #3b82f6;
            --mantap-blue-soft: #eff6ff;
            --mantap-success: #10b981;
        }

        body { font-family: 'Poppins', sans-serif; background-color: #f8fafc; color: #334155; margin: 0; overflow-x: hidden !important; }
        .main-content-wrapper { max-width: 100% !important; width: 100% !important; box-sizing: border-box !important; }
        .admin-main-content { padding: 20px 25px 30px 25px !important; box-sizing: border-box !important; width: 100%; }

        .page-header-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .page-header-controls h1 { font-weight: 700; color: #0f172a; font-size: 1.8rem; margin: 0; position: relative; }
        .page-header-controls h1::after { content: ''; position: absolute; left: 0; bottom: -5px; width: 50px; height: 4px; background: var(--mantap-blue-main); border-radius: 2px; }

        .btn-back { color: white; background-color: #64748b; padding: 8px 16px; border-radius: 25px; text-decoration: none; font-weight: 600; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08); transition: 0.2s; }
        .btn-back:hover { background-color: #475569; }

        .alert { padding: 12px 15px; margin-bottom: 20px; border-radius: 8px; border: 1px solid; font-size: 13.5px; font-weight: 500; }
        .alert.success { background-color: #d1fae5; color: #065f46; border-color: #a7f3d0; }
        .alert.error { background-color: #fee2e2; color: #991b1b; border-color: #fecaca; }

        .info-card { background-color: var(--mantap-blue-soft); border-left: 5px solid var(--mantap-blue-main); padding: 18px; margin-bottom: 25px; border-radius: 8px; text-align: left; }
        .info-card p { margin: 6px 0; font-size: 13.5px; color: #334155; }
        .info-card strong { color: var(--mantap-blue-dark); font-weight: 700; }
        
        .status-badge { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 700; color: white; display: inline-block; margin-left: 5px;}
        .status-badge.status-success { background-color: var(--mantap-success); }
        .status-badge.status-belum { background-color: #f59e0b; }

        .glass-panel { background: white; padding: 25px; border-radius: 16px; border: 2px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); margin-bottom: 25px;}
        .glass-panel h2 { font-size: 1.4rem; font-weight: 700; color: var(--mantap-blue-dark); margin-top: 0; margin-bottom: 20px; border-bottom: 2px solid #f1f5f9; padding-bottom: 12px; }

        .table-container-fixed { width: 100%; overflow-x: auto; margin-bottom: 15px; }
        .custom-table-core { width: 100%; table-layout: fixed; border-collapse: collapse; border: 2px solid var(--mantap-blue-main); border-radius: 4px; overflow: hidden; min-width: 700px; }
        .custom-table-core th { background: var(--mantap-blue-main); color: white; padding: 12px 10px; font-size: 12.5px; text-transform: uppercase; font-weight: 700; text-align: center; border: 1px solid #cbd5e1; }
        .custom-table-core td { padding: 10px 10px; border: 1px solid #e2e8f0; font-size: 13px; vertical-align: middle; }
        
        .custom-table-core th.col-no { width: 60px; } 
        .custom-table-core th.col-komp { width: auto; text-align: left; padding-left: 15px; } 
        .custom-table-core th.col-nilai { width: 120px; } 
        .custom-table-core th.col-predikat { width: 140px; }

        .header-aspek td { background-color: #f1f5f9; font-weight: 700; color: var(--mantap-blue-dark); padding: 10px 15px !important; text-align: left !important; font-size: 13.5px; }
        .komponen-detail { padding-left: 25px !important; font-weight: 500; }

        .input-nilai { width: 100%; padding: 8px; text-align: center; border: 1px solid #cbd5e1; border-radius: 6px; font-family: 'Poppins'; font-size: 13px; font-weight: 600; box-sizing: border-box; transition: 0.2s; }
        .input-nilai:focus { outline: none; border-color: var(--mantap-blue-main); box-shadow: 0 0 0 2px rgba(30, 64, 175, 0.1); }
        .input-predikat { width: 100%; padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px; font-family: 'Poppins'; font-size: 12.5px; text-align: center; font-weight: 700; background-color: #f8fafc; color: #475569; box-sizing: border-box; }

        .nilai-total-box { margin-top: 15px; padding: 15px 20px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; font-weight: 600; text-align: right; color: #065f46; font-size: 14px; }
        .nilai-total-box span { color: var(--mantap-success); font-size: 1.6em; margin-left: 10px; font-weight: 800; }

        .form-sertifikat-input { margin-top: 30px; }
        .grid-3-col { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
        
        .form-group { text-align: left; margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px; color: #0f172a; }
        .form-group input[type="text"], .form-group input[type="date"] { width: 100%; padding: 11px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 13px; background-color: #f8fafc; box-sizing: border-box; transition: 0.2s; }
        .form-group input:focus { outline: none; border-color: var(--mantap-blue-main); background-color: white; box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1); }
        .form-group input[type="file"] { width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 8px; background-color: #f8fafc; font-size: 13px; cursor: pointer;}

        .btn-terbitkan { background-color: var(--mantap-success); color: white; padding: 12px 25px; border: none; border-radius: 8px; cursor: pointer; font-weight: 700; font-size: 14px; font-family: 'Poppins'; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2); transition: 0.2s; width: 100%; justify-content: center; margin-top: 10px; }
        .btn-terbitkan:hover { background-color: #059669; transform: translateY(-2px); }
        .btn-terbitkan:disabled { background-color: #94a3b8; cursor: not-allowed; box-shadow: none; transform: none; }

        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; }
            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; }
            .btn-back { width: 100% !important; justify-content: center; padding: 10px !important; border-radius: 8px !important; }
            .grid-3-col { grid-template-columns: 1fr; gap: 10px; }
            .glass-panel { padding: 20px 15px; }
            .nilai-total-box { text-align: center; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; ?>

<div class="main-content-wrapper">
    <?php include 'panel/navbar.php'; ?>

    <div class="admin-main-content">
        <div class="page-header-controls">
            <h1>Input Nilai & Sertifikat</h1>
            <a href="sertifikat.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali ke Penerbitan</a>
        </div>
        
        <?php if(isset($_GET['status']) && $_GET['status'] == 'success'): ?>
            <div class='alert success'><i class='fas fa-check-circle me-2'></i> Nilai dan Nomor Sertifikat berhasil disimpan!</div>
        <?php endif; ?>
        <?php echo $message; ?>

        <div class="info-card">
            <p><strong>Nama Siswa:</strong> <?php echo htmlspecialchars($siswa_info['nama']); ?> (NISN: <?php echo htmlspecialchars($siswa_info['nisn']); ?>)</p>
            <p><strong>Kelas:</strong> <?php echo htmlspecialchars($siswa_info['kelas']); ?></p>
            <p><strong>Progres Kendali:</strong> <?php echo $siswa_info['completed_count']; ?> / <?php echo $total_kegiatan_def; ?> Kegiatan</p>
            <p><strong>Status Kelayakan:</strong> 
                <?php if ($is_eligible): ?>
                    <span class="status-badge status-success"><i class="fas fa-check"></i> LAYAK (LULUS KENDALI)</span>
                <?php else: ?>
                    <span class="status-badge status-belum"><i class="fas fa-times"></i> BELUM LULUS (TIDAK LAYAK)</span>
                <?php endif; ?>
            </p>
        </div>
        
        <form method="POST" action="penilaian-akhir.php?siswa_id=<?php echo $siswa_id; ?>" id="formPenilaian" enctype="multipart/form-data">
            <input type="hidden" name="submit_nilai" value="1">
            
            <div class="glass-panel">
                <h2><i class="fas fa-tasks me-2" style="color: var(--mantap-blue-main);"></i> Komponen Penilaian PKL</h2>
                
                <div class="table-container-fixed">
                    <table class="custom-table-core">
                        <thead>
                            <tr>
                                <th class="col-no">NO</th>
                                <th class="col-komp">KOMPONEN PENILAIAN</th>
                                <th class="col-nilai">NILAI ANGKA</th>
                                <th class="col-predikat">PREDIKAT</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $global_num = 1;
                            $total_bobot_komponen = 0;
                            
                            if (!empty($grouped_components)):
                                foreach ($grouped_components as $aspek_name => $components):
                                    // Baris Header Aspek
                                    echo '<tr class="header-aspek"><td colspan="4">' . htmlspecialchars($global_num++) . '. ' . htmlspecialchars(strtoupper($aspek_name)) . '</td></tr>';

                                    $component_num = 1;
                                    foreach ($components as $row):
                                        $total_bobot_komponen += $row['bobot'];
                                        
                                        $input_name = "komponen_id[{$row['komponen_id']}]";
                                        $current_nilai = $row['nilai_angka'] ?? '';
                                        $current_predikat = $row['predikat'] ?? 'Baik';

                                        echo '<tr>';
                                        echo '<td style="text-align: center; color: #64748b; font-weight: bold;">' . $component_num++ . '</td>'; 
                                        echo '<td class="komponen-detail">' . htmlspecialchars($row['nama_komponen']) . '</td>';
                                        echo '<td style="text-align: center;"><input type="number" step="0.01" min="50" max="100" class="input-nilai" name="' . $input_name . '[nilai_angka]" value="' . $current_nilai . '" '. ($is_eligible ? '' : 'readonly style="background-color:#e2e8f0; cursor:not-allowed;"') .'></td>'; 
                                        echo '<td><input type="text" class="input-predikat" name="' . $input_name . '[predikat]" value="' . $current_predikat . '" readonly tabindex="-1"></td>'; 
                                        echo '</tr>';
                                    endforeach;
                                endforeach;
                            else: ?>
                                <tr><td colspan="4" style="text-align: center; color: #ef4444; font-style: italic; padding: 20px;">Belum ada Komponen Penilaian terdaftar di sistem.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="nilai-total-box">
                    Nilai Rata-Rata Akhir: <span id="nilaiRataRata"><?php echo $nilai_rata_rata; ?></span>
                </div>
            </div>

            <div class="glass-panel form-sertifikat-input">
                <h2><i class="fas fa-file-signature me-2" style="color: var(--mantap-blue-main);"></i> Penerbitan Sertifikat & TTD DU/DI</h2>
                
                <div class="grid-3-col">
                    <div class="form-group">
                        <label for="jabatan_ttd">Jabatan Pimpinan DU/DI</label>
                        <input type="text" name="jabatan_ttd_dudi" id="jabatan_ttd" value="<?php echo htmlspecialchars($input_jabatan); ?>" placeholder="Contoh: Manajer Operasional" <?php echo $is_eligible ? 'required' : 'readonly style="background-color:#e2e8f0; cursor:not-allowed;"'; ?>>
                    </div>
                    <div class="form-group">
                        <label for="nama_ttd">Nama Lengkap Pimpinan DU/DI</label>
                        <input type="text" name="nama_ttd_dudi" id="nama_ttd" value="<?php echo htmlspecialchars($input_nama); ?>" placeholder="Contoh: Budi Santoso, S.E." <?php echo $is_eligible ? 'required' : 'readonly style="background-color:#e2e8f0; cursor:not-allowed;"'; ?>>
                    </div>
                    <div class="form-group">
                        <label for="nip_ttd">NIP/NIK Pimpinan DU/DI (Opsional)</label>
                        <input type="text" name="nip_ttd_dudi" id="nip_ttd" value="<?php echo htmlspecialchars($input_nip); ?>" placeholder="Isi NIP/NIK jika ada" <?php echo $is_eligible ? '' : 'readonly style="background-color:#e2e8f0; cursor:not-allowed;"'; ?>>
                    </div>
                </div>

                <div class="grid-3-col">
                    <div class="form-group">
                        <label for="nomor_sertifikat">Nomor Sertifikat (Baku & Otomatis)</label>
                        <input type="text" name="nomor_sertifikat" id="nomor_sertifikat" value="<?php echo htmlspecialchars($default_nomor); ?>" placeholder="<?php echo $is_eligible ? 'Membuat nomor otomatis saat disimpan...' : 'Belum Lulus Kartu Kendali'; ?>" readonly tabindex="-1" style="background-color: #e2e8f0; cursor: not-allowed; font-weight: 700; color: var(--mantap-blue-main); border: 2px solid #cbd5e1;">
                        <?php if (!$is_eligible): ?>
                            <small style="color: #ef4444; font-size: 11.5px; font-weight: 500; display:block; margin-top:4px;"><i class="fas fa-exclamation-circle"></i> Siswa belum menyelesaikan Kartu Kendali. Nomor otomatis tidak dapat diterbitkan.</small>
                        <?php else: ?>
                            <small style="color: #10b981; font-size: 11.5px; font-weight: 600; display:block; margin-top:4px;"><i class="fas fa-check-circle"></i> Format Baku Terkunci: Nomor akan dipastikan ulang secara aman oleh sistem (Anti-Tabrakan).</small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="tanggal_terbit">Tanggal Terbit Sertifikat</label>
                        <input type="date" name="tanggal_terbit" id="tanggal_terbit" value="<?php echo htmlspecialchars($siswa_info['tanggal_terbit'] ?? date('Y-m-d')); ?>" <?php echo $is_eligible ? '' : 'readonly style="background-color:#e2e8f0; cursor:not-allowed;"'; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label for="logo_dudi">Logo Perusahaan (Opsional)</label>
                        <input type="file" name="logo_dudi" id="logo_dudi" accept="image/png, image/jpeg, image/jpg" <?php echo $is_eligible ? '' : 'disabled style="cursor:not-allowed;"'; ?>>
                        <small style="color: #64748b; font-size: 11.5px; display:block; margin-top:4px;"><i class="fas fa-info-circle"></i> Format JPG/PNG. Akan ditampilkan di cetak sertifikat.</small>
                        
                        <?php if(!empty($siswa_info['logo_dudi']) && file_exists($siswa_info['logo_dudi'])): ?>
                            <div style="margin-top: 10px; display: flex; align-items: center; gap: 10px;">
                                <img src="<?php echo htmlspecialchars($siswa_info['logo_dudi']); ?>" alt="Logo Tersimpan" style="max-height: 40px; border-radius: 4px; border: 1px solid #cbd5e1; padding: 2px; background: #fff;">
                                <span style="font-size: 11px; color: #10b981; font-weight: 600;"><i class="fas fa-check"></i> Logo Tersimpan</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <button type="submit" class="btn-terbitkan" id="btnSubmitFinal" <?php echo $is_eligible ? '' : 'disabled'; ?>>
                    <i class="fas fa-save"></i> <?php echo $is_eligible ? 'Simpan Penilaian & Update Sertifikat' : 'Terkunci (Belum Layak)'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // FUNGSI JAVASCRIPT UNTUK MENGHITUNG RATA-RATA NILAI DINAMIS
    document.addEventListener('DOMContentLoaded', function() {
        const nilaiInputs = document.querySelectorAll('.input-nilai');
        const nilaiRataRataSpan = document.getElementById('nilaiRataRata');
        const formPenilaian = document.getElementById('formPenilaian');
        const btnSubmitFinal = document.getElementById('btnSubmitFinal');
        const totalBobot = <?php echo $total_bobot; ?>;

        function getPredikat(nilai) {
            if (nilai >= 91) return 'Sangat Baik';
            if (nilai >= 81) return 'Baik';
            if (nilai >= 71) return 'Cukup';
            if (nilai >= 50) return 'Kurang';
            return '';
        }

        function calculateAverage() {
            let totalNilaiTerbobot = 0;
            let totalBobotAktif = 0;

            const aspekRows = document.querySelectorAll('.custom-table-core tbody tr:not(.header-aspek)');
            
            aspekRows.forEach(row => {
                const nilaiInput = row.querySelector('.input-nilai');
                const predikatInput = row.querySelector('.input-predikat');
                if(!nilaiInput) return; // skip empty rows

                const nilai = parseFloat(nilaiInput.value);
                const bobot = 1; // Rata-rata sederhana UI
                
                if (!isNaN(nilai) && nilai >= 50 && nilai <= 100) {
                    predikatInput.value = getPredikat(nilai);
                    totalNilaiTerbobot += (nilai * bobot); 
                    totalBobotAktif += 1;
                } else {
                    predikatInput.value = '';
                }
            });

            if (totalBobotAktif > 0) {
                const finalAverage = totalNilaiTerbobot / totalBobotAktif; 
                nilaiRataRataSpan.textContent = finalAverage.toFixed(2);
            } else {
                 nilaiRataRataSpan.textContent = '0.00';
            }
        }

        nilaiInputs.forEach(input => {
            input.addEventListener('input', calculateAverage);
        });

        calculateAverage();

        // Mencegah double click saat submit
        if(formPenilaian && btnSubmitFinal) {
            formPenilaian.addEventListener('submit', function() {
                btnSubmitFinal.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> MENYIMPAN DATA...';
                btnSubmitFinal.style.pointerEvents = 'none';
                btnSubmitFinal.style.opacity = '0.8';
            });
        }
    });
</script>

<?php include 'panel/footer.php'; ?>
</body>
</html>