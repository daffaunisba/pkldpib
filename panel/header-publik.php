<?php
// panel/header-publik.php - HEADER NAVIGASI PUBLIK SIMANTAP

// Fungsi formatTanggalIndo (dipertahankan di sini untuk konsistensi)
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
        $bulan_indo = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        
        $hari = $hari_indo[date('w', $timestamp)];
        $tgl = date('d', $timestamp);
        $bln = $bulan_indo[(int)date('m', $timestamp)];
        $thn = date('Y', $timestamp);
        $waktu = date('H:i:s', $timestamp);
        
        return "{$hari}, {$tgl} {$bln} {$thn}, {$waktu} WIB"; 
    }
}

// 1. Dapatkan nama file yang memanggil header ini (ex: 'profil-perusahaan.php')
// Variabel ini harus disuplai dari file yang meng-include header.
$current_page = $current_page ?? basename($_SERVER['PHP_SELF']); 

// 2. Definisikan Item Menu (Menu lengkap dan logis)
// NOTE: Semua link yang menunjuk ke bagian (#anchor) dari index.php harus memiliki file: 'index.php'
$menu_items = [ 
    'informasi-periode.php' => ['text_long' => 'Periode', 'text_short' => 'Periode', 'file' => 'informasi-periode.php', 'fragment' => ''],
    'panduan-pkl.php'       => ['text_long' => 'Panduan', 'text_short' => 'Panduan', 'file' => 'panduan-pkl.php', 'fragment' => ''],
    'profil-perusahaan.php' => ['text_long' => 'Profil Perusahaan', 'text_short' => 'Profil', 'file' => 'profil-perusahaan.php', 'fragment' => ''],
    // 'rekap-kendali.php'     => ['text_long' => 'Kartu Kendali', 'text_short' => 'Kendali', 'file' => 'rekap-kendali.php', 'fragment' => ''],
    'pembimbing-lapangan.php'     => ['text_long' => 'Pembimbing Lapangan', 'text_short' => 'Pembimbing', 'file' => 'pembimbing-lapangan.php', 'fragment' => ''],
];
?>

<style>
/* Z-INDEX FIX */
.fixed-top { z-index: 1051 !important; }

/* FIX: Navbar Brand PKL DPIB BOLD */
.navbar-brand { font-weight: 700 !important; }

/* FIX: TAMPILAN TEXT PANJANG/PENDEK */
.nav-item .long-text { display: inline; }
.nav-item .short-text { display: none; }

@media (max-width: 991.98px) {
    .nav-item .long-text { display: none; }
    .nav-item .short-text { display: inline; }
}

/* KOREKSI UKURAN FONT NAVIGASI DAN CLOCK */
.navbar-nav .nav-link {
    color: #bdc3c7; /* Warna default link */
    transition: color 0.3s;
    font-size: 1rem; /* Ukuran font default untuk link navigasi */
    padding-left: 0.8rem !important; /* Sesuaikan padding */
    padding-right: 0.8rem !important; /* Sesuaikan padding */
}
.navbar-nav .nav-link:hover {
    color: white;
}
.navbar-nav .nav-link.active {
    color: white !important; /* Warna link aktif */
    font-weight: 600; /* Tebalkan sedikit saat aktif */
}

/* Ukuran font untuk clock desktop */
#realtime-clock-desktop { 
    color: #ffc107; 
    font-size: 1rem; /* Pastikan ukuran tetap 1rem */
    line-height: 1.5; 
    padding-right: 15px; 
    text-shadow: 0 0 5px rgba(0, 0, 0, 0.5); 
    white-space: nowrap; /* Agar tidak pecah baris */
}
/* Ukuran font untuk clock mobile */
#realtime-clock-mobile { 
    color: #ffc107; 
    font-size: 0.9rem; /* Ukuran font untuk mobile */
    white-space: nowrap;
}

/* Menghilangkan styling btn-login-admin karena tombol dihapus */
/* .btn-login-admin { ... } */

/* Penyesuaian jarak antar elemen di navbar untuk kerapian */
.navbar-nav {
    --bs-nav-link-padding-x: 0.8rem; /* Mengatur padding horizontal link Bootstrap */
    --bs-nav-link-padding-y: 0.5rem; /* Mengatur padding vertikal link Bootstrap */
}

</style>

<nav class="navbar navbar-expand-lg fixed-top navbar-dark shadow-sm bg-biru"> 
    <div class="container"> 
        <a class="navbar-brand" href="index.php">
            <img src="img/logobangunan.png" class="d-inline-block align-text-top me-2" alt="Logo" width="25" height="24"> 
            PKL DPIB
        </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">          
                <span class="navbar-toggler-icon"></span>          
              </button> 
        <div class="collapse navbar-collapse" id="navbarSupportedContent"> 
            <ul class="navbar-nav me-auto mb-2 mb-lg-0"> 
                
                <?php 
                foreach ($menu_items as $url => $item):
                    // Logika penentuan link aktif: Bandingkan nama file saja
                    $is_active = ($current_page === $item['file']);
                ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $is_active ? 'active' : ''; ?>" href="<?php echo $url; ?>">
                            <span class="long-text"><?php echo $item['text_long']; ?></span>
                            <span class="short-text"><?php echo $item['text_short']; ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>

                <li class="nav-item d-lg-none"> 
                    <span class="nav-link text-warning fw-bold"><i class="far fa-clock me-1"></i> <span id="realtime-clock-mobile"></span></span>
                </li>
            </ul>
            
            <span id="realtime-clock-desktop" class="text-warning fw-bold me-3 d-none d-lg-inline"><?php echo formatTanggalIndo(); ?></span>

            </div>
    </div> 
</nav>