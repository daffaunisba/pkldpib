<?php
session_start();
include '../config/db-koneksi.php';

// Tampilkan error di layar jika ada masalah (bukan layar putih)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['siswa_id'])) {
    header("Location: login.php");
    exit();
}

$siswa_id = $_SESSION['siswa_id'];
$success_msg = "";
$error_msg = "";

// --- AUTO-TAMBAH KOLOM DENGAN AMAN (ANTI ERROR 500) ---
try {
    $check_col = $koneksi->query("SHOW COLUMNS FROM laporan_akhir LIKE 'link_drive'");
    if ($check_col && $check_col->num_rows == 0) {
        $koneksi->query("ALTER TABLE laporan_akhir ADD COLUMN link_drive VARCHAR(500) NULL AFTER file_ppt");
    }
} catch (Exception $e) {
    // Jika user tidak punya akses ALTER, abaikan saja agar tidak crash
}

// 0. PROSES HAPUS BERKAS JIKA TOMBOL HAPUS DIKLIK
if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    $query_del = $koneksi->query("SELECT * FROM laporan_akhir WHERE siswa_id = '$siswa_id'");
    $data_del = $query_del->fetch_assoc();
    
    if ($data_del) {
        $target_dir = "../uploads/laporan_akhir/";
        // Hapus file fisik di server agar tidak menumpuk
        if (!empty($data_del['file_laporan']) && file_exists($target_dir . $data_del['file_laporan'])) {
            unlink($target_dir . $data_del['file_laporan']);
        }
        if (!empty($data_del['file_ppt']) && file_exists($target_dir . $data_del['file_ppt'])) {
            unlink($target_dir . $data_del['file_ppt']);
        }
        
        // Hapus data dari database
        if ($koneksi->query("DELETE FROM laporan_akhir WHERE siswa_id = '$siswa_id'")) {
            header("Location: upload-laporan.php?status=deleted");
            exit();
        } else {
            $error_msg = "Gagal menghapus berkas dari sistem.";
        }
    }
}

// Menangkap status hapus dari URL
if (isset($_GET['status']) && $_GET['status'] == 'deleted') {
    $success_msg = "Seluruh berkas dan tautan laporan Anda berhasil dihapus! Anda bisa mengunggah ulang sekarang.";
}

// Mengubah tampilan info batas maksimal menjadi 50MB
$max_upload = "50MB";

// Ambil data laporan yang sudah ada terlebih dahulu
$query_data = $koneksi->query("SELECT * FROM laporan_akhir WHERE siswa_id = '$siswa_id'");
$data_upload = $query_data->fetch_assoc();

