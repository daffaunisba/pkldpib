<!-- <?php
// kendali-detail-publik.php - Halaman detail kartu kendali yang diakses publik

// =================================================================
// 🚨 KONEKSI DATABASE
// =================================================================
$koneksi = null;
$error_message_koneksi = null;
$paths_to_check = ['config/db-koneksi.php', '../config/db-koneksi.php', 'db-koneksi.php'];
foreach ($paths_to_check as $path) {
    if (file_exists($path)) { include $path; break; }
}

if (!isset($koneksi) || !($koneksi instanceof mysqli) || (isset($koneksi) && $koneksi->connect_error !== null)) {
    $error_message_koneksi = "❌ Koneksi database GAGAL! File koneksi tidak ditemukan atau gagal terinisialisasi.";
    $koneksi = null;
}

$siswa_id = isset($_GET['siswa_id']) ? (int)$_GET['siswa_id'] : 0;
$detail_siswa = null;
$daftar_kegiatan = [];
$error_message = $error_message_koneksi; 
$page_title = "Detail Kartu Kendali Siswa";

if ($siswa_id > 0 && isset($koneksi) && $koneksi->connect_error === null) {
    $error_message = null; 
    
    // A. Query Detail Siswa menggunakan Prepared Statement
    $stmt_siswa = $koneksi->prepare("
        SELECT 
            p.nama AS nama_siswa, p.kelas, p.nisn, 
            l.nama_lokasi, g.nama_guru 
        FROM peserta_didik p
        LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
        LEFT JOIN guru g ON l.guru_id = g.guru_id
        WHERE p.id = ?
    ");
    $stmt_siswa->bind_param("i", $siswa_id);
    $stmt_siswa->execute();
    $result_siswa = $stmt_siswa->get_result();
    $detail_siswa = $result_siswa->fetch_assoc();
    $stmt_siswa->close();

    if (!$detail_siswa) {
        $error_message = "Data siswa ID yang diminta tidak ditemukan.";
    } else {
        $page_title = "Kartu Kendali " . htmlspecialchars($detail_siswa['nama_siswa']);

        // B. Query Daftar Kegiatan (FINAL FIX)
        // Menggunakan LEFT JOIN agar SEMUA kegiatan dari 'kendali_kegiatan_def' muncul
        // Kolom 'status' dari tabel 'kartu_kendali' (k) mungkin NULL jika belum divalidasi
        $stmt_kegiatan = $koneksi->prepare("
            SELECT 
                d.deskripsi, d.urutan,
                k.status
            FROM kendali_kegiatan_def d
            LEFT JOIN kartu_kendali k ON d.id = k.kegiatan_id AND k.siswa_id = ?
            ORDER BY d.urutan ASC
        ");
        
        $stmt_kegiatan->bind_param("i", $siswa_id);
        $stmt_kegiatan->execute();
        $result_kegiatan = $stmt_kegiatan->get_result();
        while ($row = $result_kegiatan->fetch_assoc()) {
            $daftar_kegiatan[] = $row;
        }
        $stmt_kegiatan->close();
    }
} elseif ($siswa_id === 0) {
    $error_message = "ID siswa tidak valid atau tidak dimasukkan.";
}
?>
<!doctype html>
<html lang="id">  
<head>    
    <meta charset="utf-8">    
    <meta name="viewport" content="initial-scale=1.0, width=device-width">    
    <title><?php echo $page_title; ?> | Si Mantap</title>    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>  
    
    <style>
        /* CSS FINAL UNTUK TAMPILAN YANG LEBIH BAIK */
        :root {
            --simantap-blue-dark: #224ebe; 
            --simantap-blue-light: #4e73df; 
            --simantap-accent-green: #1cc88a; 
            --simantap-red: #e74a3b; /* Warna Merah untuk Belum Divalidasi */
        }
        body { padding-top: 70px; background-color: #f4f7f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; }
        .bg-biru { background-color: var(--simantap-blue-dark) !important; }
        .fixed-top { z-index: 1051 !important; }

        .page-content-wrapper { min-height: calc(100vh - 70px); padding: 30px 0; }
        
        /* HEADER UTAMA HALAMAN */
        .info-header { text-align: center; margin-bottom: 40px; }
        .info-header h1 { 
            color: var(--simantap-blue-dark); 
            font-size: 2.5rem; 
            font-weight: 700; 
        }
        
        /* CARD UTAMA */
        .rekap-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            max-width: 1000px;
            margin: 0 auto;
        }

        /* --- STYLING DETAIL SISWA --- */
        .info-siswa-card {
            background-color: #f8f9fa !important; 
            border: 1px solid #dee2e6;
            border-left: 5px solid var(--simantap-blue-light);
            padding: 15px !important; 
            border-radius: 6px;
            margin-bottom: 25px;
        }
        .info-siswa-card strong { 
            min-width: 150px; 
            display: inline-block; 
            font-weight: 600;
            color: #5a5c69;
        }
        .info-siswa-card p {
            margin-bottom: 8px;
        }


        /* --- STYLING TABEL KEGIATAN --- */
        .table th {
            background-color: var(--simantap-blue-dark);
            color: white;
            text-align: center;
            vertical-align: middle;
            font-size: 0.9em;
        }
        .table-rekap td {
            vertical-align: top; 
            font-size: 0.9em;
            padding: 12px;
        }
        .table-bordered th, .table-bordered td { 
            border: 1px solid #dee2e6; 
        }
        /* Status Divalidasi */
        .status-valid {
            font-weight: 600;
            color: var(--simantap-accent-green);
            display: block; 
            text-align: center;
            padding: 5px 0;
        }
        /* Status Belum Divalidasi */
        .status-invalid {
            font-weight: 600;
            color: var(--simantap-red); /* Teks Merah */
            display: block; 
            text-align: center;
            padding: 5px 0;
        }
        .fa-check-circle {
            font-size: 1.1em;
        }
        
        /* Responsive Table */
        @media (max-width: 767px) {
            .info-header h1 { font-size: 2rem; }
            .table-responsive { overflow-x: auto; }
            .table-rekap th, .table-rekap td {
                white-space: normal;
            }
            .info-siswa-card strong { min-width: auto; display: block; }
        }
    </style>
</head> 
<body>   
    
    <?php 
    // PANGGIL HEADER PUBLIK
    include 'panel/header-publik.php'; 
    ?>
    
    <div class="container page-content-wrapper">
        <div class="info-header">
            <h1 class="mb-3"></i> Logbook PKL Siswa</h1>
            <a href="rekap-kendali.php" class="btn btn-sm btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Kembali ke Rekap Global</a>
        </div>

        <div class="rekap-card">
            <?php if ($error_message): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> KESALAHAN! <?php echo $error_message; ?>
                </div>
            <?php elseif ($detail_siswa): ?>
                
                <h4 class="mb-3 text-primary"><?php echo htmlspecialchars($detail_siswa['nama_siswa']); ?> (<?php echo htmlspecialchars($detail_siswa['kelas']); ?>)</h4>
                
                <div class="row info-siswa-card">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>NISN:</strong> <?php echo htmlspecialchars($detail_siswa['nisn']); ?></p>
                        <p class="mb-1"><strong>Lokasi PKL:</strong> <?php echo htmlspecialchars($detail_siswa['nama_lokasi'] ?? 'Belum Ditetapkan'); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Guru Pembimbing:</strong> <?php echo htmlspecialchars($detail_siswa['nama_guru'] ?? 'N/A'); ?></p>
                        <p class="mb-1"><strong>Total Kegiatan Divalidasi:</strong> <?php echo count(array_filter($daftar_kegiatan, function($k) { return $k['status'] == 1; })); ?> Kegiatan</p>
                    </div>
                </div>

                <h5 class="mt-4 mb-3"></i> Daftar Kegiatan</h5>
                
                <?php if (!empty($daftar_kegiatan)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-rekap">
                        <thead>
                            <tr>
                                <th style="width: 10%;">Urutan</th>
                                <th style="width: 70%;">Deskripsi Kegiatan / Tahapan</th>
                                <th style="width: 20%;">Validasi Guru</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($daftar_kegiatan as $kegiatan): 
                                $is_validated = (isset($kegiatan['status']) && $kegiatan['status'] == 1);
                                $status_class = $is_validated ? 'status-valid' : 'status-invalid';
                                $status_text = $is_validated ? '<i class="fas fa-check-circle me-1"></i> Divalidasi' : 'Belum Divalidasi';
                            ?>
                            <tr>
                                <td style="text-align: center;"><?php echo htmlspecialchars($kegiatan['urutan'] ?? 'N/A'); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($kegiatan['deskripsi'] ?? 'N/A')); ?></td>
                                <td>
                                    <strong class="<?php echo $status_class; ?>"><?php echo $status_text; ?></strong>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="alert alert-info">Daftar kegiatan belum diinisialisasi dalam sistem.</div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
        
    </div>
    
    <?php 
    // PANGGIL FOOTER PUBLIK (Menggunakan @ untuk mencegah error include menghentikan script)
    @include 'panel/public_footer.php'; 
    ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"></script> 
    <script type="text/javascript">
    // FUNGSI REALTIME CLOCK DILENGKAPI
    function updateClock() {
        const now = new Date();
        const localTime = new Date(now.toLocaleString("en-US", {timeZone: "Asia/Jakarta"}));
        const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
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
        updateClock(); 
        setInterval(updateClock, 1000); 
    });
    </script>
</body>
</html> -->