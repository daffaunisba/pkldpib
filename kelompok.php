<?php 
// kelompok.php - Daftar Kelompok PKL Ringkas & Grouping 1 Tempat
include 'config/db-koneksi.php'; 

$page_title = "Daftar Kelompok PKL";
$current_page = basename(__FILE__);

// Query untuk mengambil data dan mengurutkan berdasarkan nama_lokasi agar grouping rapi
$query = "
    SELECT 
        p.nisn, p.nama AS nama_siswa, p.kelas,
        l.nama_lokasi, l.alamat AS alamat_lokasi,
        g.nama_guru AS pembimbing,
        pr.nama_periode
    FROM peserta_didik p
    JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
    LEFT JOIN guru g ON l.guru_id = g.guru_id
    LEFT JOIN periode_pkl pr ON p.periode_id = pr.periode_id
    ORDER BY l.nama_lokasi ASC, p.nama ASC
";
$result = $koneksi->query($query);

// Logic hitung jumlah siswa per lokasi untuk rowspan
$rowspan_data = [];
$all_data = [];
if ($result) {
    while($row = $result->fetch_assoc()) {
        $all_data[] = $row;
        $loc = $row['nama_lokasi'];
        $rowspan_data[$loc] = ($rowspan_data[$loc] ?? 0) + 1;
    }
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
        .fixed-top { z-index: 1051 !important; }

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
        /* --- BAGIAN BAWAH TERANG (CLEAN WHITE CARDS) ---      */
        /* ==================================================== */
        .page-content-wrapper { position: relative; z-index: 5; min-height: calc(100vh - 70px); padding: 0 15px 50px; width: 100%; max-width: 1200px; margin: 0 auto; }

        .content-card {
            background: white; padding: 30px; border-radius: 25px;
            box-shadow: var(--card-shadow); border: 1px solid #f1f5f9;
            margin-top: -60px; position: relative; z-index: 10;
        }
        
        .content-card h2 { color: var(--accent-dark); border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px; font-size: 1.8rem; font-weight: 800; }

        /* --- TABLE STYLING --- */
        .table-wrapper-outer { border-radius: 15px; border: 1px solid #e2e8f0; overflow: hidden; margin-top: 20px; }
        
        /* Responsive Class Khusus */
        .custom-responsive { margin: 0; border: none; border-radius: 0; -webkit-overflow-scrolling: touch; }
        
        .table-perusahaan { margin-bottom: 0; background: white; border-collapse: collapse; width: 100%; }
        .table-perusahaan thead th { 
            background: var(--primary-grad) !important; 
            color: white !important; 
            border: none !important; border-right: 1px solid rgba(255,255,255,0.2) !important;
            padding: 15px; font-weight: 800; font-size: 0.95rem; text-align: center; vertical-align: middle;
            white-space: nowrap;
        }
        .table-perusahaan thead th:last-child { border-right: none !important; }
        
        .table-perusahaan tbody td { 
            background: white !important; color: var(--text-dark) !important; 
            border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0;
            padding: 15px; vertical-align: middle; font-size: 0.95rem; 
        }
        .table-perusahaan tbody td:last-child { border-right: none; }
        .table-perusahaan tbody tr:hover td { background-color: #f8fafc !important; }

        .txt-lokasi { font-weight: 800; color: var(--accent-dark); font-size: 1.05rem; }
        .alamat-kecil { display: block; font-size: 0.85rem; color: #64748b; font-weight: 500; margin-top: 5px; line-height: 1.4; }
        .student-name { font-weight: 600; color: #0f172a; }
        
        .alert-info-custom {
            background-color: rgba(102, 126, 234, 0.05); border: 1px solid rgba(102, 126, 234, 0.2);
            border-left: 5px solid var(--accent-purple); border-radius: 15px;
            color: var(--text-dark); padding: 20px; font-weight: 500;
        }

        /* --- MOBILE OPTIMIZATION --- */
        @media (max-width: 991.98px) { 
            .hero-title { font-size: 2.2rem; padding: 0 15px;}
            .content-card { padding: 25px 15px; margin-top: -40px; border-radius: 20px; }
            .content-card h2 { font-size: 1.4rem; }
            .table-perusahaan tbody td { font-size: 0.85rem; padding: 12px 10px; }
            .txt-lokasi { font-size: 0.95rem; }
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
                <i class="fas fa-users me-2 text-warning"></i> Pemetaan Siswa
            </div>
            <h1 class="hero-title"><?php echo $page_title; ?></h1>                       
            
            <p class="mt-3" style="max-width: 700px; margin: 0 auto; font-size: 0.95rem; color: rgba(255,255,255,0.95); line-height: 1.6; text-shadow: 0 2px 4px rgba(0,0,0,0.5);">
                Informasi rincian pembagian kelompok siswa berdasarkan lokasi instansi industri mitra dan guru pembimbing.
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
            <h2>Daftar Pengelompokan Siswa PKL</h2>
            <p class="text-muted fw-semibold mb-4">
                Berikut adalah rincian pembagian kelompok siswa berdasarkan lokasi industri mitra. Siswa yang berada dalam satu nomor urut (satu baris instansi) ditempatkan pada tempat yang sama.
            </p>

            <div class="table-wrapper-outer">
                <div class="table-responsive-lg custom-responsive">
                    <table class="table table-perusahaan">
                        <thead>
                            <tr>
                                <th style="width: 5%;">No</th>
                                <th style="width: 30%;" class="text-start">Tempat PKL</th>
                                <th style="width: 20%;" class="text-start">Pembimbing</th>
                                <th style="width: 30%;" class="text-start">Nama Siswa</th>
                                <th style="width: 15%;">Kelas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            $current_loc = "";
                            if (!empty($all_data)):
                                foreach ($all_data as $row): 
                            ?>
                                <tr>
                                    <?php if ($current_loc !== $row['nama_lokasi']): ?>
                                        <td rowspan="<?php echo $rowspan_data[$row['nama_lokasi']]; ?>" class="text-center fw-bold text-muted">
                                            <?php echo $no++; ?>
                                        </td>
                                        <td rowspan="<?php echo $rowspan_data[$row['nama_lokasi']]; ?>" class="text-start">
                                            <div class="txt-lokasi"><?php echo htmlspecialchars($row['nama_lokasi']); ?></div>
                                            <span class="alamat-kecil"><i class="fas fa-map-marker-alt me-1 text-muted"></i> <?php echo htmlspecialchars($row['alamat_lokasi']); ?></span>
                                        </td>
                                        <td rowspan="<?php echo $rowspan_data[$row['nama_lokasi']]; ?>" class="text-start fw-bold" style="color: #475569;">
                                            <i class="fas fa-user-tie me-2 text-primary" style="opacity: 0.7;"></i><?php echo htmlspecialchars($row['pembimbing'] ?? 'Belum Ditentukan'); ?>
                                        </td>
                                        <?php $current_loc = $row['nama_lokasi']; ?>
                                    <?php endif; ?>

                                    <td class="text-start student-name">
                                        <i class="fas fa-user-graduate me-2 text-muted" style="font-size: 0.8rem;"></i><?php echo htmlspecialchars($row['nama_siswa']); ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border px-3 py-2 rounded-pill"><?php echo htmlspecialchars($row['kelas']); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted fw-bold"><i class="fas fa-folder-open mb-2 fs-3 d-block"></i> Belum ada data kelompok yang terdaftar.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="alert alert-info-custom mt-4 d-flex align-items-center shadow-sm">
                <i class="fas fa-info-circle fa-2x me-3" style="color: var(--accent-purple);"></i> 
                <div style="font-size: 0.95rem;">
                    <strong style="color: var(--accent-dark);">Informasi Kelompok:</strong> Siswa yang berada dalam satu nomor urut yang sama merupakan rekan satu kelompok. Koordinasi teknis lebih lanjut dapat dilakukan langsung melalui Guru Pembimbing masing-masing.
                </div>
            </div>
        </div>
    </div>

    <?php include 'panel/public_footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>