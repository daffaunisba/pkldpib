<?php
// admin/kode-surat-manage.php
include 'auth-check.php';
include '../config/db-koneksi.php';

$current_user = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin';
$current_user_id = $_SESSION['user_id'] ?? 0; // Kebutuhan Log
$message = '';

// ---------------------------------------------------------------------
// 1. LOGIKA HAPUS (DELETE)
// ---------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    $check_stmt = $koneksi->prepare("SELECT COUNT(*) FROM surat_keluar_pkl WHERE kode_surat = (SELECT kode FROM klasifikasi_surat WHERE id = ?)");
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_stmt->bind_result($count);
    $check_stmt->fetch();
    $check_stmt->close();

    if ($count > 0) {
        $message = "<div class='alert error'>Gagal menghapus: {$count} arsip surat keluar masih aktif menggunakan klasifikasi kode ini.</div>";
    } else {
        // --- AMBIL KODE & NAMA KLASIFIKASI SEBELUM DIHAPUS UNTUK LOG ---
        $kode_dihapus = "ID " . $id;
        $nama_klasifikasi = "";
        $cek_kode = $koneksi->prepare("SELECT kode, nama_klasifikasi FROM klasifikasi_surat WHERE id = ?");
        $cek_kode->bind_param("i", $id);
        $cek_kode->execute();
        $res_kode = $cek_kode->get_result();
        if ($res_kode && $res_kode->num_rows > 0) {
            $dt_kode = $res_kode->fetch_assoc();
            $kode_dihapus = $dt_kode['kode'];
            $nama_klasifikasi = $dt_kode['nama_klasifikasi'];
        }
        $cek_kode->close();

        $delete_stmt = $koneksi->prepare("DELETE FROM klasifikasi_surat WHERE id = ? AND kode != 'PILIH'");
        $delete_stmt->bind_param("i", $id);
        
        if ($delete_stmt->execute()) {
            // --- TRIGGER LOG AKTIVITAS (DELETE) ---
            catatLog($koneksi, $current_user_id, "Menghapus klasifikasi kode surat: {$kode_dihapus} ({$nama_klasifikasi})");
            header("Location: kode-surat-manage.php?status=success_delete");
            exit;
        } else {
            $message = "<div class='alert error'>Gagal menghapus klasifikasi.</div>";
        }
        $delete_stmt->close();
    }
}

// ---------------------------------------------------------------------
// 2. LOGIKA TAMBAH & EDIT (DARI MODAL POP-UP)
// ---------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_klasifikasi'])) {
    $id_klas = (int)$_POST['id_klasifikasi'];
    $kode = trim($_POST['kode']);
    $nama = trim($_POST['nama_klasifikasi']);

    if (empty($kode) || empty($nama)) {
        $message = "<div class='alert error'>Kode dan Nama Klasifikasi wajib diisi.</div>";
    } else {
        if ($id_klas > 0) {
            // --- PROSES UPDATE ---
            $stmt_old = $koneksi->prepare("SELECT kode, nama_klasifikasi FROM klasifikasi_surat WHERE id = ?");
            $stmt_old->bind_param("i", $id_klas);
            $stmt_old->execute();
            $old_data = $stmt_old->get_result()->fetch_assoc();
            $stmt_old->close();

            $perubahan = [];
            if ($old_data['kode'] != $kode) $perubahan[] = "Kode Surat";
            if ($old_data['nama_klasifikasi'] != $nama) $perubahan[] = "Nama Klasifikasi";

            if (count($perubahan) > 0) {
                $detail_ubah = implode(", ", $perubahan);
                $pesan_log = "Memperbarui klasifikasi surat: " . $old_data['kode'] . " (Detail yang diubah: " . $detail_ubah . ")";

                $stmt_upd = $koneksi->prepare("UPDATE klasifikasi_surat SET kode = ?, nama_klasifikasi = ? WHERE id = ?");
                $stmt_upd->bind_param("ssi", $kode, $nama, $id_klas);
                
                if ($stmt_upd->execute()) {
                    catatLog($koneksi, $current_user_id, $pesan_log); // Log Aktivitas Edit
                    header("Location: kode-surat-manage.php?status=success_edit");
                    exit;
                } else {
                    if ($koneksi->errno == 1062) {
                        header("Location: kode-surat-manage.php?status=error_duplicate");
                    } else {
                        header("Location: kode-surat-manage.php?status=error_db");
                    }
                    exit;
                }
                $stmt_upd->close();
            } else {
                header("Location: kode-surat-manage.php?status=no_change");
                exit;
            }

        } else {
            // --- PROSES INSERT (TAMBAH BARU) ---
            $stmt_ins = $koneksi->prepare("INSERT INTO klasifikasi_surat (kode, nama_klasifikasi) VALUES (?, ?)");
            $stmt_ins->bind_param("ss", $kode, $nama);
            
            if ($stmt_ins->execute()) {
                catatLog($koneksi, $current_user_id, "Menambahkan klasifikasi kode surat baru: {$kode} ({$nama})"); // Log Aktivitas Tambah
                header("Location: kode-surat-manage.php?status=success_add");
                exit;
            } else {
                if ($koneksi->errno == 1062) {
                    header("Location: kode-surat-manage.php?status=error_duplicate");
                } else {
                    header("Location: kode-surat-manage.php?status=error_db");
                }
                exit;
            }
            $stmt_ins->close();
        }
    }
}

