<!-- <?php
// admin/sidang-edit.php (Formulir Edit Jadwal Sidang PKL - FINAL FIX V5 - SYNCHRONIZED)
include 'auth-check.php'; 
include '../config/db-koneksi.php'; 

$message = '';

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    die("ID jadwal sidang tidak valid.");
}

$id_sidang = (int)$_GET['id'];

// --- DAFTAR PILIHAN DROPDOWN (DISINKRONKAN DENGAN add-form.php) ---
$list_waktu = [
    'Sesi 1 (Pukul 07.00-08.00 WIB)' => 'Sesi 1 (Pukul 07.00-08.00 WIB)',
    'Sesi 2 (Pukul 08.00-09.00 WIB)' => 'Sesi 2 (Pukul 08.00-09.00 WIB)',
    'Sesi 3 (Pukul 09.00-10.00 WIB)' => 'Sesi 3 (Pukul 09.00-10.00 WIB)',
    'Sesi 4 (Pukul 10.00-11.00 WIB)' => 'Sesi 4 (Pukul 10.00-11.00 WIB)',
];

$list_ruangan = [
    'Aula Kampus 1' => 'Aula Kampus 1',
    'Lab Bimasena' => 'Lab Bimasena',
    'Lab APL' => 'Lab APL',
    'Ruang Rapat Guru' => 'Ruang Rapat Guru',
];

// --- 1. AMBIL DATA JADWAL SIDANG YANG AKAN DIEDIT ---
$get_sidang_stmt = $koneksi->prepare("
    SELECT 
        js.*, 
        l.nama_lokasi, 
        gp.nama_guru AS nama_pembimbing
    FROM jadwal_sidang js
    LEFT JOIN lokasi_pkl l ON js.lokasi_id = l.lokasi_id
    LEFT JOIN guru gp ON js.pembimbing_id = gp.guru_id
    WHERE js.id = ?
");
$get_sidang_stmt->bind_param("i", $id_sidang);
$get_sidang_stmt->execute();
$sidang_data = $get_sidang_stmt->get_result();

if ($sidang_data->num_rows === 0) {
    die("Data jadwal sidang tidak ditemukan.");
}
$data = $sidang_data->fetch_assoc();
$get_sidang_stmt->close();

// Ambil Daftar Guru (untuk Penguji 1 & 2) -> Optimasi: Ambil hanya sekali
$guru_result = $koneksi->query("SELECT guru_id, nama_guru FROM guru ORDER BY nama_guru ASC");
$list_guru = [];
while ($g = $guru_result->fetch_assoc()) {
    $list_guru[] = $g;
}
$guru_result->free();


// --- 2. LOGIKA HANDLING FORM SUBMISSION (UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_sidang'])) {
    // Ambil nilai dari POST (atau nilai default dari $data jika tidak ada di POST, meskipun seharusnya semua ada)
    $current_lokasi_id = (int)$_POST['lokasi_id'];
    $current_pembimbing_id = (int)$_POST['pembimbing_id'];
    $current_penguji_id = (int)$_POST['penguji_id'];
    $current_penguji_id_2 = (int)$_POST['penguji_id_2'];
    $current_tanggal = htmlspecialchars($_POST['tanggal_sidang']);
    $current_ruangan = htmlspecialchars($_POST['ruangan']);
    $current_keterangan = htmlspecialchars($_POST['keterangan']);
    $current_waktu = htmlspecialchars($_POST['waktu_sidang']); // Waktu dalam format Label Sesi

    // Validasi sederhana
    if ($current_lokasi_id <= 0 || $current_pembimbing_id <= 0 || $current_penguji_id <= 0 || $current_penguji_id_2 <= 0 || empty($current_tanggal) || empty($current_waktu) || empty($current_ruangan)) {
        $message = '<div class="alert error">Error: Semua field wajib diisi, termasuk kedua Guru Penguji dan Waktu Sidang!</div>';
    } 
    // --- VALIDASI DUPLIKASI GURU ---
    else if ($current_penguji_id == $current_penguji_id_2) {
        $message = '<div class="alert error">Error: Guru Penguji 1 dan Guru Penguji 2 tidak boleh sama!</div>';
    } 
    else if ($current_pembimbing_id > 0 && 
        ($current_penguji_id == $current_pembimbing_id || $current_penguji_id_2 == $current_pembimbing_id)
    ) {
        $message = '<div class="alert error">Error: Guru Penguji tidak boleh sama dengan Guru Pembimbing!</div></div>';
    } 
    // --- END VALIDASI DUPLIKASI GURU ---
    else {
        $update_query = "
            UPDATE jadwal_sidang 
            SET 
                tanggal_sidang = ?, 
                waktu_sidang = ?, 
                ruangan = ?, 
                lokasi_id = ?, 
                pembimbing_id = ?, 
                penguji_id = ?, 
                penguji_id_2 = ?, 
                keterangan = ?
            WHERE id = ?
        ";
        $stmt = $koneksi->prepare($update_query);
        
        // PASTIKAN URUTAN BIND PARAMETER SAMA DENGAN URUTAN KOLOM DI QUERY
        $stmt->bind_param("sssiiiisi", 
            $current_tanggal, 
            $current_waktu, // Menggunakan variabel yang sudah di-sanitize
            $current_ruangan, 
            $current_lokasi_id, 
            $current_pembimbing_id, 
            $current_penguji_id, 
            $current_penguji_id_2, 
            $current_keterangan, 
            $id_sidang
        );
        
        if ($stmt->execute()) {
            $success_msg = urlencode("Jadwal sidang berhasil diperbarui!");
            header("Location: sidang-pkl.php?msg=" . $success_msg);
            exit;
        } else {
            $message = '<div class="alert error">Gagal memperbarui jadwal: ' . $stmt->error . '</div>';
        }
        $stmt->close();
    }
} 
// --- 3. PREPARASI NILAI TAMPILAN JIKA BUKAN POST (LOAD AWAL) ---
else {
    // Ambil nilai dari DB saat load pertama
    $current_lokasi_id = (int)$data['lokasi_id'];
    $current_pembimbing_id = (int)$data['pembimbing_id'];
    $current_penguji_id = (int)$data['penguji_id'];
    $current_penguji_id_2 = (int)$data['penguji_id_2'];
    $current_tanggal = $data['tanggal_sidang'];
    $current_ruangan = $data['ruangan'];
    $current_keterangan = $data['keterangan'];
    $current_waktu = $data['waktu_sidang'];
}

