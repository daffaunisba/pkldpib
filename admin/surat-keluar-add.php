<?php
// admin/surat-keluar-add.php
include 'auth-check.php';
include '../config/db-koneksi.php';

$current_user = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin';
$current_user_id = $_SESSION['user_id'] ?? 0; // Kebutuhan Log
$message = '';
$upload_dir = '../uploads/surat/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// ---------------------------------------------------------------------
// FUNGSI BANTUAN UNTUK NOMOR SURAT ROMAWI
// ---------------------------------------------------------------------
function convertToRoman($num) {
    $romans = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V',
        6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X',
        11 => 'XI', 12 => 'XII'
    ];
    return $romans[$num] ?? '';
}

$current_year = date('Y');
$kode_jurusan = 'SMEKISA.PKL-DPIB';

// ---------------------------------------------------------------------
// LOGIKA AJAX UNTUK MENGHITUNG NOMOR URUT BERIKUTNYA (REAL-TIME)
// ---------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'get_next_number') {
    header('Content-Type: application/json');
    $ajax_kode = $_GET['kode'] ?? '';
    $ajax_tanggal = $_GET['tanggal'] ?? date('Y-m-d'); 
    
    if ($ajax_kode && $ajax_kode !== 'PILIH' && !empty($ajax_tanggal)) {
        // Hitung banyaknya surat keluar berdasarkan bulan dan tahun saja (GLOBAL COUNT)
        $stmt_count_ajax = $koneksi->prepare("
            SELECT COUNT(surat_id) AS total_surat
            FROM surat_keluar_pkl
            WHERE YEAR(tanggal_surat) = YEAR(?) AND MONTH(tanggal_surat) = MONTH(?)
        ");
        $stmt_count_ajax->bind_param("ss", $ajax_tanggal, $ajax_tanggal); 
        $stmt_count_ajax->execute();
        $final_count_ajax = $stmt_count_ajax->get_result()->fetch_assoc()['total_surat'];
        
        $next_number = sprintf('%02d', $final_count_ajax + 1);
        $stmt_count_ajax->close();
        
        echo json_encode(['next_number' => $next_number]);
    } else {
        echo json_encode(['next_number' => '00']); 
    }
    exit; 
}

// ---------------------------------------------------------------------
// LOGIKA UTAMA HALAMAN
// ---------------------------------------------------------------------

// 1. Ambil Klasifikasi DARI DATABASE
$klasifikasi_result = $koneksi->query("SELECT kode, nama_klasifikasi FROM klasifikasi_surat ORDER BY kode ASC");

$daftar_kode_surat_lengkap = ['PILIH' => 'Pilih...'];
if ($klasifikasi_result) {
    while ($row = $klasifikasi_result->fetch_assoc()) {
        if ($row['kode'] !== 'PILIH') { 
             $daftar_kode_surat_lengkap[$row['kode']] = $row['nama_klasifikasi'];
        }
    }
}

// 2. Set Default Form Data
$current_month = date('m'); 
$roman_month = convertToRoman((int)$current_month);
$default_nomor_surat_base = "XX/00/{$roman_month}/{$kode_jurusan}/{$current_year}";

$form_data = [
    'nomor_surat' => $default_nomor_surat_base,
    'tanggal_surat' => date('Y-m-d'),
    'tujuan_perusahaan' => '',
    'alamat_tujuan' => '', 
    'perihal' => '',
    'lampiran' => 'Satu Berkas', 
    'isi_surat' => '', 
    'kode_surat' => 'PILIH' 
];

