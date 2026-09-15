<?php
// admin/lokasi-siswa-detail.php
// Halaman riwayat kunjungan monitoring.

include 'auth-check.php';
include '../config/db-koneksi.php';

$current_user = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin';
$current_user_id = $_SESSION['user_id'] ?? 0; 
$current_user_level = isset($_SESSION['level']) ? strtolower($_SESSION['level']) : 'admin'; 

$lokasi_id = isset($_GET['lokasi_id']) ? (int)$_GET['lokasi_id'] : 0;

if ($lokasi_id === 0) {
    die("ID Lokasi tidak valid.");
}

// Tentukan folder penyimpanan file upload
$upload_dir = '../uploads/monitoring/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$success_message = '';
$error_message = '';

// ---------------------------------------------------------------------
// B. QUERY DATA LOKASI, SISWA DAN RIWAYAT MONITORING
// ---------------------------------------------------------------------

// 1. Ambil Info Lokasi dan Guru, termasuk guru_id lokasi
$info_query = $koneksi->prepare("
    SELECT l.nama_lokasi, l.guru_id, g.nama_guru 
    FROM lokasi_pkl l 
    LEFT JOIN guru g ON l.guru_id = g.guru_id
    WHERE l.lokasi_id = ?
");
$info_query->bind_param("i", $lokasi_id);
$info_query->execute();
$info_result = $info_query->get_result();
$lokasi_info = $info_result->fetch_assoc();
$info_query->close();

if (!$lokasi_info) {
    die("Data lokasi tidak ditemukan.");
}

$guru_id_lokasi = $lokasi_info['guru_id'] ?? 0; 

// 2. Ambil Daftar Siswa Bimbingan di Lokasi ini
$siswa_query = $koneksi->prepare("SELECT nama, kelas FROM peserta_didik WHERE lokasi_id = ? ORDER BY nama ASC");
$siswa_query->bind_param("i", $lokasi_id);
$siswa_query->execute();
$siswa_list = $siswa_query->get_result();
$siswa_query->close();

// --- LOGIKA PENENTUAN HAK AKSES DAN GURU_ID PENCATAT ---
$guru_id_pencatat = 0;
$has_permission = false;
$pencatat_role = "User Biasa"; 

// Kasus 1: User adalah Guru dan membimbing lokasi ini (Akses Penuh)
$stmt_get_guru_id = $koneksi->prepare("SELECT guru_id FROM guru WHERE user_id = ?");
if ($stmt_get_guru_id) {
    $stmt_get_guru_id->bind_param("i", $current_user_id);
    $stmt_get_guru_id->execute();
    $result_guru_id = $stmt_get_guru_id->get_result();
    if ($result_guru_id->num_rows > 0) {
        $guru_login_id = $result_guru_id->fetch_assoc()['guru_id'];
        if ($guru_login_id == $guru_id_lokasi) {
            $guru_id_pencatat = $guru_login_id;
            $has_permission = true;
            $pencatat_role = "Guru Pembimbing";
        }
    }
    $stmt_get_guru_id->close();
}

// Kasus 2: User adalah Admin (Diizinkan mengisi atas nama Guru Lokasi)
if (!$has_permission && $current_user_level == 'admin') {
    if ($guru_id_lokasi > 0) {
        $guru_id_pencatat = $guru_id_lokasi;
        $has_permission = true;
        $pencatat_role = "Admin (Mewakili Guru)";
    } else {
        $has_permission = false; 
        $error_message = "Guru Pembimbing lokasi ini belum ditetapkan. Mohon tetapkan Guru Pembimbing terlebih dahulu.";
    }
}

// ---------------------------------------------------------------------
// A. PROSES HAPUS DATA KUNJUNGAN MONITORING
// ---------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'delete_mon' && isset($_GET['id']) && $has_permission) {
    $del_id = (int)$_GET['id'];
    
    // Ambil nama file foto untuk dihapus
    $q_foto = $koneksi->query("SELECT bukti_foto FROM monitoring_kunjungan WHERE kunjungan_id = $del_id AND guru_id = $guru_id_pencatat");
    if ($q_foto && $q_foto->num_rows > 0) {
        $foto = $q_foto->fetch_assoc()['bukti_foto'];
        if (!empty($foto) && file_exists($upload_dir . $foto)) {
            unlink($upload_dir . $foto);
        }
        
        $koneksi->query("DELETE FROM monitoring_kunjungan WHERE kunjungan_id = $del_id AND guru_id = $guru_id_pencatat");
        
        // --- TRIGGER LOG AKTIVITAS (DELETE) ---
        catatLog($koneksi, $current_user_id, "Menghapus data riwayat kunjungan monitoring di lokasi: " . $lokasi_info['nama_lokasi'] . " (ID Kunjungan: " . $del_id . ")");

        header("Location: lokasi-siswa-detail.php?lokasi_id=$lokasi_id&success=delete");
        exit;
    }
}

// ---------------------------------------------------------------------
// B. PROSES SIMPAN DATA KUNJUNGAN MONITORING (POST REQUEST)
// ---------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['tambah_kunjungan']) && $has_permission && $guru_id_pencatat > 0) {
    $tanggal_kunjungan = trim($_POST['tanggal_kunjungan']);
    $catatan_guru = trim($_POST['catatan_guru']);
    $kritik_saran_hrd = trim($_POST['kritik_saran_hrd']);
    $metode_foto = $_POST['metode_foto'] ?? 'upload';
    
    $bukti_foto = '';
    $upload_ok = false;

    if ($metode_foto === 'kamera') {
        // PROSES FOTO DARI KAMERA (BASE64)
        $foto_base64 = $_POST['foto_base64'] ?? '';
        if (!empty($foto_base64)) {
            $image_parts = explode(";base64,", $foto_base64);
            if (count($image_parts) == 2) {
                $image_base64 = base64_decode($image_parts[1]);
                $new_file_name = 'mon_' . $lokasi_id . '_cam_' . time() . '.jpg';
                $file_destination = $upload_dir . $new_file_name;
                
                if (file_put_contents($file_destination, $image_base64)) {
                    $bukti_foto = $new_file_name;
                    $upload_ok = true;
                } else {
                    $error_message = "Gagal menyimpan foto hasil jepretan kamera ke server.";
                }
            } else {
                $error_message = "Format data foto kamera tidak valid.";
            }
        } else {
            $error_message = "Anda belum mengambil jepretan foto dari kamera.";
        }
    } else {
        // PROSES UPLOAD FILE BIASA
        if (isset($_FILES['bukti_foto']) && $_FILES['bukti_foto']['error'] == 0) {
            $file_info = pathinfo($_FILES['bukti_foto']['name']);
            $file_ext = strtolower($file_info['extension']);
            $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
            
            if (in_array($file_ext, $allowed_ext)) {
                $new_file_name = 'mon_' . $lokasi_id . '_' . time() . '.' . $file_ext;
                $file_destination = $upload_dir . $new_file_name;
                
                if (move_uploaded_file($_FILES['bukti_foto']['tmp_name'], $file_destination)) {
                    $bukti_foto = $new_file_name;
                    $upload_ok = true;
                } else {
                    $error_message = "Gagal mengupload file foto.";
                }
            } else {
                $error_message = "Jenis file tidak didukung. Harap upload JPG, JPEG, PNG, atau WEBP.";
            }
        } else {
            $error_message = "Bukti foto wajib diupload.";
        }
    }

    if ($upload_ok && empty($error_message)) {
        $stmt = $koneksi->prepare("INSERT INTO monitoring_kunjungan (guru_id, lokasi_id, tanggal_kunjungan, catatan_guru, kritik_saran_hrd, bukti_foto) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissss", $guru_id_pencatat, $lokasi_id, $tanggal_kunjungan, $catatan_guru, $kritik_saran_hrd, $bukti_foto);
        
        if ($stmt->execute()) {
            
            // --- TRIGGER LOG AKTIVITAS (ADD) ---
            catatLog($koneksi, $current_user_id, "Mencatat riwayat kunjungan monitoring untuk lokasi: " . $lokasi_info['nama_lokasi'] . " (Metode Foto: " . strtoupper($metode_foto) . ")");

            header("Location: lokasi-siswa-detail.php?lokasi_id={$lokasi_id}&success=kunjungan");
            exit();
        } else {
            $error_message = "Gagal menyimpan data monitoring: " . $koneksi->error;
        }
        $stmt->close();
    }
}

