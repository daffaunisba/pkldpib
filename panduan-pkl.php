<?php 
// panduan-pkl.php - Halaman publik untuk panduan PKL

// =================================================================
// 🚨 KONEKSI DATABASE & DEFINISI VARIABEL UNTUK HEADER
// =================================================================

// 1. Tentukan path koneksi secara kondisional (lebih ringkas)
$koneksi = null;
$error_message_koneksi = null;
$paths_to_check = ['config/db-koneksi.php', '../config/db-koneksi.php', 'db-koneksi.php'];

foreach ($paths_to_check as $path) {
    if (file_exists($path)) {
        include $path; // Setelah ini, variabel $koneksi harusnya tersedia
        break;
    }
}

if (!isset($koneksi) || !($koneksi instanceof mysqli)) {
    $error_message_koneksi = "❌ Koneksi database GAGAL! File koneksi tidak ditemukan atau gagal terinisialisasi.";
}

$page_title = "Panduan & Prosedur PKL DPIB";
$error_message = null; 
$data_formats = [];
// Path relatif dari folder root, jika file ini diakses dari root atau folder yang sama
$upload_base_path = 'uploads/format/'; 

// Definisikan halaman saat ini agar header bisa menandai link aktif
$current_page = basename(__FILE__);
// =================================================================

// 2. Fungsi formatTanggalIndo
if (!function_exists('formatTanggalIndo')) {
    function formatTanggalIndo($date_str = null) {
        if (empty($date_str)) {
            try {
                $tz = new DateTimeZone('Asia/Jakarta');
                $now = new DateTime('now', $tz);
                $timestamp = $now->getTimestamp();
            } catch (Exception $e) {
                $timestamp = time(); 
            }
        } else {
            $timestamp = strtotime($date_str);
        }
        
        $hari_indo = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $bulan_indo = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        
        $hari = $hari_indo[date('w', $timestamp)];
        $tgl = date('d', $timestamp);
        $bln = $bulan_indo[(int)date('m', $timestamp)];
        $thn = date('Y', $timestamp);
        $waktu = date('H:i:s', $timestamp);
        
        return "{$hari}, {$tgl} {$bln} {$thn}, {$waktu} WIB"; 
    }
}

// 3. Ambil Data dari Database
if (isset($koneksi) && $koneksi instanceof mysqli && $koneksi->connect_error === null) {
    $formats_result = $koneksi->query("SELECT tipe, deskripsi, file_path FROM format_laporan");
    if ($formats_result) {
        while ($row = $formats_result->fetch_assoc()) {
            $data_formats[$row['tipe']] = $row;
        }
    } else {
        $error_message = "❌ Gagal mengambil data format: " . $koneksi->error;
    }
} else {
    $error_message = $error_message_koneksi;
}

