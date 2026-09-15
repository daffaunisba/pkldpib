<?php
// admin/presensi-ekspor.php
include 'auth-check.php';
include '../config/db-koneksi.php';

// --- Ambil Parameter POST ---
$lokasi_id    = isset($_POST['lokasi_id']) ? $_POST['lokasi_id'] : 'all';
$rentang_tipe = isset($_POST['rentang_tipe']) ? $_POST['rentang_tipe'] : 'harian';
$format_file  = isset($_POST['format_file']) ? $_POST['format_file'] : 'pdf';

// --- Bangun Filter Tanggal Logika SQL ---
$where_clause = " WHERE 1=1 ";
$title_periode = "";

if ($rentang_tipe === 'harian') {
    $tanggal_rekap = isset($_POST['tanggal_rekap']) ? $_POST['tanggal_rekap'] : date('Y-m-d');
    $where_clause .= " AND pr.tanggal = '" . $koneksi->real_escape_string($tanggal_rekap) . "' ";
    $title_periode = date('d F Y', strtotime($tanggal_rekap));
} else {
    $bulan_rekap = isset($_POST['bulan_rekap']) ? $_POST['bulan_rekap'] : date('Y-m');
    $where_clause .= " AND pr.tanggal LIKE '" . $koneksi->real_escape_string($bulan_rekap) . "%' ";
    $title_periode = date('F Y', strtotime($bulan_rekap . "-01"));
}

// --- Filter Mitra Penempatan ---
$title_mitra = "Semua Lokasi / Mitra Penempatan";
if ($lokasi_id !== 'all') {
    $where_clause .= " AND l.lokasi_id = '" . $koneksi->real_escape_string($lokasi_id) . "' ";
    
    // Ambil nama mitra penempatan spesifik untuk header judul dokumen
    $get_mitra = $koneksi->query("SELECT nama_lokasi FROM lokasi_pkl WHERE lokasi_id = '" . $koneksi->real_escape_string($lokasi_id) . "'");
    if ($get_mitra && $get_mitra->num_rows > 0) {
        $title_mitra = $get_mitra->fetch_assoc()['nama_lokasi'];
    }
}

// --- Ambil Kumpulan Data Aktivitas Presensi ---
$query_ekspor = "
    SELECT 
        pr.*, 
        p.nama AS nama_siswa, 
        p.kelas,
        l.nama_lokasi, 
        l.jam_kerja
    FROM presensi_pkl pr
    JOIN peserta_didik p ON pr.siswa_id = p.id
    JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
    $where_clause
    ORDER BY pr.tanggal ASC, pr.jam_masuk ASC
";
$data_export = $koneksi->query($query_ekspor);

