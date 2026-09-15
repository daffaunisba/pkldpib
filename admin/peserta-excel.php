<?php
// admin/peserta-excel.php
include 'auth-check.php';
include '../config/db-koneksi.php';

// Ambil filter periode jika ada
$filter_periode_id = isset($_GET['periode_id']) ? (int)$_GET['periode_id'] : 0;
$where_clause = ($filter_periode_id > 0) ? "WHERE p.periode_id = {$filter_periode_id}" : "";

// Mendapatkan nama periode untuk penamaan file
$nama_file = "Data_Peserta_PKL_Semua";
if($filter_periode_id > 0){
    $res = $koneksi->query("SELECT nama_periode FROM periode_pkl WHERE periode_id = $filter_periode_id");
    $p_data = $res->fetch_assoc();
    $nama_file = "Data_Peserta_PKL_" . str_replace(' ', '_', $p_data['nama_periode']);
}

// Fungsi Header untuk paksa download Excel
header("Content-type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=$nama_file.xls");

// Ambil data dari database
$peserta_query = "
    SELECT 
        p.nisn, p.nama, p.email, p.kelas, p.no_hp,
        l.nama_lokasi, l.jam_kerja, l.alamat,
        pr.nama_periode 
    FROM peserta_didik p
    LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
    LEFT JOIN periode_pkl pr ON p.periode_id = pr.periode_id 
    {$where_clause}
    ORDER BY p.id ASC
";
$peserta_data = $koneksi->query($peserta_query);
?>

<table border="1">
    <thead>
        <tr>
            <th colspan="10" style="font-size: 16px; font-weight: bold;"><?php echo strtoupper(str_replace('_', ' ', $nama_file)); ?></th>
        </tr>
        <tr>
            <th>NO</th>
            <th>PERIODE</th>
            <th>NISN</th>
            <th>NAMA SISWA</th>
            <th>KELAS</th>
            <th>EMAIL</th>
            <th>NO. HP (WA)</th>
            <th>LOKASI PKL</th>
            <th>JAM KERJA</th>
            <th>ALAMAT LOKASI</th>
        </tr>
    </thead>
    <tbody>
        <?php $no = 1; while ($row = $peserta_data->fetch_assoc()): ?>
        <tr>
            <td><?php echo $no++; ?></td>
            <td><?php echo $row['nama_periode']; ?></td>
            <td>'<?php echo $row['nisn']; ?></td> <td><?php echo strtoupper($row['nama']); ?></td>
            <td><?php echo $row['kelas']; ?></td>
            <td><?php echo $row['email']; ?></td>
            <td>'<?php echo $row['no_hp']; ?></td> <td><?php echo $row['nama_lokasi']; ?></td>
            <td><?php echo $row['jam_kerja']; ?></td>
            <td><?php echo $row['alamat']; ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>