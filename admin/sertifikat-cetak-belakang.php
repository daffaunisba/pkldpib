<?php
// admin/sertifikat-cetak-belakang.php
include 'auth-check.php';
include '../config/db-koneksi.php'; 

$siswa_id = isset($_GET['siswa_id']) ? (int)$_GET['siswa_id'] : 0;
if ($siswa_id === 0) { die("ID Siswa tidak ditemukan."); }

// 1. QUERY DATA SISWA
$query_siswa = "SELECT p.nama, p.nisn, s.nomor_sertifikat FROM peserta_didik p 
                LEFT JOIN sertifikat_terbit s ON p.id = s.siswa_id WHERE p.id = ?";
$stmt_siswa = $koneksi->prepare($query_siswa);
$stmt_siswa->bind_param("i", $siswa_id);
$stmt_siswa->execute();
$siswa_info = $stmt_siswa->get_result()->fetch_assoc();
$stmt_siswa->close();

// 2. QUERY DATA NILAI
$query_nilai = "SELECT pa.nama_aspek, pk.nama_komponen, ns.nilai_angka, ns.predikat
                FROM penilaian_komponen pk
                JOIN penilaian_aspek pa ON pk.aspek_id = pa.aspek_id
                LEFT JOIN nilai_siswa_pkl ns ON pk.komponen_id = ns.komponen_id 
                WHERE ns.siswa_id = ? ORDER BY pa.urutan ASC, pk.urutan ASC";
$stmt_komponen = $koneksi->prepare($query_nilai);
$stmt_komponen->bind_param("i", $siswa_id);
$stmt_komponen->execute();
$komponen_data = $stmt_komponen->get_result();
$stmt_komponen->close();

$grouped_components = [];
$total_nilai = 0; $jumlah_komponen = 0;
while ($row = $komponen_data->fetch_assoc()) {
    $grouped_components[$row['nama_aspek']][] = $row;
    $total_nilai += (float)$row['nilai_angka'];
    $jumlah_komponen++;
}
$nilai_rata_rata = ($jumlah_komponen > 0) ? ($total_nilai / $jumlah_komponen) : 0;

function getPredikatRataRata($rata) {
    if ($rata >= 91) return 'Sangat Baik';
    else if ($rata >= 81) return 'Baik';
    else if ($rata >= 71) return 'Cukup';
    else return 'Kurang';
}