// 1. PROSES UPLOAD / SIMPAN DATA
if (isset($_POST['submit_laporan'])) {
    $target_dir = "../uploads/laporan_akhir/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $link_drive = isset($_POST['link_drive']) ? filter_var($_POST['link_drive'], FILTER_SANITIZE_URL) : '';
    $upload_ok = true;

    if (!empty($link_drive) && !filter_var($link_drive, FILTER_VALIDATE_URL)) {
        $error_msg = "Format tautan Drive tidak valid! Harus berupa URL http/https.";
        $upload_ok = false;
    }

    // Gunakan file lama sebagai default jika tidak ada file baru yang diupload
    $nama_file_laporan = $data_upload['file_laporan'] ?? '';
    $nama_file_ppt = $data_upload['file_ppt'] ?? '';

    // --- PROSES DOKUMEN LAPORAN (PDF) ---
    if ($upload_ok && isset($_FILES['file_laporan']) && $_FILES['file_laporan']['error'] === UPLOAD_ERR_OK) {
        $ext_laporan = strtolower(pathinfo($_FILES['file_laporan']['name'], PATHINFO_EXTENSION));
        if ($ext_laporan !== 'pdf') {
            $error_msg = "Dokumen Laporan wajib berformat PDF!";
            $upload_ok = false;
        } else {
            $tmp_nama_laporan = "Laporan_" . $siswa_id . "_" . time() . "." . $ext_laporan;
            if (move_uploaded_file($_FILES['file_laporan']['tmp_name'], $target_dir . $tmp_nama_laporan)) {
                if (!empty($nama_file_laporan) && file_exists($target_dir . $nama_file_laporan)) {
                    unlink($target_dir . $nama_file_laporan);
                }
                $nama_file_laporan = $tmp_nama_laporan;
            } else {
                $error_msg = "Gagal memindahkan file Laporan.";
                $upload_ok = false;
            }
        }
    } elseif ($upload_ok && isset($_FILES['file_laporan']) && $_FILES['file_laporan']['error'] !== UPLOAD_ERR_NO_FILE && $_FILES['file_laporan']['error'] !== UPLOAD_ERR_OK) {
        $error_msg = "Gagal! File Laporan mungkin melebihi batas sistem. Gunakan fitur Tautan Drive.";
        $upload_ok = false;
    }

    // --- PROSES SLIDE PRESENTASI (PPT/PPTX) ---
    if ($upload_ok && isset($_FILES['file_ppt']) && $_FILES['file_ppt']['error'] === UPLOAD_ERR_OK) {
        $ext_ppt = strtolower(pathinfo($_FILES['file_ppt']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext_ppt, ['ppt', 'pptx'])) {
            $error_msg = "File Presentasi wajib berformat PPT atau PPTX!";
            $upload_ok = false;
        } else {
            $tmp_nama_ppt = "PPT_" . $siswa_id . "_" . time() . "." . $ext_ppt;
            if (move_uploaded_file($_FILES['file_ppt']['tmp_name'], $target_dir . $tmp_nama_ppt)) {
                if (!empty($nama_file_ppt) && file_exists($target_dir . $nama_file_ppt)) {
                    unlink($target_dir . $nama_file_ppt);
                }
                $nama_file_ppt = $tmp_nama_ppt;
            } else {
                $error_msg = "Gagal memindahkan file PPT.";
                $upload_ok = false;
            }
        }
    } elseif ($upload_ok && isset($_FILES['file_ppt']) && $_FILES['file_ppt']['error'] !== UPLOAD_ERR_NO_FILE && $_FILES['file_ppt']['error'] !== UPLOAD_ERR_OK) {
        $error_msg = "Gagal! File PPT mungkin melebihi batas sistem. Gunakan fitur Tautan Drive.";
        $upload_ok = false;
    }

    // --- PROSES SIMPAN KE DATABASE ---
    if ($upload_ok && empty($error_msg)) {
        if (empty($nama_file_laporan) && empty($nama_file_ppt) && empty($link_drive)) {
            $error_msg = "Mohon unggah setidaknya salah satu berkas atau tautan.";
        } else {
            $sql = "INSERT INTO laporan_akhir (siswa_id, file_laporan, file_ppt, link_drive, tgl_upload) 
                    VALUES ('$siswa_id', '$nama_file_laporan', '$nama_file_ppt', '$link_drive', NOW())
                    ON DUPLICATE KEY UPDATE file_laporan='$nama_file_laporan', file_ppt='$nama_file_ppt', link_drive='$link_drive', tgl_upload=NOW()";
            
            try {
                if ($koneksi->query($sql)) {
                    $success_msg = "Semua berkas & tautan berhasil disimpan!";
                    $query_data = $koneksi->query("SELECT * FROM laporan_akhir WHERE siswa_id = '$siswa_id'");
                    $data_upload = $query_data->fetch_assoc();
                } else {
                    $error_msg = "Gagal menyimpan ke database: " . $koneksi->error;
                }
            } catch (Exception $e) {
                $error_msg = "Terjadi masalah database: " . $e->getMessage() . " (Pastikan kolom link_drive sudah ada di database).";
            }
        }
    }
}

include 'includes/header.php';
?>

