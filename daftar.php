<?php
// daftar.php - Halaman Khusus Formulir Pendaftaran PKL DPIB

include 'config/db-koneksi.php';

$message = '';
$page_title = "Formulir Pendaftaran PKL";

// === LOGIKA PENDAFTARAN ===
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nisn = $_POST['nisn'];
    $nama = $_POST['nama'];
    $kelas = $_POST['kelas'];
    $email = $_POST['email'];
    $no_hp = $_POST['no_hp']; 
    $lokasi_id = $_POST['lokasi_id'];
    $periode_id = $_POST['periode_id']; 
    
    // 1. CEK NISN
    $check_stmt = $koneksi->prepare("SELECT COUNT(*) FROM peserta_didik WHERE nisn = ?");
    $check_stmt->bind_param("s", $nisn);
    $check_stmt->execute();
    $check_stmt->bind_result($is_registered);
    $check_stmt->fetch();
    $check_stmt->close();

    if ($is_registered > 0) {
        $message = "<div class='alert alert-danger fw-bold rounded-4 border-0 shadow-sm'><i class='fas fa-times-circle me-2'></i> NISN <span class='text-dark'>$nisn</span> sudah terdaftar. Anda tidak bisa mendaftar dua kali.</div>";
    } else {
        // Cek Kuota Global
        $global_kuota_query = "
            SELECT 
                p.kuota_gelombang, 
                (SELECT COUNT(id) FROM peserta_didik WHERE periode_id = p.periode_id) AS terisi_global
            FROM periode_pkl p
            WHERE p.periode_id = ?
        ";
        $stmt_global_kuota = $koneksi->prepare($global_kuota_query);
        $stmt_global_kuota->bind_param("i", $periode_id);
        $stmt_global_kuota->execute();
        $result_global_kuota = $stmt_global_kuota->get_result();
        $data_global_kuota = $result_global_kuota->fetch_assoc();
        $stmt_global_kuota->close();

        $kuota_global_max = $data_global_kuota['kuota_gelombang'];
        $terisi_global = $data_global_kuota['terisi_global'];
        $sisa_global = $kuota_global_max - $terisi_global;

        if ($sisa_global <= 0) {
            $message = "<div class='alert alert-danger fw-bold rounded-4 border-0 shadow-sm'><i class='fas fa-exclamation-triangle me-2'></i> PERINGATAN! Periode PKL yang Anda pilih sudah PENUH (Kuota Maks: {$kuota_global_max}). Pendaftaran ditolak.</div>";
        } else {
            
            // 2. CEK KUOTA LOKASI
            $kuota_query = "
                SELECT l.kuota_max, COUNT(p.id) AS terisi, l.nama_lokasi
                FROM lokasi_pkl l
                LEFT JOIN peserta_didik p ON l.lokasi_id = p.lokasi_id AND p.periode_id = ? 
                WHERE l.lokasi_id = ? GROUP BY l.lokasi_id
            ";
            $stmt_kuota = $koneksi->prepare($kuota_query);
            $stmt_kuota->bind_param("ii", $periode_id, $lokasi_id);
            $stmt_kuota->execute();
            $result_kuota = $stmt_kuota->get_result();
            $data_kuota = $result_kuota->fetch_assoc();
            $stmt_kuota->close();
            
            if (!$data_kuota) {
                $message = "<div class='alert alert-danger fw-bold rounded-4 border-0 shadow-sm'>Gagal: Lokasi PKL tidak valid.</div>";
                goto end_post_logic;
            }

            $kuota_max = $data_kuota['kuota_max'];
            $terisi = $data_kuota['terisi'];
            $sisa_kuota = $kuota_max - $terisi;
            $lokasi_name = $data_kuota['nama_lokasi'];
            
            if ($sisa_kuota > 0) {
                // LOKASI DAN PERIODE TERSEDIA -> INSERT DATA
                $insert_stmt = $koneksi->prepare("INSERT INTO peserta_didik (nisn, nama, kelas, email, no_hp, lokasi_id, periode_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $insert_stmt->bind_param("sssssii", $nisn, $nama, $kelas, $email, $no_hp, $lokasi_id, $periode_id); 

                if ($insert_stmt->execute()) {
                    $message = "<div class='alert alert-success fw-bold rounded-4 border-0 shadow-sm'><i class='fas fa-check-circle me-2'></i> Pendaftaran di <span class='text-dark'>$lokasi_name</span> berhasil! Kuota tersisa di lokasi ini: " . ($sisa_kuota - 1) . " orang.</div>";
                } else {
                    $message = "<div class='alert alert-danger fw-bold rounded-4 border-0 shadow-sm'>Pendaftaran gagal: " . $insert_stmt->error . "</div>";
                }
                $insert_stmt->close();
            } else {
                $message = "<div class='alert alert-warning fw-bold rounded-4 border-0 shadow-sm'><i class='fas fa-exclamation-triangle me-2'></i> PERINGATAN! Tempat PKL <span class='text-dark'>$lokasi_name</span> sudah PENUH. Silakan pilih lokasi PKL lain.</div>";
            }
        }
    }
}
end_post_logic:
// === END LOGIKA PENDAFTARAN ===


