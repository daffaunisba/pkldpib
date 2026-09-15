<?php
// admin/surat-keluar-otomatis.php
// Halaman Preview Cetak Surat Keluar Resmi

// Menggunakan auth-check untuk proteksi keamanan yang lebih baik
include 'auth-check.php'; 
include '../config/db-koneksi.php'; 

$current_user_id = $_SESSION['user_id'] ?? 0; // Ditambahkan untuk kebutuhan log

$surat_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$data = [];
$is_from_session = isset($_SESSION['preview_surat_keluar']);

// ---------------------------------------------------------------------
// 1. PRIORITAS UTAMA: AMBIL DATA DARI SESSION (Setelah Add Berhasil)
// ---------------------------------------------------------------------
if ($is_from_session) {
    $data = $_SESSION['preview_surat_keluar'];
    // HAPUS SESI setelah diambil
    unset($_SESSION['preview_surat_keluar']);
} 
// ---------------------------------------------------------------------
// 2. PRIORITAS KEDUA: AMBIL DATA DARI DATABASE (Jika ID ada di URL)
// ---------------------------------------------------------------------
elseif ($surat_id > 0) {
    $stmt = $koneksi->prepare("SELECT nomor_surat, tanggal_surat, tujuan_perusahaan, alamat_tujuan, perihal, lampiran, isi_surat FROM surat_keluar_pkl WHERE surat_id = ?");
    $stmt->bind_param("i", $surat_id);
    $stmt->execute();
    $db_result = $stmt->get_result();

    if ($db_result->num_rows > 0) {
        $db_row = $db_result->fetch_assoc();
        $data = [
            'nomor_surat' => $db_row['nomor_surat'],
            'tanggal_surat' => $db_row['tanggal_surat'],
            'tujuan_perusahaan' => $db_row['tujuan_perusahaan'],
            'alamat_tujuan' => $db_row['alamat_tujuan'],
            'perihal' => $db_row['perihal'],
            'isi_surat' => $db_row['isi_surat'],
            'lampiran' => $db_row['lampiran']
        ];
    } else {
        die("<div style='padding: 20px;'>Data surat tidak ditemukan di database.</div>");
    }
    $stmt->close();
} else {
    header("Location: surat-keluar-add.php");
    exit();
}

// --- ASUMSI DAN DEFAULTS ---
$nomor_surat = $data['nomor_surat'] ?? '';
$tanggal_surat = $data['tanggal_surat'] ?? date('Y-m-d');
$tujuan_surat = $data['tujuan_perusahaan'] ?? '[Tujuan Surat]'; 
$alamat_tujuan = $data['alamat_tujuan'] ?? '[Alamat Tujuan]'; 
$perihal = $data['perihal'] ?? '';
$isi_surat = $data['isi_surat'] ?? '';
$lampiran_default = $data['lampiran'] ?? 'Tidak Ada';

// --- Konfigurasi Kop Surat (Statik) ---
$nama_sekolah = "SEKOLAH MENENGAH KEJURUAN ISLAM 1 BLITAR";
$jurusan = "DESAIN PEMODELAN DAN INFORMASI BANGUNAN";
$alamat_sekolah = "Jl. Musi No. 6 Blitar, 66117. Telp 081249464046";
$website = "http://dpib.smkislam1blitar.sch.id | E-mail: dpibsmkislam1blitar@gmail.com";
$jabatan_ttd = "Ketua Jurusan DPIB";
$nama_kaprog = "Mochamad Ade Satria, S.T.";
$nip_kaprog = "1994265653254630";
$ttd_image_path = "../img/ttd.png"; 

// Fungsi untuk format tanggal
function formatTanggal($tanggal) {
    if (empty($tanggal)) return '-';
    setlocale(LC_TIME, 'id_ID.utf8');
    $tanggal_timestamp = strtotime($tanggal);
    
    $bulan_indo = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    
    $hari = date('d', $tanggal_timestamp);
    $bulan = $bulan_indo[(int)date('m', $tanggal_timestamp)]; 
    $tahun = date('Y', $tanggal_timestamp);
    
    return "Blitar, $hari $bulan $tahun"; 
}

$tanggal_surat_indo = formatTanggal($tanggal_surat);

