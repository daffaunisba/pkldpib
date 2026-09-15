<!-- <?php 
// dashboard.php - Tampilan HTML/CSS untuk halaman utama.
// Variabel PHP seperti $message, $lokasi_options, $periode_options, dll., 
// sudah tersedia karena file ini di-include oleh index.php
?>
<!doctype html>
<html lang="en">   
    <head>      
        <meta charset="utf-8">      
        <meta http-equiv="X-UA-Compatible" content="IE=edge">      
        <meta name="viewport" content="initial-scale=1.0, width=device-width">      
        <meta name="keywords" content="PKL DPIB, Pendaftaran" />      
        <meta name="description" content="Sistem Informasi Pendaftaran Praktik Kerja Lapangan (PKL) DPIB" />      
        <meta name="csrf-token" content="0jDZm5Z7QftocgeKR0glIAd7an3JMomboIHVIgJY">      
        <title>Si Mantap</title>      
        <link rel="icon" type="image/x-icon" href="img/logobangunan.png">      
    
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-Zenh87qX5JnK2Jl0vWa8Ck2rdkQ2Bzep5IDxbcnCeuOxjzrPF/et3URy9Bv1WTRi" crossorigin="anonymous">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
        
        <style>
        /* SIMANTAP THEME COLORS AND GRADIENT */
        :root {
            --simantap-blue-dark: #224ebe; 
            --simantap-blue-light: #4e73df; 
            --simantap-accent: #1cc88a; 
            --simantap-soft-shadow: 0 4px 10px rgba(0, 0, 0, 0.15); 
        }
        
        /* 1. WRAPPER & BACKGROUND GRADIENT */
        .wrapper { 
            /* Menggunakan variabel SIMANTAP yang baru */
            background: linear-gradient(to right, var(--simantap-blue-light), var(--simantap-blue-dark));
            color: white; 
            position: relative;
            z-index: 1; 
        }
        .bg-biru { 
            /* Menggunakan variabel SIMANTAP yang baru */
            background-color: var(--simantap-blue-dark) !important; 
        }
        .navbar-brand { 
            font-weight: bold; 
        }

        /* 2. WAVE DIVIDER (SHAPE-FILL) - TIDAK ADA PERUBAHAN NAMA VARIABEL */
        .custom-shape-divider-bottom-1663046878 {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            overflow: hidden;
            line-height: 0;
            transform: rotate(180deg);
            z-index: 0;
        }
        .custom-shape-divider-bottom-1663046878 svg {
            position: relative;
            display: block;
            width: calc(100% + 1.3px);
            height: 100px;
        }
        .custom-shape-divider-bottom-1663046878 .shape-fill {
            fill: #FFFFFF;
        }

        /* 3. BUTTON STYLING (PERBAIKAN SHADOW) */
        .btn-primary, .btn-success, .btn-warning {
            border: none !important;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: none !important; /* HAPUS SHADOW KOTAK */
        }
        .btn-primary {
            /* Menggunakan variabel SIMANTAP yang baru */
            background-color: var(--simantap-blue-light) !important;
            background-image: linear-gradient(180deg, var(--simantap-blue-light) 10%, var(--simantap-blue-dark) 100%) !important;
            color: white !important;
        }
        .btn-primary:hover {
            /* Menggunakan variabel SIMANTAP yang baru */
            background-color: var(--simantap-blue-dark) !important;
            background-image: none !important;
            transform: translateY(-1px);
        }
        
        /* TOMBOL LOGIN ADMIN */
        .btn-login-admin {
            /* Menggunakan variabel SIMANTAP yang baru */
            background-color: white !important;
            color: var(--simantap-blue-dark) !important;
            font-weight: bold !important;
            border: 1px solid var(--simantap-blue-dark) !important;
            box-shadow: none !important; /* HAPUS SHADOW KOTAK */
        }
        .btn-login-admin:hover {
            /* Menggunakan variabel SIMANTAP yang baru */
            background-color: var(--simantap-blue-dark) !important;
            color: white !important;
            border: 1px solid white !important;
            transform: translateY(-1px);
        }


        /* 4. FEATURE ICON STYLING */
        .feature-icon-section {
            background-color: white; /* Make section white to blend with body */
        }
        .feature-icon-section .icon-wrapper {
            background: radial-gradient(circle at center, rgba(230, 245, 255, 1) 0%, rgba(255, 255, 255, 0) 70%); /* Soft radial background */
            border-radius: 50%;
            width: 110px; /* Slightly larger area */
            height: 110px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px auto;
            transition: all 0.3s ease-out; /* TRANSISI UNTUK IKON */
        }
        /* Menerapkan animasi ke seluruh elemen link */
        .feature-icon-section a {
            display: block; 
            text-decoration: none;
            color: inherit;
            transition: transform 0.3s ease-out, box-shadow 0.3s ease-out; /* TRANSISI UNTUK LINK */
            border-radius: 8px; /* Agar shadow terlihat rapi */
            padding: 15px; /* Memberi ruang untuk shadow */
            margin: -15px; /* Mengimbangi padding */
        }

        .feature-icon-section a:hover {
            transform: translateY(-5px); /* Ikon/Link naik sedikit */
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1); /* Shadow lebih menonjol */
        }
        .feature-icon-section a:hover .icon-wrapper {
            /* Opsional: Efek zoom ringan pada wrapper/ikon */
            transform: scale(1.05); 
        }
        
        .feature-icon-section .icon-wrapper i {
            /* Menggunakan variabel SIMANTAP yang baru */
            font-size: 3.5rem; 
            color: var(--simantap-blue-dark); 
        }

        /* 5. FORM AND CARD STYLING (PERBAIKAN SHADOW) */
        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: none !important; /* HAPUS SHADOW DARI CARD */
        }

        /* 6. TABLE STYLING */
        /* MENGGUNAKAN WARNA SIMANTAP BLUE DARK UNTUK HEADER TABLE */
        #lokasiTable th {
            /* Menggunakan variabel SIMANTAP yang baru */
            background-color: var(--simantap-blue-dark) !important; 
            color: white !important;
            border-color: var(--simantap-blue-dark) !important;
        }
        
        /* HAPUS STRIPING DAN ATUR WARNA BODY KE PUTIH */
        #lokasiTable tbody tr td {
            background-color: #fff !important; /* Diubah menjadi putih */
        }
        /* Hapus hover effect bawaan Bootstrap yang mungkin memberi warna */
        #lokasiTable.table-hover tbody tr:hover td {
            background-color: #f8f9fa !important; /* Biarkan hover sangat tipis */
        }
        /* FINAL FIX UNTUK MENGHILANGKAN SHADOW DARI TABEL STATUS */
        #lokasiTable {
            box-shadow: none !important; /* Targetkan id tabel untuk menghapus shadow */
            border-radius: 0 !important;
        }

        /* 7. Footer Styles */
        .about-section, #status, #form-daftar {
            padding: 30px 0;
            background-color: white; 
        }
        
        /* --- ABOUT SECTION SPECIFIC STYLING (Gambar Menyatu) --- */
        .about-section h2 {
            /* Menggunakan variabel SIMANTAP yang baru */
            color: var(--simantap-blue-dark);
            margin-bottom: 25px;
            font-size: 2.2rem;
        }
        .about-section .about-text-content {
             font-family: Georgia, serif; 
             font-size: 1.15rem;
             line-height: 1.8;
             color: #333;
        }
        .about-section .about-text-content p {
             margin-bottom: 1.2rem;
        }
        
        /* Style untuk highlight text */
        .about-text-highlight {
            /* Menggunakan variabel SIMANTAP yang baru */
            color: var(--simantap-blue-dark); 
            font-weight: 700;
            padding: 0 2px;
            background: none; 
        }
        
        /* Style untuk wrapper gambar (Box Shadow Dihilangkan) */
        .about-section .img-wrapper {
            box-shadow: none !important; 
            border: none; 
            border-radius: 0; 
            overflow: hidden;
            transition: all 0.5s ease-out;
            padding: 0 !important; 
        }
        .about-section .img-wrapper:hover {
            transform: none; 
        }
        .about-section img {
            padding: 0; 
            box-shadow: none;
            border-radius: 0;
            width: 100%;
            height: auto;
        }

        /* Make ALL table data black (except badges) */
        #lokasiTable td {
            color: #212529 !important; 
        }
        
        /* Lokasi PKL Column highlight (Optional: makes location name bolder) */
        .td-lokasi {
             font-weight: 600;
        }

        /* --- STYLING UNTUK PAGINATION INFO & SEARCH BOX --- */
        .table-controls {
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            margin-bottom: 15px;
        }

        .table-controls .table-info {
            color: #5a5c69; 
            font-weight: 500;
            font-size: 0.9em; 
            padding: 5px 0;
            margin-bottom: 0; 
        }

        .table-controls .search-box {
            width: auto; 
            margin-left: 0;
        }

        .table-controls .search-box .form-control {
            max-width: 250px; /* MEMBATASI LEBAR SEARCH BOX */
            border-radius: 5px;
            border: 1px solid #c8d3e2;
        }
        
        /* PERUBAHAN UTAMA: Konten menyatu dengan Body (Putih) */
        .container {
             padding-top: 0 !important; 
        }
        
        /* FINAL FIX UNTUK HEADER & REALTIME CLOCK */
        .navbar.fixed-top {
            z-index: 1030; 
            overflow: visible; 
        }

        /* Gaya untuk jam realtime */
        #realtime-clock {
            color: #ffc107; /* Warna warning (kuning/emas) */
            font-size: 1rem;
            line-height: 1.5;
            padding-right: 15px;
            text-shadow: 0 0 5px rgba(0, 0, 0, 0.5); /* Shadow halus agar menonjol */
        }
        /* Memastikan jam muncul sebelum tombol daftar */
        .navbar-nav + #realtime-clock {
            margin-right: 10px;
        }
        
        /* FIX UNTUK SCROLL OFFSET */
        html {
            scroll-padding-top: 70px; /* Jarak yang dibutuhkan di bawah fixed navbar */
        }

        /* CSS BARU UNTUK DROP CAP */
        .first-letter-dropcap::first-letter {
            /* Menggunakan variabel SIMANTAP yang baru */
            font-size: 3.5rem; 
            font-weight: bold;
            float: left; 
            line-height: 0.8; 
            margin-right: 8px; 
            margin-bottom: 0px; 
            color: var(--simantap-blue-dark); 
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2); 
        }

        /* FIX Responsivitas Tabel di HP */
        .table-container {
            overflow-x: auto; /* Mengaktifkan scroll horizontal hanya pada tabel */
        }

        /* --- RESPONSIVE FIXES --- */
        @media (max-width: 768px) {
            /* Mengurangi ukuran header hero di HP */
            .wrapper .display-5 {
                font-size: 2rem !important;
            }
            .wrapper h3.fw-light {
                font-size: 1rem !important;
            }
            
            /* Memastikan formulir menggunakan lebar penuh di HP */
            #form-daftar {
                width: 95% !important;
                margin: 0 auto !important;
                padding: 20px !important;
            }
            
            /* Mengatur layout tabel kontrol di HP (memastikan vertikal) */
            .table-controls {
                flex-direction: column;
                align-items: flex-start;
            }
            .table-controls .search-box {
                width: 100%;
                max-width: none;
            }
            .table-controls .table-info {
                margin-bottom: 10px;
            }
            
            /* Gaya khusus untuk tombol Mulai Pendaftaran di Hero Section (Mobile) */
            .wrapper .btn-lg {
                width: 70%; /* Lebih lebar di HP */
                min-width: 200px;
                font-size: 1.2rem;
                padding: 10px 20px;
            }
        }
        
        </style>
            
        </head> 
    <body>       
        
        <div class="modal fade" id="panduanModal" tabindex="-1" aria-labelledby="panduanModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header bg-biru text-white">
                        <h5 class="modal-title" id="panduanModalLabel"><i class="fas fa-info-circle me-2"></i> Panduan Pendaftaran PKL DPIB</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <h4 class="text-primary fw-bold">Selamat Datang Calon Peserta PKL!</h4>
                        <p>Ikuti langkah-langkah berikut untuk melakukan pendaftaran Praktik Kerja Lapangan:</p>
                        
                        <ol>
                            <li>Cek Ketersediaan Kuota: Lihat bagian "Status Kuota Lokasi PKL" di bawah. Pastikan lokasi yang Anda inginkan masih memiliki kuota (status Tersedia).</li>
                            <li>Cek Kuota Periode: Pastikan periode (gelombang) yang Anda pilih masih memiliki kuota global. Jika kuota global penuh, pendaftaran tidak dapat dilanjutkan.</li>
                            <li>Siapkan Data Diri: Siapkan NISN, Nama Lengkap, Kelas, dan Email aktif.</li>
                            <li>Pilih Lokasi dan Periode: Gulir ke bawah ke bagian "Formulir Pendaftaran" dan isi semua kolom yang diperlukan.</li>
                            <li>Konfirmasi: Setelah menekan tombol "Daftar PKL Sekarang", Anda akan menerima notifikasi status pendaftaran. Pastikan data sudah benar.</li>
                        </ol>
                        
                        <h5 class="text-danger fw-bold mt-4">Perhatian!</h5>
                        <p>Pendaftaran hanya dapat dilakukan satu kali per NISN. Pastikan Anda sudah berdiskusi dengan orang tua/wali dan guru pembimbing sebelum memilih lokasi PKL.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Saya Mengerti</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="wrapper bg-gradien mb-3" >      
            <nav class="navbar navbar-expand-lg fixed-top bg-biru navbar-dark" id="hU">        
                <div class="container">          
                    <a class="navbar-brand" href="#">          
                        <img src="img/logobangunan.png" class="d-inline-block align-text-top me-2" alt="Logo" width="25" height="24">          
                        PKL DPIB          
                    </a>          
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">          
                        <span class="navbar-toggler-icon"></span>          
                    </button>          
                    <div class="collapse navbar-collapse" id="navbarSupportedContent">            
                        <ul class="navbar-nav me-auto mb-2 mb-lg-0">              
                            <li class="nav-item">                
                                <a class="nav-link active" aria-current="page" href="#top">Beranda</a>              
                            </li>              
                            <li class="nav-item">                
                                <a class="nav-link" aria-current="page" href="#about">Tentang PKL</a>                      
                            </li>
                            <li class="nav-item">                
                                <a class="nav-link" aria-current="page" href="#status">Cek Kuota & Lokasi</a>              
                            </li>              
                        </ul>            
                        <span id="realtime-clock" class="text-warning fw-bold me-3 d-none d-lg-inline"></span>
                        
                        <ul class="navbar-nav mb-2 mb-lg-0">
                            <li class="nav-item">                
                                <a class="btn btn-login-admin fw-bold" href="admin/login.php">Login Admin</a>              
                            </li>
                        </ul>
                    </div>          
                </div>        
            </nav>        
            <div class="container pt-5">          
                <div class="row pt-5">            
                    <div class="col-md-5">            
                        <img src="img/bekerja.png" class="img-fluid" alt="Ilustrasi Bekerja">          
                    </div>              
                    <div class="col-md-7 my-auto text-white">              
                        <h2 class="display-5 fw-bold">Si Mantap</h2>                      
                        <h3 class="fw-light">Sistem Informasi Manajemen Praktik Kerja Lapangan DPIB SMEKISA</h3>                       
                        <p class="d-lg-none d-sm-block"><br></p>
                        
                        <a href="#form-daftar" class="btn btn-lg btn-warning text-dark fw-bold mt-3">Mulai Pendaftaran <i class="fas fa-arrow-down"></i></a>
                    </div>        
                </div>
            </div>        
            <div class="custom-shape-divider-bottom-1663046878">          
                <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none">            
                    <path d="M0,0V46.29c47.79,22.2,103.59,32.17,158,28,70.36-5.37,136.33-33.31,206.8-37.5C438.64,32.43,512.34,53.67,583,72.05c69.27,18,138.3,24.88,209.4,13.08,36.15-6,69.85-17.84,104.45-29.34C989.49,25,1113-14.29,1200,52.47V0Z" opacity=".25" class="shape-fill"></path>              
                    <path d="M0,0V15.81C13,36.92,27.64,56.86,47.69,72.05,99.41,111.27,165,111,224.58,91.58c31.15-10.15,60.09-26.07,89.67-39.8,40.92-19,84.73-46,130.83-49.67,36.26-2.85,70.9,9.42,98.6,31.56,31.77,25.39,62.32,62,103.63,73,40.44,10.79,81.35-6.69,119.13-24.28s75.16-39,116.92-43.05c59.73-5.85,113.28,22.88,168.9,38.84,30.2,8.66,59,6.17,87.09-7.5,22.43-10.89,48-26.93,60.65-49.24V0Z" opacity=".5" class="shape-fill"></path>              
                    <path d="M0,0V5.63C149.93,59,314.09,71.32,475.83,42.57c43-7.64,84.23-20.12,127.61-26.46,59-8.63,112.48,12.24,165.56,35.4C827.93,77.22,886,95.24,951.2,90c86.53-7,172.46-45.71,248.8-84.81V0Z" class="shape-fill"></path>          
                </svg>        
            </div>      
        </div>      
        
        <div class="container mb-5 py-3">
            <div class="text-center feature-icon-section" id="top-feature"> 
                <div class="display-6 mb-5"> Fitur Utama PKL DPIB</div>       
                <div class="row">           
                    <div class="col-md col-lg mb-4">          
                        <a href="informasi-periode.php">
                            <div class="icon-wrapper">
                                <i class="fas fa-calendar-alt"></i>
                            </div>          
                            <h3 class="fs-6 fw-bold">Informasi Periode PKL</h3>          
                            Detail periode pendaftaran dan pelaksanaan PKL DPIB         
                        </a>
                    </div>          
                    <div class="col-md col-lg mb-4">          
                        <a href="panduan-pkl.php">
                            <div class="icon-wrapper">
                                <i class="fas fa-book"></i>
                            </div>          
                            <h3 class="fs-6 fw-bold">Panduan PKL</h3>          
                            Contoh format proposal dan laporan sidang    
                        </a>
                    </div>          
                    <div class="col-md col-lg mb-4">          
                        <a href="#">
                            <div class="icon-wrapper">
                                <i class="fas fa-tasks"></i>
                            </div>          
                            <h3 class="fs-6 fw-bold">Kartu Kendali</h3>          
                            Informasi lengkap mengenai prosedur dan ketentuan PKL
                        </a>
                    </div>          
                    <div class="col-md col-lg mb-4">          
                        <a href="#">
                            <div class="icon-wrapper">
                                <i class="fas fa-user-tie"></i>
                            </div>          
                            <h3 class="fs-6 fw-bold">Pembimbing Lapangan</h3>          
                            Informasi pembimbing dari pihak industri/perusahaan        
                        </a>
                    </div>          
                    <div class="col-md col-lg mb-4">          
                        <a href="#">
                            <div class="icon-wrapper">
                                <i class="fas fa-map-marked-alt"></i>
                            </div>          
                            <h3 class="fs-6 fw-bold">Profil Perusahaan</h3>          
                            Detail profil perusahaan tempat PKL         
                        </a>
                    </div>        
                </div>        
            </div>
            
            <div class="section-divider"></div>
            
            <section id="about" class="about-section">
                <h2 class="display-6 fw-bold text-center">Apa Itu PKL DPIB?</h2>
                <div class="row align-items-center mt-4">
                    <div class="col-md-7 about-text-content">
                        <p class="first-letter-dropcap">Praktek Kerja Lapangan (PKL) adalah kegiatan pendidikan, pelatihan, dan pembelajaran yang dilaksanakan di <span class="about-text-highlight">Dunia Usaha atau Dunia Industri (DU/DI)</span> yang relevan dengan kompetensi keahlian Desain Permodelan dan Informasi Bangunan (DPIB).</p>
                        <p>Tujuan utama PKL adalah membekali siswa dengan pengalaman kerja nyata, mengaplikasikan ilmu yang didapat di sekolah, serta menumbuhkan <span class="about-text-highlight">etos kerja profesional, kemandirian, dan tanggung jawab</span>. Siswa DPIB akan terlibat dalam proyek desain, pemodelan 3D, atau survei lapangan.</p>
                    </div>
                    <div class="col-md-5 text-center">
                        <div class="img-wrapper">
                            <img src="img/about.png" alt="Siswa DPIB Berpose" class="img-fluid" style="max-height: 350px; object-fit: contain;"/> 
                        </div>
                    </div>
                </div>
            </section>

            <div class="section-divider"></div>

            <section id="status">
                <h2 class="display-6 fw-bold text-center mb-4">Status Kuota Lokasi PKL</h2>
                
                <div class="table-controls">
                    <div class="table-info">
                        Menampilkan <?php echo min($total_rows, $start + 1); ?> sampai <?php echo min($total_rows, $start + $limit); ?> dari <?php echo $total_rows; ?> total lokasi.
                    </div>
                    <div class="search-box">
                        <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Cari Lokasi / Alamat..." class="form-control" />
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="lokasiTable" class="table table-hover rounded"> 
                        <thead class="table-light">
                            <tr>
                                <th class="text-start">Lokasi PKL</th>
                                <th>Jam Kerja</th>
                                <th class="text-start">Alamat</th> 
                                <th>Kuota Max</th>
                                <th>Terisi</th>
                                <th>Sisa Kuota</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($lokasi_options)) { ?>
                                <tr><td colspan="7" class="text-danger text-center">Tidak ada data lokasi PKL saat ini.</td></tr>
                            <?php } ?>
                            <?php 
                            foreach ($lokasi_options as $row) { 
                                $sisa = $row['kuota_max'] - $row['terisi'];
                                // Badge tetap menggunakan warna success/danger (Hijau/Merah)
                                $status_class = ($sisa <= 0) ? 'bg-danger' : 'bg-success';
                                $status_text = ($sisa <= 0) ? 'PENUH' : 'Tersedia';
                                
                                $row_class = ''; 
                            ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td class="text-start td-lokasi"><?php echo htmlspecialchars($row['nama_lokasi']); ?></td>
                                <td class="text-center"><?php echo htmlspecialchars($row['jam_kerja']); ?></td>
                                <td class="text-start"><?php echo htmlspecialchars($row['alamat']); ?></td> 
                                <td class="text-center"><?php echo $row['kuota_max']; ?></td>
                                <td class="text-center td-terisi"><?php echo $row['terisi']; ?></td> 
                                <td class="text-center"><span class="badge <?php echo ($sisa <= 0) ? 'bg-danger' : 'bg-success'; ?> fw-bold"><?php echo $sisa; ?></span></td>
                                <td class="text-center"><span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div> 
                <div class="pagination-nav">
                    <?php if ($total_pages > 1) { ?>
                        <?php 
                        $nav_start = max(1, $page - 2);
                        $nav_end = min($total_pages, $page + 2);
                        
                        if ($nav_end - $nav_start < 4) {
                            if ($nav_start > 1) {
                                $nav_start = max(1, $nav_end - 4);
                            } else {
                                $nav_end = min($total_pages, 5);
                            }
                        }
                        ?>

                        <?php if ($page > 1) { ?>
                            <a href="?p=<?php echo $page - 1; ?>#status" class="prev-next btn btn-sm btn-outline-primary"><i class="fas fa-angle-left"></i> Sebelumnya</a>
                        <?php } ?>

                        <?php for ($i = $nav_start; $i <= $nav_end; $i++) { ?>
                            <a href="?p=<?php echo $i; ?>#status" class="btn btn-sm <?php echo ($i == $page) ? 'btn-primary active' : 'btn-outline-primary'; ?>"><?php echo $i; ?></a>
                        <?php } ?>

                        <?php if ($page < $total_pages) { ?>
                            <a href="?p=<?php echo $page + 1; ?>#status" class="prev-next btn btn-sm btn-outline-primary">Berikutnya <i class="fas fa-angle-right"></i></a>
                        <?php } ?>
                        
                    <?php } ?>
                </div>
            </section>
            
            <div class="section-divider"></div>

            <section id="form-daftar" class="card p-4 mx-auto" style="max-width: 600px;">
                <h2 class="display-6 fw-bold text-center">Formulir Pendaftaran PKL DPIB</h2>
                
                <?php echo $message; ?>

                <form method="POST" action="">
                    
                    <div class="mb-3">
                        <label for="periode_id" class="form-label fw-bold">Pilih Periode PKL:</label>
                        <select id="periode_id" name="periode_id" class="form-select" required>
                            <option value="">-- Pilih Periode --</option>
                            <?php 
                            if (!empty($periode_options)) {
                                foreach ($periode_options as $periode) {
                                    $tgl_mulai = date('d M Y', strtotime($periode['tgl_mulai']));
                                    $tgl_akhir = date('d M Y', strtotime($periode['tgl_akhir']));
                                    // HITUNG SISA KUOTA GLOBAL
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
                                echo '<option value="" disabled>Tidak ada periode PKL yang tersedia.</option>';
                            }
                            ?>
                        </select>
                        <p id="periode-quota-info" class="mt-2" style="font-size: 0.9em; font-weight: 600;"></p>
                    </div>

                    <div class="mb-3">
                        <label for="nisn" class="form-label fw-bold">NISN:</label>
                        <input type="text" id="nisn" name="nisn" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label for="nama" class="form-label fw-bold">Nama Lengkap:</label>
                        <input type="text" id="nama" name="nama" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label for="kelas" class="form-label fw-bold">Kelas:</label>
                        <select id="kelas" name="kelas" class="form-select" required>
                            <option value="">-- Pilih Kelas --</option>
                            <?php foreach ($daftar_kelas as $kelas_option) { ?>
                                <option value="<?php echo htmlspecialchars($kelas_option); ?>"><?php echo htmlspecialchars($kelas_option); ?></option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label fw-bold">Email:</label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>

                    <div class="mb-4">
                        <label for="lokasi_id" class="form-label fw-bold">Pilih Lokasi PKL:</label>
                        <select id="lokasi_id" name="lokasi_id" class="form-select" required>
                            <option value="">-- Pilih Tempat PKL --</option>
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
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">Daftar PKL Sekarang <i class="fas fa-arrow-right"></i></button>
                    </div>
                </form>
            </section>
            
        </div>      
        
        <div class="bg-dark pt-5">        
            <div class="container text-white">          
                <div class="row mt-5">            
                    <div class="col-md-5">            
                        <img src="img/alamatkami.png" class="img-fluid" alt="Ilustrasi Tentang Kami">          
                    </div>          
                    <div class="col-md">            
                        <div class="row">              
                            <div class="col-md-6">                
                                <p class="fs-4 fw-bold">SMK Islam 1 Blitar</p>                
                                <div class="fs-5">                  
                                    Jl. Musi No. 6 Kepanjenkidul, Blitar, Jawa Timur <br>                  
                                    Telepon (0342) 802137 <br>                  
                                    email : smkislam@gmail.com              
                                </div>
                            </div>              
                            <div class="col-md-6">                
                                <p class="fs-4 fw-bold">Menu PKL</p>                
                                <ul class="fs-5 list-unstyled">                  
                                    <li><a href="#about" class="text-white text-decoration-none">Tentang PKL DPIB</a></li>                  
                                    <li><a href="#status" class="text-white text-decoration-none">Cek Kuota & Lokasi</a></li>                  
                                    <li><a href="#form-daftar" class="btn btn-sm btn-warning fw-bold mt-2">Daftar PKL</a></li>
                                </ul>              
                            </div>            
                        </div>
                    </div>
                </div>        
            </div>      
        </div>      
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">      
            <div class="container-fluid">      
            <a class="navbar-brand mx-auto" href="#">2025 &copy; Tim IT DPIB SMK Islam 1 Blitar</a>      
            </div>      
        </nav>
        
        <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>    
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"></script>      
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>      
        
        <script type="text/javascript">
        // Fungsi JavaScript untuk jam realtime
        function updateClock() {
            const now = new Date();
            const localTime = new Date(now.getTime());

            const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

            const dayName = days[localTime.getDay()];
            const dayOfMonth = localTime.getDate();
            const monthName = months[localTime.getMonth()];
            const year = localTime.getFullYear();

            const hours = String(localTime.getHours()).padStart(2, '0');
            const minutes = String(localTime.getMinutes()).padStart(2, '0');
            const seconds = String(localTime.getSeconds()).padStart(2, '0');

            // FORMAT BARU: "Jumat, 24 Oktober 2025, 19:35:30 WIB"
            const timeString = `${dayName}, ${dayOfMonth} ${monthName} ${year}, ${hours}:${minutes}:${seconds} WIB`;
            
            const clockElement = document.getElementById('realtime-clock');
            if (clockElement) {
                clockElement.textContent = timeString;
            }
        }

        // Fungsi JavaScript untuk filter tabel
        function filterTable() {
            var input, filter, table, tr, td_lokasi, td_alamat, i, txtValue;
            input = document.getElementById("searchInput");
            filter = input.value.toUpperCase();
            table = document.getElementById("lokasiTable");
            tr = table.getElementsByTagName("tr");
            for (i = 1; i < tr.length; i++) { 
                td_lokasi = tr[i].getElementsByTagName("td")[0];
                td_alamat = tr[i].getElementsByTagName("td")[2];
                if (td_lokasi || td_alamat) {
                    txtValue = (td_lokasi ? td_lokasi.textContent || td_lokasi.innerText : '') + " " + (td_alamat ? td_alamat.textContent || td_alamat.innerText : '');
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }       
            }
        }
        
        $(document).ready(function() {        
            // Inisialisasi Select2 untuk form pendaftaran dengan dropdownParent yang benar
            $('#periode_id').select2({ theme: "bootstrap-5", dropdownParent: $("#form-daftar") });
            $('#kelas').select2({ theme: "bootstrap-5", dropdownParent: $("#form-daftar") });
            $('#lokasi_id').select2({ theme: "bootstrap-5", dropdownParent: $("#form-daftar") });

            // LOGIKA KUOTA PERIODE GLOBAL (MEMFROZEN TOMBOL DAFTAR)
            function checkGlobalQuota() {
                // Pastikan ada periode yang dipilih
                if ($('#periode_id').val() === "") {
                    $('#periode-quota-info').html('');
                    $('.btn-primary.btn-lg[type="submit"]').prop('disabled', true);
                    return;
                }

                const selectedOption = $('#periode_id').find(':selected');
                const sisa = parseInt(selectedOption.data('sisa'));
                const max = parseInt(selectedOption.data('max'));
                const terisi = parseInt(selectedOption.data('terisi'));
                const infoElement = $('#periode-quota-info');
                const submitButton = $('.btn-primary.btn-lg[type="submit"]');

                if (sisa <= 0 && max > 0) {
                    // KUOTA GLOBAL PENUH
                    infoElement.html(`<i class="fas fa-exclamation-triangle text-danger"></i> KUOTA PERIODE PENUH! Maks: ${max}, Terisi: ${terisi}. Pendaftaran tidak dapat dilanjutkan.`);
                    infoElement.addClass('text-danger').removeClass('text-success');
                    submitButton.prop('disabled', true);
                } else if (max > 0) {
                    // KUOTA TERSEDIA
                    infoElement.html(`<i class="fas fa-check-circle text-success"></i> Kuota Tersedia: ${sisa} / ${max} orang.`);
                    infoElement.addClass('text-success').removeClass('text-danger');
                    // Hanya aktifkan jika kuota tersedia
                    submitButton.prop('disabled', false); 
                } else {
                    // Periode valid tapi kuota tidak terdeteksi (atau kuota maks 0), asumsi aktif
                    infoElement.html('');
                    submitButton.prop('disabled', false);
                }
            }
            
            // Panggil fungsi saat periode berubah
            $('#periode_id').on('change', checkGlobalQuota);
            
            // Panggil saat load untuk set status awal
            checkGlobalQuota(); 
            
            // Mulai jam realtime
            updateClock();
            setInterval(updateClock, 1000);
            
            // Tampilkan modal saat halaman dimuat
            const panduanModal = new bootstrap.Modal(document.getElementById('panduanModal'));
            panduanModal.show();
        });
        </script>
        
    </body>
</html> -->