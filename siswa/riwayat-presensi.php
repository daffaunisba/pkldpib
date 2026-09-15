<?php
// riwayat-presensi.php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log');
error_reporting(E_ALL);

session_start();
include '../config/db-koneksi.php';

if (!isset($_SESSION['siswa_id'])) {
    header("Location: login.php");
    exit();
}

$siswa_id = $_SESSION['siswa_id'];
$errors = [];

// Array Translasi Bahasa Indonesia untuk Tanggal & Hari
$bulan_indo = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

$bulan_indo_short = [
    '01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr',
    '05' => 'Mei', '06' => 'Jun', '07' => 'Jul', '08' => 'Agu',
    '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'
];

$hari_indo = [
    'Monday'    => 'Senin',
    'Tuesday'   => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday'  => 'Kamis',
    'Friday'    => 'Jumat',
    'Saturday'  => 'Sabtu',
    'Sunday'    => 'Minggu'
];

// Map Index Hari (Untuk Logika Rentang Hari Kerja)
$map_hari_idx = [
    'Senin' => 1, 'Selasa' => 2, 'Rabu' => 3, 'Kamis' => 4, 
    'Jumat' => 5, 'Sabtu' => 6, 'Minggu' => 7
];

// Ambil bulan dan tahun dari filter
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

