<?php
// admin/sertifikat-detail.php
// Halaman Detail Siswa dan Preview Data untuk Pembuatan Sertifikat

include 'auth-check.php';
include '../config/db-koneksi.php';

$siswa_id = isset($_GET['siswa_id']) ? (int)$_GET['siswa_id'] : 0;

// ---------------------------------------------------------------------
// [PERBAIKAN KRITIS]: Cek ID dan Tampilkan Error Jika 0
// ---------------------------------------------------------------------
if ($siswa_id === 0) {
    die("<div class='alert error'>ID Siswa tidak ditemukan atau tidak valid.</div>");
}

// ---------------------------------------------------------------------
// QUERY DATA LENGKAP SISWA (SAFER JOIN)
// ---------------------------------------------------------------------
// Menggunakan nama kolom yang paling umum: l.alamat dan pr.nama_periode
$query = "
    SELECT 
        p.id, p.nama, p.nisn, p.kelas, p.alamat_siswa,
        l.nama_lokasi, l.alamat AS alamat_lokasi, -- Asumsi kolomnya 'alamat' di lokasi_pkl
        pr.nama_periode AS nama_gelombang, pr.tgl_mulai AS periode_mulai, pr.tgl_akhir AS periode_selesai,
        s.nomor_sertifikat
    FROM peserta_didik p
    LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
    LEFT JOIN periode_pkl pr ON p.periode_id = pr.periode_id
    LEFT JOIN sertifikat_terbit s ON p.id = s.siswa_id
    WHERE p.id = ?
";

$stmt = $koneksi->prepare($query);

if ($stmt === FALSE) {
    // Memberi pesan error yang lebih jelas jika prepare gagal
    die("<div class='alert error'>FATAL SQL ERROR: Gagal menyiapkan query. Pastikan tabel `lokasi_pkl` memiliki kolom `alamat` dan `periode_pkl` memiliki kolom `nama_periode`, `tgl_mulai`, `tgl_akhir`.</div>");
}

$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$result = $stmt->get_result();
$siswa_data = $result->fetch_assoc();
$stmt->close();

if (!$siswa_data) {
    die("<div class='alert error'>Data siswa tidak ditemukan di database.</div>");
}

// Format Tanggal
function formatPeriode($mulai, $selesai) {
    setlocale(LC_TIME, 'id_ID.utf8');
    $tgl_mulai = strftime('%d %B %Y', strtotime($mulai));
    $tgl_selesai = strftime('%d %B %Y', strtotime($selesai));
    return "{$tgl_mulai} s/d {$tgl_selesai}";
}

$periode_pkl = isset($siswa_data['periode_mulai']) && isset($siswa_data['periode_selesai']) 
    ? formatPeriode($siswa_data['periode_mulai'], $siswa_data['periode_selesai']) 
    : 'N/A';

