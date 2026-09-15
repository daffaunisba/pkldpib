<?php
session_start();
include '../config/db-koneksi.php';

date_default_timezone_set('Asia/Jakarta');

// FUNGSI FIX: Menghitung selisih waktu dengan menyertakan tanggal hari ini
function hitungSelisihDetail($jam_sekarang, $jam_target) {
    $tgl_hari_ini = date('Y-m-d');
    
    // Gabungkan tanggal agar strtotime akurat
    $awal  = strtotime($tgl_hari_ini . ' ' . $jam_target);
    $akhir = strtotime($tgl_hari_ini . ' ' . $jam_sekarang);
    
    $diff  = abs($akhir - $awal);

    $jam   = floor($diff / 3600);
    $menit = floor(($diff % 3600) / 60);
    $detik = $diff % 60;

    return "$jam Jam, $menit Menit"; // Disingkat agar lebih rapi di pop-up
}

$show_modal = false;
$res_title = "";
$res_status = "";
$pesan_popup = ""; 
$res_time = date('H:i');
$is_error = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $siswa_id = $_SESSION['siswa_id'] ?? null;
    $lat_siswa = $_POST['lat'] ?? 0;
    $lng_siswa = $_POST['lng'] ?? 0;
    $img_data = $_POST['image_data'] ?? '';

    // 1. Ambil Data Lokasi & Jam Kerja
    $stmt_lokasi = $koneksi->prepare("
        SELECT l.latitude, l.longitude, l.jam_kerja 
        FROM peserta_didik p 
        JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id 
        WHERE p.id = ?
    ");
    $stmt_lokasi->bind_param("i", $siswa_id);
    $stmt_lokasi->execute();
    $target = $stmt_lokasi->get_result()->fetch_assoc();
    $stmt_lokasi->close();
    
    $jam_sekarang = date('H:i:s');
    $tgl = date('Y-m-d');

    // 2. Cek Absen Hari Ini
    $stmt_cek = $koneksi->prepare("SELECT * FROM presensi_pkl WHERE siswa_id = ? AND tanggal = ?");
    $stmt_cek->bind_param("is", $siswa_id, $tgl);
    $stmt_cek->execute();
    $data_hari_ini = $stmt_cek->get_result()->fetch_assoc();
    $stmt_cek->close();

    if (!$data_hari_ini) {
        // =======================================================
        // KONDISI 1: ABSEN DATANG
        // =======================================================
        $jam_target_masuk = $target['jam_kerja'] ?? "08:00:00"; 
        $selisih_teks = hitungSelisihDetail($jam_sekarang, $jam_target_masuk);

        if (strtotime($tgl . ' ' . $jam_sekarang) > strtotime($tgl . ' ' . $jam_target_masuk)) {
            $res_status = "Terlambat ($selisih_teks)";
        } else {
            $res_status = "Tepat Waktu";
        }

        // Simpan Foto
        $img_data = str_replace(['data:image/jpeg;base64,', ' '], ['', '+'], $img_data);
        $decoded_img = base64_decode($img_data);
        $filename = 'datang_' . $siswa_id . '_' . time() . '.jpg';
        file_put_contents(__DIR__ . '/../uploads/absensi/' . $filename, $decoded_img);

        $stmt = $koneksi->prepare("INSERT INTO presensi_pkl (siswa_id, tanggal, jam_masuk, lat_absen, lng_absen, status, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $siswa_id, $tgl, $jam_sekarang, $lat_siswa, $lng_siswa, $res_status, $filename);
        
        if ($stmt->execute()) { 
            $show_modal = true; 
            $res_title = "Presensi Datang Berhasil!";
            $pesan_popup = "Semangat belajar dan jadilah versi terbaikmu hari ini di tempat PKL! 🚀";
        } else {
            $is_error = true;
        }
        $stmt->close();

    } else {
        // =======================================================
        // KONDISI 2: ABSEN PULANG
        // =======================================================
        $jam_target_pulang = "16:00:00"; 
        $selisih_teks = hitungSelisihDetail($jam_sekarang, $jam_target_pulang);

        if (strtotime($tgl . ' ' . $jam_sekarang) < strtotime($tgl . ' ' . $jam_target_pulang)) {
            $res_status = "Pulang Cepat ($selisih_teks)";
        } else {
            $res_status = "Pulang Normal";
        }

        $img_data = str_replace(['data:image/jpeg;base64,', ' '], ['', '+'], $img_data);
        $decoded_img = base64_decode($img_data);
        $filename_pulang = 'pulang_' . $siswa_id . '_' . time() . '.jpg';
        file_put_contents(__DIR__ . '/../uploads/absensi/' . $filename_pulang, $decoded_img);

        $stmt = $koneksi->prepare("UPDATE presensi_pkl SET jam_pulang = ?, keterangan2 = ? WHERE siswa_id = ? AND tanggal = ?");
        $stmt->bind_param("ssis", $jam_sekarang, $filename_pulang, $siswa_id, $tgl);
        
        if ($stmt->execute()) { 
            $show_modal = true; 
            $res_title = "Presensi Pulang Berhasil!"; 
            $pesan_popup = "Kerja bagus hari ini! Hati-hati di jalan pulang dan selamat beristirahat. 🏡";
        } else {
            $is_error = true;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proses Presensi - Si Mantap</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Poppins', sans-serif; 
            margin: 0; 
            background: #f1f5f9; 
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.6); 
            backdrop-filter: blur(10px);
            display: flex; align-items: center; justify-content: center; 
            z-index: 9999;
            padding: 20px;
            box-sizing: border-box;
        }

        .modal-card {
            background: white; 
            width: 100%; 
            max-width: 380px; 
            border-radius: 28px; 
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: slideUpBounce 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes slideUpBounce {
            0% { transform: translateY(50px) scale(0.9); opacity: 0; }
            100% { transform: translateY(0) scale(1); opacity: 1; }
        }

        /* Tampilan Header Kartu */
        .modal-header {
            height: 130px;
            position: relative;
        }
        .header-success { background: linear-gradient(135deg, #764ba2 0%, #764ba2 100%); }
        .header-error { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }

        /* Ikon Melayang */
        .icon-wrapper {
            width: 80px; height: 80px;
            background: white;
            border-radius: 50%;
            position: absolute;
            bottom: -40px;
            left: 50%;
            transform: translateX(-50%);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            z-index: 10;
        }
        
        .icon-wrapper i { font-size: 35px; }
        .icon-success i { color: #10b981; animation: popIn 0.5s 0.3s backwards cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .icon-error i { color: #ef4444; animation: shake 0.5s 0.3s backwards; }

        @keyframes popIn {
            0% { transform: scale(0); }
            100% { transform: scale(1); }
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        /* Tampilan Body Kartu */
        .modal-body {
            padding: 60px 30px 30px;
            background: white;
        }

        .modal-body h2 { 
            margin: 0; color: #1e293b; font-size: 20px; font-weight: 800; 
        }
        
        .modal-body p { 
            color: #64748b; margin: 12px 0 25px; font-size: 13px; line-height: 1.6; font-weight: 500; 
        }

        /* Kotak Detail Waktu & Status */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 30px;
        }

        .info-box {
            background: #f8fafc;
            padding: 15px 10px;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
        }

        .info-box span { 
            display: block; color: #94a3b8; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px; 
        }
        
        .info-box strong { 
            color: #1e293b; font-size: 14px; font-weight: 700; 
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 700;
            background: #e0e7ff;
            color: #4f46e5;
            margin-top: 5px;
        }

        /* Tombol Animasi */
        .btn-action {
            display: block;
            width: 100%; 
            padding: 16px; 
            border: none; 
            border-radius: 18px;
            font-family: 'Poppins', sans-serif;
            color: white; 
            font-weight: 700; 
            font-size: 14px; 
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-success {
            background: #1e293b;
            box-shadow: 0 10px 20px rgba(30, 41, 59, 0.2);
        }
        
        .btn-success:hover { 
            background: #0f172a;
            transform: translateY(-3px); 
            box-shadow: 0 15px 25px rgba(30, 41, 59, 0.3); 
        }

        .btn-error {
            background: #ef4444;
            box-shadow: 0 10px 20px rgba(239, 68, 68, 0.2);
        }

        .btn-error:hover {
            background: #dc2626;
            transform: translateY(-3px); 
            box-shadow: 0 15px 25px rgba(239, 68, 68, 0.3); 
        }
    </style>
</head>
<body>

<?php if ($show_modal): ?>
<div class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header header-success">
            <div class="icon-wrapper icon-success">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
        <div class="modal-body">
            <h2><?= $res_title ?></h2>
            <p><?= $pesan_popup ?></p>
            
            <div class="info-grid">
                <div class="info-box">
                    <span>Waktu Absen</span>
                    <strong><?= $res_time ?> WIB</strong>
                </div>
                <div class="info-box">
                    <span>Kondisi</span>
                    <strong><?= explode(' (', $res_status)[0] ?></strong>
                </div>
            </div>

            <button onclick="window.location.href='index.php'" class="btn-action btn-success">
                Kembali ke Beranda
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($is_error): ?>
<div class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header header-error">
            <div class="icon-wrapper icon-error">
                <i class="fas fa-times-circle"></i>
            </div>
        </div>
        <div class="modal-body">
            <h2>Sistem Sibuk</h2>
            <p>Terjadi kesalahan saat menyimpan data absensi Anda. Pastikan koneksi stabil dan silakan coba beberapa saat lagi.</p>
            
            <button onclick="window.location.href='absen-wajah.php'" class="btn-action btn-error">
                <i class="fas fa-redo me-2"></i> Coba Lagi
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

</body>
</html>