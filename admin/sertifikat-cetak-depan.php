<?php
// admin/sertifikat-cetak-depan.php
// Mencetak halaman depan sertifikat PKL.

include 'auth-check.php';
include '../config/db-koneksi.php';

$siswa_id = isset($_GET['siswa_id']) ? (int)$_GET['siswa_id'] : 0;

if ($siswa_id === 0) {
    die("ID Siswa tidak valid untuk pencetakan sertifikat.");
}

// ---------------------------------------------------------------------
// FUNGSI PERHITUNGAN JAM KERJA DINAMIS
// ---------------------------------------------------------------------
function calculateTotalHours($koneksi, $tgl_mulai, $tgl_akhir) {
    if (empty($tgl_mulai) || empty($tgl_akhir)) return 0;
    
    $hari_kerja_map = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    $jam_kerja_harian = 9; 
    
    $start = new DateTime($tgl_mulai);
    $end = new DateTime($tgl_akhir);
    $end->modify('+1 day'); 
    
    $interval = DateInterval::createFromDateString('1 day');
    $period = new DatePeriod($start, $interval, $end);
    
    $total_jam = 0;
    
    foreach ($period as $dt) {
        $hari_nama = $dt->format('l');
        if (in_array($hari_nama, $hari_kerja_map)) {
            $total_jam += $jam_kerja_harian;
        }
    }
    return $total_jam;
}

// ---------------------------------------------------------------------
// FUNGSI PAKSA TITLE CASE (HURUF DEPAN BESAR) DARI PHP
// ---------------------------------------------------------------------
function textTitleCase($str) {
    if (empty($str)) return '';
    // Kecilkan semua dulu, baru besarkan huruf depannya
    $str = ucwords(strtolower(trim($str)));
    // Pengecualian agar singkatan badan usaha tetap Kapital Murni
    $str = str_replace(
        ['Pt.', 'Pt ', 'Cv.', 'Cv ', 'Ud.', 'Ud '], 
        ['PT.', 'PT ', 'CV.', 'CV ', 'UD.', 'UD '], 
        $str
    );
    return $str;
}

// 1. Ambil Data Siswa
$query_siswa = "
    SELECT 
        p.nama, p.kelas, p.nisn, 
        l.nama_lokasi, l.alamat AS alamat_lokasi,
        pr.tgl_mulai, pr.tgl_akhir,
        s.nomor_sertifikat, s.tanggal_terbit, s.nama_ttd_dudi, s.jabatan_ttd_dudi, s.nip_ttd_dudi, s.logo_dudi
    FROM peserta_didik p
    LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
    LEFT JOIN periode_pkl pr ON p.periode_id = pr.periode_id
    LEFT JOIN sertifikat_terbit s ON p.id = s.siswa_id
    WHERE p.id = ?
";

$stmt_siswa = $koneksi->prepare($query_siswa);

if ($stmt_siswa === FALSE) {
     die("<div class='cetak-info' style='background: #ffe3e3; border: 1px solid red; padding: 20px;'>FATAL ERROR SQL: Gagal menyiapkan query. Pastikan semua tabel (`lokasi_pkl`, `periode_pkl`, `sertifikat_terbit`) sudah ada dan nama kolomnya (`alamat`, `tgl_mulai`, `tanggal_terbit`, `logo_dudi` dll) benar. Error DB: " . $koneksi->error . "</div>");
}

$stmt_siswa->bind_param("i", $siswa_id);
$stmt_siswa->execute();
$siswa_info = $stmt_siswa->get_result()->fetch_assoc();
$stmt_siswa->close();

if (!$siswa_info) {
    die("Data siswa tidak ditemukan.");
}

// 2. Ambil Konfigurasi TTD SEKOLAH (Default)
$config_sekolah = $koneksi->query("SELECT * FROM sertifikat_config WHERE id = 1")->fetch_assoc();
$upload_dir = '../uploads/profiles/'; 
$nomor_sertifikat = $siswa_info['nomor_sertifikat'] ?? 'SMK-1BLITAR/PKL/XXX/2026'; 

