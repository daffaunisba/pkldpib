<?php
session_start();
include '../config/db-koneksi.php';

// Helper function untuk mencatat log ke database
function catatLogLoginSiswa($koneksi, $siswa_id, $username, $nama, $metode) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $ua = $_SERVER['HTTP_USER_AGENT'];
    
    $log_stmt = $koneksi->prepare("INSERT INTO log_aktivitas_login_siswa (siswa_id, username, nama_siswa, ip_address, user_agent, metode_login) VALUES (?, ?, ?, ?, ?, ?)");
    if ($log_stmt) {
        $log_stmt->bind_param("isssss", $siswa_id, $username, $nama, $ip, $ua, $metode);
        $log_stmt->execute();
        $log_stmt->close();
    }
}

// ==========================================
// 1. Cek Cookie (Fitur Simpan Login Jangka Panjang)
// ==========================================
if (isset($_COOKIE['id_murid']) && isset($_COOKIE['key'])) {
    $id = $_COOKIE['id_murid'];
    $key = $_COOKIE['key'];

    // Gunakan Prepared Statement untuk keamanan
    $stmt = $koneksi->prepare("SELECT id, username, nama, lokasi_id FROM peserta_didik WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    // Cek kecocokan hash username
    if ($row && $key === hash('sha256', $row['username'])) {
        // Jika sesi belum ada, buat sesi baru dan catat log masuk otomatis via Cookie
        if (!isset($_SESSION['siswa_id'])) {
            $_SESSION['siswa_id']   = $row['id'];
            $_SESSION['nama_siswa'] = $row['nama'];
            $_SESSION['lokasi_id']  = $row['lokasi_id'];
            
            // CATAT LOG MASUK VIA COOKIE
            catatLogLoginSiswa($koneksi, $row['id'], $row['username'], $row['nama'], 'Cookie');
        }
    }
}

// Jika sudah ada session login, langsung ke dashboard
if (isset($_SESSION['siswa_id'])) {
    header("Location: index.php");
    exit();
}

// ==========================================
// 2. Proses Login Manual
// ==========================================
$error = '';
if (isset($_POST['login'])) {
    $user = $_POST['username']; 
    $pass = $_POST['password'];

    // Gunakan Prepared Statement
    $stmt = $koneksi->prepare("SELECT * FROM peserta_didik WHERE username = ?");
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();

        if (password_verify($pass, $data['password'])) {
            // Set Session
            $_SESSION['siswa_id']   = $data['id'];
            $_SESSION['nama_siswa'] = $data['nama'];
            $_SESSION['lokasi_id']  = $data['lokasi_id'];

            // CATAT LOG MASUK MANUALLY
            catatLogLoginSiswa($koneksi, $data['id'], $data['username'], $data['nama'], 'Manual');

            // Fitur Simpan Login 
            if (isset($_POST['remember'])) {
                $satu_tahun = time() + (60 * 60 * 24 * 365);
                setcookie('id_murid', $data['id'], $satu_tahun, '/');
                setcookie('key', hash('sha256', $data['username']), $satu_tahun, '/');
            }

            header("Location: index.php");
            exit();
        } else {
            $error = "Password salah!";
        }
    } else {
        $error = "Username tidak ditemukan!";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Murid | Si Mantap</title>
    <link rel="icon" type="image/x-icon" href="logobangunan.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#667eea">
    <link rel="apple-touch-icon" href="logobangunan.png">
    
    <style>
        :root {
            --primary-grad: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --accent-purple: #667eea;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #1a1a2e; /* Fallback color */
            overflow-x: hidden;
        }

        /* --- FULL SCREEN GALAXY BACKGROUND --- */
        .login-layout {
            position: relative;
            min-height: 100vh;
            width: 100%;
            background: var(--primary-grad);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            z-index: 1;
        }

        /* Latar Belakang Lingkaran Halus */
        .login-layout::before { content: ''; position: absolute; top: -15%; left: -10%; width: 50vw; height: 50vw; background: rgba(255, 255, 255, 0.04); border-radius: 50%; z-index: -1; }
        .login-layout::after { content: ''; position: absolute; bottom: -10%; right: -5%; width: 40vw; height: 40vw; background: rgba(255, 255, 255, 0.04); border-radius: 50%; z-index: -1; }

        /* ==================================================== */
        /* --- SPACE ELEMENTS (ANIMASI DI BELAKANG FORM) --- */
        /* ==================================================== */
        .space-elements {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            z-index: 0; pointer-events: none;
        }

        /* Bintang Kelap-Kelip */
        .star { position: absolute; background: white; border-radius: 50%; animation: twinkle 3s infinite ease-in-out alternate; }
        @keyframes twinkle { 0% { opacity: 0.2; transform: scale(0.8); } 100% { opacity: 1; transform: scale(1.2); box-shadow: 0 0 10px #fff; } }

        /* Planet 3D Melayang */
        .planet { position: absolute; border-radius: 50%; box-shadow: inset -15px -15px 25px rgba(0,0,0,0.3), 0 0 20px rgba(255,255,255,0.1); }
        .planet-1 { width: 150px; height: 150px; background: linear-gradient(135deg, #fcd34d 0%, #d97706 100%); top: 10%; right: 8%; animation: floatObj 8s ease-in-out infinite; }
        .planet-2 { width: 80px; height: 80px; background: linear-gradient(135deg, #f472b6 0%, #db2777 100%); bottom: 20%; left: 8%; animation: floatObj 10s ease-in-out infinite reverse; }
        .planet-3 { width: 50px; height: 50px; background: linear-gradient(135deg, #38bdf8 0%, #0284c7 100%); top: 30%; left: 35%; animation: floatObj 6s ease-in-out infinite 1s; }
        
        @keyframes floatObj { 0%, 100% { transform: translateY(0) rotate(0deg); } 50% { transform: translateY(-30px) rotate(5deg); } }

        /* Roket Terbang Melintasi Layar */
        .rocket-container { position: absolute; bottom: -100px; left: -100px; animation: flyRocket 18s linear infinite; filter: drop-shadow(0 10px 15px rgba(255,255,255,0.4)); z-index: 1; }
        .rocket-icon { font-size: 5rem; color: #ffffff; transform: rotate(45deg); }
        @keyframes flyRocket {
            0% { transform: translate(0, 0) scale(0.8); opacity: 0; }
            10% { opacity: 1; }
            80% { opacity: 1; transform: translate(70vw, -80vh) scale(1.5); }
            100% { transform: translate(85vw, -100vh) scale(1.5); opacity: 0; }
        }

        /* Bintang Jatuh (Shooting Star) */
        .shooting-star { position: absolute; top: 15%; right: -20%; width: 150px; height: 2px; background: linear-gradient(90deg, rgba(255,255,255,0), #fff); animation: shootingStar 7s linear infinite; transform: rotate(-45deg); z-index: 1; }
        @keyframes shootingStar {
            0% { transform: translate(0, 0) rotate(-45deg); opacity: 1; }
            15% { transform: translate(-100vw, 100vh) rotate(-45deg); opacity: 0; }
            100% { transform: translate(-100vw, 100vh) rotate(-45deg); opacity: 0; }
        }

        /* Gelombang Bawah */
        .waves { position: absolute; bottom: 0; left: 0; width: 100%; height: 18vh; min-height: 120px; max-height: 180px; margin-bottom: -7px; z-index: 2; pointer-events: none; }
        .parallax > use { animation: move-forever 25s cubic-bezier(.55,.5,.45,.5) infinite; }
        .parallax > use:nth-child(1) { animation-delay: -2s; animation-duration: 7s; }
        .parallax > use:nth-child(2) { animation-delay: -3s; animation-duration: 10s; }
        .parallax > use:nth-child(3) { animation-delay: -4s; animation-duration: 13s; }
        .parallax > use:nth-child(4) { animation-delay: -5s; animation-duration: 20s; }
        @keyframes move-forever { 0% { transform: translate3d(-90px,0,0); } 100% { transform: translate3d(85px,0,0); } }


        /* ==================================================== */
        /* --- FOREGROUND: KONTEN DEPAN (Teks & Form) --- */
        /* ==================================================== */
        .content-wrapper {
            position: relative;
            z-index: 10; /* Berada di depan background */
            width: 100%;
            max-width: 1100px;
            padding: 2rem 1rem;
        }

        /* Konten Motivasi Kiri */
        .motivation-content { color: white; animation: fadeInLeft 1s ease-out; text-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .motivation-badge { display: inline-block; background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(10px); padding: 8px 20px; border-radius: 50px; font-size: 0.85rem; font-weight: 800; letter-spacing: 1px; margin-bottom: 1.5rem; border: 1px solid rgba(255, 255, 255, 0.4); box-shadow: 0 4px 15px rgba(0,0,0,0.1); color: white; }
        .motivation-title { font-size: 3.5rem; font-weight: 800; line-height: 1.2; margin-bottom: 1.2rem; }
        .motivation-text { font-size: 1.15rem; opacity: 0.95; line-height: 1.6; font-weight: 400; }

        /* Kartu Login Kanan */
        .login-card {
            background: #ffffff;
            border-radius: 30px;
            padding: 45px 40px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.8);
            animation: fadeInRight 1s ease-out;
            margin: 0 auto;
        }

        .brand-logo {
            width: 75px; height: 75px; background: var(--primary-grad); border-radius: 22px;
            display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; 
            font-size: 32px; color: white; box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
            transform: rotate(-5deg); transition: 0.3s;
        }
        .brand-logo:hover { transform: rotate(0deg) scale(1.05); }

        .form-label { font-weight: 700; font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 8px; }
        .input-wrapper { position: relative; margin-bottom: 20px; }
        .input-wrapper i { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #cbd5e1; transition: 0.3s; }
        
        .form-control { border-radius: 16px; padding: 14px 15px 14px 48px; background: #f8fafc; border: 2px solid #e2e8f0; color: #1e293b; font-weight: 600; font-size: 0.95rem; transition: 0.3s; }
        .form-control:focus { background: #fff; border-color: var(--accent-purple); box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15); outline: none; }
        .form-control:focus + i { color: var(--accent-purple); }

        .btn-login { background: var(--primary-grad); border: none; border-radius: 16px; padding: 16px; font-weight: 800; color: white; letter-spacing: 0.5px; transition: 0.3s; margin-top: 10px; font-size: 0.95rem; box-shadow: 0 10px 20px rgba(102, 126, 234, 0.25); }
        .btn-login:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(102, 126, 234, 0.35); color: white; }

        /* Style Tombol Kembali Ke Beranda */
        .btn-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            margin-top: 15px;
            background: #f1f5f9;
            color: #64748b;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        .btn-back i {
            margin-right: 8px;
            transition: transform 0.3s ease;
        }
        .btn-back:hover {
            background: #e2e8f0;
            color: var(--accent-purple);
        }
        .btn-back:hover i {
            transform: translateX(-4px);
        }

        .alert-error { background: #fff1f2; border: 1px solid #ffe4e6; color: #e11d48; border-radius: 14px; font-size: 0.85rem; font-weight: 700; }
        .form-check-input:checked { background-color: var(--accent-purple); border-color: var(--accent-purple); }
        .eye-toggle { position: absolute; right: 18px; top: 50%; transform: translateY(-50%); color: #94a3b8; cursor: pointer; z-index: 10; transition: 0.2s; }
        .eye-toggle:hover { color: var(--accent-purple); }

        @keyframes fadeInLeft { from { opacity: 0; transform: translateX(-30px); } to { opacity: 1; transform: translateX(0); } }
        @keyframes fadeInRight { from { opacity: 0; transform: translateX(30px); } to { opacity: 1; transform: translateX(0); } }

        /* --- RESPONSIVE MOBILE --- */
        @media (max-width: 992px) {
            .motivation-content { text-align: center; margin-bottom: 3rem; }
            .motivation-title { font-size: 2.2rem; }
            .login-card { padding: 35px 25px; box-shadow: 0 15px 40px rgba(0,0,0,0.15); }
            .rocket-container { display: none; } /* Sembunyikan roket di HP agar tidak terlalu ramai */
            .waves { height: 10vh; }
        }
    </style>
</head>

<body>

    <div class="login-layout">
        
        <div class="space-elements">
            <div class="star" style="top: 15%; left: 25%; width: 4px; height: 4px; animation-delay: 0s;"></div>
            <div class="star" style="top: 30%; left: 85%; width: 6px; height: 6px; animation-delay: 1s;"></div>
            <div class="star" style="top: 65%; left: 10%; width: 3px; height: 3px; animation-delay: 0.5s;"></div>
            <div class="star" style="top: 75%; left: 75%; width: 5px; height: 5px; animation-delay: 1.5s;"></div>
            <div class="star" style="top: 45%; left: 55%; width: 4px; height: 4px; animation-delay: 2s;"></div>
            
            <div class="shooting-star"></div>

            <div class="planet planet-1"></div>
            <div class="planet planet-2"></div>
            <div class="planet planet-3"></div>

            <div class="rocket-container">
                <i class="fas fa-rocket rocket-icon"></i>
            </div>
        </div>

        <svg class="waves" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 24 150 28" preserveAspectRatio="none" shape-rendering="auto">
            <defs>
                <path id="gentle-wave" d="M-160 44c30 0 58-18 88-18s 58 18 88 18 58-18 88-18 58 18 88 18 v44h-352z" />
            </defs>
            <g class="parallax">
                <use xlink:href="#gentle-wave" x="48" y="0" fill="rgba(255, 255, 255, 0.15)" />
                <use xlink:href="#gentle-wave" x="48" y="3" fill="rgba(255, 255, 255, 0.1)" />
                <use xlink:href="#gentle-wave" x="48" y="5" fill="rgba(255, 255, 255, 0.05)" />
                <use xlink:href="#gentle-wave" x="48" y="7" fill="rgba(255, 255, 255, 0.02)" />
            </g>
        </svg>

        <div class="container content-wrapper">
            <div class="row align-items-center justify-content-between w-100 m-0">
                
                <div class="col-lg-6 col-12 px-0 pe-lg-4">
                    <div class="motivation-content">
                        <div class="motivation-badge">
                            <i class="fas fa-satellite me-2 text-warning"></i> v2.0 Si Mantap App
                        </div>
                        <h1 class="motivation-title">Masa Depan<br>Dimulai Hari Ini.</h1>
                        <p class="motivation-text">Bersiaplah meluncur menuju kesuksesan magang. Disiplin, fokus, dan konsistensi adalah bahan bakar utamamu!</p>
                    </div>
                </div>

                <div class="col-lg-5 col-12 px-0 mt-4 mt-lg-0">
                    <div class="login-card">
                        <div class="text-center mb-4">
                            <div class="brand-logo">
                                <i class="fas fa-user-astronaut"></i>
                            </div>
                            <h4 class="fw-bold mb-1" style="color: #1e293b;">Portal Murid</h4>
                            <p class="small fw-semibold" style="color: #94a3b8;">Silakan masuk ke planet SI MANTAP</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-error text-center py-2 mb-4">
                                <i class="fas fa-circle-exclamation me-2"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <div class="input-wrapper">
                                    <input type="text" name="username" class="form-control" placeholder="Ketikkan Username" required autofocus>
                                    <i class="fas fa-user"></i>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <div class="input-wrapper">
                                    <input type="password" name="password" id="passInput" class="form-control" placeholder="••••••••" required>
                                    <i class="fas fa-lock"></i>
                                    <span class="eye-toggle" onclick="togglePassword()">
                                        <i class="fas fa-eye-slash" id="eyeIcon"></i>
                                    </span>
                                </div>
                            </div>

                            <div class="mb-4 form-check d-flex align-items-center">
                                <input type="checkbox" name="remember" class="form-check-input mt-0 me-2" id="remember" checked style="width: 1.2rem; height: 1.2rem;">
                                <label class="form-check-label small fw-bold" for="remember" style="color: #64748b; cursor:pointer; margin-top:2px;">Ingat saya di perangkat ini</label>
                            </div>

                            <button type="submit" name="login" class="btn btn-login w-100">
                                MASUK SISTEM <i class="fas fa-sign-in-alt ms-2"></i>
                            </button>
                        </form>

                        <div class="text-center">
                            <a href="../index.php" class="btn-back">
                                <i class="fas fa-arrow-left"></i> Kembali ke Beranda
                            </a>
                        </div>
                        
                        <div class="text-center mt-4">
                            <p style="font-size: 0.7rem; color: #cbd5e1; font-weight: 600; margin:0;">
                                &copy; <?php echo date('Y'); ?> Tim IT DPIB SMK Islam 1 Blitar
                            </p>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <script>
        function togglePassword() {
            const passInput = document.getElementById('passInput');
            const eyeIcon = document.getElementById('eyeIcon');
            if (passInput.type === 'password') {
                passInput.type = 'text';
                eyeIcon.classList.replace('fa-eye-slash', 'fa-eye');
                eyeIcon.style.color = 'var(--accent-purple)';
            } else {
                passInput.type = 'password';
                eyeIcon.classList.replace('fa-eye', 'fa-eye-slash');
                eyeIcon.style.color = '#94a3b8';
            }
        }
    </script>

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js')
                    .then(reg => console.log('PWA Service Worker Terdaftar!'))
                    .catch(err => console.log('PWA gagal terdaftar:', err));
            });
        }
    </script>

</body>
</html>