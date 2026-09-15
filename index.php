<?php
// index.php - Halaman utama (Landing Page) PKL DPIB
include 'config/db-koneksi.php';

$limit = 10; // Batas data per halaman untuk tabel kuota
$page = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;
$start = ($page - 1) * $limit; 

// 1. Hitung Total Data Lokasi (Untuk Pagination)
$total_result = $koneksi->query("SELECT COUNT(lokasi_id) FROM lokasi_pkl");
$total_rows = $total_result->fetch_array()[0];
$total_pages = ceil($total_rows / $limit);

// 2. Ambil Data Lokasi untuk Halaman Saat Ini
$lokasi_data = $koneksi->query("
    SELECT 
        l.lokasi_id, 
        l.nama_lokasi, 
        l.jam_kerja, 
        l.alamat,    
        l.kuota_max, 
        (SELECT COUNT(id) FROM peserta_didik WHERE lokasi_id = l.lokasi_id) AS terisi
    FROM lokasi_pkl l
    ORDER BY l.nama_lokasi
    LIMIT $start, $limit
");

$lokasi_options = [];
if ($lokasi_data) {
    while ($row = $lokasi_data->fetch_assoc()) {
        $lokasi_options[] = $row;
    }
}
?>

<!doctype html>
<html lang="id">   
<head>      
    <meta charset="utf-8">      
    <meta http-equiv="X-UA-Compatible" content="IE=edge">      
    <meta name="viewport" content="initial-scale=1.0, width=device-width">      
    <meta name="keywords" content="PKL DPIB, Pendaftaran" />      
    <meta name="description" content="Sistem Informasi Pendaftaran Praktik Kerja Lapangan (PKL) DPIB" />      
    <title>Si Mantap | PKL DPIB</title>      
    <link rel="icon" type="image/x-icon" href="img/logobangunan.png">      

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
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
    }

    /* --- NAVBAR --- */
    .bg-biru { 
        background-color: rgba(30, 41, 59, 0.85) !important; 
        backdrop-filter: blur(15px);
        -webkit-backdrop-filter: blur(15px);
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    .navbar-brand { font-weight: 800; font-size: 1.3rem; color: #fff !important; }
    .nav-link { color: rgba(255,255,255,0.8) !important; font-weight: 600; }
    .nav-link.active, .nav-link:hover { color: #fff !important; text-shadow: 0 0 10px rgba(255,255,255,0.5); }
    .navbar-toggler { border: none !important; }

    .btn-login-admin { background-color: rgba(255,255,255,0.15) !important; color: white !important; font-weight: 700 !important; border: 1px solid rgba(255,255,255,0.3) !important; border-radius: 50px; padding: 8px 20px;}
    .btn-login-admin:hover { background-color: white !important; color: var(--accent-dark) !important; transform: translateY(-1px); }

    /* --- HERO SECTION (GALAXY) --- */
    .wrapper { 
        position: relative; 
        z-index: 1; 
        padding-top: 130px; 
        padding-bottom: 80px; 
        background: var(--primary-grad);
        overflow: hidden; 
    }
    
    .hero-title { font-size: 4rem; font-weight: 900; color: white; text-shadow: 0 10px 30px rgba(0,0,0,0.2); }

    .wrapper::before { content: ''; position: absolute; top: -15%; left: -10%; width: 50vw; height: 50vw; background: rgba(255, 255, 255, 0.04); border-radius: 50%; z-index: 0; pointer-events: none;}
    .wrapper::after { content: ''; position: absolute; bottom: 10%; right: -5%; width: 40vw; height: 40vw; background: rgba(255, 255, 255, 0.04); border-radius: 50%; z-index: 0; pointer-events: none;}

    /* SPACE ELEMENTS */
    .space-elements { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1; pointer-events: none; }
    .star { position: absolute; background: white; border-radius: 50%; animation: twinkle 3s infinite ease-in-out alternate; }
    @keyframes twinkle { 0% { opacity: 0.2; transform: scale(0.8); } 100% { opacity: 1; transform: scale(1.2); box-shadow: 0 0 10px #fff; } }
    
    .shooting-star { position: absolute; top: 15%; right: -20%; width: 150px; height: 2px; background: linear-gradient(90deg, rgba(255,255,255,0), #fff); animation: shootingStar 7s linear infinite; transform: rotate(-45deg); z-index: 1; }
    @keyframes shootingStar { 0% { transform: translate(0, 0) rotate(-45deg); opacity: 1; } 15% { transform: translate(-100vw, 100vh) rotate(-45deg); opacity: 0; } 100% { transform: translate(-100vw, 100vh) rotate(-45deg); opacity: 0; } }

    .planet { position: absolute; border-radius: 50%; box-shadow: inset -15px -15px 25px rgba(0,0,0,0.3), 0 0 20px rgba(255,255,255,0.1); }
    .planet-1 { width: 120px; height: 120px; background: linear-gradient(135deg, #fcd34d 0%, #d97706 100%); top: 15%; right: 5%; animation: floatObj 8s ease-in-out infinite; }
    .planet-2 { width: 60px; height: 60px; background: linear-gradient(135deg, #f472b6 0%, #db2777 100%); bottom: 25%; left: 8%; animation: floatObj 10s ease-in-out infinite reverse; }
    .planet-3 { width: 40px; height: 40px; background: linear-gradient(135deg, #38bdf8 0%, #0284c7 100%); top: 20%; left: 45%; animation: floatObj 6s ease-in-out infinite 1s; }
    @keyframes floatObj { 0%, 100% { transform: translateY(0) rotate(0deg); } 50% { transform: translateY(-30px) rotate(5deg); } }

    .rocket-container { position: absolute; bottom: 0; left: -100px; animation: flyRocket 18s linear infinite; filter: drop-shadow(0 10px 15px rgba(255,255,255,0.4)); }
    .rocket-icon { font-size: 4rem; color: #ffffff; transform: rotate(45deg); }
    @keyframes flyRocket { 0% { transform: translate(0, 0) scale(0.8); opacity: 0; } 10% { opacity: 1; } 80% { opacity: 1; transform: translate(70vw, -60vh) scale(1.3); } 100% { transform: translate(85vw, -80vh) scale(1.3); opacity: 0; } }

    .wrapper .container { position: relative; z-index: 5; }

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
    
    .content-card {
        background: white;
        border-radius: 30px;
        box-shadow: var(--card-shadow);
        border: 1px solid #f1f5f9;
        padding: 3rem;
        transition: 0.3s;
    }

    /* --- KARTU FITUR MENU CEPAT (DIUPGRADE LEBIH MENARIK) --- */
    .feature-icon-section { padding-top: 1rem; }
    .feature-card { 
        display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;
        text-decoration: none; color: var(--text-dark); transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
        padding: 35px 20px; margin: 10px; height: 100%;
        background: linear-gradient(145deg, #ffffff, #f8fafc); 
        border-radius: 25px; box-shadow: 0 10px 20px rgba(0,0,0,0.03); 
        border: 1px solid #e2e8f0;
        position: relative; overflow: hidden;
    }
    /* Efek garis bawah warna-warni saat hover */
    .feature-card::after {
        content: ''; position: absolute; bottom: 0; left: 0; width: 100%; height: 5px;
        background: var(--primary-grad); transform: scaleX(0); transition: transform 0.4s; transform-origin: left;
    }
    .feature-card:hover::after { transform: scaleX(1); }
    .feature-card:hover { transform: translateY(-12px); box-shadow: 0 20px 40px rgba(102,126,234,0.12); border-color: transparent; }
    
    .icon-wrapper { 
        background: #f1f5f9; color: var(--accent-purple);
        border-radius: 20px; width: 85px; height: 85px; display: flex; align-items: center; justify-content: center; 
        margin: 0 auto 20px auto; transition: 0.4s; box-shadow: inset 0 0 0 1px rgba(102,126,234,0.1);
    }
    .feature-card:hover .icon-wrapper { transform: scale(1.1) rotate(5deg); background: var(--primary-grad); color: white; box-shadow: 0 10px 20px rgba(102,126,234,0.3); }
    .icon-wrapper i { font-size: 2.5rem; transition: 0.4s; }

    /* --- SECTION TENTANG PKL (DIUPGRADE) --- */
    .about-box {
        background: linear-gradient(to right, #f8fafc, #ffffff);
        border-left: 6px solid var(--accent-purple);
        padding: 30px; border-radius: 0 20px 20px 0;
        box-shadow: inset 0 0 20px rgba(0,0,0,0.01);
    }
    .about-list { list-style: none; padding: 0; margin-top: 20px; }
    .about-list li { margin-bottom: 15px; display: flex; align-items: flex-start; font-weight: 500;}
    .about-list li i { color: #10b981; font-size: 1.2rem; margin-right: 15px; margin-top: 3px; }

    /* --- TABLE STATUS --- */
    #status, .about-section { padding: 40px 0; position: relative; z-index: 5; }
    
    .table-wrapper-outer { border-radius: 20px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: var(--card-shadow); background: white; }
    .table-responsive { overflow-x: auto !important; -webkit-overflow-scrolling: touch; margin: 0; border: none; border-radius: 0; }
    
    #lokasiTable { margin-bottom: 0; background: white; }
    #lokasiTable th { background: var(--primary-grad) !important; color: white !important; border: none !important; padding: 12px 10px; font-weight: 800; font-size: 0.9rem; white-space: nowrap; }
    #lokasiTable td { background: white !important; color: var(--text-dark) !important; border-bottom: 1px solid #f1f5f9; padding: 12px 10px; vertical-align: middle; font-size: 0.9rem; }
    #lokasiTable tbody tr:hover td { background-color: #f8fafc !important; }
    
    .td-lokasi { font-weight: 800; color: var(--accent-dark) !important; white-space: nowrap;}
    .td-alamat { min-width: 150px; max-width: 250px; white-space: normal; line-height: 1.4; }
    
    .search-box .form-control { background: white; border: 1px solid #cbd5e1; color: var(--text-dark); border-radius: 50px; padding: 10px 20px; font-weight: 600;}
    .search-box .form-control::placeholder { color: #94a3b8; }
    .search-box .form-control:focus { background: white; border-color: var(--accent-purple); box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1); outline: none;}

    .btn-outline-primary { border-color: var(--accent-purple); color: var(--accent-purple); }
    .btn-outline-primary:hover, .btn-primary.active { background: var(--primary-grad); border: none; color: white; box-shadow: 0 4px 10px rgba(102,126,234,0.3);}

    /* --- CTA BANNER PENDAFTARAN --- */
    .cta-banner {
        background: linear-gradient(135deg, var(--accent-dark), var(--accent-purple));
        border-radius: 35px;
        padding: 60px 40px;
        color: white;
        text-align: center;
        box-shadow: 0 20px 40px rgba(118, 75, 162, 0.25);
        position: relative;
        overflow: hidden;
        margin: 40px auto 60px;
        max-width: 1000px;
    }
    .cta-banner::before { content: ''; position: absolute; top: -50px; right: -50px; width: 200px; height: 200px; background: rgba(255,255,255,0.05); border-radius: 50%; pointer-events: none;}
    .cta-banner::after { content: ''; position: absolute; bottom: -50px; left: -50px; width: 150px; height: 150px; background: rgba(255,255,255,0.05); border-radius: 50%; pointer-events: none;}
    
    .btn-cta-submit { 
        background: white !important; 
        color: var(--accent-dark) !important; 
        font-weight: 800; 
        text-transform: uppercase; 
        letter-spacing: 1px; 
        transition: 0.4s;
        padding: 16px 45px;
        border-radius: 50px;
        display: inline-block;
        font-size: 1.05rem;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        text-decoration: none;
    }
    .btn-cta-submit:hover { 
        transform: translateY(-5px); 
        box-shadow: 0 15px 35px rgba(0,0,0,0.2) !important; 
        color: var(--accent-purple) !important;
    }

    /* --- LAIN-LAIN --- */
    html { scroll-padding-top: 100px; scroll-behavior: smooth; }
    #realtime-clock { color: #fcd34d; font-size: 0.95rem; font-weight: 700; text-shadow: 0 0 5px rgba(0, 0, 0, 0.5); }
    .first-letter-dropcap::first-letter { font-size: 4.5rem; font-weight: 900; float: left; line-height: 0.8; margin-right: 12px; color: var(--accent-purple); }

    /* --- RESPONSIVE FIXES (HP & TABLET) --- */
    @media (max-width: 768px) {
        .hero-title { font-size: 2.5rem; }
        .wrapper { padding-top: 100px; padding-bottom: 50px; text-align: center; }
        
        .content-card { padding: 30px 20px; border-radius: 20px;}
        .about-box { padding: 20px 15px; border-radius: 0 15px 15px 0;}
        
        .table-controls { flex-direction: column; align-items: flex-start; }
        .table-controls .search-box, .table-controls .search-box .form-control { width: 100%; max-width: none; }
        .table-info { margin-bottom: 10px; }
        .rocket-container { display: none; } /* Sembunyikan roket di HP */
        
        .cta-banner { padding: 40px 20px; border-radius: 25px;}
        .btn-cta-submit { width: 100%; padding: 14px 20px; font-size: 0.9rem;}
    }
    </style>
    
</head> 
<body>       

    <nav class="navbar navbar-expand-lg fixed-top bg-biru navbar-dark" id="hU">        
        <div class="container">          
            <a class="navbar-brand" href="#">          
                <img src="img/logobangunan.png" class="d-inline-block align-text-top me-2" alt="Logo" width="25" height="24">          
                PKL DPIB          
            </a>          
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">          
                <i class="fas fa-bars text-white fs-4"></i>          
            </button>          
            <div class="collapse navbar-collapse" id="navbarSupportedContent">            
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">              
                    <li class="nav-item"><a class="nav-link active" href="#top">Beranda</a></li>              
                    <li class="nav-item"><a class="nav-link" href="#about">Tentang PKL</a></li>
                    <li class="nav-item"><a class="nav-link" href="#status">Cek Kuota</a></li>              
                </ul>            
                <span id="realtime-clock" class="d-none d-lg-inline"></span>
                <ul class="navbar-nav mb-2 mb-lg-0">
                    <li class="nav-item"><a class="btn btn-login-admin" href="admin/login.php"><i class="fas fa-user-astronaut me-2"></i>Portal Pembimbing</a></li>
                </ul>
            </div>          
        </div>        
    </nav>        

    <div class="wrapper" id="top">      
        
        <div class="space-elements">
            <div class="star" style="top: 15%; left: 25%; width: 4px; height: 4px; animation-delay: 0s;"></div>
            <div class="star" style="top: 30%; left: 85%; width: 6px; height: 6px; animation-delay: 1s;"></div>
            <div class="star" style="top: 65%; left: 10%; width: 3px; height: 3px; animation-delay: 0.5s;"></div>
            <div class="star" style="top: 75%; left: 75%; width: 5px; height: 5px; animation-delay: 1.5s;"></div>
            <div class="shooting-star"></div>
            <div class="planet planet-1"></div>
            <div class="planet planet-2"></div>
            <div class="planet planet-3"></div>
            <div class="rocket-container"><i class="fas fa-rocket rocket-icon"></i></div>
        </div>

        <div class="container">          
            <div class="row align-items-center">            
                <div class="col-md-5 d-none d-md-block text-center">            
                    <img src="img/bekerja.png" class="img-fluid" alt="Ilustrasi Bekerja" style="animation: floatObj 5s ease-in-out infinite; max-height: 400px; filter: drop-shadow(0 20px 30px rgba(0,0,0,0.2));">          
                </div>              
                <div class="col-md-7 text-white">              
                    <div class="d-inline-block mb-3" style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); padding: 6px 18px; border-radius: 50px; font-size: 0.85rem; font-weight: 800; border: 1px solid rgba(255,255,255,0.4); color: white;">
                        <i class="fas fa-satellite me-2 text-warning"></i> v2.0 Si Mantap App
                    </div>
                    <h2 class="hero-title">Si Mantap</h2>                      
                    <h3 class="fw-normal mb-4" style="line-height: 1.6; font-size: 1.25rem; color: rgba(255,255,255,0.9);">Sistem Informasi Manajemen Praktek Kerja Lapangan DPIB SMEKISA.</h3>                       
                    
                    <a href="https://pkl.dpibsmekisa.my.id/daftar.php" class="btn btn-lg btn-warning fw-bold mt-2 px-4 py-2 rounded-pill text-dark shadow-sm" style="font-size:1.1rem; transition:0.3s;">Mulai Pendaftaran <i class="fas fa-rocket ms-2"></i></a>
                </div>        
            </div>
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
    
    <div class="container mb-5 py-3 position-relative z-index-5">
        <div class="text-center feature-icon-section" id="top-feature"> 
            <div class="display-6 fw-bold mb-5" style="color: var(--accent-dark);">Akses Menu Cepat</div>       
            <div class="row">
                <div class="col-md-4 col-6 mb-4">          
                    <a href="https://pkl.dpibsmekisa.my.id/siswa" class="feature-card">
                        <div class="icon-wrapper"><i class="fas fa-fingerprint"></i></div>          
                        <h3 class="fs-6 fw-bold mb-2">Portal Siswa</h3>          
                        <span class="small text-muted fw-semibold">Login presensi & jurnal harian</span>
                    </a>
                </div>           
                <div class="col-md-4 col-6 mb-4">          
                    <a href="informasi-periode.php" class="feature-card">
                        <div class="icon-wrapper"><i class="fas fa-calendar-alt"></i></div>          
                        <h3 class="fs-6 fw-bold mb-2">Jadwal Periode</h3>          
                        <span class="small text-muted fw-semibold">Timeline & gelombang PKL</span>        
                    </a>
                </div>          
                <div class="col-md-4 col-6 mb-4">          
                    <a href="panduan-pkl.php" class="feature-card">
                        <div class="icon-wrapper"><i class="fas fa-book"></i></div>          
                        <h3 class="fs-6 fw-bold mb-2">Panduan Buku</h3>          
                        <span class="small text-muted fw-semibold">Format & template laporan</span>    
                    </a>
                </div>                  
                <div class="col-md-4 col-6 mb-4">          
                    <a href="pembimbing-lapangan.php" class="feature-card">
                        <div class="icon-wrapper"><i class="fas fa-user-tie"></i></div>          
                        <h3 class="fs-6 fw-bold mb-2">Data Pembimbing</h3>          
                        <span class="small text-muted fw-semibold">Info pembimbing industri</span>        
                    </a>
                </div>          
                <div class="col-md-4 col-6 mb-4">          
                    <a href="profil-perusahaan.php" class="feature-card">
                        <div class="icon-wrapper"><i class="fas fa-building"></i></div>          
                        <h3 class="fs-6 fw-bold mb-2">Mitra Industri</h3>          
                        <span class="small text-muted fw-semibold">Profil perusahaan PKL</span>        
                    </a>
                </div>  
                <div class="col-md-4 col-6 mb-4">          
                    <a href="kelompok.php" class="feature-card">
                        <div class="icon-wrapper"><i class="fas fa-users"></i></div>          
                        <h3 class="fs-6 fw-bold mb-2">Hasil Plotting</h3>          
                        <span class="small text-muted fw-semibold">Cek kelompok lokasi</span>        
                    </a>
                </div>      
            </div>        
        </div>
        
        <section id="about" class="about-section mt-5">
            <h2 class="fw-bold text-center mb-5" style="color: var(--accent-dark);">Misi PKL DPIB</h2>
            <div class="row align-items-center content-card" style="margin:0;">
                <div class="col-md-7 pe-lg-5">
                    <p class="first-letter-dropcap text-muted">Praktek Kerja Lapangan (PKL) adalah kegiatan pendidikan, pelatihan, dan pembelajaran yang dilaksanakan di <span class="fw-bold text-dark">Dunia Usaha atau Dunia Industri (DU/DI)</span> yang relevan dengan kompetensi keahlian Desain Permodelan dan Informasi Bangunan (DPIB).</p>
                    
                    <div class="about-box mt-4 text-muted">
                        <p class="mb-0 fw-semibold text-dark">Tujuan utama PKL membekali siswa dengan pengalaman nyata:</p>
                        <ul class="about-list">
                            <li><i class="fas fa-check-circle"></i> Mengaplikasikan ilmu desain dan arsitektur yang didapat di sekolah.</li>
                            <li><i class="fas fa-check-circle"></i> Menumbuhkan etos kerja profesional dan kemandirian.</li>
                            <li><i class="fas fa-check-circle"></i> Terlibat langsung dalam proyek desain, 3D, atau survei lapangan.</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-5 text-center mt-5 mt-lg-0">
                    <img src="img/about.png" alt="Siswa DPIB Berpose" class="img-fluid" style="max-height: 380px; animation: floatObj 7s ease-in-out infinite alternate-reverse; filter: drop-shadow(0 15px 25px rgba(0,0,0,0.1));"/> 
                </div>
            </div>
        </section>

        <section id="status" class="mt-5">
            <h2 class="fw-bold text-center mb-4" style="color: var(--accent-dark);">Papan Ketersediaan Kuota</h2>
            
            <div class="content-card px-3 px-md-5">
                <div class="table-controls">
                    <div class="table-info">
                        Menampilkan <?php echo min($total_rows, $start + 1); ?> sampai <?php echo min($total_rows, $start + $limit); ?> dari <?php echo $total_rows; ?> total lokasi industri.
                    </div>
                    <div class="search-box">
                        <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Cari Mitra Perusahaan..." class="form-control shadow-sm" />
                    </div>
                </div>

                <div class="table-wrapper-outer">
                    <div class="table-responsive">
                        <table id="lokasiTable" class="table table-borderless table-hover mb-0"> 
                            <thead>
                                <tr>
                                    <th class="text-start rounded-start">Perusahaan/Instansi</th>
                                    <th class="text-center">Jam Kerja</th>
                                    <th class="text-start">Alamat Lengkap</th> 
                                    <th class="text-center">Kapasitas</th>
                                    <th class="text-center">Terisi</th>
                                    <th class="text-center">Sisa</th>
                                    <th class="text-center rounded-end">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($lokasi_options)) { ?>
                                    <tr><td colspan="7" class="text-danger text-center py-4 fw-bold">Belum ada data kemitraan PKL.</td></tr>
                                <?php } ?>
                                <?php 
                                foreach ($lokasi_options as $row) { 
                                    $sisa = $row['kuota_max'] - $row['terisi'];
                                    $status_class = ($sisa <= 0) ? 'bg-danger' : 'bg-success';
                                    $status_text = ($sisa <= 0) ? 'PENUH' : 'TERSEDIA';
                                ?>
                                <tr>
                                    <td class="text-start td-lokasi"><i class="fas fa-building me-2 text-muted"></i> <?php echo htmlspecialchars($row['nama_lokasi']); ?></td>
                                    <td class="text-center fw-semibold"><i class="far fa-clock me-1 text-muted"></i> <?php echo htmlspecialchars($row['jam_kerja']); ?></td>
                                    <td class="text-start td-alamat"><small class="text-muted fw-semibold"><?php echo htmlspecialchars($row['alamat']); ?></small></td> 
                                    <td class="text-center fw-bold text-muted"><?php echo $row['kuota_max']; ?></td>
                                    <td class="text-center fw-bold" style="color: var(--accent-purple) !important;"><?php echo $row['terisi']; ?></td> 
                                    <td class="text-center"><span class="badge <?php echo ($sisa <= 0) ? 'bg-danger' : 'bg-success'; ?> fw-bold px-2 py-1 rounded-pill shadow-sm"><?php echo $sisa; ?></span></td>
                                    <td class="text-center"><span class="badge <?php echo $status_class; ?> px-2 py-1 rounded-pill shadow-sm" style="letter-spacing:1px;"><?php echo $status_text; ?></span></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div> 
                
                <div class="pagination-nav mt-4 text-center">
                    <?php if ($total_pages > 1) { ?>
                        <?php 
                        $nav_start = max(1, $page - 2);
                        $nav_end = min($total_pages, $page + 2);
                        if ($nav_end - $nav_start < 4) {
                            if ($nav_start > 1) $nav_start = max(1, $nav_end - 4);
                            else $nav_end = min($total_pages, 5);
                        }
                        ?>
                        <?php if ($page > 1) { ?>
                            <a href="?p=<?php echo $page - 1; ?>#status" class="prev-next btn btn-sm btn-outline-primary fw-bold px-3 py-2 rounded-pill"><i class="fas fa-angle-left me-1"></i> Prev</a>
                        <?php } ?>
                        <?php for ($i = $nav_start; $i <= $nav_end; $i++) { ?>
                            <a href="?p=<?php echo $i; ?>#status" class="btn btn-sm <?php echo ($i == $page) ? 'btn-primary active' : 'btn-outline-primary'; ?> fw-bold mx-1 rounded-circle" style="width:35px; height:35px; display:inline-flex; align-items:center; justify-content:center;"><?php echo $i; ?></a>
                        <?php } ?>
                        <?php if ($page < $total_pages) { ?>
                            <a href="?p=<?php echo $page + 1; ?>#status" class="prev-next btn btn-sm btn-outline-primary fw-bold px-3 py-2 rounded-pill">Next <i class="fas fa-angle-right ms-1"></i></a>
                        <?php } ?>
                    <?php } ?>
                </div>
            </div>
        </section>
        
        <section class="mt-5 mb-5 px-3">
            <div class="cta-banner">
                <i class="fas fa-space-shuttle fa-4x mb-4 text-warning" style="animation: floatObj 3s infinite ease-in-out alternate;"></i>
                <h2 class="fw-bold mb-3" style="font-size: 2.2rem;">Siap Memulai Misi Magangmu?</h2>
                <p class="fs-5 mb-5 mx-auto" style="max-width: 600px; color: rgba(255,255,255,0.85); line-height:1.6;">Kapasitas lokasi industri terbatas! Segera tentukan titik pendaratan dan daftarkan dirimu ke lokasi PKL pilihan sekarang juga.</p>
                <a href="https://pkl.dpibsmekisa.my.id/daftar.php" class="btn-cta-submit">MELUNCUR KE FORM PENDAFTARAN <i class="fas fa-arrow-right ms-2"></i></a>
            </div>
        </section>

    </div>

    <div class="pt-5" style="background-color: #0f172a;">        
      <div class="container text-white">          
        <div class="row mt-4 pb-4">            
          <div class="col-md-5 mb-4 mb-md-0">            
            <img src="img/alamatkami.png" class="img-fluid rounded-4 shadow-lg" alt="Alamat Kami" style="border: 1px solid rgba(255,255,255,0.1);">          
          </div>          
          <div class="col-md">            
            <div class="row ps-lg-4">              
              <div class="col-md-7 mb-4 mb-md-0">                
                <h4 class="fw-bold mb-3"><i class="fas fa-school text-warning me-2"></i> SMK Islam 1 Blitar</h4>                
                <div class="fs-6 fw-light" style="line-height: 1.8; color: rgba(255,255,255,0.8);">                  
                  Jl. Musi No. 6 Kepanjenkidul, <br>Kota Blitar, Jawa Timur <br>                  
                  <i class="fas fa-phone-alt me-2 mt-2 text-info"></i> (0342) 802137 <br>                  
                  <i class="fas fa-envelope me-2 mt-2 text-warning"></i> smkislam@gmail.com              
                </div>
              </div>              
              <div class="col-md-5">                
                <h5 class="fw-bold mb-3 text-info">Radar Navigasi</h5>                
                <ul class="fs-6 list-unstyled fw-light" style="line-height: 2;">                  
                  <li><a href="#about" class="text-white text-decoration-none" style="opacity: 0.8; transition:0.3s;"><i class="fas fa-angle-right me-2 text-warning"></i> Tentang Program</a></li>                  
                  <li><a href="#status" class="text-white text-decoration-none" style="opacity: 0.8; transition:0.3s;"><i class="fas fa-angle-right me-2 text-warning"></i> Pantau Kuota</a></li>                  
                  <li><a href="https://pkl.dpibsmekisa.my.id/daftar.php" class="btn btn-sm btn-outline-info fw-bold mt-3 px-4 rounded-pill shadow-sm">Daftar Sekarang</a></li>
                </ul>              
              </div>            
            </div>
          </div>
        </div>        
      </div>      
      <div style="background: rgba(0,0,0,0.3); border-top: 1px solid rgba(255,255,255,0.05);">      
        <div class="container-fluid py-3 text-center">      
            <span class="fs-6 fw-light" style="color: rgba(255,255,255,0.6);">2026 &copy; Tim IT DPIB SMK Islam 1 Blitar</span>      
        </div>      
      </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"></script>      
    
    <script type="text/javascript">
    function updateClock() {
        const now = new Date();
        const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        const timeString = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()} | ${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}:${String(now.getSeconds()).padStart(2, '0')} WIB`;
        const clockElement = document.getElementById('realtime-clock');
        if (clockElement) clockElement.textContent = timeString;
    }

    function filterTable() {
        var input, filter, table, tr, td, i, j, txtValue;
        input = document.getElementById("searchInput");
        filter = input.value.toUpperCase();
        table = document.getElementById("lokasiTable");
        tr = table.getElementsByTagName("tr");
        for (i = 1; i < tr.length; i++) {
            tr[i].style.display = "none";
            td = tr[i].getElementsByTagName("td");
            for (j = 0; j < td.length; j++) {
                if (td[j]) {
                    txtValue = td[j].textContent || td[j].innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                        break;
                    }
                }
            }
        }
    }

    $(document).ready(function() {        
        updateClock(); 
        setInterval(updateClock, 1000); 
    });
    </script>
</body>
</html>