// 3. LOGIKA SINKRONISASI TTD & PERBAIKAN CAPSLOCK
$nama_penanda_tangan = $siswa_info['nama_ttd_dudi'] ?? $config_sekolah['nama_penanda_tangan'];
$nip_penanda_tangan = $siswa_info['nip_ttd_dudi'] ?? $config_sekolah['nip_penanda_tangan'];

// Terapkan fungsi Title Case pada Jabatan TTD dan Nama Lokasi
$jabatan_raw = $siswa_info['jabatan_ttd_dudi'] ?? $config_sekolah['jabatan_penanda_tangan'];
$jabatan_penanda_tangan = textTitleCase($jabatan_raw);

$nama_lokasi_raw = $siswa_info['nama_lokasi'] ?? '[Lokasi PKL]';
$nama_lokasi_pkl = textTitleCase($nama_lokasi_raw);

$alamat_lokasi_pkl = $siswa_info['alamat_lokasi'] ?? '[Alamat Lokasi]'; 

// Cek Logo DU/DI
$logo_dudi = $siswa_info['logo_dudi'] ?? null;
$show_logo = (!empty($logo_dudi) && file_exists($logo_dudi));

// Data Periode
$tgl_mulai = $siswa_info['tgl_mulai'] ?? null;
$tgl_akhir = $siswa_info['tgl_akhir'] ?? null;

// Hitung total jam
if ($tgl_mulai && $tgl_akhir) {
    $total_jam = calculateTotalHours($koneksi, $tgl_mulai, $tgl_akhir);
} else {
    $total_jam = 'XXX';
}

// Format Tanggal Indonesia
function formatTanggalIndo($tanggal) {
    if (empty($tanggal)) return '[Tanggal]';
    $timestamp = strtotime($tanggal);
    $bulan_indo = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $tgl = date('d', $timestamp);
    $bln = $bulan_indo[(int)date('m', $timestamp)];
    $thn = date('Y', $timestamp);
    
    return "{$tgl} {$bln} {$thn}"; 
}

$tgl_mulai_indo = $tgl_mulai ? formatTanggalIndo($tgl_mulai) : '[Tgl Mulai]';
$tgl_akhir_indo = $tgl_akhir ? formatTanggalIndo($tgl_akhir) : '[Tgl Akhir]';

