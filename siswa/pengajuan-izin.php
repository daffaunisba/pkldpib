<?php
session_start();
include '../config/db-koneksi.php';

if (!isset($_SESSION['siswa_id'])) {
    header("Location: login.php");
    exit();
}

$siswa_id = $_SESSION['siswa_id'];
$swal_script = "";

// --- PROSES PENGAJUAN IZIN & NOTIFIKASI WHATSAPP ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['jenis_izin'])) {
    $jenis_izin = mysqli_real_escape_string($koneksi, $_POST['jenis_izin']);
    $tgl_mulai = mysqli_real_escape_string($koneksi, $_POST['tgl_mulai']);
    $tgl_selesai = mysqli_real_escape_string($koneksi, $_POST['tgl_selesai']);
    $alasan = mysqli_real_escape_string($koneksi, $_POST['alasan']);

    // --- CEK VALIDASI TANGGAL BERTABRAKAN ---
    $query_cek_overlap = "
        SELECT id FROM pengajuan_izin 
        WHERE siswa_id = '$siswa_id' 
        AND status != 'Ditolak' 
        AND (tgl_mulai <= '$tgl_selesai' AND tgl_selesai >= '$tgl_mulai')
    ";
    $cek_overlap = $koneksi->query($query_cek_overlap);

    if ($cek_overlap && $cek_overlap->num_rows > 0) {
        $swal_script = "
        <script>
            document.addEventListener('DOMContentLoaded', function() { 
                Swal.fire(
                    'Peringatan!', 
                    'Anda sudah memiliki riwayat pengajuan izin pada rentang tanggal tersebut. Silakan cek kembali riwayat pengajuan Anda.', 
                    'warning'
                ); 
            });
        </script>";
    } else {
        $target_dir = "../uploads/izin/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_name = $_FILES['lampiran']['name'];
        $file_tmp = $_FILES['lampiran']['tmp_name'];
        $file_size = $_FILES['lampiran']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
        if (!in_array($file_ext, $allowed_ext)) {
            $swal_script = "<script>Swal.fire('Gagal!', 'Format file tidak didukung! Hanya JPG, PNG, atau PDF.', 'error');</script>";
        } elseif ($file_size > 2097152) { 
            $swal_script = "<script>Swal.fire('Gagal!', 'Ukuran file maksimal 2MB!', 'error');</script>";
        } else {
            $new_filename = "izin_" . $siswa_id . "_" . time() . "." . $file_ext;
            $target_file = $target_dir . $new_filename;

            if (move_uploaded_file($file_tmp, $target_file)) {
                $query_insert = "INSERT INTO pengajuan_izin (siswa_id, jenis_izin, tgl_mulai, tgl_selesai, alasan, file_pendukung, status, created_at)
                                 VALUES ('$siswa_id', '$jenis_izin', '$tgl_mulai', '$tgl_selesai', '$alasan', '$new_filename', 'Pending', NOW())";

                if ($koneksi->query($query_insert)) {
                    
                    // --- AMBIL DATA GURU PEMBIMBING & KIRIM WHATSAPP ---
                    $query_guru = "SELECT p.nama AS nama_siswa, g.nama_guru, g.no_hp
                                   FROM peserta_didik p
                                   JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
                                   JOIN guru g ON l.guru_id = g.guru_id
                                   WHERE p.id = '$siswa_id'";
                    $res_guru = $koneksi->query($query_guru);

                    if ($res_guru && $res_guru->num_rows > 0) {
                        $data_guru = $res_guru->fetch_assoc();
                        $no_hp_guru = $data_guru['no_hp'];
                        $nama_guru = $data_guru['nama_guru'];
                        $nama_siswa = $data_guru['nama_siswa'];

                        // Format nomor WA
                        $no_wa = preg_replace('/[^0-9]/', '', $no_hp_guru);
                        if (strpos($no_wa, '0') === 0) {
                            $no_wa = '62' . substr($no_wa, 1);
                        }

                        if (!empty($no_wa)) {
                            $token_fonnte = 'enmpN6YNngTwkYpWzzcf';
                            $pesan = "Halo Bpk/Ibu *$nama_guru*,\n\n";
                            $pesan .= "Terdapat pengajuan *$jenis_izin* baru dari siswa bimbingan Anda:\n\n";
                            $pesan .= "👤 *Nama Siswa:* $nama_siswa\n";
                            $pesan .= "📅 *Tanggal:* " . date('d M Y', strtotime($tgl_mulai)) . " s/d " . date('d M Y', strtotime($tgl_selesai)) . "\n";
                            $pesan .= "📝 *Alasan:* $alasan\n\n";
                            $pesan .= "Mohon segera cek aplikasi *Si Mantap PKL* untuk melihat bukti lampiran dan melakukan proses validasi (Setujui/Tolak).\n\n";
                            $pesan .= "Terima kasih.";

                            $curl = curl_init();
                            curl_setopt_array($curl, array(
                              CURLOPT_URL => 'https://api.fonnte.com/send',
                              CURLOPT_RETURNTRANSFER => true,
                              CURLOPT_ENCODING => '',
                              CURLOPT_MAXREDIRS => 10,
                              CURLOPT_TIMEOUT => 0,
                              CURLOPT_FOLLOWLOCATION => true,
                              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                              CURLOPT_CUSTOMREQUEST => 'POST',
                              CURLOPT_POSTFIELDS => array(
                                'target' => $no_wa,
                                'message' => $pesan,
                                'countryCode' => '62', 
                              ),
                              CURLOPT_HTTPHEADER => array(
                                "Authorization: $token_fonnte"
                              ),
                            ));
                            
                            // Eksekusi cURL dan tangkap responnya
                            $response = curl_exec($curl);
                            $error = curl_error($curl);
                            curl_close($curl);
                            
                            // --- SIMPAN LOG WHATSAPP KE DATABASE ---
                            $status_kirim = ($error || strpos(strtolower($response), 'false') !== false) ? 'Gagal' : 'Terkirim';
                            $response_db = $error ? $error : $response;
                            $jenis_pesan = "Notifikasi Izin";

                            $stmt_log = $koneksi->prepare("INSERT INTO log_whatsapp (target_nomor, jenis_pesan, pesan, status, response_api) VALUES (?, ?, ?, ?, ?)");
                            if ($stmt_log) {
                                $stmt_log->bind_param("sssss", $no_wa, $jenis_pesan, $pesan, $status_kirim, $response_db);
                                $stmt_log->execute();
                                $stmt_log->close();
                            }
                            // ----------------------------------------
                        }
                    }

                    $swal_script = "
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            Swal.fire({
                                title: 'Berhasil Terkirim!',
                                text: 'Pengajuan izin Anda sedang diproses. Pembimbing telah dinotifikasi via WhatsApp.',
                                icon: 'success',
                                confirmButtonColor: '#667eea',
                                confirmButtonText: 'Siap, Mantap!'
                            }).then(() => {
                                window.location.href = ''; 
                            });
                        });
                    </script>";
                } else {
                    $swal_script = "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire('Gagal!', 'Gagal menyimpan data pengajuan ke database!', 'error'); });</script>";
                }
            } else {
                $swal_script = "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire('Gagal!', 'Gagal mengupload file pendukung!', 'error'); });</script>";
            }
        }
    }
}