$proposal_data = $data_formats['proposal'] ?? ['deskripsi' => 'Silakan download proposal resmi untuk pengajuan PKL Anda.', 'file_path' => null];
$pelaporan_data = $data_formats['pelaporan'] ?? ['deskripsi' => 'Silakan download contoh format pelaporan sidang PKL.', 'file_path' => null];
?>
<!doctype html>
<html lang="id">  
<head>    
    <meta charset="utf-8">    
    <meta name="viewport" content="initial-scale=1.0, width=device-width">    
    <title><?php echo $page_title; ?> | Si Mantap</title>    
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script> 
    
    <style>
        /* ========================================================= */
        /* CLEAN & COLORFUL THEME (GALAXY TOP, WHITE BOTTOM)         */
        /* ========================================================= */
        :root {
            --primary-grad: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --accent-purple: #667eea;
            --accent-dark: #764ba2;
            --bg-light: #f8fafc;
            --text-dark: #1e293b;
            --card-shadow: 0 15px 40px rgba(0,0,0,0.04);
            
            /* Warna spesifik kartu download */
            --download-blue-bg: #eff6ff;
            --download-blue-text: #3b82f6;
            --download-pink-bg: #fdf2f8;
            --download-pink-text: #ec4899;
        }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: var(--bg-light); 
            color: var(--text-dark);
            overflow-x: hidden;
            padding-top: 0 !important;
        }

        /* --- NAVBAR KACA --- */
        .bg-biru { 
            background-color: rgba(30, 41, 59, 0.85) !important; 
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border-bottom: 1px solid rgba(255,255,255,0.05);
            transition: all 0.3s ease;
        }
        .navbar-brand { font-weight: 800; font-size: 1.3rem; color: #fff !important; }
        .nav-link { color: rgba(255,255,255,0.8) !important; font-weight: 600; }
        .nav-link.active, .nav-link:hover { color: #fff !important; text-shadow: 0 0 10px rgba(255,255,255,0.5); }
        .navbar-toggler { border: none !important; }

        .btn-login-admin { 
            background-color: rgba(255,255,255,0.15) !important; color: white !important; 
            font-weight: 700 !important; border: 1px solid rgba(255,255,255,0.3) !important; 
            border-radius: 50px; padding: 8px 20px; transition: 0.3s;
        }
        .btn-login-admin:hover { background-color: white !important; color: var(--accent-dark) !important; transform: translateY(-1px); }

        /* --- HERO SECTION (GALAXY) --- */
        .wrapper { 
            position: relative; z-index: 1; padding-top: 130px; padding-bottom: 80px; 
            background: var(--primary-grad); overflow: hidden; text-align: center;
        }
        .hero-title { font-size: 3.5rem; font-weight: 900; color: white; text-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .wrapper::before { content: ''; position: absolute; top: -15%; left: -10%; width: 50vw; height: 50vw; background: rgba(255, 255, 255, 0.04); border-radius: 50%; z-index: 0; pointer-events: none;}
        .wrapper::after { content: ''; position: absolute; bottom: 10%; right: -5%; width: 40vw; height: 40vw; background: rgba(255, 255, 255, 0.04); border-radius: 50%; z-index: 0; pointer-events: none;}

        /* SPACE ELEMENTS */
        .space-elements { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1; pointer-events: none; }
        .star { position: absolute; background: white; border-radius: 50%; animation: twinkle 3s infinite ease-in-out alternate; }
        @keyframes twinkle { 0% { opacity: 0.2; transform: scale(0.8); } 100% { opacity: 1; transform: scale(1.2); box-shadow: 0 0 10px #fff; } }
        .shooting-star { position: absolute; top: 15%; right: -20%; width: 150px; height: 2px; background: linear-gradient(90deg, rgba(255,255,255,0), #fff); animation: shootingStar 7s linear infinite; transform: rotate(-45deg); z-index: 1; }
        @keyframes shootingStar { 0% { transform: translate(0, 0) rotate(-45deg); opacity: 1; } 15% { transform: translate(-100vw, 100vh) rotate(-45deg); opacity: 0; } 100% { transform: translate(-100vw, 100vh) rotate(-45deg); opacity: 0; } }
        .planet { position: absolute; border-radius: 50%; box-shadow: inset -15px -15px 25px rgba(0,0,0,0.3), 0 0 20px rgba(255,255,255,0.1); }
        .planet-1 { width: 90px; height: 90px; background: linear-gradient(135deg, #fcd34d 0%, #d97706 100%); top: 18%; right: 12%; animation: floatObj 8s ease-in-out infinite; }
        .planet-2 { width: 50px; height: 50px; background: linear-gradient(135deg, #f472b6 0%, #db2777 100%); bottom: 25%; left: 10%; animation: floatObj 10s ease-in-out infinite reverse; }
        @keyframes floatObj { 0%, 100% { transform: translateY(0) rotate(0deg); } 50% { transform: translateY(-25px) rotate(5deg); } }

        /* SVG Gelombang Transition */
        .waves { position: absolute; bottom: 0; left: 0; width: 100%; height: 12vh; min-height: 80px; max-height: 120px; margin-bottom: -7px; z-index: 2; pointer-events: none; }
        .parallax > use { animation: move-forever 25s cubic-bezier(.55,.5,.45,.5) infinite; }
        .parallax > use:nth-child(1) { animation-delay: -2s; animation-duration: 7s; }
        .parallax > use:nth-child(2) { animation-delay: -3s; animation-duration: 10s; }
        .parallax > use:nth-child(3) { animation-delay: -4s; animation-duration: 13s; }
        .parallax > use:nth-child(4) { animation-delay: -5s; animation-duration: 20s; }
        @keyframes move-forever { 0% { transform: translate3d(-90px,0,0); } 100% { transform: translate3d(85px,0,0); } }


        /* ==================================================== */
        /* --- BAGIAN BAWAH TERANG (CLEAN WHITE CARDS) ---      */
        /* ==================================================== */
        .page-content-wrapper { position: relative; z-index: 5; min-height: calc(100vh - 70px); padding: 0 15px 50px; width: 100%; max-width: 1100px; margin: 0 auto; }

        .download-card {
            background: white; padding: 40px 30px; border-radius: 25px; text-align: center;
            box-shadow: var(--card-shadow); border: 1px solid #f1f5f9; transition: 0.3s;
            height: 100%; display: flex; flex-direction: column; justify-content: center;
            margin-top: -60px; /* Overlap ke Hero Section */
            position: relative; z-index: 10;
        }
        .download-card:hover { transform: translateY(-10px); box-shadow: 0 20px 40px rgba(102,126,234,0.12); border-color: rgba(102,126,234,0.2); }
        
        .download-card .icon-wrapper {
            width: 90px; height: 90px; border-radius: 50%; margin: 0 auto 20px;
            display: flex; align-items: center; justify-content: center; transition: 0.3s;
        }
        .download-card:hover .icon-wrapper { transform: scale(1.1); }
        .download-card .icon-wrapper i { font-size: 3rem; }

        .card-proposal .icon-wrapper { background-color: var(--download-blue-bg); }
        .card-proposal .icon-wrapper i { color: var(--download-blue-text); }
        .card-pelaporan .icon-wrapper { background-color: var(--download-pink-bg); }
        .card-pelaporan .icon-wrapper i { color: var(--download-pink-text); }
        
        .download-card h3 { font-size: 1.4rem; font-weight: 800; color: var(--text-dark); margin-bottom: 15px; }
        .download-card p { font-size: 0.95rem; color: #64748b; font-weight: 500; min-height: 50px; margin-bottom: 25px;}

        .btn-download {
            display: inline-block; padding: 12px 25px; border-radius: 50px; font-weight: 700;
            text-decoration: none; transition: 0.3s; text-transform: uppercase; letter-spacing: 0.5px; font-size: 0.85rem;
        }
        .btn-proposal { background: var(--download-blue-bg); color: var(--download-blue-text); border: 2px solid transparent; }
        .btn-proposal:hover { background: var(--download-blue-text); color: white; box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3); }
        .btn-pelaporan { background: var(--download-pink-bg); color: var(--download-pink-text); border: 2px solid transparent; }
        .btn-pelaporan:hover { background: var(--download-pink-text); color: white; box-shadow: 0 8px 20px rgba(236, 72, 153, 0.3); }

        .highlight-box {
            background-color: #f8fafc; border-left: 5px solid var(--accent-purple); padding: 20px 25px;
            margin-top: 30px; border-radius: 15px; font-size: 0.95rem; color: var(--text-dark); font-weight: 600;
            box-shadow: var(--card-shadow); border-top: 1px solid #f1f5f9; border-right: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9;
        }

        /* REALTIME CLOCK */
        .fixed-top { z-index: 1051 !important; }
        #realtime-clock-desktop { color: #fcd34d; font-size: 0.95rem; font-weight: 700; text-shadow: 0 0 5px rgba(0, 0, 0, 0.5); }
        #realtime-clock-mobile { color: #fcd34d; font-size: 0.9rem; }

        /* --- MOBILE OPTIMIZATION --- */
        @media (max-width: 991.98px) { 
            .hero-title { font-size: 2.2rem; padding: 0 15px;}
            .download-card { padding: 30px 20px; margin-top: -30px; border-radius: 20px; margin-bottom: 15px; }
            .download-card p { min-height: auto; margin-bottom: 20px;}
            .btn-download { width: 100%; }
        }
    </style>
</head> 
<body>   
    
    <?php include 'panel/header-publik.php'; ?>
    
    <!-- HERO SECTION (GALAXY) -->
    <div class="wrapper" id="top">      
        
        <div class="space-elements">
            <div class="star" style="top: 15%; left: 25%; width: 4px; height: 4px; animation-delay: 0s;"></div>
            <div class="star" style="top: 30%; left: 85%; width: 6px; height: 6px; animation-delay: 1s;"></div>
            <div class="star" style="top: 65%; left: 10%; width: 3px; height: 3px; animation-delay: 0.5s;"></div>
            <div class="star" style="top: 75%; left: 75%; width: 5px; height: 5px; animation-delay: 1.5s;"></div>
            <div class="shooting-star"></div>
            <div class="planet planet-1"></div>
            <div class="planet planet-2"></div>
        </div>

        <div class="container position-relative" style="z-index: 5;">          
            <div class="d-inline-block mb-3" style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); padding: 6px 18px; border-radius: 50px; font-size: 0.85rem; font-weight: 800; border: 1px solid rgba(255,255,255,0.4); color: white;">
                <i class="fas fa-folder-open me-2 text-warning"></i> Pusat Unduhan
            </div>
            <h1 class="hero-title"><?php echo $page_title; ?></h1>                      
            
            <p class="mt-3" style="max-width: 650px; margin: 0 auto; font-size: 0.95rem; color: rgba(255,255,255,0.95); line-height: 1.6; text-shadow: 0 2px 4px rgba(0,0,0,0.5);">
                Untuk seluruh siswa yang akan melaksanakan program PKL, silakan mengunduh format dokumen resmi sebelum keberangkatan dan berdiskusi dengan guru pembimbing.
            </p>
        </div>        

        <!-- ANIMATED WAVES BOTTOM -->
        <svg class="waves" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 24 150 28" preserveAspectRatio="none" shape-rendering="auto">
            <defs><path id="gentle-wave" d="M-160 44c30 0 58-18 88-18s 58 18 88 18 58-18 88-18 58 18 88 18 v44h-352z" /></defs>
            <g class="parallax">
                <use xlink:href="#gentle-wave" x="48" y="0" fill="rgba(248, 250, 252, 0.7)" />
                <use xlink:href="#gentle-wave" x="48" y="3" fill="rgba(248, 250, 252, 0.5)" />
                <use xlink:href="#gentle-wave" x="48" y="5" fill="rgba(248, 250, 252, 0.3)" />
                <use xlink:href="#gentle-wave" x="48" y="7" fill="#f8fafc" />
            </g>
        </svg>
    </div>   

    <!-- KONTEN BAWAH (CLEAN WHITE) -->
    <div class="page-content-wrapper">
        <div class="container">
            <?php if ($error_message): ?>
                <div class="alert alert-danger rounded-4 fw-bold border-0 shadow-sm mt-3" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="row justify-content-center">
                 <div class="col-12 col-lg-5 mb-4">
                    <div class="download-card card-proposal">
                        <div class="icon-wrapper">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h3>Format Proposal PKL</h3>
                        <p>
                            <?php echo nl2br(htmlspecialchars($proposal_data['deskripsi'])); ?>
                        </p>
                        <?php if ($proposal_data['file_path']): ?>
                            <a href="<?php echo $upload_base_path . urlencode($proposal_data['file_path']); ?>" target="_blank" class="btn-download btn-proposal mt-auto">
                                Unduh Proposal <i class="fas fa-cloud-download-alt ms-1"></i>
                            </a>
                        <?php else: ?>
                            <div class="alert alert-warning py-2 small mt-auto fw-bold rounded-pill" role="alert" style="background: var(--download-blue-bg); border: none; color: var(--download-blue-text);"><i class="fas fa-lock me-1"></i> Dokumen Belum Tersedia</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-12 col-lg-5 mb-4">
                    <div class="download-card card-pelaporan">
                        <div class="icon-wrapper">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <h3>Format Laporan Sidang</h3>
                        <p>
                            <?php echo nl2br(htmlspecialchars($pelaporan_data['deskripsi'])); ?>
                        </p>
                        <?php if ($pelaporan_data['file_path']): ?>
                            <a href="<?php echo $upload_base_path . urlencode($pelaporan_data['file_path']); ?>" target="_blank" class="btn-download btn-pelaporan mt-auto">
                                Unduh Pelaporan <i class="fas fa-cloud-download-alt ms-1"></i>
                            </a>
                        <?php else: ?>
                            <div class="alert alert-warning py-2 small mt-auto fw-bold rounded-pill" role="alert" style="background: var(--download-pink-bg); border: none; color: var(--download-pink-text);"><i class="fas fa-lock me-1"></i> Dokumen Belum Tersedia</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="highlight-box mx-auto" style="max-width: 900px;">
                <i class="fas fa-lightbulb me-2 text-warning fs-5 align-middle"></i> Segala pertanyaan mengenai prosedur atau cara pengisian dokumen dapat ditanyakan secara langsung kepada Koordinator PKL atau Guru Pembimbing di sekolah.
            </div>
        </div>
    </div>
    
    <?php include 'panel/public_footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"></script> 
    <script type="text/javascript">
    function updateClock() {
        const now = new Date();
        const localTime = new Date(now.toLocaleString("en-US", {timeZone: "Asia/Jakarta"}));

        const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

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
        if (clockDesktop) clockDesktop.textContent = timeString;

        const clockMobile = document.getElementById('realtime-clock-mobile');
        if (clockMobile) clockMobile.textContent = `${dateString} | ${hours}:${minutes}:${seconds} WIB`;
    }

    $(document).ready(function() { 
        updateClock(); 
        setInterval(updateClock, 1000); 
    });
    </script>
</body>
</html>