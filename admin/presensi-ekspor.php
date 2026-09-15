<?php
// admin/presensi-ekspor.php
include 'auth-check.php';
include '../config/db-koneksi.php';

// --- Ambil Parameter POST ---
$lokasi_id    = isset($_POST['lokasi_id']) ? $_POST['lokasi_id'] : 'all';
$rentang_tipe = isset($_POST['rentang_tipe']) ? $_POST['rentang_tipe'] : 'harian';
$format_file  = isset($_POST['format_file']) ? $_POST['format_file'] : 'pdf';

// --- Filter Mitra Penempatan Setup ---
$title_mitra = "Semua Lokasi / Mitra Penempatan";
$filter_lokasi_clause = "";
if ($lokasi_id !== 'all') {
    $filter_lokasi_clause = " AND l.lokasi_id = '" . $koneksi->real_escape_string($lokasi_id) . "' ";
    $get_mitra = $koneksi->query("SELECT nama_lokasi FROM lokasi_pkl WHERE lokasi_id = '" . $koneksi->real_escape_string($lokasi_id) . "'");
    if ($get_mitra && $get_mitra->num_rows > 0) {
        $title_mitra = $get_mitra->fetch_assoc()['nama_lokasi'];
    }
}

// --- Bangun Logika Query & Tanggal ---
$query_ekspor = "";
$title_periode = "";

if ($rentang_tipe === 'harian') {
    $tanggal_rekap = isset($_POST['tanggal_rekap']) ? $_POST['tanggal_rekap'] : date('Y-m-d');
    $tgl_aman = $koneksi->real_escape_string($tanggal_rekap);
    $title_periode = date('d F Y', strtotime($tanggal_rekap));
    
    // Logika Harian (menggunakan LEFT JOIN agar yang izin masuk)
    $query_ekspor = "
        SELECT 
            pr.id_presensi, pr.siswa_id, pr.jam_masuk, pr.jam_pulang, pr.keterangan, pr.keterangan2,
            COALESCE(pr.tanggal, '$tgl_aman') AS tanggal, 
            p.nama AS nama_siswa, 
            p.kelas,
            l.nama_lokasi, 
            l.jam_kerja,
            (SELECT jenis_izin FROM pengajuan_izin 
             WHERE siswa_id = p.id AND status = 'Disetujui' 
             AND '$tgl_aman' BETWEEN tgl_mulai AND tgl_selesai LIMIT 1) AS status_perizinan
        FROM peserta_didik p
        JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
        LEFT JOIN presensi_pkl pr ON p.id = pr.siswa_id AND pr.tanggal = '$tgl_aman'
        WHERE (
            pr.siswa_id IS NOT NULL 
            OR EXISTS (
                SELECT 1 FROM pengajuan_izin 
                WHERE siswa_id = p.id AND status = 'Disetujui' 
                AND '$tgl_aman' BETWEEN tgl_mulai AND tgl_selesai
            )
        ) {$filter_lokasi_clause}
        ORDER BY p.nama ASC
    ";
} else {
    // Logika Bulanan (Murni mengambil record yang ada + cek izin di hari tersebut)
    $bulan_rekap = isset($_POST['bulan_rekap']) ? $_POST['bulan_rekap'] : date('Y-m');
    $bln_aman = $koneksi->real_escape_string($bulan_rekap);
    $title_periode = date('F Y', strtotime($bulan_rekap . "-01"));
    
    $query_ekspor = "
        SELECT 
            pr.*, 
            p.nama AS nama_siswa, 
            p.kelas,
            l.nama_lokasi, 
            l.jam_kerja,
            (SELECT jenis_izin FROM pengajuan_izin 
             WHERE siswa_id = p.id AND status = 'Disetujui' 
             AND pr.tanggal BETWEEN tgl_mulai AND tgl_selesai LIMIT 1) AS status_perizinan
        FROM presensi_pkl pr
        JOIN peserta_didik p ON pr.siswa_id = p.id
        JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
        WHERE pr.tanggal LIKE '$bln_aman%' {$filter_lokasi_clause}
        ORDER BY pr.tanggal ASC, p.nama ASC
    ";
}

$data_export = $koneksi->query($query_ekspor);

