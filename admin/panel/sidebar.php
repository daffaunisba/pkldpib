<?php
// admin/panel/sidebar.php

// Ambil level dan username dari session
$user_level = isset($_SESSION['level']) ? strtolower($_SESSION['level']) : 'pembimbing';
$user_name = isset($_SESSION['username']) ? strtolower($_SESSION['username']) : '';

$current_page = basename($_SERVER['PHP_SELF']);

// Logika Hak Akses Khusus: Admin ATAU Username 'ade'
$is_admin_or_ade = ($user_level === 'admin' || $user_name === 'ade');
?>

<button type="button" class="mantap-hamburger-trigger" id="mantapMobileToggle" aria-label="Menu">
    <i class="fas fa-bars"></i>
</button>

<!-- Latar belakang gelap saat menu terbuka di HP -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<style>
    /* ========================================= */
    /* DESAIN SIDEBAR MINIMALIS & COLLAPSIBLE    */
    /* ========================================= */
    :root {
        --sidebar-bg: #0B1121;        /* Deep Elegant Navy */
        --sidebar-active-bg: rgba(59, 130, 246, 0.12); /* Transparan Biru */
        --sidebar-active-text: #3b82f6; /* Biru Terang (Blue 500) */
        --sidebar-hover-bg: rgba(255, 255, 255, 0.04);
        --text-main: #94a3b8;         /* Slate 400 */
        --text-hover: #f1f5f9;        /* Slate 100 */
        --sidebar-width-full: 260px;
        --sidebar-width-mini: 85px;
    }

    /* Pengaturan Body agar konten utama bergeser otomatis */
    body {
        padding-left: var(--sidebar-width-full) !important;
        transition: padding-left 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
        overflow-x: hidden;
    }
    body.sidebar-collapsed {
        padding-left: var(--sidebar-width-mini) !important;
    }

    .sidebar { 
        width: var(--sidebar-width-full); 
        background-color: var(--sidebar-bg); 
        color: #e2e8f0; 
        display: flex; 
        flex-direction: column; 
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border-right: 1px solid rgba(255, 255, 255, 0.04);
        position: fixed;
        top: 0; left: 0; height: 100vh;
        z-index: 10000;
        font-family: 'Poppins', sans-serif;
    }

    /* Header Brand (Logo & Hamburger) */
    .sidebar-brand { 
        height: 80px; 
        display: flex; 
        align-items: center; 
        padding: 0 20px; 
        font-size: 20px; 
        font-weight: 800; 
        letter-spacing: 0.5px; 
        border-bottom: 1px solid rgba(255, 255, 255, 0.04); 
        color: #fff;
        position: relative;
        white-space: nowrap;
        overflow: hidden;
    }

    .brand-wrapper {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .brand-logo {
        width: 34px; height: 34px; min-width: 34px;
        background: linear-gradient(135deg, #3b82f6, #1d4ed8); /* Gradasi Biru */
        border-radius: 10px; display: flex; align-items: center; justify-content: center;
        box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
    }
    
    .brand-logo i { color: white; font-size: 16px; }
    .brand-text { color: #ffffff; }
    .brand-text span { color: var(--sidebar-active-text); font-weight: 400; }

    /* Tombol Toggle Desktop (Hamburger) */
    .desktop-toggle-btn {
        background: transparent;
        border: none;
        color: #64748b;
        font-size: 1.2rem;
        cursor: pointer;
        padding: 5px;
        transition: color 0.2s;
        position: absolute;
        right: 15px; /* Nempel Kanan saat dilebarkan */
    }
    .desktop-toggle-btn:hover { color: var(--sidebar-active-text); }

    /* Area Menu Scroll */
    .sidebar-menu { flex: 1; padding: 20px 0; overflow-y: auto; overflow-x: hidden; }
    .sidebar-menu::-webkit-scrollbar { width: 5px; }
    .sidebar-menu::-webkit-scrollbar-track { background: transparent; }
    .sidebar-menu::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }
    .sidebar-menu::-webkit-scrollbar-thumb:hover { background: #475569; }

    /* Judul Kategori (Master Data, dll) */
    .menu-title { 
        padding: 0 24px; font-size: 11px; text-transform: uppercase; color: #475569; 
        font-weight: 800; letter-spacing: 1.5px; margin-bottom: 10px; margin-top: 25px; 
        white-space: nowrap; transition: 0.3s;
    }
    .menu-title:first-child { margin-top: 0; }

    /* Gaya Item Menu */
    .menu-item { 
        display: flex; align-items: center; gap: 14px; margin: 4px 16px; padding: 12px 16px; 
        border-radius: 12px; color: var(--text-main); text-decoration: none; font-weight: 500; 
        font-size: 14px; transition: all 0.2s ease; white-space: nowrap;
    }

    .menu-item i { 
        font-size: 18px; width: 22px; text-align: center;
        opacity: 0.7; transition: 0.2s ease;
    }

    /* Hover Effect */
    .menu-item:hover { background-color: var(--sidebar-hover-bg); color: var(--text-hover); }
    .menu-item:hover i { opacity: 1; color: var(--sidebar-active-text); transform: scale(1.15); }

    /* Active State Effect */
    .menu-item.active { background: var(--sidebar-active-bg); color: var(--sidebar-active-text); font-weight: 700; }
    .menu-item.active i { opacity: 1; color: var(--sidebar-active-text); }

    /* ========================================= */
    /* LOGIKA KETIKA SIDEBAR MENGECIL (COLLAPSED)*/
    /* ========================================= */
    body.sidebar-collapsed .sidebar { width: var(--sidebar-width-mini); }
    
    /* Perbaikan Header saat Collapsed: Tampilkan Hamburger di Tengah */
    body.sidebar-collapsed .sidebar-brand { padding: 0; justify-content: center; }
    body.sidebar-collapsed .brand-wrapper { display: none; } /* Sembunyikan Logo & Teks */
    body.sidebar-collapsed .desktop-toggle-btn { 
        position: static; 
        margin: 0 auto; 
        font-size: 1.3rem; 
        display: block; 
    }
    
    body.sidebar-collapsed .menu-title { opacity: 0; height: 0; margin: 0; padding: 0; overflow: hidden; }
    body.sidebar-collapsed .menu-item { padding: 14px 0; justify-content: center; margin: 4px 12px; }
    body.sidebar-collapsed .menu-text { display: none; }
    body.sidebar-collapsed .menu-item i { transform: scale(1.15); font-size: 20px;}
    body.sidebar-collapsed .menu-item:hover i { transform: scale(1.3); }

    /* ========================================= */
    /* RESPONSIVE MOBILE (HP)                    */
    /* ========================================= */
    .mantap-hamburger-trigger { display: none; }
    .sidebar-overlay { 
        display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; 
        background: rgba(11, 17, 33, 0.7); z-index: 9999; backdrop-filter: blur(2px);
        opacity: 0; transition: opacity 0.3s ease;
    }

    @media (max-width: 768px) {
        body { padding-left: 0 !important; }
        .desktop-toggle-btn { display: none !important; }

        .mantap-hamburger-trigger {
            display: flex !important; align-items: center; justify-content: center;
            position: fixed !important; top: 10px !important; left: 15px !important;
            width: 42px !important; height: 42px !important;
            background-color: var(--sidebar-active-text) !important; color: white !important;
            border: none !important; border-radius: 10px !important;
            font-size: 1.2rem !important; z-index: 100005 !important; 
            cursor: pointer !important; box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3) !important;
        }

        .sidebar { transform: translateX(-100%); width: 280px; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: none; z-index: 100000;}
        body.mobile-sidebar-open .sidebar { transform: translateX(0); box-shadow: 5px 0 25px rgba(0,0,0,0.5); }
        body.mobile-sidebar-open .sidebar-overlay { display: block; opacity: 1; }
        body.sidebar-collapsed .sidebar { width: 280px; } /* Nonaktifkan fitur collapse di HP */
    }
</style>

<aside class="sidebar" id="mainSidebar">
    <div class="sidebar-brand">
        <div class="brand-wrapper">
            <div class="brand-logo"><i class="fas fa-layer-group"></i></div>
            <div class="brand-text">PKL<span>DPIB</span></div>
        </div>
        <button class="desktop-toggle-btn" id="desktopToggleBtn" aria-label="Toggle Sidebar"><i class="fas fa-bars"></i></button>
    </div>

    <div class="sidebar-menu">

        <div class="menu-title">Main Menu</div>
        <a href="dashboard.php" class="menu-item <?= ($current_page == 'dashboard.php') ? 'active' : ''; ?>" title="Dashboard Utama">
            <i class="fas fa-tachometer-alt"></i>
            <span class="menu-text">Dashboard Utama</span>
        </a>

        <div class="menu-title">Master Data</div>
        <a href="peserta-list.php" class="menu-item <?= ($current_page == 'peserta-list.php') ? 'active' : ''; ?>" title="Data Murid">
            <i class="fas fa-users"></i>
            <span class="menu-text">Data Murid</span>
        </a>
        
        <?php if ($user_level === 'admin'): ?>
            <a href="lokasi-add.php" class="menu-item <?= ($current_page == 'lokasi-add.php') ? 'active' : ''; ?>" title="Data Lokasi PKL">
                <i class="fas fa-map-marker-alt"></i>
                <span class="menu-text">Data Lokasi PKL</span>
            </a>
        <?php endif; ?>

        <a href="guru-list.php" class="menu-item <?= ($current_page == 'guru-list.php') ? 'active' : ''; ?>" title="Daftar Pembimbing">
            <i class="fas fa-user-tie"></i>
            <span class="menu-text">Daftar Pembimbing</span>
        </a>

        <div class="menu-title">Aktivitas & Monitoring</div>
        <a href="presensi-list.php" class="menu-item <?= ($current_page == 'presensi-list.php') ? 'active' : ''; ?>" title="Presensi Online">
            <i class="fas fa-clipboard-check"></i>
            <span class="menu-text">Presensi Online</span>
        </a>
        
        <a href="riwayat-presensi.php" class="menu-item <?= ($current_page == 'riwayat-presensi.php') ? 'active' : ''; ?>" title="Riwayat Presensi">
            <i class="fas fa-history"></i>
            <span class="menu-text">Riwayat Presensi</span>
        </a>

        <a href="perizinan.php" class="menu-item <?= ($current_page == 'perizinan.php') ? 'active' : ''; ?>" title="Perizinan Siswa">
            <i class="fas fa-user-check"></i>
            <span class="menu-text">Perizinan Siswa</span>
        </a>

        <?php if ($user_level === 'admin'): ?>
            <a href="hari-libur.php" class="menu-item <?= ($current_page == 'hari-libur.php') ? 'active' : ''; ?>" title="Kalender Libur">
                <i class="fas fa-calendar-times"></i>
                <span class="menu-text">Kalender Libur</span>
            </a>
        <?php endif; ?>

        <a href="asistensi.php" class="menu-item <?= ($current_page == 'asistensi.php') ? 'active' : ''; ?>" title="Jurnal Asistensi">
            <i class="fas fa-file-signature"></i>
            <span class="menu-text">Jurnal Asistensi</span>
        </a>

        <a href="kartu-kendali.php" class="menu-item <?= ($current_page == 'kartu-kendali.php') ? 'active' : ''; ?>" title="Kartu Kendali">
            <i class="fas fa-address-card"></i>
            <span class="menu-text">Kartu Kendali</span>
        </a>

        <a href="monitoring-guru.php" class="menu-item <?= ($current_page == 'monitoring-guru.php') ? 'active' : ''; ?>" title="Monitoring PKL">
            <i class="fas fa-tv"></i>
            <span class="menu-text">Monitoring PKL</span>
        </a>

        <div class="menu-title">Laporan & Dokumen</div>
        <?php if ($user_level === 'admin'): ?>
            <a href="profil.php" class="menu-item <?= ($current_page == 'profil.php') ? 'active' : ''; ?>" title="Profil Perusahaan">
                <i class="fas fa-building"></i>
                <span class="menu-text">Profil Perusahaan</span>
            </a>
            <a href="pengumuman.php" class="menu-item <?= ($current_page == 'pengumuman.php') ? 'active' : ''; ?>" title="Pengumuman">
                <i class="fas fa-bullhorn"></i>
                <span class="menu-text">Pengumuman</span>
            </a>
            <a href="persuratan.php" class="menu-item <?= ($current_page == 'persuratan.php') ? 'active' : ''; ?>" title="Persuratan">
                <i class="fas fa-envelope"></i>
                <span class="menu-text">Persuratan</span>
            </a>
            <a href="format-laporan.php" class="menu-item <?= ($current_page == 'format-laporan.php') ? 'active' : ''; ?>" title="Format Laporan">
                <i class="fas fa-book"></i>
                <span class="menu-text">Format Laporan</span>
            </a>
        <?php endif; ?>

        <a href="hasil-laporan.php" class="menu-item <?= ($current_page == 'hasil-laporan.php') ? 'active' : ''; ?>" title="Hasil Laporan">
            <i class="fas fa-folder-open"></i>
            <span class="menu-text">Hasil Laporan</span>
        </a>
        <a href="sidang-pkl.php" class="menu-item <?= ($current_page == 'sidang-pkl.php') ? 'active' : ''; ?>" title="Sidang PKL">
            <i class="fas fa-gavel"></i>
            <span class="menu-text">Sidang PKL</span>
        </a>
        <a href="sertifikat.php" class="menu-item <?= ($current_page == 'sertifikat.php') ? 'active' : ''; ?>" title="Sertifikat">
            <i class="fas fa-certificate"></i>
            <span class="menu-text">Sertifikat</span>
        </a>

        <div class="menu-title">Pengaturan Sistem</div>
        <?php if ($user_level === 'admin'): ?>
            <a href="siswa-add.php" class="menu-item <?= ($current_page == 'siswa-add.php') ? 'active' : ''; ?>" title="User/Pw Absen">
                <i class="fas fa-key"></i>
                <span class="menu-text">User/Pw Absen</span>
            </a>
        <?php endif; ?>

        <a href="log.php" class="menu-item <?= ($current_page == 'log.php') ? 'active' : ''; ?>" title="Log Aktivitas">
            <i class="fas fa-clipboard-list"></i>
            <span class="menu-text">Log Aktivitas</span>
        </a>

        <?php if ($user_level === 'admin'): ?>
            <a href="log-wa.php" class="menu-item <?= ($current_page == 'log-wa.php') ? 'active' : ''; ?>" title="Log WhatsApp">
                <i class="fab fa-whatsapp"></i>
                <span class="menu-text">Log WhatsApp</span>
            </a>
        <?php endif; ?>

        <a href="../index.php" class="menu-item" style="margin-top: 15px; border-top: 1px solid rgba(255,255,255,0.04); border-radius:0; padding-top: 20px; color:#64748b;" title="Ke Website Utama">
            <i class="fas fa-globe"></i>
            <span class="menu-text">Website Utama</span>
        </a>

    </div>
</aside>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const bodyClass = document.body.classList;
        const desktopBtn = document.getElementById('desktopToggleBtn');
        const mobileBtn = document.getElementById('mantapMobileToggle');
        const overlay = document.getElementById('sidebarOverlay');

        // 1. Auto-Adjust Navbar Fixed (Jika Ada)
        const topNavbars = document.querySelectorAll('header, .navbar, .top-nav, nav');
        function adjustNavbars(isCollapsed) {
            topNavbars.forEach(nav => {
                if(window.getComputedStyle(nav).position === 'fixed' && window.innerWidth > 768) {
                    nav.style.transition = 'width 0.3s cubic-bezier(0.4, 0, 0.2, 1), left 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
                    if(isCollapsed) {
                        nav.style.width = 'calc(100% - 85px)'; nav.style.left = '85px';
                    } else {
                        nav.style.width = 'calc(100% - 260px)'; nav.style.left = '260px';
                    }
                } else if(window.innerWidth <= 768) {
                    nav.style.width = '100%'; nav.style.left = '0';
                }
            });
        }

        // Cek state dari LocalStorage untuk Desktop
        if(localStorage.getItem('sidebar-collapsed') === 'true' && window.innerWidth > 768) {
            bodyClass.add('sidebar-collapsed');
            adjustNavbars(true);
        } else {
            adjustNavbars(false);
        }

        // 2. Event Toggle Desktop
        if(desktopBtn) {
            desktopBtn.addEventListener('click', function() {
                bodyClass.toggle('sidebar-collapsed');
                const isCollapsed = bodyClass.contains('sidebar-collapsed');
                localStorage.setItem('sidebar-collapsed', isCollapsed ? 'true' : 'false');
                adjustNavbars(isCollapsed);
            });
        }

        // 3. Event Toggle Mobile
        if(mobileBtn) {
            mobileBtn.addEventListener('click', function(e) {
                e.stopPropagation(); 
                bodyClass.add('mobile-sidebar-open');
                const icon = this.querySelector('i');
                icon.classList.remove('fa-bars'); 
                icon.classList.add('fa-times');
            });
        }

        // Tutup sidebar saat overlay diklik (hanya di HP)
        if(overlay) {
            overlay.addEventListener('click', function() {
                bodyClass.remove('mobile-sidebar-open');
                if(mobileBtn) {
                    const icon = mobileBtn.querySelector('i');
                    icon.classList.remove('fa-times'); 
                    icon.classList.add('fa-bars');
                }
            });
        }

        // Adjust saat resize layar
        window.addEventListener('resize', function() {
            if(window.innerWidth > 768) {
                bodyClass.remove('mobile-sidebar-open');
                if(mobileBtn) {
                    const icon = mobileBtn.querySelector('i');
                    if (icon) {
                        icon.classList.remove('fa-times'); 
                        icon.classList.add('fa-bars');
                    }
                }
            }
            adjustNavbars(bodyClass.contains('sidebar-collapsed'));
        });
    });
</script>