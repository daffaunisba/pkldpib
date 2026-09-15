<?php
// admin/guru-edit.php
include 'auth-check.php'; 
include '../config/db-koneksi.php';

$current_user = isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin';
$message = '';
$guru_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$guru_data = null;

// --- LOGIKA UPDATE GURU ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $guru_id_post = (int)$_POST['guru_id'];
    $nama_guru = trim($_POST['nama_guru']);
    $no_hp = trim($_POST['no_hp']); 
    
    // Validasi: Nama tidak kosong DAN No HP adalah angka
    if (!empty($nama_guru) && is_numeric($no_hp)) {
        
        $update_stmt = $koneksi->prepare("UPDATE guru SET nama_guru = ?, no_hp = ? WHERE guru_id = ?"); 
        $update_stmt->bind_param("ssi", $nama_guru, $no_hp, $guru_id_post); 
        
        if ($update_stmt->execute()) {
            $message = "<div class='alert success'>✅ Data guru " . htmlspecialchars($nama_guru) . " berhasil diperbarui!</div>";
        } else {
            $message = "<div class='alert error'>Gagal memperbarui data. Error: " . $update_stmt->error . "</div>";
        }
        $update_stmt->close();
        
        $guru_id = $guru_id_post; // Refresh data state
    } else {
        $message = "<div class='alert warning'>Nama Guru harus diisi, dan No HP harus berupa angka.</div>";
    }
}

// --- AMBIL DATA GURU SAAT INI ---
if ($guru_id > 0) {
    $guru_stmt = $koneksi->prepare("SELECT guru_id, nama_guru, no_hp FROM guru WHERE guru_id = ?");
    $guru_stmt->bind_param("i", $guru_id);
    $guru_stmt->execute();
    $result = $guru_stmt->get_result();

    if ($result->num_rows > 0) {
        $guru_data = $result->fetch_assoc();
    } else {
        $message = "<div class='alert error'>Guru tidak ditemukan.</div>";
    }
    $guru_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin - Edit Guru Pembimbing | Si Mantap PKL</title>
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

        .alert { padding: 12px 15px; margin-bottom: 20px; border-radius: 8px; border: 1px solid; font-size: 14px; font-weight: 500; }
        .alert.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .alert.warning { background-color: #fff3cd; color: #856404; border-color: #ffeeba; }
        
        /* CARD PANEL FORM CONTAINER */
        .form-edit-container {
            background-color: white;
            padding: 30px;
            border-radius: 16px;
            max-width: 650px;
            width: 100%;
            border: 2px solid #e2e8f0;
            box-sizing: border-box;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        }

        .form-edit-container label { 
            display: block; 
            margin-bottom: 6px; 
            font-weight: 600; 
            font-size: 13.5px; 
            color: #0f172a; 
        }
        
        .form-edit-container input[type="text"] {
            width: 100%;
            padding: 11px 14px;
            margin-bottom: 16px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 13.5px;
            box-sizing: border-box;
            background-color: #f8fafc;
        }
        .form-edit-container input:focus { outline: none; border-color: #1e40af; background-color: white; }

        /* BUTTONS STYLING */
        .btn-submit-save {
            padding: 11px 24px !important;
            font-weight: 600 !important;
            background-color: var(--mantap-blue-main);
            color: white;
            border: none;
            border-radius: 20px;
            font-size: 13px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            box-shadow: 0 4px 6px -1px rgba(30, 64, 175, 0.2);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-submit-save:hover { background-color: var(--mantap-blue-light); }

        .btn-danger-link {
            color: #dc3545;
            text-decoration: none;
            font-weight: 600;
            font-size: 13.5px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            margin-top: 20px;
        }
        .btn-danger-link:hover { color: #ef4444; text-decoration: underline; }
        
        .sidebar-nav ul, .sidebar-nav li, .dropdown-menu, .dropdown-menu li { list-style: none !important; margin: 0; padding: 0; }

        /* =========================================================================
           RESPONSIVE VIEWPORT HANDPHONE (HP)
        ========================================================================= */
        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 

            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; gap: 10px !important; padding: 5px 0px !important; }
            .page-header-controls h1 { font-size: 1.4rem !important; }
            
            .form-edit-container { padding: 20px 14px !important; border-radius: 12px !important; }
            .form-edit-container input { font-size: 13.5px !important; padding: 10px !important; }
            
            .form-edit-container button.btn-submit-save { width: 100% !important; justify-content: center !important; padding: 12px !important; border-radius: 8px !important; font-size: 14px !important; }
            .btn-back-link { font-size: 12.5px !important; margin-top: 5px; }
            .danger-zone-mobile { width: 100% !important; text-align: center; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; ?>

<div class="main-content-wrapper">

    <?php include 'panel/navbar.php'; ?>

    <div class="admin-main-content"> 
        <div class="page-header-controls">
            <h1>Edit Data Guru Pembimbing</h1>
            <a href="guru-list.php" class="btn-back-link">
                <i class="fas fa-arrow-left"></i> Kembali ke Daftar Guru
            </a>
        </div>
        
        <?php echo $message; ?>

        <?php if ($guru_data): ?>
        <div class="form-edit-container">
            <form method="POST" action="guru-edit.php?id=<?php echo $guru_data['guru_id']; ?>" id="form_edit_guru">
                <input type="hidden" name="guru_id" value="<?php echo $guru_data['guru_id']; ?>">

                <label for="nama_guru">Nama Lengkap Guru:</label>
                <input type="text" id="nama_guru" name="nama_guru" value="<?php echo htmlspecialchars($guru_data['nama_guru']); ?>" required>

                <label for="no_hp">No. HP (WhatsApp):</label>
                <input type="text" id="no_hp" name="no_hp" value="<?php echo htmlspecialchars($guru_data['no_hp']); ?>" required pattern="\d+" title="Hanya boleh angka. Contoh: 0812xxxxxxxx"> 
                
                <hr style="border: 0; border-top: 2px dashed #e2e8f0; margin: 20px 0;">
                
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; width: 100%;">
                    <button type="submit" class="btn-submit-save">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                    
                    <div class="danger-zone-mobile">
                        <button type="button" class="btn-danger-link" onclick="konfirmasiHapusGuru('<?php echo $guru_data['guru_id']; ?>')">
                            <i class="fas fa-trash-alt"></i> Hapus Akun Guru
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
    // Fungsi SweetAlert2 Terstandarisasi untuk Validasi Penghapusan Akun Pendidik
    function konfirmasiHapusGuru(id) {
        Swal.fire({
            title: 'Hapus Guru Pembimbing?',
            text: "PERINGATAN: Lokasi instansi PKL yang dibimbing oleh guru ini otomatis akan dikosongkan (Set: Belum Ditentukan).",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `guru-delete.php?id=${id}`;
            }
        });
    }
</script>

<?php include 'panel/footer.php'; ?>
</body>
</html>