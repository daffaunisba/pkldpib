<?php 
// admin/panel/navbar.php

// Pastikan zona waktu sudah sesuai (WIB)
date_default_timezone_set('Asia/Jakarta');

// Logika Sapaan Berdasarkan Waktu
$jam = date('H');
if ($jam >= 3 && $jam < 11) {
    $sapaan = 'Selamat Pagi';
} elseif ($jam >= 11 && $jam < 15) {
    $sapaan = 'Selamat Siang';
} elseif ($jam >= 15 && $jam < 18) {
    $sapaan = 'Selamat Sore';
} else {
    $sapaan = 'Selamat Malam';
}

// Ambil data dari Sesi
$current_full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : (isset($current_user) ? $current_user : 'Pengguna');
$current_user_id = $_SESSION['user_id'] ?? 0;
$profile_photo_path = $_SESSION['profile_photo'] ?? '';
$upload_base_path = '../uploads/profiles/';

// Tentukan Label Jabatan (Role)
$current_role = isset($_SESSION['level']) ? strtolower($_SESSION['level']) : 'admin';
$role_label = ($current_role === 'admin') ? 'Administrator Sistem' : (($current_role === 'guru' || $current_role === 'pembimbing') ? 'Guru Pembimbing' : 'Staf Pegawai');
$role_color = ($current_role === 'admin') ? '#8b5cf6' : '#10b981'; // Ungu untuk Admin, Hijau untuk Guru
?>

<div class="main-content-wrapper">
    
    <nav class="top-navbar-vibrant">
        <div class="nav-left-section">
            <span class="mobile-brand-title">
                <i class="fas fa-cube brand-icon-anim"></i> SI MANTAP <span style="font-weight: 300;">PKL</span>
            </span>

            <div class="user-profile-group">
                <div class="profile-img-wrapper">
                    <?php if (!empty($profile_photo_path)): ?>
                        <img src="<?php echo $upload_base_path . htmlspecialchars($profile_photo_path); ?>" alt="Foto Profil" class="profile-photo-nav">
                    <?php else: ?>
                        <div class="default-avatar-gradient">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                    <span class="status-pulse-dot"></span>
                </div>
                
                <div class="user-info-text">
                    <span class="user-name">
                        <span style="font-weight: 400; color: #64748b;"><?php echo $sapaan; ?>,</span> <?php echo htmlspecialchars($current_full_name); ?>
                    </span>
                    <span class="user-role-badge" style="border: 1px solid <?php echo $role_color; ?>40; color: <?php echo $role_color; ?>; background: <?php echo $role_color; ?>10;">
                        <i class="fas <?php echo ($current_role === 'admin') ? 'fa-shield-alt' : 'fa-chalkboard-teacher'; ?>"></i> <?php echo $role_label; ?>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="nav-right-actions">
            <?php if ($current_user_id): ?>
            <a href="user-edit.php?id=<?php echo $current_user_id; ?>" class="btn-nav-vibrant btn-nav-profile" title="Pengaturan Profil">
                <i class="fas fa-user-edit"></i> <span class="action-text">Profil</span>
            </a>
            <?php endif; ?>
            
            <a href="logout.php" class="btn-nav-vibrant btn-nav-logout" title="Keluar Aplikasi">
                <i class="fas fa-power-off"></i> <span class="action-text">Keluar</span>
            </a>
        </div>
    </nav>

    <main class="admin-main-content">
        <div class="container">

