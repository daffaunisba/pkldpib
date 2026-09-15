<?php
// Pastikan koneksi database dan session_start() sudah ada di file utama yang memanggil header ini.
$siswa_id_session = $_SESSION['siswa_id'] ?? '';

// Ambil data murid untuk ditampilkan di profil sidebar & navigasi atas
$query_user_nav = mysqli_query($koneksi, "SELECT p.nama, p.foto_profil, l.nama_lokasi 
                                         FROM peserta_didik p 
                                         LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id 
                                         WHERE p.id = '$siswa_id_session'");
$data_user_nav = mysqli_fetch_assoc($query_user_nav);

$nama_tampilan = $data_user_nav['nama'] ?? 'Murid';
$lokasi_tampilan = $data_user_nav['nama_lokasi'] ?? 'Lokasi Belum Plotting';
$foto_nav_atas = $data_user_nav['foto_profil'] ?? ''; // Ambil nama file foto

// HITUNG PENGUMUMAN BELUM DIBACA UNTUK NOTIFIKASI LONCENG DI SIDEBAR/BOTTOM NAV
$unread_count = 0;
$cek_notif = mysqli_query($koneksi, "
    SELECT COUNT(*) as unread FROM pengumuman 
    WHERE id_pengumuman NOT IN (SELECT id_pengumuman FROM pengumuman_baca WHERE siswa_id = '$siswa_id_session')
");
if ($cek_notif) {
    $unread_count = mysqli_fetch_assoc($cek_notif)['unread'];
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Si Mantap | Siswa</title>
    <link rel="icon" type="image/x-icon" href="logobangunan.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-grad: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #ee74e1 100%);
            --sidebar-grad: linear-gradient(180deg, #243b55 0%, #141e30 100%);
            --bg-body: #f0f3f9; 
            --text-dark: #2d3436;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background-color: var(--bg-body);
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
            display: flex;
            color: var(--text-dark);
            -webkit-tap-highlight-color: transparent;
        }

        /* --- SIDEBAR DESKTOP --- */
        #sidebar-wrapper {
            width: 280px; min-height: 100vh; background: var(--sidebar-grad);
            transition: all 0.3s; position: fixed; z-index: 1000;
            display: flex; flex-direction: column; box-shadow: 10px 0 30px rgba(0, 0, 0, 0.1);
        }
        .sidebar-heading { padding: 2rem 1.5rem 1rem; text-align: center; }
        .brand-text { font-weight: 800; font-size: 1.8rem; background: var(--primary-grad); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        
        .profile-sidebar { background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 20px; margin: 0 1.2rem 1.5rem; padding: 1rem; text-align: center; }
        
        .nav-link-custom { color: rgba(255, 255, 255, 0.7); padding: 1rem 1.5rem; display: flex; align-items: center; text-decoration: none; transition: 0.3s; margin: 0.2rem 1.2rem; border-radius: 15px; font-weight: 500; }
        .nav-link-custom:hover { color: white; background: rgba(255, 255, 255, 0.05); transform: translateX(5px); }
        .nav-link-custom.active { background: var(--primary-grad); color: white; box-shadow: 0 4px 15px rgba(118, 75, 162, 0.4); }
        .nav-link-custom i { width: 35px; font-size: 1.3rem; }

        /* --- WRAPPER KONTEN --- */
        #page-content-wrapper {
            width: 100%; margin-left: 280px; transition: all 0.3s;
            display: flex; flex-direction: column; min-height: 100vh;
        }

        /* --- MOBILE NAV (TOP) --- */
        .mobile-nav {
            display: none; background: var(--primary-grad); padding: 1rem 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); position: sticky; top: 0; z-index: 999;
        }

        /* --- CSS BOTTOM NAVIGATION (FAB STYLE) --- */
        .bottom-nav {
            display: none; position: fixed; bottom: 0; width: 100%; background: #ffffff;
            box-shadow: 0 -5px 20px rgba(0,0,0,0.08); z-index: 1040;
            border-top-left-radius: 25px; border-top-right-radius: 25px;
            padding: 0.5rem; justify-content: space-around; align-items: center;
            height: 70px;
        }
        .bottom-nav-item {
            flex: 1; text-align: center; text-decoration: none; color: #a0a5ba;
            font-size: 0.7rem; font-weight: 600; display: flex; flex-direction: column;
            align-items: center; justify-content: center; transition: 0.3s; height: 100%;
        }
        .bottom-nav-item i { font-size: 1.35rem; margin-bottom: 4px; transition: 0.3s; }
        .bottom-nav-item.active { color: #764ba2; }
        .bottom-nav-item.active i { transform: translateY(-3px); color: #667eea; }

        /* Style Khusus Tombol Tengah (Floating Button) */
        .bottom-nav-center-wrapper {
            position: relative; flex: 1.2; display: flex; flex-direction: column;
            justify-content: flex-end; align-items: center; text-decoration: none; height: 100%;
        }
        .bottom-nav-center-btn {
            position: absolute; top: -35px; width: 62px; height: 62px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            color: white; font-size: 1.6rem; box-shadow: 0 6px 15px rgba(102, 126, 234, 0.4);
            border: 6px solid var(--bg-body); 
            transition: transform 0.3s ease; z-index: 1041;
        }
        .bottom-nav-center-wrapper:hover .bottom-nav-center-btn { transform: scale(1.05); }
        .bottom-nav-center-text { font-size: 0.7rem; font-weight: 600; color: #a0a5ba; margin-top: 30px; transition: 0.3s; }
        .bottom-nav-center-wrapper.active .bottom-nav-center-text { color: #764ba2; }

        /* Responsif HP */
        @media (max-width: 992px) {
            #sidebar-wrapper { display: none; }
            #page-content-wrapper { margin-left: 0; padding-bottom: 110px; }
            .mobile-nav { display: flex; justify-content: space-between; align-items: center; }
            .bottom-nav { display: flex; }
        }
    </style>
</head>

<body>

    <!-- SIDEBAR DESKTOP -->
    <div id="sidebar-wrapper">
        <div class="sidebar-heading">
            <span class="brand-text">SI MANTAP</span>
        </div>
        <div class="profile-sidebar">
            <h6 class="text-white fw-bold mb-1" style="font-size: 0.9rem;"><?= htmlspecialchars($nama_tampilan) ?></h6>
            <p class="mb-0 text-info small" style="font-size: 0.7rem;">
                <i class="fas fa-map-marker-alt me-1"></i> <?= htmlspecialchars($lokasi_tampilan) ?>
            </p>
        </div>
        <div class="list-group list-group-flush mt-0 flex-grow-1">
            <a href="index.php" class="nav-link-custom <?= ($current_page == 'index.php') ? 'active' : '' ?>">
                <i class="fas fa-th-large"></i> Beranda
            </a>
            <a href="pengumuman.php" class="nav-link-custom <?= ($current_page == 'pengumuman.php') ? 'active' : '' ?>">
                <i class="fas fa-bullhorn"></i> Pengumuman
                <?php if ($unread_count > 0): ?>
                    <span class="badge bg-danger ms-auto rounded-pill shadow-sm"><?= $unread_count ?></span>
                <?php endif; ?>
            </a>
            <a href="biodata.php" class="nav-link-custom <?= ($current_page == 'biodata.php') ? 'active' : '' ?>">
                <i class="fas fa-user-circle"></i> Biodata Saya
            </a>
            <a href="riwayat-presensi.php" class="nav-link-custom <?= ($current_page == 'riwayat-presensi.php') ? 'active' : '' ?>">
                <i class="fas fa-clock-rotate-left"></i> Riwayat Absen
            </a>
            <a href="pengajuan-izin.php" class="nav-link-custom <?= ($current_page == 'pengajuan-izin.php') ? 'active' : '' ?>">
                <i class="fas fa-file-signature"></i> Izin / Sakit
            </a>
            <a href="kartu-kendali.php" class="nav-link-custom <?= ($current_page == 'kartu-kendali.php') ? 'active' : '' ?>">
                <i class="fas fa-tasks"></i> Kartu Kendali
            </a>
            <a href="upload-laporan.php" class="nav-link-custom <?= ($current_page == 'upload-laporan.php') ? 'active' : '' ?>">
                <i class="fas fa-cloud-upload-alt"></i> Laporan PKL
            </a>
            <div class="mt-auto p-4 mb-3">
                <a href="logout.php" class="btn btn-danger w-100 rounded-pill shadow" style="background: linear-gradient(135deg, #ed213a 0%, #93291e 100%); border: none;">
                    <i class="fas fa-sign-out-alt me-2"></i> Keluar
                </a>
            </div>
        </div>
    </div>

    <!-- WRAPPER KONTEN UTAMA (Ditutup di footer.php) -->
    <div id="page-content-wrapper">
        
        <!-- NAVIGASI ATAS MOBILE -->
        <nav class="mobile-nav">
            <!-- Teks SI MANTAP (Otomatis ke kiri) -->
            <span class="text-white fw-bold fs-4 tracking-wider">SI MANTAP</span>
            
            <!-- Foto Profil (Otomatis ke kanan) -->
            <a href="biodata.php" class="text-decoration-none" style="text-align: right;">
                <?php if(!empty($foto_nav_atas)): ?>
                    <img src="../assets/img/profile/<?= htmlspecialchars($foto_nav_atas) ?>" alt="Profil" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                <?php else: ?>
                    <i class="fas fa-user-circle fa-2x text-white"></i>
                <?php endif; ?>
            </a>
        </nav>
        
        <!-- Konten Halaman PHP Anda Akan Masuk Di Sini -->