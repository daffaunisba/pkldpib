<?php
// asistensi-riwayat.php (Halaman Publik - Riwayat Detail Siswa)
include 'config/db-koneksi.php'; 

$siswa_id = isset($_GET['siswa_id']) ? (int)$_GET['siswa_id'] : 0;
$max_asistensi = 5;

if ($siswa_id === 0) {
    die("ID Siswa tidak ditemukan.");
}

// 1. Ambil Data Siswa (Nama, Kelas, Lokasi, Guru)
$siswa_query = $koneksi->query("
    SELECT 
        p.nama AS nama_siswa, p.kelas, p.nisn,
        l.nama_lokasi,
        g.nama_guru
    FROM peserta_didik p
    LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
    LEFT JOIN guru g ON l.guru_id = g.guru_id
    WHERE p.id = {$siswa_id}
");

$siswa_info = $siswa_query->fetch_assoc();

if (!$siswa_info) {
    die("Data siswa tidak ditemukan.");
}

// 2. Ambil Riwayat Asistensi (maksimal 5)
$riwayat_query = $koneksi->query("
    SELECT 
        tanggal_asistensi, catatan, status
    FROM asistensi_pkl
    WHERE siswa_id = {$siswa_id}
    ORDER BY tanggal_asistensi DESC
    LIMIT {$max_asistensi}
");

$riwayat_asistensi = [];
if ($riwayat_query) {
    while ($row = $riwayat_query->fetch_assoc()) {
        $riwayat_asistensi[] = $row;
    }
}
$asistensi_count = count($riwayat_asistensi);

// Fungsi untuk format tanggal
function formatTanggal($tanggal) {
    if (empty($tanggal)) return '-';
    setlocale(LC_TIME, 'id_ID.utf8');
    return strftime('%d %B %Y', strtotime($tanggal));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Riwayat Asistensi | <?php echo htmlspecialchars($siswa_info['nama_siswa']); ?></title>
    <link rel="stylesheet" href="css/style.css"> 
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        /* CSS diambil dari asistensi-list.php */
        body { background-color: #f0f2f5; font-family: 'Poppins', sans-serif; }
        .page-content {
            width: 85%;
            margin: 100px auto 50px auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .page-content h1 {
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-size: 2em;
        }
        .info-card {
            background-color: #f8f9fa;
            border-left: 5px solid #007bff;
            padding: 15px;
            margin-bottom: 30px;
            border-radius: 4px;
        }
        .info-card strong { color: #343a40; font-weight: 600; }
        .data-table {
            border-collapse: collapse;
            width: 100%;
        }
        .data-table th, .data-table td { 
            padding: 12px 15px; 
            vertical-align: top; 
            border: 1px solid #dee2e6;
        }
        .data-table th {
            background-color: #37475A;
            color: white;
            text-align: left;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            color: white;
            display: inline-block;
            white-space: nowrap;
            margin-top: 5px;
        }
        .status-badge.Revisi { background-color: #ffc107; color: #333; }
        .status-badge.Selesai { background-color: #28a745; }
        .status-badge.Disetujui { background-color: #17a2b8; }
        .status-badge.Ditolak { background-color: #dc3545; }
        .btn-back {
            background-color: #6c757d;
            color: white;
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
        }
        .main-navbar {
            background-color: #37475A;
            color: white;
            padding: 15px 50px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .main-navbar .logo { font-size: 1.5em; font-weight: 700; }
        .main-navbar nav a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
            padding: 5px 0;
            transition: color 0.2s;
        }
    </style>
</head>
<body>

    <header class="main-navbar">
        <div class="logo">PKL DPIB | SMK ISLAM 1 BLITAR</div>
        <nav>
            <a href="index.php">Beranda</a>
            <a href="tentang-pkl.php">Tentang PKL</a>
            <a href="cek-kuota.php">Cek Kuota</a>
            <div class="dropdown">
                <a href="asistensi-list.php">Info PKL <i class="fas fa-caret-down"></i></a>
            </div>
            <a href="daftar-sekarang.php" class="btn-daftar">Daftar Sekarang</a>
        </nav>
    </header>

    <div class="page-content">
        <a href="asistensi-list.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali ke Daftar Siswa</a>
        
        <h1>Riwayat Asistensi Laporan</h1>

        <div class="info-card">
            <p><strong>Siswa:</strong> <?php echo htmlspecialchars($siswa_info['nama_siswa']); ?> (NISN: <?php echo htmlspecialchars($siswa_info['nisn']); ?>)</p>
            <p><strong>Kelas:</strong> <?php echo htmlspecialchars($siswa_info['kelas']); ?></p>
            <p><strong>Lokasi PKL:</strong> <?php echo htmlspecialchars($siswa_info['nama_lokasi'] ?? 'N/A'); ?></p>
            <p><strong>Guru Pembimbing:</strong> <?php echo htmlspecialchars($siswa_info['nama_guru'] ?? 'Belum Ditentukan'); ?></p>
            <p><strong>Total Asistensi:</strong> <?php echo $asistensi_count; ?> dari <?php echo $max_asistensi; ?> kali</p>
        </div>

        <?php if ($asistensi_count > 0): ?>
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="width: 5%;">NO</th>
                        <th style="width: 15%;">Tanggal</th>
                        <th style="width: 65%;">Uraian/Catatan Guru</th>
                        <th style="width: 15%;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $riwayat_num = 1; foreach (array_reverse($riwayat_asistensi) as $riwayat): ?>
                    <tr>
                        <td><?php echo $riwayat_num++; ?></td>
                        <td><?php echo formatTanggal($riwayat['tanggal_asistensi']); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($riwayat['catatan'])); ?></td>
                        <td>
                            <span class="status-badge <?php echo htmlspecialchars($riwayat['status']); ?>">
                                <?php echo htmlspecialchars($riwayat['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="text-align: center; color: #6c757d;">Belum ada riwayat asistensi yang tercatat untuk siswa ini.</p>
        <?php endif; ?>
    </div>
    
    </body>
</html>