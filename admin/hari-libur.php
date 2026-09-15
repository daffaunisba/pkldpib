<?php
// admin/hari-libur.php
// Halaman untuk mengatur kalender hari libur nasional / tanggal merah.

include 'auth-check.php';
include '../config/db-koneksi.php';

$current_user_level = strtolower($_SESSION['level'] ?? 'user'); 
$current_user_id = $_SESSION['user_id'] ?? 0; // Ditambahkan untuk kebutuhan log

// Hanya Admin yang boleh mengakses halaman ini
if ($current_user_level !== 'admin') {
    die("<div class='alert error' style='margin:20px; font-family:sans-serif;'>Akses Ditolak. Hanya Administrator yang diizinkan untuk mengatur kalender akademik/hari libur.</div>");
}

$message = '';

// ---------------------------------------------------------------------
// 1. AUTO-CREATE TABLE HARI LIBUR
// ---------------------------------------------------------------------
$koneksi->query("CREATE TABLE IF NOT EXISTS hari_libur (
    id_libur INT AUTO_INCREMENT PRIMARY KEY, 
    tanggal_libur DATE NOT NULL, 
    keterangan VARCHAR(255) NOT NULL,
    UNIQUE(tanggal_libur)
)");

// ---------------------------------------------------------------------
// 2. LOGIKA HAPUS DATA
// ---------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_del = (int)$_GET['id'];

    // --- AMBIL DATA SEBELUM DIHAPUS UNTUK LOG ---
    $ket_del = "ID " . $id_del;
    $tgl_del = "";
    $cek_del = $koneksi->query("SELECT keterangan, tanggal_libur FROM hari_libur WHERE id_libur = $id_del");
    if ($cek_del && $cek_del->num_rows > 0) {
        $dt = $cek_del->fetch_assoc();
        $ket_del = $dt['keterangan'];
        $tgl_del = $dt['tanggal_libur'];
    }

    $stmt_del = $koneksi->prepare("DELETE FROM hari_libur WHERE id_libur = ?");
    $stmt_del->bind_param("i", $id_del);
    if ($stmt_del->execute()) { 
        
        // --- TRIGGER LOG AKTIVITAS (DELETE) ---
        catatLog($koneksi, $current_user_id, "Menghapus hari libur: {$ket_del} (Tanggal: {$tgl_del})");

        // Mengembalikan ke bulan dan tahun saat ini saat dihapus
        $m_return = isset($_GET['m']) ? $_GET['m'] : date('m');
        $y_return = isset($_GET['y']) ? $_GET['y'] : date('Y');
        header("Location: hari-libur.php?status=deleted&m=$m_return&y=$y_return"); 
        exit(); 
    }
}