// =====================================================================
// PROSES FORMAT EXCEL SPREADSHEET
// =====================================================================
if ($format_file === 'excel') {
    $filename = "Rekap_Presensi_" . str_replace(' ', '_', $title_mitra) . "_" . $rentang_tipe . ".xls";
    
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=$filename");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Cache-Control: private", false);
    
    echo "<h3>REKAPITULASI DATA PRESENSI SISWA PKL</h3>";
    echo "<table>";
    echo "<tr><td><b>Jurusan</b></td><td>: DESAIN PEMODELAN DAN INFORMASI BANGUNAN</td></tr>";
    echo "<tr><td><b>Lokasi PKL</b></td><td>: " . htmlspecialchars($title_mitra) . "</td></tr>";
    echo "<tr><td><b>Periode Rekap</b></td><td>: " . htmlspecialchars($title_periode) . "</td></tr>";
    echo "</table><br>";
    
    echo "<table border='1'>";
    echo "<thead>
            <tr style='background-color:#4e73df; color:white; font-weight:bold;'>
                <th>No</th>
                <th>Tanggal</th>
                <th>Nama Siswa</th>
                <th>Kelas</th>
                <th>Lokasi PKL</th>
                <th>Jam Masuk</th>
                <th>Keterangan Masuk</th>
                <th>Jam Pulang</th>
                <th>Keterangan Pulang</th>
            </tr>
          </thead>
          <tbody>";
          
    if ($data_export && $data_export->num_rows > 0) {
        $no = 1;
        while ($row = $data_export->fetch_assoc()) {
            $is_perizinan = !empty($row['status_perizinan']);
            
            $tampil_jam_m = "-";
            $tampil_jam_p = "-";
            $ket_masuk = "-";
            $get_pulang = "-";

            if ($is_perizinan) {
                $ket_masuk = "Dispensasi Izin (" . $row['status_perizinan'] . ")";
                $get_pulang = "Dispensasi Izin (" . $row['status_perizinan'] . ")";
            } else {
                $tampil_jam_m = htmlspecialchars($row['jam_masuk'] ?? '-');
                $tampil_jam_p = htmlspecialchars($row['jam_pulang'] ?? '-');
                
                $ket_masuk = "Tepat Waktu";
                if (!empty($row['jam_masuk']) && !empty($row['jam_kerja'])) {
                    $raw_jam_kerja = trim(explode('s.d.', $row['jam_kerja'])[0]);
                    $time_t = strtotime(str_replace('.', ':', $raw_jam_kerja));
                    $time_s = strtotime(str_replace('.', ':', trim($row['jam_masuk'])));
                    if ($time_t && $time_s && ($time_s - $time_t) > 0) {
                        $ket_masuk = "Terlambat " . round(($time_s - $time_t) / 60) . "m";
                    }
                }
                
                $get_pulang = "Belum Pulang";
                if (!empty($row['jam_pulang']) && !empty($row['jam_kerja'])) {
                    $parts_kerja = explode('s.d.', $row['jam_kerja']);
                    if (isset($parts_kerja[1])) {
                        $raw_plg_target = trim(str_replace('WIB', '', $parts_kerja[1]));
                        $time_pt = strtotime(str_replace('.', ':', $raw_plg_target));
                        $time_ps = strtotime(str_replace('.', ':', trim($row['jam_pulang'])));
                        if ($time_pt && $time_ps) {
                            if (($time_ps - $time_pt) < 0) {
                                $get_pulang = "Pulang Cepat " . round(abs($time_ps - $time_pt) / 60) . "m";
                            } else {
                                $get_pulang = "Lembur " . round(($time_ps - $time_pt) / 60) . "m";
                            }
                        }
                    }
                }
            }
            
            echo "<tr>";
            echo "<td align='center'>".$no++."</td>";
            echo "<td align='center'>".htmlspecialchars($row['tanggal'])."</td>";
            echo "<td>".htmlspecialchars($row['nama_siswa'])."</td>";
            echo "<td align='center'>".htmlspecialchars($row['kelas'])."</td>";
            echo "<td>".htmlspecialchars($row['nama_lokasi'])."</td>";
            echo "<td align='center'>".$tampil_jam_m."</td>";
            echo "<td>".htmlspecialchars($ket_masuk)."</td>";
            echo "<td align='center'>".$tampil_jam_p."</td>";
            echo "<td>".htmlspecialchars($get_pulang)."</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='9' align='center'>Tidak ada rekaman log presensi siswa.</td></tr>";
    }
    echo "</tbody></table>";
    exit;
}