// ---------------------------------------------------------------------
// LOGIKA SUBMIT FORM (SIMPAN DB DAN REDIRECT KE ARSIP)
// ---------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $kode_surat = $_POST['kode_surat']; 
    $tanggal_surat = trim($_POST['tanggal_surat']);
    $tujuan_perusahaan = trim($_POST['tujuan_perusahaan']);
    $alamat_tujuan = trim($_POST['alamat_tujuan']); 
    $perihal = trim($_POST['perihal']);
    $lampiran = trim($_POST['lampiran']); 
    $isi_surat = trim($_POST['isi_surat']); 
    
    $file_name = null;
    $error_upload = false;

    // Simpan data input jika terjadi error
    $form_data = [
        'tanggal_surat' => $tanggal_surat,
        'tujuan_perusahaan' => $tujuan_perusahaan,
        'alamat_tujuan' => $alamat_tujuan, 
        'perihal' => $perihal,
        'lampiran' => $lampiran, 
        'isi_surat' => $isi_surat, 
        'kode_surat' => $kode_surat
    ];
    
    if ($kode_surat === 'PILIH' || empty($tanggal_surat) || empty($tujuan_perusahaan) || empty($perihal) || empty($isi_surat) || empty($alamat_tujuan)) {
        $message = "<div class='alert error'>Semua kolom wajib diisi.</div>";
    } else {
        
        // --- FINAL RE-CALCULATE (Safety Check) ---
        $stmt_count_final = $koneksi->prepare("
            SELECT COUNT(surat_id) AS total_surat
            FROM surat_keluar_pkl
            WHERE YEAR(tanggal_surat) = YEAR(?) AND MONTH(tanggal_surat) = MONTH(?)
        ");
        $stmt_count_final->bind_param("ss", $tanggal_surat, $tanggal_surat);
        $stmt_count_final->execute();
        $final_count = $stmt_count_final->get_result()->fetch_assoc()['total_surat'];
        $nomor_urut_final = sprintf('%02d', $final_count + 1);
        $stmt_count_final->close();

        $roman_month_final = convertToRoman((int)date('m', strtotime($tanggal_surat)));
        $final_nomor_surat = "{$kode_surat}/{$nomor_urut_final}/{$roman_month_final}/{$kode_jurusan}/{$current_year}";
        
        $form_data['nomor_surat'] = $final_nomor_surat; 
        
        // Logika upload file
        if (isset($_FILES['file_dokumen']) && $_FILES['file_dokumen']['error'] == 0) {
            $file_ext = strtolower(pathinfo($_FILES['file_dokumen']['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
            if (in_array($file_ext, $allowed_ext)) {
                $safe_nomor_surat = str_replace('/', '_', $final_nomor_surat);
                $file_name = 'SK_' . time() . '_' . $safe_nomor_surat . '.' . $file_ext; 
                $file_destination = $upload_dir . $file_name;
                
                if (!move_uploaded_file($_FILES['file_dokumen']['tmp_name'], $file_destination)) {
                    $message = "<div class='alert error'>Gagal mengunggah file dokumen.</div>";
                    $error_upload = true;
                }
            } else {
                $message = "<div class='alert error'>Jenis file tidak didukung.</div>";
                $error_upload = true;
            }
        } else if ($_FILES['file_dokumen']['error'] != 4 && $_FILES['file_dokumen']['error'] != 0) { 
             $message = "<div class='alert error'>Terjadi kesalahan saat upload file.</div>";
             $error_upload = true;
        }

        if (empty($message) && !$error_upload) {
             // INSERT KE DATABASE
             $insert_stmt = $koneksi->prepare("INSERT INTO surat_keluar_pkl (nomor_surat, tanggal_surat, tujuan_perusahaan, alamat_tujuan, perihal, lampiran, isi_surat, kode_surat, file_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
             $insert_stmt->bind_param("sssssssss", $final_nomor_surat, $tanggal_surat, $tujuan_perusahaan, $alamat_tujuan, $perihal, $lampiran, $isi_surat, $kode_surat, $file_name);
            
             if ($insert_stmt->execute()) {
                
                // --- TRIGGER LOG AKTIVITAS (ADD SURAT KELUAR) ---
                catatLog($koneksi, $current_user_id, "Menerbitkan arsip Surat Keluar baru untuk {$tujuan_perusahaan} (Nomor: {$final_nomor_surat}). Perihal: {$perihal}");

                header("Location: persuratan.php?status=success&tab=keluar");
                exit();
            } else {
                if ($koneksi->errno == 1062) {
                    $message = "<div class='alert error'>Gagal menyimpan data: Nomor surat sudah terdaftar.</div>";
                } else {
                    $message = "<div class='alert error'>Gagal menyimpan data: " . $koneksi->error . "</div>";
                }
            }
            $insert_stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin - Tambah Surat Keluar | Si Mantap PKL</title>
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
        
        /* CARD PANEL FORM CONTAINER */
        .form-add-container {
            background-color: white;
            padding: 30px;
            border-radius: 16px;
            max-width: 800px;
            width: 100%;
            border: 2px solid #e2e8f0;
            box-sizing: border-box;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        }

        .form-add-container label { 
            display: block; 
            margin-bottom: 6px; 
            font-weight: 600; 
            font-size: 13.5px; 
            color: #0f172a; 
            text-align: left;
        }
        
        .form-add-container input[type="text"], 
        .form-add-container input[type="date"], 
        .form-add-container select, 
        .form-add-container input[type="file"], 
        .form-add-container textarea {
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
        .form-add-container input:focus, 
        .form-add-container select:focus, 
        .form-add-container textarea:focus { outline: none; border-color: #1e40af; background-color: white; }

        /* FORM COMPONENT INLINE FLEX */
        .form-group-flex {
            display: flex;
            justify-content: space-between;
            align-items: center; 
            margin-bottom: 6px;
            width: 100%;
        }
        .form-group-flex label { margin-bottom: 0; }
        
        .btn-kelola-inline {
            font-size: 11.5px;
            color: #0ea5e9;
            text-decoration: none;
            font-weight: 600;
            padding: 4px 10px;
            border: 1px solid #0ea5e9;
            border-radius: 4px;
            transition: 0.2s;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-kelola-inline:hover {
            background-color: var(--mantap-blue-soft);
        }

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
            
            .form-add-container { padding: 20px 14px !important; border-radius: 12px !important; border: 2px solid #cbd5e1 !important; }
            .form-add-container input, .form-add-container select, .form-add-container textarea { font-size: 13.5px !important; padding: 10px !important; }
            
            .form-add-container button.btn-submit-save { padding: 12px !important; border-radius: 8px !important; font-size: 14px !important; }
            .btn-back-link { font-size: 12.5px !important; margin-top: 5px; }
            .btn-kelola-inline { font-size: 11px !important; padding: 3px 8px !important; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; ?>

<div class="main-content-wrapper">

    <?php include 'panel/navbar.php'; ?>

    <div class="admin-main-content"> 
        <div class="page-header-controls">
            <h1>Tambah Surat Keluar Baru</h1>
            <a href="persuratan.php?tab=keluar" class="btn-back-link">
                <i class="fas fa-arrow-left"></i> Kembali ke Administrasi Persuratan
            </a>
        </div>
        
        <?php echo $message; ?>

        <div class="form-add-container">
            <form method="POST" action="surat-keluar-add.php" enctype="multipart/form-data" style="box-shadow: none; max-width: 100%; margin: 0; padding: 0;">
                
                <div class="form-group-flex">
                    <label for="kode_surat">Kode Surat (Klasifikasi):</label>
                    <a href="kode-surat-manage.php" class="btn-kelola-inline" title="Kelola List Klasifikasi Agenda">
                        <i class="fas fa-edit"></i> Kelola Klasifikasi
                    </a>
                </div>
                <select id="kode_surat" name="kode_surat" required onchange="updateNomorSurat()">
                    <?php foreach ($daftar_kode_surat_lengkap as $kode => $nama): ?>
                        <option value="<?php echo htmlspecialchars($kode); ?>" 
                            <?php echo ($form_data['kode_surat'] === $kode) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($nama); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <label for="tanggal_surat">Tanggal Surat Dibuat:</label>
                <input type="date" id="tanggal_surat" name="tanggal_surat" value="<?php echo $form_data['tanggal_surat']; ?>" required onchange="updateNomorSurat()">

                <label for="nomor_surat">Nomor Surat Keluar (Otomatis):</label>
                <input type="text" id="nomor_surat" name="nomor_surat" value="<?php echo htmlspecialchars($form_data['nomor_surat']); ?>" placeholder="Nomor urut agenda akan digenerate otomatis..." required>
                
                <label for="tujuan_perusahaan">Tujuan Perusahaan / Nama Instansi:</label>
                <input type="text" id="tujuan_perusahaan" name="tujuan_perusahaan" value="<?php echo htmlspecialchars($form_data['tujuan_perusahaan']); ?>" placeholder="Contoh: PT. Adhikari Teknik Blitar" required>

                <label for="alamat_tujuan">Alamat Kantor / Lokasi Tujuan:</label>
                <input type="text" id="alamat_tujuan" name="alamat_tujuan" value="<?php echo htmlspecialchars($form_data['alamat_tujuan'] ?? 'Di tempat'); ?>" placeholder="Masukkan alamat lengkap operasional instansi..." required>
                
                <label for="perihal">Perihal Surat Keluar:</label>
                <input type="text" id="perihal" name="perihal" value="<?php echo htmlspecialchars($form_data['perihal']); ?>" placeholder="Contoh: Permohonan Tempat Pelaksanaan PKL Kelompok 1" required>
                
                <label for="lampiran">Keterangan Berkas Lampiran:</label>
                <input type="text" id="lampiran" name="lampiran" value="<?php echo htmlspecialchars($form_data['lampiran'] ?? 'Satu Berkas'); ?>" placeholder="Contoh: Satu Lembar / Satu Berkas" required>

                <label for="isi_surat">Isi Surat (Editor Format Srikandi):</label>
                <textarea id="isi_surat" name="isi_surat" rows="18"><?php echo htmlspecialchars($form_data['isi_surat'] ?? ''); ?></textarea>

                <label for="file_dokumen">Upload Lampiran File Pindai/Scan Fisik (PDF/Doc/Gambar - Opsional):</label>
                <input type="file" id="file_dokumen" name="file_dokumen" accept=".pdf, .doc, .docx, .jpg, .jpeg, .png">
                <p style="font-size: 11.5px; color: #64748b; margin-top: -10px; margin-bottom: 15px; text-align: left;">Kosongkan jika tidak melampirkan salinan fisik tanda tangan basah. Batas maksimum ukuran berkas: 5MB.</p>

                <button type="submit" class="btn-submit-save">
                    <i class="fas fa-save"></i> Preview & Simpan Surat Keluar
                </button>
            </form>
        </div>
    </div>

</div>

<?php include 'panel/footer.php'; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js" referrerpolicy="origin"></script>

<script>
    // INISIALISASI TINYMCE - FORMAT RESMI SRIKANDI (TIMES NEW ROMAN, JUSTIFY, TABEL SOLID DENGAN RESIZE)
    tinymce.init({
        selector: '#isi_surat',
        height: 500,
        menubar: 'edit insert view format table tools',
        plugins: [
            'advlist', 'autolink', 'lists', 'link', 'charmap', 'preview',
            'searchreplace', 'visualblocks', 'code', 'fullscreen',
            'insertdatetime', 'table', 'wordcount', 'pagebreak' // Ditambahkan plugin pagebreak
        ],
        toolbar: 'undo redo | fontfamily fontsize | bold italic underline | ' +
                 'alignleft aligncenter alignright alignjustify | ' +
                 'outdent indent | bullist numlist | table pagebreak | removeformat | fullscreen code', // Ditambahkan tombol pagebreak
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
                padding: 10px 14px; 
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
        placeholder: "Tuliskan isi redaksi surat secara lengkap disini (Gunakan tombol Table di atas untuk membuat jadwal kegiatan)...",
        setup: function (editor) {
            // Memastikan data disinkronisasi ke textarea asli saat user mengetik
            editor.on('change', function () {
                tinymce.triggerSave();
            });
        }
    });

    // FUNGSI ROMAWI (Diambil dari PHP, ditranslasikan ke JS)
    function convertToRoman(num) {
        if (num < 1 || num > 12) return '';
        const map = { 1: 'I', 2: 'II', 3: 'III', 4: 'IV', 5: 'V', 6: 'VI', 7: 'VII', 8: 'VIII', 9: 'IX', 10: 'X', 11: 'XI', 12: 'XII' };
        return map[num] || '';
    }

    // FUNGSI UTAMA UNTUK MENGUPDATE NOMOR SURAT SECARA DINAMIS
    async function updateNomorSurat() {
        const kodeSelect = document.getElementById('kode_surat');
        const tanggalInput = document.getElementById('tanggal_surat');
        const nomorInput = document.getElementById('nomor_surat');

        const kode = kodeSelect.value;
        const tanggal = tanggalInput.value;
        const kodeJurusan = '<?php echo $kode_jurusan; ?>'; 
        const currentYear = '<?php echo $current_year; ?>';

        // 1. Validasi Input (Untuk Placeholder)
        if (kode === 'PILIH' || !tanggal) {
            const roman = tanggal ? convertToRoman(new Date(tanggal).getMonth() + 1) : 'XX';
            nomorInput.value = `${kode}/00/${roman}/${kodeJurusan}/${currentYear}`; 
            return; 
        }

        // 2. Ekstraksi Tanggal untuk Format Nomor
        const date = new Date(tanggal);
        const monthNum = date.getMonth() + 1; // 1-12
        const year = date.getFullYear();
        const romanMonth = convertToRoman(monthNum);
        
        // 3. Ambil Nomor Urut Berikutnya dari Server (AJAX/Fetch)
        const url = `surat-keluar-add.php?action=get_next_number&kode=${kode}&tanggal=${tanggal}`;
        let nomorUrutFinal = '01';
        
        try {
            const response = await fetch(url);
            const data = await response.json();
            
            if (data && data.next_number) {
                nomorUrutFinal = data.next_number;
            }
        } catch (error) {
            console.error('Gagal mengambil nomor urut:', error);
        }
        
        // 4. Gabungkan komponen
        const finalNomor = `${kode}/${nomorUrutFinal}/${romanMonth}/${kodeJurusan}/${year}`;
        nomorInput.value = finalNomor;
    }

    // Panggil saat halaman dimuat untuk menginisialisasi
    document.addEventListener('DOMContentLoaded', function() {
        updateNomorSurat();
    });
</script>
</body>
</html>