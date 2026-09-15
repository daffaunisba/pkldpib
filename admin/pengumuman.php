<?php
// admin/pengumuman.php
include 'auth-check.php';
include '../config/db-koneksi.php';

$current_user_level = $_SESSION['level'] ?? 'user'; 
$current_username = $_SESSION['username'] ?? 'Admin';
$current_user_id = $_SESSION['user_id'] ?? 0; // Ditambahkan untuk kebutuhan log
$message = '';

if ($current_user_level !== 'admin') {
    die("<div class='alert error'>Akses Ditolak. Hanya Administrator yang diizinkan.</div>");
}

// ---------------------------------------------------------------------
// 1. HAPUS PENGUMUMAN
// ---------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_del = (int)$_GET['id'];
    
    // --- AMBIL JUDUL PENGUMUMAN SEBELUM DIHAPUS UNTUK LOG ---
    $judul_dihapus = "ID " . $id_del;
    $cek_judul = $koneksi->query("SELECT judul FROM pengumuman WHERE id_pengumuman = $id_del");
    if ($cek_judul && $cek_judul->num_rows > 0) {
        $judul_dihapus = $cek_judul->fetch_assoc()['judul'];
    }

    $stmt_del = $koneksi->prepare("DELETE FROM pengumuman WHERE id_pengumuman = ?");
    $stmt_del->bind_param("i", $id_del);
    if ($stmt_del->execute()) {
        // Hapus juga log baca agar database tetap bersih
        $koneksi->query("DELETE FROM pengumuman_baca WHERE id_pengumuman = $id_del");
        
        // --- TRIGGER LOG AKTIVITAS (DELETE) ---
        catatLog($koneksi, $current_user_id, "Menghapus permanen pengumuman: " . $judul_dihapus);

        header("Location: pengumuman.php?status=deleted");
        exit();
    }
}
if (isset($_GET['status']) && $_GET['status'] == 'deleted') {
    $message = "<div class='alert success'>✅ Pengumuman berhasil dihapus.</div>";
}

// ---------------------------------------------------------------------
// 2. SIMPAN / UPDATE PENGUMUMAN
// ---------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_pengumuman'])) {
    $id_pengumuman = (int)$_POST['id_pengumuman'];
    $judul = trim($_POST['judul']);
    $isi = trim($_POST['isi']);

    if (empty($judul) || empty($isi)) {
        $message = "<div class='alert error'>❌ Judul dan Isi pengumuman wajib diisi.</div>";
    } else {
        if ($id_pengumuman > 0) {
            // --- DETEKSI PERUBAHAN DATA UNTUK LOG SUPER DETAIL ---
            $stmt_old = $koneksi->prepare("SELECT judul, isi FROM pengumuman WHERE id_pengumuman = ?");
            $stmt_old->bind_param("i", $id_pengumuman);
            $stmt_old->execute();
            $old_data = $stmt_old->get_result()->fetch_assoc();
            $stmt_old->close();

            $perubahan = [];
            if ($old_data['judul'] != $judul) $perubahan[] = "Judul Pengumuman";
            if ($old_data['isi'] != $isi) $perubahan[] = "Isi Pengumuman";

            $pesan_log = "";
            if (count($perubahan) > 0) {
                $detail_ubah = implode(", ", $perubahan);
                $pesan_log = "Memperbarui pengumuman: " . $old_data['judul'] . " (Detail yang diubah: " . $detail_ubah . ")";
            } else {
                $pesan_log = "Menyimpan ulang pengumuman: " . $old_data['judul'] . " (Tanpa perubahan data)";
            }
            // ------------------------------------------

            $stmt = $koneksi->prepare("UPDATE pengumuman SET judul=?, isi=? WHERE id_pengumuman=?");
            $stmt->bind_param("ssi", $judul, $isi, $id_pengumuman);
            
            if ($stmt->execute()) {
                // --- TRIGGER LOG AKTIVITAS (UPDATE) ---
                catatLog($koneksi, $current_user_id, $pesan_log);
                $message = "<div class='alert success'>✅ Pengumuman berhasil diperbarui.</div>";
            } else {
                $message = "<div class='alert error'>❌ Gagal menyimpan: " . $koneksi->error . "</div>";
            }
            $stmt->close();

        } else {
            // --- PROSES INSERT (PENGUMUMAN BARU) ---
            $stmt = $koneksi->prepare("INSERT INTO pengumuman (judul, isi, author) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $judul, $isi, $current_username);
            
            if ($stmt->execute()) {
                // --- TRIGGER LOG AKTIVITAS (ADD) ---
                catatLog($koneksi, $current_user_id, "Menyiarkan pengumuman baru: " . $judul);
                $message = "<div class='alert success'>✅ Pengumuman berhasil disiarkan ke siswa.</div>";
            } else {
                $message = "<div class='alert error'>❌ Gagal menyimpan: " . $koneksi->error . "</div>";
            }
            $stmt->close();
        }
    }
}

