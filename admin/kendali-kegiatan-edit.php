<?php
// admin/kendali-kegiatan-edit.php
// Halaman untuk mengelola definisi daftar kegiatan kartu kendali (DINAMIS).

include 'auth-check.php';
include '../config/db-koneksi.php'; 

$current_user_level = isset($_SESSION['level']) ? strtolower($_SESSION['level']) : 'user'; 

// HANYA ADMIN YANG BOLEH MENGEDIT DEFINISI KEGIATAN
if ($current_user_level !== 'admin') {
    die("<div class='alert error'>Akses Ditolak. Hanya Administrator yang dapat mengedit daftar kegiatan.</div>");
}

$current_user = $_SESSION['username'] ?? 'Admin'; 
$message = '';
$edit_id = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
$mode = ($edit_id > 0) ? 'edit' : 'add';
$form_data = ['urutan' => '', 'deskripsi' => '', 'id' => 0];
$page_title = ($mode === 'edit') ? 'Edit Kegiatan' : 'Tambah Kegiatan Baru';

// --- LOGIKA CRUD ---

// A. Handle POST (ADD / UPDATE)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_kegiatan'])) {
    $id_post = (int)$_POST['kegiatan_id'];
    $urutan = (int)$_POST['urutan'];
    $deskripsi = trim($_POST['deskripsi']);
    $post_mode = ($id_post > 0) ? 'edit' : 'add';

    if (empty($urutan) || empty($deskripsi)) {
        $message = "<div class='alert error'>Urutan dan Deskripsi wajib diisi.</div>";
    } else {
        if ($post_mode === 'add') {
            // INSERT
            $stmt = $koneksi->prepare("INSERT INTO kendali_kegiatan_def (urutan, deskripsi) VALUES (?, ?)");
            $stmt = $koneksi->prepare("INSERT INTO kendali_kegiatan_def (urutan, deskripsi) VALUES (?, ?)");
            $stmt->bind_param("is", $urutan, $deskripsi);
        } else {
            // UPDATE
            $stmt = $koneksi->prepare("UPDATE kendali_kegiatan_def SET urutan = ?, deskripsi = ? WHERE id = ?");
            $stmt->bind_param("isi", $urutan, $deskripsi, $id_post);
        }

        if ($stmt->execute()) {
            if ($post_mode === 'add') {
                $message = "<div class='alert success'>✅ Kegiatan baru dengan nomor urut [{$urutan}] berhasil ditambahkan ke sistem.</div>";
                $form_data = ['urutan' => '', 'deskripsi' => '', 'id' => 0];
            }
            if ($post_mode === 'edit') {
                header("Location: kendali-kegiatan-edit.php?msg=update_success");
                exit();
            }
        } else {
            if ($koneksi->errno == 1062) {
                $message = "<div class='alert error'>Gagal menyimpan: Nomor urutan **{$urutan}** sudah digunakan.</div>";
            } else {
                $message = "<div class='alert error'>Gagal menyimpan: " . $koneksi->error . "</div>";
            }
            $form_data = ['urutan' => $urutan, 'deskripsi' => $deskripsi, 'id' => $id_post];
        }
        $stmt->close();
    }
}

// B. Handle DELETE
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    $check_stmt = $koneksi->prepare("SELECT COUNT(*) FROM kartu_kendali WHERE kegiatan_id = ?");
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $count = $check_stmt->get_result()->fetch_array()[0];
    $check_stmt->close();

    if ($count > 0) {
        $message = "<div class='alert error'>❌ Gagal hapus: Ada **{$count}** catatan kendali siswa yang terhubung dengan kegiatan ini. Harap hapus catatan siswa tersebut terlebih dahulu.</div>";
    } else {
        $delete_stmt = $koneksi->prepare("DELETE FROM kendali_kegiatan_def WHERE id = ?");
        $delete_stmt->bind_param("i", $id);
        if ($delete_stmt->execute()) {
            $message = "<div class='alert success'>🗑️ Kegiatan berhasil dihapus dari sistem bimbingan.</div>";
        } else {
            $message = "<div class='alert error'>Gagal menghapus kegiatan dari database.</div>";
        }
        $delete_stmt->close();
    }
}

