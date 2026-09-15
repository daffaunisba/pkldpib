<?php
// admin/penilaian-format.php
include 'auth-check.php';
include '../config/db-koneksi.php';

$current_user_level = $_SESSION['level'] ?? 'user'; 
$message = '';
$edit_id = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
$mode = ($edit_id > 0) ? 'edit' : 'add';
$form_data = ['urutan' => '', 'aspek_id' => '', 'nama_komponen' => '', 'komponen_id' => 0]; // Bobot dihapus

// ---------------------------------------------------------------------
// PERBAIKAN: INISIALISASI $page_title
// ---------------------------------------------------------------------
$page_title = ($mode === 'edit') ? 'Edit Komponen Penilaian' : 'Tambah Komponen Baru';
// ---------------------------------------------------------------------

if ($current_user_level !== 'admin') {
    die("<div class='alert error'>Akses Ditolak. Hanya Administrator yang diizinkan.</div>");
}

// ---------------------------------------------------------------------
// QUERIES UNTUK DROPDOWN (Aspek)
// ---------------------------------------------------------------------
$aspek_options_result = $koneksi->query("SELECT aspek_id, nama_aspek FROM penilaian_aspek ORDER BY urutan ASC");
$aspek_options = $aspek_options_result ? $aspek_options_result->fetch_all(MYSQLI_ASSOC) : [];

// ---------------------------------------------------------------------
// LOGIKA CRUD KOMPONEN PENILAIAN
// ---------------------------------------------------------------------

// A. Handle POST (ADD / UPDATE)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_komponen'])) {
    $id_post = (int)$_POST['komponen_id'];
    $aspek_id = (int)$_POST['aspek_id'];
    $nama_komponen = trim($_POST['nama_komponen']);
    $post_mode = ($id_post > 0) ? 'edit' : 'add';

    // Bobot dihilangkan dari POST, tapi diasumsikan sebagai 0 atau 1
    $bobot = 1; 

    if (empty($nama_komponen) || $aspek_id <= 0) {
        $message = "<div class='alert error'>Aspek dan Komponen wajib diisi.</div>";
    } else {
        // Hitung urutan tertinggi di aspek tersebut
        $stmt_order = $koneksi->prepare("SELECT MAX(urutan) FROM penilaian_komponen WHERE aspek_id = ?");
        $stmt_order->bind_param("i", $aspek_id);
        $stmt_order->execute();
        $max_order = $stmt_order->get_result()->fetch_array()[0] ?? 0;
        $urutan_baru = $max_order + 1;
        $stmt_order->close();

        if ($post_mode === 'add') {
            // Perubahan: Hilangkan kolom bobot dari INSERT
            $stmt = $koneksi->prepare("INSERT INTO penilaian_komponen (aspek_id, urutan, nama_komponen) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $aspek_id, $urutan_baru, $nama_komponen);
        } else {
            // Perubahan: Hilangkan kolom bobot dari UPDATE
            $stmt = $koneksi->prepare("UPDATE penilaian_komponen SET aspek_id = ?, nama_komponen = ? WHERE komponen_id = ?");
            $stmt->bind_param("isi", $aspek_id, $nama_komponen, $id_post);
        }

        if ($stmt->execute()) {
            $message = "<div class='alert success'>✅ Komponen penilaian berhasil di" . ($post_mode === 'add' ? "tambahkan." : "perbarui.") . "</div>";
            if ($post_mode === 'add') {
                $form_data = ['urutan' => '', 'aspek_id' => '', 'nama_komponen' => '', 'komponen_id' => 0];
            }
            if ($post_mode === 'edit') {
                 header("Location: penilaian-format.php?msg=update_success");
                 exit();
            }
        } else {
            $message = "<div class='alert error'>Gagal menyimpan: " . $koneksi->error . "</div>";
            $form_data = ['urutan' => '', 'aspek_id' => $aspek_id, 'nama_komponen' => $nama_komponen, 'komponen_id' => $id_post];
        }
        $stmt->close();
    }
}

