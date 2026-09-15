<?php
// TAMPILKAN ERROR (Hapus 3 baris ini setelah debugging selesai!)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// admin/sidang-add-form.php (Formulir Tambah Jadwal Sidang)
include 'auth-check.php'; 
include '../config/db-koneksi.php'; // Pastikan file koneksi ada di jalur yang benar

// Dapatkan daftar Lokasi/Peserta dari tabel 'lokasi_pkl'
$lokasi_query = "SELECT lokasi_id, nama_lokasi FROM lokasi_pkl ORDER BY nama_lokasi ASC";
$lokasi_result = $koneksi->query($lokasi_query);

// Dapatkan daftar Guru dari tabel 'guru' (untuk Pembimbing dan Penguji)
$guru_query = "SELECT guru_id, nama_guru FROM guru ORDER BY nama_guru ASC";
$guru_result = $koneksi->query($guru_query);
$guru_pembimbing_result = $koneksi->query($guru_query); // Clone untuk Pembimbing
$guru_penguji1_result = $koneksi->query($guru_query);   // Clone untuk Penguji 1
$guru_penguji2_result = $koneksi->query($guru_query);   // Clone untuk Penguji 2

// Cek jika ada error query
if (!$lokasi_result || !$guru_result || !$guru_pembimbing_result || !$guru_penguji1_result || !$guru_penguji2_result) {
    die("Query Gagal: " . $koneksi->error);
}

// Inisialisasi variabel untuk menampung data form jika terjadi error
$tanggal_sidang = '';
$waktu_sidang = '';
$ruangan = '';
$keterangan = '';
$lokasi_id_selected = '';
$pembimbing_id_selected = '';
$penguji_id_1_selected = '';
$penguji_id_2_selected = '';
$error_message = '';

