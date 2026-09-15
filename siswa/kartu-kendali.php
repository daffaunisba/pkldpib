<?php
session_start();
include '../config/db-koneksi.php';

if (!isset($_SESSION['siswa_id'])) {
    header("Location: login.php");
    exit();
}

$siswa_id = $_SESSION['siswa_id'];

// 1. Ambil Definisi Kegiatan (Daftar Tahapan PKL)
$query_def = "SELECT id, urutan, deskripsi FROM kendali_kegiatan_def WHERE is_active = TRUE ORDER BY urutan ASC";
$res_def = $koneksi->query($query_def);

// 2. Ambil Status Kendali milik Siswa ini
$query_status = "SELECT kegiatan_id, status FROM kartu_kendali WHERE siswa_id = '$siswa_id'";
$res_status = $koneksi->query($query_status);

$status_user = [];
$total_selesai = 0;
while ($s = $res_status->fetch_assoc()) {
    $status_user[$s['kegiatan_id']] = $s['status'];
    if ($s['status'] == 1) $total_selesai++;
}

// Hitung Persentase Progres
$total_kegiatan = $res_def->num_rows;
$persen = ($total_kegiatan > 0) ? round(($total_selesai / $total_kegiatan) * 100) : 0;

include 'includes/header.php';
?>