// ---------------------------------------------------------------------
// 3. LOGIKA SIMPAN / UPDATE DATA
// ---------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_libur'])) {
    $id_libur = (int)$_POST['id_libur']; 
    $tanggal_libur = trim($_POST['tanggal_libur']); 
    $keterangan = trim($_POST['keterangan']);
    
    // Untuk return ke halaman kalender yang sama
    $m_return = date('m', strtotime($tanggal_libur));
    $y_return = date('Y', strtotime($tanggal_libur));

    // Cek apakah tanggal tersebut sudah ada di database (agar tidak bentrok)
    $stmt_cek = $koneksi->prepare("SELECT id_libur FROM hari_libur WHERE tanggal_libur = ? AND id_libur != ?");
    $stmt_cek->bind_param("si", $tanggal_libur, $id_libur); 
    $stmt_cek->execute();
    
    if ($stmt_cek->get_result()->num_rows > 0) { 
        $message = "<div class='alert error'>❌ Gagal: Tanggal tersebut sudah terdaftar sebagai hari libur.</div>"; 
    } else {
        if ($id_libur > 0) {
            // --- DETEKSI PERUBAHAN UNTUK LOG ---
            $stmt_old = $koneksi->prepare("SELECT keterangan, tanggal_libur FROM hari_libur WHERE id_libur = ?");
            $stmt_old->bind_param("i", $id_libur);
            $stmt_old->execute();
            $old_data = $stmt_old->get_result()->fetch_assoc();
            $stmt_old->close();

            $perubahan = [];
            if ($old_data['tanggal_libur'] != $tanggal_libur) $perubahan[] = "Tanggal Libur"; // UI readonly, tapi bisa diubah via inspect element
            if ($old_data['keterangan'] != $keterangan) $perubahan[] = "Keterangan / Nama Libur";

            $pesan_log = count($perubahan) > 0 
                ? "Memperbarui hari libur pada tanggal {$tanggal_libur} (Detail yang diubah: " . implode(", ", $perubahan) . ")"
                : "Menyimpan ulang hari libur pada tanggal {$tanggal_libur} (Tanpa perubahan data)";

            $stmt = $koneksi->prepare("UPDATE hari_libur SET tanggal_libur=?, keterangan=? WHERE id_libur=?");
            $stmt->bind_param("ssi", $tanggal_libur, $keterangan, $id_libur);
            
            if ($stmt->execute()) { 
                catatLog($koneksi, $current_user_id, $pesan_log); // Log Update
                header("Location: hari-libur.php?status=success&m=$m_return&y=$y_return"); 
                exit(); 
            } else { 
                $message = "<div class='alert error'>❌ Error database: " . $koneksi->error . "</div>"; 
            }
        } else {
            // --- INSERT BARU ---
            $stmt = $koneksi->prepare("INSERT INTO hari_libur (tanggal_libur, keterangan) VALUES (?, ?)");
            $stmt->bind_param("ss", $tanggal_libur, $keterangan);
            
            if ($stmt->execute()) { 
                catatLog($koneksi, $current_user_id, "Menambahkan hari libur baru: {$keterangan} (Tanggal: {$tanggal_libur})"); // Log Tambah
                header("Location: hari-libur.php?status=success&m=$m_return&y=$y_return"); 
                exit(); 
            } else { 
                $message = "<div class='alert error'>❌ Error database: " . $koneksi->error . "</div>"; 
            }
        }
        $stmt->close();
    }
    $stmt_cek->close();
}

if (isset($_GET['status'])) {
    if ($_GET['status'] == 'deleted') $message = "<div class='alert success'>✅ Data hari libur berhasil dihapus.</div>";
    if ($_GET['status'] == 'success') $message = "<div class='alert success'>✅ Data hari libur berhasil disimpan.</div>";
}

// ---------------------------------------------------------------------
// 4. AMBIL DATA & LOGIKA KALENDER
// ---------------------------------------------------------------------
$bulan_indo = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

// Menentukan bulan dan tahun aktif
$m = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('m');
$y = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');

// Handle navigasi bulan
if ($m < 1) { $m = 12; $y--; }
if ($m > 12) { $m = 1; $y++; }

$prev_m = $m - 1; $prev_y = $y;
$next_m = $m + 1; $next_y = $y;
if ($prev_m < 1) { $prev_m = 12; $prev_y--; }
if ($next_m > 12) { $next_m = 1; $next_y++; }

// Ambil semua data hari libur ke dalam Array (Map) agar mudah dicocokkan ke tanggal
$data_libur_raw = $koneksi->query("SELECT * FROM hari_libur");
$holiday_map = [];
if ($data_libur_raw) {
    while ($row = $data_libur_raw->fetch_assoc()) {
        $holiday_map[$row['tanggal_libur']] = [
            'id' => $row['id_libur'],
            'keterangan' => $row['keterangan']
        ];
    }
}

