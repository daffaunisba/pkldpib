<?php 
// pembimbing-lapangan.php (PUBLIK) - Menampilkan daftar Guru Pembimbing dan Lokasi Bimbingan mereka.

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
    $error_message_koneksi = "❌ Koneksi database GAGAL! File koneksi tidak ditemukan atau gagal terinisialisasi.";
    $koneksi = null;
}

$page_title = "Daftar Guru Pembimbing";
$guru_data_list = [];
$error_message = null; 
$current_page = basename(__FILE__); 

if (!function_exists('formatTanggalIndo')) {
    function formatTanggalIndo($date_str = null) {
        if (empty($date_str)) { $timestamp = time(); } else { $timestamp = strtotime($date_str); }
        $hari_indo = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $bulan_indo = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $hari = $hari_indo[date('w', $timestamp)];
        $tgl = date('d', $timestamp);
        $bln = $bulan_indo[(int)date('m', $timestamp)];
        $thn = date('Y', $timestamp);
        $waktu = date('H:i:s', $timestamp);
        return "{$hari}, {$tgl} {$bln} {$thn}, {$waktu} WIB"; 
    }
}

// ---------------------------------------------------------------------
// 2. QUERY DATA GURU DAN LOKASI BIMBINGAN
// ---------------------------------------------------------------------
if (isset($koneksi) && $koneksi->connect_error === null) {
    $guru_query_sql = "
        SELECT 
            g.guru_id,
            g.nama_guru,
            g.no_hp,
            COUNT(l.lokasi_id) AS total_lokasi_bimbingan,
            GROUP_CONCAT(l.nama_lokasi ORDER BY l.nama_lokasi ASC SEPARATOR '@@@') AS list_lokasi
        FROM guru g
        LEFT JOIN lokasi_pkl l ON g.guru_id = l.guru_id
        GROUP BY g.guru_id, g.nama_guru, g.no_hp
        ORDER BY g.nama_guru ASC
    ";

    $guru_query = $koneksi->query($guru_query_sql);
    if ($guru_query) {
        while ($row = $guru_query->fetch_assoc()) {
            $guru_data_list[] = $row;
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
        .planet-1 { width: 100px; height: 100px; background: linear-gradient(135deg, #fcd34d 0%, #d97706 100%); top: 15%; right: 10%; animation: floatObj 8s ease-in-out infinite; }
        .planet-2 { width: 50px; height: 50px; background: linear-gradient(135deg, #f472b6 0%, #db2777 100%); bottom: 25%; left: 12%; animation: floatObj 10s ease-in-out infinite reverse; }
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
        /* --- BAGIAN BAWAH TERANG (CLEAN WHITE) ---            */
        /* ==================================================== */
        .page-content-wrapper { position: relative; z-index: 5; min-height: calc(100vh - 70px); padding: 0 15px 50px; width: 100%; max-width: 1100px; margin: 0 auto; }

        .content-card {
            background: white; padding: 40px; border-radius: 25px;
            box-shadow: var(--card-shadow); border: 1px solid #f1f5f9;
            margin-top: -60px; position: relative; z-index: 10;
        }
        
        .content-card h2 { color: var(--accent-dark); border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 25px; font-size: 1.8rem; font-weight: 800; }

        /* --- TABLE STYLING --- */
        .table-wrapper-outer { border-radius: 15px; overflow: hidden; border: 1px solid #e2e8f0; margin-top: 20px; }
        .table-responsive { overflow-x: auto !important; -webkit-overflow-scrolling: touch; margin: 0; border: none; border-radius: 0; }
        
        .table-guru { margin-bottom: 0; background: white; white-space: nowrap; border-collapse: collapse; width: 100%; }
        .table-guru thead th { 
            background: var(--primary-grad) !important; color: white !important; 
            border: none !important; border-right: 1px solid rgba(255,255,255,0.2) !important;
            padding: 15px 20px; font-weight: 800; font-size: 0.95rem; text-align: center; vertical-align: middle;
        }
        .table-guru thead th:last-child { border-right: none !important; }
        
        .table-guru tbody td { 
            background: white !important; color: var(--text-dark) !important; 
            border-bottom: 1px solid #f1f5f9; border-right: 1px solid #f1f5f9;
            padding: 15px 20px; vertical-align: top; font-size: 0.95rem; font-weight: 600;
        }
        .table-guru tbody td:last-child { border-right: none; }
        .table-guru tbody tr:hover td { background-color: #f8fafc !important; }

        /* List Lokasi */
        .lokasi-bimbingan-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 6px; }
        .lokasi-bimbingan-list li { background-color: #f8fafc; color: var(--accent-dark); padding: 8px 12px; border-radius: 8px; font-size: 0.85rem; font-weight: 700; border: 1px solid #e2e8f0; transition: 0.2s;}
        .lokasi-bimbingan-list li:hover { background-color: var(--accent-purple); color: white; border-color: var(--accent-purple); transform: translateX(3px);}
        
        .wa-link { color: #10b981; font-weight: 800; text-decoration: none; padding: 5px 12px; background: rgba(16, 185, 129, 0.1); border-radius: 50px; display: inline-flex; align-items: center; transition: 0.3s;}
        .wa-link:hover { background: #10b981; color: white; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(16,185,129,0.3);}

        /* REALTIME CLOCK */
        .fixed-top { z-index: 1051 !important; }
        #realtime-clock-desktop { color: #fcd34d; font-size: 0.95rem; font-weight: 700; text-shadow: 0 0 5px rgba(0, 0, 0, 0.5); }
        #realtime-clock-mobile { color: #fcd34d; font-size: 0.9rem; }

        /* --- MOBILE OPTIMIZATION --- */
        @media (max-width: 991.98px) { 
            .hero-title { font-size: 2.2rem; padding: 0 15px;}
            .content-card { padding: 25px 20px; margin-top: -40px; border-radius: 20px; }
            .content-card h2 { font-size: 1.4rem; }
        }
    </style>
</head> 
<body>   
    
    <?php include 'panel/header-publik.php'; ?>
    
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
                <i class="fas fa-user-tie me-2 text-warning"></i> Data Personel
            </div>
            <h1 class="hero-title"><?php echo $page_title; ?></h1>                      
            
            <p class="mt-3" style="max-width: 650px; margin: 0 auto; font-size: 0.95rem; color: rgba(255,255,255,0.95); line-height: 1.6; text-shadow: 0 2px 4px rgba(0,0,0,0.5);">
                Berikut adalah daftar guru pembimbing PKL dari Program Keahlian DPIB beserta lokasi industri/perusahaan yang berada di bawah bimbingan mereka.
            </p>
        </div>        

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

    <div class="page-content-wrapper">
        <div class="content-card">
            <h2>Daftar Guru Pembimbing & Mitra</h2>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger rounded-4 fw-bold border-0 shadow-sm mt-3" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i> KESALAHAN! <?php echo $error_message; ?>
                </div>
            <?php elseif (empty($guru_data_list)): ?>
                <div class="alert alert-warning rounded-4 fw-bold border-0 mt-3 shadow-sm" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> Data Guru Pembimbing belum tersedia saat ini.
                </div>
            <?php else: ?>
            
            <div class="table-wrapper-outer">
                <div class="table-responsive">
                    <table class="table table-guru mb-0">
                        <thead>
                            <tr>
                                <th style="width: 5%;">No</th>
                                <th style="width: 25%;" class="text-start">Nama Pembimbing</th>
                                <th style="width: 25%;" class="text-start">Kontak WhatsApp</th> 
                                <th style="width: 45%;" class="text-start">Daftar Lokasi Bimbingan (Industri)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1; 
                            // Pesan WhatsApp default
                            $default_message = urlencode("Assalamu'alaikum Wr. Wb. Selamat pagi, Bapak/Ibu. Mohon maaf menggagu waktunya, saya [Nama Kamu] siswa PKL DPIB ingin menanyakan mengenai informasi lebih lanjut [Informasi yang ingin kamu dapatkan/sampaikan] Wassalamualaikum Wr. Wb.");
                            
                            foreach ($guru_data_list as $guru): 
                                $contact_data = htmlspecialchars($guru['no_hp'] ?? '-');
                                $clean_number = preg_replace('/[^0-9]/', '', $contact_data);
                                
                                if (substr($clean_number, 0, 1) === '0') {
                                    $wa_number = '62' . substr($clean_number, 1);
                                } elseif (substr($clean_number, 0, 2) === '62') {
                                    $wa_number = $clean_number;
                                } else {
                                    $wa_number = false; 
                                }
                            ?>
                            <tr>
                                <td class="text-center text-muted"><?php echo $no++; ?></td>
                                <td class="text-start fs-6" style="color: var(--accent-dark) !important; font-weight:800;">
                                    <i class="fas fa-user-circle me-2 text-muted"></i><?php echo htmlspecialchars($guru['nama_guru'] ?? 'N/A'); ?>
                                </td>
                                <td class="text-start align-middle">
                                    <?php if ($wa_number && $contact_data != '-'): ?>
                                        <a href="https://wa.me/<?php echo $wa_number; ?>?text=<?php echo $default_message; ?>" target="_blank" class="wa-link">
                                            <i class="fab fa-whatsapp fs-5"></i> <span class="ms-1"><?php echo $contact_data; ?></span>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic"><i class="fas fa-phone-slash me-1"></i> Tidak Tersedia</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-start">
                                    <?php 
                                    if ($guru['total_lokasi_bimbingan'] > 0) {
                                        $lokasi_array = explode('@@@', $guru['list_lokasi']);
                                        echo '<ul class="lokasi-bimbingan-list">'; 
                                        foreach ($lokasi_array as $lokasi) {
                                            echo '<li><i class="fas fa-building me-2 opacity-50"></i>' . htmlspecialchars(trim($lokasi)) . '</li>';
                                        }
                                        echo '</ul>';
                                    } else {
                                        echo '<span class="text-danger fw-bold"><i class="fas fa-times-circle me-1"></i> Belum diploting ke lokasi manapun.</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
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