<style>
    /* Menggunakan token desain utama dari halaman biodata untuk menjaga konsistensi */
    :root {
        --primary-grad: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --purple-light: #f0f3ff;
        --purple-accent: #667eea;
        --card-radius: 25px;
        --item-radius: 20px;
    }

    .container-wrapper { 
        padding: 1.5rem; 
        width: 100%;
        overflow-x: hidden;
        background: #fafbfe;
        box-sizing: border-box;
    }
    
    /* Header Section */
    .page-header-box {
        background: var(--primary-grad);
        color: white;
        border-radius: var(--card-radius);
        padding: 2.5rem 2rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
        position: relative;
        overflow: hidden;
    }
    .page-header-box::after {
        content: '';
        position: absolute;
        width: 150px;
        height: 150px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 50%;
        right: -30px;
        top: -30px;
    }

    /* Progress Card */
    .progres-card {
        background: white; 
        border-radius: var(--card-radius); 
        padding: 2rem;
        margin-bottom: 1.5rem; 
        box-shadow: 0 15px 40px rgba(0,0,0,0.03);
        border: 1px solid #f1f5f9;
    }
    .progres-card h5 {
        color: #1e293b;
        font-weight: 800;
    }
    .progress-custom {
        height: 12px; 
        background: #f1f5f9;
        border-radius: 50px; 
        margin-top: 15px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
    }
    .progress-bar-custom {
        background: linear-gradient(90deg, #667eea 0%, #42e695 100%); 
        border-radius: 50px; 
        transition: 1.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Step List */
    .kendali-list { 
        display: flex; 
        flex-direction: column; 
        gap: 1rem; 
    }
    .kendali-item {
        background: white; 
        border-radius: var(--item-radius); 
        padding: 1.2rem 1.5rem;
        display: flex; 
        align-items: center; 
        justify-content: space-between;
        box-shadow: 0 10px 25px rgba(0,0,0,0.01); 
        border: 1px solid #f1f5f9;
        transition: 0.3s;
    }
    .kendali-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 30px rgba(102, 126, 234, 0.06);
    }
    
    /* Styling Sisi Kiri Berdasarkan Status */
    .kendali-item.done { border-left: 6px solid #1cc88a; }
    .kendali-item.pending { border-left: 6px solid #cbd5e1; }

    .step-number {
        width: 38px; 
        height: 38px; 
        border-radius: 50%;
        display: flex; 
        align-items: center; 
        justify-content: center;
        font-weight: 800; 
        margin-right: 15px; 
        font-size: 0.9rem;
        flex-shrink: 0;
    }
    .done .step-number { background: #e3fcef; color: #1cc88a; border: 1px solid #d1f7e8; }
    .pending .step-number { background: #f8fafc; color: #94a3b8; border: 1px solid #e2e8f0; }

    .check-icon { 
        font-size: 1.4rem; 
        flex-shrink: 0;
        margin-left: 10px;
    }
    
    .text-success-custom { color: #1cc88a !important; font-weight: 700; }
    .text-muted-custom { color: #94a3b8 !important; font-weight: 700; }
    .text-muted-light { color: #cbd5e1; }
    
    /* Info Box */
    .tip-box {
        border-radius: var(--item-radius);
        background: var(--purple-light);
        color: #5a67d8;
        border: 1px solid #dcdfe8;
    }

    /* Penyesuaian khusus untuk Mobile / Layar Kecil */
    @media (max-width: 768px) {
        .container-wrapper { padding: 1rem; }
        .page-header-box { padding: 2rem 1.5rem; border-radius: 20px; }
        .progres-card { padding: 1.5rem; border-radius: 20px; }
        .kendali-item { padding: 1rem 1.2rem; border-radius: 15px; }
        
        /* Memperkecil margin agar teks punya ruang lebih luas untuk turun ke bawah */
        .step-number { width: 32px; height: 32px; font-size: 0.85rem; margin-right: 12px; }
    }
</style>

<div class="container-wrapper">
    <!-- Judul Halaman Bertema Ungu Gradasi -->
    <div class="page-header-box">
        <h4 class="fw-bold text-white mb-1">Kartu Kendali Progres 📋</h4>
        <p class="mb-0 opacity-75 small">Pantau kelengkapan berkas administrasi dan tahapan pelaporan PKL Anda.</p>
    </div>

    <!-- Kotak Ringkasan Progres Utama -->
    <div class="progres-card">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-1">Keseluruhan Progres Capaian</h5>
                <p class="mb-0 small text-muted fw-semibold"><?= $total_selesai ?> dari <?= $total_kegiatan ?> tahapan telah diverifikasi</p>
            </div>
            <h2 class="fw-bold mb-0 font-monospace" style="color: #764ba2; font-weight: 800;"><?= $persen ?>%</h2>
        </div>
        <div class="progress progress-custom">
            <div class="progress-bar progress-bar-custom" style="width: <?= $persen ?>%"></div>
        </div>
    </div>

    <!-- Daftar List Tahapan -->
    <div class="kendali-list">
        <?php 
        $res_def->data_seek(0); // Reset pointer baris data
        while ($row = $res_def->fetch_assoc()): 
            $isDone = (isset($status_user[$row['id']]) && $status_user[$row['id']] == 1);
        ?>
            <div class="kendali-item <?= $isDone ? 'done' : 'pending' ?>">
                <div class="d-flex align-items-center" style="flex-grow: 1; margin-right: 10px;">
                    <div class="step-number"><?= $row['urutan'] ?></div>
                    <div style="flex-grow: 1; word-break: break-word;">
                        <!-- Class text-truncate dan overflow hidden dihapus agar teks bebas wrap ke bawah -->
                        <span class="d-block fw-bold <?= $isDone ? 'text-dark' : 'text-secondary' ?>" style="font-size: 0.95rem; line-height: 1.4; margin-bottom: 3px;">
                            <?= htmlspecialchars($row['deskripsi']) ?>
                        </span>
                        <small class="d-block <?= $isDone ? 'text-success-custom' : 'text-muted-custom' ?>" style="font-size: 11px; line-height: 1.3;">
                            <i class="<?= $isDone ? 'fas fa-shield-alt' : 'far fa-clock' ?> me-1"></i>
                            <?= $isDone ? 'Sudah Terverifikasi Pembimbing' : 'Menunggu Penyelesaian' ?>
                        </small>
                    </div>
                </div>
                <div class="check-icon">
                    <?php if ($isDone): ?>
                        <i class="fas fa-check-circle text-success-custom"></i>
                    <?php else: ?>
                        <i class="far fa-circle text-muted-light"></i>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    </div>

    <!-- Catatan Kaki / Info Box -->
    <div class="mt-4 p-3 tip-box shadow-sm d-flex align-items-start gap-2">
        <i class="fas fa-info-circle mt-1" style="font-size: 1rem;"></i> 
        <p class="mb-0 small" style="font-weight: 500; line-height: 1.5;">
            Status lembar kartu kendali ini hanya dapat diperbarui langsung oleh <strong>Admin Utama</strong> atau <strong>Guru Pembimbing Lapangan</strong> setelah Anda mengumpulkan atau menyelesaikan urutan administrasi terkait.
        </p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>