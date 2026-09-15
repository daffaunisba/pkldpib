<?php 
// kartu-kendali.php (PUBLIK) - Halaman informasi prosedur dan ketentuan Kartu Kendali PKL.

// =================================================================
// 🚨 KONFIGURASI DAN VARIABEL UNTUK HEADER
// =================================================================

// Fungsi formatTanggalIndo (dipertahankan di sini untuk konsistensi header)
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

$page_title = "Kartu Kendali & Prosedur PKL";
$current_page = basename(__FILE__); // FILE INI: kartu-kendali.php
$error_message = null; 

// Konten statis
$prosedur = [
    "Pengajuan Lokasi oleh Siswa",
    "Persetujuan Lokasi dan Penetapan Guru Pembimbing",
    "Penyusunan Proposal oleh Siswa",
    "Pelaksanaan PKL di DU/DI",
    "Pengisian Kartu Kendali Harian",
    "Validasi Kendali oleh Pembimbing Lapangan/Sekolah",
    "Penyusunan Laporan Akhir (Logbook)",
    "Sidang PKL dan Penilaian Akhir"
];
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
            --simantap-blue-dark: #224ebe; 
            --simantap-blue-light: #4e73df; 
            --simantap-accent: #1cc88a; 
        }
        body { padding-top: 70px; background-color: #f4f7f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; }
        .bg-biru { background-color: var(--simantap-blue-dark) !important; }
        .fixed-top { z-index: 1051 !important; }

        .page-content-wrapper { min-height: calc(100vh - 70px); padding: 30px 0; }
        
        /* HEADER UTAMA HALAMAN */
        .info-header h1 { 
            color: var(--simantap-blue-dark); 
            font-size: 2.5rem; 
            font-weight: 700; 
            margin-bottom: 5px !important; 
        }
        .info-header { text-align: center; margin-bottom: 40px; }
        
        /* CARD PROSEDUR */
        .prosedur-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .prosedur-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px;
            border-left: 4px solid var(--simantap-blue-light);
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        .prosedur-item .step-number {
            font-size: 1.2rem;
            font-weight: 700;
            color: white;
            background-color: var(--simantap-blue-dark);
            border-radius: 50%;
            width: 35px;
            height: 35px;
            text-align: center;
            line-height: 35px;
            margin-right: 15px;
            flex-shrink: 0;
        }
        .prosedur-item p {
            margin: 0;
            font-size: 1.1em;
            color: #555;
            font-weight: 500;
        }

        /* HIGHLIGHT BOX */
        .highlight-box {
            background-color: #EBF8FF; 
            border-left: 5px solid var(--simantap-blue-light);
            padding: 15px;
            margin-top: 40px;
            border-radius: 5px;
            font-style: italic;
            color: #1E3A8A;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* NAVBAR FIXES (Diambil dari header-publik.php untuk konsistensi) */
        .navbar-brand { font-weight: 700 !important; }
        .navbar-nav .nav-link { color: white !important; font-weight: 500; }
        .navbar-nav .nav-link.active { font-weight: 700; border-bottom: none; }
    </style>
</head> 
<body>   
    
    <?php 
    // =================================================================
    // 🚨 PANGGIL HEADER PUBLIK
    // =================================================================
    // Asumsi file header_publik.php berada di folder panel
    include 'panel/header-publik.php'; 
    // =================================================================
    ?>
    
    <div class="container page-content-wrapper">
        <div class="info-header">
            <h1 class="mb-3"><i class="fas fa-tasks me-2"></i> <?php echo $page_title; ?></h1>
            <p class="text-secondary">
                Halaman ini menjelaskan alur kerja dan prosedur resmi yang harus dilalui siswa selama program PKL, 
                termasuk kewajiban pengisian **Kartu Kendali Harian**.
            </p>
        </div>

        <div class="prosedur-card mx-auto" style="max-width: 800px;">
            <h2 class="text-center mb-4" style="font-size: 1.8rem; color: var(--simantap-blue-dark);">8 Langkah Prosedur PKL DPIB</h2>
            
            <?php 
            $i = 1;
            foreach ($prosedur as $step): ?>
                <div class="prosedur-item">
                    <span class="step-number"><?php echo $i++; ?></span>
                    <p><?php echo htmlspecialchars($step); ?></p>
                </div>
            <?php endforeach; ?>

            <div class="highlight-box mt-5">
                <i class="fas fa-clipboard-list me-1"></i> **PENTING:** Kartu Kendali harus diisi **SETIAP HARI** dan divalidasi oleh Pembimbing Lapangan secara berkala sebagai bukti pelaksanaan PKL.
            </div>
            
        </div>
        
    </div>
    
    <?php include 'panel/public_footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"></script> 
    <script type="text/javascript">
    // FUNGSI REALTIME CLOCK LENGKAP UNTUK UPDATE DETIK (Harus ada di setiap halaman)
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
</html>