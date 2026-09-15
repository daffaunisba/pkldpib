<?php
// siswa/pengumuman.php
session_start();
include '../config/db-koneksi.php';

if (!isset($_SESSION['siswa_id'])) {
    header("Location: login.php");
    exit();
}

$siswa_id = $_SESSION['siswa_id'];

// 1. TANDAI SEMUA SEBAGAI DIBACA (MARK AS READ)
$query_unread = "
    SELECT id_pengumuman FROM pengumuman 
    WHERE id_pengumuman NOT IN (SELECT id_pengumuman FROM pengumuman_baca WHERE siswa_id = ?)
";
$stmt_unread = $koneksi->prepare($query_unread);
$stmt_unread->bind_param("i", $siswa_id);
$stmt_unread->execute();
$res_unread = $stmt_unread->get_result();

if ($res_unread->num_rows > 0) {
    $stmt_insert_read = $koneksi->prepare("INSERT IGNORE INTO pengumuman_baca (id_pengumuman, siswa_id) VALUES (?, ?)");
    while ($row = $res_unread->fetch_assoc()) {
        $stmt_insert_read->bind_param("ii", $row['id_pengumuman'], $siswa_id);
        $stmt_insert_read->execute();
    }
    $stmt_insert_read->close();
}
$stmt_unread->close();

// 2. AMBIL DAFTAR PENGUMUMAN UNTUK DITAMPILKAN
$query_feed = "SELECT *, IF(DATEDIFF(NOW(), tanggal) <= 3, 1, 0) as is_new FROM pengumuman ORDER BY tanggal DESC";
$feed_data = $koneksi->query($query_feed);

include 'includes/header.php';
?>

<style>
    :root {
        --primary-grad: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --card-radius: 20px;
    }
    .container-wrapper { padding: 1.5rem; width: 100%; overflow-x: hidden; background: #fafbfe; min-height: 80vh;}
    
    .page-header-box { background: var(--primary-grad); color: white; border-radius: var(--card-radius); padding: 2rem; margin-bottom: 2rem; box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2); position: relative; overflow: hidden; }
    .page-header-box::after { content: ''; position: absolute; width: 150px; height: 150px; background: rgba(255, 255, 255, 0.05); border-radius: 50%; right: -30px; top: -30px; }
    
    .announcement-card { background: white; border-radius: 16px; padding: 1.5rem 2rem; margin-bottom: 1.5rem; box-shadow: 0 10px 25px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; border-left: 5px solid #667eea; transition: 0.3s; position: relative;}
    .announcement-card:hover { transform: translateY(-3px); box-shadow: 0 15px 35px rgba(0,0,0,0.06); }
    
    .badge-new { position: absolute; top: 1.5rem; right: 2rem; background: #ef4444; color: white; padding: 4px 12px; font-size: 10px; font-weight: 800; border-radius: 20px; letter-spacing: 1px; }
    
    .a-title { font-size: 1.3rem; font-weight: 700; color: #1e293b; margin-bottom: 5px; padding-right: 50px;}
    .a-meta { font-size: 0.85rem; color: #94a3b8; font-weight: 500; margin-bottom: 15px; display: flex; gap: 15px; align-items: center;}
    .a-content { color: #475569; font-size: 0.95rem; line-height: 1.6; white-space: pre-wrap; }
    
    .empty-state { text-align: center; padding: 3rem; background: white; border-radius: 16px; border: 2px dashed #cbd5e1; }
    .empty-state i { font-size: 3rem; color: #cbd5e1; margin-bottom: 1rem; }
</style>

<div class="container-wrapper">
    <div class="page-header-box">
        <h4 class="fw-bold text-white mb-1"><i class="fas fa-bullhorn me-2"></i> Papan Pengumuman</h4>
        <p class="mb-0 opacity-75 small">Informasi resmi terbaru dari panitia dan hubin sekolah.</p>
    </div>

    <?php if ($feed_data && $feed_data->num_rows > 0): ?>
        <?php while ($row = $feed_data->fetch_assoc()): ?>
            <div class="announcement-card">
                <?php if ($row['is_new'] == 1): ?>
                    <span class="badge-new">NEW</span>
                <?php endif; ?>
                
                <div class="a-title"><?= htmlspecialchars($row['judul']) ?></div>
                <div class="a-meta">
                    <span><i class="far fa-clock me-1"></i> <?= date('d M Y, H:i', strtotime($row['tanggal'])) ?> WIB</span>
                </div>
                <div class="a-content"><?= htmlspecialchars($row['isi']) ?></div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h5 class="text-secondary fw-bold">Belum Ada Pengumuman</h5>
            <p class="text-muted small">Saat ini belum ada informasi terbaru dari sekolah.</p>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>