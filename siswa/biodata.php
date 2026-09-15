<?php
session_start();
include '../config/db-koneksi.php';

if (!isset($_SESSION['siswa_id'])) {
    header("Location: login.php");
    exit();
}

$siswa_id = $_SESSION['siswa_id'];
$swal_script = "";

// ---------------------------------------------------------------------
// 1. AUTO-CREATE KOLOM SPESIFIK UNTUK TRACKING JIKA BELUM ADA (ANTI-ERROR)
// ---------------------------------------------------------------------
$cek_kolom = $koneksi->query("SHOW COLUMNS FROM peserta_didik LIKE 'update_email'");
if ($cek_kolom && $cek_kolom->num_rows == 0) {
    // Buat kolom jika belum ada di database
    $koneksi->query("ALTER TABLE peserta_didik 
                     ADD COLUMN update_email DATETIME NULL DEFAULT NULL,
                     ADD COLUMN update_no_hp DATETIME NULL DEFAULT NULL,
                     ADD COLUMN update_alamat DATETIME NULL DEFAULT NULL,
                     ADD COLUMN update_foto DATETIME NULL DEFAULT NULL");
}

// ---------------------------------------------------------------------
// 2. LOGIKA UPDATE DATA & FOTO (DENGAN TRACKING PERUBAHAN)
// ---------------------------------------------------------------------
if (isset($_POST['update_profil'])) {
    // Ambil data lama untuk dicompare
    $q_lama = $koneksi->query("SELECT email, no_hp, alamat_siswa FROM peserta_didik WHERE id = '$siswa_id'");
    $d_lama = $q_lama->fetch_assoc();

    $email  = trim(mysqli_real_escape_string($koneksi, $_POST['email']));
    $no_hp  = trim(mysqli_real_escape_string($koneksi, $_POST['no_hp']));
    $alamat = trim(mysqli_real_escape_string($koneksi, $_POST['alamat']));
    
    // Cek Perubahan: Menyusun syntax SET SQL dengan benar
    $set_clause = "";
    if ($email !== ($d_lama['email'] ?? '')) { $set_clause .= "update_email = NOW(), "; }
    if ($no_hp !== ($d_lama['no_hp'] ?? '')) { $set_clause .= "update_no_hp = NOW(), "; }
    if ($alamat !== ($d_lama['alamat_siswa'] ?? '')) { $set_clause .= "update_alamat = NOW(), "; }

    $folder_tujuan = "../assets/img/profile/";
    if (!is_dir($folder_tujuan)) {
        mkdir($folder_tujuan, 0777, true);
    }

    $stmt_upd = false;

    if (!empty($_FILES['foto_profil']['name'])) {
        $nama_file = $_FILES['foto_profil']['name'];
        $tmp_file  = $_FILES['foto_profil']['tmp_name'];
        $ekstensi  = pathinfo($nama_file, PATHINFO_EXTENSION);
        $nama_baru = "profil_" . $siswa_id . "_" . time() . "." . $ekstensi;
        $tujuan    = $folder_tujuan . $nama_baru;

        if (move_uploaded_file($tmp_file, $tujuan)) {
            $set_clause .= "update_foto = NOW(), ";
            
            $update_query = "UPDATE peserta_didik SET $set_clause email = ?, no_hp = ?, alamat_siswa = ?, foto_profil = ? WHERE id = ?";
            $stmt_upd = $koneksi->prepare($update_query);
            if ($stmt_upd) {
                $stmt_upd->bind_param("ssssi", $email, $no_hp, $alamat, $nama_baru, $siswa_id);
            }
        }
    } else {
        $update_query = "UPDATE peserta_didik SET $set_clause email = ?, no_hp = ?, alamat_siswa = ? WHERE id = ?";
        $stmt_upd = $koneksi->prepare($update_query);
        if ($stmt_upd) {
            $stmt_upd->bind_param("sssi", $email, $no_hp, $alamat, $siswa_id);
        }
    }
    
    // Eksekusi jika statement valid
    if ($stmt_upd && $stmt_upd->execute()) {
        $swal_script = "
        <script>
            Swal.fire({
                title: 'Berhasil!',
                text: 'Profil Anda telah diperbarui.',
                icon: 'success',
                confirmButtonColor: '#667eea',
                confirmButtonText: 'Mantap'
            }).then(() => { window.location='biodata.php'; });
        </script>";
    }
}

// --- 3. AMBIL DATA MURID (DENGAN TANGGAL PERIODE, HARI KERJA & JAM KERJA) ---
$query = "SELECT p.*, l.nama_lokasi, l.hari_mulai, l.hari_selesai, l.jam_kerja, g.nama_guru, per.tgl_mulai, per.tgl_akhir 
          FROM peserta_didik p
          LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
          LEFT JOIN guru g ON l.guru_id = g.guru_id
          LEFT JOIN periode_pkl per ON p.periode_id = per.periode_id
          WHERE p.id = ?";

$stmt = $koneksi->prepare($query);
$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

$foto_db = $data['foto_profil'] ?? '';
$foto_path = (!empty($foto_db) && file_exists("../assets/img/profile/" . $foto_db)) 
             ? "../assets/img/profile/" . $foto_db 
             : "../assets/img/avatar.png";

// Formatting Tanggal
function formatTgl($tanggal) {
    if (empty($tanggal)) return '-';
    return date('d M Y', strtotime($tanggal));
}
$tgl_mulai_format = formatTgl($data['tgl_mulai']);
$tgl_akhir_format = formatTgl($data['tgl_akhir']);

// Formatting Hari Kerja
$hari_mulai_db = $data['hari_mulai'] ?? 'Senin';
$hari_selesai_db = $data['hari_selesai'] ?? 'Jumat';
$hari_kerja_format = ($data['lokasi_id']) ? $hari_mulai_db . " - " . $hari_selesai_db : 'Belum Diatur';

include 'includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    :root { 
        --primary-grad: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
    }
    
    .main-wrapper { 
        padding: 1.5rem; 
        width: 100%; 
        overflow-x: hidden; 
        background: #fafbfe; 
        min-height: calc(100vh - 70px);
    }
    
    .biodata-container { 
        width: 100%; 
        margin: 0 auto; 
    }
    
    .profile-header { 
        background: var(--primary-grad); 
        border-radius: 25px; 
        padding: 4rem 1.5rem; 
        text-align: center; 
        color: white; 
        margin-bottom: -50px; 
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2); 
        position: relative; 
        overflow: hidden; 
    }
    
    .profile-header::after {
        content: ''; 
        position: absolute; 
        width: 150px; 
        height: 150px; 
        background: rgba(255, 255, 255, 0.05); 
        border-radius: 50%; 
        right: -30px; 
        top: -30px; 
        pointer-events: none;
    }

    .profile-avatar { 
        width: 130px; 
        height: 130px; 
        border-radius: 50%; 
        border: 4px solid rgba(255, 255, 255, 0.3); 
        object-fit: cover; 
        background: white; 
        position: relative; 
        z-index: 2; 
    }
    
    .info-card { 
        background: white; 
        border-radius: 25px; 
        padding: 80px 2rem 2.5rem; 
        box-shadow: 0 15px 40px rgba(0,0,0,0.03); 
        border: 1px solid #f1f5f9; 
        position: relative; 
        z-index: 1; 
    }
    
    .detail-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
    
    .detail-item { 
        display: flex; 
        align-items: center; 
        padding: 1.2rem; 
        background: #f8fafc; 
        border-radius: 20px; 
        border: 1px solid #e2e8f0; 
        transition: 0.3s; 
    }
    .detail-item:hover { 
        transform: translateX(3px); 
        background: #f1f5f9; 
    }
    
    .detail-icon { 
        width: 48px; 
        height: 48px; 
        background: white; 
        color: #667eea; 
        border-radius: 12px; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        margin-right: 1.2rem; 
        flex-shrink: 0; 
        font-size: 1.3rem; 
        box-shadow: 0 2px 10px rgba(102, 126, 234, 0.05); 
    }
    
    .detail-content { flex: 1; overflow: hidden; }
    .detail-label { font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px; display: block; margin-bottom: 3px; }
    .detail-value { font-weight: 700; color: #1e293b; font-size: 1.05rem; margin: 0; word-break: break-word; }
    
    .btn-update-biodata {
        background: var(--primary-grad);
        color: white;
        border: none;
        font-size: 1rem;
        font-weight: 800;
        padding: 1.2rem 3rem;
        border-radius: 50px;
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.25);
        transition: 0.3s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .btn-update-biodata:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        color: white;
    }

    /* Styling Tombol ID Card Baru */
    .btn-id-card {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 1rem 3rem;
        border-radius: 50px;
        background: #f8fafc;
        color: #667eea;
        text-decoration: none;
        font-weight: 800;
        font-size: 0.95rem;
        transition: 0.3s;
        border: 2px solid #e2e8f0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 15px;
    }
    .btn-id-card:hover {
        background: #e2e8f0;
        color: #4f46e5;
        transform: translateY(-2px);
    }
    
    @media (min-width: 992px) { 
        .detail-grid { grid-template-columns: repeat(2, 1fr); gap: 1.5rem; } 
        .full-row { grid-column: span 2; } 
        .info-card { padding: 90px 3rem 3rem; } 
        .profile-avatar { width: 140px; height: 140px; } 
        .btn-update-biodata, .btn-id-card { min-width: 400px; }
    }
    
    @media (max-width: 768px) {
        .info-card { padding: 70px 1.2rem 2rem; }
        .btn-update-biodata, .btn-id-card {
            width: auto;
            min-width: 260px;
            padding: 1rem 1.5rem;
            font-size: 0.9rem;
        }
    }
    
    .badge-info-container { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin-bottom: 35px; }
    .badge-custom { padding: 8px 18px; border-radius: 50px; font-weight: 800; font-size: 0.85rem; display: inline-flex; align-items: center; border: 1px solid transparent; }
    .bg-soft-primary { background: #f0f3ff; color: #5a67d8; border-color: #e0e7ff; }
    .bg-soft-danger { background: #fff5f5; color: #e53e3e; border-color: #fed7d7; }
    .bg-soft-success { background: #f0fdf4; color: #16a34a; border-color: #bbf7d0; }
    .bg-soft-warning { background: #fffbeb; color: #d97706; border-color: #fde68a; } /* Tambahan warna untuk hari kerja */
</style>

<div class="main-wrapper">
    <?= $swal_script ?>

    <div class="biodata-container">
        <div class="profile-header">
            <img src="<?= $foto_path ?>" alt="Avatar" class="profile-avatar shadow">
            <h3 class="mt-3 fw-bold mb-1" style="text-shadow: 0 2px 4px rgba(0,0,0,0.2);"><?= htmlspecialchars($data['nama'] ?? $_SESSION['nama_siswa']) ?></h3>
            <p class="opacity-75 mb-0" style="font-size: 0.95rem;">NISN: <?= htmlspecialchars($data['nisn'] ?? '-') ?></p>
        </div>

        <div class="info-card">
            
            <div class="badge-info-container">
                <div class="badge-custom bg-soft-primary">
                    <i class="fas fa-map-marker-alt me-2"></i><?= htmlspecialchars($data['nama_lokasi'] ?? 'Belum Ditentukan') ?>
                </div>
                <div class="badge-custom bg-soft-warning">
                    <i class="fas fa-calendar-day me-2"></i>Hari Kerja: <?= $hari_kerja_format ?>
                </div>
                <div class="badge-custom bg-soft-danger">
                    <i class="fas fa-clock me-2"></i>Jam Kerja: <?= htmlspecialchars($data['jam_kerja'] ?? 'Belum Diatur') ?>
                </div>
                <div class="badge-custom bg-soft-success">
                    <i class="far fa-calendar-alt me-2"></i><?= $tgl_mulai_format ?> s.d. <?= $tgl_akhir_format ?>
                </div>
            </div>

            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-icon"><i class="fas fa-envelope"></i></div>
                    <div class="detail-content">
                        <span class="detail-label">Email Aktif</span>
                        <p class="detail-value"><?= htmlspecialchars($data['email'] ?? '-') ?></p>
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-icon"><i class="fas fa-phone-alt"></i></div>
                    <div class="detail-content">
                        <span class="detail-label">WhatsApp</span>
                        <p class="detail-value"><?= htmlspecialchars($data['no_hp'] ?? '-') ?></p>
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-icon"><i class="fas fa-graduation-cap"></i></div>
                    <div class="detail-content">
                        <span class="detail-label">Kelas</span>
                        <p class="detail-value"><?= htmlspecialchars($data['kelas'] ?? '-') ?></p>
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-icon"><i class="fas fa-user-shield"></i></div>
                    <div class="detail-content">
                        <span class="detail-label">Pembimbing Sekolah</span>
                        <p class="detail-value"><?= htmlspecialchars($data['nama_guru'] ?? 'N/A') ?></p>
                    </div>
                </div>

                <div class="detail-item full-row">
                    <div class="detail-icon"><i class="fas fa-home"></i></div>
                    <div class="detail-content">
                        <span class="detail-label">Alamat Rumah</span>
                        <p class="detail-value"><?= htmlspecialchars($data['alamat_siswa'] ?? '-') ?></p>
                    </div>
                </div>
            </div>

            <div class="mt-5 text-center">
                <button type="button" class="btn-update-biodata" data-bs-toggle="modal" data-bs-target="#modalEditProfil">
                    <i class="fas fa-user-edit me-2"></i> PERBARUI BIODATA
                </button>
                <br>
                <a href="cetak-id-card.php" class="btn-id-card">
                    <i class="fas fa-id-card me-2"></i> LIHAT ID CARD
                </a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditProfil" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0" style="border-radius: 20px;">
            <div class="modal-header border-0 pb-0">
                <h4 class="fw-bold mb-0 ms-2 mt-2" style="color: var(--text-dark);">Edit Profil Siswa</h4>
                <button type="button" class="btn-close me-2 mt-2" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <div class="row align-items-center">
                        <div class="col-12 col-md-4 text-center mb-4 mb-md-0">
                            <div class="p-3 rounded-4" style="background: #f8fafc; border: 1px dashed #cbd5e1;">
                                <img src="<?= $foto_path ?>" id="preview" class="mb-3 shadow-sm" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid white;">
                                <input type="file" name="foto_profil" class="form-control form-control-sm" accept="image/*" onchange="previewImg(this)">
                                <small class="text-muted d-block mt-2">Format: JPG, PNG (Maks 2MB)</small>
                            </div>
                        </div>
                        <div class="col-12 col-md-8">
                            <div class="mb-3">
                                <label class="small fw-bold text-muted mb-1">Email Aktif</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($data['email'] ?? '') ?>" required style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 10px; padding: 12px 15px;">
                            </div>
                            <div class="mb-3">
                                <label class="small fw-bold text-muted mb-1">Nomor WhatsApp</label>
                                <input type="text" name="no_hp" class="form-control" value="<?= htmlspecialchars($data['no_hp'] ?? '') ?>" required style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 10px; padding: 12px 15px;">
                            </div>
                            <div class="mb-2">
                                <label class="small fw-bold text-muted mb-1">Alamat Lengkap</label>
                                <textarea name="alamat" class="form-control" rows="3" required style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 10px; padding: 12px 15px;"><?= htmlspecialchars($data['alamat_siswa'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="update_profil" class="btn btn-primary rounded-pill px-5 fw-bold" style="background: var(--primary-grad); border:none;">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function previewImg(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) { document.getElementById('preview').src = e.target.result; }
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>

<?php include 'includes/footer.php'; ?>