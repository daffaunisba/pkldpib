<!-- <?php
// jadwal-sidang.php (Tampilan Publik: Daftar Jadwal Sidang)

// TIDAK ADA include 'auth-check.php' karena ini untuk publik

include 'config/db-koneksi.php'; // Sesuaikan path koneksi database Anda jika perlu

$message = '';
$sidang_per_page = 10; 

// --- LOGIKA PAGINATION ---
$page = isset($_GET['p']) && ctype_digit($_GET['p']) && (int)$_GET['p'] > 0 ? (int)$_GET['p'] : 1;
$start = ($page - 1) * $sidang_per_page;

// 1. Hitung Total Jadwal Sidang
$total_result = $koneksi->query("SELECT COUNT(id) FROM jadwal_sidang");
if (!$total_result) {
    // Pesan error yang lebih 'user-friendly' untuk publik
    die("Sistem dalam perawatan. Silakan coba beberapa saat lagi."); 
}
$total_rows = $total_result->fetch_array()[0];
$total_pages = ceil($total_rows / $sidang_per_page);

// 2. Ambil Data Jadwal Sidang, Guru Pembimbing, dan Guru Penguji
// Query sama dengan versi admin, namun pastikan hanya data yang relevan yang diambil.
$sidang_query = "
    SELECT 
        js.id,
        IFNULL(js.tanggal_sidang, 'Belum Ditentukan') AS tanggal_sidang,
        IFNULL(js.waktu_sidang, 'Belum Ditentukan') AS waktu_sidang,
        IFNULL(js.ruangan, 'Belum Ditentukan') AS ruangan,
        IFNULL(js.keterangan, '') AS keterangan, 
        js.lokasi_id,
        gp.nama_guru AS nama_pembimbing,
        gu.nama_guru AS nama_penguji_1,
        gu2.nama_guru AS nama_penguji_2,
        l.nama_lokasi
    FROM jadwal_sidang js
    LEFT JOIN guru gp ON js.pembimbing_id = gp.guru_id
    LEFT JOIN guru gu ON js.penguji_id = gu.guru_id
    LEFT JOIN guru gu2 ON js.penguji_id_2 = gu2.guru_id
    LEFT JOIN lokasi_pkl l ON js.lokasi_id = l.lokasi_id
    ORDER BY js.tanggal_sidang ASC, js.waktu_sidang ASC
    LIMIT $start, $sidang_per_page
";
$sidang_data = $koneksi->query($sidang_query);

if (!$sidang_data) {
    die("Gagal memuat data jadwal sidang."); 
}

// --- FUNGSI BANTU UNTUK CEK KOSONG & FORMAT TANGGAL (Diambil dari admin/sidang-pkl.php) ---

// Fungsi ini akan digunakan untuk menampilkan "Belum Ditentukan" jika data kosong atau nilai default SQL.
function format_field($value, $default = 'Belum Ditentukan', $style = '') {
    $value = trim($value);
    // Cek apakah nilai kosong, NULL, atau merupakan string default dari Query
    if (empty($value) || $value == 'Belum Ditentukan' || $value == 'N/A') {
        // Gunakan warna yang lebih netral untuk publik, tapi tetap menonjolkan
        return '<span style="color: #ffc107; font-style: italic; font-weight: 500; ' . $style . '">' . $default . '</span>';
    }
    return htmlspecialchars($value);
}

// Fungsi untuk format tanggal Y-m-d menjadi format Indonesia lengkap
function format_tanggal_indo($tanggal) {
    if (empty($tanggal) || $tanggal == 'Belum Ditentukan') {
        return format_field($tanggal);
    }
    
    $nama_hari = array("Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu");
    $nama_bulan = array(1 => "Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember");

    $timestamp = strtotime($tanggal);
    if ($timestamp === false) {
        return format_field($tanggal);
    }

    $hari = $nama_hari[date('w', $timestamp)];
    $tgl = date('j', $timestamp);
    $bulan = $nama_bulan[(int)date('n', $timestamp)];
    $tahun = date('Y', $timestamp);

    return $hari . ', ' . $tgl . ' ' . $bulan . ' ' . $tahun;
}

