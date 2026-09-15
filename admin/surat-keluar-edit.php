<?php
// admin/surat-keluar-edit.php
include 'auth-check.php';
include '../config/db-koneksi.php';

$current_user = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin';
$current_user_id = $_SESSION['user_id'] ?? 0; // Ditambahkan untuk kebutuhan Log
$message = '';
$upload_dir = '../uploads/surat/';
$surat_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$surat_data = null;

if ($surat_id === 0) {
    die("<div class='alert error'>ID Surat Keluar tidak valid.</div>");
}

// ---------------------------------------------------------------------
// DAFTAR KODE SURAT (STATIS)
// ---------------------------------------------------------------------
$daftar_kode_surat_lengkap = [
    'PILIH' => 'Pilih...',
    '421.1' => 'PENGANTAR PKL (Permohonan/Balasan)',
    '421.2' => 'NILAI / SERTIFIKAT KELULUSAN',
    '421.5' => 'DISPENSASI / IZIN SISWA',
    '421.6' => 'MONITORING / KUNJUNGAN INDUSTRI',
    '420.5' => 'KESISWAAN (Non-PKL/Mutasi)',
    '000' => 'UMUM / INTERNAL SEKOLAH', 
    '100' => 'KETATALAKSANAAN (SK/Tata Tertib)',
    '800' => 'KEPEGAWAIAN / SDM',
    '900' => 'KEUANGAN',
];

// ---------------------------------------------------------------------
// LOGIKA UPDATE DATA
// ---------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $surat_id_post = (int)$_POST['surat_id'];
    
    // Ambil data yang dibekukan dari hidden field
    $nomor_surat = trim($_POST['nomor_surat_frozen']); // FROZEN
    $kode_surat = $_POST['kode_surat_frozen'];        // FROZEN

    // Ambil data yang diedit
    $tanggal_surat = trim($_POST['tanggal_surat']);
    $tujuan_perusahaan = trim($_POST['tujuan_perusahaan']);
    $alamat_tujuan = trim($_POST['alamat_tujuan']); // SINKRONISASI: Alamat Tujuan
    $perihal = trim($_POST['perihal']);
    $lampiran = trim($_POST['lampiran']); 
    $isi_surat = trim($_POST['isi_surat']); 
    $file_path_lama = $_POST['file_path_lama'];
    $file_path_baru = $file_path_lama;
    
    $error_upload = false;

    if (empty($nomor_surat) || empty($tanggal_surat) || empty($tujuan_perusahaan) || empty($alamat_tujuan) || empty($perihal) || empty($lampiran) || empty($isi_surat) || $kode_surat === 'PILIH') {
        $message = "<div class='alert error'>Semua kolom wajib diisi.</div>";
    } else {
        // Proses Upload File Baru (jika ada)
        if (isset($_FILES['file_dokumen_new']) && $_FILES['file_dokumen_new']['error'] == 0) {
             $file_ext = strtolower(pathinfo($_FILES['file_dokumen_new']['name'], PATHINFO_EXTENSION));
             $allowed_ext = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
             if (in_array($file_ext, $allowed_ext)) {
                 $file_name = 'SK_' . time() . '_' . str_replace('/', '_', $nomor_surat) . '.' . $file_ext;
                 $file_destination = $upload_dir . $file_name;
                 
                 if (move_uploaded_file($_FILES['file_dokumen_new']['tmp_name'], $file_destination)) {
                     if (!empty($file_path_lama) && file_exists($upload_dir . $file_path_lama)) {
                         unlink($upload_dir . $file_path_lama);
                     }
                     $file_path_baru = $file_name;
                 } else {
                     $message = "<div class='alert error'>Gagal mengunggah file dokumen baru.</div>";
                     $error_upload = true;
                 }
             } else {
                 $message = "<div class='alert error'>Jenis file tidak didukung.</div>";
                 $error_upload = true;
             }
         } 

        if (empty($message) && !$error_upload) {

            // --- DETEKSI PERUBAHAN DATA UNTUK LOG SUPER DETAIL ---
            $stmt_old = $koneksi->prepare("SELECT nomor_surat, tanggal_surat, tujuan_perusahaan, alamat_tujuan, perihal, lampiran, isi_surat, file_path FROM surat_keluar_pkl WHERE surat_id = ?");
            $stmt_old->bind_param("i", $surat_id_post);
            $stmt_old->execute();
            $old_data = $stmt_old->get_result()->fetch_assoc();
            $stmt_old->close();

            $perubahan = [];
            if ($old_data['tanggal_surat'] != $tanggal_surat) $perubahan[] = "Tanggal Surat";
            if ($old_data['tujuan_perusahaan'] != $tujuan_perusahaan) $perubahan[] = "Tujuan Instansi";
            if ($old_data['alamat_tujuan'] != $alamat_tujuan) $perubahan[] = "Alamat Tujuan";
            if ($old_data['perihal'] != $perihal) $perubahan[] = "Perihal";
            if ($old_data['lampiran'] != $lampiran) $perubahan[] = "Keterangan Lampiran";
            if ($old_data['isi_surat'] != $isi_surat) $perubahan[] = "Isi Redaksi Surat";
            if ($old_data['file_path'] != $file_path_baru) $perubahan[] = "File Lampiran Scan/Fisik";

            $pesan_log = "";
            if (count($perubahan) > 0) {
                $detail_ubah = implode(", ", $perubahan);
                $pesan_log = "Memperbarui data arsip Surat Keluar: " . $old_data['nomor_surat'] . " (Detail yang diubah: " . $detail_ubah . ")";
            } else {
                $pesan_log = "Menyimpan ulang arsip Surat Keluar: " . $old_data['nomor_surat'] . " (Tanpa perubahan data)";
            }
            // ------------------------------------------

            // UPDATE database dengan 8 kolom data (termasuk alamat_tujuan)
            $update_stmt = $koneksi->prepare("UPDATE surat_keluar_pkl SET nomor_surat = ?, tanggal_surat = ?, tujuan_perusahaan = ?, alamat_tujuan = ?, perihal = ?, lampiran = ?, isi_surat = ?, file_path = ? WHERE surat_id = ?");
            
            // BIND PARAMETERS (8 string + 1 integer)
            $update_stmt->bind_param("ssssssssi", $nomor_surat, $tanggal_surat, $tujuan_perusahaan, $alamat_tujuan, $perihal, $lampiran, $isi_surat, $file_path_baru, $surat_id_post);

            if ($update_stmt->execute()) {
                
                // --- TRIGGER LOG AKTIVITAS (UPDATE DETAIL SURAT KELUAR) ---
                catatLog($koneksi, $current_user_id, $pesan_log);

                header("Location: persuratan.php?status=edit_success&tab=keluar");
                exit();
            } else {
                 if ($koneksi->errno == 1062) {
                    $message = "<div class='alert error'>Gagal menyimpan data: Nomor surat sudah terdaftar.</div>";
                } else {
                    $message = "<div class='alert error'>Gagal memperbarui data: " . $update_stmt->error . "</div>";
                }
            }
            $update_stmt->close();
        }
    }
    $surat_id = $surat_id_post; 
}

