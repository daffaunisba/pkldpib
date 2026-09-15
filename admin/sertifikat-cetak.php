<?php
// admin/sertifikat-cetak.php
// Halaman Master untuk mencetak Halaman Depan dan Belakang Sertifikat.

include 'auth-check.php';
include '../config/db-koneksi.php';

$siswa_id = isset($_GET['siswa_id']) ? (int)$_GET['siswa_id'] : 0;

if ($siswa_id === 0) {
    die("ID Siswa tidak valid untuk pencetakan sertifikat.");
}

// 1. Set Flag agar template tahu sedang di-include oleh master cetak
$_SESSION['IS_MASTER_CETAK'] = true;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sertifikat PKL - Siswa ID: <?php echo $siswa_id; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        /* CSS untuk Pengaturan Kertas A4 Landscape */
        @page {
            size: 297mm 210mm; /* A4 Landscape */
            margin: 0;
        }
        
        /* Menggunakan Class Spesifik .master-body agar tidak terganggu style include */
        body.master-body { 
            margin: 0; 
            padding: 0; 
            background: #f1f5f9 !important; 
            font-family: 'Poppins', sans-serif;
            display: block !important; /* Memaksa tampilan menumpuk ke bawah di layar monitor */
            min-height: auto !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* Control Bar Bar Atas Premium (Aesthetic Si Mantap) */
        .print-control-bar {
            background: #ffffff;
            padding: 12px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #cbd5e1;
            font-family: 'Poppins', sans-serif;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            position: relative;
            z-index: 9999;
        }
        .control-title { font-weight: 600; color: #334155; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; }
        .btn-group { display: flex; gap: 10px; }
        .btn-control { padding: 7px 16px; border-radius: 6px; font-weight: 600; font-size: 13px; cursor: pointer; border: 1px solid #cbd5e1; text-decoration: none; font-family: 'Poppins'; display: inline-flex; align-items: center; gap: 6px; }
        .btn-back { background: #ffffff; color: #475569; }
        .btn-back:hover { background: #f8fafc; }
        .btn-print { background: #1e3a8a; color: #ffffff; border: none; }
        .btn-print:hover { background: #1e40af; }

        /* Pembungkus Halaman untuk Efek Lembar Kertas Realistis */
        .sertifikat-page-wrapper {
            width: 297mm; /* Sesuaikan dengan A4 */
            height: 210mm; /* Sesuaikan dengan A4 */
            box-sizing: border-box;
            margin: 30px auto;
            background: #ffffff;
            position: relative;
            overflow: hidden;
        }

        /* =====================================================================
        SINKRONISASI & OVERRIDE STYLE UNTUK TEMPLATE YANG DI-INCLUDE
        ===================================================================== */
        /* 1. Sembunyikan tombol cetak bawaan dari file depan & belakang agar tidak double */
        .sertifikat-page-wrapper .cetak-info { 
            display: none !important; 
        }
        
        /* 2. Paksa kanvas anak memenuhi 100% kanvas master (Mencegah penyusutan/resize otomatis) */
        .sertifikat-page-wrapper .sertifikat-wrapper {
            box-shadow: none !important;
            margin: 0 !important;
            width: 100% !important;
            height: 100% !important;
        }

        /* 3. PAKSA TULISAN "SERTIFIKAT" PROPORSIONAL DARI MASTER */
        .sertifikat-page-wrapper .title {
            font-size: 85px !important;      /* Diperkecil dari 110px agar pas */
            letter-spacing: 15px !important; /* Disesuaikan agar tidak terlalu renggang */
            line-height: 1 !important;
            padding-left: 15px !important;   /* Mengimbangi letter-spacing agar rata tengah */
        }

        /* Aturan Paksa Ganti Halaman saat Print */
        .page-break-after {
            page-break-after: always;
        }

        /* Pengaturan Khusus Media Cetak Printer / PDF */
        @media print {
            .no-print { display: none !important; }
            body.master-body { background: #ffffff !important; background-color: #ffffff !important; }
            .sertifikat-page-wrapper {
                margin: 0 !important;
                border: none !important;
                box-shadow: none !important;
                page-break-inside: avoid;
            }
        }

        /* Tampilan Presentasi Layar Desktop Monitor */
        @media screen {
            .sertifikat-page-wrapper {
                box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.1), 0 8px 10px -6px rgba(15, 23, 42, 0.1);
                border: 1px solid #cbd5e1;
            }
        }
    </style>
</head>
<body class="master-body">

    <div class="print-control-bar no-print">
        <span class="control-title">
            <i class="fas fa-certificate" style="color: #1e3a8a;"></i> Pratinjau Cetak Dokumen Sertifikat PKL Resmi (A4 Landscape)
        </span>
        <div class="btn-group">
            <a href="sertifikat.php" class="btn-control btn-back">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
            <button onclick="window.print()" class="btn-control btn-print">
                <i class="fas fa-print"></i> Cetak Berkas (2 Halaman)
            </button>
        </div>
    </div>

    <div class="sertifikat-page-wrapper page-break-after">
        <?php include 'sertifikat-cetak-depan.php'; ?>
    </div>

    <div class="sertifikat-page-wrapper">
        <?php include 'sertifikat-cetak-belakang.php'; ?>
    </div>

    <?php 
    // Hapus flag penanda master cetak setelah selesai memuat berkas
    unset($_SESSION['IS_MASTER_CETAK']); 
    ?>

</body>
</html>