// --- Definisi Variabel Kop Surat Keluar Resmi ---
$nama_sekolah = "SEKOLAH MENENGAH KEJURUAN ISLAM 1 BLITAR";
$jurusan = "DESAIN PEMODELAN DAN INFORMASI BANGUNAN";
$alamat_sekolah = "Jl. Musi No. 6 Blitar, 66117. Telp 081249464046";
$website = "http://dpib.smkislam1blitar.sch.id | E-mail: dpibsmkislam1blitar@gmail.com";
$jabatan_ttd = "Ketua Jurusan DPIB";
$nama_kaprog = "Mochamad Ade Satria, S.T.";
$nip_kaprog = "1994265653254630";
$ttd_image_path = "../img/ttd.png";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Rekap Presensi <?php echo htmlspecialchars($title_periode); ?></title>
    <style>
        body { 
            font-family: 'Times New Roman', Times, serif; 
            color: #000; 
            background: #fff; 
            padding: 5px 30px 30px 30px; 
            font-size: 11pt; 
            line-height: 1.4;
        }
        
        .kop { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            border-bottom: 4px double #000; 
            padding-bottom: 10px; 
            margin-top: 0px;
            margin-bottom: 20px;
        }
        .kop .logo { width: 90px; height: 90px; object-fit: contain; }
        .kop .info-sekolah { text-align: center; flex-grow: 1; }
        .kop .info-sekolah h2 { margin: 0; line-height: 1.1; font-weight: bold; font-size: 14pt; text-transform: uppercase; }
        .kop .info-sekolah h3 { margin: 4px 0; font-size: 12pt; font-weight: bold; text-transform: uppercase; }
        .kop .info-sekolah p { margin: 1px 0; font-size: 9.5pt; font-style: normal; }

        .document-title {
            text-align: center;
            font-weight: bold;
            font-size: 12pt;
            text-decoration: underline;
            text-transform: uppercase;
            margin-bottom: 20px;
            margin-top: 5px;
            letter-spacing: 0.5px;
        }
        
        .meta-info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .meta-info-table td { padding: 3px 0; font-size: 11pt; vertical-align: top; }
        .meta-info-table td.label { width: 18%; font-weight: bold; }
        .meta-info-table td.value { width: 82%; }

        .report-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .report-table th { 
            background-color: #f2f2f2 !important; 
            color: #000 !important; 
            font-weight: bold; 
            text-transform: uppercase; 
            font-size: 10pt; 
            padding: 10px 6px; 
            border: 1px solid #000; 
            text-align: center; 
        }
        .report-table td { padding: 8px 6px; border: 1px solid #000; font-size: 10.5pt; vertical-align: middle; }
        
        .badge-info-txt { font-size: 10px; font-weight: bold; display: inline-block; margin-top: 2px; }
        .text-danger-custom { color: #c0392b; }
        .text-success-custom { color: #27ae60; }
        .text-warning-custom { color: #d35400; }
        .text-primary-custom { color: #2980b9; }

        .print-footer { 
            width: 100%; 
            margin-top: 35px; 
            display: flex; 
            justify-content: flex-end; 
            page-break-inside: avoid;
        }
        .ttd-lokasi { 
            width: 320px; 
            text-align: left; 
            line-height: 1.5;
            position: relative; 
        }
        .ttd-lokasi p { margin: 2px 0; }
        .ttd-lokasi .nama-penanda { margin-top: 2px; font-weight: bold; text-decoration: underline; }

        .ttd-images-container {
            height: 115px;
            position: relative; 
            margin-bottom: 2px;
        }
        
        .ttd-image {
            width: 210px;
            height: auto;
            position: absolute;
            left: -60px;
            top: 5px;
            z-index: 2;
        }

        .stempel-image {
            width: 145px;
            height: auto;
            position: absolute;
            left: -25px;
            top: -15px;
            z-index: 1;
            opacity: 0.85;
        }
        
        @media print {
            body { padding: 0; background: #fff; }
            .no-print { display: none !important; }
            @page { margin: 1cm 1.5cm 1.5cm 1.5cm; size: A4 landscape; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="background: #f1f5f9; padding: 12px 25px; margin: -5px -30px 25px -30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #cbd5e1; font-family:'Poppins', sans-serif;">
        <span style="font-weight: 600; color: #475569;"><i class="fas fa-print me-2"></i>Dokumen PDF Siap Cetak (Landscape)</span>
        <div style="display: flex; gap: 8px;">
            <button onclick="window.history.back();" style="padding: 6px 14px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; font-weight: 600; cursor: pointer; color: #475569;">Kembali</button>
            <button onclick="window.print();" style="padding: 6px 18px; border-radius: 6px; border: none; background: #4e73df; color: #fff; font-weight: 600; cursor: pointer;">Cetak Berkas</button>
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

    <div class="document-title">REKAPITULASI PRESENSI PKL</div>

    <table class="meta-info-table">
        <tr>
            <td class="label">Lokasi PKL</td>
            <td class="value">: <strong><?php echo htmlspecialchars($title_mitra); ?></strong></td>
        </tr>
        <tr>
            <td class="label">Periode Rekap</td>
            <td class="value">: <?php echo htmlspecialchars($title_periode); ?></td>
        </tr>
    </table>

    <table class="report-table">
        <thead>
            <tr>
                <th width="4%">NO</th>
                <th width="10%">Tanggal</th>
                <th width="24%">Nama Lengkap Siswa</th>
                <th width="8%">Kelas</th>
                <th width="18%">Lokasi PKL</th>
                <th width="18%">Aktivitas Jam Masuk</th>
                <th width="18%">Aktivitas Jam Pulang</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($data_export && $data_export->num_rows > 0): ?>
                <?php 
                $no = 1; 
                while ($row = $data_export->fetch_assoc()): 
                    $nama_aman = htmlspecialchars($row['nama_siswa']);
                    $is_perizinan = !empty($row['status_perizinan']);
                    
                    if ($is_perizinan) {
                        $ket_masuk = "<span class='badge-info-txt text-warning-custom'>Dispensasi Izin (" . htmlspecialchars($row['status_perizinan']) . ")</span>";
                        $ket_pulang = "<span class='badge-info-txt text-warning-custom'>Dispensasi Izin (" . htmlspecialchars($row['status_perizinan']) . ")</span>";
                        $row['jam_masuk'] = "-";
                        $row['jam_pulang'] = "-";
                    } else {
                        // Status Masuk
                        $ket_masuk = "<span class='badge-info-txt text-success-custom'>Tepat Waktu</span>";
                        if (!empty($row['jam_masuk']) && !empty($row['jam_kerja'])) {
                            $raw_jam_kerja = trim(explode('s.d.', $row['jam_kerja'])[0]);
                            $time_t = strtotime(str_replace('.', ':', $raw_jam_kerja));
                            $time_s = strtotime(str_replace('.', ':', trim($row['jam_masuk'])));
                            if ($time_t && $time_s && ($time_s - $time_t) > 0) {
                                $ket_masuk = "<span class='badge-info-txt text-danger-custom'>Telat " . round(($time_s - $time_t) / 60) . "m</span>";
                            }
                        }

                        // Status Pulang
                        $ket_pulang = "-";
                        if (!empty($row['jam_pulang']) && !empty($row['jam_kerja'])) {
                            $parts_kerja = explode('s.d.', $row['jam_kerja']);
                            if (isset($parts_kerja[1])) {
                                $raw_plg_target = trim(str_replace('WIB', '', $parts_kerja[1]));
                                $time_pt = strtotime(str_replace('.', ':', $raw_plg_target));
                                $time_ps = strtotime(str_replace('.', ':', trim($row['jam_pulang'])));
                                if ($time_pt && $time_ps) {
                                    if (($time_ps - $time_pt) < 0) {
                                        $ket_pulang = "<span class='badge-info-txt text-warning-custom'>Cepat " . round(abs($time_ps - $time_pt) / 60) . "m</span>";
                                    } else {
                                        $ket_pulang = "<span class='badge-info-txt text-primary-custom'>Lembur " . round(($time_ps - $time_pt) / 60) . "m</span>";
                                    }
                                }
                            }
                        } else {
                            $ket_pulang = "<span class='text-muted' style='font-size:10px;'><i>Belum Pulang</i></span>";
                        }
                    }
                ?>
                    <tr>
                        <td align="center"><?php echo $no++; ?></td>
                        <td align="center"><?php echo htmlspecialchars($row['tanggal']); ?></td>
                        <td><strong><?php echo $nama_aman; ?></strong></td>
                        <td align="center"><?php echo htmlspecialchars($row['kelas']); ?></td>
                        <td><?php echo htmlspecialchars($row['nama_lokasi']); ?></td>
                        <td align="center">
                            <span><?php echo htmlspecialchars($row['jam_masuk']); ?></span> <?php echo $ket_masuk; ?>
                        </td>
                        <td align="center">
                            <span><?php echo htmlspecialchars($row['jam_pulang'] ?? '-'); ?></span> <?php echo $ket_pulang; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" align="center" style="padding: 30px 0; color:#555;">Tidak ditemukan rekaman log riwayat presensi pkl pada periode filter ini.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="print-footer">
        <div class="ttd-lokasi">
            <p>Blitar, <?php echo date('d F Y'); ?></p>
            <p><?php echo htmlspecialchars($jabatan_ttd); ?>,</p>
            
            <div class="ttd-images-container">
                <img src="<?php echo $ttd_image_path; ?>" alt="Tanda Tangan" class="ttd-image">
            </div>
            
            <div class="nama-penanda">
                <?php echo htmlspecialchars($nama_kaprog); ?>
            </div>
            <p>NIY. <?php echo htmlspecialchars($nip_kaprog); ?></p>
        </div>
    </div>

    <script>
        window.addEventListener('DOMContentLoaded', (event) => {
            window.print();
        });
    </script>
</body>
</html>