// ---------------------------------------------------------------------
// AMBIL DATA SURAT SAAT INI
// ---------------------------------------------------------------------
$stmt_get_data = $koneksi->prepare("SELECT surat_id, nomor_surat, tanggal_surat, tujuan_perusahaan, alamat_tujuan, perihal, kode_surat, file_path, lampiran, isi_surat FROM surat_keluar_pkl WHERE surat_id = ?");
$stmt_get_data->bind_param("i", $surat_id);
$stmt_get_data->execute();
$result = $stmt_get_data->get_result();

if ($result->num_rows > 0) {
    $surat_data = $result->fetch_assoc();
} else {
    $message = "<div class='alert error'>Surat tidak ditemukan.</div>";
}
$stmt_get_data->close();

function formatTanggalInput($date_str) {
    return date('Y-m-d', strtotime($date_str));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin - Edit Surat Keluar | Si Mantap PKL</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
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

        .alert { padding: 12px 15px; margin-bottom: 20px; border-radius: 8px; border: 1px solid; font-size: 14px; font-weight: 500; text-align: left; }
        .alert.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .alert.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        
        /* CARD PANEL FORM CONTAINER */
        .form-edit-container {
            background-color: white;
            padding: 30px;
            border-radius: 16px;
            max-width: 800px; /* Lebar disinkronkan dengan add */
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
            text-align: left;
        }
        
        .form-edit-container input[type="text"], 
        .form-edit-container input[type="date"], 
        .form-edit-container select, 
        .form-edit-container input[type="file"], 
        .form-edit-container textarea {
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
        .form-edit-container input:focus, 
        .form-edit-container select:focus, 
        .form-edit-container textarea:focus { outline: none; border-color: #1e40af; background-color: white; }

        /* Style khusus untuk input yang dibekukan */
        .form-edit-container input[readonly], 
        .form-edit-container select[disabled] {
            background-color: #f1f5f9 !important;
            color: #64748b !important;
            cursor: not-allowed;
            border: 1px solid #cbd5e1 !important;
        }

        .current-file-box { 
            border: 1px solid #cbd5e1; 
            padding: 12px; 
            margin-bottom: 16px; 
            border-radius: 8px; 
            background: #f8fafc; 
            font-size: 13px; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px;
            width: 100%;
            box-sizing: border-box;
        }
        .current-file-box i { color: var(--mantap-blue-main); }
        .file-preview-link { color: var(--mantap-blue-main); text-decoration: none; font-weight: 600; }
        .file-preview-link:hover { text-decoration: underline; }

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
            justify-content: center;
            gap: 6px;
            width: 100%;
            box-sizing: border-box;
        }
        .btn-submit-save:hover { background-color: var(--mantap-blue-light); }

        .sidebar-nav ul, .sidebar-nav li, .dropdown-menu, .dropdown-menu li { list-style: none !important; margin: 0; padding: 0; }

        /* Custom Override for TinyMCE Layout within standard form container */
        .tox-tinymce {
            border-radius: 8px !important;
            border: 1px solid #cbd5e1 !important;
            margin-bottom: 16px !important;
        }

        /* =========================================================================
           RESPONSIVE VIEWPORT HANDPHONE (HP)
        ========================================================================= */
        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 

            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; gap: 10px !important; padding: 5px 0px !important; }
            .page-header-controls h1 { font-size: 1.4rem !important; }
            
            .form-edit-container { padding: 20px 14px !important; border-radius: 12px !important; border: 2px solid #cbd5e1 !important; }
            .form-edit-container input, .form-edit-container select, .form-edit-container textarea { font-size: 13.5px !important; padding: 10px !important; }
            
            .form-edit-container button.btn-submit-save { padding: 12px !important; border-radius: 8px !important; font-size: 14px !important; }
            .btn-back-link { font-size: 12.5px !important; margin-top: 5px; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; ?>

<div class="main-content-wrapper">

    <?php include 'panel/navbar.php'; ?>

    <div class="admin-main-content"> 
        <div class="page-header-controls">
            <h1>Edit Surat Keluar</h1>
            <a href="persuratan.php?tab=keluar" class="btn-back-link">
                <i class="fas fa-arrow-left"></i> Kembali ke Administrasi Persuratan
            </a>
        </div>
        
        <?php echo $message; ?>

        <?php if ($surat_data): ?>
        <div class="form-edit-container">
            <form method="POST" action="surat-keluar-edit.php?id=<?php echo $surat_data['surat_id']; ?>" enctype="multipart/form-data" style="box-shadow: none; max-width: 100%; margin: 0; padding: 0;">
                <input type="hidden" name="surat_id" value="<?php echo $surat_data['surat_id']; ?>">
                <input type="hidden" name="file_path_lama" value="<?php echo htmlspecialchars($surat_data['file_path']); ?>">
                
                <input type="hidden" name="nomor_surat_frozen" value="<?php echo htmlspecialchars($surat_data['nomor_surat']); ?>">
                <input type="hidden" name="kode_surat_frozen" value="<?php echo htmlspecialchars($surat_data['kode_surat']); ?>">

                <label for="kode_surat">Kode Surat (Klasifikasi):</label>
                <select id="kode_surat" name="kode_surat" required disabled> 
                    <?php foreach ($daftar_kode_surat_lengkap as $kode => $nama): ?>
                        <option value="<?php echo htmlspecialchars($kode); ?>" 
                            <?php echo ($surat_data['kode_surat'] == $kode) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($nama); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <label for="tanggal_surat">Tanggal Surat Dibuat:</label>
                <input type="date" id="tanggal_surat" name="tanggal_surat" value="<?php echo formatTanggalInput($surat_data['tanggal_surat']); ?>" required>

                <label for="nomor_surat">Nomor Surat Keluar (Unik):</label>
                <input type="text" id="nomor_surat" name="nomor_surat" value="<?php echo htmlspecialchars($surat_data['nomor_surat']); ?>" required readonly>
                
                <label for="tujuan_perusahaan">Tujuan Perusahaan / Instansi:</label>
                <input type="text" id="tujuan_perusahaan" name="tujuan_perusahaan" value="<?php echo htmlspecialchars($surat_data['tujuan_perusahaan']); ?>" placeholder="Contoh: PT. Adhikari Teknik Blitar" required>

                <label for="alamat_tujuan">Alamat Kantor / Lokasi Tujuan:</label>
                <input type="text" id="alamat_tujuan" name="alamat_tujuan" value="<?php echo htmlspecialchars($surat_data['alamat_tujuan'] ?? 'Di tempat'); ?>" placeholder="Masukkan alamat lengkap operasional instansi..." required>
                
                <label for="perihal">Perihal Surat:</label>
                <input type="text" id="perihal" name="perihal" value="<?php echo htmlspecialchars($surat_data['perihal']); ?>" placeholder="Contoh: Permohonan Tempat Pelaksanaan PKL Kelompok 1" required>
                
                <label for="lampiran">Keterangan Berkas Lampiran:</label>
                <input type="text" id="lampiran" name="lampiran" value="<?php echo htmlspecialchars($surat_data['lampiran'] ?? ''); ?>" placeholder="Contoh: Satu Lembar / Satu Berkas" required>
                
                <label for="isi_surat">Isi Surat (Editor Format Srikandi):</label>
                <textarea id="isi_surat" name="isi_surat" rows="18" required><?php echo htmlspecialchars($surat_data['isi_surat'] ?? ''); ?></textarea>

                <label>Dokumen Saat Ini:</label>
                <div class="current-file-box">
                    <i class="fas fa-file-alt"></i> 
                    <?php if (!empty($surat_data['file_path'])): ?>
                        <a href="<?php echo $upload_dir . urlencode($surat_data['file_path']); ?>" target="_blank" class="file-preview-link">
                            <?php echo basename($surat_data['file_path']); ?> (Klik untuk Preview)
                        </a>
                    <?php else: ?>
                        <span class="text-muted" style="font-style: italic;">Tidak ada dokumen fisik terlampir.</span>
                    <?php endif; ?>
                </div>
                
                <label for="file_dokumen_new">Ganti Lampiran File Pindai/Scan (PDF/Doc/Gambar - Opsional):</label>
                <input type="file" id="file_dokumen_new" name="file_dokumen_new" accept=".pdf, .doc, .docx, .jpg, .jpeg, .png">

                <button type="submit" class="btn-submit-save">
                    <i class="fas fa-save"></i> Simpan Perubahan Surat Keluar
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php include 'panel/footer.php'; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js" referrerpolicy="origin"></script>

<script>
    // INISIALISASI TINYMCE - FORMAT RESMI SRIKANDI DENGAN FITUR RESIZE TABEL
    tinymce.init({
        selector: '#isi_surat',
        height: 500,
        menubar: 'edit insert view format table tools',
        plugins: [
            'advlist', 'autolink', 'lists', 'link', 'charmap', 'preview',
            'searchreplace', 'visualblocks', 'code', 'fullscreen',
            'insertdatetime', 'table', 'wordcount'
        ],
        toolbar: 'undo redo | fontfamily fontsize | bold italic underline | ' +
                 'alignleft aligncenter alignright alignjustify | ' +
                 'outdent indent | bullist numlist | table | removeformat | fullscreen code',
        font_size_formats: '8pt 10pt 11pt 12pt 14pt 18pt 24pt',
        font_family_formats: 'Times New Roman=times new roman,times,serif; Arial=arial,helvetica,sans-serif;',
        
        // MENGAKTIFKAN FITUR DRAG / RESIZE PADA TABEL
        table_resizing: true,
        
        content_style: `
            body { 
                font-family: 'Times New Roman', Times, serif; 
                font-size: 12pt; 
                color: #000000; 
                line-height: 1.5; 
                text-align: justify;
                padding: 15px;
            }
            p { 
                margin: 0 0 10px 0; 
            }
            /* Menghapus 'width: 100%' agar lebar inline-style berfungsi saat digeser */
            table { 
                border-collapse: collapse; 
                margin-top: 10px;
                margin-bottom: 10px;
            }
            table, th, td { 
                border: 1px solid black; 
            }
            th, td { 
                padding: 6px 10px; 
                vertical-align: middle;
                text-align: left;
            }
            th { 
                font-weight: bold; 
                text-align: center; 
            }
        `,
        branding: false,
        promotion: false,
        setup: function (editor) {
            editor.on('change', function () {
                tinymce.triggerSave();
            });
        }
    });
</script>
</body>
</html>