$is_sertifikat_terbit = !empty($siswa_data['nomor_sertifikat']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Sertifikat Siswa - <?php echo htmlspecialchars($siswa_data['nama']); ?></title>
    <link rel="stylesheet" href="../css/style.css"> 
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        .admin-main-content .container { max-width: 900px; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; border: 1px solid;}
        .alert.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6fb;}
        
        .detail-box { 
            background: white; 
            padding: 30px; 
            border-radius: 12px; 
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08); 
            margin-bottom: 30px;
        }
        .detail-item { 
            display: flex; 
            margin-bottom: 15px; 
            padding: 10px 0;
            border-bottom: 1px dashed #e9ecef;
        }
        .detail-item:last-child { border-bottom: none; margin-bottom: 0; }
        .detail-label { 
            font-weight: 600; 
            width: 35%; 
            color: #495057;
        }
        .detail-value { 
            width: 65%; 
            color: #212529; 
            font-weight: 500;
        }
        h1 { border-bottom: 3px solid #ff8c00; padding-bottom: 10px; margin-bottom: 30px; }

        /* Status Sertifikat */
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 0.9em;
        }
        .status-terbit { background-color: #d4edda; color: #155724; }
        .status-belum { background-color: #f8d7da; color: #721c24; }

        /* Tombol Aksi */
        .action-buttons { margin-top: 30px; display: flex; gap: 15px; }
        .btn-print, .btn-input { 
            padding: 12px 25px; 
            border: none; 
            border-radius: 6px; 
            font-size: 16px; 
            font-weight: 700; 
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        .btn-print { 
            background-color: #007bff; 
            color: white; 
        }
        .btn-print:hover { background-color: #0056b3; }
        .btn-input {
            background-color: #ffc107;
            color: #212529;
        }
        .btn-input:hover { background-color: #e0a800; }
    </style>
</head>
<body class="admin-body">

<?php 
include 'panel/sidebar.php'; 
include 'panel/navbar.php'; 
?>
            <div class="container">
                <a href="sertifikat.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali ke Menu Sertifikat</a>
                
                <h1><i class="fas fa-user-check"></i> Detail Data Siswa: **<?php echo htmlspecialchars($siswa_data['nama']); ?>**</h1>

                <div class="detail-box">
                    <h3 style="margin-bottom: 20px; color: #ff8c00;"><i class="fas fa-file-invoice"></i> Data Sertifikat</h3>

                    <div class="detail-item">
                        <div class="detail-label">Status Sertifikat</div>
                        <div class="detail-value">
                            <?php if ($is_sertifikat_terbit): ?>
                                <span class="status-badge status-terbit">Sudah Terbit</span>
                            <?php else: ?>
                                <span class="status-badge status-belum">Belum Terbit</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">No. Urut (ID Database)</div>
                        <div class="detail-value"><?php echo htmlspecialchars($siswa_data['id']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Nomor Sertifikat</div>
                        <div class="detail-value">
                            **<?php echo htmlspecialchars($siswa_data['nomor_sertifikat'] ?? '-'); ?>**
                        </div>
                    </div>

                    <h3 style="margin-top: 40px; margin-bottom: 20px; color: #ff8c00;"><i class="fas fa-school"></i> Data Diri & Akademik</h3>

                    <div class="detail-item">
                        <div class="detail-label">Nama Lengkap</div>
                        <div class="detail-value"><?php echo htmlspecialchars($siswa_data['nama']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">NISN</div>
                        <div class="detail-value"><?php echo htmlspecialchars($siswa_data['nisn']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Kelas</div>
                        <div class="detail-value"><?php echo htmlspecialchars($siswa_data['kelas']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Kompetensi Keahlian</div>
                        <div class="detail-value">Desain Pemodelan dan Informasi Bangunan (Contoh Statis)</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Asal Sekolah</div>
                        <div class="detail-value">SMK Islam 1 Blitar (Contoh Statis)</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Alamat Siswa</div>
                        <div class="detail-value"><?php echo htmlspecialchars($siswa_data['alamat_siswa'] ?? 'N/A'); ?></div>
                    </div>

                    <h3 style="margin-top: 40px; margin-bottom: 20px; color: #ff8c00;"><i class="fas fa-building"></i> Data PKL</h3>

                    <div class="detail-item">
                        <div class="detail-label">Lokasi PKL</div>
                        <div class="detail-value"><?php echo htmlspecialchars($siswa_data['nama_lokasi'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Alamat Lokasi</div>
                        <div class="detail-value"><?php echo htmlspecialchars($siswa_data['alamat_lokasi'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Periode Gelombang</div>
                        <div class="detail-value"><?php echo htmlspecialchars($siswa_data['nama_gelombang'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Durasi PKL</div>
                        <div class="detail-value"><?php echo $periode_pkl; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Format Penilaian PKL</div>
                        <div class="detail-value">
                            **<?php echo htmlspecialchars($siswa_data['format_penilaian'] ?? 'Format Default'); ?>**
                        </div>
                    </div>

                    <div class="action-buttons">
                        <?php if ($is_sertifikat_terbit): ?>
                            <a href="sertifikat-cetak.php?siswa_id=<?php echo $siswa_data['id']; ?>" target="_blank" class="btn-print">
                                <i class="fas fa-print"></i> Cetak Sertifikat (Depan & Belakang)
                            </a>
                        <?php endif; ?>
                        <a href="sertifikat-nomor-input.php?siswa_id=<?php echo $siswa_data['id']; ?>" class="btn-input">
                            <i class="fas fa-barcode"></i> <?php echo $is_sertifikat_terbit ? 'Edit Nomor' : 'Input Nomor Sertifikat'; ?>
                        </a>
                    </div>
                </div>

            </div>
<?php 
include 'panel/footer.php'; 
?>