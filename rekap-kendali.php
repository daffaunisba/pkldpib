<!-- <?php 
// rekap-kendali.php (PUBLIK) - Menampilkan daftar rekapan kemajuan Kartu Kendali semua siswa.

// =================================================================
// 🚨 KONEKSI DATABASE & DEFINISI VARIABEL UNTUK HEADER
// =================================================================
$koneksi = null;
$error_message_koneksi = null; 
$paths_to_check = ['config/db-koneksi.php', '../config/db-koneksi.php', 'db-koneksi.php'];

foreach ($paths_to_check as $path) {
    if (file_exists($path)) {
        include $path; 
        break;
    }
}

if (!isset($koneksi) || !($koneksi instanceof mysqli) || (isset($koneksi) && $koneksi->connect_error !== null)) {
    $error_message_koneksi = "❌ Koneksi database GAGAL! File koneksi tidak ditemukan atau gagal terinisialisasi: " . ($koneksi ? $koneksi->connect_error : "Path tidak valid.");
    $koneksi = null;
}

$page_title = "Rekap Kemajuan Kartu Kendali PKL";
$siswa_data = [];
$error_message = null; 
$current_page = basename(__FILE__); 

// Fungsi formatTanggalIndo (untuk konsistensi header)
if (!function_exists('formatTanggalIndo')) {
    function formatTanggalIndo($date_str = null) {
        if (empty($date_str)) { $timestamp = time(); } else { $timestamp = strtotime($date_str); }
        $hari_indo = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $bulan_indo = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $hari = $hari_indo[date('w', $timestamp)];
        $tgl = date('d', $timestamp);
        $bln = $bulan_indo[(int)date('m', $timestamp)];
        $thn = date('Y', $timestamp);
        $waktu = date('H:i:s', $timestamp);
        return "{$hari}, {$tgl} {$bln} {$thn}, {$waktu} WIB"; 
    }
}

// ---------------------------------------------------------------------
// 2. QUERY DATA SISWA + STATUS KENDALI
// ---------------------------------------------------------------------