// Cek parameter success setelah redirect
if (isset($_GET['success'])) {
    if ($_GET['success'] == 'kunjungan') $success_message = "Data kunjungan monitoring berhasil dicatat!";
    if ($_GET['success'] == 'delete') $success_message = "Data riwayat kunjungan berhasil dihapus!";
}

// 2. Ambil Riwayat Kunjungan Monitoring
$riwayat_kunjungan_query = $koneksi->prepare("
    SELECT k.kunjungan_id, k.tanggal_kunjungan, k.catatan_guru, k.kritik_saran_hrd, k.bukti_foto, g.nama_guru 
    FROM monitoring_kunjungan k
    LEFT JOIN guru g ON k.guru_id = g.guru_id
    WHERE k.lokasi_id = ?
    ORDER BY k.tanggal_kunjungan ASC
");
$riwayat_kunjungan_query->bind_param("i", $lokasi_id);
$riwayat_kunjungan_query->execute();
$riwayat_kunjungan_data = $riwayat_kunjungan_query->get_result();
$riwayat_kunjungan_query->close();

$target_monitoring = 3; // Target standar jumlah kunjungan
$total_kunjungan = $riwayat_kunjungan_data->num_rows;
$progress_persen = min(100, round(($total_kunjungan / $target_monitoring) * 100));

// Fungsi untuk format tanggal (Contoh output: 09 Juni 2026)
function formatTanggal($tanggal) {
    if (empty($tanggal)) return '-';
    $timestamp = strtotime($tanggal);
    $bulan_indo = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $tgl = date('d', $timestamp);
    $bln = $bulan_indo[(int)date('m', $timestamp)];
    $thn = date('Y', $timestamp);
    
    return "{$tgl} {$bln} {$thn}"; 
}

$tanggal_sekarang_indo = formatTanggal(date('Y-m-d'));

// =====================================================================
// 4. INTERCEPTOR HALAMAN CETAK LAPORAN (MUNCUL JIKA ?print=1)
// =====================================================================
if (isset($_GET['print']) && $_GET['print'] == '1') {
    // Parameter Kop Surat
    $nama_sekolah = "SEKOLAH MENENGAH KEJURUAN ISLAM 1 BLITAR";
    $jurusan = "DESAIN PEMODELAN DAN INFORMASI BANGUNAN";
    $alamat_sekolah = "Jl. Musi No. 6 Blitar, 66117. Telp 081249464046";
    $website = "http://dpib.smkislam1blitar.sch.id | E-mail: dpibsmkislam1blitar@gmail.com";
    $nama_kaprog = "Mochamad Ade Satria, S.T.";
    
    // --- LOGIKA FILTER CETAK KHUSUS TANGGAL (Jika Ada) ---
    $kunj_id_print = isset($_GET['kunj_id']) ? (int)$_GET['kunj_id'] : 0;
    
    $query_print = "
        SELECT k.kunjungan_id, k.tanggal_kunjungan, k.catatan_guru, k.kritik_saran_hrd, k.bukti_foto, g.nama_guru 
        FROM monitoring_kunjungan k
        LEFT JOIN guru g ON k.guru_id = g.guru_id
        WHERE k.lokasi_id = ?
    ";
    
    if ($kunj_id_print > 0) {
        $query_print .= " AND k.kunjungan_id = ?";
        $query_print .= " ORDER BY k.tanggal_kunjungan ASC";
        $stmt_print = $koneksi->prepare($query_print);
        $stmt_print->bind_param("ii", $lokasi_id, $kunj_id_print);
    } else {
        $query_print .= " ORDER BY k.tanggal_kunjungan ASC";
        $stmt_print = $koneksi->prepare($query_print);
        $stmt_print->bind_param("i", $lokasi_id);
    }
    
    $stmt_print->execute();
    $print_data = $stmt_print->get_result();
    $stmt_print->close();

    // --- TRIGGER LOG AKTIVITAS (PRINT Laporan) ---
    catatLog($koneksi, $current_user_id, "Mencetak / mem-preview laporan riwayat kunjungan monitoring untuk lokasi: " . $lokasi_info['nama_lokasi']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Laporan Monitoring - <?php echo htmlspecialchars($lokasi_info['nama_lokasi']); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Times New Roman', Times, serif; color: #000; background: #fff; padding: 5px 30px 30px 30px; font-size: 11pt; line-height: 1.4; }
        .kop { display: flex; justify-content: space-between; align-items: center; border-bottom: 4px double #000; padding-bottom: 10px; margin-top: 0px; margin-bottom: 20px; }
        .kop .logo { width: 90px; height: 90px; object-fit: contain; }
        .kop .info-sekolah { text-align: center; flex-grow: 1; }
        .kop .info-sekolah h2 { margin: 0; line-height: 1.1; font-weight: bold; font-size: 13.5pt; text-transform: uppercase; white-space: nowrap; }
        .kop .info-sekolah h3 { margin: 4px 0; font-size: 12pt; font-weight: bold; text-transform: uppercase; }
        .kop .info-sekolah p { margin: 1px 0; font-size: 9.5pt; font-style: normal; }

        .document-title { text-align: center; font-weight: bold; font-size: 12pt; text-decoration: underline; text-transform: uppercase; margin-bottom: 20px; margin-top: 5px; letter-spacing: 0.5px; }
        
        .meta-info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .meta-info-table td { padding: 3px 0; font-size: 11pt; vertical-align: top; }
        .meta-info-table td.label { width: 22%; font-weight: bold; }
        .meta-info-table td.value { width: 78%; }

        .report-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .report-table th { background-color: #f2f2f2 !important; color: #000 !important; font-weight: bold; text-transform: uppercase; font-size: 10pt; padding: 10px 6px; border: 1px solid #000; text-align: center; }
        .report-table td { padding: 8px 6px; border: 1px solid #000; font-size: 10.5pt; vertical-align: top; }

        .print-footer { display: flex; justify-content: space-between; margin-top: 50px; page-break-inside: avoid; }
        .ttd-box { text-align: center; width: 250px; }
        .ttd-box p { margin: 0; line-height: 1.5; }

        @media print {
            body { padding: 0; background: #fff; }
            .no-print { display: none !important; }
            @page { margin: 1cm 1.5cm 1.5cm 1.5cm; size: A4 portrait; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="background: #f1f5f9; padding: 12px 25px; margin: -5px -30px 25px -30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #cbd5e1; font-family:'Poppins', sans-serif;">
        <span style="font-weight: 600; color: #475569;"><i class="fas fa-print me-2"></i>Dokumen PDF Siap Cetak (A4 Portrait) <?php echo $kunj_id_print > 0 ? '- Difilter' : ''; ?></span>
        <div style="display: flex; gap: 8px;">
            <button onclick="window.close();" style="padding: 6px 14px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; font-weight: 600; cursor: pointer; color: #475569;">Tutup</button>
            <button onclick="window.print();" style="padding: 6px 18px; border-radius: 6px; border: none; background: #10b981; color: #fff; font-weight: 600; cursor: pointer;">Cetak Berkas</button>
        </div>
    </div>

    <div class="kop">
        <img src="../img/logosekolah.png" alt="Logo Sekolah" class="logo">
        <div class="info-sekolah">
            <h2><?php echo htmlspecialchars(strtoupper($jurusan)); ?></h2> 
            <h3><?php echo htmlspecialchars(strtoupper($nama_sekolah)); ?></h3> 
            <p><?php echo htmlspecialchars($alamat_sekolah); ?></p>
            <p><?php echo htmlspecialchars($website); ?></p>
        </div>
        <img src="../img/logobangunan.png" alt="Logo DPIB" class="logo">
    </div>

    <div class="document-title">LAPORAN KUNJUNGAN KERJA MONITORING PKL</div>

    <table class="meta-info-table">
        <tr>
            <td class="label">Lokasi PKL</td>
            <td class="value">: <strong><?php echo htmlspecialchars($lokasi_info['nama_lokasi']); ?></strong></td>
        </tr>
        <tr>
            <td class="label">Guru Pembimbing</td>
            <td class="value">: <?php echo htmlspecialchars($lokasi_info['nama_guru'] ?? 'Belum Ditentukan'); ?></td>
        </tr>
        <tr>
            <td class="label" style="vertical-align: top;">Siswa Bimbingan</td>
            <td class="value" style="vertical-align: top;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                    <tr>
                        <td width="10" valign="top">:</td>
                        <td valign="top">
                            <?php if ($siswa_list && $siswa_list->num_rows > 0): ?>
                                <ol style="margin: 0; padding-left: 18px;">
                                    <?php 
                                    $siswa_list->data_seek(0);
                                    while ($s = $siswa_list->fetch_assoc()): 
                                    ?>
                                        <li><?php echo htmlspecialchars($s['nama']) . ' (' . htmlspecialchars($s['kelas']) . ')'; ?></li>
                                    <?php endwhile; ?>
                                </ol>
                            <?php else: ?>
                                <span style="font-style: italic;">Belum ada siswa penempatan di lokasi ini.</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="report-table">
        <thead>
            <tr>
                <th width="5%">NO</th>
                <th width="15%">Tanggal</th>
                <th width="30%">Catatan Hasil Monitoring</th>
                <th width="25%">Kritik & Masukan DUDI</th>
                <th width="25%">Bukti Foto</th> 
            </tr>
        </thead>
        <tbody>
            <?php if ($print_data && $print_data->num_rows > 0): ?>
                <?php 
                $no = 1; 
                while ($row = $print_data->fetch_assoc()): 
                    $kritik = empty($row['kritik_saran_hrd']) && $row['kritik_saran_hrd'] !== '0' ? '-' : nl2br(htmlspecialchars($row['kritik_saran_hrd']));
                ?>
                <tr>
                    <td align="center"><?php echo $no++; ?></td>
                    <td align="center"><?php echo formatTanggal($row['tanggal_kunjungan']); ?></td>
                    <td><?php echo nl2br(htmlspecialchars($row['catatan_guru'])); ?></td>
                    <td><?php echo $kritik; ?></td>
                    <td align="center" style="vertical-align: middle;">
                        <?php if (!empty($row['bukti_foto'])): ?>
                            <img src="../uploads/monitoring/<?php echo htmlspecialchars($row['bukti_foto']); ?>" style="max-width: 140px; max-height: 120px; object-fit: cover; border: 1px solid #ccc; padding: 2px;">
                        <?php else: ?>
                            <span style="font-size: 10px; color: #777;">Tidak ada foto</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" align="center" style="padding: 20px;">Belum ada riwayat kunjungan.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="print-footer">
        <div class="ttd-box">
            <p style="margin-bottom: 80px;">Mengetahui,<br>Ketua Jurusan DPIB</p>
            <p style="font-weight: bold; text-decoration: underline;"><?php echo htmlspecialchars($nama_kaprog); ?></p>
        </div>
        <div class="ttd-box">
            <p style="margin-bottom: 80px;">Blitar, <?php echo $tanggal_sekarang_indo; ?><br>Pembimbing Institusi</p>
            <p style="font-weight: bold; text-decoration: underline;"><?php echo htmlspecialchars($lokasi_info['nama_guru'] ?? '.......................'); ?></p>
        </div>
    </div>

    <script>
        window.addEventListener('DOMContentLoaded', (event) => {
            window.print();
        });
    </script>
</body>
</html>
<?php 
    exit; 
} 
// =====================================================================
// AKHIR BLOK CETAK LAPORAN
// =====================================================================
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin - Monitoring Lokasi <?php echo htmlspecialchars($lokasi_info['nama_lokasi']); ?> | Si Mantap PKL</title>
    
    <link rel="stylesheet" href="../css/style.css"> 
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

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

        .btn-back {
            color: white !important;
            padding: 8px 16px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            font-size: 12px;
            background-color: #64748b;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08);
            transition: 0.2s;
        }
        .btn-back:hover { background-color: #475569; transform: translateY(-1px); }

        .alert-success, .alert-error { padding: 12px 15px; margin-bottom: 20px; border-radius: 8px; border: 1px solid; font-size: 14px; font-weight: 500; }
        .alert-success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }

        /* INFO CARD PANEL & DAFTAR SISWA */
        .info-card {
            background-color: var(--mantap-blue-soft);
            border-left: 5px solid var(--mantap-blue-main);
            padding: 18px;
            margin-bottom: 25px;
            border-radius: 8px;
            text-align: left;
        }
        .info-card p { margin: 6px 0; font-size: 13.5px; color: #334155; }
        .info-card strong { color: var(--mantap-blue-dark); font-weight: 700; }
        
        .student-list-box {
            margin-top: 10px;
        }
        .student-list-box ol {
            margin-top: 4px;
            margin-bottom: 10px;
            padding-left: 20px;
            font-size: 13.5px;
            color: #334155;
            line-height: 1.5;
        }
        
        /* PROGRESS BAR STYLE */
        .progress-container { background: #cbd5e1; border-radius: 8px; height: 10px; width: 100%; margin-top: 5px; overflow: hidden; }
        .progress-bar { height: 100%; border-radius: 8px; transition: width 0.5s ease-in-out; }
        
        /* FORM MONITORING SECTION */
        .monitoring-section {
            background: white;
            padding: 25px;
            margin-bottom: 30px;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
            border: 2px solid #e2e8f0;
            box-sizing: border-box;
        }
        .monitoring-section h2 {
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 12px;
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--mantap-blue-dark);
        }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13.5px; color: #0f172a; text-align: left; }
        .form-group input[type="date"], 
        .form-group textarea, 
        .form-group input[type="file"] {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 13.5px;
            box-sizing: border-box;
            background-color: #f8fafc;
        }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #1e40af; background-color: white; }
        .form-group textarea { resize: vertical; }

        /* Pilihan Metode Foto */
        .metode-radio-box {
            display: flex; gap: 15px; background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #cbd5e1; margin-bottom: 10px;
        }
        .metode-radio-box label { margin: 0; font-weight: 500; font-size: 13.5px; display: flex; align-items: center; gap: 6px; cursor: pointer; color: #334155; }
        .metode-radio-box input[type="radio"] { cursor: pointer; }

        /* KOTAK KAMERA & MAP PREVIEW */
        .status-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px; }
        .status-item { background: white; border-radius: 8px; padding: 10px; text-align: center; border: 1px solid #e2e8f0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; transition: 0.3s; }
        .status-item.active { background: #f0fdf4; border-color: #22c55e; color: #166534; }
        .status-item.inactive { background: #fef2f2; border-color: #ef4444; color: #991b1b; }
        .status-item i { font-size: 1.2rem; }
        .status-item p { font-size: 10px; font-weight: 700; margin: 0; letter-spacing: 0.5px; }

        .kamera-container { display: flex; flex-wrap: wrap; gap: 15px; }
        .kamera-box, .map-box { flex: 1; min-width: 300px; background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 12px; overflow: hidden; position: relative; }
        
        #kamera_wrapper { width: 100%; aspect-ratio: 4/3; background: #000; position: relative; display: flex; align-items: center; justify-content: center; }
        #kamera_wrapper video, #kamera_wrapper img { width: 100%; height: 100%; object-fit: cover; display: block; }
        
        #map-kamera { width: 100%; height: 100%; min-height: 250px; background: #e2e8f0; }

        #btnStartCamera { background: #3b82f6; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 10px; width: 100%; justify-content: center; transition: 0.2s;}
        #btnStartCamera:hover { background: #2563eb; }
        
        #btnCapture { background: #22c55e; color: white; padding: 12px; border: none; border-radius: 8px; cursor: pointer; font-weight: 700; font-size: 14px; display: none; align-items: center; justify-content: center; gap: 6px; width: 100%; margin-top: 10px; text-transform: uppercase; box-shadow: 0 4px 6px rgba(34,197,94,0.2); }
        #btnCapture:hover { background: #16a34a; }

        #btnRetake { background: #f59e0b; color: white; padding: 10px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; display: none; align-items: center; justify-content: center; gap: 6px; width: 100%; margin-top: 10px; }
        #btnRetake:hover { background: #d97706; }

        .btn-submit {
            background-color: var(--mantap-blue-main);
            color: white;
            padding: 10px 24px;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            font-family: 'Poppins', sans-serif;
            box-shadow: 0 4px 6px -1px rgba(30, 64, 175, 0.15);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: 0.2s;
        }
        .btn-submit:hover { background-color: var(--mantap-blue-light); transform: translateY(-1px); }
        
        .glass-panel-table {
            background: white !important;
            padding: 25px !important;
            border-radius: 16px !important;
            border: 2px solid #e2e8f0 !important; 
            box-sizing: border-box !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -2px rgba(0, 0, 0, 0.02);
            width: 100%;
        }

        /* --- PERBAIKAN RESPONSIVITAS TABEL MONITORING --- */
        .table-container-fixed { 
            width: 100%; 
            overflow-x: auto; 
            box-sizing: border-box; 
            border: none !important; 
            -webkit-overflow-scrolling: touch; 
        }

        .custom-table-core {
            width: 100%; 
            table-layout: auto; 
            min-width: 1000px; /* Diubah agar tidak terlalu tabrakan tapi tetap proporsional */
            border-collapse: collapse; 
            background: white; 
            border-radius: 4px; 
            overflow: hidden; 
            border: 2px solid #1e40af; 
        }
        
        .custom-table-core th {
            background: #1e40af; color: white; padding: 14px 15px; font-size: 13px; text-transform: uppercase; font-weight: 700; text-align: center; border: 1px solid #cbd5e1; box-sizing: border-box; white-space: nowrap;
        }
        
        .custom-table-core td { 
            padding: 12px 10px; font-size: 13px; vertical-align: middle; word-wrap: break-word; overflow-wrap: break-word; white-space: normal; text-align: center; border: 1px solid #cbd5e1; color: #0f172a; background-color: white !important; box-sizing: border-box;
        }
        .custom-table-core tbody tr:hover td { background-color: #f8fafc !important; }

        /* KOLOM DATA HISTORI RIWAYAT (Dinamic Width) */
        .custom-table-core th.rw-no { width: 50px; }
        .custom-table-core th.rw-tgl { width: 140px; }
        .custom-table-core th.rw-guru { width: 160px; }
        .custom-table-core th.rw-cat { min-width: 250px; }
        .custom-table-core th.rw-hrd { min-width: 250px; }
        .custom-table-core th.rw-foto { width: 120px; }
        .custom-table-core th.rw-aksi { width: 100px; }

        .btn-aksi-link {
            color: white !important; padding: 5px 10px; border-radius: 4px; text-decoration: none; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; gap: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.15); transition: 0.2s;
        }
        .btn-edit-inline { background-color: #f59e0b; }
        .btn-edit-inline:hover { background-color: #d97706; transform: translateY(-1px); }
        .btn-delete-inline { background-color: #dc3545; }
        .btn-delete-inline:hover { background-color: #ef4444; transform: translateY(-1px); }
        .btn-print-inline { background-color: #10b981; }
        .btn-print-inline:hover { background-color: #059669; transform: translateY(-1px); }

        .dashboard-btn-group { display: flex; gap: 5px; justify-content: center; align-items: center; }

        /* FOTO THUMBNAIL MONITORING */
        .img-monitoring-thumb { 
            max-width: 80px; max-height: 60px; object-fit: cover; border-radius: 6px; cursor: pointer; border: 2px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: 0.2s;
        }
        .img-monitoring-thumb:hover { border-color: var(--mantap-blue-light); transform: scale(1.05); }

        /* FOTO LIGHTBOX PREVIEW POPUP */
        .foto-modal {
            display: none; position: fixed; z-index: 999999 !important; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(15, 23, 42, 0.6); backdrop-filter: blur(3px);
        }
        .foto-modal-content {
            background-color: #fff; margin: 5% auto; border-radius: 12px; width: 90%; max-width: 480px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15); overflow: hidden; border: 2px solid #cbd5e1;
        }
        .foto-modal-header { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
        .foto-modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #64748b; line-height: 1; }

        /* =========================================================================
           RESPONSIVE VIEWPORT SMARTPHONE (HP SCROLLABLE TABLE MODE)
        ========================================================================= */
        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 

            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; padding: 5px 0px !important; }
            .page-header-controls h1 { font-size: 1.4rem !important; }
            .btn-back { width: 100% !important; justify-content: center !important; padding: 11px !important; border-radius: 8px !important; }
            
            .info-card { padding: 14px 12px !important; }
            .info-card p, .student-list-box ol { font-size: 13px !important; }
            
            .monitoring-section { padding: 18px 14px !important; border-radius: 12px !important; border: 2px solid #cbd5e1 !important; }
            .monitoring-section h2, h2 { font-size: 1.2rem !important; }
            .form-group input, .form-group textarea { font-size: 13.5px !important; padding: 10px !important; }
            .btn-submit { width: 100% !important; justify-content: center !important; padding: 12px !important; border-radius: 8px !important; font-size: 14px !important; }
            .metode-radio-box { flex-direction: column; gap: 10px; }

            .glass-panel-table { padding: 16px 10px !important; border-radius: 12px !important; border: 2px solid #cbd5e1 !important; margin: 0 !important; }

            .table-container-fixed { display: block !important; width: 100% !important; overflow-x: auto !important; }
            
            .custom-table-core { table-layout: auto !important; min-width: 1050px !important; }
            
            .custom-table-core th, .custom-table-core td { padding: 12px 10px !important; font-size: 13px !important; }
            .custom-table-core th.rw-no, .custom-table-core th.rw-tgl, .custom-table-core th.rw-guru, .custom-table-core th.rw-cat, .custom-table-core th.rw-hrd, .custom-table-core th.rw-foto, .custom-table-core th.rw-aksi { width: auto !important; }
            
            .custom-table-core td:nth-child(3), .custom-table-core td:nth-child(4), .custom-table-core td:nth-child(5) { text-align: left !important; padding-left: 10px !important; }
            .dashboard-btn-group { flex-direction: row; gap: 4px; }
            .btn-aksi-link { padding: 6px 10px !important; font-size: 11px !important; }
            .img-monitoring-thumb { max-width: 90px; max-height: 65px; }
            
            .kamera-container { flex-direction: column; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; ?>

<div class="main-content-wrapper">

    <?php include 'panel/navbar.php'; ?>

    <div class="admin-main-content"> 
        
        <div class="page-header-controls">
            <h1>Monitoring Lokasi Bimbingan</h1>
            <a href="monitoring-guru.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali ke Daftar Lokasi</a>
        </div>
        
        <div class="info-card">
            <p><strong>Nama Mitra Instansi DUDI:</strong> <?php echo htmlspecialchars($lokasi_info['nama_lokasi']); ?></p>
            <p><strong>Guru Pembimbing Resmi:</strong> <?php echo htmlspecialchars($lokasi_info['nama_guru'] ?? 'Belum Ditentukan'); ?></p>
            
            <div class="student-list-box">
                <p><strong>Siswa Bimbingan:</strong></p>
                <?php if ($siswa_list && $siswa_list->num_rows > 0): ?>
                    <ol>
                        <?php 
                        $siswa_list->data_seek(0);
                        while ($s = $siswa_list->fetch_assoc()): 
                        ?>
                            <li><?php echo htmlspecialchars($s['nama']) . ' (' . htmlspecialchars($s['kelas']) . ')'; ?></li>
                        <?php endwhile; ?>
                    </ol>
                <?php else: ?>
                    <p style="color: #ef4444; font-style: italic; font-size: 13.5px; margin-top: 4px;">Belum ada siswa penempatan di lokasi ini.</p>
                <?php endif; ?>
            </div>

            <hr style="border: none; border-top: 1px dashed #cbd5e1; margin: 12px 0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                <p style="margin:0;"><strong>Progres Monitoring:</strong> <?php echo $total_kunjungan; ?> dari <?php echo $target_monitoring; ?> Kunjungan Target</p>
                <span style="font-size: 12px; font-weight: 700; color: <?php echo $progress_persen >= 100 ? '#22c55e' : 'var(--mantap-blue-main)'; ?>;"><?php echo $progress_persen; ?>%</span>
            </div>
            <div class="progress-container">
                <div class="progress-bar" style="width: <?php echo $progress_persen; ?>%; background: <?php echo $progress_persen >= 100 ? '#22c55e' : 'var(--mantap-blue-main)'; ?>;"></div>
            </div>

            <?php if (!$has_permission): ?>
                <p style="color: #ef4444; font-weight: 600; margin: 10px 0 0 0;"><i class="fas fa-exclamation-triangle fa-fw me-1"></i> <?php echo !empty($error_message) ? $error_message : 'Akses ditolak: Anda tidak memiliki otoritas pencatatan lembar kunjungan di mitra industri ini.'; ?></p>
            <?php endif; ?>
        </div>

        <div class="monitoring-section">
            <h2>Catat Kunjungan Monitoring Baru</h2>
            
            <?php if ($success_message): ?>
                <div class="alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            <?php if ($error_message && $has_permission): ?>
                <div class="alert-error">Gagal menyimpan: <?php echo $error_message; ?></div>
            <?php endif; ?>

            <?php if ($has_permission && $guru_id_pencatat > 0): ?>
                <form method="POST" action="lokasi-siswa-detail.php?lokasi_id=<?php echo $lokasi_id; ?>" enctype="multipart/form-data" id="formMonitoring">
                    <input type="hidden" name="tambah_kunjungan" value="1">
                    
                    <div class="form-group">
                        <label for="tanggal_kunjungan">Tanggal Pelaksanaan Kunjungan:</label>
                        <input type="date" id="tanggal_kunjungan" name="tanggal_kunjungan" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="catatan_guru">Uraian / Hasil Lembar Catatan Guru:</label>
                        <textarea id="catatan_guru" name="catatan_guru" rows="3" placeholder="Masukkan ringkasan hasil pemantauan kemajuan kompetensi atau kendala siswa..." required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="kritik_saran_hrd">Kritik, Umpan Balik, & Saran Pihak HRD (Mitra Penempatan):</label>
                        <textarea id="kritik_saran_hrd" name="kritik_saran_hrd" rows="3" placeholder="Masukkan feedback masukan kurikulum atau penilaian sikap dari supervisor lapangan..." required></textarea>
                    </div>

                    <div class="form-group">
                        <label>Pilih Metode Lampiran Bukti Foto:</label>
                        <div class="metode-radio-box">
                            <label><input type="radio" name="metode_foto" value="upload" checked onchange="toggleModeFoto()"> Unggah File Gambar</label>
                            <label><input type="radio" name="metode_foto" value="kamera" onchange="toggleModeFoto()"> Kamera Langsung (GPS Watermark)</label>
                        </div>
                    </div>

                    <div class="form-group" id="area_upload">
                        <label for="bukti_foto">Unggah Bukti Foto Kunjungan Fisik (JPG/PNG/WEBP):</label>
                        <input type="file" id="bukti_foto" name="bukti_foto" accept=".jpg, .jpeg, .png, .webp" required>
                    </div>

                    <div class="form-group" id="area_kamera" style="display:none; border: 2px dashed #cbd5e1; padding: 15px; border-radius: 12px; background: #f8fafc;">
                        
                        <div class="status-grid">
                            <div class="status-item inactive" id="status-kamera">
                                <i class="fas fa-camera"></i>
                                <p id="label-kamera">KAMERA: OFF</p>
                            </div>
                            <div class="status-item inactive" id="status-gps">
                                <i class="fas fa-location-dot"></i>
                                <p id="label-gps">GPS: MENCARI...</p>
                            </div>
                        </div>

                        <button type="button" id="btnStartCamera"><i class="fas fa-camera"></i> Izin Kamera & Lokasi</button>
                        
                        <div class="kamera-container" id="cameraSystemBox" style="display: none;">
                            <div class="kamera-box">
                                <div id="kamera_wrapper">
                                    <video id="videoCam" autoplay playsinline></video>
                                    <img id="previewCam" style="display:none;" alt="Preview Foto Kamera">
                                </div>
                                <canvas id="canvasCam" style="display:none;"></canvas>
                                
                                <button type="button" id="btnCapture"><i class="fas fa-circle"></i> Ambil Foto</button>
                                <button type="button" id="btnRetake"><i class="fas fa-redo"></i> Foto Ulang</button>
                            </div>
                            
                            <div class="map-box">
                                <div id="map-kamera"></div>
                                <div style="padding: 10px; text-align: center; background: white; border-top: 1px solid #e2e8f0;">
                                    <span id="gps_status_text" style="font-size: 11px; font-weight: 600; color: #64748b;"><i class="fas fa-spinner fa-spin"></i> Mendapatkan Lokasi Satelit...</span>
                                </div>
                            </div>
                        </div>
                        
                        <input type="hidden" name="foto_base64" id="foto_base64">
                    </div>

                    <div style="margin-top: 25px;">
                        <button type="submit" class="btn-submit" id="btnFinalSubmit"><i class="fas fa-save"></i> Simpan Lembar Kunjungan</button>
                    </div>
                </form>
            <?php else: ?>
                <div style="text-align: center; color: #64748b; font-style: italic; padding: 15px; border: 2px dashed #cbd5e1; background: #f8fafc; border-radius: 8px; font-weight: 500;">
                    <i class="fas fa-lock me-1"></i> Formulir entri dikunci. Anda hanya diberikan hak akses meninjau histori kunjungan tanpa otorisasi penulisan.
                </div>
            <?php endif; ?>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 35px; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
            <h2 style="font-size: 1.4rem; font-weight: 700; color: var(--mantap-blue-dark); margin: 0;"><i class="fas fa-history me-2" style="color:var(--mantap-blue-main);"></i>Riwayat Kunjungan Kerja Monitoring</h2>
            <?php if ($riwayat_kunjungan_data && $riwayat_kunjungan_data->num_rows > 0): ?>
                <a href="lokasi-siswa-detail.php?lokasi_id=<?php echo $lokasi_id; ?>&print=1" target="_blank" class="btn-submit" style="background-color: #10b981; margin: 0; padding: 8px 16px; border-radius: 6px;"><i class="fas fa-print"></i> Cetak Laporan (Semua)</a>
            <?php endif; ?>
        </div>
        
        <div class="glass-panel-table" style="margin-bottom: 20px;">
            <div class="table-container-fixed">
                <table class="custom-table-core">
                    <thead>
                        <tr>
                            <th class="rw-no">NO</th>
                            <th class="rw-tgl">Tanggal Kunjungan</th>
                            <th class="rw-guru" style="text-align: left; padding-left: 10px;">Oleh Pendidik</th>
                            <th class="rw-cat" style="text-align: left; padding-left: 10px;">Catatan Hasil Monitoring</th>
                            <th class="rw-hrd" style="text-align: left; padding-left: 10px;">Kritik & Masukan DUDI</th>
                            <th class="rw-foto">Bukti Foto</th>
                            <th class="rw-aksi">Opsi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($riwayat_kunjungan_data && $riwayat_kunjungan_data->num_rows > 0): ?>
                            <?php 
                            $riwayat_kunjungan_data->data_seek(0);
                            $riwayat_num = 1; 
                            while ($row = $riwayat_kunjungan_data->fetch_assoc()): 
                                $kritik_saran_output = empty($row['kritik_saran_hrd']) && $row['kritik_saran_hrd'] !== '0' ? '<span style="color:#cbd5e1; font-style:italic;">-</span>' : nl2br(htmlspecialchars($row['kritik_saran_hrd']));
                                $pencatat_nama_aman = htmlspecialchars($row['nama_guru'] ?? 'Staf IT Si Mantap', ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr>
                                <td style="font-weight: 700; color: #64748b; text-align: center;"><?php echo $riwayat_num++; ?></td>
                                <td><strong style="color: var(--mantap-blue-dark);"><?php echo formatTanggal($row['tanggal_kunjungan']); ?></strong></td>
                                <td style="text-align: left; padding-left: 10px; font-weight: 600; color: #475569;"><?php echo htmlspecialchars($row['nama_guru'] ?? 'Administrator'); ?></td>
                                <td style="text-align: left; padding-left: 10px; line-height: 1.4;"><?php echo nl2br(htmlspecialchars($row['catatan_guru'])); ?></td>
                                <td style="text-align: left; padding-left: 10px; line-height: 1.4; color: #475569; font-style: italic;"><?php echo $kritik_saran_output; ?></td>
                                <td>
                                    <?php if (!empty($row['bukti_foto'])): ?>
                                        <?php $foto_path_url = '../uploads/monitoring/' . htmlspecialchars($row['bukti_foto']); ?>
                                        <img src="<?php echo $foto_path_url; ?>" class="img-monitoring-thumb" alt="Bukti Foto Kunjungan" onclick="openFotoLightbox('<?php echo $foto_path_url; ?>', '<?php echo $pencatat_nama_aman; ?>')">
                                    <?php else: ?>
                                        <span class="text-muted font-monospace small" style="opacity: 0.6; font-style: italic;">Kosong</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="dashboard-btn-group">
                                        <a href="lokasi-siswa-detail.php?lokasi_id=<?php echo $lokasi_id; ?>&print=1&kunj_id=<?php echo $row['kunjungan_id']; ?>" target="_blank" class="btn-aksi-link btn-print-inline" title="Cetak Kunjungan Ini">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <?php if ($has_permission && $guru_id_pencatat > 0): ?>
                                            <a href="monitoring-edit.php?kunjungan_id=<?php echo $row['kunjungan_id']; ?>" class="btn-aksi-link btn-edit-inline" title="Edit Lembar Kunjungan">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="javascript:void(0);" onclick="konfirmasiHapusMon('<?php echo $row['kunjungan_id']; ?>')" class="btn-aksi-link btn-delete-inline" title="Hapus Permanen">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size: 11px; font-style: italic; color: #94a3b8; margin-left:4px;"><i class="fas fa-lock"></i></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #64748b; font-style: italic; padding: 25px;">Belum ada berkas lembar riwayat rekam kunjungan monitoring yang dicatat untuk mitra industri ini.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="fotoLightbox" class="foto-modal">
    <div class="foto-modal-content">
        <div class="foto-modal-header">
            <h5 style="margin:0; font-weight:700; color:var(--mantap-blue-dark); font-size:1.1rem;"><i class="fas fa-camera text-primary" style="margin-right: 6px;"></i>Pratinjau Foto Kunjungan</h5>
            <button class="foto-modal-close" onclick="closeFotoLightbox()">&times;</button>
        </div>
        <div style="text-align: center; padding: 20px; box-sizing: border-box;">
            <img id="lightboxFotoImg" src="" alt="Bukti Lapangan" style="max-width: 100%; height: auto; border-radius: 8px; border: 1px solid #cbd5e1; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <div style="margin-top: 15px; padding: 10px; background: #f8fafc; border-radius: 6px; border: 1px solid #cbd5e1; font-weight: 700; color: var(--mantap-blue-dark); text-transform: uppercase; font-size: 12.5px;" id="lightboxFotoName"></div>
        </div>
    </div>
</div>

<?php include 'panel/footer.php'; ?>

<script>
    // FUNGSI LIGHTBOX
    function openFotoLightbox(imgUrl, guruName) {
        const modal = document.getElementById('fotoLightbox');
        document.getElementById('lightboxFotoImg').src = imgUrl;
        document.getElementById('lightboxFotoName').innerHTML = `<i class="fas fa-user-edit opacity-50 me-1"></i> Dicatat Oleh: ${guruName}`;
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
    
    function closeFotoLightbox() {
        document.getElementById('fotoLightbox').style.display = 'none';
        document.body.style.overflow = 'auto';
    }
    
    function konfirmasiHapusMon(id) {
        Swal.fire({
            title: 'Hapus Riwayat?',
            text: 'Data kunjungan dan foto lampirannya akan dihapus secara permanen dari server.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#64748b',
            cancelButtonText: 'Batal',
            confirmButtonText: 'Ya, Hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `?lokasi_id=<?php echo $lokasi_id; ?>&action=delete_mon&id=${id}`;
            }
        });
    }

    // ==========================================================
    // LOGIKA KAMERA DAN GPS WATERMARK LEAFLET
    // ==========================================================
    
    let cameraStream = null;
    let mapCam = null;
    let userMarkerCam = null;
    let gpsData = { lat: "-", lng: "-", address: "Mencari alamat lokasi dari satelit..." };

    function toggleModeFoto() {
        const mode = document.querySelector('input[name="metode_foto"]:checked').value;
        const areaUpload = document.getElementById('area_upload');
        const areaKamera = document.getElementById('area_kamera');
        const inputUpload = document.getElementById('bukti_foto');

        if (mode === 'upload') {
            areaUpload.style.display = 'block';
            areaKamera.style.display = 'none';
            inputUpload.required = true;
            stopCamera();
        } else {
            areaUpload.style.display = 'none';
            areaKamera.style.display = 'block';
            inputUpload.required = false;
        }
    }

    document.getElementById('btnStartCamera').addEventListener('click', async function() {
        const video = document.getElementById('videoCam');
        const sysBox = document.getElementById('cameraSystemBox');
        const btnCapture = document.getElementById('btnCapture');
        const preview = document.getElementById('previewCam');
        const btnRetake = document.getElementById('btnRetake');
        const statusKamera = document.getElementById('status-kamera');
        const statusKameraLabel = document.getElementById('label-kamera');
        
        sysBox.style.display = 'flex';
        preview.style.display = 'none';
        btnRetake.style.display = 'none';
        this.style.display = 'none'; 

        try {
            cameraStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" }, audio: false });
            video.srcObject = cameraStream;
            video.style.display = 'block';
            btnCapture.style.display = 'inline-flex';
            
            statusKamera.classList.remove('inactive');
            statusKamera.classList.add('active');
            statusKameraLabel.innerText = "KAMERA: SIAP";

            // Inisialisasi Map Leaflet
            if (!mapCam) {
                mapCam = L.map('map-kamera').setView([-2.548926, 118.0148634], 5); // Default Indonesia
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(mapCam);
            }
            // Penting agar Leaflet tidak glitch jika di-load dari display:none
            setTimeout(() => { mapCam.invalidateSize(); }, 300);

            // Mulai ambil GPS
            getGPSLocation();
        } catch (err) {
            Swal.fire('Error Kamera', 'Gagal mengakses kamera perangkat Anda. Pastikan izin telah diberikan.', 'error');
            this.style.display = 'inline-flex';
            sysBox.style.display = 'none';
            statusKameraLabel.innerText = "KAMERA: ERROR";
        }
    });

    function getGPSLocation() {
        const statusText = document.getElementById('gps_status_text');
        const statusGps = document.getElementById('status-gps');
        const labelGps = document.getElementById('label-gps');

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                async (pos) => {
                    let lat = pos.coords.latitude;
                    let lng = pos.coords.longitude;
                    gpsData.lat = lat.toFixed(6);
                    gpsData.lng = lng.toFixed(6);
                    
                    statusText.innerHTML = `<i class="fas fa-location-dot text-success"></i> Mengambil alamat Reverse Geocoding...`;
                    
                    // Update Peta Live
                    if (userMarkerCam) mapCam.removeLayer(userMarkerCam);
                    userMarkerCam = L.marker([lat, lng]).addTo(mapCam);
                    mapCam.setView([lat, lng], 16);
                    
                    try {
                        let response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`);
                        let data = await response.json();
                        if(data && data.display_name) {
                            gpsData.address = data.display_name;
                        } else {
                            gpsData.address = "Alamat tidak terdeteksi oleh satelit.";
                        }
                    } catch (e) {
                        gpsData.address = "Sistem gagal memuat alamat satelit.";
                    }
                    
                    statusText.innerHTML = `<i class="fas fa-check-circle text-success"></i> Titik GPS dan Alamat Terkunci.`;
                    statusGps.classList.remove('inactive');
                    statusGps.classList.add('active');
                    labelGps.innerText = "GPS: SIAP";
                },
                (err) => {
                    statusText.innerHTML = `<i class="fas fa-exclamation-circle text-danger"></i> GPS Error / Izin Ditolak!`;
                    gpsData.address = "Izin lokasi GPS ditolak oleh perangkat.";
                    labelGps.innerText = "GPS: DITOLAK";
                },
                { enableHighAccuracy: true }
            );
        } else {
            statusText.innerHTML = `<i class="fas fa-exclamation-circle text-danger"></i> GPS tidak didukung.`;
            labelGps.innerText = "GPS: ERROR";
        }
    }

    document.getElementById('btnCapture').addEventListener('click', function() {
        const video = document.getElementById('videoCam');
        const canvas = document.getElementById('canvasCam');
        const preview = document.getElementById('previewCam');
        const hiddenBase64 = document.getElementById('foto_base64');
        const btnRetake = document.getElementById('btnRetake');

        // Setup dimensi canvas
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');

        // 1. Gambar frame video asli
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        // 2. Gambar Overlay Transparan Premium (Gaya GPS Map Camera)
        let rectHeight = 150; // Tinggi kotak overlay
        ctx.fillStyle = "rgba(0, 0, 0, 0.65)";
        ctx.fillRect(0, canvas.height - rectHeight, canvas.width, rectHeight);

        // Garis Aksen Hijau di atas kotak
        ctx.fillStyle = "#22c55e"; 
        ctx.fillRect(0, canvas.height - rectHeight, canvas.width, 5);

        // 3. Persiapkan Teks Watermark
        ctx.fillStyle = "white";
        ctx.textAlign = "left";
        
        // --- NAMA LOKASI (BARIS 1) ---
        ctx.font = "bold 22px Arial, sans-serif";
        ctx.fillText("📍 Monitoring PKL - " + "<?php echo addslashes($lokasi_info['nama_lokasi']); ?>", 20, canvas.height - 110);
        
        // --- ALAMAT WRAPPING (BARIS 2 & 3) ---
        ctx.font = "16px Arial, sans-serif";
        ctx.fillStyle = "#e2e8f0";
        
        let maxChars = 75; 
        let addr1 = gpsData.address.substring(0, maxChars);
        let addr2 = gpsData.address.substring(maxChars, maxChars*2);
        if(gpsData.address.length > maxChars && addr1.charAt(addr1.length-1) !== ' ') addr1 += "-";
        
        ctx.fillText(addr1, 20, canvas.height - 80);
        if(addr2) {
            ctx.fillText(addr2 + (gpsData.address.length > maxChars*2 ? "..." : ""), 20, canvas.height - 58);
        }

        // --- LAT/LONG & WAKTU INDO (BARIS 4 BAWAH) ---
        ctx.font = "bold 15px Arial, sans-serif";
        ctx.fillStyle = "#fff";
        
        const days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
        const months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'];
        let d = new Date();
        let timeStr = `${days[d.getDay()]}, ${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()} ${d.getHours().toString().padStart(2,'0')}:${d.getMinutes().toString().padStart(2,'0')} WIB`;
        
        ctx.fillText(`🧭 Lat: ${gpsData.lat}  Long: ${gpsData.lng}   |   📅 ${timeStr}`, 20, canvas.height - 25);

        // Convert ke base64 gambar kualitas tinggi
        const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
        hiddenBase64.value = dataUrl;
        
        // Tampilkan hasil di UI
        video.style.display = 'none';
        preview.src = dataUrl;
        preview.style.display = 'block';
        
        this.style.display = 'none';
        btnRetake.style.display = 'inline-flex';
        
        if(cameraStream) {
            cameraStream.getTracks().forEach(track => track.stop());
        }
    });

    document.getElementById('btnRetake').addEventListener('click', async function() {
        this.style.display = 'none';
        document.getElementById('foto_base64').value = "";
        
        try {
            cameraStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" }, audio: false });
            document.getElementById('videoCam').srcObject = cameraStream;
            document.getElementById('videoCam').style.display = 'block';
            document.getElementById('previewCam').style.display = 'none';
            document.getElementById('btnCapture').style.display = 'inline-flex';
        } catch (err) {
            Swal.fire('Error', 'Gagal memulai ulang kamera.', 'error');
        }
    });

    function stopCamera() {
        if(cameraStream) {
            cameraStream.getTracks().forEach(track => track.stop());
        }
        document.getElementById('cameraSystemBox').style.display = 'none';
        document.getElementById('btnStartCamera').style.display = 'inline-flex';
        document.getElementById('btnCapture').style.display = 'none';
        document.getElementById('btnRetake').style.display = 'none';
        document.getElementById('foto_base64').value = "";
        
        document.getElementById('status-kamera').classList.replace('active', 'inactive');
        document.getElementById('label-kamera').innerText = "KAMERA: OFF";
    }

    // Submit Validation Form
    document.getElementById('formMonitoring').addEventListener('submit', function(e) {
        const mode = document.querySelector('input[name="metode_foto"]:checked').value;
        const btnSubmit = document.getElementById('btnFinalSubmit');
        
        if(mode === 'kamera') {
            const base64Input = document.getElementById('foto_base64').value;
            if(base64Input === '') {
                e.preventDefault();
                Swal.fire('Foto Kosong', 'Anda memilih mode kamera, tetapi belum menekan tombol "Ambil Foto".', 'warning');
                return;
            }
        }
        // Cegah klik ganda
        btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> MEMPROSES...';
        btnSubmit.style.pointerEvents = 'none';
    });

    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('fotoLightbox');
        if (modal) { 
            modal.addEventListener('click', function(event) { if (event.target === modal) closeFotoLightbox(); }); 
        }
        document.addEventListener('keydown', function(event) { if (event.key === 'Escape' && modal.style.display === 'block') closeFotoLightbox(); });
    });
</script>
</body>
</html>