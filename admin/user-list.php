<?php
// admin/user-list.php
include 'auth-check.php'; 
include '../config/db-koneksi.php';

// --- PROTEKSI HALAMAN ---
$user_level = isset($_SESSION['level']) ? strtolower($_SESSION['level']) : '';
$current_username = isset($_SESSION['username']) ? strtolower($_SESSION['username']) : '';

// Hanya Admin atau 'ade' yang bisa masuk
if ($user_level !== 'admin' && $current_username !== 'ade') {
    header("Location: dashboard.php?status=restricted");
    exit();
}

$message = '';
// Ambil data user
$user_data = $koneksi->query("SELECT id, username, full_name, role FROM users ORDER BY username ASC");

if (isset($_GET['status'])) {
    if ($_GET['status'] == 'add_success') $message = "<div class='alert success'>🎉 Data user baru berhasil ditambahkan!</div>";
    elseif ($_GET['status'] == 'edit_success') $message = "<div class='alert success'>✅ Data user berhasil diperbarui!</div>";
    elseif ($_GET['status'] == 'delete_success') $message = "<div class='alert success'>🗑️ User berhasil dihapus!</div>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin - Manajemen User | Si Mantap PKL</title>
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

        .btn-add { 
            background-color: #22c55e; color: white !important; padding: 9px 20px; border: none; border-radius: 25px; cursor: pointer; font-size: 12px; font-weight: 600; font-family: 'Poppins', sans-serif; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.15); text-decoration: none;
        }
        .btn-add:hover { background-color: #16a34a; }

        .alert { padding: 12px 15px; margin-bottom: 20px; border-radius: 8px; border: 1px solid; font-size: 14px; font-weight: 500; text-align: left; }
        .alert.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }

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

        /* OUTLINE KOTAK TEBAL BERWARNA BIRU UTAMA */
        .custom-table-core { 
            width: 100%; table-layout: fixed; border-collapse: collapse; background: white; border-radius: 4px; overflow: hidden; border: 2px solid #1e40af; 
        }
        .custom-table-core th { 
            background: #1e40af; color: white; padding: 14px 6px; font-size: 13px; text-transform: uppercase; font-weight: 700; text-align: center; border: 1px solid #cbd5e1; box-sizing: border-box;
        }

        /* DATA LEBAR KOLOM DESKTOP */
        .custom-table-core th.col-no { width: 60px; }
        .custom-table-core th.col-user { width: 25%; text-align: left; padding-left: 15px; }
        .custom-table-core th.col-nama { width: 35%; text-align: left; padding-left: 15px; }
        .custom-table-core th.col-level { width: 15%; }
        .custom-table-core th.col-aksi { width: 160px; }

        .custom-table-core td { 
            padding: 12px 10px; font-size: 13px; vertical-align: middle; word-wrap: break-word; overflow-wrap: break-word; text-align: center; border: 1px solid #cbd5e1; color: #0f172a; background-color: white !important; box-sizing: border-box;
        }
        .custom-table-core tbody tr:hover td { background-color: #f8fafc !important; }

        .dashboard-btn-group { display: flex; gap: 6px; justify-content: center; }
        .btn-edit-inline { background-color: #f59e0b; color: white !important; padding: 5px 12px; border-radius: 4px; text-decoration: none; font-size: 11px; font-weight: 600; box-shadow: 0 2px 4px rgba(245,158,11,0.15); }
        .btn-edit-inline:hover { background-color: #d97706; }
        .btn-delete-inline { background-color: #dc3545; color: white !important; padding: 5px 12px; border-radius: 4px; text-decoration: none; font-size: 11px; font-weight: 600; box-shadow: 0 2px 4px rgba(220,53,69,0.15); }
        .btn-delete-inline:hover { background-color: #ef4444; }

        .user-role { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; display: inline-block; }
        .role-admin { background-color: var(--mantap-blue-soft); color: var(--mantap-blue-main); }
        .role-editor { background-color: #f1f5f9; color: #475569; }
        .lock-icon { color: #94a3b8; font-size: 11.5px; font-style: italic; font-weight: 500; }

        .sidebar-nav ul, .sidebar-nav li, .dropdown-menu, .dropdown-menu li { list-style: none !important; margin: 0; padding: 0; }

        /* =========================================================================
           RESPONSIVE VIEWPORT SMARTPHONE (HP SCROLLABLE TABLE MODE)
        ========================================================================= */
        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 

            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; padding: 5px 0px !important; }
            .page-header-controls h1 { font-size: 1.4rem !important; }
            .btn-add { width: 100% !important; justify-content: center !important; padding: 11px !important; border-radius: 8px !important; font-size: 13.5px !important; }

            .glass-panel-table { padding: 16px 10px !important; border-radius: 12px !important; border: 2px solid #cbd5e1 !important; }
            
            .table-container-fixed { display: block !important; width: 100% !important; overflow-x: auto !important; }
            .custom-table-core { table-layout: auto !important; min-width: 700px !important; }
            .custom-table-core th, .custom-table-core td { padding: 12px 10px !important; font-size: 13px !important; }
            .custom-table-core th.col-no, .custom-table-core th.col-user, .custom-table-core th.col-nama, .custom-table-core th.col-level, .custom-table-core th.col-aksi { width: auto !important; }
            
            .custom-table-core td:nth-child(2), .custom-table-core td:nth-child(3) { text-align: left !important; padding-left: 10px !important; }
            .btn-edit-inline, .btn-delete-inline { padding: 6px 12px !important; font-size: 11.5px !important; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; ?>

<div class="main-content-wrapper">

    <?php include 'panel/navbar.php'; ?>

    <div class="admin-main-content"> 
        
        <div class="page-header-controls">
            <h1>Manajemen Hak Akses</h1>
            <a href="user-add.php" class="btn-add"><i class="fas fa-user-plus"></i> Tambah User Baru</a>
        </div>

        <?php echo $message; ?>
        
        <div class="glass-panel-table">
            <div class="table-container-fixed">
                <table class="custom-table-core">
                    <thead>
                        <tr>
                            <th class="col-no">NO</th>
                            <th class="col-user" style="text-align: left; padding-left: 15px;">Username</th> 
                            <th class="col-nama" style="text-align: left; padding-left: 15px;">Nama Lengkap Pengguna</th> 
                            <th class="col-level">Level</th>
                            <th class="col-aksi">Aksi Operasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $row_num = 1; while ($user = $user_data->fetch_assoc()): 
                            $role_db = str_replace('pembimbing', 'guru', strtolower($user['role']));
                            $target_username = strtolower($user['username']);
                            $role_class = ($role_db == 'admin') ? 'role-admin' : 'role-editor';

                            // --- LOGIKA CEK WEWENANG ---
                            // Ade TIDAK BOLEH mengedit/menghapus siapapun yang role-nya 'admin'
                            $can_edit = true;
                            if ($current_username === 'ade' && $role_db === 'admin') {
                                $can_edit = false;
                            }
                        ?>
                        <tr>
                            <td style="font-weight: 700; color: #64748b;"><?php echo $row_num++; ?></td>
                            <td style="text-align: left; padding-left: 15px;"><strong style="color: var(--mantap-blue-dark);"><?php echo htmlspecialchars($user['username']); ?></strong></td>
                            <td style="text-align: left; padding-left: 15px; font-weight: 500; color: #475569;"><?php echo htmlspecialchars($user['full_name'] ?? '-'); ?></td> 
                            <td><span class="user-role <?php echo $role_class; ?>"><?php echo htmlspecialchars(strtoupper($role_db)); ?></span></td>
                            <td>
                                <div class="dashboard-btn-group">
                                    <?php if ($can_edit): ?>
                                        <a href="user-edit.php?id=<?php echo $user['id']; ?>" class="btn-edit-inline"><i class="fas fa-edit"></i> Ubah</a>
                                        <button type="button" class="btn-delete-inline" onclick="konfirmasiHapusUser('<?php echo $user['id']; ?>')"><i class="fas fa-trash-alt"></i> Hapus</button>
                                    <?php else: ?>
                                        <span class="lock-icon"><i class="fas fa-lock"></i> Terkunci</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script>
    // Fungsi SweetAlert2 Terstandarisasi untuk Validasi Penghapusan Manajemen Otoritas Akun Staf
    function konfirmasiHapusUser(id) {
        Swal.fire({
            title: 'Hapus Pengguna Ini?',
            text: "Akun pengelola yang terhapus tidak dapat memulihkan hak akses login kembali.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `user-delete.php?id=${id}`;
            }
        });
    }
</script>

<?php include 'panel/footer.php'; ?>
</body>
</html>