<!-- Tambahkan Library SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    :root {
        --primary-grad: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --purple-light: #f0f3ff;
        --purple-accent: #667eea;
        --card-radius: 25px;
        --item-radius: 20px;
    }

    .container-wrapper { padding: 1.5rem; width: 100%; overflow-x: hidden; background: #fafbfe; }

    .page-header-box { background: var(--primary-grad); color: white; border-radius: var(--card-radius); padding: 2.5rem 2rem; margin-bottom: 1.5rem; box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2); position: relative; overflow: hidden; }
    .page-header-box::after { content: ''; position: absolute; width: 150px; height: 150px; background: rgba(255, 255, 255, 0.05); border-radius: 50%; right: -30px; top: -30px; }
    
    .upload-card, .result-card { background: white; border-radius: var(--card-radius); padding: 2.5rem 2rem; box-shadow: 0 15px 40px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; margin-bottom: 1.5rem; }
    
    .form-label { font-weight: 800; color: #475569; margin-bottom: 10px; display: block; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }

    /* Dashed Dropzone Style */
    .file-input-group { background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 25px 20px; text-align: center; transition: 0.3s; margin-bottom: 1.5rem; position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center; }
    .file-input-group:hover { border-color: #667eea; background: var(--purple-light); }
    .file-input-group i { font-size: 2.5rem; color: #94a3b8; margin-bottom: 12px; transition: 0.3s; }
    .file-input-group:hover i { color: #667eea; transform: scale(1.05); }
    .custom-file-input { position: absolute; width: 100%; height: 100%; top: 0; left: 0; opacity: 0; cursor: pointer; }
    
    /* Text Input URL */
    .url-input-group { margin-bottom: 1.5rem; }
    .url-input-group input { width: 100%; padding: 14px 20px; border: 2px dashed #cbd5e1; border-radius: 12px; font-family: 'Poppins', sans-serif; font-size: 0.9rem; background: #f8fafc; transition: 0.3s; text-align: center;}
    .url-input-group input:focus { outline: none; border-color: #10b981; border-style: solid; background: #ecfdf5; box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1); color: #065f46; }

    .btn-upload { background: var(--primary-grad); color: white; padding: 1rem; border-radius: 50px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; border: none; width: 100%; transition: 0.3s; font-size: 0.85rem; box-shadow: 0 6px 20px rgba(102, 126, 234, 0.25); }
    .btn-upload:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(102, 126, 234, 0.35); color: white; }

    /* Result Box / File Lists */
    .file-item { display: flex; align-items: center; background: #f8fafc; padding: 16px 20px; border-radius: var(--item-radius); margin-bottom: 12px; border: 1px solid #e2e8f0; transition: 0.3s; }
    .file-item:hover { transform: translateX(3px); background: #f1f5f9; }
    .file-item i.type-icon { font-size: 1.8rem; margin-right: 15px; flex-shrink: 0; }
    .file-info { flex-grow: 1; overflow: hidden; }
    .file-info span { display: block; font-weight: 700; font-size: 0.9rem; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .file-info small { color: #94a3b8; font-weight: 600; font-size: 11px; text-transform: uppercase; }

    .btn-view-file { width: 36px; height: 36px; border-radius: 50%; background: white; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; color: #667eea; box-shadow: 0 4px 10px rgba(0,0,0,0.03); transition: 0.3s; text-decoration: none; }
    .btn-view-file:hover { background: var(--primary-grad); color: white; border-color: transparent; }

    /* Tombol Hapus Laporan */
    .btn-delete-laporan { background: #fef2f2; color: #ef4444; border: 1px solid #fecaca; padding: 8px 15px; border-radius: 8px; font-size: 0.8rem; font-weight: 700; text-decoration: none; transition: 0.3s; display: inline-flex; align-items: center; cursor: pointer; }
    .btn-delete-laporan:hover { background: #ef4444; color: white; border-color: #ef4444; }

    /* Flex Container untuk footer arsip */
    .arsip-footer { display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed #e2e8f0; padding-top: 15px; margin-top: 5px; flex-wrap: wrap; gap: 10px;}
</style>

<div class="container-wrapper">
    <div class="page-header-box">
        <h4 class="fw-bold text-white mb-1">Laporan Akhir PKL 📋</h4>
        <p class="mb-0 opacity-75 small">Kirimkan berkas laporan dan presentasi sidang. Gunakan opsi Tautan Drive jika file terlalu besar.</p>
    </div>

    <div class="upload-card">
        <div style="background:#fff3cd; color:#b45309; padding:12px 15px; border-radius:8px; font-size:0.8rem; font-weight:600; margin-bottom:20px; border: 1px dashed #fcd34d;">
            <i class="fas fa-info-circle me-1"></i> Batas ukuran unggah file server adalah <strong><?= $max_upload ?></strong>. Jika file melebihi batas, kosongkan bagian unggah file dan kirimkan dalam bentuk <strong>Tautan Google Drive</strong> di bawah.
        </div>

        <form action="" method="POST" id="formLaporan" enctype="multipart/form-data">
            
            <!-- 1. DOKUMEN LAPORAN -->
            <label class="form-label mt-3">1. Dokumen Laporan (Wajib PDF)</label>
            <div class="file-input-group">
                <i class="fas fa-file-pdf"></i>
                <p class="mb-0 text-muted small fw-semibold" id="label-laporan">
                    <?php if($data_upload && !empty($data_upload['file_laporan'])): ?>
                        <span style="color:#10b981;"><i class="fas fa-check-circle"></i> Telah diunggah: <?= htmlspecialchars($data_upload['file_laporan']) ?></span><br>
                        <small style="color:#94a3b8;">(Klik/tarik file ke sini jika ingin mengganti)</small>
                    <?php else: ?>
                        Klik atau tarik file PDF ke sini
                    <?php endif; ?>
                </p>
                <input type="file" name="file_laporan" id="input_file_laporan" class="custom-file-input" accept=".pdf" <?= (empty($data_upload['file_laporan'])) ? 'required' : '' ?> onchange="updateLabel(this, 'label-laporan')">
            </div>

            <!-- 2. SLIDE PRESENTASI -->
            <label class="form-label mt-4">2. Slide Presentasi (Wajib PPT/PPTX)</label>
            <div class="file-input-group">
                <i class="fas fa-file-powerpoint"></i>
                <p class="mb-0 text-muted small fw-semibold" id="label-ppt">
                    <?php if($data_upload && !empty($data_upload['file_ppt'])): ?>
                        <span style="color:#10b981;"><i class="fas fa-check-circle"></i> Telah diunggah: <?= htmlspecialchars($data_upload['file_ppt']) ?></span><br>
                        <small style="color:#94a3b8;">(Klik/tarik file ke sini jika ingin mengganti)</small>
                    <?php else: ?>
                        Klik atau tarik file PPT / PPTX ke sini
                    <?php endif; ?>
                </p>
                <input type="file" name="file_ppt" id="input_file_ppt" class="custom-file-input" accept=".ppt,.pptx" <?= (empty($data_upload['file_ppt'])) ? 'required' : '' ?> onchange="updateLabel(this, 'label-ppt')">
            </div>

            <!-- 3. TAUTAN DRIVE EKS (OPSIONAL) -->
            <label class="form-label mt-4" style="color:#10b981;"><i class="fas fa-link me-1"></i> 3. Masukkan Tautan Drive (Opsional)</label>
            <div class="url-input-group">
                <input type="url" name="link_drive" id="input_link_drive" placeholder="Misal: https://drive.google.com/..." value="<?= htmlspecialchars($data_upload['link_drive'] ?? '') ?>">
            </div>

            <button type="submit" name="submit_laporan" class="btn btn-upload mt-3" onclick="checkBypass()">
                <i class="fas fa-paper-plane me-2"></i> <?= ($data_upload) ? 'Perbarui Data Laporan' : 'Serahkan Tugas Sekarang' ?>
            </button>
        </form>
    </div>

    <!-- Kotak Review Berkas Terunggah -->
    <?php if ($data_upload): ?>
    <div class="result-card">
        <h5 class="fw-bold text-dark mb-3" style="font-size: 1rem;"><i class="fas fa-folder-open me-2" style="color: #667eea;"></i>Arsip Terkirim Anda</h5>

        <!-- Menampilkan PDF Jika Ada -->
        <?php if (!empty($data_upload['file_laporan'])): ?>
        <div class="file-item">
            <i class="type-icon fas fa-file-pdf" style="color: #ef4444;"></i>
            <div class="file-info">
                <span><?= htmlspecialchars($data_upload['file_laporan']) ?></span>
                <small>Dokumen Laporan Akhir</small>
            </div>
            <a href="../uploads/laporan_akhir/<?= $data_upload['file_laporan'] ?>" target="_blank" class="btn-view-file" title="Buka Laporan">
                <i class="fas fa-eye" style="font-size: 0.9rem; margin:0;"></i>
            </a>
        </div>
        <?php endif; ?>

        <!-- Menampilkan PPT Jika Ada -->
        <?php if (!empty($data_upload['file_ppt'])): ?>
        <div class="file-item">
            <i class="type-icon fas fa-file-powerpoint" style="color: #f59e0b;"></i>
            <div class="file-info">
                <span><?= htmlspecialchars($data_upload['file_ppt']) ?></span>
                <small>Bahan Presentasi Sidang</small>
            </div>
            <a href="../uploads/laporan_akhir/<?= $data_upload['file_ppt'] ?>" target="_blank" class="btn-view-file" title="Unduh Presentasi">
                <i class="fas fa-download" style="font-size: 0.9rem; margin:0;"></i>
            </a>
        </div>
        <?php endif; ?>

        <!-- Menampilkan Link Drive Jika Ada -->
        <?php if (!empty($data_upload['link_drive'])): ?>
        <div class="file-item" style="border-color: #a7f3d0; background: #f0fdf4;">
            <i class="type-icon fas fa-link" style="color: #10b981;"></i>
            <div class="file-info">
                <span style="color: #065f46;">Tautan Eksternal Tersimpan</span>
                <small style="color: #059669;">Akses via Google Drive / Lainnya</small>
            </div>
            <a href="<?= htmlspecialchars($data_upload['link_drive'] ?? '') ?>" target="_blank" class="btn-view-file" style="color: #10b981; border-color: #a7f3d0;" title="Buka Tautan">
                <i class="fas fa-external-link-alt" style="font-size: 0.9rem; margin:0;"></i>
            </a>
        </div>
        <?php endif; ?>

        <div class="arsip-footer">
            <a href="#" class="btn-delete-laporan" onclick="konfirmasiHapus(event)">
                <i class="fas fa-trash-alt me-1"></i> Hapus Semua Berkas
            </a>
            <small class="text-muted fw-semibold" style="font-size: 11px; font-style: italic;">
                <i class="far fa-clock me-1"></i>Diperbarui: <?= date('d M Y - H:i', strtotime($data_upload['tgl_upload'])) ?> WIB
            </small>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    // Fitur Pop-Up Success / Error menggunakan SweetAlert2
    document.addEventListener("DOMContentLoaded", function() {
        <?php if(!empty($success_msg)): ?>
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: '<?= addslashes($success_msg) ?>',
                confirmButtonColor: '#10b981'
            }).then(() => {
                // Menghilangkan parameter status=deleted dari URL tanpa merefresh halaman
                if (window.location.search.includes('status=deleted')) {
                    window.history.replaceState(null, null, window.location.pathname);
                }
            });
        <?php endif; ?>

        <?php if(!empty($error_msg)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                text: '<?= addslashes($error_msg) ?>',
                confirmButtonColor: '#ef4444'
            });
        <?php endif; ?>
    });

    function updateLabel(input, labelId) {
        if (input.files && input.files[0]) {
            const fileName = input.files[0].name;
            const label = document.getElementById(labelId);
            label.innerHTML = "<strong><i class='fas fa-check text-success me-1'></i> " + fileName + "</strong><br><small style='color:#94a3b8;'>(Siap diunggah)</small>";
            label.style.color = "#667eea";
        }
    }

    function checkBypass() {
        const linkInput = document.getElementById('input_link_drive').value;
        const fileLaporan = document.getElementById('input_file_laporan');
        const filePpt = document.getElementById('input_file_ppt');
        
        if(linkInput.trim() !== '') {
            fileLaporan.removeAttribute('required');
            filePpt.removeAttribute('required');
        } else {
            <?php if(empty($data_upload['file_laporan'])): ?> fileLaporan.setAttribute('required', 'required'); <?php endif; ?>
            <?php if(empty($data_upload['file_ppt'])): ?> filePpt.setAttribute('required', 'required'); <?php endif; ?>
        }
    }

    // Fungsi Pop-Up Konfirmasi Hapus yang Elegan
    function konfirmasiHapus(e) {
        e.preventDefault(); // Mencegah link langsung terbuka
        Swal.fire({
            title: 'Hapus Semua Arsip?',
            text: "Berkas fisik dan tautan akan dihapus secara permanen dari server. Anda harus mengunggahnya kembali dari awal.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444', // Merah bahaya
            cancelButtonColor: '#64748b', // Abu-abu aman
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal',
            reverseButtons: true, // Letakkan tombol batal di sebelah kiri
            backdrop: `rgba(15,23,42,0.4)` // Latar belakang sedikit blur
        }).then((result) => {
            if (result.isConfirmed) {
                // Lanjutkan penghapusan jika dikonfirmasi
                window.location.href = "?action=delete";
            }
        });
    }
</script>

<?php include 'includes/footer.php'; ?>