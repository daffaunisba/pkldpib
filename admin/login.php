<?php
// admin/login.php
include '../config/db-koneksi.php';

// Mulai sesi
session_start();

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. VERIFIKASI KEAMANAN (CAPTCHA MATEMATIKA)
    $jawaban_user = isset($_POST['captcha_answer']) ? (int)$_POST['captcha_answer'] : 0;
    $jawaban_benar = isset($_SESSION['captcha_result']) ? $_SESSION['captcha_result'] : -1;

    if ($jawaban_user !== $jawaban_benar) {
        $message = "<div class='alert alert-error text-center py-2 mb-4'><i class='fas fa-shield-alt me-2'></i> Keamanan salah. Silakan hitung dengan benar.</div>";
    } else {
        // 2. PROSES LOGIN JIKA VERIFIKASI BENAR
        $username = mysqli_real_escape_string($koneksi, $_POST['username']);
        $password = $_POST['password'];

        // Ambil data user
        $stmt = $koneksi->prepare("SELECT id, username, password, role, full_name, profile_photo FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Cek Password
            if (password_verify($password, $user['password'])) {
                
                $user_role = strtolower($user['role']); 
                
                // Izinkan akses jika role sesuai
                if ($user_role === 'admin' || $user_role === 'pembimbing' || $user_role === 'guru') {
                    
                    // --- SET SESSION ---
                    $_SESSION['logged_in'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['level'] = $user_role; 
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['profile_photo'] = $user['profile_photo'];

                    // --- LOG AKTIVITAS ---
                    $ip_address = $_SERVER['REMOTE_ADDR'];
                    $user_id_log = $user['id'];
                    $aksi_log = "Berhasil Login ke Panel Admin";
                    
                    $stmt_log = $koneksi->prepare("INSERT INTO log_aktivitas (user_id, aksi, ip_address) VALUES (?, ?, ?)");
                    $stmt_log->bind_param("iss", $user_id_log, $aksi_log, $ip_address);
                    $stmt_log->execute();
                    $stmt_log->close();

                    $_SESSION['login_success_message'] = "Selamat Datang, " . htmlspecialchars($user['full_name']) . "!";
                    
                    // Redirect berdasarkan role
                    if ($user_role === 'pembimbing' || $user_role === 'guru') {
                        header("Location: dashboard.php");
                    } else {
                        header("Location: dashboard.php");
                    }
                    exit(); 
                    
                } else {
                    $message = "<div class='alert alert-error text-center py-2 mb-4'><i class='fas fa-ban me-2'></i> Akses ditolak. Akun ini tidak memiliki hak akses admin.</div>";
                }
            } else {
                $message = "<div class='alert alert-error text-center py-2 mb-4'><i class='fas fa-exclamation-circle me-2'></i> Username atau Password salah.</div>";
            }
        } else {
            $message = "<div class='alert alert-error text-center py-2 mb-4'><i class='fas fa-exclamation-circle me-2'></i> Username atau Password salah.</div>";
        }
        $stmt->close();
    }
}

