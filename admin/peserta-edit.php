<?php
// admin/peserta-edit.php
include 'auth-check.php'; // Proteksi Login
include '../config/db-koneksi.php';

$current_user = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin';
$message = '';
$siswa_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$siswa_data = null;

if ($siswa_id === 0) {
    die("<div class='alert error'>ID Peserta Didik tidak valid.</div>");
}

// ---------------------------------------------------------------------
// QUERIES UNTUK DROPDOWN
// ---------------------------------------------------------------------

// Ambil semua Lokasi PKL (termasuk kuota untuk referensi)
$lokasi_options_result = $koneksi->query("
    SELECT l.lokasi_id, l.nama_lokasi, l.kuota_max, (SELECT COUNT(id) FROM peserta_didik WHERE lokasi_id = l.lokasi_id) AS terisi
    FROM lokasi_pkl l ORDER BY l.nama_lokasi ASC
");
$lokasi_options = $lokasi_options_result ? $lokasi_options_result->fetch_all(MYSQLI_ASSOC) : [];

// Ambil semua Periode PKL
$periode_options_result = $koneksi->query("SELECT periode_id, nama_periode FROM periode_pkl ORDER BY tgl_mulai DESC");
$periode_options = $periode_options_result ? $periode_options_result->fetch_all(MYSQLI_ASSOC) : [];

// DAFTAR KELAS
$daftar_kelas = ["X DPIB 1", "X DPIB 2", "XI DPIB 1", "XI DPIB 2", "XI DPIB 3", "XII DPIB 1", "XII DPIB 2", "XII DPIB 3"];


// ---------------------------------------------------------------------
// LOGIKA UPDATE DATA
// ---------------------------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $siswa_id_post = (int)$_POST['siswa_id'];
    
    // AMBIL SEMUA DATA DARI POST 
    $nisn = trim($_POST['nisn']);
    $nama = trim($_POST['nama']);
    $kelas = $_POST['kelas'];
    $email = trim($_POST['email']);
    $no_hp = trim($_POST['no_hp']); // PENAMBAHAN NO HP

    // Ambil data yang boleh diubah
    $lokasi_id_new = (int)$_POST['lokasi_id'];
    $periode_id_new = (int)$_POST['periode_id'];
    
    // PENAMBAHAN KOLOM no_hp PADA UPDATE QUERY DAN BIND PARAM
    $update_stmt = $koneksi->prepare("UPDATE peserta_didik SET nisn = ?, nama = ?, kelas = ?, email = ?, no_hp = ?, lokasi_id = ?, periode_id = ? WHERE id = ?");
    $update_stmt->bind_param("sssssiii", $nisn, $nama, $kelas, $email, $no_hp, $lokasi_id_new, $periode_id_new, $siswa_id_post);
    // Tipe parameter: nisn(s), nama(s), kelas(s), email(s), no_hp(s), lokasi_id(i), periode_id(i), siswa_id(i)

    if ($update_stmt->execute()) {
        $message = "<div class='alert success'>✅ Data peserta {$nama} berhasil diperbarui!</div>";
    } else {
        // Cek error untuk duplikat NISN (jika NISN UNIQUE)
        if ($koneksi->errno == 1062) {
             $message = "<div class='alert error'>Gagal memperbarui data: NISN sudah digunakan oleh peserta lain.</div>";
        } else {
             $message = "<div class='alert error'>Gagal memperbarui data: " . $update_stmt->error . "</div>";
        }
    }
    $update_stmt->close();
    
    // Agar form memuat data terbaru
    $siswa_id = $siswa_id_post;
}


// ---------------------------------------------------------------------
// AMBIL DATA SISWA SAAT INI
// ---------------------------------------------------------------------
// PENAMBAHAN KOLOM no_hp PADA SELECT QUERY
$stmt_get_data = $koneksi->prepare("SELECT id, nisn, nama, kelas, email, no_hp, lokasi_id, periode_id FROM peserta_didik WHERE id = ?");
$stmt_get_data->bind_param("i", $siswa_id);
$stmt_get_data->execute();
$result = $stmt_get_data->get_result();