$query_riwayat = "SELECT * FROM pengajuan_izin WHERE siswa_id = '$siswa_id' ORDER BY created_at DESC";
$res_riwayat = $koneksi->query($query_riwayat);

include 'includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    :root {
        --primary-grad: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --card-radius: 20px;
    }

    /* Full Width Wrapper */
    .main-content-wrapper {
        padding: 1.5rem;
        width: 100%;
        background: #fafbfe;
    }

    .card-custom {
        border-radius: var(--card-radius);
        border: none;
        box-shadow: 0 10px 30px rgba(0,0,0,0.03);
        background: white;
        overflow: hidden;
    }

    .card-header-purple {
        background: var(--primary-grad);
        color: white;
        padding: 1.5rem;
    }

    /* Preview Foto di Tabel */
    .img-trigger {
        width: 45px;
        height: 45px;
        object-fit: cover;
        border-radius: 10px;
        cursor: pointer;
        border: 2px solid #e2e8f0;
        transition: 0.2s;
    }
    .img-trigger:hover {
        transform: scale(1.15);
        border-color: #667eea;
        box-shadow: 0 4px 10px rgba(102, 126, 234, 0.2);
    }

    /* Styling Tabel yang Rapi & Responsive (Scroll Samping) */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch; /* Smooth scroll di HP */
        padding: 0 1rem 1rem 1rem;
    }
    
    .custom-table {
        width: 100%;
        min-width: 650px; /* Memaksa tabel tetap berbentuk tabel di HP */
        border-collapse: collapse;
    }
    
    .custom-table th {
        background: #f8fafc;
        color: #64748b;
        font-weight: 800;
        text-transform: uppercase;
        font-size: 0.75rem;
        padding: 1rem;
        border-bottom: 2px solid #e2e8f0;
        letter-spacing: 0.5px;
    }
    
    .custom-table td {
        padding: 1rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        color: #1e293b;
    }
    
    .custom-table tr:last-child td {
        border-bottom: none;
    }
    
    .custom-table tbody tr:hover td {
        background: #fdfdfe;
    }

    .badge-status {
        padding: 6px 14px;
        border-radius: 50px;
        font-weight: 800;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: inline-block;
        min-width: 80px;
        text-align: center;
    }
    .st-pending { background: #fff7ed; color: #f97316; } /* Orange */
    .st-approved { background: #e3fcef; color: #10b981; } /* Green */
    .st-rejected { background: #fff5f5; color: #dc2626; } /* Red */

</style>

<div class="main-content-wrapper">
    <?= $swal_script; ?>

    <div class="mb-4">
        <h3 class="fw-bold text-dark">Layanan Izin & Sakit</h3>
        <p class="text-muted">Ajukan izin PKL dengan melampirkan bukti yang valid.</p>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-4 col-lg-5">
            <div class="card card-custom h-100">
                <div class="card-header-purple">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-edit me-2"></i>Buat Pengajuan</h6>
                </div>
                <div class="card-body p-4">
                    <form action="" method="POST" enctype="multipart/form-data">
                        <div class="form-floating mb-3">
                            <select name="jenis_izin" class="form-select border-0 bg-light" required>
                                <option value="" selected disabled>Pilih Jenis...</option>
                                <option value="Sakit">Sakit</option>
                                <option value="Izin">Izin Keperluan</option>
                            </select>
                            <label>Kategori Izin</label>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div class="form-floating">
                                    <input type="date" name="tgl_mulai" class="form-control border-0 bg-light" required>
                                    <label>Mulai</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-floating">
                                    <input type="date" name="tgl_selesai" class="form-control border-0 bg-light" required>
                                    <label>Selesai</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-floating mb-4">
                            <textarea name="alasan" class="form-control border-0 bg-light" style="height: 100px" placeholder="Alasan" required></textarea>
                            <label>Alasan Detail</label>
                        </div>
                        <div class="mb-4">
                            <label class="small fw-bold text-muted mb-2">Lampiran Bukti</label>
                            <input type="file" name="lampiran" class="form-control form-control-sm" accept="image/*,application/pdf" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-3 rounded-pill fw-bold border-0 shadow" style="background: var(--primary-grad);">
                            KIRIM SEKARANG
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8 col-lg-7">
            <div class="card card-custom h-100">
                <div class="card-header bg-white border-0 p-4 pb-2">
                    <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-history me-2" style="color: #667eea;"></i>Status Pengajuan</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th width="15%" class="text-center">Bukti</th>
                                    <th width="40%">Info Izin</th>
                                    <th width="25%">Tanggal</th>
                                    <th width="20%" class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($res_riwayat->num_rows > 0): ?>
                                    <?php while ($row = $res_riwayat->fetch_assoc()): 
                                        
                                        // 1. Ambil data status, jika kosong/NULL otomatis jadikan 'Pending'
                                        $st_db = !empty($row['status']) ? $row['status'] : 'Pending';
                                        
                                        // 2. Terjemahkan ke Bahasa Indonesia & tentukan warnanya
                                        if ($st_db == 'Disetujui') {
                                            $st_tampil = 'Disetujui';
                                            $cls = 'st-approved';
                                        } elseif ($st_db == 'Ditolak') {
                                            $st_tampil = 'Ditolak';
                                            $cls = 'st-rejected';
                                        } else {
                                            $st_tampil = 'Menunggu';
                                            $cls = 'st-pending';
                                        }
                                        
                                        $file_ext = pathinfo($row['file_pendukung'], PATHINFO_EXTENSION);
                                    ?>
                                    <tr>
                                        <td class="text-center">
                                            <?php if(strtolower($file_ext) == 'pdf'): ?>
                                                <div class="img-trigger mx-auto d-flex align-items-center justify-content-center bg-light" onclick="window.open('../uploads/izin/<?= $row['file_pendukung'] ?>')">
                                                    <i class="fas fa-file-pdf text-danger fa-lg"></i>
                                                </div>
                                            <?php else: ?>
                                                <img src="../uploads/izin/<?= $row['file_pendukung'] ?>" 
                                                     class="img-trigger mx-auto" 
                                                     data-bs-toggle="modal" 
                                                     data-bs-target="#imgModal<?= $row['id'] ?>">
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($row['jenis_izin']) ?></div>
                                            <div class="small text-muted text-truncate" style="max-width: 250px;">
                                                <?= htmlspecialchars($row['alasan']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= date('d M Y', strtotime($row['tgl_mulai'])) ?></div>
                                            <div class="small text-muted">s/d <?= date('d M Y', strtotime($row['tgl_selesai'])) ?></div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge-status <?= $cls ?>"><?= $st_tampil ?></span>
                                        </td>
                                    </tr>

                                    <div class="modal fade" id="imgModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content border-0 bg-transparent">
                                                <div class="modal-body p-0 text-center">
                                                    <img src="../uploads/izin/<?= $row['file_pendukung'] ?>" class="img-fluid rounded-4 shadow-lg" style="max-height: 80vh; object-fit: contain;">
                                                    <button class="btn btn-light rounded-pill mt-3 px-4 fw-bold shadow" data-bs-dismiss="modal">Tutup</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-5 text-muted">
                                            <i class="fas fa-folder-open fs-3 mb-2 opacity-50"></i><br>
                                            Belum ada riwayat pengajuan.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>