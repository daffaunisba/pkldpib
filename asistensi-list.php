<?php
// asistensi-list.php (Halaman Publik - Daftar Siswa)
include 'config/db-koneksi.php'; // Asumsi koneksi ada di folder config

$max_asistensi = 5; // Batas maksimum asistensi

// Query untuk mengambil daftar siswa, lokasi PKL, guru pembimbing, dan jumlah asistensi
$query_peserta = "
    SELECT 
        p.id, p.nama AS nama_siswa, p.kelas,
        l.nama_lokasi,
        g.nama_guru,
        -- Subquery untuk menghitung jumlah asistensi
        (
            SELECT COUNT(asistensi_id) 
            FROM asistensi_pkl 
            WHERE siswa_id = p.id
        ) AS asistensi_count
        
    FROM peserta_didik p
    LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
    LEFT JOIN guru g ON l.guru_id = g.guru_id
    ORDER BY p.nama ASC
";

$peserta_data = $koneksi->query($query_peserta);

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Asistensi PKL | SMK ISLAM 1 BLITAR</title>
    <link rel="stylesheet" href="css/style.css"> 
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        /* CSS Tambahan untuk Halaman Umum */
        body { background-color: #f0f2f5; font-family: 'Poppins', sans-serif; }
        .page-content {
            width: 85%;
            /* Margin atas disesuaikan agar konten tidak tertutup navbar */
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
        .data-table {
            border-collapse: collapse;
            width: 100%;
        }
        .data-table th, .data-table td { 
            padding: 12px 15px; 
            vertical-align: middle; 
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
        }
        .status-badge.Lengkap { background-color: #28a745; }
        .status-badge.Belum { background-color: #ffc107; color: #333; }
        .link-siswa {
            color: #007bff;
            font-weight: 600;
            text-decoration: none;
        }
        .link-siswa:hover {
            text-decoration: underline;
        }
        
        /* --- CSS Navbar Index (Disederhanakan) --- */
        .navbar {
            background-color: #37475A;
            padding: 15px 50px;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-sizing: border-box;
        }
        .nav-brand a {
            color: white;
            text-decoration: none;
            font-size: 1.5em; 
            font-weight: 700;
        }
        .main-nav {
            display: flex;
            align-items: center;
        }
        .main-nav a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
            padding: 8px 10px; 
            transition: color 0.2s;
            font-weight: 600;
        }
        .main-nav a:hover {
            color: #007bff;
        }
        /* Hapus style dropdown karena menu diangkat ke level utama */
        .dropdown { display: none; } 
        /* --- AKHIR CSS Navbar Index (Disederhanakan) --- */

    </style>
</head>
<body>

    <header class="navbar">
        <div class="nav-brand">
            <a href="index.php">PKL DPIB | SMK ISLAM 1 BLITAR</a>
        </div>
        <nav class="main-nav">
            <a href="index.php">Beranda</a>
            <a href="asistensi-list.php">Asistensi</a>
            <a href="izin.php">Perizinan</a>
            <a href="#">Format Laporan</a>
            <a href="#">Rekab Pengajuan PKL</a>
            
            </nav>
    </header>

    <div class="page-content">
        <h1>Daftar Siswa dan Status Asistensi Laporan PKL</h1>

        <?php if ($peserta_data && $peserta_data->num_rows > 0): ?>
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="width: 5%;">NO</th> 
                        <th style="width: 30%;">Nama Siswa (Kelas)</th> 
                        <th style="width: 25%;">Lokasi PKL</th>      
                        <th style="width: 25%;">Guru Pembimbing</th>
                        <th style="width: 15%;">Status Asistensi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $row_num = 1; 
                    while ($row = $peserta_data->fetch_assoc()):
                        $guru_nama = $row['nama_guru'] ?? 'Belum Ditentukan';
                        
                        // Tentukan Status Kelengkapan
                        $asistensi_count = (int)$row['asistensi_count'];
                        $is_complete = $asistensi_count >= $max_asistensi;
                        $status_text = $is_complete ? 'Lengkap' : 'Belum';
                        $status_class = $is_complete ? 'Lengkap' : 'Belum';
                    ?>
                    <tr>
                        <td><?php echo $row_num++; ?></td>
                        <td>
                            <a href="asistensi-riwayat.php?siswa_id=<?php echo $row['id']; ?>" class="link-siswa">
                                <?php echo htmlspecialchars($row['nama_siswa']); ?> (<?php echo htmlspecialchars($row['kelas']); ?>)
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars($row['nama_lokasi'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($guru_nama); ?></td>
                        <td>
                            <span class="status-badge <?php echo $status_class; ?>">
                                <?php echo $status_text; ?> (<?php echo $asistensi_count; ?>/<?php echo $max_asistensi; ?>)
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="text-align: center; color: #6c757d;">Belum ada peserta didik terdaftar atau data tidak ditemukan.</p>
        <?php endif; ?>
    </div>

    </body>
</html>