// --- LOGIKA SIMPAN DATA (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil dan bersihkan data dari form
    $tanggal_sidang       = $koneksi->real_escape_string($_POST['tanggal_sidang']);
    $waktu_sidang         = $koneksi->real_escape_string($_POST['waktu_sidang']);
    $ruangan              = $koneksi->real_escape_string($_POST['ruangan']);
    $keterangan           = $koneksi->real_escape_string($_POST['keterangan']);
    $lokasi_id_selected   = (int)$_POST['lokasi_id']; // ID Lokasi
    $pembimbing_id_selected = (int)$_POST['pembimbing_id']; // ID Pembimbing
    $penguji_id_1_selected  = (int)$_POST['penguji_id_1'];  // ID Penguji 1
    $penguji_id_2_selected  = (int)$_POST['penguji_id_2'];  // ID Penguji 2

    // Validasi sederhana (Pastikan field wajib terisi)
    if (empty($tanggal_sidang) || empty($waktu_sidang) || empty($ruangan) || empty($lokasi_id_selected) || empty($pembimbing_id_selected) || empty($penguji_id_1_selected) || empty($penguji_id_2_selected)) {
        $error_message = '<div class="alert error">Mohon lengkapi semua field wajib (Tanggal, Waktu, Ruangan, Lokasi/Peserta, Pembimbing, Penguji 1, dan Penguji 2).</div>';
    } else {
        // Gunakan Prepared Statement untuk keamanan
        $stmt = $koneksi->prepare("INSERT INTO jadwal_sidang (tanggal_sidang, waktu_sidang, ruangan, keterangan, lokasi_id, pembimbing_id, penguji_id, penguji_id_2) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        // 'ssssiiii' berarti string, string, string, string, integer, integer, integer, integer
        $stmt->bind_param("ssssiiii", 
            $tanggal_sidang, 
            $waktu_sidang, 
            $ruangan, 
            $keterangan, 
            $lokasi_id_selected, 
            $pembimbing_id_selected, 
            $penguji_id_1_selected, 
            $penguji_id_2_selected
        );

        if ($stmt->execute()) {
            // Sukses, redirect ke halaman list dengan pesan sukses
            $success_msg = urlencode("Jadwal sidang berhasil ditambahkan!");
            header("Location: sidang-pkl.php?msg=" . $success_msg);
            exit();
        } else {
            // Gagal eksekusi query
            $error_message = '<div class="alert error">Gagal menyimpan data ke database: ' . $stmt->error . '</div>';
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Admin - Tambah Jadwal Sidang PKL</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        /* CSS Tambahan untuk Form */
        .form-container { max-width: 600px; margin: 30px auto; padding: 30px; background: #fff; border-radius: 10px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        .form-container h1 { text-align: center; color: #2c3e50; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #34495e; }
        .form-group input[type="date"],
        .form-group input[type="time"],
        .form-group input[type="text"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: #3498db;
            outline: none;
        }
        .form-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 30px; }
        .btn-submit { background-color: #28a745; color: white; padding: 12px 25px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background-color 0.3s; }
        .btn-submit:hover { background-color: #218838; }
        .btn-cancel { background-color: #6c757d; color: white; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: 600; transition: background-color 0.3s; }
        .btn-cancel:hover { background-color: #5a6268; }

        /* Style untuk Alert */
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; border: 1px solid transparent; }
        .alert.error { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .required-star { color: #dc3545; font-weight: 700; margin-left: 5px; }

        /* Untuk konsistensi tampilan sidebar dan navbar */
        .admin-main-content { padding: 20px; }
    </style>
</head>
<body class="admin-body">

<?php 
// 1. INCLUDE SIDEBAR DARI FOLDER PANEL
include 'panel/sidebar.php'; 
?>

<?php 
// 2. INCLUDE NAVBAR DARI FOLDER PANEL
include 'panel/navbar.php'; 
?>
    <div class="admin-main-content">
        <div class="form-container">
            <h1>Tambah Jadwal Sidang PKL</h1>
            
            <?php echo $error_message; ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                
                <div class="form-group">
                    <label for="tanggal_sidang">Tanggal Sidang <span class="required-star">*</span></label>
                    <input type="date" id="tanggal_sidang" name="tanggal_sidang" value="<?php echo htmlspecialchars($tanggal_sidang); ?>" required>
                </div>

                <div class="form-group">
                    <label for="waktu_sidang">Waktu Sidang (misalnya: 10:00) <span class="required-star">*</span></label>
                    <input type="time" id="waktu_sidang" name="waktu_sidang" value="<?php echo htmlspecialchars($waktu_sidang); ?>" required>
                </div>

                <div class="form-group">
                    <label for="ruangan">Ruangan <span class="required-star">*</span></label>
                    <input type="text" id="ruangan" name="ruangan" value="<?php echo htmlspecialchars($ruangan); ?>" placeholder="Contoh: Ruang Sidang A" required>
                </div>
                
                <div class="form-group">
                    <label for="lokasi_id">Lokasi (Peserta) <span class="required-star">*</span></label>
                    <select id="lokasi_id" name="lokasi_id" required>
                        <option value="">-- Pilih Lokasi/Peserta --</option>
                        <?php while ($lokasi = $lokasi_result->fetch_assoc()): ?>
                            <option value="<?php echo $lokasi['lokasi_id']; ?>" 
                                <?php echo ($lokasi['lokasi_id'] == $lokasi_id_selected) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lokasi['nama_lokasi']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="pembimbing_id">Pembimbing <span class="required-star">*</span></label>
                    <select id="pembimbing_id" name="pembimbing_id" required>
                        <option value="">-- Pilih Pembimbing --</option>
                        <?php while ($guru = $guru_pembimbing_result->fetch_assoc()): ?>
                            <option value="<?php echo $guru['guru_id']; ?>"
                                <?php echo ($guru['guru_id'] == $pembimbing_id_selected) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($guru['nama_guru']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="penguji_id_1">Penguji 1 <span class="required-star">*</span></label>
                    <select id="penguji_id_1" name="penguji_id_1" required>
                        <option value="">-- Pilih Penguji 1 --</option>
                        <?php while ($guru = $guru_penguji1_result->fetch_assoc()): ?>
                            <option value="<?php echo $guru['guru_id']; ?>"
                                <?php echo ($guru['guru_id'] == $penguji_id_1_selected) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($guru['nama_guru']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="penguji_id_2">Penguji 2 <span class="required-star">*</span></label>
                    <select id="penguji_id_2" name="penguji_id_2" required>
                        <option value="">-- Pilih Penguji 2 --</option>
                        <?php while ($guru = $guru_penguji2_result->fetch_assoc()): ?>
                            <option value="<?php echo $guru['guru_id']; ?>"
                                <?php echo ($guru['guru_id'] == $penguji_id_2_selected) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($guru['nama_guru']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="keterangan">Keterangan (Opsional)</label>
                    <textarea id="keterangan" name="keterangan" rows="3" placeholder="Contoh: Sidang Ulang atau Catatan Khusus"><?php echo htmlspecialchars($keterangan); ?></textarea>
                </div>
                
                <div class="form-actions">
                    <a href="sidang-pkl.php" class="btn-cancel">Batal / Kembali</a>
                    <button type="submit" class="btn-submit">Simpan Jadwal Sidang</button>
                </div>

            </form>
        </div>
    </div>

<?php 
// 3. INCLUDE FOOTER DARI FOLDER PANEL
include 'panel/footer.php'; 
// Tutup koneksi database
$koneksi->close();
?>
<script>
// Logika untuk sidebar dropdown (Pastikan ini sesuai dengan sidebar.php Anda)
document.querySelectorAll('.dropdown-toggle').forEach(item => {
    item.addEventListener('click', event => {
        const dropdownMenu = item.nextElementSibling;
        dropdownMenu.classList.toggle('active');
        item.classList.toggle('active');
    });
});
</script>
</body>
</html>