// =====================================================================
// PROSES FORMAT EXCEL SPREADSHEET (CLEAN STYLE)
// =====================================================================
if ($format_file === 'excel') {
    $filename = "Rekap_Presensi_" . str_replace(' ', '_', $title_mitra) . "_" . $rentang_tipe . ".xls";
    
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=$filename");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Cache-Control: private", false);
    
    echo "<h3>REKAPITULASI DATA PRESENSI SISWA PKL SMEKISA</h3>";
    echo "<table>";
    echo "<tr><td><b>Mitra / Lokasi</b></td><td>: " . htmlspecialchars($title_mitra) . "</td></tr>";
    echo "<tr><td><b>Periode Rekap</b></td><td>: " . htmlspecialchars($title_periode) . "</td></tr>";
    echo "<tr><td><b>Waktu Unduh</b></td><td>: " . date('d-m-Y H:i:s') . "</td></tr>";
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
            // Kalkulasi Status Masuk
            $ket_masuk = "Tepat Waktu";
            if (!empty($row['jam_masuk']) && !empty($row['jam_kerja'])) {
                $raw_jam_kerja = trim(explode('s.d.', $row['jam_kerja'])[0]);
                $time_t = strtotime(str_replace('.', ':', $raw_jam_kerja));
                $time_s = strtotime(str_replace('.', ':', trim($row['jam_masuk'])));
                if ($time_t && $time_s && ($time_s - $time_t) > 0) {
                    $ket_masuk = "Terlambat " . round(($time_s - $time_t) / 60) . "m";
                }
            }
            
            // Kalkulasi Status Pulang
            $ket_pulang = "-";
            if (!empty($row['jam_pulang']) && !empty($row['jam_kerja'])) {
                $parts_kerja = explode('s.d.', $row['jam_kerja']);
                if (isset($parts_kerja[1])) {
                    $raw_plg_target = trim(str_replace('WIB', '', $parts_kerja[1]));
                    $time_pt = strtotime(str_replace('.', ':', $raw_plg_target));
                    $time_ps = strtotime(str_replace('.', ':', trim($row['jam_pulang'])));
                    if ($time_pt && $time_ps) {
                        if (($time_ps - $time_pt) < 0) {
                            $ket_pulang = "Pulang Cepat " . round(abs($time_ps - $time_pt) / 60) . "m";
                        } else {
                            $ket_pulang = "Lembur " . round(($time_ps - $time_pt) / 60) . "m";
                        }
                    }
                }
            } else {
                $ket_pulang = "Belum Pulang";
            }
            
            echo "<tr>";
            echo "<td align='center'>".$no++."</td>";
            echo "<td align='center'>".htmlspecialchars($row['tanggal'])."</td>";
            echo "<td>".htmlspecialchars($row['nama_siswa'])."</td>";
            echo "<td align='center'>".htmlspecialchars($row['kelas'])."</td>";
            echo "<td>".htmlspecialchars($row['nama_lokasi'])."</td>";
            echo "<td align='center'>".htmlspecialchars($row['jam_masuk'])."</td>";
            echo "<td>".htmlspecialchars($ket_masuk)."</td>";
            echo "<td align='center'>".htmlspecialchars($row['jam_pulang'] ?? '-')."</td>";
            echo "<td>".htmlspecialchars($ket_pulang)."</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='9' align='center'>Tidak ada rekaman log presensi siswa.</td></tr>";
    }
    echo "</tbody></table>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Rekap Presensi Geolocation</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; color: #2c3e50; background: #fff; padding: 20px; font-size: 13px; }
        .print-header { text-align: center; border-bottom: 3px solid #2c3e50; padding-bottom: 10px; margin-bottom: 25px; }
        .print-header h2 { margin: 0; font-weight: 700; font-size: 1.6rem; color: #1e293b; text-transform: uppercase; }
        .print-header p { margin: 5px 0 0 0; color: #64748b; font-size: 13px; }
        
        .meta-info-table { width: 100%; margin-bottom: 25px; border-collapse: collapse; }
        .meta-info-table td { padding: 4px 0; font-size: 13px; vertical-align: top; }
        .meta-info-table td.label { width: 15%; font-weight: 600; color: #475569; }
        .meta-info-table td.value { width: 85%; color: #0f172a; }

        .report-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .report-table th { background-color: #4e73df !important; color: white !important; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; padding: 12px 10px; border: 1px solid #d1d3e2; text-align: center; }
        .report-table td { padding: 10px 8px; border: 1px solid #e3e6f0; font-size: 12.5px; color: #334155; vertical-align: middle; }
        .report-table tbody tr:nth-child(even) { background-color: #f8fafc; }
        
        .badge-info-txt { font-size: 10.5px; font-weight: 700; padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 3px; }
        .bg-soft-danger { background-color: #fef2f2; color: #ef4444; }
        .bg-soft-success { background-color: #ecfdf5; color: #10b981; }
        .bg-soft-warning { background-color: #fff7ed; color: #f97316; }
        .bg-soft-primary { background-color: #eff6ff; color: #3b82f6; }

        .print-footer { width: 100%; margin-top: 50px; display: flex; justify-content: flex-end; }
        .signature-box { text-align: center; width: 250px; font-size: 13px; }
        .signature-box .space { height: 70px; }
        
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
            @page { margin: 1.5cm; size: A4 landscape; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="background: #f1f5f9; padding: 12px 25px; margin: -20px -20px 25px -20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #cbd5e1;">
        <span style="font-weight: 600; color: #475569;"><i class="fas fa-print me-2"></i>Dokumen PDF Siap Cetak (Landscape)</span>
        <div style="display: flex; gap: 8px;">
            <button onclick="window.history.back();" style="padding: 7px 16px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; font-weight: 600; cursor: pointer; color: #475569;">Kembali</button>
            <button onclick="window.print();" style="padding: 7px 20px; border-radius: 6px; border: none; background: #4e73df; color: #fff; font-weight: 600; cursor: pointer; box-shadow: 0 2px 5px rgba(78,115,223,0.2);">Mulai Cetak Dokumen</button>
        </div>
    </div>

    <div class="print-header">
        <h2>Rekapitulasi Presensi Geolocation PKL</h2>
        <p>SMK NEGERI 1 KOTA BLITAR (SMEKISA)</p>
    </div>

    <table class="meta-info-table">
        <tr>
            <td class="label">Mitra Industri</td>
            <td class="value">: <strong><?php echo htmlspecialchars($title_mitra); ?></strong></td>
        </tr>
        <tr>
            <td class="label">Periode Log</td>
            <td class="value">: <?php echo htmlspecialchars($title_periode); ?></td>
        </tr>
        <tr>
            <td class="label">Waktu Cetak</td>
            <td class="value">: <?php echo date('d F Y H:i:s') . " WIB oleh " . htmlspecialchars($current_user); ?></td>
        </tr>
    </table>

    <table class="report-table">
        <thead>
            <tr>
                <th width="4%">NO</th>
                <th width="10%">Tanggal</th>
                <th width="22%">Nama Siswa</th>
                <th width="8%">Kelas</th>
                <th width="18%">Mitra Lokasi</th>
                <th width="14%">Aktivitas Masuk</th>
                <th width="14%">Aktivitas Pulang</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($data_export && $data_export->num_rows > 0): ?>
                <?php 
                $no = 1; 
                while ($row = $data_export->fetch_assoc()): 
                    $nama_aman = htmlspecialchars($row['nama_siswa']);
                    
                    // Hitung Selisih Terlambat / Tepat Waktu Masuk
                    $ket_masuk = "<span class='badge-info-txt bg-soft-success'>Tepat Waktu</span>";
                    if (!empty($row['jam_masuk']) && !empty($row['jam_kerja'])) {
                        $raw_jam_kerja = trim(explode('s.d.', $row['jam_kerja'])[0]);
                        $time_t = strtotime(str_replace('.', ':', $raw_jam_kerja));
                        $time_s = strtotime(str_replace('.', ':', trim($row['jam_masuk'])));
                        if ($time_t && $time_s && ($time_s - $time_t) > 0) {
                            $ket_masuk = "<span class='badge-info-txt bg-soft-danger'><i class='fas fa-caret-up'></i> Telat " . round(($time_s - $time_t) / 60) . "m</span>";
                        }
                    }

                    // Hitung Selisih Pulang Cepat / Lembur
                    $ket_pulang = "-";
                    if (!empty($row['jam_pulang']) && !empty($row['jam_kerja'])) {
                        $parts_kerja = explode('s.d.', $row['jam_kerja']);
                        if (isset($parts_kerja[1])) {
                            $raw_plg_target = trim(str_replace('WIB', '', $parts_kerja[1]));
                            $time_pt = strtotime(str_replace('.', ':', $raw_plg_target));
                            $time_ps = strtotime(str_replace('.', ':', trim($row['jam_pulang'])));
                            if ($time_pt && $time_ps) {
                                if (($time_ps - $time_pt) < 0) {
                                    $ket_pulang = "<span class='badge-info-txt bg-soft-warning'><i class='fas fa-running'></i> Cepat " . round(abs($time_ps - $time_pt) / 60) . "m</span>";
                                } else {
                                    $ket_pulang = "<span class='badge-info-txt bg-soft-primary'><i class='fas fa-business-time'></i> Lembur " . round(($time_ps - $time_pt) / 60) . "m</span>";
                                }
                            }
                        }
                    } else {
                        $ket_pulang = "<span class='text-muted small'><i>Belum Pulang</i></span>";
                    }
                ?>
                    <tr>
                        <td align="center" class="font-monospace"><?php echo $no++; ?></td>
                        <td align="center"><?php echo htmlspecialchars($row['tanggal']); ?></td>
                        <td><strong><?php echo $nama_aman; ?></strong></td>
                        <td align="center"><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['kelas']); ?></span></td>
                        <td><?php echo htmlspecialchars($row['nama_lokasi']); ?></td>
                        <td align="center">
                            <span class="fw-semibold text-dark"><?php echo htmlspecialchars($row['jam_masuk']); ?></span><br>
                            <?php echo $ket_masuk; ?>
                        </td>
                        <td align="center">
                            <span class="fw-semibold text-dark"><?php echo htmlspecialchars($row['jam_pulang'] ?? '-'); ?></span>
                            <?php echo $ket_pulang; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" align="center" style="padding: 40px 0; color:#64748b;">Tidak ditemukan rekaman log riwayat presensi pkl pada periode filter ini.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="print-footer">
        <div class="signature-box">
            <p>Blitar, <?php echo date('d F Y'); ?></p>
            <p style="font-weight: 600; margin-top: -10px;">Kepala Program Keahlian / Admin,</p>
            <div class="space"></div>
            <p style="text-decoration: underline; font-weight: 700; margin-bottom: 0;">..........................................</p>
            <p class="text-muted" style="font-size: 11px; margin-top: 2px;">NIP. ..........................................</p>
        </div>
    </div>

    <script>
        window.addEventListener('DOMContentLoaded', (event) => {
            // Un-comment baris di bawah ini jika ingin modal print browser langsung muncul otomatis
            // window.print();
        });
    </script>
</body>
</html>