// Menangkap Notifikasi dari Redirect
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'success_add') {
        $message = "<div class='alert success'>✅ Klasifikasi baru berhasil ditambahkan.</div>";
    } elseif ($_GET['status'] == 'success_edit') {
        $message = "<div class='alert success'>✅ Data klasifikasi berhasil diperbarui.</div>";
    } elseif ($_GET['status'] == 'success_delete') {
        $message = "<div class='alert success'>🗑️ Klasifikasi kode surat berhasil dihapus.</div>";
    } elseif ($_GET['status'] == 'error_duplicate') {
        $message = "<div class='alert error'>❌ Gagal menyimpan: Kode surat sudah terdaftar.</div>";
    } elseif ($_GET['status'] == 'error_db') {
        $message = "<div class='alert error'>❌ Terjadi kesalahan pada database saat menyimpan data.</div>";
    }
}

// Ambil Data Klasifikasi (Menghindari kode 'PILIH' di list manajemen utama)
$klasifikasi_data = $koneksi->query("SELECT * FROM klasifikasi_surat WHERE kode != 'PILIH' ORDER BY kode ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin - Kelola Kode Surat | Si Mantap PKL</title>
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

        body { 
            font-family: 'Poppins', sans-serif; 
            background-color: #f8fafc; 
            color: #334155; 
            margin: 0;
            overflow-x: hidden !important;
        }

        .main-content-wrapper {
            max-width: 100% !important;
            width: 100% !important;
            box-sizing: border-box !important;
            box-shadow: none !important;
        }

        .admin-main-content {
            padding: 20px 25px 30px 25px !important; 
            box-sizing: border-box !important;
            clear: both;
            width: 100% !important;
        }

        .page-header-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            width: 100%;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-header-controls h1 {
            font-weight: 700;
            color: #0f172a;
            font-size: 1.8rem; 
            margin: 0;
            position: relative;
        }

        .page-header-controls h1::after {
            content: ''; position: absolute; left: 0; bottom: -5px; width: 50px; height: 4px; background: var(--mantap-blue-main); border-radius: 2px;
        }

        .btn-back-link {
            color: var(--mantap-blue-main);
            text-decoration: none;
            font-weight: 600;
            font-size: 13.5px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-back-link:hover { color: var(--mantap-blue-light); }

        .btn-tambah-baru { 
            background-color: #22c55e; color: white !important; padding: 9px 20px; border: none; border-radius: 25px; cursor: pointer; font-size: 12px; font-weight: 600; font-family: 'Poppins', sans-serif; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.15); text-decoration: none;
        }
        .btn-tambah-baru:hover { background-color: #16a34a; }

        .alert { padding: 12px 15px; margin-bottom: 20px; border-radius: 8px; border: 1px solid; font-size: 13.5px; font-weight: 500; text-align: left; }
        .alert.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }

        .glass-panel-table {
            background: white !important; 
            padding: 25px !important; 
            border-radius: 16px !important; 
            border: 2px solid #e2e8f0 !important; 
            box-sizing: border-box; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); 
            width: 100%;
        }

        .table-container-fixed { width: 100%; overflow-x: auto; box-sizing: border-box; border: none !important; }

        .custom-table-core { 
            width: 100%; table-layout: fixed; border-collapse: collapse; background: white; border-radius: 4px; overflow: hidden; border: 2px solid #1e40af; 
        }
        .custom-table-core th { 
            background: #1e40af; color: white; padding: 14px 6px; font-size: 13px; text-transform: uppercase; font-weight: 700; text-align: center; border: 1px solid #cbd5e1; box-sizing: border-box;
        }

        .custom-table-core th.kode-col { width: 15%; }
        .custom-table-core th.nama-col { width: 60%; text-align: left; padding-left: 15px; }
        .custom-table-core th.aksi-col { width: 160px; }

        .custom-table-core td { 
            padding: 12px 10px; font-size: 13px; vertical-align: middle; word-wrap: break-word; overflow-wrap: break-word; text-align: center; border: 1px solid #cbd5e1; color: #0f172a; background-color: white !important; box-sizing: border-box;
        }
        .custom-table-core tbody tr:hover td { background-color: #f8fafc !important; }

        .dashboard-btn-group { display: flex; gap: 6px; justify-content: center; }
        .btn-edit-inline { background-color: #f59e0b; color: white !important; padding: 5px 12px; border-radius: 4px; text-decoration: none; font-size: 11px; font-weight: 600; box-shadow: 0 2px 4px rgba(245,158,11,0.15); border: none; cursor: pointer; font-family: 'Poppins', sans-serif;}
        .btn-edit-inline:hover { background-color: #d97706; }
        .btn-delete-inline { background-color: #dc3545; color: white !important; padding: 5px 12px; border-radius: 4px; text-decoration: none; font-size: 11px; font-weight: 600; box-shadow: 0 2px 4px rgba(220,53,69,0.15); border: none; cursor: pointer; font-family: 'Poppins', sans-serif; }
        .btn-delete-inline:hover { background-color: #ef4444; }

        /* MODAL STYLING */
        .mantap-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.6); z-index: 9999; backdrop-filter: blur(3px); overflow-y: auto; }
        .mantap-modal-dialog { width: 90%; max-width: 550px; margin: 5rem auto; }
        .mantap-modal-content { background: white; padding: 25px; border-radius: 16px; border: 2px solid #cbd5e1; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15); }
        .mantap-modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 20px;}
        .mantap-modal-header h5 { font-size: 1.3rem; margin: 0; font-weight: 700; color: #0f172a; }
        .close-modal-btn { background: none; border: none; font-size: 1.7rem; cursor: pointer; color: #64748b; }
        
        .mantap-modal-body label { display: block; font-weight: 600; font-size: 13.5px; margin-bottom: 6px; color: #0f172a; }
        .mantap-modal-body input { width: 100%; padding: 11px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 13.5px; margin-bottom: 15px; box-sizing: border-box; background-color: #f8fafc;}
        .mantap-modal-body input:focus { outline: none; border-color: var(--mantap-blue-main); background-color: white;}
        .btn-modal-submit { background: var(--mantap-blue-main); color: white; padding: 12px; border: none; border-radius: 8px; font-weight: 600; width: 100%; cursor: pointer; font-family: 'Poppins', sans-serif; font-size: 13.5px; display: flex; justify-content: center; align-items: center; gap: 6px; box-shadow: 0 4px 6px -1px rgba(30, 64, 175, 0.15);}
        .btn-modal-submit:hover { background: var(--mantap-blue-light); }

        .sidebar-nav ul, .sidebar-nav li, .dropdown-menu, .dropdown-menu li { list-style: none !important; margin: 0; padding: 0; }

        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 

            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; padding: 5px 0px !important; }
            .page-header-controls h1 { font-size: 1.4rem !important; }
            .btn-tambah-baru { width: 100% !important; justify-content: center !important; padding: 11px !important; border-radius: 8px !important; font-size: 13.5px !important; }

            .glass-panel-table { padding: 16px 10px !important; border-radius: 12px !important; border: 2px solid #cbd5e1 !important; }
            
            .table-container-fixed { display: block !important; width: 100% !important; overflow-x: auto !important; }
            .custom-table-core { table-layout: auto !important; min-width: 580px !important; }
            .custom-table-core th, .custom-table-core td { padding: 12px 10px !important; font-size: 13px !important; }
            .custom-table-core th.kode-col, .custom-table-core th.nama-col, .custom-table-core th.aksi-col { width: auto !important; }
            
            .custom-table-core td:nth-child(2) { text-align: left !important; padding-left: 10px !important; }
            .btn-edit-inline, .btn-delete-inline { padding: 6px 12px !important; font-size: 11.5px !important; }
            .btn-back-link { font-size: 12.5px !important; margin-top: 5px; }
            .mantap-modal-dialog { margin: 1.5rem auto; width: 95%; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; ?>

<div class="main-content-wrapper">

    <?php include 'panel/navbar.php'; ?>

    <div class="admin-main-content"> 
        
        <div class="page-header-controls">
            <h1>Kelola Klasifikasi Kode Surat</h1>
            <a href="surat-keluar-add.php" class="btn-back-link">
                <i class="fas fa-arrow-left"></i> Kembali ke Tambah Surat Keluar
            </a>
        </div>

        <div style="text-align: right; margin-bottom: 20px;">
            <button type="button" class="btn-tambah-baru" onclick="openModalKlasifikasi()">
                <i class="fas fa-plus"></i> Tambah Klasifikasi Baru
            </button>
        </div>

        <?php echo $message; ?>
        
        <div class="glass-panel-table">
            <div class="table-container-fixed">
                <table class="custom-table-core">
                    <thead>
                        <tr>
                            <th class="kode-col">KODE</th>
                            <th class="nama-col" style="text-align: left; padding-left: 15px;">NAMA KLASIFIKASI AGENDA SURAT</th>
                            <th class="aksi-col">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($klasifikasi_data && $klasifikasi_data->num_rows > 0): ?>
                            <?php while ($row = $klasifikasi_data->fetch_assoc()): ?>
                            <tr>
                                <td style="font-weight: 700; color: var(--mantap-blue-dark);"><?php echo htmlspecialchars($row['kode']); ?></td>
                                <td style="text-align: left; padding-left: 15px; font-weight: 500; color: #475569;"><?php echo htmlspecialchars($row['nama_klasifikasi']); ?></td>
                                <td>
                                    <div class="dashboard-btn-group">
                                        <button type="button" class="btn-edit-inline" 
                                            data-id="<?php echo $row['id']; ?>"
                                            data-kode="<?php echo htmlspecialchars($row['kode'], ENT_QUOTES); ?>"
                                            data-nama="<?php echo htmlspecialchars($row['nama_klasifikasi'], ENT_QUOTES); ?>"
                                            onclick="openModalKlasifikasi(this)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button type="button" class="btn-delete-inline" onclick="konfirmasiHapusKlasifikasi('<?php echo $row['id']; ?>', '<?php echo htmlspecialchars($row['kode'], ENT_QUOTES); ?>')">
                                            <i class="fas fa-trash-alt"></i> Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="text-align: center; color: #ef4444; font-style: italic; padding: 25px;">Belum ada klasifikasi kode surat resmi yang terdaftar di database.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL FORM KLASIFIKASI -->
<div class="mantap-modal" id="modalKlasifikasi">
    <div class="mantap-modal-dialog">
        <div class="mantap-modal-content">
            <div class="mantap-modal-header">
                <h5 id="modalTitle"><i class="fas fa-plus-circle" style="color: var(--mantap-blue-main); margin-right: 6px;"></i> Tambah Klasifikasi Baru</h5>
                <button type="button" class="close-modal-btn" onclick="closeModalKlasifikasi()">&times;</button>
            </div>
            <form method="POST" action="kode-surat-manage.php">
                <div class="mantap-modal-body">
                    <input type="hidden" name="submit_klasifikasi" value="1">
                    <input type="hidden" name="id_klasifikasi" id="id_klasifikasi" value="0">

                    <label for="kode">Kode Klasifikasi (Contoh: 421.1):</label>
                    <input type="text" id="kode" name="kode" maxlength="15" required placeholder="Masukkan kode surat...">

                    <label for="nama_klasifikasi">Nama Klasifikasi Lengkap:</label>
                    <input type="text" id="nama_klasifikasi" name="nama_klasifikasi" required placeholder="Contoh: Surat Pengantar PKL...">
                    
                    <button type="submit" class="btn-modal-submit"><i class="fas fa-save"></i> Simpan Klasifikasi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const modal = document.getElementById('modalKlasifikasi');
    
    // Fungsi Buka Modal Tambah/Edit
    function openModalKlasifikasi(btnElement = null) {
        if (btnElement) {
            // Mode EDIT
            document.getElementById('id_klasifikasi').value = btnElement.getAttribute('data-id');
            document.getElementById('kode').value = btnElement.getAttribute('data-kode');
            document.getElementById('nama_klasifikasi').value = btnElement.getAttribute('data-nama');
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit" style="color: #f59e0b; margin-right: 6px;"></i> Edit Klasifikasi Surat';
        } else {
            // Mode TAMBAH
            document.getElementById('id_klasifikasi').value = '0';
            document.getElementById('kode').value = '';
            document.getElementById('nama_klasifikasi').value = '';
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle" style="color: var(--mantap-blue-main); margin-right: 6px;"></i> Tambah Klasifikasi Baru';
        }
        modal.style.display = 'block';
    }

    function closeModalKlasifikasi() {
        modal.style.display = 'none';
    }

    // Klik luar modal untuk menutup
    window.onclick = function(e) {
        if (e.target == modal) {
            closeModalKlasifikasi();
        }
    }

    // Fungsi SweetAlert2 Terstandarisasi untuk Validasi Penghapusan Skema Klasifikasi Surat
    function konfirmasiHapusKlasifikasi(id, kode) {
        Swal.fire({
            title: `Hapus Klasifikasi ${kode}?`,
            text: "Pastikan tidak ada dokumen arsip surat keluar aktif yang sedang terikat dengan kode klasifikasi ini.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `kode-surat-manage.php?action=delete&id=${id}`;
            }
        });
    }
</script>

<?php include 'panel/footer.php'; ?>
</body>
</html>