// B. Handle DELETE (Dipertahankan)
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $delete_stmt = $koneksi->prepare("DELETE FROM penilaian_komponen WHERE komponen_id = ?");
    $delete_stmt->bind_param("i", $id);
    
    if ($delete_stmt->execute()) {
        $message = "<div class='alert success'>🗑️ Komponen berhasil dihapus.</div>";
    } else {
        $message = "<div class='alert error'>Gagal menghapus komponen.</div>";
    }
    $delete_stmt->close();
}

// C. Handle LOAD FOR EDIT
if ($mode === 'edit' && empty($form_data['nama_komponen'])) {
    // Perubahan: Hilangkan kolom bobot dari SELECT
    $stmt = $koneksi->prepare("SELECT komponen_id, aspek_id, urutan, nama_komponen FROM penilaian_komponen WHERE komponen_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $form_data = $result->fetch_assoc();
        $page_title = 'Edit Komponen Penilaian'; 
    } else {
        $message = "<div class='alert error'>Komponen untuk diedit tidak ditemukan.</div>";
        $mode = 'add';
        $edit_id = 0;
        $page_title = 'Tambah Komponen Baru';
    }
    $stmt->close();
}

// D. Cek pesan sukses dari redirect
if (isset($_GET['msg']) && $_GET['msg'] == 'update_success') {
    $message = "<div class='alert success'>✅ Komponen berhasil diperbarui.</div>";
}