// 3. Ambil Data Lokasi untuk Dropdown Form
$lokasi_data = $koneksi->query("
    SELECT 
        l.lokasi_id, l.nama_lokasi, l.alamat, l.kuota_max, 
        (SELECT COUNT(id) FROM peserta_didik WHERE lokasi_id = l.lokasi_id) AS terisi
    FROM lokasi_pkl l
    ORDER BY l.nama_lokasi
");

$lokasi_options = [];
if ($lokasi_data) {
    while ($row = $lokasi_data->fetch_assoc()) {
        $lokasi_options[] = $row;
    }
}

// 4. Ambil Data Periode PKL (Hanya Aktif)
$periode_data = $koneksi->query("
    SELECT 
        p.periode_id, p.nama_periode, p.tgl_mulai, p.tgl_akhir, p.kuota_gelombang,
        (SELECT COUNT(id) FROM peserta_didik WHERE periode_id = p.periode_id) AS kuota_terisi
    FROM periode_pkl p
    WHERE p.is_active = 1
    ORDER BY p.tgl_mulai ASC
");

$periode_options = [];
if ($periode_data) {
    while ($row = $periode_data->fetch_assoc()) {
        $periode_options[] = $row;
    }
}

$daftar_kelas = ["X DPIB 1", "X DPIB 2", "XI DPIB 1", "XI DPIB 2", "XI DPIB 3", "XII DPIB 1", "XII DPIB 2", "XII DPIB 3"];
?>

<!doctype html>
<html lang="id">   
<head>      
    <meta charset="utf-8">      
    <meta http-equiv="X-UA-Compatible" content="IE=edge">      
    <meta name="viewport" content="initial-scale=1.0, width=device-width">      
    <meta name="keywords" content="Pendaftaran PKL DPIB" />      
    <meta name="description" content="Formulir Pendaftaran Praktik Kerja Lapangan (PKL) DPIB" />      
    <title>Pendaftaran PKL | Si Mantap</title>      
    <link rel="icon" type="image/x-icon" href="img/logobangunan.png">      

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
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

        /* --- NAVBAR KACA (SAMA DENGAN LAINNYA) --- */
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

        /* --- HERO SECTION (GALAXY) --- */
        .wrapper { 
            position: relative; z-index: 1; padding-top: 130px; padding-bottom: 100px; 
            background: var(--primary-grad); overflow: hidden; text-align: center;
        }
        .hero-title { font-size: 3.2rem; font-weight: 900; color: white; text-shadow: 0 10px 30px rgba(0,0,0,0.2); }
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
        @keyframes floatObj { 0%, 100% { transform: translateY(0) rotate(0deg); } 50% { transform: translateY(-30px) rotate(5deg); } }

        /* SVG Gelombang Transition */
        .waves { position: absolute; bottom: 0; left: 0; width: 100%; height: 12vh; min-height: 80px; max-height: 120px; margin-bottom: -7px; z-index: 2; pointer-events: none; }
        .parallax > use { animation: move-forever 25s cubic-bezier(.55,.5,.45,.5) infinite; }
        .parallax > use:nth-child(1) { animation-delay: -2s; animation-duration: 7s; }
        .parallax > use:nth-child(2) { animation-delay: -3s; animation-duration: 10s; }
        .parallax > use:nth-child(3) { animation-delay: -4s; animation-duration: 13s; }
        .parallax > use:nth-child(4) { animation-delay: -5s; animation-duration: 20s; }
        @keyframes move-forever { 0% { transform: translate3d(-90px,0,0); } 100% { transform: translate3d(85px,0,0); } }

        /* ==================================================== */
        /* --- KONTEN FORMULIR (CLEAN WHITE) ---                */
        /* ==================================================== */
        .page-content-wrapper { position: relative; z-index: 5; min-height: 60vh; padding: 0 15px 50px; width: 100%; max-width: 800px; margin: 0 auto; }

        .content-card {
            background: white; border-radius: 30px; box-shadow: var(--card-shadow); border: 1px solid #f1f5f9;
            padding: 3rem; margin-top: -80px; position: relative; z-index: 10;
        }

        /* --- FORM STYLING --- */
        .form-control, .form-select { 
            background: #f8fafc !important; border: 2px solid #e2e8f0 !important; border-radius: 15px; 
            padding: 14px 18px; color: var(--text-dark) !important; font-weight: 600; transition: 0.3s; 
        }
        .form-control::placeholder { color: #94a3b8; }
        .form-control:focus, .form-select:focus { background: white !important; border-color: var(--accent-purple) !important; box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15) !important;}
        
        /* SELECT2 THEME FIX TABRAKAN NAVBAR */
        .select2-container--bootstrap-5 .select2-selection { background: #f8fafc !important; border: 2px solid #e2e8f0 !important; border-radius: 15px !important; min-height: 52px; display: flex; align-items: center; }
        .select2-container--bootstrap-5 .select2-selection__rendered { color: var(--text-dark) !important; font-weight: 600; padding-left: 18px;}
        .select2-container--bootstrap-5.select2-container--focus .select2-selection { background: white !important; border-color: var(--accent-purple) !important; box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15) !important; }
        .select2-dropdown { background-color: white !important; border: 1px solid #e2e8f0 !important; border-radius: 15px; z-index: 999999 !important; overflow: hidden; box-shadow: 0 15px 40px rgba(0,0,0,0.15);}
        .select2-results__option { color: #475569 !important; padding: 12px 18px; font-weight: 500;}
        .select2-results__option--highlighted { background: var(--primary-grad) !important; color: white !important; font-weight: bold; }
        .select2-search__field { background: #f8fafc !important; color: var(--text-dark) !important; border: 1px solid #e2e8f0 !important; border-radius: 10px !important;}

        /* TOMBOL PENDAFTARAN */
        .btn-daftar-submit { 
            background: linear-gradient(135deg, #ff007a, #764ba2) !important; border: none !important; 
            box-shadow: 0 8px 20px rgba(255, 0, 122, 0.3) !important; color: white; font-weight: 800; 
            text-transform: uppercase; letter-spacing: 1px; transition: 0.4s;
            padding: 14px 40px; border-radius: 50px; display: inline-block; font-size: 0.95rem;
        }
        .btn-daftar-submit:hover { transform: translateY(-3px); box-shadow: 0 12px 25px rgba(255, 0, 122, 0.45) !important; }

        /* REALTIME CLOCK */
        .fixed-top { z-index: 1051 !important; }
        #realtime-clock-desktop { color: #fcd34d; font-size: 0.95rem; font-weight: 700; text-shadow: 0 0 5px rgba(0, 0, 0, 0.5); }
        #realtime-clock-mobile { color: #fcd34d; font-size: 0.9rem; }

        /* --- RESPONSIVE FIXES (HP & TABLET) --- */
        @media (max-width: 768px) {
            .hero-title { font-size: 2.2rem; padding: 0 15px;}
            .content-card { padding: 30px 20px; border-radius: 20px;}
            .btn-daftar-submit { width: auto !important; padding: 10px 25px !important; font-size: 0.85rem !important; display: inline-block;}
        }
    </style>
</head> 
<body>       

    <!-- NAVBAR SAMA DENGAN HALAMAN LAIN (MENGGUNAKAN INCLUDE) -->
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
                <i class="fas fa-rocket me-2 text-warning"></i> Pendaftaran
            </div>
            <h1 class="hero-title">Formulir Pendaftaran PKL</h1>                      
            
            <!-- PERBAIKAN TEKS: Diperkecil, Diterangkan, dan Diberi Shadow -->
            <p class="mt-3" style="max-width: 650px; margin: 0 auto; font-size: 0.95rem; color: rgba(255,255,255,0.95); line-height: 1.6; text-shadow: 0 2px 4px rgba(0,0,0,0.5);">
                Isi data diri dengan benar dan pastikan Anda sudah berdiskusi dengan orang tua serta pembimbing sekolah sebelum memilih lokasi PKL.
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

    <!-- KONTEN FORMULIR (CLEAN WHITE) -->
    <div class="page-content-wrapper" id="form-section"> 
        <div class="content-card border-top border-5" style="border-top-color: var(--accent-purple) !important;">
            <div class="text-center mb-5">
                <div style="width:80px; height:80px; background:rgba(102,126,234,0.1); border-radius:25px; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; color:var(--accent-purple); font-size:35px; transform: rotate(-10deg);">
                    <i class="fas fa-edit"></i>
                </div>
                <h2 class="fw-bold m-0" style="color: var(--text-dark);">Detail Pendaftaran</h2>
                <p class="mt-2 text-muted fw-semibold">Pastikan data yang diisi sudah benar dan akurat.</p>
            </div>
            
            <?php echo $message; ?>

            <form method="POST" action="daftar.php#form-section">
                <div class="mb-4">
                    <label for="periode_id" class="form-label fw-bold text-secondary text-uppercase" style="font-size:0.8rem; letter-spacing:1px;"><i class="fas fa-calendar-alt me-2 text-warning"></i>1. Pilih Gelombang Keberangkatan</label>
                    <select id="periode_id" name="periode_id" class="form-select" required>
                        <option value="">-- Tentukan Gelombang/Periode --</option>
                        <?php 
                        if (!empty($periode_options)) {
                            foreach ($periode_options as $periode) {
                                $tgl_mulai = date('d M Y', strtotime($periode['tgl_mulai']));
                                $tgl_akhir = date('d M Y', strtotime($periode['tgl_akhir']));
                                $sisa_global = $periode['kuota_gelombang'] - $periode['kuota_terisi'];
                                $display_text = htmlspecialchars($periode['nama_periode']) . " ({$tgl_mulai} - {$tgl_akhir})" . ($sisa_global <= 0 ? ' (PENUH)' : " (Sisa: $sisa_global)");
                        ?>
                            <option 
                                value="<?php echo $periode['periode_id']; ?>"
                                data-max="<?php echo $periode['kuota_gelombang']; ?>"
                                data-terisi="<?php echo $periode['kuota_terisi']; ?>"
                                data-sisa="<?php echo $sisa_global; ?>"
                                <?php echo ($sisa_global <= 0) ? 'disabled' : ''; ?>
                            >
                                <?php echo $display_text; ?>
                            </option>
                        <?php 
                            } 
                        } else {
                            echo '<option value="" disabled>Pendaftaran masih ditutup / belum ada gelombang aktif</option>';
                        }
                        ?>
                    </select>
                    <p id="periode-quota-info" class="mt-2 mb-0" style="font-size: 0.85em; font-weight: 700;"></p>
                </div>

                <hr class="mb-4 text-muted" style="opacity: 0.15;">

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="nisn" class="form-label fw-bold text-secondary text-uppercase" style="font-size:0.8rem; letter-spacing:1px;"><i class="fas fa-id-card me-2 text-muted"></i>NISN</label>
                        <input type="text" id="nisn" name="nisn" class="form-control" placeholder="Nomor Induk Siswa Nasional" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="kelas" class="form-label fw-bold text-secondary text-uppercase" style="font-size:0.8rem; letter-spacing:1px;"><i class="fas fa-graduation-cap me-2 text-muted"></i>Kelas</label>
                        <select id="kelas" name="kelas" class="form-select" required>
                            <option value="">-- Pilih Kelas --</option>
                            <?php foreach ($daftar_kelas as $kelas_option) { ?>
                                <option value="<?php echo htmlspecialchars($kelas_option); ?>"><?php echo htmlspecialchars($kelas_option); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="nama" class="form-label fw-bold text-secondary text-uppercase" style="font-size:0.8rem; letter-spacing:1px;"><i class="fas fa-user me-2 text-muted"></i>Nama Lengkap</label>
                    <input type="text" id="nama" name="nama" class="form-control" placeholder="Sesuai Ijazah Resmi" required>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label fw-bold text-secondary text-uppercase" style="font-size:0.8rem; letter-spacing:1px;"><i class="fas fa-envelope me-2 text-muted"></i>Email Aktif</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="nama@email.com" required>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label for="no_hp" class="form-label fw-bold text-secondary text-uppercase" style="font-size:0.8rem; letter-spacing:1px;"><i class="fab fa-whatsapp me-2 text-success"></i>No WhatsApp</label>
                        <input type="text" id="no_hp" name="no_hp" class="form-control" placeholder="Contoh: 0812xxxxxxxx" required>
                    </div>
                </div>

                <hr class="mb-4 mt-0 text-muted" style="opacity: 0.15;">

                <div class="mb-5 position-relative">
                    <label for="lokasi_id" class="form-label fw-bold text-secondary text-uppercase" style="font-size:0.8rem; letter-spacing:1px;"><i class="fas fa-map-marked-alt me-2 text-primary"></i>2. Titik Lokasi PKL</label>
                    
                    <select id="lokasi_id" name="lokasi_id" class="form-select w-100" required>
                        <option value="">-- Cari & Pilih Mitra Perusahaan --</option>
                        <?php 
                        foreach ($lokasi_options as $row) { 
                            $sisa = $row['kuota_max'] - $row['terisi'];
                            $disabled = ($sisa <= 0) ? 'disabled' : '';
                            $display_text = htmlspecialchars($row['nama_lokasi']) . (!empty($row['alamat']) ? " (" . htmlspecialchars($row['alamat']) . ")" : "") . ($sisa <= 0 ? ' (PENUH)' : " (Sisa: $sisa)");
                        ?>
                            <option value="<?php echo $row['lokasi_id']; ?>" <?php echo $disabled; ?>>
                                <?php echo $display_text; ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-daftar-submit">Daftar Sekarang<i class="fas fa-space-shuttle ms-2"></i></button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- FOOTER DARK (MENGGUNAKAN INCLUDE AGAR SAMA) -->
    <?php include 'panel/public_footer.php'; ?>
    
    <!-- SCRIPTS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"></script>      
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>      
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
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
        // Z-INDEX FIX: Select2 di form pendaftaran
        $('#periode_id, #kelas, #lokasi_id').select2({ 
            theme: "bootstrap-5",
            width: '100%'
        });

        function checkGlobalQuota() {
            if ($('#periode_id').val() === "") {
                $('#periode-quota-info').html('');
                return;
            }
            const selected = $('#periode_id').find(':selected');
            const sisa = parseInt(selected.data('sisa'));
            const max = parseInt(selected.data('max'));
            
            const info = $('#periode-quota-info');
            const btn = $('.btn-daftar-submit');

            if (sisa <= 0 && max > 0) {
                info.html(`<i class="fas fa-exclamation-triangle text-danger me-1"></i> KUOTA PERIODE PENUH! Pendaftaran diblokir.`).addClass('text-danger').removeClass('text-success');
                btn.prop('disabled', true);
                btn.css('opacity', '0.5');
            } else {
                info.html(`<i class="fas fa-satellite-dish text-success me-1"></i> Sinyal Diterima! Kuota Global Tersedia: ${sisa} / ${max} kursi.`).addClass('text-success').removeClass('text-danger');
                btn.prop('disabled', false);
                btn.css('opacity', '1');
            }
        }
        
        $('#periode_id').on('change', checkGlobalQuota);
        checkGlobalQuota(); 
        setInterval(updateClock, 1000);

        // LOGIKA POP-UP SWEET ALERT
        const isSuccess = <?php echo (isset($message) && strpos($message, 'success') !== false) ? 'true' : 'false'; ?>;
        const isError = <?php echo (isset($message) && (strpos($message, 'danger') !== false || strpos($message, 'warning') !== false)) ? 'true' : 'false'; ?>;

        if (isSuccess) {
            Swal.fire({
                title: 'Pendaratan Sukses!',
                text: 'Data Anda telah tersimpan di server pusat. Silakan cek hasil plotting kelompok.',
                icon: 'success',
                background: '#fff',
                color: '#1e293b',
                confirmButtonColor: '#667eea',
                confirmButtonText: 'Lihat Kelompok',
                backdrop: `rgba(15, 23, 42,0.8)`
            }).then((result) => {
                if (result.isConfirmed) window.location.href = 'kelompok.php';
            });
        }
    });
    </script>
</body>
</html>