// SINKRONISASI TANGGAL TERBIT DENGAN YANG ADA DI DATABASE
$tanggal_terbit_db = !empty($siswa_info['tanggal_terbit']) ? $siswa_info['tanggal_terbit'] : date('Y-m-d');
$tanggal_lulus_indo = formatTanggalIndo($tanggal_terbit_db); 
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sertifikat PKL - <?php echo htmlspecialchars($siswa_info['nama']); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --dark-blue: #0f172a;   
            --main-blue: #1e3a8a;   
            --accent-blue: #3b82f6; 
        }

        /* Set ukuran kertas A4 Landscape */
        @page {
            size: 297mm 210mm; 
            margin: 0;
        }

        body { 
            margin: 0; 
            padding: 0; 
            background: #e2e8f0; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            font-family: 'Times New Roman', Times, serif;
        }

        .cetak-info { 
            position: fixed; top: 20px; left: 50%; transform: translateX(-50%);
            background: white; padding: 15px 30px; border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1); z-index: 1000; text-align: center;
            font-family: 'Times New Roman', Times, serif;
        }
        .cetak-info button { background: var(--main-blue); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: bold; cursor: pointer; font-family: 'Times New Roman', Times, serif; font-size: 15px;}
        .cetak-info a { color: #64748b; text-decoration: none; font-weight: 600; padding: 10px; font-size: 15px;}

        /* AREA KERTAS SERTIFIKAT A4 */
        .sertifikat-wrapper {
            width: 297mm; 
            height: 210mm; 
            background-color: #ffffff;
            position: relative;
            box-sizing: border-box;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            overflow: hidden;
            font-family: 'Times New Roman', Times, serif;
        }

        /* BINGKAI MEWAH (DOUBLE BORDER) */
        .border-outer {
            position: absolute;
            top: 8mm; bottom: 8mm; left: 8mm; right: 8mm;
            border: 4px solid var(--dark-blue);
            z-index: 2;
            pointer-events: none;
        }
        .border-inner {
            position: absolute;
            top: 10mm; bottom: 10mm; left: 10mm; right: 10mm;
            border: 1px solid var(--accent-blue);
            z-index: 2;
            pointer-events: none;
        }

        /* AKSEN SUDUT (CORNER ORNAMENTS) */
        .corner-tl {
            position: absolute; top: 0; left: 0;
            width: 150px; height: 150px;
            background: linear-gradient(135deg, var(--main-blue) 0%, var(--accent-blue) 100%);
            clip-path: polygon(0 0, 100% 0, 0 100%);
            z-index: 1;
        }
        .corner-br {
            position: absolute; bottom: 0; right: 0;
            width: 200px; height: 200px;
            background: linear-gradient(135deg, var(--accent-blue) 0%, var(--main-blue) 100%);
            clip-path: polygon(100% 0, 100% 100%, 0 100%);
            z-index: 1;
        }
        .bg-pattern {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            width: 60%; height: 60%;
            background-image: radial-gradient(var(--accent-blue) 1px, transparent 1px);
            background-size: 30px 30px;
            opacity: 0.05;
            z-index: 0;
            border-radius: 50%;
        }

        /* KONTEN UTAMA */
        .sertifikat-content {
            position: relative;
            z-index: 5;
            width: 100%; height: 100%;
            padding: 15mm 20mm 15mm 20mm;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            text-align: center;
        }

        /* LOGO PERUSAHAAN (POJOK KANAN ATAS) */
        .logo-dudi {
            position: absolute;
            top: 15mm; 
            right: 20mm; 
            width: 35mm;
            height: 35mm;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10;
        }
        .logo-dudi img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        /* HEADER */
        .header-section { margin-bottom: 25px; margin-top: 10px; }
        
        .title {
            font-family: 'Times New Roman', Times, serif;
            font-size: 60px; 
            font-weight: 900;
            color: var(--main-blue);
            letter-spacing: 16px; 
            text-transform: uppercase;
            margin: 0;
            line-height: 1;
            padding-left: 16px; 
        }
        
        .cert-number {
            font-size: 17px; 
            font-weight: 600;
            color: #64748b; 
            margin: 8px 0 35px 0; 
        }

        .intro-text {
            font-size: 18px; 
            color: #334155;
            margin-bottom: 20px; 
        }
        .company-name {
            font-size: 20px; 
            font-weight: 700;
            color: var(--main-blue);
            /* Dihapus agar PHP yang memformatnya */
        }

        /* DATA SISWA (POLOS, RAPAT) */
        .student-data-box {
            margin: 0 auto 25px auto;
            width: 80%;
            text-align: left;
        }
        .student-table {
            width: 100%;
            border-collapse: collapse;
            margin-left: 20px; 
            line-height: 1.3; 
        }
        .student-table td {
            padding: 2px 0; 
            font-size: 18px; 
            color: var(--dark-blue);
        }
        .student-table td.label {
            font-weight: 700;
            width: 280px; 
        }
        .student-table td.colon {
            width: 20px;
            font-weight: 700;
        }
        .student-table td.value {
            font-weight: 600;
            text-transform: capitalize;
        }
        .student-table td.value.uppercase { text-transform: uppercase; }

        /* BODY DESKRIPSI */
        .body-text {
            font-size: 16px; 
            line-height: 1.6;
            color: #334155;
            width: 85%;
            margin: 0 auto 45px auto; 
        }
        .body-text strong { color: var(--dark-blue); }

        /* =========================================================
           FOOTER (FOTO & TANDA TANGAN) DI TENGAH BERDAMPINGAN
           ========================================================= */
        .footer-section {
            margin-top: auto;
            padding-top: 20px; 
            display: flex;
            justify-content: center; 
            align-items: flex-end;   
            gap: 40px; 
            margin-bottom: 25mm; 
        }

        .photo-box {
            width: 30mm; 
            height: 40mm; 
            border: -5px dashed #94a3b8;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            color: #94a3b8;
            font-weight: 600;
            font-size: 14px; 
            border-radius: 20px;
            flex-shrink: 0;
        }

        .signature-box {
            text-align: left; 
            min-width: 280px; 
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            height: 40mm; 
        }
        .signature-date {
            font-size: 16px; 
            color: #334155;
            margin-bottom: 5px;
        }
        .signature-role {
            font-size: 16px; 
            font-weight: 600;
            color: var(--dark-blue);
            margin-bottom: 55px; 
        }
        .signature-name {
            font-size: 17px; 
            font-weight: 700;
            color: var(--dark-blue);
            text-decoration: underline;
            margin: 0;
        }
        .signature-nip {
            font-size: 15px; 
            color: #64748b;
            margin: 3px 0 0 0;
        }

        @media print {
            body { background: white; }
            .cetak-info { display: none !important; }
            .sertifikat-wrapper { box-shadow: none; }
        }
    </style>
</head>
<body>

<div class="cetak-info">
    <p style="margin: 0 0 10px 0; font-size: 14px; color: #64748b;">Gunakan orientasi <strong>Landscape (A4)</strong> & aktifkan <strong>Background Graphics</strong> saat mencetak.</p>
    <button onclick="window.print()"><i class="fas fa-print me-2"></i> Cetak Dokumen</button>
    <a href="sertifikat.php"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
</div>

<div class="sertifikat-wrapper">
    <div class="border-outer"></div>
    <div class="border-inner"></div>
    <div class="corner-tl"></div>
    <div class="corner-br"></div>
    <div class="bg-pattern"></div>

    <div class="sertifikat-content">
        
        <?php if ($show_logo): ?>
        <div class="logo-dudi">
            <img src="<?php echo htmlspecialchars($logo_dudi); ?>" alt="Logo DU/DI">
        </div>
        <?php endif; ?>
        
        <div class="header-section">
            <h1 class="title">SERTIFIKAT</h1>
            <div class="cert-number">Nomor: <?php echo htmlspecialchars($siswa_info['nomor_sertifikat'] ?? $nomor_sertifikat); ?></div>
            
            <div class="intro-text">
                Pimpinan <span class="company-name"><?php echo htmlspecialchars($nama_lokasi_pkl); ?></span> menerangkan bahwa:
            </div>
        </div>

        <div class="student-data-box">
            <table class="student-table">
                <tr>
                    <td class="label">Nama Lengkap</td>
                    <td class="colon">:</td>
                    <td class="value uppercase"><?php echo htmlspecialchars($siswa_info['nama']); ?></td>
                </tr>
                <tr>
                    <td class="label">Nomor Induk Siswa Nasional</td>
                    <td class="colon">:</td>
                    <td class="value"><?php echo htmlspecialchars($siswa_info['nisn']); ?></td>
                </tr>
                <tr>
                    <td class="label">Kompetensi Keahlian</td>
                    <td class="colon">:</td>
                    <td class="value">Desain Pemodelan dan Informasi Bangunan</td>
                </tr>
                <tr>
                    <td class="label">Asal Sekolah</td>
                    <td class="colon">:</td>
                    <td class="value">SMK Islam 1 Blitar</td>
                </tr>
            </table>
        </div>

        <div class="body-text">
            Telah menyelesaikan kegiatan Praktik Kerja Lapangan (PKL) yang diselenggarakan di <strong><?php echo htmlspecialchars($alamat_lokasi_pkl); ?></strong>. Kegiatan ini berlangsung selama <strong><?php echo $total_jam; ?> Jam Pelajaran</strong>, terhitung mulai tanggal <strong><?php echo $tgl_mulai_indo; ?></strong> sampai dengan <strong><?php echo $tgl_akhir_indo; ?></strong>.<br><br>
            <i>Detail penilaian kompetensi dan aspek sikap selama pelaksanaan tercantum di halaman belakang sertifikat ini.</i>
        </div>

        <div class="footer-section">
            <div class="photo-box">
                Pas Foto<br>3 x 4 cm
            </div>
            
            <div class="signature-box">
                <div class="signature-date">Blitar, <?php echo $tanggal_lulus_indo; ?></div>
                <div class="signature-role"><?php echo htmlspecialchars($jabatan_penanda_tangan); ?></div>
                <div class="signature-name"><?php echo htmlspecialchars($nama_penanda_tangan); ?></div>
                <div class="signature-nip">NIP. <?php echo htmlspecialchars($nip_penanda_tangan); ?></div>
            </div>
        </div>

    </div>
</div>

</body>
</html>