// --- TRIGGER LOG AKTIVITAS (MENCETAK/MENGAKSES SURAT) ---
catatLog($koneksi, $current_user_id, "Mencetak / melihat pratinjau dokumen fisik Surat Keluar: " . $nomor_surat);

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Preview Surat Keluar - <?php echo $nomor_surat; ?></title>
    <link rel="stylesheet" href="../css/style.css"> 
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <style>
        /* CSS KHUSUS TAMPILAN KERTAS A4 & CETAK */
        body { background-color: #f0f2f5; } 

        .surat-wrapper { 
            font-family: 'Times New Roman', Times, serif; 
            font-size: 12pt; 
            line-height: 1.5; 
            background: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            
            /* Dimensi presisi ukuran Kertas A4 */
            width: 21cm; 
            min-height: 29.7cm; 
            margin: 30px auto;
            
            /* Margin surat formal: Kiri 3cm, Atas-Kanan-Bawah 2cm */
            padding: 2cm 2cm 2cm 3cm; 
            box-sizing: border-box; 
        }

        /* =====================================================================
           KOP SURAT
           ===================================================================== */
        .kop { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            border-bottom: 4px double #000; 
            padding-bottom: 10px; 
            margin-top: 0px; 
            margin-bottom: 20px; 
        }
        .kop .logo { 
            width: 90px; 
            height: 90px; 
            object-fit: contain; 
        }
        .kop .info-sekolah { 
            text-align: center; 
            flex-grow: 1; 
        }
        .kop .info-sekolah h2 { 
            margin: 0; 
            line-height: 1.1; 
            font-weight: bold; 
            font-size: 13.5pt; 
            text-transform: uppercase; 
            white-space: nowrap; 
        }
        .kop .info-sekolah h3 { 
            margin: 4px 0; 
            font-size: 12pt; 
            font-weight: bold; 
            text-transform: uppercase; 
        }
        .kop .info-sekolah p { 
            margin: 1px 0; 
            font-size: 9.5pt; 
            font-style: normal; 
        }

        /* =====================================================================
           METADATA SURAT (NOMOR, LAMPIRAN, PERIHAL SEJAJAR DENGAN TANGGAL)
           ===================================================================== */
        .surat-metadata { 
            margin-bottom: 25px; 
            display: flex; 
            justify-content: space-between; 
            align-items: flex-start; /* Kunci agar sejajar di atas */
        }
        .metadata-group { 
            width: 60%; 
            text-align: left; 
        }
        .metadata-group p { 
            margin: 0; 
            padding: 0; 
            line-height: 1.5; 
            display: flex; 
            align-items: flex-start;
        }
        .metadata-group .label-key { 
            display: inline-block; 
            width: 85px; 
            font-weight: bold; 
            text-align: left; 
        }
        .metadata-group .separator { 
            width: 15px; 
            font-weight: bold; 
            text-align: center; 
        }
        .metadata-group .value-content { 
            flex-grow: 1; 
            display: inline-block; 
        }

        .tanggal-surat-wrapper { 
            width: 35%; 
            text-align: right; 
            line-height: 1.5; 
        }
        .tanggal-surat-wrapper p {
            margin: 0; 
            padding: 0;
            line-height: 1.5; /* Disamakan agar presisi sejajar */
        }

        /* =========================================
           ALAMAT TUJUAN
           ========================================= */
        .surat-tujuan { 
            margin-top: 25px; 
            margin-bottom: 25px; 
            line-height: 1.2; 
        }
        .surat-tujuan p { 
            margin: 0; 
            padding: 0;
        } 
        
        /* =========================================
           ISI SURAT & TABEL (SINKRON DENGAN TINYMCE)
           ========================================= */
        .surat-content { 
            text-align: justify; 
            margin-bottom: 40px; 
            line-height: 1.5;
        }
        .surat-content p { 
            margin: 0 0 10px 0; 
        } 
        .surat-content ul, .surat-content ol {
            margin-top: 5px;
            margin-bottom: 10px;
            padding-left: 25px;
        }

        /* Styling Table Polos MENGHORMATI INLINE STYLE (Agar Bisa Rata Tengah / Lebar Bebas) */
        .surat-content table {
            max-width: 100%;
            border-collapse: collapse !important;
            margin-top: 15px !important;
            margin-bottom: 15px !important;
            box-shadow: none !important;
            border-radius: 0 !important; 
            background-color: #ffffff !important; 
            border: 1px solid #000 !important; 
        }
        .surat-content table th, 
        .surat-content table td {
            border: 1px solid #000 !important; 
            padding: 6px 10px !important; 
            vertical-align: middle;
            text-align: left;
            background-color: #ffffff !important; 
        }
        .surat-content table th {
            text-align: center !important; 
            font-weight: bold;
        }
        .surat-content table td p, 
        .surat-content table th p {
            margin: 0 !important;
            padding: 0 !important;
            text-indent: 0 !important;
        }

        /* =========================================
           TANDA TANGAN
           ========================================= */
        .ttd-lokasi { 
            margin-left: auto;
            width: 300px; 
            text-align: left; 
            line-height: 1.4;
            position: relative; 
            padding-top: 20px; 
        }
        .ttd-lokasi p { margin: 5px 0; }
        .ttd-lokasi .nama-penanda { margin-top: 5px; font-weight: bold; text-decoration: underline; }

        .ttd-image {
            width: 240px; 
            height: 160px;
            position: absolute;
            left: 20%; 
            top: -30px; 
            transform: translateX(-50%);
            z-index: 2;
        }
        .ttd-images-container { height: 100px; position: relative; margin-bottom: -15px; }
        
        .action-bar { text-align: center; margin-top: 20px; margin-bottom: 20px; }

        /* =========================================
           KONFIGURASI PRINT DIALOG SUPER KETAT
           ========================================= */
        @media print {
            @page {
                size: A4 portrait;
                margin: 0 !important; 
            }
            body { background: white; margin: 0; padding: 0; }
            .action-bar { display: none !important; }
            
            .surat-wrapper { 
                box-shadow: none !important; 
                margin: 0 !important; 
                width: 21cm !important; 
                height: 29.7cm !important; 
                padding: 2cm 2cm 2cm 3cm !important; 
                box-sizing: border-box !important;
            }
        }
    </style>
</head>
<body>
    <div class="action-bar">
        <button onclick="window.print()"><i class="fas fa-print"></i> Cetak Surat</button>
        <a href="persuratan.php" style="margin-left: 10px;" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali ke Arsip</a>
    </div>

    <div class="surat-wrapper">
        
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

        <div class="surat-metadata">
            <div class="metadata-group">
                <p>
                    <span class="label-key">Nomor</span><span class="separator">:</span> 
                    <span class="value-content"><?php echo htmlspecialchars($nomor_surat); ?></span>
                </p>
                <p>
                    <span class="label-key">Lampiran</span><span class="separator">:</span> 
                    <span class="value-content"><?php echo htmlspecialchars($lampiran_default); ?></span>
                </p>
                <p>
                    <span class="label-key">Perihal</span><span class="separator">:</span> 
                    <span class="value-content"><?php echo htmlspecialchars($perihal); ?></span>
                </p> 
            </div>
            <div class="tanggal-surat-wrapper">
                <p><?php echo htmlspecialchars($tanggal_surat_indo); ?></p> 
            </div>
        </div>

        <div class="surat-tujuan">
            <p>Kepada Yth.</p>
            <p><strong><?php echo htmlspecialchars($tujuan_surat); ?></strong></p>
            <p><?php echo htmlspecialchars($alamat_tujuan); ?></p> 
            <p>di</p>
            <p style="padding-left: 40px;">Tempat</p> 
        </div>

        <div class="surat-content">
            <p>Dengan hormat,</p>
            
            <?php echo $isi_surat; ?>
            
            <p>Demikian surat ini kami sampaikan. Atas perhatian Bapak/Ibu, kami ucapkan terima kasih.</p>
        </div>

        <div class="ttd-lokasi">
            <p><?php echo htmlspecialchars($jabatan_ttd); ?></p>
            
            <div class="ttd-images-container">
                <img src="<?php echo $ttd_image_path; ?>" alt="TTD" class="ttd-image">
            </div>
            
            <div class="nama-penanda">
                <?php echo htmlspecialchars($nama_kaprog); ?>
            </div>
            <p>NIP. <?php echo htmlspecialchars($nip_kaprog); ?></p>
        </div>

    </div>
</body>
</html>