// C. Handle LOAD FOR EDIT
if ($mode === 'edit' && empty($form_data['urutan'])) {
    $stmt = $koneksi->prepare("SELECT id, urutan, deskripsi FROM kendali_kegiatan_def WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $form_data = $result->fetch_assoc();
    } else {
        $message = "<div class='alert error'>Kegiatan untuk diedit tidak ditemukan.</div>";
        $mode = 'add';
        $edit_id = 0;
    }
    $stmt->close();
}

// D. Cek pesan sukses dari redirect
if (isset($_GET['msg']) && $_GET['msg'] == 'update_success') {
    $message = "<div class='alert success'>✅ Kegiatan berhasil diperbarui.</div>";
}

// Fetch semua kegiatan untuk tabel daftar
$kegiatan_list_data = $koneksi->query("SELECT id, urutan, deskripsi FROM kendali_kegiatan_def ORDER BY urutan ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin - <?php echo $page_title; ?> | Si Mantap PKL</title>
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

        .btn-back {
            color: white !important;
            padding: 8px 16px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            font-size: 12px;
            background-color: #64748b;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08);
        }
        .btn-back:hover { background-color: #475569; }

        .alert { padding: 12px 15px; margin-bottom: 20px; border-radius: 8px; border: 1px solid; font-size: 14px; font-weight: 500; }
        .alert.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb;}
        .alert.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb;}
        
        /* FORM MANAGE STYLING */
        .form-manage-box { 
            background: white !important; 
            padding: 25px !important; 
            border-radius: 16px !important; 
            border: 2px solid #e2e8f0 !important; 
            margin-bottom: 30px; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
            box-sizing: border-box;
        }
        .form-manage-box h2 { 
            margin-top: 0;
            font-size: 1.3rem; 
            font-weight: 700;
            color: var(--mantap-blue-dark);
            border-bottom: 2px solid #f1f5f9; 
            padding-bottom: 12px; 
            margin-bottom: 20px;
        }
        .form-manage-box label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13.5px; color: #0f172a; }
        
        .form-manage-box input[type="number"], 
        .form-manage-box textarea { 
            width: 100%; 
            padding: 11px 14px; 
            margin-bottom: 16px; 
            border: 1px solid #cbd5e1; 
            border-radius: 8px; 
            font-family: 'Poppins', sans-serif;
            font-size: 13.5px;
            box-sizing: border-box; 
            background-color: #f8fafc;
            resize: vertical;
        }
        .form-manage-box input:focus, .form-manage-box textarea:focus { outline: none; border-color: #1e40af; background-color: white; }
        
        /* BUTTON MANAGE ACC */
        .btn-submit { 
            background-color: #22c55e; color: white; padding: 10px 24px; border-radius: 20px; border: none; cursor: pointer; font-weight: 600; font-size: 13px; font-family: 'Poppins', sans-serif; box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.2); display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-submit:hover { background-color: #16a34a; }
        
        .btn-cancel-edit { 
            background-color: #64748b; color: white !important; padding: 10px 24px; border-radius: 20px; text-decoration: none; font-weight: 600; font-size: 13px; margin-left: 8px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        .btn-cancel-edit:hover { background-color: #475569; }
        
        /* GRID MANAGEMENT FORM */
        .form-grid-row { display: flex; gap: 15px; box-sizing: border-box; width: 100%; }
        
        /* TABEL MONITORING UTAMA */
        .table-panel-box {
            background: white !important; padding: 25px !important; border-radius: 16px !important; border: 2px solid #e2e8f0 !important; box-sizing: border-box; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); width: 100%;
        }
        .table-panel-box h2 { margin-top: 0; font-weight: 700; color: var(--mantap-blue-dark); font-size: 1.4rem; margin-bottom: 20px; }

        .table-container-fixed { width: 100%; overflow-x: auto; box-sizing: border-box; border: none !important; }

        .kegiatan-table { 
            width: 100%; table-layout: fixed; border-collapse: collapse; background: white; border-radius: 4px; overflow: hidden; border: 2px solid #1e40af; 
        }
        .kegiatan-table th { 
            background: #1e40af; color: white; padding: 14px 6px; font-size: 13px; text-transform: uppercase; font-weight: 700; text-align: center; border: 1px solid #cbd5e1; box-sizing: border-box;
        }
        
        .kegiatan-table th.col-no { width: 70px; }
        .kegiatan-table th.col-desc { width: 75%; text-align: left; padding-left: 15px; }
        .kegiatan-table th.col-aksi { width: 160px; }

        .kegiatan-table td { 
            padding: 12px 10px; font-size: 13px; vertical-align: middle; word-wrap: break-word; overflow-wrap: break-word; text-align: center; border: 1px solid #cbd5e1; color: #0f172a; background-color: white !important; box-sizing: border-box;
        }
        .kegiatan-table tr:hover td { background-color: #f8fafc !important; }

        /* KELOLA INLINE LINK */
        .aksi-cell-content { display: flex; gap: 6px; justify-content: center; } 
        .btn-edit-inline { background-color: #f59e0b; color: white !important; padding: 5px 10px; border-radius: 4px; text-decoration: none; font-size: 11px; font-weight: 600; box-shadow: 0 2px 4px rgba(245,158,11,0.15); }
        .btn-edit-inline:hover { background-color: #d97706; }
        .btn-delete-inline { background-color: #dc3545; color: white !important; padding: 5px 10px; border-radius: 4px; text-decoration: none; font-size: 11px; font-weight: 600; box-shadow: 0 2px 4px rgba(220,53,69,0.15); }
        .btn-delete-inline:hover { background-color: #ef4444; }

        .sidebar-nav ul, .sidebar-nav li, .dropdown-menu, .dropdown-menu li { list-style: none !important; margin: 0; padding: 0; }

        /* =========================================================================
           RESPONSIVE VIEWPORT SMARTPHONE (HP)
        ========================================================================= */
        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 

            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; padding: 5px 0px !important; }
            .page-header-controls h1 { font-size: 1.4rem !important; }
            .btn-back { width: 100% !important; justify-content: center !important; padding: 11px !important; border-radius: 8px !important; }
            
            .form-manage-box { padding: 18px 14px !important; border-radius: 12px !important; border: 2px solid #cbd5e1 !important; }
            .form-grid-row { flex-direction: column !important; gap: 0px !important; }
            .form-manage-box input[type="number"], .form-manage-box textarea { font-size: 13.5px !important; padding: 10px !important; }
            
            .btn-submit { width: 100% !important; justify-content: center !important; padding: 12px !important; border-radius: 8px !important; font-size: 14px !important; }
            .btn-cancel-edit { width: 100% !important; justify-content: center !important; padding: 12px !important; border-radius: 8px !important; font-size: 14px !important; margin-left: 0 !important; margin-top: 10px; }

            .table-panel-box { padding: 16px 10px !important; border-radius: 12px !important; border: 2px solid #cbd5e1 !important; }
            .table-panel-box h2 { font-size: 1.2rem !important; }

            .table-container-fixed { display: block !important; width: 100% !important; overflow-x: auto !important; }
            .kegiatan-table { table-layout: auto !important; min-width: 650px !important; }
            .kegiatan-table th, .kegiatan-table td { padding: 12px 10px !important; }
            .kegiatan-table th.col-no, .kegiatan-table th.col-desc, .kegiatan-table th.col-aksi { width: auto !important; }
            .kegiatan-table td:nth-child(2) { text-align: left !important; padding-left: 10px !important; }
            
            .btn-edit-inline, .btn-delete-inline { padding: 6px 10px !important; font-size: 11.5px !important; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; ?>

<div class="main-content-wrapper">

    <?php include 'panel/navbar.php'; ?>

    <div class="admin-main-content"> 
        
        <div class="page-header-controls">
            <h1>Kelola Definisi Kegiatan Kartu Kendali</h1>
            <a href="kartu-kendali.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali ke Daftar Siswa Kendali</a>
        </div>
        
        <p style="font-size: 13.5px; color: #64748b; margin-top: -15px; margin-bottom: 25px; line-height: 1.5;">Halaman ini digunakan untuk menentukan daftar checklist kegiatan Pra-PKL, Selama PKL, dan Pasca-PKL. Daftar ini akan muncul secara otomatis di kartu kendali progres setiap siswa.</p>
        
        <?php echo $message; ?>
        
        <div class="form-manage-box">
            <h2><i class="fas fa-<?php echo ($mode === 'edit' ? 'edit' : 'plus-circle'); ?>" style="color: var(--mantap-blue-main); margin-right: 4px;"></i> <?php echo $page_title; ?></h2>
            
            <form method="POST" action="kendali-kegiatan-edit.php">
                <input type="hidden" name="submit_kegiatan" value="1">
                <input type="hidden" name="kegiatan_id" value="<?php echo $form_data['id']; ?>">
                
                <div class="form-grid-row">
                    <div style="flex: 0 0 110px;">
                        <label for="urutan">No Urutan:</label>
                        <input type="number" id="urutan" name="urutan" value="<?php echo htmlspecialchars($form_data['urutan']); ?>" min="1" required placeholder="Cth: 1">
                    </div>
                    <div style="flex: 1;">
                        <label for="deskripsi">Deskripsi Item Tahapan Monitoring:</label>
                        <textarea id="deskripsi" name="deskripsi" rows="3" placeholder="Contoh: Penyelesaian dan Validasi Laporan Bab I" required><?php echo htmlspecialchars($form_data['deskripsi']); ?></textarea>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> <?php echo ($mode === 'edit' ? 'Simpan Perubahan' : 'Tambah Item Kegiatan'); ?>
                </button>
                <?php if ($mode === 'edit'): ?>
                     <a href="kendali-kegiatan-edit.php" class="btn-cancel-edit">
                        <i class="fas fa-times"></i> Batal Edit
                     </a>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="table-panel-box">
            <h2>Daftar Kegiatan Kendali Aktif</h2>
            <div class="table-container-fixed">
                <table class="kegiatan-table">
                    <thead>
                        <tr>
                            <th class="col-no">Urutan</th>
                            <th class="col-desc">Deskripsi Tahapan Monitoring</th>
                            <th class="col-aksi">Aksi Operasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($kegiatan_list_data && $kegiatan_list_data->num_rows > 0): ?>
                            <?php while ($row = $kegiatan_list_data->fetch_assoc()): ?>
                            <tr>
                                <td style="font-weight: 700; text-align: center; color: var(--mantap-blue-dark);"><?php echo $row['urutan']; ?></td>
                                <td style="text-align: left; padding-left: 15px; font-weight: 500;"><?php echo htmlspecialchars($row['deskripsi']); ?></td>
                                <td>
                                    <div class="aksi-cell-content">
                                        <a href="kendali-kegiatan-edit.php?edit_id=<?php echo $row['id']; ?>" class="btn-edit-inline">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <button type="button" class="btn-delete-inline" onclick="konfirmasiHapusDef('<?php echo $row['id']; ?>')">
                                            <i class="fas fa-trash"></i> Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="text-align: center; color: #ef4444; font-style: italic; padding: 25px;">Belum ada kegiatan monitoring kartu kendali yang terdaftar dalam sistem.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script>
    // Fungsi SweetAlert2 Terstandarisasi untuk Validasi Penghapusan Skema Kegiatan
    function konfirmasiHapusDef(id) {
        Swal.fire({
            title: 'Hapus Item Kegiatan?',
            text: "Pastikan tidak ada data siswa bimbingan aktif yang sedang terikat dengan tahapan monitoring ini.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `kendali-kegiatan-edit.php?action=delete&id=${id}`;
            }
        });
    }
</script>

<?php include 'panel/footer.php'; ?>
</body>
</html>