if (isset($koneksi) && $koneksi->connect_error === null) {
    $siswa_query_sql = "
        SELECT 
            p.id, p.nisn, p.nama AS nama_siswa, p.kelas,
            l.nama_lokasi,
            (SELECT COUNT(k.id) FROM kartu_kendali k WHERE k.siswa_id = p.id AND k.status = 1) AS kegiatan_valid,
            (SELECT COUNT(d.id) FROM kendali_kegiatan_def d WHERE d.is_active = TRUE) AS kegiatan_total
        FROM peserta_didik p
        LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
        ORDER BY l.nama_lokasi, p.nama ASC
    ";

    $siswa_query = $koneksi->query($siswa_query_sql);
    if ($siswa_query) {
        while ($row = $siswa_query->fetch_assoc()) {
            $siswa_data[] = $row;
        }
    } else {
        $error_message = "❌ Gagal mengambil data: " . $koneksi->error;
    }
} else {
    $error_message = $error_message_koneksi ?? "Koneksi database gagal.";
}
?>
<!doctype html>
<html lang="id">  
<head>    
    <meta charset="utf-8">    
    <meta name="viewport" content="initial-scale=1.0, width=device-width">    
    <title><?php echo $page_title; ?> | Si Mantap</title>    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>  
    
    <style>
        /* CSS SIMANTAP PUBLIC THEME */
        :root {
            --simantap-blue-dark: #224ebe; /* Warna utama (Navbar/Header) */
            --simantap-blue-light: #4e73df; /* Warna progress bar/link */
            --simantap-accent-green: #1cc88a; /* Badge Selesai */
            --simantap-red: #e74a3b; /* Badge Belum Mulai */
            --simantap-yellow: #ffc107; /* Badge Berjalan */
            --simantap-soft-shadow: 0 4px 10px rgba(0, 0, 0, 0.15); /* Shadow */
        }
        body { padding-top: 70px; background-color: #f4f7f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; }
        .bg-biru { background-color: var(--simantap-blue-dark) !important; }
        .fixed-top { z-index: 1051 !important; }

        /* PENTING: Konten utama diatur Full-Width */
        .page-content-wrapper { 
            min-height: calc(100vh - 70px); 
            padding: 30px 0; 
            width: 100%; 
        }
        
        /* HEADER UTAMA HALAMAN */
        .info-header { text-align: center; margin-bottom: 40px; }
        .info-header h1 { 
            color: var(--simantap-blue-dark); 
            font-size: 2.5rem; 
            font-weight: 700; 
        }
        
        /* CARD UTAMA (Content-Card) */
.content-card {
    background: white;
    padding: 30px 0; /* KRITIS: Padding horizontal dihilangkan di sini */
    border-radius: 10px;
    box-shadow: var(--simantap-soft-shadow);
    margin-bottom: 25px;
    width: 100%; /* Penting untuk Full-Width */
    box-sizing: border-box; /* Hitung padding dalam lebar */
}

/* Tambahkan padding horizontal ke elemen di dalam card (selain tabel) */
.card-content-wrapper {
    padding: 0 30px; /* Padding untuk konten di dalam card, kecuali tabel */
}

        /* JUDUL DI DALAM CARD (H2) */
        .content-card h2 {
            color: var(--simantap-blue-dark);
            border-bottom: 2px solid var(--simantap-blue-light);
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-size: 1.8rem;
            font-weight: 700;
        }

        /* TABLE STYLING */
.table-rekap {
    border-collapse: collapse; 
    border-spacing: 0;
    overflow: hidden; 
    /* UBAH INI: Agar tabel tidak 100% dan ada padding di samping */
    width: calc(100% - 60px); 
    margin: 0 30px; /* Tambahkan margin horizontal (padding) */
    /* --- END UBAH --- */
}
        
        /* 1. STYLING HEADER (TH) */
        .table-rekap thead th {
            background-color: var(--simantap-blue-dark);
            color: white;
            text-align: center;
            vertical-align: middle;
            font-size: 1.0em; 
            font-weight: 700; 
            padding: 10px 10px; 
            border-radius: 0 !important; 
            /* Garis Vertikal Putih Solid */
            border-right: 1px solid #FFFFFF; 
            border-left: none;
        }
        /* Hapus garis vertikal pada kolom header terakhir */
        .table-rekap thead th:last-child {
            border-right: none; 
        }

        /* 2. STYLING DATA CELL (TD) */
        .table-rekap tbody td {
            /* Garis Vertikal Abu-abu Muda */
            border-right: 1px solid #dee2e6;
            vertical-align: middle; 
            font-size: 0.95em; 
            padding: 15px 10px;
            border-left: none;
        }
        /* Hapus garis vertikal pada kolom data terakhir */
        .table-rekap tbody td:last-child {
            border-right: none;
        }
        
        .table-striped tbody tr:nth-of-type(odd) { background-color: #F8FAFC; }
        
        /* PROGRESS BAR & STATUS BADGE STYLES */
        .progress-container { display: flex; flex-direction: column; align-items: center; }
        .progress { height: 25px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); background-color: #e9ecef; width: 90%; }
        .progress-bar { background-color: var(--simantap-blue-light); font-weight: 600; color: white; text-shadow: 1px 1px 1px rgba(0,0,0,0.2); display: flex; align-items: center; justify-content: center; }
        .progress-detail { font-size: 0.8em; color: #6c757d; margin-top: 5px; }
        .status-badge { font-size: 0.9em; padding: 8px 15px; border-radius: 20px; font-weight: 700; display: inline-block; min-width: 100px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .status-valid { background-color: var(--simantap-accent-green); color: white; }
        .status-pending { background-color: var(--simantap-yellow); color: #333; }
        .status-muted { background-color: #6c757d; color: white; }
        .table-rekap a { color: var(--simantap-blue-dark); font-weight: 700; text-decoration: none; }
        .table-rekap a:hover { color: var(--simantap-blue-light); text-decoration: underline; }
        .siswa-detail { font-size: 0.85em; color: #6c757d; display: block; }

        /* MOBILE/RESPONSIVE OPTIMIZATION */
        @media (max-width: 991.98px) { 
            .page-content-wrapper { padding: 0; } 
            .content-card { margin: 0; border-radius: 0; padding: 10px 0; } 
            .card-content-wrapper { padding: 0 10px; } 
            .table-responsive { border: 1px solid #dee2e6; border-radius: 6px; overflow-x: auto; }
            .table-rekap {
        width: calc(100% - 20px); /* 100% minus 2x10px padding */
        margin: 0 10px; /* Padding 10px di kiri dan kanan */
            .info-header h1 { font-size: 2rem; }
            /* Garis vertikal di mobile juga */
            .table-rekap tbody td { border-right: 1px solid #dee2e6; }
            .table-rekap tbody td:last-child { border-right: none; }
        }

        /* DESKTOP OPTIMIZATION (Memberi padding samping ke wrapper) */
        @media (min-width: 992px) {
            .page-content-wrapper { padding: 30px 50px; } 
        }
    </style>
</head> 
<body>   
    
    <?php 
    // PANGGIL HEADER PUBLIK (Pastikan path ke file header-publik.php benar)
    include 'panel/header-publik.php'; 
    ?>
    
    <div class="page-content-wrapper">
        <div class="info-header">
            <h1 class="mb-3"><i class="fas fa-clipboard-list me-2"></i> Rekap Kemajuan Kartu Kendali</h1>
        </div>

        <div class="content-card">
            
            <div class="card-content-wrapper">
                <h2>Ringkasan Progres Pengisian Kartu Kendali</h2>
                <p>Tabel di bawah menampilkan status kemajuan pengisian Kartu Kendali (Logbook) dan validasi yang dilakukan oleh pembimbing di tempat PKL.</p>
            </div>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger" role="alert" style="margin: 0 30px;">
                    <i class="fas fa-exclamation-triangle"></i> KESALAHAN! <?php echo $error_message; ?>
                </div>
            <?php elseif (empty($siswa_data)): ?>
                <div class="alert alert-warning" role="alert" style="margin: 0 30px;">
                    <i class="fas fa-exclamation-triangle"></i> Belum ada peserta didik yang terdaftar atau belum ada data kendali yang diinput.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-rekap mt-3">
                        <thead>
                            <tr>
                                <th style="width: 5%;">No.</th>
                                <th style="width: 25%;" class="text-start">Siswa</th>
                                <th style="width: 25%;" class="text-start">Lokasi PKL</th>
                                <th style="width: 25%;">Progres Validasi</th>
                                <th style="width: 20%;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($siswa_data as $siswa): 
                                $total = (int)($siswa['kegiatan_total'] ?? 0); 
                                $valid = (int)($siswa['kegiatan_valid'] ?? 0); 
                                $persen = ($total > 0) ? round(($valid / $total) * 100) : 0;
                                
                                $status_text = 'Belum Mulai'; 
                                if ($valid > 0 && $valid < $total) { $status_text = 'Berjalan'; } 
                                elseif ($valid == $total && $total > 0) { $status_text = 'Selesai'; }
                                
                                $badge_class = 'status-muted';
                                if ($status_text == 'Selesai') { $badge_class = 'status-valid'; } 
                                elseif ($status_text == 'Berjalan') { $badge_class = 'status-pending'; }
                            ?>
                            <tr>
                                <td class="text-center"><?php echo $no++; ?></td>
                                <td class="text-start">
                                    <a href="kendali-detail-publik.php?siswa_id=<?php echo htmlspecialchars($siswa['id'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($siswa['nama_siswa'] ?? 'N/A'); ?>
                                    </a>
                                    <span class="siswa-detail"><?php echo htmlspecialchars($siswa['kelas'] ?? 'N/A'); ?> (NISN: <?php echo htmlspecialchars($siswa['nisn'] ?? 'N/A'); ?>)</span>
                                </td>
                                <td class="text-start"><?php echo htmlspecialchars($siswa['nama_lokasi'] ?? 'N/A'); ?></td>
                                <td class="text-center">
                                    <div class="progress-container">
                                        <div class="progress" style="height: 25px;">
                                            <div class="progress-bar" role="progressbar" style="width: <?php echo $persen; ?>%;" aria-valuenow="<?php echo $persen; ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?php echo $persen; ?>%
                                            </div>
                                        </div>
                                        <span class="progress-detail">
                                            <?php echo $valid; ?> dari <?php echo $total; ?> Kegiatan Tervalidasi
                                        </span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="status-badge <?php echo $badge_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
    
    <?php 
    // PANGGIL FOOTER PUBLIK (Pastikan path ke file public_footer.php benar)
    include 'panel/public_footer.php'; 
    ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"></script> 
    <script type="text/javascript">
    // FUNGSI REALTIME CLOCK LENGKAP UNTUK UPDATE DETIK
    function updateClock() {
        const now = new Date();
        const localTime = new Date(now.toLocaleString("en-US", {timeZone: "Asia/Jakarta"}));
        const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        const dayName = days[localTime.getDay()];
        const dayOfMonth = localTime.getDate();
        const monthName = months[localTime.getMonth()];
        const year = localTime.getFullYear();
        const hours = String(localTime.getHours()).padStart(2, '0');
        const minutes = String(localTime.getMinutes()).padStart(2, '0');
        const seconds = String(localTime.getSeconds()).padStart(2, '0');
        const dateString = `${dayName}, ${dayOfMonth} ${monthName} ${year}`;
        const timeString = `${dateString}, ${hours}:${minutes}:${seconds} WIB`;
        
        const clockDesktop = document.getElementById('realtime-clock-desktop');
        if (clockDesktop) { clockDesktop.textContent = timeString; }

        const clockMobile = document.getElementById('realtime-clock-mobile');
        if (clockMobile) { clockMobile.textContent = `${dateString} | ${hours}:${minutes}:${seconds} WIB`; }
    }

    $(document).ready(function() { 
        updateClock(); 
        setInterval(updateClock, 1000); 
    });
    </script>
</body>
</html> -->