function romanNumerals($num) {
    $n = intval($num); $res = '';
    $roman = ['M'=>1000,'CM'=>900,'D'=>500,'CD'=>400,'C'=>100,'XC'=>90,'L'=>50,'XL'=>40,'X'=>10,'IX'=>9,'V'=>5,'IV'=>4,'I'=>1];
    foreach ($roman as $key => $value) { $matches = intval($n / $value); $res .= str_repeat($key, $matches); $n = $n % $value; }
    return $res;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Daftar Nilai - <?php echo htmlspecialchars($siswa_info['nama']); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --dark-blue: #0f172a;   
            --main-blue: #1e3a8a;   
            --accent-blue: #3b82f6; 
        }

        /* Set ukuran kertas A4 Landscape */
        @page {
            size: 297mm 210mm; /* Standar A4 Landscape */
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

        /* Tombol melayang tidak ikut dicetak */
        .cetak-info { 
            position: fixed; top: 20px; left: 50%; transform: translateX(-50%);
            background: white; padding: 15px 30px; border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1); z-index: 1000; text-align: center;
            font-family: 'Times New Roman', Times, serif;
        }
        .cetak-info button { background: var(--main-blue); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: bold; cursor: pointer; font-family: 'Times New Roman', Times, serif; font-size: 15px; margin: 0 5px;}
        .cetak-info button.btn-close { background: #f1f5f9; color: #64748b; }
        .cetak-info button:hover { opacity: 0.9; }

        /* AREA KERTAS SERTIFIKAT A4 */
        .sertifikat-wrapper {
            width: 297mm; 
            height: 210mm; 
            background-color: #ffffff;
            position: relative;
            box-sizing: border-box;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            overflow: hidden;
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

        /* KONTEN UTAMA - MENGGUNAKAN TIMES NEW ROMAN */
        .sertifikat-content {
            position: relative;
            z-index: 5;
            width: 100%; height: 100%;
            padding: 13mm 20mm 10mm 20mm;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            font-family: 'Times New Roman', Times, serif;
        }

        /* HEADER */
        .header-section { text-align: center; margin-bottom: 10px; }
        .title {
            font-family: 'Times New Roman', Times, serif;
            font-size: 28px;
            font-weight: 900;
            color: var(--main-blue);
            letter-spacing: 4px;
            text-transform: uppercase;
            margin: 0 0 4px 0;
            line-height: 1;
        }
        .cert-number {
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            letter-spacing: 1px;
            margin: 0;
        }

        /* ============================================================ */
        /* DATA SISWA - DIPERSEMPIT (LEBIH RAPAT)                       */
        /* ============================================================ */
        .student-info-box {
            margin: 0 auto 8px auto; 
            width: 75%; 
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            line-height: 1.1; 
        }
        .info-table td {
            padding: 2px 0; 
            font-size: 15px; 
            color: #000;
            text-align: left; 
        }
        .info-table td.label { font-weight: bold; width: 240px; }
        .info-table td.colon { width: 15px; font-weight: bold; }
        .info-table td.value { font-weight: bold; } /* DIHAPUS: text-transform: uppercase */
        .info-table td.value.uppercase { text-transform: uppercase; } /* KHUSUS NAMA SISWA */

        /* ============================================================ */
        /* TABEL NILAI UTAMA (DESAIN POLOS & FORMAL)                    */
        /* ============================================================ */
        .scores-box {
            width: 75%; 
            margin: 0 auto 8px auto; 
            font-family: 'Times New Roman', Times, serif; 
        }
        .scores-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
        }
        .scores-table th {
            background-color: #ffffff;
            color: #000000;
            padding: 4px 8px; 
            font-size: 15px; 
            font-weight: bold;
            text-transform: uppercase;
            border: 1px solid #000;
        }
        .scores-table td {
            padding: 2px 8px; 
            font-size: 15px; 
            color: #000000;
            border: 1px solid #000;
            line-height: 1.2;
        }
        
        .group-row td { font-weight: bold; }
        
        .average-row td {
            font-weight: bold;
            font-size: 15px; 
            padding: 4px 8px; 
        }

        /* ============================================================ */
        /* TABEL PREDIKAT KETERANGAN                                    */
        /* ============================================================ */
        .legend-box {
            width: 75%; 
            margin: 0 auto; 
            text-align: left;
            font-family: 'Times New Roman', Times, serif;
        }
        .legend-title {
            font-size: 14px; 
            font-weight: bold;
            color: #000;
            margin: 0 0 2px 0; 
        }
        .legend-table {
            width: 340px; 
            border-collapse: collapse;
            border: 1px solid #000;
        }
        .legend-table th {
            background-color: #ffffff;
            color: #000;
            padding: 2px 4px; 
            font-size: 13px; 
            font-weight: bold;
            text-align: center;
            border: 1px solid #000;
        }
        .legend-table td {
            padding: 2px 4px; 
            font-size: 13px; 
            text-align: center;
            border: 1px solid #000;
            color: #000;
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
    <button class="btn-close" onclick="window.close()"><i class="fas fa-times me-2"></i> Tutup</button>
</div>

<div class="sertifikat-wrapper">
    <div class="border-outer"></div>
    <div class="border-inner"></div>

    <div class="sertifikat-content">
        
        <div class="header-section">
            <h1 class="title">DAFTAR NILAI</h1>
            <p class="cert-number">Nomor: <?php echo htmlspecialchars($siswa_info['nomor_sertifikat']); ?></p>
        </div>

        <div class="student-info-box">
            <table class="info-table">
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

        <div class="scores-box">
            <table class="scores-table">
                <thead>
                    <tr>
                        <th width="8%">NO</th>
                        <th width="56%">KOMPONEN PENILAIAN</th> 
                        <th width="16%">NILAI</th>
                        <th width="20%">PREDIKAT</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $idx_aspek = 1;
                    foreach ($grouped_components as $aspek => $items): 
                    ?>
                        <tr class="group-row">
                            <td align="center"><?php echo romanNumerals($idx_aspek++); ?></td>
                            <td style="text-align: left; padding-left: 10px !important;"><?php echo htmlspecialchars($aspek); ?></td>
                            <td></td>
                            <td></td>
                        </tr>
                        
                        <?php 
                        $idx_komp = 1;
                        foreach ($items as $item): ?>
                        <tr>
                            <td></td>
                            <td style="text-align: left; padding-left: 25px;"><?php echo $idx_komp++; ?>. &nbsp; <?php echo htmlspecialchars($item['nama_komponen']); ?></td>
                            <td align="center"><?php echo number_format($item['nilai_angka'], 2); ?></td>
                            <td align="center"><?php echo htmlspecialchars($item['predikat']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>

                    <tr class="average-row">
                        <td colspan="2" align="center">NILAI RATA - RATA</td>
                        <td align="center"><?php echo number_format($nilai_rata_rata, 2); ?></td>
                        <td align="center"><?php echo getPredikatRataRata($nilai_rata_rata); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="legend-box">
            <p class="legend-title">Predikat Nilai:</p>
            <table class="legend-table">
                <tr>
                    <th width="30%">NILAI</th>
                    <th width="25%">KATEGORI</th>
                    <th width="45%">KETERANGAN</th>
                </tr>
                <tr><td>91 - 100</td><td>A</td><td>Sangat Baik</td></tr>
                <tr><td>81 - 90</td><td>B</td><td>Baik</td></tr>
                <tr><td>71 - 80</td><td>C</td><td>Cukup</td></tr>
                <tr><td>50 - 70</td><td>D</td><td>Kurang</td></tr>
            </table>
        </div>

    </div>
</div>

</body>
</html>