// Menentukan detail grid kalender
$first_day_in_month = date('w', strtotime(sprintf("%04d-%02d-01", $y, $m))); // 0 (Minggu) s/d 6 (Sabtu)
$total_days = date('t', strtotime(sprintf("%04d-%02d-01", $y, $m)));

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Manajemen Hari Libur | Si Mantap PKL</title>
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
            --mantap-danger: #dc2626;
            --mantap-red-soft: #fef2f2;
        }

        body { font-family: 'Poppins', sans-serif; background-color: #f8fafc; color: #334155; margin: 0; overflow-x: hidden !important; }
        .main-content-wrapper { max-width: 100% !important; width: 100% !important; box-sizing: border-box !important; box-shadow: none !important;}
        .admin-main-content { padding: 20px 25px 30px 25px !important; box-sizing: border-box !important; width: 100% !important; clear: both; }

        .page-header-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .page-header-controls h1 { font-weight: 700; color: #0f172a; font-size: 1.8rem; margin: 0; position: relative; }
        .page-header-controls h1::after { content: ''; position: absolute; left: 0; bottom: -5px; width: 50px; height: 4px; background: var(--mantap-blue-main); border-radius: 2px; }

        .header-actions-group { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        
        .alert { padding: 12px 15px; margin-bottom: 20px; border-radius: 8px; border: 1px solid; font-size: 13.5px; font-weight: 500; text-align: left; }
        .alert.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert.error { background-color: #fef2f2; color: #ef4444; border-color: #fecaca; }

        /* CUSTOM CALENDAR STYLES */
        .glass-panel-calendar { background: white !important; padding: 20px !important; border-radius: 16px !important; border: 2px solid #e2e8f0 !important; box-sizing: border-box; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); width: 100%; }
        
        .calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
        .calendar-header h2 { margin: 0; font-size: 1.5rem; color: var(--mantap-blue-dark); font-weight: 700; }
        .calendar-nav-buttons { display: flex; gap: 8px; align-items: center; }
        .btn-cal-nav { background-color: var(--mantap-blue-light); color: white !important; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 500; border: none; cursor: pointer; transition: all 0.2s;}
        .btn-cal-nav:hover { background-color: var(--mantap-blue-main); }
        
        .calendar-grid-wrapper { overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px; }
        .calendar-grid { display: grid; grid-template-columns: repeat(7, minmax(120px, 1fr)); min-width: 800px; background: #e2e8f0; gap: 1px;}
        
        .cal-day-name { background: #f8fafc; padding: 15px 10px; text-align: center; font-weight: 700; font-size: 14px; color: #0f172a; }
        .cal-day-cell { background: white; min-height: 120px; padding: 10px; position: relative; cursor: pointer; transition: background 0.2s ease; }
        .cal-day-cell:hover { background: #f1f5f9; }
        .cal-day-cell.empty-cell { background: #fef2f2; opacity: 0.5; cursor: default; }
        .cal-day-cell.empty-cell:hover { background: #fef2f2; }
        
        .cal-date-number { display: block; text-align: right; font-weight: 600; font-size: 15px; color: #64748b; margin-bottom: 8px; }
        
        .holiday-badge { background-color: #ef4444; color: white; border-radius: 6px; padding: 6px 10px; font-size: 12px; font-weight: 500; display: flex; justify-content: space-between; align-items: flex-start; gap: 5px; word-break: break-word; line-height: 1.3; box-shadow: 0 2px 4px rgba(239,68,68,0.2);}
        .holiday-badge:hover { background-color: #dc2626; }
        .del-holiday-btn { color: white; background: transparent; border: none; cursor: pointer; font-size: 13px; opacity: 0.8; padding: 0; margin: 0; }
        .del-holiday-btn:hover { opacity: 1; color: #fecaca; }

        /* MODAL */
        .mantap-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(15, 23, 42, 0.5); z-index: 999999 !important; backdrop-filter: blur(3px); overflow-y: auto; }
        .mantap-modal-dialog { position: relative; width: 90%; max-width: 500px; margin: 5rem auto; box-sizing: border-box; }
        .mantap-modal-content { background-color: white; padding: 25px; border-radius: 16px; border: 2px solid #cbd5e1; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15); }
        .mantap-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; }
        .mantap-modal-header h5 { font-size: 1.3rem; margin: 0; color: #0f172a; font-weight: 700; }
        .close-modal-btn { background: none; border: none; font-size: 1.7rem; color: #64748b; cursor: pointer; line-height: 1; }
        
        .mantap-modal-body label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13.5px; color: #0f172a; margin-top: 15px; }
        .mantap-modal-body input, .mantap-modal-body textarea { width: 100%; padding: 11px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 13px; background-color: #f8fafc; box-sizing: border-box; }
        .mantap-modal-body input:focus, .mantap-modal-body textarea:focus { outline: none; border-color: var(--mantap-blue-main); background-color: white; }

        .btn-modal-submit { background-color: var(--mantap-blue-main); color: white; padding: 12px; border: none; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; font-family: 'Poppins', sans-serif; width: 100%; margin-top: 25px; box-shadow: 0 4px 6px -1px rgba(30, 64, 175, 0.15); }
        .btn-modal-submit:hover { background-color: var(--mantap-blue-light); }

        .sidebar-nav ul, .sidebar-nav li, .dropdown-menu, .dropdown-menu li { list-style: none !important; margin: 0; padding: 0; }

        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 
            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; }
            .mantap-modal-dialog { margin: 2rem auto; width: 95%; }
            .calendar-header { flex-direction: column; align-items: flex-start; }
            .calendar-nav-buttons { width: 100%; justify-content: space-between; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; ?>

<div class="main-content-wrapper">
    <?php include 'panel/navbar.php'; ?>

    <div class="admin-main-content"> 
        <div class="page-header-controls">
            <h1><i class="fas fa-calendar-times" style="color: var(--mantap-danger); margin-right: 6px;"></i> Kalender Hari Libur</h1>
        </div>

        <?php echo $message; ?>
        
        <div class="glass-panel-calendar">
            
            <div class="calendar-header">
                <h2><?php echo $bulan_indo[$m] . ' ' . $y; ?></h2>
                <div class="calendar-nav-buttons">
                    <a href="?m=<?php echo date('m'); ?>&y=<?php echo date('Y'); ?>" class="btn-cal-nav" style="background:#64748b;">Hari Ini</a>
                    <a href="?m=<?php echo $prev_m; ?>&y=<?php echo $prev_y; ?>" class="btn-cal-nav"><i class="fas fa-chevron-left"></i></a>
                    <a href="?m=<?php echo $next_m; ?>&y=<?php echo $next_y; ?>" class="btn-cal-nav"><i class="fas fa-chevron-right"></i></a>
                </div>
            </div>

            <div class="calendar-grid-wrapper">
                <div class="calendar-grid">
                    <div class="cal-day-name" style="color: var(--mantap-danger);">Min</div>
                    <div class="cal-day-name">Sen</div>
                    <div class="cal-day-name">Sel</div>
                    <div class="cal-day-name">Rab</div>
                    <div class="cal-day-name">Kam</div>
                    <div class="cal-day-name">Jum</div>
                    <div class="cal-day-name">Sab</div>

                    <?php
                    // Kosongkan blok awal jika tanggal 1 bukan hari minggu
                    for ($i = 0; $i < $first_day_in_month; $i++) {
                        echo '<div class="cal-day-cell empty-cell"></div>';
                    }

                    // Render tanggal
                    for ($day = 1; $day <= $total_days; $day++) {
                        $current_date_str = sprintf("%04d-%02d-%02d", $y, $m, $day);
                        
                        $has_holiday = isset($holiday_map[$current_date_str]);
                        $id_libur = $has_holiday ? $holiday_map[$current_date_str]['id'] : 0;
                        $keterangan = $has_holiday ? htmlspecialchars($holiday_map[$current_date_str]['keterangan'], ENT_QUOTES) : '';
                        
                        // Action onclick untuk cell kalender
                        $onclick_action = "openModal('$id_libur', '$current_date_str', '$keterangan')";

                        echo '<div class="cal-day-cell" onclick="'.$onclick_action.'">';
                        echo '<span class="cal-date-number">' . $day . '</span>';
                        
                        if ($has_holiday) {
                            echo '<div class="holiday-badge" onclick="event.stopPropagation(); '.$onclick_action.'">';
                            echo '<span>' . $keterangan . '</span>';
                            // Tombol hapus terpisah dalam badge
                            echo '<button class="del-holiday-btn" onclick="event.stopPropagation(); konfirmasiHapus('.$id_libur.', '.$m.', '.$y.')" title="Hapus"><i class="fas fa-times"></i></button>';
                            echo '</div>';
                        }
                        echo '</div>';
                    }

                    // Kosongkan blok akhir hingga pas 7 kolom (1 baris penuh)
                    $total_cells = $first_day_in_month + $total_days;
                    $remaining_cells = 7 - ($total_cells % 7);
                    if ($remaining_cells < 7) {
                        for ($i = 0; $i < $remaining_cells; $i++) {
                            echo '<div class="cal-day-cell empty-cell"></div>';
                        }
                    }
                    ?>
                </div>
            </div>

            <div style="margin-top: 15px; font-size: 11.5px; color: #64748b; background: #eff6ff; padding: 12px 15px; border-radius: 6px; border-left: 4px solid var(--mantap-blue-main); line-height: 1.5;">
                <i class="fas fa-info-circle me-1" style="color: var(--mantap-blue-main);"></i> <b style="color: var(--mantap-blue-dark);">Catatan Sistem:</b> Klik pada tanggal manapun di kalender untuk menambahkan atau mengubah keterangan hari libur. 
            </div>
        </div>
    </div>
</div>

<div class="mantap-modal" id="modalLibur">
    <div class="mantap-modal-dialog">
        <div class="mantap-modal-content">
            <div class="mantap-modal-header">
                <h5 id="modalTitle"><i class="fas fa-calendar-plus" style="color: var(--mantap-danger); margin-right: 6px;"></i> Form Hari Libur</h5>
                <button type="button" class="close-modal-btn" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" action="hari-libur.php">
                <div class="mantap-modal-body">
                    <input type="hidden" name="submit_libur" value="1">
                    <input type="hidden" name="id_libur" id="id_libur" value="0">

                    <label for="tanggal_libur">Tanggal Libur:</label>
                    <input type="date" name="tanggal_libur" id="tanggal_libur" required readonly style="background:#e2e8f0; cursor:not-allowed;">

                    <label for="keterangan">Keterangan / Nama Libur:</label>
                    <input type="text" name="keterangan" id="keterangan" placeholder="Contoh: Libur Tahun Baru Islam" required>
                    
                    <button type="submit" class="btn-modal-submit"><i class="fas fa-save"></i> Simpan Tanggal Libur</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const modal = document.getElementById('modalLibur');

    function openModal(id, tanggal, keterangan) {
        document.getElementById('id_libur').value = id;
        document.getElementById('tanggal_libur').value = tanggal;
        document.getElementById('keterangan').value = keterangan;
        
        if (id == 0 || id == '0') {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-calendar-plus" style="color: var(--mantap-danger); margin-right: 6px;"></i> Tambah Hari Libur';
        } else {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit" style="color: #f59e0b; margin-right: 6px;"></i> Edit Hari Libur';
        }
        
        modal.style.display = 'block';
        
        // Auto-focus input keterangan
        setTimeout(() => { document.getElementById('keterangan').focus(); }, 100);
    }

    function closeModal() { 
        modal.style.display = 'none'; 
    }

    function konfirmasiHapus(id, m, y) {
        Swal.fire({
            title: 'Hapus Hari Libur?', 
            text: "Tanggal libur yang dihapus akan kembali dihitung sebagai hari kerja biasa oleh sistem.",
            icon: 'warning', 
            showCancelButton: true, 
            confirmButtonColor: '#dc3545', 
            cancelButtonColor: '#64748b', 
            confirmButtonText: 'Ya, Hapus!', 
            cancelButtonText: 'Batal'
        }).then((result) => { 
            if (result.isConfirmed) {
                window.location.href = `hari-libur.php?action=delete&id=${id}&m=${m}&y=${y}`; 
            }
        });
    }

    window.onclick = function(event) { 
        if (event.target == modal) closeModal(); 
    }
</script>

<?php include 'panel/footer.php'; ?>
</body>
</html>