// 1. Ambil lokasi siswa & PERIODE PKL (Termasuk Hari Kerja)
$qLokasi = $koneksi->query("
    SELECT l.nama_lokasi, l.hari_mulai, l.hari_selesai, l.jam_kerja, per.tgl_mulai, per.tgl_akhir 
    FROM peserta_didik p 
    LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id 
    LEFT JOIN periode_pkl per ON p.periode_id = per.periode_id
    WHERE p.id = '$siswa_id'
");

if (!$qLokasi) {
    $errors[] = "Error ambil data siswa/periode: " . $koneksi->error;
}
$dataSiswa = $qLokasi ? $qLokasi->fetch_assoc() : null;

// Ekstrak range hari kerja
$h_mulai = $dataSiswa['hari_mulai'] ?? 'Senin';
$h_selesai = $dataSiswa['hari_selesai'] ?? 'Jumat';
$idx_mulai = $map_hari_idx[$h_mulai] ?? 1;
$idx_selesai = $map_hari_idx[$h_selesai] ?? 5;

// 2. Query data Presensi (Kehadiran)
$query = $koneksi->query("
    SELECT pr.*, l.jam_kerja 
    FROM presensi_pkl pr
    JOIN peserta_didik p ON pr.siswa_id = p.id
    LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
    WHERE pr.siswa_id = '$siswa_id' 
    AND MONTH(pr.tanggal) = '$bulan' 
    AND YEAR(pr.tanggal) = '$tahun'
");

if (!$query) {
    $errors[] = "Query Presensi Error: " . $koneksi->error;
}

$riwayat_db = [];
if ($query) {
    while ($row = $query->fetch_assoc()) {
        $date_key = date('Y-m-d', strtotime($row['tanggal']));
        $riwayat_db[$date_key] = $row;
    }
}

// 3. Query data Izin & Sakit yang DISETUJUI
$query_izin = $koneksi->query("
    SELECT jenis_izin, tgl_mulai, tgl_selesai 
    FROM pengajuan_izin 
    WHERE siswa_id = '$siswa_id' AND status = 'Disetujui'
");

$data_izin = [];
if ($query_izin) {
    while ($row = $query_izin->fetch_assoc()) {
        $start = strtotime($row['tgl_mulai']);
        $end = strtotime($row['tgl_selesai']);
        // Looping untuk memasukkan semua tanggal di antara rentang izin
        for ($currentDate = $start; $currentDate <= $end; $currentDate += 86400) {
            $data_izin[date('Y-m-d', $currentDate)] = $row['jenis_izin'];
        }
    }
} else {
    $errors[] = "Query Izin Error: " . $koneksi->error;
}

// 4. Query Data Hari Libur Nasional
$query_libur = $koneksi->query("
    SELECT tanggal_libur, keterangan 
    FROM hari_libur 
    WHERE MONTH(tanggal_libur) = '$bulan' AND YEAR(tanggal_libur) = '$tahun'
");

$data_libur_nasional = [];
if ($query_libur) {
    while ($row = $query_libur->fetch_assoc()) {
        $data_libur_nasional[$row['tanggal_libur']] = $row['keterangan'];
    }
}

// Persiapan Variabel Rekapan & Statistik
$riwayat_table = [];
$stat_hadir = 0;
$stat_telat = 0;
$stat_alpa = 0;
$stat_sakit = 0;
$stat_izin = 0;

$days_in_month = cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);
$current_date = date('Y-m-d');

$tgl_mulai_pkl = $dataSiswa['tgl_mulai'] ?? null;
$tgl_akhir_pkl = $dataSiswa['tgl_akhir'] ?? null;

// Looping seluruh hari dalam bulan yang dipilih
for ($i = 1; $i <= $days_in_month; $i++) {
    $date_str = sprintf("%04d-%02d-%02d", $tahun, $bulan, $i);
    $day_of_week = date('N', strtotime($date_str)); // 1 (Senin) - 7 (Minggu)
    $is_past_or_today = ($date_str <= $current_date);
    
    // CEK SINKRONISASI PERIODE PKL
    $is_before_pkl = (!empty($tgl_mulai_pkl) && $date_str < $tgl_mulai_pkl);
    $is_after_pkl  = (!empty($tgl_akhir_pkl) && $date_str > $tgl_akhir_pkl);

    if ($is_before_pkl) {
        $riwayat_table[] = [
            'tanggal' => $date_str,
            'status' => 'Belum Mulai',
            'data' => null,
            'ket_waktu' => '',
            'ket_pulang' => '',
            'ket_libur' => ''
        ];
        continue;
    }

    if ($is_after_pkl) {
        $riwayat_table[] = [
            'tanggal' => $date_str,
            'status' => 'Selesai',
            'data' => null,
            'ket_waktu' => '',
            'ket_pulang' => '',
            'ket_libur' => ''
        ];
        continue;
    }
    
    // Jika ada histori presensi (Kehadiran fisik)
    if (isset($riwayat_db[$date_str])) {
        $row = $riwayat_db[$date_str];
        
        $is_telat_row = false;
        $keterangan_waktu = "";
        $keterangan_pulang = "";
        
        // Kalkulasi Waktu Datang
        if (!empty($row['jam_masuk']) && !empty($row['jam_kerja'])) {
            $raw_target = trim(explode('s.d.', $row['jam_kerja'])[0]);
            $time_target = strtotime(str_replace('.', ':', $raw_target));
            $time_siswa  = strtotime(str_replace('.', ':', trim($row['jam_masuk'])));

            if ($time_target && $time_siswa) {
                $selisih_detik = $time_siswa - $time_target;
                $selisih_menit = round($selisih_detik / 60);

                if ($selisih_menit > 0) {
                    $is_telat_row = true;
                    $stat_telat++;
                    $keterangan_waktu = "<span class='time-badge-inline inline-telat'><i class='fas fa-caret-up'></i>Telat " . abs($selisih_menit) . "m</span>";
                } elseif ($selisih_menit < 0) {
                    $stat_hadir++;
                    $keterangan_waktu = "<span class='time-badge-inline inline-awal'><i class='fas fa-caret-down'></i>Awal " . abs($selisih_menit) . "m</span>";
                } else {
                    $stat_hadir++;
                }
            } else {
                $stat_hadir++; 
            }
        } else {
            $stat_hadir++; 
        }

        // Kalkulasi Waktu Pulang
        if (!empty($row['jam_pulang']) && !empty($row['jam_kerja'])) {
            $parts_kerja = explode('s.d.', $row['jam_kerja']);
            if (isset($parts_kerja[1])) {
                $raw_pulang_target = trim(str_replace('WIB', '', $parts_kerja[1]));
                $time_pulang_target = strtotime(str_replace('.', ':', $raw_pulang_target));
                $time_pulang_siswa  = strtotime(str_replace('.', ':', trim($row['jam_pulang'])));

                if ($time_pulang_target && $time_pulang_siswa) {
                    $selisih_pulang_detik = $time_pulang_siswa - $time_pulang_target;
                    $selisih_pulang_menit = round($selisih_pulang_detik / 60);

                    if ($selisih_pulang_menit < 0) {
                        $keterangan_pulang = "<span class='time-badge-inline inline-cepat'><i class='fas fa-person-running'></i>Cepat " . abs($selisih_pulang_menit) . "m</span>";
                    } elseif ($selisih_pulang_menit > 0) {
                        $keterangan_pulang = "<span class='time-badge-inline inline-lembur'><i class='fas fa-business-time'></i>Lembur " . $selisih_pulang_menit . "m</span>";
                    }
                }
            }
        }

        $riwayat_table[] = [
            'tanggal' => $date_str,
            'status' => $is_telat_row ? 'Telat' : 'Hadir',
            'data' => $row,
            'ket_waktu' => $keterangan_waktu,
            'ket_pulang' => $keterangan_pulang,
            'ket_libur' => ''
        ];

    } else {
        // Jika tidak ada presensi
        $status = '';
        $keterangan_khusus = '';
        
        // CEK HARI KERJA DINAMIS (Berdasarkan Lokasi Siswa)
        $is_libur = true;
        if ($idx_mulai <= $idx_selesai) {
            // Rentang normal (ex: Senin(1) s.d Jumat(5))
            if ($day_of_week >= $idx_mulai && $day_of_week <= $idx_selesai) {
                $is_libur = false;
            }
        } else {
            // Rentang lintas minggu (ex: Jumat(5) s.d Selasa(2))
            if ($day_of_week >= $idx_mulai || $day_of_week <= $idx_selesai) {
                $is_libur = false;
            }
        }

        // Cek sinkronisasi Libur Nasional vs Libur Akhir Pekan vs Absensi Aktif
        if (isset($data_libur_nasional[$date_str])) {
            $status = 'Libur Nasional';
            $keterangan_khusus = $data_libur_nasional[$date_str];
        } elseif ($is_libur) { 
            // Bukan hari kerja (Libur)
            $status = 'Libur Akhir Pekan';
        } else {
            // Berada di dalam hari kerja aktif
            if (isset($data_izin[$date_str])) {
                // Ada Izin/Sakit yang disetujui pada tanggal ini
                $status = $data_izin[$date_str]; // 'Sakit' atau 'Izin'
                if ($status == 'Sakit') $stat_sakit++;
                if ($status == 'Izin') $stat_izin++;
            } else {
                if ($is_past_or_today) {
                    $status = 'Alpa';
                    $stat_alpa++;
                } else {
                    $status = 'Belum'; // Tanggal di masa depan
                }
            }
        }
        
        $riwayat_table[] = [
            'tanggal' => $date_str,
            'status' => $status,
            'data' => null,
            'ket_waktu' => '',
            'ket_pulang' => '',
            'ket_libur' => $keterangan_khusus
        ];
    }
}

// Urutkan data dari tanggal 1 ke paling akhir (Ascending - Dari Atas ke Bawah)
usort($riwayat_table, function($a, $b) {
    return strtotime($a['tanggal']) - strtotime($b['tanggal']);
});

include 'includes/header.php';
?>

<style>
    /* Token Desain & Skema Warna */
    :root {
        --primary-grad: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --card-radius: 25px;
        --item-radius: 20px;
    }

    .container-wrapper {
        padding: 1.5rem;
        width: 100%;
        overflow-x: hidden;
        background: #fafbfe;
    }

    /* Greeting / Title Card */
    .greeting-card {
        background: var(--primary-grad);
        color: white;
        border-radius: var(--card-radius);
        padding: 2.5rem 2rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
        position: relative;
        overflow: hidden;
    }
    .greeting-card::after {
        content: '';
        position: absolute;
        width: 150px;
        height: 150px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 50%;
        right: -30px;
        top: -30px;
    }

    /* Stats & Cards */
    .stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .stat-card {
        position: relative;
        overflow: hidden;
        padding: 1.25rem 1.5rem;
        border-radius: var(--item-radius);
        color: white;
        display: flex;
        align-items: center;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.02);
        transition: 0.3s;
        border: 1px solid transparent;
    }
    .stat-card:hover { transform: translateY(-3px); }

    .stat-icon {
        font-size: 2.8rem;
        opacity: 0.15;
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
    }

    .stat-info h3 {
        font-size: 1.6rem;
        font-weight: 800;
        margin-bottom: 0;
    }
    .stat-info p {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
        font-weight: 800;
        opacity: 0.9;
    }

    /* Warna Spesifik Stats */
    .stat-hadir { background: white; color: #1e293b; border-color: #e2e8f0; }
    .stat-hadir .stat-info p { color: #10b981; }
    .stat-hadir h3 { color: #1e293b; }

    .stat-telat { background: white; color: #1e293b; border-color: #e2e8f0; }
    .stat-telat .stat-info p { color: #f97316; }
    .stat-telat h3 { color: #1e293b; }

    .stat-alpa { background: #fff5f5; border-color: #fecaca; color: #dc2626; }
    .stat-alpa .stat-info p { color: #dc2626; }
    .stat-alpa h3 { color: #dc2626; }

    .stat-sakit { background: #eff6ff; border-color: #bfdbfe; color: #2563eb; }
    .stat-sakit .stat-info p { color: #2563eb; }
    .stat-sakit h3 { color: #2563eb; }

    .stat-izin { background: #fefce8; border-color: #fef08a; color: #ca8a04; }
    .stat-izin .stat-info p { color: #ca8a04; }
    .stat-izin h3 { color: #ca8a04; }

    /* Filter Container */
    .filter-section {
        background: white;
        border-radius: var(--card-radius);
        padding: 1.5rem 1.8rem;
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.03);
        margin-bottom: 1.5rem;
        border: 1px solid #f1f5f9;
    }
    .filter-section h5 {
        color: #1e293b;
        font-weight: 800;
        margin-bottom: 1.25rem;
        font-size: 1rem;
    }
    .filter-group {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        align-items: center;
    }
    .filter-group select {
        padding: 0.6rem 1.2rem;
        border: 1px solid #e2e8f0;
        border-radius: 50px;
        font-size: 0.85rem;
        background: #f8fafc;
        color: #475569;
        cursor: pointer;
        transition: 0.3s;
        font-weight: 700;
    }
    .filter-group select:focus {
        outline: none;
        border-color: #667eea;
        background: white;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    .filter-group button {
        padding: 0.6rem 1.5rem;
        background: var(--primary-grad);
        color: white;
        border: none;
        border-radius: 50px;
        font-weight: 700;
        cursor: pointer;
        transition: 0.3s;
        font-size: 0.85rem;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.25);
    }
    .filter-group button:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 15px rgba(102, 126, 234, 0.35);
    }

    /* Styling Tabel Presensi */
    .table-wrapper {
        background: white;
        border-radius: var(--card-radius);
        padding: 1.5rem;
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.03);
        border: 1px solid #f1f5f9;
        overflow-x: auto;
    }
    .table-presensi {
        width: 100%;
        border-collapse: collapse;
        min-width: 800px;
    }
    .table-presensi th {
        background: #f8fafc;
        color: #64748b;
        font-weight: 800;
        text-transform: uppercase;
        font-size: 0.75rem;
        padding: 1.2rem 1rem;
        border-bottom: 2px solid #e2e8f0;
        letter-spacing: 0.5px;
    }
    .table-presensi td {
        padding: 1rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        color: #1e293b;
        font-size: 0.9rem;
        font-weight: 600;
    }
    .table-presensi tr:last-child td { border-bottom: none; }
    .table-presensi tr:hover td { background: #fdfdfe; }
    
    .date-cell {
        font-weight: 800;
        color: #334155;
    }
    
    /* Badges Status Inline */
    .badge-status {
        padding: 6px 14px;
        border-radius: 50px;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: inline-block;
        text-align: center;
        min-width: 70px;
    }
    .badge-hadir { background: #e3fcef; color: #10b981; }
    .badge-telat { background: #fff7ed; color: #f97316; }

    /* Inline Time Info */
    .time-badge-inline {
        font-size: 10px;
        font-weight: 800;
        padding: 3px 8px;
        border-radius: 50px;
        margin-top: 4px;
        display: inline-flex;
        align-items: center;
        gap: 3px;
    }
    .inline-telat { background-color: #fff5f5; color: #dc2626; }
    .inline-awal { background-color: #e3fcef; color: #10b981; }
    .inline-cepat { background-color: #fff7ed; color: #ea580c; }
    .inline-lembur { background-color: #f0f3ff; color: #667eea; }

    /* Thumbnails Foto Tabel */
    .thumb-img {
        width: 45px;
        height: 45px;
        object-fit: cover;
        border-radius: 10px;
        cursor: pointer;
        border: 2px solid #e2e8f0;
        transition: 0.2s;
    }
    .thumb-img:hover {
        transform: scale(1.15);
        border-color: #667eea;
        box-shadow: 0 4px 10px rgba(102, 126, 234, 0.2);
    }
    .thumb-placeholder {
        width: 45px;
        height: 45px;
        border-radius: 10px;
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #cbd5e1;
        font-size: 0.85rem;
    }

    /* Modal Bukti Foto */
    .foto-modal {
        display: none;
        position: fixed;
        z-index: 10000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
    }
    .foto-modal-content {
        background-color: white;
        margin: 10% auto;
        padding: 1rem;
        border-radius: var(--card-radius);
        width: 90%;
        max-width: 420px;
        position: relative;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        border: 1px solid rgba(255,255,255,0.2);
    }
    .foto-modal-close {
        position: absolute;
        right: -12px;
        top: -12px;
        width: 32px;
        height: 32px;
        background: var(--primary-grad);
        color: white;
        border-radius: 50%;
        font-size: 1.2rem;
        font-weight: bold;
        cursor: pointer;
        border: 3px solid white;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        transition: 0.2s;
    }
    .foto-modal-close:hover { transform: scale(1.1); }
</style>

<div class="container-wrapper">

    <div class="greeting-card">
        <h4 class="fw-bold text-white mb-1">Rekapan Presensi & Izin 📋</h4>
        <p class="mb-0 opacity-75 small">Pantau catatan kehadiran dan status pengajuan Anda bulan ini.</p>
    </div>

    <div class="stats-container">
        <div class="stat-card stat-hadir">
            <div class="stat-icon" style="color: #10b981;"><i class="fas fa-calendar-check"></i></div>
            <div class="stat-info">
                <p>Hadir Tepat</p>
                <h3><?= $stat_hadir; ?></h3>
            </div>
        </div>
        <div class="stat-card stat-telat">
            <div class="stat-icon" style="color: #f97316;"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <p>Terlambat</p>
                <h3><?= $stat_telat; ?></h3>
            </div>
        </div>
        <div class="stat-card stat-alpa">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-info">
                <p>Alpa</p>
                <h3><?= $stat_alpa; ?></h3>
            </div>
        </div>
        <div class="stat-card stat-sakit">
            <div class="stat-icon"><i class="fas fa-procedures"></i></div>
            <div class="stat-info">
                <p>Sakit</p>
                <h3><?= $stat_sakit; ?></h3>
            </div>
        </div>
        <div class="stat-card stat-izin">
            <div class="stat-icon"><i class="fas fa-envelope-open-text"></i></div>
            <div class="stat-info">
                <p>Izin</p>
                <h3><?= $stat_izin; ?></h3>
            </div>
        </div>
    </div>

    <?php if (count($errors) > 0): ?>
        <div class="error-box p-3 mb-4 rounded" style="background:#fff5f5; border:1px solid #fed7d7; color:#dc2626;">
            <strong class="d-block mb-1">⚠️ Ada Kesalahan Sistem:</strong>
            <ul style="margin: 0; padding-left: 1.25rem;">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="filter-section">
        <h5><i class="fas fa-sliders-h me-2" style="color: #667eea;"></i>Filter Rekapan Bulan</h5>
        <form method="GET" class="filter-group">
            <div>
                <select name="bulan" class="form-select">
                    <?php for ($i = 1; $i <= 12; $i++): 
                        $b_val = str_pad($i, 2, '0', STR_PAD_LEFT);
                    ?>
                        <option value="<?= $b_val ?>" <?= ($b_val == $bulan) ? 'selected' : '' ?>>
                            <?= $bulan_indo[$b_val] ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <select name="tahun" class="form-select">
                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                        <option value="<?= $y ?>" <?= ($y == $tahun) ? 'selected' : '' ?>>
                            <?= $y ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <button type="submit" class="shadow-sm"><i class="fas fa-search me-1"></i> Tampilkan</button>
        </form>
    </div>

    <div class="table-wrapper">
        <table class="table-presensi">
            <thead>
                <tr>
                    <th width="15%">Tanggal</th>
                    <th width="15%">Status</th>
                    <th width="20%">Jam Datang</th>
                    <th width="20%">Jam Pulang</th>
                    <th width="15%" class="text-center">Foto Datang</th>
                    <th width="15%" class="text-center">Foto Pulang</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($riwayat_table as $item): 
                    // Format Tanggal & Hari Bahasa Indonesia
                    $tgl_formatted = date('d', strtotime($item['tanggal'])) . ' ' . $bulan_indo_short[date('m', strtotime($item['tanggal']))] . ' ' . date('Y', strtotime($item['tanggal']));
                    $hari_formatted = $hari_indo[date('l', strtotime($item['tanggal']))];
                ?>
                    <tr>
                        <td class="date-cell">
                            <?= $tgl_formatted ?>
                            <div class="small fw-normal text-muted mt-1"><?= $hari_formatted ?></div>
                        </td>

                        <?php if (in_array($item['status'], ['Hadir', 'Telat'])): ?>
                            <td>
                                <?php $badgeClass = ($item['status'] == 'Hadir') ? 'badge-hadir' : 'badge-telat'; ?>
                                <span class="badge-status <?= $badgeClass ?>"><?= $item['status'] ?></span>
                            </td>

                            <td>
                                <?php if($item['data'] && !empty($item['data']['jam_masuk'])): ?>
                                    <div class="fw-bold"><?= htmlspecialchars($item['data']['jam_masuk']) ?></div>
                                    <?= $item['ket_waktu'] ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if($item['data'] && !empty($item['data']['jam_pulang'])): ?>
                                    <div class="fw-bold"><?= htmlspecialchars($item['data']['jam_pulang']) ?></div>
                                    <?= $item['ket_pulang'] ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-center">
                                <?php if($item['data'] && !empty($item['data']['keterangan'])): ?>
                                    <img src="../uploads/absensi/<?= htmlspecialchars($item['data']['keterangan']) ?>" class="thumb-img mx-auto" title="Foto Datang" onclick="openModal(this.src)">
                                <?php else: ?>
                                    <div class="thumb-placeholder mx-auto" title="Tidak ada foto datang"><i class="fas fa-image"></i></div>
                                <?php endif; ?>
                            </td>

                            <td class="text-center">
                                <?php if($item['data'] && !empty($item['data']['keterangan2'])): ?>
                                    <img src="../uploads/absensi/<?= htmlspecialchars($item['data']['keterangan2']) ?>" class="thumb-img mx-auto" title="Foto Pulang" onclick="openModal(this.src)">
                                <?php else: ?>
                                    <div class="thumb-placeholder mx-auto" title="Tidak ada foto pulang"><i class="fas fa-image"></i></div>
                                <?php endif; ?>
                            </td>

                        <?php else: ?>
                            <?php 
                                // Setup Warna Barikade
                                $bar_style = "background: #f8fafc; color: #64748b;"; // Default (Libur/Belum)
                                $icon = "fa-info-circle";
                                $text_display = strtoupper($item['status'] ?: '-');
                                
                                if ($item['status'] == 'Alpa') {
                                    $bar_style = "background-color: #fff5f5; color: #dc2626;"; // Merah
                                    $icon = "fa-times-circle";
                                } elseif ($item['status'] == 'Sakit') {
                                    $bar_style = "background-color: #eff6ff; color: #2563eb;"; // Biru
                                    $icon = "fa-procedures";
                                } elseif ($item['status'] == 'Izin') {
                                    $bar_style = "background-color: #fefce8; color: #ca8a04;"; // Kuning
                                    $icon = "fa-envelope-open-text";
                                } elseif ($item['status'] == 'Libur Nasional') {
                                    $bar_style = "background-color: #fef2f2; color: #dc2626; border: 1px dashed #fca5a5;"; // Aksen Libur Merah Soft
                                    $icon = "fa-calendar-times";
                                    $text_display = "LIBUR: " . strtoupper($item['ket_libur']);
                                } elseif ($item['status'] == 'Belum Mulai') {
                                    $bar_style = "background-color: #f1f5f9; color: #64748b; opacity: 0.8;"; 
                                    $icon = "fa-hourglass-start";
                                    $text_display = "PKL BELUM DIMULAI";
                                } elseif ($item['status'] == 'Selesai') {
                                    $bar_style = "background-color: #f1f5f9; color: #64748b; opacity: 0.8;"; 
                                    $icon = "fa-flag-checkered";
                                    $text_display = "PKL TELAH BERAKHIR";
                                }
                            ?>
                            <td colspan="5" class="text-center" style="border-radius: 8px; <?= $bar_style ?>">
                                <span class="fw-bold opacity-100 fst-italic">
                                    <i class="fas <?= $icon ?> me-1"></i> <?= $text_display ?>
                                </span>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<div id="fotoModal" class="foto-modal">
    <div class="foto-modal-content">
        <button class="foto-modal-close" onclick="closeModal()">&times;</button>
        <img id="modalImage" src="" alt="Foto Absensi" style="width: 100%; height: auto; border-radius: 15px; display:block;">
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bundle.min.js"></script>
<script>
    function openModal(src) {
        document.getElementById('fotoModal').style.display = 'block';
        document.getElementById('modalImage').src = src;
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        document.getElementById('fotoModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    window.onclick = function(event) {
        var modal = document.getElementById('fotoModal');
        if (event.target == modal) {
            closeModal();
        }
    }
</script>

<?php include 'includes/footer.php'; ?>