// Fetch semua komponen yang dikelompokkan berdasarkan aspek
// Perubahan: Hilangkan kolom bobot dari SELECT
$komponen_list_data = $koneksi->query("
    SELECT pk.komponen_id, pk.urutan, pk.nama_komponen, pa.nama_aspek, pa.urutan AS aspek_urutan
    FROM penilaian_komponen pk
    JOIN penilaian_aspek pa ON pk.aspek_id = pa.aspek_id
    ORDER BY pa.urutan ASC, pk.urutan ASC
");

// Total bobot sekarang tidak relevan, tapi saya pertahankan variabelnya sebagai 0
$total_bobot = 0; 

// Kelompokkan data untuk tampilan tabel
$grouped_components = [];
if ($komponen_list_data) {
    while ($row = $komponen_list_data->fetch_assoc()) {
        $grouped_components[$row['nama_aspek']][] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin - Kelola Format Penilaian | Si Mantap PKL</title>
    <link rel="stylesheet" href="../css/style.css"> 
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <style>
        :root {
            --mantap-blue-dark: #0f172a;
            --mantap-blue-main: #1e40af;
            --mantap-blue-light: #3b82f6;
            --mantap-blue-soft: #eff6ff;
            --mantap-green: #28a745;
            --mantap-orange: #ff8c00;
            --mantap-orange-hover: #ffa534;
            --table-border: #cbd5e1;
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

        .btn-back { 
            color: var(--mantap-blue-main); 
            text-decoration: none; 
            font-weight: 600; 
            display: inline-flex; 
            align-items: center;
            gap: 8px;
            margin-bottom: 20px; 
            font-size: 14px;
            transition: color 0.2s;
        }
        .btn-back:hover { color: var(--mantap-blue-light); }

        .admin-main-content h1 {
            font-weight: 700;
            color: var(--mantap-blue-dark);
            font-size: 1.8rem; 
            margin: 0 0 25px 0;
            position: relative;
        }
        .admin-main-content h1::after {
            content: ''; position: absolute; left: 0; bottom: -5px; width: 50px; height: 4px; background: var(--mantap-blue-main); border-radius: 2px;
        }

        /* Message Alerts */
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; border: 1px solid; font-size: 14px; }
        .alert.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }

        /* Form Container Box */
        .form-manage-box { 
            background: white; 
            padding: 25px; 
            border-radius: 16px; 
            border: 2px solid #e2e8f0; 
            margin-bottom: 30px; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
            box-sizing: border-box;
        }
        .form-manage-box h2 { 
            font-size: 1.3rem; 
            font-weight: 700;
            color: var(--mantap-blue-dark);
            margin-top: 0;
            padding-bottom: 12px; 
            margin-bottom: 20px; 
            border-bottom: 2px solid #f1f5f9;
        }
        .form-manage-box label { display: block; margin-top: 10px; font-weight: 600; color: #475569; font-size: 14px; }
        
        .form-manage-box input[type="number"], 
        .form-manage-box input[type="text"], 
        .form-manage-box textarea, 
        .form-manage-box select { 
            width: 100%; 
            padding: 11px 14px; 
            margin-top: 6px; 
            margin-bottom: 15px; 
            border-radius: 8px; 
            border: 2px solid #cbd5e1; 
            box-sizing: border-box; 
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            color: var(--mantap-blue-dark);
            transition: border-color 0.2s; 
        }
        .form-manage-box input:focus, .form-manage-box select:focus, .form-manage-box textarea:focus {
            border-color: var(--mantap-blue-light);
            outline: none;
        }
        
        /* Form Layout Flex Group */
        .form-flex-group { display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap; }
        .form-flex-group > div { flex: 2; min-width: 240px; }
        .form-flex-group .input-urutan { flex: 0 0 100px; min-width: 100px; } 

        .btn-submit { 
            background-color: var(--mantap-orange); 
            color: white; 
            padding: 12px 24px; 
            border-radius: 25px; 
            border: none; 
            cursor: pointer; 
            margin-top: 10px; 
            font-weight: 600; 
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08);
            transition: background-color 0.2s;
        }
        .btn-submit:hover { background-color: var(--mantap-orange-hover); }
        
        .btn-cancel-edit { 
            background-color: #64748b; 
            color: white; 
            padding: 12px 24px; 
            border-radius: 25px; 
            text-decoration: none; 
            font-weight: 600; 
            font-size: 13px;
            margin-left: 10px; 
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background-color 0.2s;
        }
        .btn-cancel-edit:hover { background-color: #475569; }

        /* Hierarchical Table Styling */
        .table-responsive-container {
            width: 100%;
            overflow-x: auto;
            box-sizing: border-box;
        }
        .kriteria-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 15px; 
            table-layout: fixed;
            border: 2px solid var(--mantap-blue-main);
        }
        .kriteria-table th.header-aspek { 
            background-color: var(--mantap-blue-main); 
            color: white; 
            padding: 14px 8px; 
            text-align: center; 
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            border: 1px solid var(--table-border);
            box-sizing: border-box;
        }
        .kriteria-table td { 
            padding: 12px 10px; 
            border: 1px solid var(--table-border); 
            font-size: 13.5px; 
            vertical-align: middle;
            color: var(--mantap-blue-dark);
            background-color: white;
            word-wrap: break-word;
            overflow-wrap: break-word;
            box-sizing: border-box;
        }

        /* Aspect Section Divider Header Row */
        .row-aspek-nama { 
            background-color: var(--mantap-blue-soft) !important; 
        }
        .row-aspek-nama td { 
            background-color: #f1f5f9 !important;
            font-weight: 700; 
            color: var(--mantap-blue-main);
            font-size: 14px;
            padding: 14px 15px;
            border-bottom: 2px solid var(--mantap-blue-main);
            text-align: left !important;
        }
        
        /* Inline Component Sub-items custom fonts */
        .component-title-text {
            font-weight: 500;
            color: #334155;
        }

        /* Table Action Utility Buttons */
        .btn-edit-inline { 
            background-color: #ffc107; 
            color: #0f172a !important; 
            padding: 6px 10px; 
            border-radius: 4px; 
            text-decoration: none; 
            font-size: 13px; 
            margin-right: 5px; 
            display: inline-flex;
            align-items: center;
        }
        .btn-delete-inline { 
            background-color: #dc3545; 
            color: white !important; 
            padding: 6px 10px; 
            border-radius: 4px; 
            text-decoration: none; 
            font-size: 13px; 
            display: inline-flex;
            align-items: center;
        }
        .btn-edit-inline:hover, .btn-delete-inline:hover { opacity: 0.9; }

        /* Footer Element */
        .total-bobot-footer { 
            background-color: #f8fafc; 
            padding: 15px; 
            text-align: right; 
            border-radius: 0 0 16px 16px; 
            font-weight: 600; 
            font-size: 13px;
            color: #64748b;
            border: 2px solid #e2e8f0; 
            border-top: none; 
            box-sizing: border-box;
        }
        
        /* Sidebar layout navigation utilities */
        .dropdown-menu { display: none; list-style: none; padding: 0; margin-top: 0; background-color: #2c3e50; }
        .dropdown-menu.active { display: block; }
        .dropdown-menu li a { padding: 10px 20px 10px 50px; display: block; color: #bdc3c7; font-size: 14px; border-left: 3px solid transparent; transition: all 0.3s; text-decoration: none; }
        .dropdown-menu li a:hover { background-color: #34495e; border-left-color: #3498db; color: white; }
        .dropdown-toggle { cursor: pointer; display: flex; justify-content: space-between; align-items: center; padding: 12px 20px; color: #bdc3c7; font-weight: 600; text-decoration: none; }
        .sidebar-nav .monitoring-menu a { background-color: var(--mantap-blue-main) !important; color: white !important; font-weight: 600; margin-top: 5px; }

        /* VIEWPORT RESPONSIVE BREAKPOINTS */
        @media (max-width: 768px) {
            body { padding-top: 60px !important; }
            .admin-main-content { padding: 15px 12px 25px 12px !important; }
            .admin-main-content h1 { font-size: 1.4rem !important; }
            
            .form-manage-box { padding: 18px 15px !important; border-radius: 12px !important; }
            .form-flex-group { gap: 10px; }
            .form-flex-group .input-urutan { flex: 1 1 100% !important; }
            .btn-submit, .btn-cancel-edit { width: 100% !important; box-sizing: border-box; justify-content: center; border-radius: 8px !important; margin-left: 0; margin-top: 10px; }

            .kriteria-table { table-layout: auto !important; min-width: 750px; }
            .kriteria-table th.header-aspek, .kriteria-table td { padding: 10px 8px !important; font-size: 13px !important; }
        }
    </style>
</head>
<body class="admin-body">

<?php 
include 'panel/sidebar.php'; 
?>

<div class="main-content-wrapper">

    <?php include 'panel/navbar.php'; ?>

    <div class="admin-main-content">
        <a href="sertifikat.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali ke Penerbitan Sertifikat</a>
        
        <h1>Kelola Format Penilaian PKL</h1>
        
        <?php echo $message; ?>
        
        <div class="form-manage-box">
            <h2><i class="fas fa-<?php echo ($mode === 'edit' ? 'edit' : 'plus'); ?>"></i> <?php echo $page_title; ?></h2>
            
            <form method="POST" action="penilaian-format.php">
                <input type="hidden" name="submit_komponen" value="1">
                <input type="hidden" name="komponen_id" value="<?php echo $form_data['komponen_id']; ?>">
                
                <div class="form-flex-group">
                    <div class="input-urutan">
                        <label for="urutan">Urutan:</label>
                        <input type="number" id="urutan" name="urutan" value="<?php echo htmlspecialchars($form_data['urutan']); ?>" min="1" required>
                    </div>
                    <div>
                        <label for="aspek_id">Aspek Penilaian Utama:</label>
                        <select name="aspek_id" id="aspek_id" required>
                            <option value="">-- Pilih Aspek --</option>
                            <?php foreach ($aspek_options as $aspek): ?>
                                <option value="<?php echo $aspek['aspek_id']; ?>" 
                                    <?php echo ($form_data['aspek_id'] == $aspek['aspek_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($aspek['nama_aspek']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex: 3;">
                        <label for="nama_komponen">Nama Komponen Penilaian:</label>
                        <input type="text" id="nama_komponen" name="nama_komponen" value="<?php echo htmlspecialchars($form_data['nama_komponen']); ?>" placeholder="Contoh: Disiplin, Gambar Teknik, Keselamatan Kerja" required>
                    </div>
                </div>
                
                <label for="deskripsi">Deskripsi Detail Kriteria (Opsional):</label>
                <textarea id="deskripsi" name="deskripsi" rows="2" placeholder="Tulis rincian atau acuan indikator penilaian jika diperlukan..."><?php echo htmlspecialchars($form_data['deskripsi'] ?? ''); ?></textarea>
                
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> <?php echo ($mode === 'edit' ? 'Simpan Perubahan' : 'Tambah Komponen'); ?>
                </button>
                <?php if ($mode === 'edit'): ?>
                     <a href="penilaian-format.php" class="btn-cancel-edit">
                        <i class="fas fa-times"></i> Batal Edit
                     </a>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="form-manage-box" style="padding: 20px 20px 0 20px;">
            <h2>Daftar Komponen Penilaian Aktif</h2>
            <div class="table-responsive-container">
                <table class="kriteria-table">
                    <thead>
                        <tr>
                            <th class="header-aspek" colspan="2" style="width: 70px;">NO</th>
                            <th class="header-aspek" style="text-align: left; padding-left: 15px;">KOMPONEN PENILAIAN</th>
                            <th class="header-aspek" style="width: 90px; text-align: center;">NILAI</th>
                            <th class="header-aspek" style="width: 110px; text-align: center;">PREDIKAT</th>
                            <th class="header-aspek" style="width: 100px; text-align: center;">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $global_num = 1;
                        
                        if (!empty($grouped_components)):
                            foreach ($grouped_components as $aspek_name => $components):
                                
                                // Baris header Aspek Utama 
                                echo '<tr class="row-aspek-nama">';
                                echo '<td colspan="3">' . htmlspecialchars($global_num++) . '. ' . htmlspecialchars(strtoupper($aspek_name)) . '</td>';
                                echo '<td colspan="3"></td>'; 
                                echo '</tr>';

                                $component_num = 1;
                                foreach ($components as $row):
                                    
                                    echo '<tr>'; // Selesai Diperbaiki
                                    echo '<td style="width: 35px; background-color: #fafafa;"></td>'; 
                                    echo '<td style="width: 35px; text-align: center; font-weight: 600; color: #64748b; background-color: #f8fafc;">' . $component_num++ . '</td>'; 
                                    echo '<td style="padding-left: 15px;"><span class="component-title-text">' . htmlspecialchars($row['nama_komponen']) . '</span></td>';
                                    
                                    echo '<td style="text-align: center; background-color: #f8fafc; color: #cbd5e1;">-</td>';
                                    echo '<td style="text-align: center; background-color: #f8fafc; color: #cbd5e1;">-</td>';
                                    
                                    echo '<td style="text-align: center;">';
                                    echo '<a href="penilaian-format.php?edit_id=' . $row['komponen_id'] . '" class="btn-edit-inline" title="Edit"><i class="fas fa-edit"></i></a>';
                                    echo '<a href="penilaian-format.php?action=delete&id=' . $row['komponen_id'] . '" class="btn-delete-inline" onclick="return confirm(\'Yakin hapus kriteria ini?\');" title="Hapus"><i class="fas fa-trash"></i></a>';
                                    echo '</td>';
                                    echo '</tr>';
                                endforeach;
                            endforeach;
                        else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: #dc3545; padding: 20px; font-weight: 600;">Tidak ada kriteria penilaian terdaftar.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="total-bobot-footer">
                Total Bobot Keseluruhan: <strong style="color: var(--mantap-blue-main); font-size: 14px;"><?php echo $total_bobot; ?>%</strong> (Bobot Dihapus)
            </div>
        </div>
    </div>
</div>

<?php 
include 'panel/footer.php'; 
?>
</body>
</html>