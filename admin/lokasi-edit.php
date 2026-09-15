<?php
// admin/lokasi_edit.php
include '../config/db-koneksi.php';

// PENTING: Memastikan proteksi login dasar terlebih dahulu
include 'auth-check.php'; 

// =========================================================================
// PROTEKSI HAK AKSES MUTLAK: Hanya level Admin yang boleh memproses halaman ini
// =========================================================================
$current_user_level = isset($_SESSION['level']) ? strtolower($_SESSION['level']) : 'pembimbing';

if ($current_user_level !== 'admin') {
    // Terpental kembali ke halaman dashboard jika bukan Admin
    header("Location: dashboard.php");
    exit(); // Menghentikan eksekusi script selanjutnya
}
// =========================================================================

$current_user = isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin';
$message = '';
$lokasi_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$lokasi_data = null;

// --- LOGIKA UPDATE LOKASI ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $lokasi_id = (int)$_POST['lokasi_id'];
    $nama_lokasi = $_POST['nama_lokasi'];
    $kuota_max = (int)$_POST['kuota_max'];
    $jam_kerja = $_POST['jam_kerja'];
    $alamat = $_POST['alamat'];
    
    $guru_id_input = $_POST['guru_id'];
    // Jika 'NULL' dikirim dari dropdown default, set ke PHP NULL.
    $guru_id = ($guru_id_input === 'NULL') ? NULL : (int)$guru_id_input;


    $update_stmt = $koneksi->prepare("UPDATE lokasi_pkl SET nama_lokasi = ?, kuota_max = ?, jam_kerja = ?, alamat = ?, guru_id = ? WHERE lokasi_id = ?");
    $update_stmt->bind_param("sissii", $nama_lokasi, $kuota_max, $jam_kerja, $alamat, $guru_id, $lokasi_id); 

    if ($update_stmt->execute()) {
        $message = "<div class='alert success'><i class='fas fa-check-circle me-2'></i> Data lokasi dan Pembimbing berhasil diperbarui!</div>";
    } else {
        $message = "<div class='alert error'><i class='fas fa-exclamation-circle me-2'></i> Gagal memperbarui data: " . $update_stmt->error . "</div>";
    }
    $update_stmt->close();
    
    // Ini agar tampilan di bawah me-refresh data terbaru
    $_GET['id'] = $lokasi_id; 
}

// Query untuk mengambil daftar guru (tetap di sini untuk dropdown update)
$guru_list_result = $koneksi->query("SELECT guru_id, nama_guru FROM guru ORDER BY nama_guru ASC");
$guru_list = [];
if ($guru_list_result) {
    while ($guru = $guru_list_result->fetch_assoc()) {
        $guru_list[] = $guru;
    }
}


// Ambil data lokasi yang akan diedit (termasuk guru_id saat ini)
$lokasi_stmt = $koneksi->prepare("SELECT * FROM lokasi_pkl WHERE lokasi_id = ?");
$lokasi_stmt->bind_param("i", $lokasi_id);
$lokasi_stmt->execute();
$result = $lokasi_stmt->get_result();