<style>
    /* =========================================================================
       1. CSS DASAR NAVBAR DESKTOP VIBRANT (HIDUP & MODERN)
       ========================================================================= */
    :root {
        --nav-height: 75px; /* Sedikit dikecilkan agar lebih compact */
    }

    .top-navbar-vibrant {
        position: sticky; 
        top: 0;
        z-index: 999; 
        width: 100%;
        height: var(--nav-height);
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 35px;
        background: rgba(255, 255, 255, 0.85); /* Agak transparan */
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border-bottom: 1px solid rgba(226, 232, 240, 0.8);
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.05); /* Soft glowing shadow */
        box-sizing: border-box;
        transition: all 0.3s ease-in-out;
    }

    .nav-left-section {
        display: flex;
        align-items: center;
        height: 100%;
    }
    
    .mobile-brand-title {
        display: none;
    }

    /* Profil Grup Kiri */
    .user-profile-group {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 6px 14px 6px 6px;
        border-radius: 50px;
        transition: 0.3s ease;
        cursor: pointer;
    }
    .user-profile-group:hover {
        background: #f1f5f9;
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
    }

    .profile-img-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .profile-photo-nav {
        width: 42px; /* Ukuran foto dikecilkan proporsional */
        height: 42px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid white;
        box-shadow: 0 4px 10px rgba(30, 64, 175, 0.2);
        transition: 0.3s ease;
    }
    .user-profile-group:hover .profile-photo-nav {
        transform: scale(1.05) rotate(2deg);
    }
    
    /* Default Avatar Gradient yang Keren */
    .default-avatar-gradient {
        width: 42px; /* Ukuran disamakan dengan foto */
        height: 42px;
        border-radius: 50%;
        background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        border: 2px solid white;
        box-shadow: 0 4px 10px rgba(30, 64, 175, 0.2);
    }

    /* Titik Status Berdenyut (Pulse) */
    .status-pulse-dot {
        position: absolute;
        bottom: 2px;
        right: -2px;
        width: 12px;
        height: 12px;
        background-color: #10b981;
        border: 2px solid white;
        border-radius: 50%;
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
        animation: pulse-green 2s infinite;
    }

    @keyframes pulse-green {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }

    .user-info-text {
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 2px;
    }

    .user-name {
        font-size: 14px; /* Ukuran font nama diperkecil */
        color: #0f172a;
        font-weight: 700;
        letter-spacing: -0.2px;
        font-family: 'Poppins', sans-serif;
    }

    .user-role-badge {
        font-size: 9.5px; /* Badge diperkecil sedikit */
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 50px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        width: max-content;
    }

    /* Aksi Grup Kanan */
    .nav-right-actions {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .btn-nav-vibrant {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px; /* Padding tombol diperkecil */
        border-radius: 50px;
        font-size: 12px; /* Ukuran font tombol diperkecil */
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        font-family: 'Poppins', sans-serif;
        overflow: hidden;
        position: relative;
    }

    .btn-nav-vibrant i { z-index: 2; position: relative; }
    .btn-nav-vibrant .action-text { z-index: 2; position: relative; }

    /* Tombol Profil Biru Halus */
    .btn-nav-profile {
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
    }
    .btn-nav-profile:hover {
        background: #1d4ed8;
        color: white;
        box-shadow: 0 4px 12px rgba(29, 78, 216, 0.25);
        transform: translateY(-2px);
    }

    /* Tombol Logout Merah Menyala */
    .btn-nav-logout {
        background: #fef2f2;
        color: #dc2626;
        border: 1px solid #fecaca;
    }
    .btn-nav-logout:hover {
        background: #dc2626;
        color: white;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
        transform: translateY(-2px);
    }

    /* =========================================================================
       2. OPTIMASI SUPER RESPONSIVE UNTUK MOBILE (MAKSIMAL 768px)
       ========================================================================= */
    @media (max-width: 768px) {
        :root {
            --nav-height: 65px;
        }

        .top-navbar-vibrant {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: var(--nav-height) !important;
            padding: 0 15px 0 65px !important; /* Space kiri untuk hamburger menu */
            background: rgba(255, 255, 255, 0.95) !important; 
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            box-sizing: border-box !important;
            border-bottom: 1px solid #e2e8f0 !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05) !important;
        }
        
        .nav-left-section {
            flex: 1 !important;
            min-width: 0 !important;
        }

        .mobile-brand-title {
            display: inline-flex !important;
            align-items: center;
            gap: 6px;
            font-size: 15px !important; /* Judul versi mobile diperkecil */
            font-weight: 800 !important;
            color: var(--mantap-blue-dark) !important;
            letter-spacing: 0.5px;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important; 
        }
        
        /* Ikon kubus berputar kecil di judul mobile */
        .brand-icon-anim {
            color: var(--mantap-blue-main);
            animation: spin-cube 4s linear infinite;
        }
        @keyframes spin-cube { 100% { transform: rotate(360deg); } }
        
        /* Sembunyikan elemen desktop kompleks di HP */
        .user-profile-group,
        .btn-nav-vibrant .action-text,
        .btn-nav-profile {
            display: none !important; 
        }
        
        .nav-right-actions {
            margin-left: 10px !important;
            flex-shrink: 0 !important; 
        }
        
        /* Tombol Logout Minimalis untuk HP */
        .btn-nav-logout {
            padding: 0 !important;
            width: 38px !important;
            height: 38px !important;
            border-radius: 10px !important; /* Bentuk squircle (kotak membulat) */
            justify-content: center !important;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
            color: white !important;
            border: none !important;
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3) !important;
        }
        .btn-nav-logout i {
            margin: 0 !important;
            font-size: 14px !important;
        }
        .btn-nav-logout:hover {
            transform: scale(1.05) !important;
        }
    }
</style>