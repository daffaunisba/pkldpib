<?php
session_start();
include '../config/db-koneksi.php';
// Pastikan Anda sudah menginstal library PDF (misal: composer require dompdf/dompdf)
// require 'vendor/autoload.php'; 

if (!isset($_SESSION['siswa_id'])) { exit; }

$siswa_id = $_SESSION['siswa_id'];
$bulan = $_GET['bulan'];
$tahun = $_GET['tahun'];

// Query data sama dengan halaman riwayat
$query = "SELECT * FROM presensi_pkl WHERE siswa_id = '$siswa_id' AND MONTH(tanggal) = '$bulan' AND YEAR(tanggal) = '$tahun' ORDER BY tanggal ASC";
$result = $koneksi->query($query);

// Di sini Anda menyusun HTML untuk isi PDF-nya
$html = '<h2>Laporan Presensi PKL - Periode '.$bulan.'/'.$tahun.'</h2>';
$html .= '<table border="1" width="100%" cellpadding="5" cellspacing="0">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Jam Masuk</th>
                    <th>Jam Pulang</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>';

while($row = $result->fetch_assoc()) {
    $html .= '<tr>
                <td>'.$row['tanggal'].'</td>
                <td>'.$row['jam_masuk'].'</td>
                <td>'.($row['jam_pulang'] ?? '--:--').'</td>
                <td>'.$row['status'].'</td>
              </tr>';
}
$html .= '</tbody></table>';

// Contoh menggunakan Dompdf:
// $dompdf = new Dompdf\Dompdf();
// $dompdf->loadHtml($html);
// $dompdf->render();
// $dompdf->stream("Laporan_Presensi.pdf");
?>