if ($result->num_rows > 0) {
    $lokasi_data = $result->fetch_assoc();
} else {
    $message = "<div class='alert error'><i class='fas fa-exclamation-circle me-2'></i> Lokasi tidak ditemukan.</div>";
}
$lokasi_stmt->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin - Edit Kuota Lokasi | Si Mantap PKL</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --mantap-blue-dark: #0f172a;
            --mantap-blue-main: #1e40af;
            --mantap-blue-light: #3b82f6;
            --mantap-blue-soft: #eff6ff;
        }

        body { 
            font-family: 'Poppins', sans-serif; 
            background-color: #f4f6fa; 
            color: #334155; 
            margin: 0;
            overflow-x: hidden !important;
        }

        /* Pembungkus Utama */
        .main-content-wrapper, .admin-main-content, .container {
            max-width: 100% !important;
            box-sizing: border-box !important;
            box-shadow: none !important;
            transition: none !important;
        }

        .container { 
            max-width: 800px !important; 
            padding: 25px 30px;
            margin: 0 auto;
            width: 100%;
        }

        .page-title {
            font-weight: 700; 
            color: var(--mantap-blue-dark); 
            font-size: 1.9rem; 
            margin: 0 0 30px 0; 
            position: relative;
        }
        
        .page-title::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -5px;
            width: 50px;
            height: 4px;
            background: var(--mantap-blue-main);
            border-radius: 2px;
        }

        .glass-panel { 
            background: white; 
            padding: 30px; 
            border-radius: 16px; 
            border: 1px solid #cbd5e1;
            box-sizing: border-box;
            box-shadow: none !important;
        }

        /* Notifikasi Modern */
        .alert { 
            padding: 14px 20px; 
            margin-bottom: 25px; 
            border-radius: 10px; 
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
        }
        .alert.success { color: #16a34a; background-color: #f0fdf4; border: 1px solid #bbf7d0; }
        .alert.error { color: #dc2626; background-color: #fef2f2; border: 1px solid #fecaca; }

        /* Form Styling */
        .form-group {
            margin-bottom: 20px;
        }

        .glass-panel label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 600; 
            color: var(--mantap-blue-dark);
            font-size: 14px;
        }

        .glass-panel input[type="text"],
        .glass-panel input[type="number"],
        .glass-panel select { 
            width: 100%; 
            padding: 12px 16px; 
            border: 1px solid #cbd5e1; 
            border-radius: 10px; 
            box-sizing: border-box; 
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            color: #334155;
            background-color: #f8fafc;
        }

        .glass-panel input:focus,
        .glass-panel select:focus {
            outline: none;
            border-color: var(--mantap-blue-light);
            background-color: #ffffff;
        }

        /* Tombol Simpan Mantap */
        .btn-submit-mantap {
            background-color: var(--mantap-blue-main);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: none !important;
        }

        /* Link Tambah Guru */
        .helper-text {
            margin-top: 15px; 
            font-size: 13px;
            color: #64748b;
        }
        .helper-text a {
            color: var(--mantap-blue-light);
            text-decoration: none;
            font-weight: 600;
        }
        .helper-text a:hover {
            text-decoration: underline;
            color: var(--mantap-blue-main);
        }

        /* Dropdown & Sidebar Menu CSS */
        .dropdown-menu { list-style: none; padding: 0; margin-top: 0; display: none; background-color: #2c3e50; }
        .dropdown-menu.active { display: block; }
        .dropdown-menu li a { padding: 10px 20px 10px 50px; display: block; color: #bdc3c7; font-size: 14px; border-left: 3px solid transparent; transition: all 0.3s; text-decoration: none; }
        .dropdown-menu li a:hover { background-color: #34495e; border-left-color: #3498db; color: white; }
        .dropdown-toggle { cursor: pointer; display: flex; justify-content: space-between; align-items: center; padding: 12px 20px; color: #bdc3c7; font-weight: 600; text-decoration: none; }
        .dropdown-toggle.active i.fa-caret-down { transform: rotate(180deg); }
        .dropdown-toggle i.fa-caret-down { transition: transform 0.3s; }
        .sidebar-nav .monitoring-menu a { background-color: var(--mantap-blue-main) !important; color: white !important; font-weight: 600; margin-top: 5px; }

        /* === ATURAN MOBILE RESPONSIVE UNTUK HP (MAKSIMAL 768px) === */
        @media (max-width: 768px) { 
            body { 
                padding-top: 60px !important; 
            }
            .container {
                padding: 20px 15px !important;
            }
            .page-title {
                font-size: 1.6rem !important; /* Font judul di HP diperbesar & dipertegas */
                margin-bottom: 20px !important;
            }
            .glass-panel {
                padding: 15px 5px !important; /* Ramping tanpa margin berlebih */
                border: none !important; /* HAPUS KOTAK SHADOW/BORDER FORM DI HP */
                background: transparent !important; /* Menyatu bersih dengan latar belakang */
            }
            .glass-panel label {
                font-size: 15px !important; /* Font label input diperbesar agar terbaca jelas */
                margin-bottom: 10px !important;
            }
            .glass-panel input[type="text"],
            .glass-panel input[type="number"],
            .glass-panel select { 
                font-size: 15px !important; /* Font teks di dalam field input diperbesar */
                padding: 14px 16px !important; /* Bidang ketik diperlebar agar ramah layar sentuh HP */
                border-radius: 8px !important;
                border: 1px solid #cbd5e1 !important;
            }
            .btn-submit-mantap {
                width: 100% !important; /* Tombol aksi memenuhi lebar layar di HP */
                justify-content: center !important;
                font-size: 15px !important; /* Font teks tombol diperbesar */
                padding: 14px 20px !important;
                border-radius: 10px !important;
            }
            .helper-text {
                font-size: 14px !important; /* Teks bantuan diperbesar sedikit */
                line-height: 1.5;
            }
            .alert {
                font-size: 15px !important;
                padding: 14px 16px !important;
            }
        }
    </style>
</head>
<body class="admin-body">

<?php 
// 1. INCLUDE SIDEBAR DARI FOLDER PANEL
include 'panel/sidebar.php'; 
?>

<?php 
// 2. INCLUDE NAVBAR DARI FOLDER PANEL
include 'panel/navbar.php'; 
?>

<div class="container">
    <h1 class="page-title">Edit Lokasi PKL & Pembimbing</h1>
    
    <?php echo $message; ?>

    <?php if ($lokasi_data): ?>
    <div class="glass-panel">
        <form method="POST" action="">
            <input type="hidden" name="lokasi_id" value="<?php echo $lokasi_data['lokasi_id']; ?>">

            <div class="form-group">
                <label for="nama_lokasi">Nama Lokasi</label>
                <input type="text" id="nama_lokasi" name="nama_lokasi" value="<?php echo htmlspecialchars($lokasi_data['nama_lokasi']); ?>" required placeholder="Masukkan nama instansi / perusahaan">
            </div>

            <div class="form-group">
                <label for="kuota_max">Kuota Maksimum (Jumlah Siswa)</label>
                <input type="number" id="kuota_max" name="kuota_max" value="<?php echo $lokasi_data['kuota_max']; ?>" required min="1">
            </div>
            
            <div class="form-group">
                <label for="jam_kerja">Jam Kerja</label>
                <input type="text" id="jam_kerja" name="jam_kerja" value="<?php echo htmlspecialchars($lokasi_data['jam_kerja']); ?>" required placeholder="Contoh: 08:00 - 16:00">
            </div>

            <div class="form-group">
                <label for="alamat">Alamat Lengkap</label>
                <input type="text" id="alamat" name="alamat" value="<?php echo htmlspecialchars($lokasi_data['alamat']); ?>" required placeholder="Masukkan alamat lokasi PKL">
            </div>
            
            <div class="form-group">
                <label for="guru_id">Guru Pembimbing</label>
                <select id="guru_id" name="guru_id">
                    <option value="NULL">-- Pilih Guru Pembimbing --</option>
                    <?php foreach ($guru_list as $guru): ?>
                        <option value="<?php echo $guru['guru_id']; ?>" 
                            <?php echo ($lokasi_data['guru_id'] == $guru['guru_id']) ? 'selected' : ''; ?>>
                            <?php echo $guru['nama_guru']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="helper-text">Belum ada nama guru di daftar? <a href="guru-add-form.php"><i class="fas fa-plus-circle fa-sm"></i> Tambah Guru Pembimbing Baru</a>.</p>
            </div>

            <div style="margin-top: 30px;">
                <button type="submit" class="btn-submit-mantap">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php 
// 3. INCLUDE FOOTER DARI FOLDER PANEL
include 'panel/footer.php'; 
?>
</body>
</html>