// ---------------------------------------------------------------------
// 3. AMBIL DATA PENGUMUMAN BESERTA NAMA SISWA YANG MEMBACA
// ---------------------------------------------------------------------
$data_pengumuman = $koneksi->query("
    SELECT p.*, 
           (SELECT COUNT(*) FROM pengumuman_baca WHERE id_pengumuman = p.id_pengumuman) as total_dibaca,
           (SELECT GROUP_CONCAT(pd.nama SEPARATOR ', ') 
            FROM pengumuman_baca pb 
            JOIN peserta_didik pd ON pb.siswa_id = pd.id 
            WHERE pb.id_pengumuman = p.id_pengumuman) as nama_pembaca
    FROM pengumuman p 
    ORDER BY p.tanggal DESC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Manajemen Pengumuman | Si Mantap PKL</title>
    <link rel="stylesheet" href="../css/style.css"> 
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { 
            --mantap-blue-dark: #0f172a; 
            --mantap-blue-main: #1e40af; 
            --mantap-blue-light: #3b82f6; 
            --mantap-blue-soft: #eff6ff;
        }
        body { font-family: 'Poppins', sans-serif; background-color: #f8fafc; color: #334155; margin: 0; overflow-x: hidden !important; }
        .main-content-wrapper { max-width: 100% !important; width: 100% !important; box-sizing: border-box !important; }
        .admin-main-content { padding: 20px 25px 30px 25px !important; box-sizing: border-box !important; }

        .page-header-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .page-header-controls h1 { font-weight: 700; color: #0f172a; font-size: 1.8rem; margin: 0; position: relative; }
        .page-header-controls h1::after { content: ''; position: absolute; left: 0; bottom: -5px; width: 50px; height: 4px; background: var(--mantap-blue-main); border-radius: 2px; }

        .btn-add { background-color: #22c55e; color: white; padding: 10px 20px; border: none; border-radius: 25px; cursor: pointer; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; gap: 6px; box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.15); transition: 0.2s;}
        .btn-add:hover { background-color: #16a34a; transform: translateY(-1px); }

        .alert { padding: 12px 15px; margin-bottom: 20px; border-radius: 8px; border: 1px solid; font-size: 13.5px; font-weight: 500; }
        .alert.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert.error { background-color: #fef2f2; color: #ef4444; border-color: #fecaca; }

        .glass-panel-table { background: white; padding: 25px; border-radius: 16px; border: 2px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); }
        .table-container-fixed { width: 100%; overflow-x: auto; box-sizing: border-box; border: none !important; }

        .custom-table-core { width: 100%; table-layout: fixed; border-collapse: collapse; border: 2px solid var(--mantap-blue-main); border-radius: 4px; min-width: 950px; overflow: hidden; }
        .custom-table-core th { background: var(--mantap-blue-main); color: white; padding: 14px 10px; font-size: 12px; font-weight: 700; text-align: center; border: 1px solid #cbd5e1; }
        .custom-table-core td { padding: 12px 10px; border: 1px solid #e2e8f0; font-size: 13px; vertical-align: top; }
        .custom-table-core tbody tr:hover td { background-color: #f8fafc !important; }

        .custom-table-core th.col-no { width: 50px; }
        .custom-table-core th.col-tgl { width: 12%; }
        .custom-table-core th.col-judul { width: 23%; text-align: left; padding-left: 15px; }
        .custom-table-core th.col-isi { width: 24%; text-align: left; padding-left: 15px; }
        .custom-table-core th.col-baca { width: 28%; text-align: left; padding-left: 15px; }
        .custom-table-core th.col-aksi { width: 90px; }

        .btn-edit-inline { background-color: #f59e0b; color: white !important; padding: 6px 12px; border-radius: 4px; font-size: 11px; font-weight: 600; border: none; cursor: pointer; width:100%; margin-bottom: 6px; display: flex; align-items: center; justify-content: center; gap: 4px; transition: 0.2s;}
        .btn-edit-inline:hover { background-color: #d97706; }
        .btn-delete-inline { background-color: #ef4444; color: white !important; padding: 6px 12px; border-radius: 4px; font-size: 11px; font-weight: 600; border: none; cursor: pointer; width:100%; display: flex; align-items: center; justify-content: center; gap: 4px; transition: 0.2s;}
        .btn-delete-inline:hover { background-color: #dc2626; }

        /* =========================================================
           DESAIN BARU LOG MEMBACA (LEBIH MODERN & RAPI)
           ========================================================= */
        .log-baca-header {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background-color: #ecfdf5;
            color: #059669;
            padding: 5px 10px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 11.5px;
            border: 1px solid #a7f3d0;
            margin-bottom: 8px;
        }

        .reader-container {
            max-height: 110px; /* Batasi tinggi agar tabel tidak terlalu panjang ke bawah */
            overflow-y: auto;
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            padding-top: 8px;
            border-top: 1px dashed #cbd5e1;
            
            /* Styling Scrollbar */
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 transparent;
        }

        /* Webkit Scrollbar untuk Chrome/Safari/Edge */
        .reader-container::-webkit-scrollbar { width: 5px; }
        .reader-container::-webkit-scrollbar-track { background: transparent; }
        .reader-container::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }

        .reader-chip {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 10px;
            font-weight: 500;
            padding: 4px 8px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
            white-space: nowrap; /* Mencegah nama terpotong dua baris */
        }
        .reader-chip i { font-size: 9px; color: #94a3b8; }
        .reader-chip:hover {
            background-color: var(--mantap-blue-soft);
            color: var(--mantap-blue-main);
            border-color: #bfdbfe;
        }
        /* ========================================================= */

        /* MODAL */
        .mantap-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.6); z-index: 9999; backdrop-filter: blur(3px); overflow-y: auto; }
        .mantap-modal-dialog { width: 90%; max-width: 650px; margin: 3rem auto; }
        .mantap-modal-content { background: white; padding: 25px; border-radius: 16px; border: 2px solid #cbd5e1; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15); }
        .mantap-modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 20px;}
        .mantap-modal-header h5 { font-size: 1.3rem; margin: 0; font-weight: 700; color: #0f172a; }
        .close-modal-btn { background: none; border: none; font-size: 1.7rem; cursor: pointer; color: #64748b; }
        
        .mantap-modal-body label { display: block; font-weight: 600; font-size: 13.5px; margin-bottom: 6px; color: #0f172a; }
        .mantap-modal-body input, .mantap-modal-body textarea { width: 100%; padding: 11px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 13px; margin-bottom: 15px; box-sizing: border-box; background-color: #f8fafc;}
        .mantap-modal-body input:focus, .mantap-modal-body textarea:focus { outline: none; border-color: var(--mantap-blue-main); background-color: white;}
        .btn-modal-submit { background: var(--mantap-blue-main); color: white; padding: 12px; border: none; border-radius: 8px; font-weight: 600; width: 100%; cursor: pointer; font-family: 'Poppins', sans-serif; font-size: 13.5px; display: flex; justify-content: center; align-items: center; gap: 6px; box-shadow: 0 4px 6px -1px rgba(30, 64, 175, 0.15);}
        .btn-modal-submit:hover { background: var(--mantap-blue-light); }

        /* =========================================================================
           RESPONSIVE VIEWPORT SMARTPHONE (HP)
           ========================================================================= */
        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 

            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; padding: 5px 0px !important; }
            .page-header-controls h1 { font-size: 1.4rem !important; }
            .btn-add { width: 100% !important; padding: 12px !important; border-radius: 8px !important; font-size: 13.5px !important; }

            .glass-panel-table { padding: 16px 10px !important; border-radius: 12px !important; border: 2px solid #cbd5e1 !important; }
            .table-container-fixed { display: block !important; width: 100% !important; overflow-x: auto !important; -webkit-overflow-scrolling: touch; }
            
            .custom-table-core { table-layout: auto !important; min-width: 850px !important; }
            .custom-table-core th, .custom-table-core td { padding: 12px 10px !important; font-size: 13px !important; }
            .custom-table-core th.col-no, .custom-table-core th.col-tgl, .custom-table-core th.col-judul, .custom-table-core th.col-isi, .custom-table-core th.col-baca, .custom-table-core th.col-aksi { width: auto !important; }
            
            .mantap-modal-dialog { margin: 1.5rem auto; width: 95%; }
            .btn-edit-inline, .btn-delete-inline { padding: 8px !important; font-size: 11.5px !important; margin-bottom: 6px !important; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; include 'panel/navbar.php'; ?>

<div class="main-content-wrapper">
    <div class="admin-main-content"> 
        <div class="page-header-controls">
            <h1>Siaran Pengumuman</h1>
            <button class="btn-add" onclick="openModalAdd()"><i class="fas fa-bullhorn"></i> Buat Info Baru</button>
        </div>

        <?php echo $message; ?>
        
        <div class="glass-panel-table">
            <div class="table-container-fixed">
                <table class="custom-table-core">
                    <thead>
                        <tr>
                            <th class="col-no">NO</th>
                            <th class="col-tgl">TANGGAL</th>
                            <th class="col-judul">JUDUL PENGUMUMAN</th>
                            <th class="col-isi">ISI PENGUMUMAN</th>
                            <th class="col-baca"><i class="fas fa-eye"></i> LOG MEMBACA</th>
                            <th class="col-aksi">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($data_pengumuman && $data_pengumuman->num_rows > 0): ?>
                            <?php $no = 1; while ($row = $data_pengumuman->fetch_assoc()): ?>
                            <tr>
                                <td style="text-align: center; font-weight: bold; color: #64748b;"><?= $no++; ?></td>
                                <td style="text-align: center;">
                                    <strong style="color: var(--mantap-blue-dark); font-size: 12.5px;"><?= date('d M Y', strtotime($row['tanggal'])); ?></strong><br>
                                    <span style="font-size:11px; font-weight:600; color:#ef4444;"><i class="far fa-clock"></i> <?= date('H:i', strtotime($row['tanggal'])); ?> WIB</span>
                                </td>
                                <td style="font-weight: 700; color: var(--mantap-blue-main); font-size: 13.5px;">
                                    <?= htmlspecialchars($row['judul']); ?>
                                </td>
                                
                                <td style="color: #475569; font-size: 12.5px; line-height: 1.5; white-space: pre-wrap; word-break: break-word;">
                                    <?= htmlspecialchars($row['isi']); ?>
                                </td>
                                
                                <td style="text-align: left; vertical-align: top;">
                                    <div class="log-baca-header">
                                        <i class="fas fa-check-double"></i> <?= $row['total_dibaca']; ?> Siswa Membaca
                                    </div>
                                    
                                    <?php if (!empty($row['nama_pembaca'])): ?>
                                        <div class="reader-container">
                                            <?php 
                                            // Memecah nama dan menampilkannya sebagai chip/badge
                                            $nama_array = explode(', ', $row['nama_pembaca']);
                                            foreach ($nama_array as $nama) {
                                                echo '<span class="reader-chip" title="'.htmlspecialchars($nama).'"><i class="fas fa-user"></i> ' . htmlspecialchars($nama) . '</span>';
                                            }
                                            ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="font-size: 11px; color: #94a3b8; margin-top: 5px; font-style: italic; display: flex; align-items: center; gap: 5px; font-weight: 500;">
                                            <i class="fas fa-eye-slash"></i> Belum ada yang membaca
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center; vertical-align: middle;">
                                    <!-- MENGGUNAKAN DATA ATTRIBUTES UNTUK MENGHINDARI ERROR JS -->
                                    <button class="btn-edit-inline btn-edit-trigger" 
                                            data-id="<?= $row['id_pengumuman']; ?>" 
                                            data-judul="<?= htmlspecialchars($row['judul'], ENT_QUOTES); ?>" 
                                            data-isi="<?= htmlspecialchars($row['isi'], ENT_QUOTES); ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    
                                    <button class="btn-delete-inline" onclick="hapusPengumuman('<?= $row['id_pengumuman']; ?>')"><i class="fas fa-trash-alt"></i> Hapus</button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align: center; color: #ef4444; font-style: italic; padding: 25px; font-weight: 500;"><i class="fas fa-folder-open mb-2 fa-lg" style="display:block;"></i> Belum ada pengumuman yang disiarkan.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="mantap-modal" id="modalPengumuman">
    <div class="mantap-modal-dialog">
        <div class="mantap-modal-content">
            <div class="mantap-modal-header">
                <h5 id="modalTitle"><i class="fas fa-bullhorn" style="color: var(--mantap-blue-main); margin-right: 6px;"></i> Form Pengumuman</h5>
                <button type="button" class="close-modal-btn" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" action="pengumuman.php">
                <div class="mantap-modal-body">
                    <input type="hidden" name="submit_pengumuman" value="1">
                    <input type="hidden" name="id_pengumuman" id="id_pengumuman" value="0">

                    <label>Judul Pengumuman</label>
                    <input type="text" name="judul" id="judul" required placeholder="Contoh: Info Pelaksanaan Sidang Gelombang 1">

                    <label>Isi Pesan Lengkap</label>
                    <textarea name="isi" id="isi" rows="6" required placeholder="Tuliskan detail pengumuman di sini..."></textarea>
                    
                    <button type="submit" class="btn-modal-submit"><i class="fas fa-paper-plane"></i> Siarkan Pengumuman</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const modal = document.getElementById('modalPengumuman');
    
    function openModalAdd() {
        document.getElementById('id_pengumuman').value = 0;
        document.getElementById('judul').value = '';
        document.getElementById('isi').value = '';
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Buat Pengumuman Baru';
        modal.style.display = 'block';
    }

    // Mengambil data menggunakan Event Listener (Lebih aman dari karakter unik dan enter)
    document.querySelectorAll('.btn-edit-trigger').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const judul = this.getAttribute('data-judul');
            const isi = this.getAttribute('data-isi');
            
            document.getElementById('id_pengumuman').value = id;
            document.getElementById('judul').value = judul;
            document.getElementById('isi').value = isi;
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit" style="color:#f59e0b;"></i> Edit Pengumuman';
            
            modal.style.display = 'block';
        });
    });

    function closeModal() { modal.style.display = 'none'; }
    
    function hapusPengumuman(id) {
        Swal.fire({
            title: 'Hapus Pengumuman?', text: "Data ini dan rekam jejak siswa yang membacanya akan dihapus secara permanen.", icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#dc3545', cancelButtonColor: '#64748b', confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal'
        }).then((res) => { if (res.isConfirmed) window.location.href = `pengumuman.php?action=delete&id=${id}`; });
    }
    
    window.onclick = function(e) { if (e.target == modal) closeModal(); }
</script>

<?php include 'panel/footer.php'; ?>
</body>
</html>