// GENERATE SOAL KEAMANAN BARU UNTUK TAMPILAN FORM
$angka1 = rand(1, 10);
$angka2 = rand(1, 10);
$_SESSION['captcha_result'] = $angka1 + $angka2; // Simpan hasil yang benar ke session
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Pembimbing | Si Mantap</title>
    <link rel="icon" type="image/x-icon" href="../img/logobangunan.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta name="theme-color" content="#1e3a8a">
    
    <style>
        :root {
            /* Gradasi disesuaikan jadi lebih elegan/serius untuk guru */
            --primary-grad: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            --accent-blue: #3b82f6;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #0f172a; 
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

        .login-layout::before { content: ''; position: absolute; top: -15%; left: -10%; width: 50vw; height: 50vw; background: rgba(255, 255, 255, 0.04); border-radius: 50%; z-index: -1; }
        .login-layout::after { content: ''; position: absolute; bottom: -10%; right: -5%; width: 40vw; height: 40vw; background: rgba(255, 255, 255, 0.04); border-radius: 50%; z-index: -1; }

        /* --- SPACE ELEMENTS --- */
        .space-elements {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            z-index: 0; pointer-events: none;
        }

        .star { position: absolute; background: white; border-radius: 50%; animation: twinkle 3s infinite ease-in-out alternate; }
        @keyframes twinkle { 0% { opacity: 0.2; transform: scale(0.8); } 100% { opacity: 1; transform: scale(1.2); box-shadow: 0 0 10px #fff; } }

        .planet { position: absolute; border-radius: 50%; box-shadow: inset -15px -15px 25px rgba(0,0,0,0.3), 0 0 20px rgba(255,255,255,0.1); }
        .planet-1 { width: 120px; height: 120px; background: linear-gradient(135deg, #fbbf24 0%, #b45309 100%); top: 15%; right: 10%; animation: floatObj 9s ease-in-out infinite; }
        .planet-2 { width: 60px; height: 60px; background: linear-gradient(135deg, #10b981 0%, #047857 100%); bottom: 25%; left: 10%; animation: floatObj 11s ease-in-out infinite reverse; }
        
        @keyframes floatObj { 0%, 100% { transform: translateY(0) rotate(0deg); } 50% { transform: translateY(-30px) rotate(5deg); } }

        .shooting-star { position: absolute; top: 10%; right: -20%; width: 150px; height: 2px; background: linear-gradient(90deg, rgba(255,255,255,0), #fff); animation: shootingStar 8s linear infinite; transform: rotate(-45deg); z-index: 1; }
        @keyframes shootingStar { 0% { transform: translate(0, 0) rotate(-45deg); opacity: 1; } 15%, 100% { transform: translate(-100vw, 100vh) rotate(-45deg); opacity: 0; } }

        /* Gelombang Bawah */
        .waves { position: absolute; bottom: 0; left: 0; width: 100%; height: 18vh; min-height: 120px; max-height: 180px; margin-bottom: -7px; z-index: 2; pointer-events: none; }
        .parallax > use { animation: move-forever 25s cubic-bezier(.55,.5,.45,.5) infinite; }
        .parallax > use:nth-child(1) { animation-delay: -2s; animation-duration: 7s; }
        .parallax > use:nth-child(2) { animation-delay: -3s; animation-duration: 10s; }
        .parallax > use:nth-child(3) { animation-delay: -4s; animation-duration: 13s; }
        .parallax > use:nth-child(4) { animation-delay: -5s; animation-duration: 20s; }
        @keyframes move-forever { 0% { transform: translate3d(-90px,0,0); } 100% { transform: translate3d(85px,0,0); } }

        /* --- KONTEN --- */
        .content-wrapper { position: relative; z-index: 10; width: 100%; max-width: 1100px; padding: 2rem 1rem; }

        .motivation-content { color: white; animation: fadeInLeft 1s ease-out; text-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .motivation-badge { display: inline-block; background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(10px); padding: 8px 20px; border-radius: 50px; font-size: 0.85rem; font-weight: 800; letter-spacing: 1px; margin-bottom: 1.5rem; border: 1px solid rgba(255, 255, 255, 0.4); box-shadow: 0 4px 15px rgba(0,0,0,0.1); color: white; }
        .motivation-title { font-size: 3.5rem; font-weight: 800; line-height: 1.2; margin-bottom: 1.2rem; }
        .motivation-text { font-size: 1.15rem; opacity: 0.95; line-height: 1.6; font-weight: 400; }

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
            font-size: 32px; color: white; box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);
            transform: rotate(-5deg); transition: 0.3s;
        }
        .brand-logo:hover { transform: rotate(0deg) scale(1.05); }

        .form-label { font-weight: 700; font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 8px; }
        .input-wrapper { position: relative; margin-bottom: 20px; }
        .input-wrapper i.fa-icon-left { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #cbd5e1; transition: 0.3s; }
        
        .form-control { border-radius: 16px; padding: 14px 15px 14px 48px; background: #f8fafc; border: 2px solid #e2e8f0; color: #1e293b; font-weight: 600; font-size: 0.95rem; transition: 0.3s; }
        .form-control:focus { background: #fff; border-color: var(--accent-blue); box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15); outline: none; }
        .form-control:focus + i.fa-icon-left { color: var(--accent-blue); }

        /* Spesial untuk Captcha agar icon tetep pas */
        .captcha-label { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .captcha-math { background: #e0e7ff; color: #3730a3; padding: 4px 12px; border-radius: 8px; font-weight: 800; font-size: 0.9rem; letter-spacing: 1px;}

        .btn-login { background: var(--primary-grad); border: none; border-radius: 16px; padding: 16px; font-weight: 800; color: white; letter-spacing: 0.5px; transition: 0.3s; margin-top: 10px; font-size: 0.95rem; box-shadow: 0 10px 20px rgba(59, 130, 246, 0.25); }
        .btn-login:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(59, 130, 246, 0.35); color: white; }

        .btn-back { display: inline-flex; align-items: center; justify-content: center; padding: 10px 20px; margin-top: 15px; background: #f1f5f9; color: #64748b; text-decoration: none; font-weight: 600; font-size: 0.9rem; border-radius: 12px; transition: all 0.3s ease; }
        .btn-back i { margin-right: 8px; transition: transform 0.3s ease; }
        .btn-back:hover { background: #e2e8f0; color: var(--accent-blue); }
        .btn-back:hover i { transform: translateX(-4px); }

        .alert-error { background: #fff1f2; border: 1px solid #ffe4e6; color: #e11d48; border-radius: 14px; font-size: 0.85rem; font-weight: 700; }
        .eye-toggle { position: absolute; right: 18px; top: 50%; transform: translateY(-50%); color: #94a3b8; cursor: pointer; z-index: 10; transition: 0.2s; }
        .eye-toggle:hover { color: var(--accent-blue); }

        @keyframes fadeInLeft { from { opacity: 0; transform: translateX(-30px); } to { opacity: 1; transform: translateX(0); } }
        @keyframes fadeInRight { from { opacity: 0; transform: translateX(30px); } to { opacity: 1; transform: translateX(0); } }

        @media (max-width: 992px) {
            .motivation-content { text-align: center; margin-bottom: 3rem; }
            .motivation-title { font-size: 2.2rem; }
            .login-card { padding: 35px 25px; box-shadow: 0 15px 40px rgba(0,0,0,0.15); }
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
        </div>

        <svg class="waves" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 24 150 28" preserveAspectRatio="none" shape-rendering="auto">
            <defs><path id="gentle-wave" d="M-160 44c30 0 58-18 88-18s 58 18 88 18 58-18 88-18 58 18 88 18 v44h-352z" /></defs>
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
                        <h1 class="motivation-title">Membimbing<br>Generasi Depan.</h1>
                        <p class="motivation-text">Awasi, pantau, dan arahkan murid menuju kesuksesan Praktik Kerja Lapangan dari pusat kendali utama SI MANTAP.</p>
                    </div>
                </div>

                <div class="col-lg-5 col-12 px-0 mt-4 mt-lg-0">
                    <div class="login-card">
                        <div class="text-center mb-4">
                            <div class="brand-logo">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <h4 class="fw-bold mb-1" style="color: #1e293b;">Portal Pembimbing</h4>
                            <p class="small fw-semibold" style="color: #94a3b8;">Silakan masuk ke planet SI MANTAP</p>
                        </div>

                        <?php echo $message; ?>

                        <form method="POST" action="login.php" id="loginForm">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <div class="input-wrapper">
                                    <input type="text" name="username" class="form-control" placeholder="Ketikkan Username" required autofocus>
                                    <i class="fas fa-user fa-icon-left"></i>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <div class="input-wrapper">
                                    <input type="password" name="password" id="passInput" class="form-control" placeholder="••••••••" required>
                                    <i class="fas fa-lock fa-icon-left"></i>
                                    <span class="eye-toggle" onclick="togglePassword()">
                                        <i class="fas fa-eye-slash" id="eyeIcon"></i>
                                    </span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="captcha-label">
                                    <label class="form-label mb-0">Verifikasi Keamanan</label>
                                    <span class="captcha-math"><?php echo $angka1 . ' + ' . $angka2; ?> = ?</span>
                                </div>
                                <div class="input-wrapper mb-1">
                                    <input type="number" name="captcha_answer" class="form-control" placeholder="Jawaban..." required>
                                    <i class="fas fa-shield-alt fa-icon-left"></i>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-login w-100">
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
                eyeIcon.style.color = 'var(--accent-blue)';
            } else {
                passInput.type = 'password';
                eyeIcon.classList.replace('fa-eye', 'fa-eye-slash');
                eyeIcon.style.color = '#94a3b8';
            }
        }
    </script>

</body>
</html>