if ($result->num_rows > 0) {
    $siswa_data = $result->fetch_assoc();
} else {
    $message = "<div class='alert error'>Peserta didik tidak ditemukan.</div>";
    $siswa_id = 0;
}
$stmt_get_data->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Admin - Edit Peserta</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        /* REVISI KRITIS: Menghilangkan max-width pada container */
        .admin-main-content .container { max-width: 95% !important; }

        .alert.error { background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 15px; border: 1px solid;}
        .alert.success { background-color: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px; border: 1px solid;}

        .admin-main-content form label { display: block; margin-top: 15px; margin-bottom: 5px; font-weight: 600; }
        .admin-main-content form input[type="text"], .admin-main-content form input[type="email"], .admin-main-content form select {
            width: 100%; padding: 10px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
        }
        /* Hapus style untuk input yang dibekukan */
        .btn-submit { background-color: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin-top: 20px; font-size: 16px; }
        .btn-back { color: #007bff; text-decoration: none; font-weight: 500; display: inline-block; margin-bottom: 20px; }
    /* Sidebar CSS untuk konsistensi */
        .dropdown-menu { display: none; list-style: none; padding: 0; margin-top: 0; background-color: #2c3e50; }
        .dropdown-menu.active { display: block; }
        .dropdown-menu li a { padding: 10px 20px 10px 50px; display: block; color: #bdc3c7; font-size: 14px; border-left: 3px solid transparent; transition: all 0.3s; text-decoration: none; }
        .dropdown-menu li a:hover { background-color: #34495e; border-left-color: #3498db; color: white; }
        .dropdown-toggle { cursor: pointer; display: flex; justify-content: space-between; align-items: center; padding: 12px 20px; color: #bdc3c7; font-weight: 600; text-decoration: none; }
        .dropdown-toggle:hover { background-color: #34495e; color: white; }
        .dropdown-toggle i.fa-caret-down { margin-right: 0; transition: transform 0.3s; }
        .dropdown-toggle.active i.fa-caret-down { transform: rotate(180deg); }
        .sidebar-nav .monitoring-menu a { background-color: #007bff !important; color: white !important; font-weight: 600; margin-top: 5px; }
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
            <div class="container">
                <a href="peserta-list.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Kembali ke Daftar Peserta
                </a>
                
                <h1>Edit Data Peserta Didik</h1>
                
                <?php echo $message; ?>

                <?php if ($siswa_data): ?>
                <form method="POST" action="peserta-edit.php?id=<?php echo $siswa_data['id']; ?>">
                    <input type="hidden" name="siswa_id" value="<?php echo $siswa_data['id']; ?>">
                    
                    <label for="nisn">NISN:</label>
                    <input type="text" id="nisn" name="nisn" value="<?php echo htmlspecialchars($siswa_data['nisn']); ?>" required>
                    
                    <label for="nama">Nama Lengkap:</label>
                    <input type="text" id="nama" name="nama" value="<?php echo htmlspecialchars($siswa_data['nama']); ?>" required>
                    
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($siswa_data['email']); ?>" required>

                    <label for="no_hp">Nomor HP (WhatsApp):</label>
                    <input type="text" id="no_hp" name="no_hp" value="<?php echo htmlspecialchars($siswa_data['no_hp']); ?>" placeholder="Cth: 0812xxxxxxxx" required>
                    <label for="kelas">Kelas:</label>
                    <select id="kelas" name="kelas" required>
                        <?php foreach ($daftar_kelas as $kelas_option): ?>
                            <option value="<?php echo htmlspecialchars($kelas_option); ?>" 
                                <?php echo ($siswa_data['kelas'] == $kelas_option) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($kelas_option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <hr style="margin: 30px 0;">

                    <label for="lokasi_id">Lokasi PKL:</label>
                    <select id="lokasi_id" name="lokasi_id" required>
                        <option value="">-- Pilih Lokasi --</option>
                        <?php foreach ($lokasi_options as $lokasi): ?>
                            <?php 
                                $sisa = $lokasi['kuota_max'] - $lokasi['terisi'];
                                $kuota_info = " (Kuota: {$lokasi['terisi']}/{$lokasi['kuota_max']})";
                                $display_text = htmlspecialchars($lokasi['nama_lokasi']) . $kuota_info;
                            ?>
                            <option value="<?php echo $lokasi['lokasi_id']; ?>" 
                                <?php echo ($siswa_data['lokasi_id'] == $lokasi['lokasi_id']) ? 'selected' : ''; ?>>
                                <?php echo $display_text; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="periode_id">Periode PKL:</label>
                    <select id="periode_id" name="periode_id" required>
                        <option value="">-- Pilih Periode --</option>
                        <?php foreach ($periode_options as $periode): ?>
                            <option value="<?php echo $periode['periode_id']; ?>" 
                                <?php echo ($siswa_data['periode_id'] == $periode['periode_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($periode['nama_periode']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Simpan Perubahan Peserta
                    </button>
                </form>
                <?php endif; ?>
            </div>
<?php 
// 3. INCLUDE FOOTER DARI FOLDER PANEL
include 'panel/footer.php'; 
?>