// Nilai tampilan yang tidak berubah
$initial_lokasi_name = $data['nama_lokasi'] ? htmlspecialchars($data['nama_lokasi']) : 'Lokasi Tidak Ditemukan';
$initial_pembimbing_name = $data['nama_pembimbing'] ? htmlspecialchars($data['nama_pembimbing']) : 'Guru Pembimbing Belum Ditetapkan di Lokasi Ini';

// LOGIKA KOREKSI KRITIS untuk WAKTU SIDANG SAAT LOAD
// Jika data lama/mentah dari DB ('00:00:00', '07:00:00', '08:00:00', dll.), paksa kosong agar user memilih sesi yang benar
if (in_array($current_waktu, array('00:00:00', '07:00:00', '08:00:00', '09:00:00', '10:00:00', ''))) {
    $current_waktu = ''; 
} 

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Admin - Edit Jadwal Sidang</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        /* CSS Styling sama seperti sebelumnya */
        .admin-main-content { 
            padding-top: 20px; 
            padding-left: 20px;
            padding-right: 20px;
        }
        .form-header { margin-bottom: 10px; }
        .btn-kembali-style {
            color: #007bff;
            font-size: 16px; 
            font-weight: 500;
            text-decoration: none;
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
        }
        .btn-kembali-style i { margin-right: 8px; }
        .btn-kembali-style:hover { text-decoration: underline; color: #0056b3; }
        .form-title { font-size: 28px; font-weight: 700; margin-top: 0; margin-bottom: 0; }
        .admin-main-content hr { border: none; border-top: 1px solid #ddd; margin: 10px 0 25px 0; }
        .form-container { 
            max-width: 100%; 
            width: 100%;        
            margin: 0;        
            padding: 30px; 
            background: white; 
            border-radius: 8px; 
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); 
        }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        .form-group input[type="date"],
        .form-group input[type="text"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #007bff; outline: none; }
        .btn-submit { background-color: #ffc107; color: #333; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: 600; transition: background-color 0.2s; }
        .btn-submit:hover { background-color: #e0a800; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; font-size: 14px; }
        .alert.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .read-only-field, .frozen-field { 
            background-color: #f8f9fa; 
            color: #6c757d; 
            border: 1px dashed #ced4da !important; 
            font-weight: 600; 
        }
        .btn-secondary { background-color: #6c757d; color: white; padding: 12px 20px; border-radius: 4px; font-weight: 600; transition: background-color 0.2s;}
        .btn-secondary:hover { background-color: #5a6268; }
    </style>

</head>
<body class="admin-body">

<?php 
// 1. INCLUDE SIDEBAR DARI FOLDER PANEL
include 'panel/sidebar.php'; 
?>

<?php 
include 'panel/navbar.php'; 
?>

<div class="admin-main-content">
    <div class="form-header">
        <a href="sidang-pkl.php" class="btn-kembali-style">
            <i class="fas fa-arrow-left"></i> Kembali ke Daftar Jadwal Sidang PKL
        </a>
    </div>

    <h1 class="form-title">Edit Jadwal Sidang PKL #<?php echo $id_sidang; ?></h1>
    <hr> 
    
    <div class="form-container">
        
        <?php echo $message; ?>

        <form method="POST" action="sidang-edit.php?id=<?php echo $id_sidang; ?>" id="editSidangForm">
            
            <div class="form-group">
                <label for="lokasi_display">Lokasi PKL (Tidak Dapat Diubah)</label>
                <input type="text" id="lokasi_display" class="frozen-field" readonly 
                    value="<?php echo $initial_lokasi_name; ?>">
                
                <input type="hidden" id="lokasi_id" name="lokasi_id" value="<?php echo $current_lokasi_id; ?>">
                <input type="hidden" id="nama_lokasi_display" name="nama_lokasi_display" value="<?php echo $initial_lokasi_name; ?>">
            </div>

            <div class="form-group">
                <label for="pembimbing_display">Guru Pembimbing (Otomatis & Tidak Dapat Diubah)</label>
                <input type="text" id="pembimbing_display" class="frozen-field" readonly 
                    value="<?php echo $initial_pembimbing_name; ?>">
                
                <input type="hidden" id="pembimbing_id" name="pembimbing_id" value="<?php echo $current_pembimbing_id; ?>">
                <input type="hidden" id="nama_pembimbing_display" name="nama_pembimbing_display" value="<?php echo $initial_pembimbing_name; ?>">
            </div>
            
            <div class="form-group">
                <label for="penguji_id">Pilih Guru Penguji 1 *</label>
                <select id="penguji_id" name="penguji_id" required>
                    <option value="">-- Pilih Guru Penguji 1 --</option>
                    <?php foreach ($list_guru as $g): // Menggunakan array tunggal $list_guru
                        $selected = ($g['guru_id'] == $current_penguji_id) ? 'selected' : '';
                    ?>
                        <option value="<?php echo $g['guru_id']; ?>" <?php echo $selected; ?>>
                            <?php echo htmlspecialchars($g['nama_guru']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="penguji_id_2">Pilih Guru Penguji 2 *</label>
                <select id="penguji_id_2" name="penguji_id_2" required>
                    <option value="">-- Pilih Guru Penguji 2 --</option>
                    <?php foreach ($list_guru as $g): // Menggunakan array tunggal $list_guru
                        $selected = ($g['guru_id'] == $current_penguji_id_2) ? 'selected' : '';
                    ?>
                        <option value="<?php echo $g['guru_id']; ?>" <?php echo $selected; ?>>
                            <?php echo htmlspecialchars($g['nama_guru']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <hr>
            
            <div class="form-group" style="display: flex; gap: 20px;">
                <div style="flex: 1;">
                    <label for="tanggal_sidang">Tanggal Sidang *</label>
                    <input type="date" id="tanggal_sidang" name="tanggal_sidang" required value="<?php echo htmlspecialchars($current_tanggal); ?>">
                </div>
                <div style="flex: 1;">
                    <label for="waktu_sidang">Waktu Sidang (Sesi) *</label>
                    <select id="waktu_sidang" name="waktu_sidang" required>
                        <option value="">-- Pilih Sesi --</option>
                        <?php foreach ($list_waktu as $value => $label): 
                            // Menggunakan $current_waktu yang sudah diproses di atas
                            $selected = ($label == $current_waktu) ? 'selected' : '';
                        ?>
                            <option value="<?php echo htmlspecialchars($label); ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="ruangan">Ruangan Sidang *</label>
                <select id="ruangan" name="ruangan" required>
                    <option value="">-- Pilih Ruangan --</option>
                    <?php foreach ($list_ruangan as $value => $label): 
                        $selected = ($value == $current_ruangan) ? 'selected' : '';
                    ?>
                        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $selected; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="keterangan">Keterangan (Opsional)</label>
                <textarea id="keterangan" name="keterangan" rows="3" placeholder="Contoh: Sidang khusus kelompok kelas 12 TKJ"><?php echo htmlspecialchars($current_keterangan); ?></textarea>
            </div>

            <button type="submit" name="submit_sidang" class="btn-submit">Simpan Perubahan Jadwal</button>
            <a href="sidang-pkl.php" class="btn-secondary" style="margin-left: 10px; text-decoration: none;">Batal</a>
        </form>
    </div>
</div>

<?php 
include 'panel/footer.php'; 
?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const pengujiSelect1 = document.getElementById('penguji_id');
        const pengujiSelect2 = document.getElementById('penguji_id_2');
        const pembimbingId = document.getElementById('pembimbing_id').value;
        const form = document.getElementById('editSidangForm');

        // Fungsi untuk validasi penguji tidak boleh sama
        function validatePenguji(event) {
            const penguji1 = pengujiSelect1.value;
            const penguji2 = pengujiSelect2.value;
            const pembimbing = pembimbingId;
            let errorMessage = '';

            if (penguji1 && penguji2 && penguji1 === penguji2) {
                errorMessage = 'Guru Penguji 1 dan Guru Penguji 2 tidak boleh sama. Harap pilih guru yang berbeda.';
            } else if (pembimbing && (penguji1 === pembimbing || penguji2 === pembimbing)) {
                errorMessage = 'Guru Penguji tidak boleh sama dengan Guru Pembimbing.';
            }
            
            if (errorMessage) {
                alert('⚠️ Validasi Error: ' + errorMessage);
                event.preventDefault(); // Mencegah submit
                return false;
            }
            return true;
        }

        form.addEventListener('submit', validatePenguji);
    });
</script>

</body>
</html> -->