// Asumsi Waktu Sidang adalah Sesi atau waktu mentah
function format_waktu($waktu) {
    return format_field($waktu, 'N/A', 'font-size: 0.9em; color: #343a40; font-weight: 600;');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Sidang PKL - Informasi Publik</title>
    <link rel="stylesheet" href="css/style-public.css"> <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        /* Gaya dasar untuk tampilan publik */
        body { background-color: #f8f9fa; font-family: 'Poppins', sans-serif; }
        .public-container { 
            max-width: 1200px; 
            margin: 40px auto; 
            padding: 20px;
            background-color: white; 
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1); 
        }
        h1 { 
            text-align: center; 
            color: #007bff; 
            margin-bottom: 30px; 
            font-weight: 700;
        }
        .info-box { 
            text-align: center; 
            background-color: #e9ecef; 
            padding: 15px; 
            border-radius: 8px; 
            margin-bottom: 20px;
            color: #495057;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
        }
        th, td { 
            padding: 15px; 
            text-align: left; 
            border-bottom: 1px solid #dee2e6;
            vertical-align: top;
            font-size: 14px;
        }
        th { 
            background-color: #007bff; 
            color: white; 
            font-weight: 600; 
            text-transform: uppercase;
        }
        tr:hover { background-color: #f2f2f2; }

        /* Gaya Khusus */
        .guru-tag { display: inline-block; padding: 4px 8px; margin: 2px 0; border-radius: 4px; font-size: 13px; font-weight: 600; }
        .pembimbing-tag { background-color: #d1ecf1; color: #0c5460; }
        .penguji-tag { background-color: #fcebeb; color: #721c24; margin-bottom: 5px; }
        .penguji-label { display: block; font-size: 11px; font-weight: 700; color: #555; margin-bottom: 2px; }
        .siswa-list-box { list-style: none; padding: 0; margin: 0; }
        .siswa-list-box li { 
            background-color: #f1f1f1; color: #383d41; padding: 4px 8px;
            margin-bottom: 3px; border-radius: 3px; font-size: 13px;
        }
        .pagination a {
            padding: 8px 15px; border: 1px solid #ccc; margin: 0 2px; 
            text-decoration: none; color: #333; border-radius: 4px;
        }
        .pagination a.active {
            background-color: #007bff; color: white; border-color: #007bff;
        }
        .pagination a:hover:not(.active) {
            background-color: #e9ecef;
        }
        .date-cell { font-size: 1.1em; font-weight: 700; color: #28a745; }
    </style>
</head>
<body>

<div class="public-container">
    <h1><i class="fas fa-calendar-alt"></i> Jadwal Sidang PKL Tahun Ajaran 2024/2025</h1>
    
    <div class="info-box">
        Silakan cek jadwal, tanggal, ruangan, serta pembimbing dan penguji Anda di bawah ini. Hubungi koordinator PKL jika ada ketidaksesuaian data.
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 3%;">NO</th> 
                <th style="width: 25%;">LOKASI & PESERTA SIDANG</th> 
                <th style="width: 15%;">TANGGAL SIDANG</th> 
                <th style="width: 8%;">WAKTU</th> 
                <th style="width: 10%;">RUANGAN</th> 
                <th style="width: 15%;">PEMBIMBING</th> 
                <th style="width: 20%;">PENGUJI</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            if ($sidang_data->num_rows > 0):
                $no = $start + 1; 
                while ($row = $sidang_data->fetch_assoc()): 
            ?>
            <tr>
                <td><?php echo $no++; ?></td>
                
                <td>
                    <?php if ($row['nama_lokasi']): ?>
                        <p style="margin: 0 0 5px 0;">
                            <strong style="color: #007bff;"><?php echo htmlspecialchars($row['nama_lokasi']); ?></strong>
                        </p>
                        
                        <?php
                        // Mengambil daftar siswa menggunakan Prepared Statement
                        $lokasi_id_sidang = $row['lokasi_id'];
                        // Ambil hanya Nama dan Kelas
                        $siswa_query = $koneksi->prepare("SELECT nama, kelas FROM peserta_didik WHERE lokasi_id = ?");
                        $siswa_query->bind_param("i", $lokasi_id_sidang);
                        $siswa_query->execute();
                        $siswa_result = $siswa_query->get_result();
                        
                        if ($siswa_result->num_rows > 0) {
                            echo '<ul class="siswa-list-box">';
                            while ($siswa = $siswa_result->fetch_assoc()) {
                                echo '<li>' . htmlspecialchars($siswa['nama']) . ' (' . htmlspecialchars($siswa['kelas']) . ')</li>';
                            }
                            echo '</ul>';
                        } else {
                            echo '<span style="color: #dc3545; font-size: 0.9em;">Tidak ada siswa terdaftar.</span>';
                        }
                        $siswa_query->close();
                        ?>
                    <?php else: ?>
                        <span style="color: #dc3545;">Lokasi Tidak Valid</span>
                    <?php endif; ?>
                </td>

                <td class="date-cell">
                    <?php echo format_tanggal_indo($row['tanggal_sidang']); ?>
                </td>
                
                <td>
                    <?php echo format_waktu($row['waktu_sidang']); ?>
                </td>
                
                <td>
                    <?php echo format_field($row['ruangan']); ?>
                </td>
                
                <td>
                    <?php if ($row['nama_pembimbing']): ?>
                        <span class="guru-tag pembimbing-tag"><?php echo htmlspecialchars($row['nama_pembimbing']); ?></span>
                    <?php else: ?>
                        <?php echo format_field('', 'Belum Ditentukan'); ?>
                    <?php endif; ?>
                </td>
                
                <td>
                    <?php 
                    $nama_penguji_1 = $row['nama_penguji_1'];
                    $nama_penguji_2 = $row['nama_penguji_2'];
                    ?>
                    
                    <?php if ($nama_penguji_1): ?>
                        <span class="penguji-label">Penguji 1:</span>
                        <span class="guru-tag penguji-tag"><?php echo htmlspecialchars($nama_penguji_1); ?></span>
                    <?php else: ?>
                        <span class="penguji-label">Penguji 1:</span>
                        <?php echo format_field('', 'Belum Ditentukan'); ?>
                    <?php endif; ?>
                    
                    <div style="height: 5px;"></div> 
                    
                    <?php if ($nama_penguji_2): ?>
                        <span class="penguji-label">Penguji 2:</span>
                        <span class="guru-tag penguji-tag"><?php echo htmlspecialchars($nama_penguji_2); ?></span>
                    <?php else: ?>
                        <span class="penguji-label">Penguji 2:</span>
                        <?php echo format_field('', 'Belum Ditentukan'); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php 
                endwhile; 
            else:
            ?>
            <tr>
                <td colspan="7" style="text-align: center; color: #6c757d; padding: 30px; font-style: italic;">
                    **Belum ada jadwal sidang PKL yang dipublikasikan saat ini.**
                </td>
            </tr>
            <?php 
            endif; 
            ?>
        </tbody>
    </table>
    
    <div class="pagination" style="text-align: center; margin-top: 30px;">
        <?php if ($total_pages > 1): ?>
            <div class="pagination-links">
                <?php if ($page > 1): ?>
                    <a href="?p=<?php echo $page - 1; ?>">&laquo; Sebelumnya</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?p=<?php echo $i; ?>" class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?p=<?php echo $page + 1; ?>">Berikutnya &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

</body>
</html> -->