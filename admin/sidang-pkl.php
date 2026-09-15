<?php
// admin/sidang-pkl.php
include 'auth-check.php';
include '../config/db-koneksi.php';

$current_user_level = strtolower($_SESSION['level'] ?? 'user'); 
$current_user_id = $_SESSION['user_id'] ?? 0; // Ditambahkan untuk kebutuhan log

function formatTanggal($tanggal) {
    if (empty($tanggal) || $tanggal == '0000-00-00') return '-';
    $timestamp = strtotime($tanggal);
    $bulan_indo = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return date('d', $timestamp) . ' ' . $bulan_indo[(int)date('m', $timestamp)] . ' ' . date('Y', $timestamp);
}

// ---------------------------------------------------------------------
// --- JEMBATAN PENGHUBUNG AKUN USERS KE TABEL GURU ---
// ---------------------------------------------------------------------
$current_username = $_SESSION['username'] ?? '';
$user_full_name = '';
$guru_id = 0;

if (!empty($current_username)) {
    $q_user = $koneksi->query("SELECT full_name FROM users WHERE username = '$current_username'");
    if ($q_user && $q_user->num_rows > 0) {
        $user_full_name = trim($q_user->fetch_assoc()['full_name']);
        
        $safe_name = $koneksi->real_escape_string($user_full_name);
        $q_guru = $koneksi->query("SELECT guru_id FROM guru WHERE nama_guru = '$safe_name' LIMIT 1");
        
        if ($q_guru && $q_guru->num_rows > 0) {
            $guru_id = $q_guru->fetch_assoc()['guru_id'];
        } else {
            $q_guru_like = $koneksi->query("SELECT guru_id FROM guru WHERE nama_guru LIKE '%$safe_name%' OR '$safe_name' LIKE CONCAT('%', nama_guru, '%') LIMIT 1");
            if ($q_guru_like && $q_guru_like->num_rows > 0) {
                $guru_id = $q_guru_like->fetch_assoc()['guru_id'];
            }
        }
    }
}

if ($current_user_level !== 'admin' && $current_user_level !== 'pembimbing') {
    die("<div class='alert error'>Akses Ditolak. Hanya Administrator/Pembimbing yang diizinkan.</div>");
}

// ---------------------------------------------------------------------
// LOGIKA CETAK JADWAL SIDANG BER-KOP (DENGAN FILTER)
// ---------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'print' && $current_user_level === 'admin') {
    // Parameter Kop Surat
    $nama_sekolah = "SEKOLAH MENENGAH KEJURUAN ISLAM 1 BLITAR";
    $jurusan = "DESAIN PEMODELAN DAN INFORMASI BANGUNAN";
    $alamat_sekolah = "Jl. Musi No. 6 Blitar, 66117. Telp 081249464046";
    $website = "http://dpib.smkislam1blitar.sch.id | E-mail: dpibsmkislam1blitar@gmail.com";
    $nama_kaprog = "Mochamad Ade Satria, S.T.";

    // --- LOGIKA FILTER PENCETAKAN ---
    $filter_type = $_GET['filter_type'] ?? 'semua';
    $where_print = "1=1";
    $print_title_suffix = "";
    $filter_log_desc = "Semua Jadwal";

    if ($filter_type === 'ruang' && !empty($_GET['filter_ruang'])) {
        $r = $koneksi->real_escape_string($_GET['filter_ruang']);
        $where_print .= " AND s.ruangan_ujian = '$r'";
        $print_title_suffix = "<br><small style='font-size:10pt; font-weight:normal; color:#475569;'>Filter Ruangan: " . htmlspecialchars($_GET['filter_ruang']) . "</small>";
        $filter_log_desc = "Ruang " . htmlspecialchars($_GET['filter_ruang']);
    } elseif ($filter_type === 'tanggal' && !empty($_GET['filter_tanggal'])) {
        $t = $koneksi->real_escape_string($_GET['filter_tanggal']);
        $where_print .= " AND s.tanggal_ujian = '$t'";
        $hari_f = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][date('w', strtotime($t))];
        $print_title_suffix = "<br><small style='font-size:10pt; font-weight:normal; color:#475569;'>Filter Waktu: " . $hari_f . ", " . formatTanggal($t) . "</small>";
        $filter_log_desc = "Tanggal " . formatTanggal($t);
    } elseif ($filter_type === 'guru' && !empty($_GET['filter_guru'])) {
        $g = (int)$_GET['filter_guru'];
        $where_print .= " AND (s.penguji_1 = $g OR s.penguji_2 = $g OR l.guru_id = $g)";
        
        $q_tg = $koneksi->query("SELECT nama_guru FROM guru WHERE guru_id = $g");
        if($q_tg && $q_tg->num_rows > 0) {
            $nm_guru = htmlspecialchars($q_tg->fetch_assoc()['nama_guru']);
            $print_title_suffix = "<br><small style='font-size:10pt; font-weight:normal; color:#475569;'>Filter Personal (Penguji/Pembimbing): " . $nm_guru . "</small>";
            $filter_log_desc = "Personal Guru (" . $nm_guru . ")";
        }
    }

    // --- TRIGGER LOG AKTIVITAS (PRINT/PREVIEW JADWAL) ---
    catatLog($koneksi, $current_user_id, "Mencetak / mem-preview jadwal sidang PKL (Filter: {$filter_log_desc})");

    $q_print = $koneksi->query("
        SELECT s.tanggal_ujian, s.waktu_sidang, s.ruangan_ujian, p.nama, p.kelas, l.nama_lokasi, gp.nama_guru as pembimbing, g1.nama_guru as p1, g2.nama_guru as p2
        FROM sidang_pkl s
        JOIN peserta_didik p ON s.siswa_id = p.id
        LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
        LEFT JOIN guru gp ON l.guru_id = gp.guru_id
        LEFT JOIN guru g1 ON s.penguji_1 = g1.guru_id
        LEFT JOIN guru g2 ON s.penguji_2 = g2.guru_id
        WHERE $where_print
        ORDER BY s.ruangan_ujian ASC, s.tanggal_ujian ASC, s.waktu_sidang ASC
    ");
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>Cetak Jadwal Sidang PKL</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
        <style>
            body { font-family: 'Times New Roman', Times, serif; color: #000; background: #fff; padding: 10px 30px; font-size: 11pt; line-height: 1.4; }
            .kop { display: flex; justify-content: space-between; align-items: center; border-bottom: 4px double #000; padding-bottom: 10px; margin-top: 0px; margin-bottom: 20px; }
            .kop .logo { width: 90px; height: 90px; object-fit: contain; }
            .kop .info-sekolah { text-align: center; flex-grow: 1; }
            .kop .info-sekolah h2 { margin: 0; line-height: 1.1; font-weight: bold; font-size: 13.5pt; text-transform: uppercase; white-space: nowrap; }
            .kop .info-sekolah h3 { margin: 4px 0; font-size: 12pt; font-weight: bold; text-transform: uppercase; }
            .kop .info-sekolah p { margin: 1px 0; font-size: 9.5pt; font-style: normal; }

            .document-title { text-align: center; font-weight: bold; font-size: 12pt; text-decoration: underline; text-transform: uppercase; margin-bottom: 20px; margin-top: 5px; letter-spacing: 0.5px; }
            
            .ruang-title { font-size: 12.5pt; font-weight: bold; margin-top: 25px; margin-bottom: 10px; border-left: 5px solid #000; padding-left: 10px; text-transform: uppercase; background: #f8fafc; padding: 6px 10px; }
            .report-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 10pt; }
            .report-table th, .report-table td { padding: 8px 6px; border: 1px solid #000; vertical-align: middle; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .report-table th { font-weight: bold; text-transform: uppercase; text-align: center; background-color: #f1f5f9 !important; }
            
            .tengah { text-align: center; }

            .print-footer { display: flex; justify-content: flex-end; margin-top: 50px; page-break-inside: avoid; }
            .ttd-box { text-align: center; width: 280px; }
            .ttd-box p { margin: 0; line-height: 1.5; }

            @media print {
                body { padding: 0; background: #fff; }
                .no-print { display: none !important; }
                @page { margin: 1.5cm; size: A4 landscape; }
            }
        </style>
    </head>
    <body>
        <div class="no-print" style="background: #f1f5f9; padding: 12px 25px; margin: -10px -30px 25px -30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #cbd5e1; font-family:'Poppins', sans-serif;">
            <span style="font-weight: 600; color: #475569;"><i class="fas fa-print me-2"></i>Dokumen PDF Siap Cetak (A4 Landscape)</span>
            <div style="display: flex; gap: 8px;">
                <button onclick="window.close();" style="padding: 6px 14px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; font-weight: 600; cursor: pointer; color: #475569;">Tutup</button>
                <button onclick="window.print();" style="padding: 6px 18px; border-radius: 6px; border: none; background: #10b981; color: #fff; font-weight: 600; cursor: pointer;">Cetak Berkas</button>
            </div>
        </div>

        <div class="kop">
            <img src="../img/logosekolah.png" alt="Logo Sekolah" class="logo">
            <div class="info-sekolah">
                <h2><?php echo htmlspecialchars(strtoupper($jurusan)); ?></h2> 
                <h3><?php echo htmlspecialchars(strtoupper($nama_sekolah)); ?></h3> 
                <p><?php echo htmlspecialchars($alamat_sekolah); ?></p>
                <p><?php echo htmlspecialchars($website); ?></p>
            </div>
            <img src="../img/logobangunan.png" alt="Logo DPIB" class="logo">
        </div>

        <div class="document-title">
            JADWAL PELAKSANAAN SIDANG PRAKTIK KERJA LAPANGAN (PKL)<br>TAHUN PELAJARAN <?php echo date('Y') . '/' . (date('Y')+1); ?>
            <?php echo $print_title_suffix; ?>
        </div>
        
        <?php if($q_print && $q_print->num_rows > 0): ?>
            <?php 
            $current_ruang = '';
            $no = 1; 
            
            // --- Palet Warna Pastel Super Beragam Khusus Print (35 Warna) ---
            $color_palette = [
                '#e0f2fe', '#f3e8ff', '#fef08a', '#dcfce7', '#fce7f3', 
                '#ffedd5', '#ccfbf1', '#e0e7ff', '#fae8ff', '#ffe4e6',
                '#ecfccb', '#fca5a5', '#fcd34d', '#86efac', '#93c5fd',
                '#c4b5fd', '#d8b4fe', '#f9a8d4', '#fda4af', '#fecaca',
                '#ffb3ba', '#ffdfba', '#ffffba', '#baffc9', '#bae1ff',
                '#d4a5a5', '#ffc4e1', '#e2f0cb', '#c6d8ff', '#bbf7d0',
                '#99f6e4', '#bfdbfe', '#ddd6fe', '#fbcfe8', '#fecdd3'
            ];
            $teacher_colors = [];
            $color_index = 0;

            function getTeacherBgColor($nama, &$teacher_colors, $color_palette, &$color_index) {
                if (empty(trim($nama)) || $nama == '-') return '#ffffff';
                $key = strtolower(trim($nama));
                if (!isset($teacher_colors[$key])) {
                    $teacher_colors[$key] = $color_palette[$color_index % count($color_palette)];
                    $color_index++;
                }
                return $teacher_colors[$key];
            }

            while($r = $q_print->fetch_assoc()): 
                // Deteksi Perubahan Ruangan
                if ($current_ruang !== $r['ruangan_ujian']) {
                    if ($current_ruang !== '') {
                        echo "</tbody></table>"; // Tutup tabel sebelumnya
                    }
                    $current_ruang = $r['ruangan_ujian'];
                    $no = 1; // Reset nomor urut per ruangan
                    
                    echo "<div class='ruang-title'>Ruangan : " . htmlspecialchars($current_ruang) . "</div>";
                    echo "<table class='report-table'>
                        <thead>
                            <tr>
                                <th style='width:30px;'>No</th>
                                <th style='width:120px;'>Hari, Tanggal</th>
                                <th style='width:80px;'>Waktu</th>
                                <th style='width:170px;'>Nama Siswa (Kelas)</th>
                                <th style='width:160px;'>Lokasi PKL</th>
                                <th style='width:150px;'>Pembimbing</th>
                                <th style='width:150px;'>Penguji 1</th>
                                <th style='width:150px;'>Penguji 2</th>
                            </tr>
                        </thead>
                        <tbody>";
                }

                $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][date('w', strtotime($r['tanggal_ujian']))];
                $tgl = date('d/m/Y', strtotime($r['tanggal_ujian']));
                
                $bg_pem = getTeacherBgColor($r['pembimbing'], $teacher_colors, $color_palette, $color_index);
                $bg_p1  = getTeacherBgColor($r['p1'], $teacher_colors, $color_palette, $color_index);
                $bg_p2  = getTeacherBgColor($r['p2'], $teacher_colors, $color_palette, $color_index);
            ?>
            <tr>
                <td class="tengah"><?= $no++ ?></td>
                <td><?= $hari . ', ' . $tgl ?></td>
                <td class="tengah"><b><?= $r['waktu_sidang'] ?></b></td>
                <td><b><?= strtoupper($r['nama']) ?></b><br><?= $r['kelas'] ?></td>
                <td><?= $r['nama_lokasi'] ?: '-' ?></td>
                <td style="background-color: <?= $bg_pem ?> !important;"><?= $r['pembimbing'] ?: '-' ?></td>
                <td style="background-color: <?= $bg_p1 ?> !important;"><?= $r['p1'] ?: '-' ?></td>
                <td style="background-color: <?= $bg_p2 ?> !important;"><?= $r['p2'] ?: '-' ?></td>
            </tr>
            <?php endwhile; ?>
            </tbody></table> <!-- Tutup tabel terakhir -->
        <?php else: ?>
            <p style="text-align:center; font-style:italic;">Belum ada jadwal sidang yang sesuai dengan filter tersebut.</p>
        <?php endif; ?>

        <div class="print-footer">
            <div class="ttd-box">
                <p style="margin-bottom: 80px;">Blitar, <?= date('d F Y') ?><br>Ketua Jurusan DPIB,</p>
                <p style="font-weight: bold; text-decoration: underline;"><?php echo htmlspecialchars($nama_kaprog); ?></p>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

$error_session = '';
if ($current_user_level === 'pembimbing' && $guru_id == 0) {
    $error_session = "<div class='alert error' style='margin-bottom:20px;'><i class='fas fa-exclamation-triangle'></i> <b>Perhatian:</b> Nama akun Anda (<b>$user_full_name</b>) tidak ditemukan di data Guru. Pastikan nama di tabel Users dan tabel Guru sama persis.</div>";
}

$message = '';

// 1. AUTO-CREATE TABLE SIDANG PKL
$koneksi->query("CREATE TABLE IF NOT EXISTS sidang_pkl (
    id_sidang INT AUTO_INCREMENT PRIMARY KEY, siswa_id INT NOT NULL, tanggal_ujian DATE NOT NULL,
    waktu_sidang VARCHAR(50) NOT NULL, ruangan_ujian VARCHAR(100) NOT NULL,
    penguji_1 INT NOT NULL, penguji_2 INT NOT NULL, keterangan TEXT
)");

// 1.B AUTO-CREATE TABLE KRITERIA
$koneksi->query("CREATE TABLE IF NOT EXISTS kriteria_sidang (
    id INT AUTO_INCREMENT PRIMARY KEY, nama_kriteria VARCHAR(100) NOT NULL, bobot FLOAT NOT NULL
)");
$cek_kriteria = $koneksi->query("SELECT COUNT(*) as tot FROM kriteria_sidang");
if ($cek_kriteria && $cek_kriteria->fetch_assoc()['tot'] == 0) {
    $koneksi->query("INSERT INTO kriteria_sidang (nama_kriteria, bobot) VALUES ('Sistematika Laporan', 20), ('Penguasaan Materi', 20), ('Penyajian / Presentasi', 20), ('Sikap & Penampilan', 20), ('Tanya Jawab', 20)");
}

$kriteria = [];
$q_k = $koneksi->query("SELECT * FROM kriteria_sidang ORDER BY id ASC");
if ($q_k) { while($k = $q_k->fetch_assoc()){ $kriteria[] = $k; } }
if (count($kriteria) == 0) { $kriteria[] = ['id'=>0, 'nama_kriteria'=>'', 'bobot'=>0]; }

// DEFINISI DATA DROPDOWN WAKTU
$ruang_options = ['Lab APL 1', 'Lab APL 2', 'Bimasena Studio', 'Aula Kampus 1', 'Ruang Rapat Kampus 1'];
$waktu_options = [];
$start_time = strtotime('07:00');
$end_time = strtotime('14:00');
while ($start_time < $end_time) {
    $slot_end = strtotime('+30 minutes', $start_time);
    $waktu_options[] = date('H:i', $start_time) . ' - ' . date('H:i', $slot_end) . ' WIB';
    $start_time = $slot_end;
}

// ---------------------------------------------------------------------
// LOGIKA HAPUS JADWAL (HANYA ADMIN)
// ---------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id']) && $current_user_level === 'admin') {
    $id_del = (int)$_GET['id'];
    
    // --- AMBIL NAMA SISWA SEBELUM DIHAPUS UNTUK LOG ---
    $nama_siswa_del = "ID " . $id_del;
    $cek_siswa = $koneksi->query("SELECT p.nama FROM sidang_pkl s JOIN peserta_didik p ON s.siswa_id = p.id WHERE s.id_sidang = $id_del");
    if ($cek_siswa && $cek_siswa->num_rows > 0) {
        $nama_siswa_del = $cek_siswa->fetch_assoc()['nama'];
    }

    $stmt_del = $koneksi->prepare("DELETE FROM sidang_pkl WHERE id_sidang = ?");
    $stmt_del->bind_param("i", $id_del);
    if ($stmt_del->execute()) { 
        // --- TRIGGER LOG AKTIVITAS (DELETE JADWAL) ---
        catatLog($koneksi, $current_user_id, "Menghapus jadwal sidang PKL untuk siswa: " . $nama_siswa_del);

        header("Location: sidang-pkl.php?status=deleted"); 
        exit(); 
    }
}

// ---------------------------------------------------------------------
// LOGIKA HAPUS SEMUA JADWAL (HANYA ADMIN)
// ---------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'delete_all' && $current_user_level === 'admin') {
    @$koneksi->query("DELETE FROM penilaian_sidang"); // Bersihkan nilai agar foreign key aman
    if ($koneksi->query("DELETE FROM sidang_pkl")) { 
        $koneksi->query("ALTER TABLE sidang_pkl AUTO_INCREMENT = 1");

        // --- TRIGGER LOG AKTIVITAS (DELETE SEMUA JADWAL) ---
        catatLog($koneksi, $current_user_id, "Mereset dan menghapus seluruh jadwal pelaksanaan sidang PKL secara masal");

        header("Location: sidang-pkl.php?status=deleted_all"); 
        exit(); 
    }
}

// ---------------------------------------------------------------------
// LOGIKA GENERATE JADWAL ACAK OTOMATIS BERURUTAN (HARI KERJA SAJA)
// ---------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_auto_sidang']) && $current_user_level === 'admin') {
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    $start = strtotime($start_date);
    $end = strtotime($end_date);
    
    if ($start > $end) {
        $message = "<div class='alert error'>❌ Gagal: Tanggal Akhir tidak boleh lebih kecil dari Tanggal Mulai.</div>";
    } else {
        $q_unscheduled = $koneksi->query("
            SELECT p.id, l.lokasi_id, l.guru_id AS id_pembimbing 
            FROM peserta_didik p 
            LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id 
            WHERE p.id NOT IN (SELECT siswa_id FROM sidang_pkl)
            ORDER BY l.lokasi_id ASC, p.nama ASC
        ");
        
        $students_by_loc = [];
        while ($r = $q_unscheduled->fetch_assoc()) { 
            $loc = $r['lokasi_id'] ?: '0';
            $students_by_loc[$loc][] = $r; 
        }

        if (empty($students_by_loc)) {
            $message = "<div class='alert warning'>⚠️ Semua siswa sudah memiliki jadwal sidang.</div>";
        } else {
            $q_guru = $koneksi->query("SELECT guru_id FROM guru");
            $teachers = [];
            while ($r = $q_guru->fetch_assoc()) { $teachers[] = $r['guru_id']; }

            $valid_dates = [];
            for ($i = $start; $i <= $end; $i += 86400) {
                $dayOfWeek = date('N', $i);
                if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
                    $valid_dates[] = date('Y-m-d', $i);
                }
            }

            $used_slots = []; 
            $busy_teachers = []; 
            $q_exist = $koneksi->query("SELECT tanggal_ujian, waktu_sidang, ruangan_ujian, penguji_1, penguji_2 FROM sidang_pkl");
            while ($r = $q_exist->fetch_assoc()) {
                $slot_key = $r['tanggal_ujian'] . '_' . $r['waktu_sidang'] . '_' . $r['ruangan_ujian'];
                $time_key = $r['tanggal_ujian'] . '_' . $r['waktu_sidang'];
                $used_slots[$slot_key] = true;
                if (!isset($busy_teachers[$time_key])) $busy_teachers[$time_key] = [];
                $busy_teachers[$time_key][] = $r['penguji_1'];
                $busy_teachers[$time_key][] = $r['penguji_2'];
            }

            $day_rooms = [];
            foreach ($valid_dates as $vd) {
                foreach ($ruang_options as $ro) {
                    $day_rooms[] = ['date' => $vd, 'room' => $ro];
                }
            }
            shuffle($day_rooms);

            $success_count = 0;
            $fail_count = 0;

            foreach ($students_by_loc as $loc_id => $group_students) {
                $group_size = count($group_students);
                $assigned_count = 0;
                $last_p1 = null; 
                $last_p2 = null;

                foreach ($day_rooms as $dr) {
                    $vd = $dr['date'];
                    $ro = $dr['room'];
                    
                    foreach ($waktu_options as $wt) {
                        if ($assigned_count >= $group_size) break;

                        $slot_key = $vd . '_' . $wt . '_' . $ro;
                        if (isset($used_slots[$slot_key])) continue;

                        $stu = $group_students[$assigned_count];
                        $pembimbing_id = $stu['id_pembimbing'];
                        $time_key = $vd . '_' . $wt;
                        $busy_now = $busy_teachers[$time_key] ?? [];

                        $p1 = null; $p2 = null;
                        if ($last_p1 && $last_p2 && !in_array($last_p1, $busy_now) && !in_array($last_p2, $busy_now) && $last_p1 != $pembimbing_id && $last_p2 != $pembimbing_id) {
                            $p1 = $last_p1; $p2 = $last_p2;
                        } else {
                            $avail_t = [];
                            foreach ($teachers as $t_id) {
                                if ($t_id != $pembimbing_id && !in_array($t_id, $busy_now)) {
                                    $avail_t[] = $t_id;
                                }
                            }
                            if (count($avail_t) >= 2) {
                                shuffle($avail_t);
                                $p1 = $avail_t[0]; 
                                $p2 = $avail_t[1];
                                $last_p1 = $p1; 
                                $last_p2 = $p2;
                            }
                        }

                        if ($p1 && $p2) {
                            $stmt_ins = $koneksi->prepare("INSERT INTO sidang_pkl (siswa_id, tanggal_ujian, waktu_sidang, ruangan_ujian, penguji_1, penguji_2, keterangan) VALUES (?, ?, ?, ?, ?, ?, 'Jadwal Generate Acak')");
                            $stmt_ins->bind_param("isssii", $stu['id'], $vd, $wt, $ro, $p1, $p2);
                            
                            if ($stmt_ins->execute()) {
                                $used_slots[$slot_key] = true;
                                $busy_teachers[$time_key][] = $p1;
                                $busy_teachers[$time_key][] = $p2;
                                $success_count++;
                                $assigned_count++;
                            }
                            $stmt_ins->close();
                        }
                    }
                    if ($assigned_count >= $group_size) break; 
                }
                
                if ($assigned_count < $group_size) {
                    $fail_count += ($group_size - $assigned_count);
                }
            }

            // --- TRIGGER LOG AKTIVITAS (GENERATE JADWAL) ---
            if ($success_count > 0 || $fail_count > 0) {
                catatLog($koneksi, $current_user_id, "Meng-generate jadwal sidang PKL secara acak/otomatis (Sukses: {$success_count} siswa dijadwalkan, Gagal: {$fail_count})");
            }

            $message = "<div class='alert success'>✅ Pembuatan Otomatis Selesai: <b>$success_count siswa</b> berhasil dijadwalkan secara terurut. " . ($fail_count > 0 ? "($fail_count siswa gagal karena kehabisan ruang/penguji luang)" : "") . "</div>";
        }
    }
}

if (isset($_GET['status'])) {
    if ($_GET['status'] == 'deleted') $message = "<div class='alert success'>✅ Jadwal sidang berhasil dihapus.</div>";
    if ($_GET['status'] == 'deleted_all') $message = "<div class='alert success'>✅ Seluruh jadwal sidang telah dikosongkan.</div>";
    if ($_GET['status'] == 'kriteria_ok') $message = "<div class='alert success'>✅ Format Kriteria Penilaian Sidang berhasil diperbarui. Pastikan Total Bobot 100%.</div>";
    if ($_GET['status'] == 'kriteria_err') $message = "<div class='alert error'>❌ Format Kriteria Gagal Disimpan: Total Bobot Harus 100%.</div>";
}

// ---------------------------------------------------------------------
// LOGIKA SIMPAN KRITERIA (HANYA ADMIN)
// ---------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_kriteria']) && $current_user_level === 'admin') {
    $total_input_bobot = 0;
    if (isset($_POST['bobot_k']) && is_array($_POST['bobot_k'])) {
        foreach($_POST['bobot_k'] as $b) { $total_input_bobot += (float)$b; }
    }

    if (round($total_input_bobot) !== 100.0) {
        header("Location: sidang-pkl.php?status=kriteria_err"); exit();
    }

    $koneksi->query("TRUNCATE TABLE kriteria_sidang"); 
    if (isset($_POST['nama_k']) && is_array($_POST['nama_k'])) {
        for ($i=0; $i<count($_POST['nama_k']); $i++) {
            $nama = trim($_POST['nama_k'][$i]); $bobot = (float)$_POST['bobot_k'][$i];
            if (!empty($nama)) {
                $koneksi->query("INSERT INTO kriteria_sidang (nama_kriteria, bobot) VALUES ('".$koneksi->real_escape_string($nama)."', '$bobot')");
            }
        }
    }

    // --- TRIGGER LOG AKTIVITAS (UPDATE KRITERIA) ---
    catatLog($koneksi, $current_user_id, "Memperbarui format dan rasio bobot persentase Kriteria Penilaian Sidang PKL");

    header("Location: sidang-pkl.php?status=kriteria_ok"); exit();
}

// ---------------------------------------------------------------------
// LOGIKA SIMPAN / UPDATE JADWAL MANUAL
// ---------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_sidang']) && $current_user_level === 'admin') {
    $id_sidang = (int)$_POST['id_sidang']; $siswa_id = (int)$_POST['siswa_id'];
    $tanggal_ujian = trim($_POST['tanggal_ujian']); $waktu_sidang = trim($_POST['waktu_sidang']);
    $ruangan_ujian = trim($_POST['ruangan_ujian']); $penguji_1 = (int)$_POST['penguji_1'];
    $penguji_2 = (int)$_POST['penguji_2']; $keterangan = trim($_POST['keterangan']);

    $stmt_cek = $koneksi->prepare("SELECT id_sidang FROM sidang_pkl WHERE siswa_id = ? AND id_sidang != ?");
    $stmt_cek->bind_param("ii", $siswa_id, $id_sidang); $stmt_cek->execute();
    if ($stmt_cek->get_result()->num_rows > 0) { 
        $message = "<div class='alert error'>❌ Gagal: Siswa sudah memiliki jadwal.</div>"; 
    } elseif ($penguji_1 === $penguji_2) { 
        $message = "<div class='alert error'>❌ Gagal: Penguji 1 dan 2 tidak boleh sama.</div>"; 
    } else {
        $stmt_pem = $koneksi->prepare("SELECT l.guru_id FROM peserta_didik p JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id WHERE p.id = ?");
        $stmt_pem->bind_param("i", $siswa_id); $stmt_pem->execute();
        $pembimbing_id = $stmt_pem->get_result()->fetch_assoc()['guru_id'] ?? 0; $stmt_pem->close();

        if ($penguji_1 === $pembimbing_id || $penguji_2 === $pembimbing_id) {
            $message = "<div class='alert error'>❌ Gagal: Pembimbing tidak diizinkan merangkap sebagai penguji.</div>";
        } else {
            $stmt_bentrok = $koneksi->prepare("SELECT ruangan_ujian, penguji_1, penguji_2 FROM sidang_pkl WHERE tanggal_ujian = ? AND waktu_sidang = ? AND id_sidang != ? AND (penguji_1 IN (?, ?) OR penguji_2 IN (?, ?) OR ruangan_ujian = ?)");
            $stmt_bentrok->bind_param("ssiiiiis", $tanggal_ujian, $waktu_sidang, $id_sidang, $penguji_1, $penguji_2, $penguji_1, $penguji_2, $ruangan_ujian);
            $stmt_bentrok->execute();
            $res_bentrok = $stmt_bentrok->get_result();
            
            if ($res_bentrok->num_rows > 0) { 
                $row_b = $res_bentrok->fetch_assoc();
                if (strcasecmp(trim($row_b['ruangan_ujian']), trim($ruangan_ujian)) == 0) {
                    $message = "<div class='alert error'>❌ Gagal: <b>Ruangan ($ruangan_ujian)</b> sudah dibooking/terpakai pada tanggal dan jam tersebut.</div>";
                } else {
                    $message = "<div class='alert error'>❌ Gagal: Salah satu Penguji memiliki jadwal bentrok pada tanggal dan jam tersebut.</div>";
                }
            } else {
                // Ambil Nama Siswa untuk Log
                $nama_siswa_log = "ID " . $siswa_id;
                $cek_s = $koneksi->query("SELECT nama FROM peserta_didik WHERE id = $siswa_id");
                if ($cek_s && $cek_s->num_rows > 0) {
                    $nama_siswa_log = $cek_s->fetch_assoc()['nama'];
                }

                if ($id_sidang > 0) {
                    // --- DETEKSI PERUBAHAN DATA UNTUK LOG ---
                    $stmt_old = $koneksi->prepare("SELECT siswa_id, tanggal_ujian, waktu_sidang, ruangan_ujian, penguji_1, penguji_2, keterangan FROM sidang_pkl WHERE id_sidang = ?");
                    $stmt_old->bind_param("i", $id_sidang);
                    $stmt_old->execute();
                    $old = $stmt_old->get_result()->fetch_assoc();
                    $stmt_old->close();

                    $perubahan = [];
                    if($old['siswa_id'] != $siswa_id) $perubahan[] = "Peserta Sidang";
                    if($old['tanggal_ujian'] != $tanggal_ujian) $perubahan[] = "Tanggal Pelaksanaan";
                    if($old['waktu_sidang'] != $waktu_sidang) $perubahan[] = "Waktu Sidang";
                    if($old['ruangan_ujian'] != $ruangan_ujian) $perubahan[] = "Ruangan/Tempat";
                    if($old['penguji_1'] != $penguji_1) $perubahan[] = "Penguji 1";
                    if($old['penguji_2'] != $penguji_2) $perubahan[] = "Penguji 2";
                    if($old['keterangan'] != $keterangan) $perubahan[] = "Keterangan Tambahan";

                    $pesan_log = count($perubahan) > 0 
                        ? "Memperbarui jadwal sidang PKL untuk siswa: {$nama_siswa_log} (Detail yang diubah: " . implode(", ", $perubahan) . ")"
                        : "Menyimpan ulang jadwal sidang PKL untuk siswa: {$nama_siswa_log} (Tanpa perubahan data)";
                    // ----------------------------------------

                    $stmt = $koneksi->prepare("UPDATE sidang_pkl SET siswa_id=?, tanggal_ujian=?, waktu_sidang=?, ruangan_ujian=?, penguji_1=?, penguji_2=?, keterangan=? WHERE id_sidang=?");
                    $stmt->bind_param("isssiisi", $siswa_id, $tanggal_ujian, $waktu_sidang, $ruangan_ujian, $penguji_1, $penguji_2, $keterangan, $id_sidang);
                    
                    if ($stmt->execute()) { 
                        catatLog($koneksi, $current_user_id, $pesan_log); // Log Edit
                        $message = "<div class='alert success'>✅ Jadwal Sidang berhasil diperbarui.</div>"; 
                    } 
                    else { $message = "<div class='alert error'>❌ Error database: " . $koneksi->error . "</div>"; }

                } else {
                    $stmt = $koneksi->prepare("INSERT INTO sidang_pkl (siswa_id, tanggal_ujian, waktu_sidang, ruangan_ujian, penguji_1, penguji_2, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("isssiis", $siswa_id, $tanggal_ujian, $waktu_sidang, $ruangan_ujian, $penguji_1, $penguji_2, $keterangan);
                    
                    if ($stmt->execute()) { 
                        catatLog($koneksi, $current_user_id, "Membuat jadwal sidang PKL baru untuk siswa: {$nama_siswa_log} (Ruang: {$ruangan_ujian}, Waktu: {$waktu_sidang})"); // Log Tambah
                        $message = "<div class='alert success'>✅ Jadwal Sidang berhasil disimpan.</div>"; 
                    } 
                    else { $message = "<div class='alert error'>❌ Error database: " . $koneksi->error . "</div>"; }
                }
                $stmt->close();
            }
            $stmt_bentrok->close();
        }
    }
    $stmt_cek->close();
}

// 4. AMBIL DATA UNTUK FORM & TABEL
$guru_data = $koneksi->query("SELECT guru_id, nama_guru FROM guru ORDER BY nama_guru ASC")->fetch_all(MYSQLI_ASSOC);
$siswa_data = $koneksi->query("SELECT p.id, p.nama, p.kelas, l.nama_lokasi, l.guru_id AS id_pembimbing, g.nama_guru AS nama_pembimbing, la.file_laporan, la.file_ppt, (SELECT COUNT(id_sidang) FROM sidang_pkl WHERE siswa_id = p.id) AS is_scheduled FROM peserta_didik p LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id LEFT JOIN guru g ON l.guru_id = g.guru_id LEFT JOIN laporan_akhir la ON p.id = la.siswa_id ORDER BY p.nama ASC")->fetch_all(MYSQLI_ASSOC);

// DATA DISTINCT UNTUK FILTER MODAL CETAK
$distinct_dates_q = $koneksi->query("SELECT DISTINCT tanggal_ujian FROM sidang_pkl ORDER BY tanggal_ujian ASC");
$distinct_dates = [];
if($distinct_dates_q) { while($d = $distinct_dates_q->fetch_assoc()){ $distinct_dates[] = $d['tanggal_ujian']; } }

$distinct_rooms_q = $koneksi->query("SELECT DISTINCT ruangan_ujian FROM sidang_pkl ORDER BY ruangan_ujian ASC");
$distinct_rooms = [];
if($distinct_rooms_q) { while($r = $distinct_rooms_q->fetch_assoc()){ $distinct_rooms[] = $r['ruangan_ujian']; } }

// FILTER WHERE CLAUSE TABEL UTAMA (MENYESUAIKAN HAK AKSES GURU/PEMBIMBING/PENGUJI)
$safe_user_full_name = $koneksi->real_escape_string($user_full_name);
$where_clause = "1=1";
if ($current_user_level !== 'admin') {
    $nama_filter = !empty($user_full_name) ? " OR g_p1.nama_guru = '$safe_user_full_name' OR g_p2.nama_guru = '$safe_user_full_name' OR g_pem.nama_guru = '$safe_user_full_name'" : "";
    $where_clause = "(s.penguji_1 = '$guru_id' OR s.penguji_2 = '$guru_id' OR l.guru_id = '$guru_id' $nama_filter)";
}

$jadwal_query = "
    SELECT 
        s.id_sidang, s.tanggal_ujian, s.waktu_sidang, s.ruangan_ujian, s.keterangan, s.siswa_id, s.penguji_1, s.penguji_2,
        p.nama AS nama_siswa, p.kelas,
        l.nama_lokasi,
        g_pem.nama_guru AS nama_pembimbing,
        g_p1.guru_id AS id_p1, g_p1.nama_guru AS nama_p1,
        g_p2.guru_id AS id_p2, g_p2.nama_guru AS nama_p2,
        la.file_laporan, la.file_ppt,
        ps1.total_nilai AS nilai_p1, ps1.keputusan AS kep_p1,
        ps2.total_nilai AS nilai_p2, ps2.keputusan AS kep_p2
    FROM sidang_pkl s
    JOIN peserta_didik p ON s.siswa_id = p.id
    LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
    LEFT JOIN guru g_pem ON l.guru_id = g_pem.guru_id
    LEFT JOIN guru g_p1 ON s.penguji_1 = g_p1.guru_id
    LEFT JOIN guru g_p2 ON s.penguji_2 = g_p2.guru_id
    LEFT JOIN laporan_akhir la ON s.siswa_id = la.siswa_id
    LEFT JOIN penilaian_sidang ps1 ON s.id_sidang = ps1.id_sidang AND ps1.penguji_id = s.penguji_1
    LEFT JOIN penilaian_sidang ps2 ON s.id_sidang = ps2.id_sidang AND ps2.penguji_id = s.penguji_2
    WHERE $where_clause
    ORDER BY s.tanggal_ujian ASC, s.waktu_sidang ASC
";
$jadwal_sidang = $koneksi->query($jadwal_query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Manajemen Sidang PKL | Si Mantap PKL</title>
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
            --mantap-purple: #6f42c1;
        }

        body { font-family: 'Poppins', sans-serif; background-color: #f8fafc; color: #334155; margin: 0; overflow-x: hidden !important; }
        .main-content-wrapper { max-width: 100% !important; width: 100% !important; box-sizing: border-box !important; box-shadow: none !important;}
        .admin-main-content { padding: 20px 25px 30px 25px !important; box-sizing: border-box !important; width: 100% !important; clear: both; }

        .page-header-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .page-header-controls h1 { font-weight: 700; color: #0f172a; font-size: 1.8rem; margin: 0; position: relative; }
        .page-header-controls h1::after { content: ''; position: absolute; left: 0; bottom: -5px; width: 50px; height: 4px; background: var(--mantap-blue-main); border-radius: 2px; }

        /* SEARCH BAR STYLE */
        .header-actions-group { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .search-wrapper { position: relative; display: flex; align-items: center; }
        .search-wrapper i { position: absolute; left: 15px; color: #64748b; font-size: 14px; }
        .search-input { 
            padding: 9px 15px 9px 38px; border: 1px solid #cbd5e1; border-radius: 20px; 
            font-family: 'Poppins', sans-serif; font-size: 13px; width: 260px; transition: all 0.3s ease; 
            outline: none; background: #f8fafc;
        }
        .search-input:focus { border-color: var(--mantap-blue-main); width: 300px; background: white; box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.15); }

        .btn-add { background-color: #22c55e; color: white !important; padding: 9px 20px; border: none; border-radius: 25px; cursor: pointer; font-size: 12.5px; font-weight: 600; font-family: 'Poppins', sans-serif; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.15); text-decoration: none; }
        .btn-add:hover { background-color: #16a34a; }
        
        .btn-add.secondary { background-color: #0ea5e9; box-shadow: 0 4px 6px -1px rgba(14, 165, 233, 0.15); }
        .btn-add.secondary:hover { background-color: #0284c7; }

        .btn-add.danger { background-color: var(--mantap-danger); box-shadow: 0 4px 6px -1px rgba(220, 38, 38, 0.15); }
        .btn-add.danger:hover { background-color: #b91c1c; }

        .btn-print-jadwal { background-color: var(--mantap-purple); color: white !important; padding: 9px 20px; border: none; border-radius: 25px; cursor: pointer; font-size: 12.5px; font-weight: 600; font-family: 'Poppins', sans-serif; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 6px -1px rgba(111, 66, 193, 0.2); text-decoration: none; }
        .btn-print-jadwal:hover { background-color: #5a32a3; }
        
        .btn-kriteria { background-color: #3b82f6; color: white !important; padding: 9px 20px; border: none; border-radius: 25px; cursor: pointer; font-size: 12.5px; font-weight: 600; font-family: 'Poppins', sans-serif; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.15); text-decoration: none; }
        .btn-kriteria:hover { background-color: #2563eb; }

        .alert { padding: 12px 15px; margin-bottom: 20px; border-radius: 8px; border: 1px solid; font-size: 13.5px; font-weight: 500; text-align: left; }
        .alert.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert.error { background-color: #fef2f2; color: #ef4444; border-color: #fecaca; }
        .alert.warning { background-color: #fffbeb; color: #d97706; border-color: #fde68a; }

        .glass-panel-table { background: white !important; padding: 25px !important; border-radius: 16px !important; border: 2px solid #e2e8f0 !important; box-sizing: border-box; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); width: 100%; }
        .table-container-fixed { width: 100%; overflow-x: auto; box-sizing: border-box; border: none !important; }

        .custom-table-core { width: 100%; table-layout: fixed; border-collapse: collapse; background: white; border-radius: 4px; overflow: hidden; border: 2px solid #1e40af; min-width: 1150px; }
        .custom-table-core th { background: #1e40af; color: white; padding: 14px 10px; font-size: 12px; text-transform: uppercase; font-weight: 700; text-align: center; border: 1px solid #cbd5e1; box-sizing: border-box; }
        .custom-table-core td { padding: 12px 10px; border: 1px solid #e2e8f0; font-size: 13px; vertical-align: middle; background-color: white !important; box-sizing: border-box; }
        .custom-table-core tbody tr:hover td { background-color: #f8fafc !important; }

        .custom-table-core th.col-no { width: 40px; }
        .custom-table-core th.col-waktu { width: 13%; }
        .custom-table-core th.col-siswa { width: 22%; text-align: left; padding-left: 15px; } 
        .custom-table-core th.col-penguji { width: 19%; text-align: left; padding-left: 15px; }
        .custom-table-core th.col-nilai { width: 20%; text-align: left; padding-left: 10px; }
        .custom-table-core th.col-ruang-ket { width: 15%; }
        .custom-table-core th.col-aksi { width: 110px; }

        .dashboard-btn-group { display: flex; gap: 6px; justify-content: center; flex-direction: column; }
        .btn-edit-inline { background-color: #f59e0b; color: white !important; padding: 5px 12px; border-radius: 4px; text-decoration: none; font-size: 11px; font-weight: 600; cursor: pointer; border: none; font-family: 'Poppins'; display: inline-flex; align-items: center; justify-content: center; gap: 4px;}
        .btn-delete-inline { background-color: #ef4444; color: white !important; padding: 5px 12px; border-radius: 4px; text-decoration: none; font-size: 11px; font-weight: 600; cursor: pointer; border: none; font-family: 'Poppins'; display: inline-flex; align-items: center; justify-content: center; gap: 4px;}
        .btn-nilai-inline { background-color: #10b981; color: white !important; padding: 5px 12px; border-radius: 4px; text-decoration: none; font-size: 11px; font-weight: 600; cursor: pointer; border: none; font-family: 'Poppins'; display: inline-flex; align-items: center; justify-content: center; gap: 4px; }
        .btn-nilai-inline:hover { background-color: #059669; }
        
        .btn-small-link { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 12px; font-size: 10px; font-weight: 700; text-decoration: none; text-transform: uppercase; }
        .btn-small-link:hover { opacity: 0.8; }

        .text-success { color: #16a34a; }
        .text-danger { color: #dc2626; }
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 10px; font-weight: 700; text-transform: uppercase; display: inline-block; white-space: nowrap; }
        .badge-lulus { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .badge-tidaklulus { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .badge-menunggu { background-color: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

        .mantap-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(15, 23, 42, 0.5); z-index: 999999 !important; backdrop-filter: blur(3px); overflow-y: auto; }
        .mantap-modal-dialog { position: relative; width: 90%; max-width: 650px; margin: 3rem auto; box-sizing: border-box; }
        .mantap-modal-content { background-color: white; padding: 25px; border-radius: 16px; border: 2px solid #cbd5e1; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15); }
        .mantap-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; }
        .mantap-modal-header h5 { font-size: 1.3rem; margin: 0; color: #0f172a; font-weight: 700; }
        .close-modal-btn { background: none; border: none; font-size: 1.7rem; color: #64748b; cursor: pointer; line-height: 1; }
        
        .mantap-modal-body label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13.5px; color: #0f172a; margin-top: 15px; }
        .mantap-modal-body input, .mantap-modal-body select, .mantap-modal-body textarea { width: 100%; padding: 11px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 13px; background-color: #f8fafc; box-sizing: border-box; }
        .mantap-modal-body input:focus, .mantap-modal-body select:focus, .mantap-modal-body textarea:focus { outline: none; border-color: var(--mantap-blue-main); background-color: white; }
        
        .grid-2-col { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        
        .info-pembimbing { background: #eff6ff; border-left: 4px solid var(--mantap-blue-main); padding: 10px 15px; border-radius: 4px; font-size: 12.5px; color: #1e40af; margin-top: 10px; font-weight: 500; display: none; }
        
        .info-laporan { display: none; background: #f0fdf4; border-left: 4px solid #22c55e; padding: 10px 15px; border-radius: 4px; font-size: 12.5px; color: #166534; margin-top: 10px; font-weight: 500; }
        .info-laporan-belum { display: none; background: #fff1f2; border-left: 4px solid #ef4444; padding: 10px 15px; border-radius: 4px; font-size: 12.5px; color: #991b1b; margin-top: 10px; font-weight: 500; }
        .btn-link-laporan { display: inline-flex; align-items: center; gap: 4px; background: white; padding: 4px 10px; border-radius: 4px; text-decoration: none; color: #334155; font-size: 11.5px; border: 1px solid #cbd5e1; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .btn-link-laporan:hover { background: #f8fafc; }

        .btn-modal-submit { background-color: var(--mantap-blue-main); color: white; padding: 12px; border: none; border-radius: 8px; font-weight: 600; font-size: 13.5px; cursor: pointer; font-family: 'Poppins', sans-serif; width: 100%; margin-top: 25px; box-shadow: 0 4px 6px -1px rgba(30, 64, 175, 0.15); }
        .btn-modal-submit:hover { background-color: var(--mantap-blue-light); }
        
        .btn-bagi-rata { background-color: #10b981; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 11px; cursor: pointer; transition: 0.2s; }
        .btn-bagi-rata:hover { background-color: #059669; }

        .sidebar-nav ul, .sidebar-nav li, .dropdown-menu, .dropdown-menu li { list-style: none !important; margin: 0; padding: 0; }

        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 
            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; padding: 5px 0px !important; }
            .page-header-controls h1 { font-size: 1.4rem !important; }
            
            .header-actions-group { width: 100%; flex-direction: column; align-items: stretch; gap: 10px; }
            .search-wrapper { width: 100%; margin-top: 5px; }
            .search-input { width: 100%; box-sizing: border-box; }
            .search-input:focus { width: 100%; }
            .btn-add, .btn-kriteria, .btn-print-jadwal { width: 100% !important; justify-content: center; padding: 12px !important; border-radius: 8px !important; }
            
            .grid-2-col { grid-template-columns: 1fr; gap: 0; }
            .glass-panel-table { padding: 16px 10px !important; border-radius: 12px !important; border: 2px solid #cbd5e1 !important; }
            .table-container-fixed { display: block !important; width: 100% !important; overflow-x: auto !important; -webkit-overflow-scrolling: touch; }
            .custom-table-core { table-layout: auto !important; min-width: 950px !important; }
            .mantap-modal-dialog { margin: 1.5rem auto; width: 95%; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; ?>

<div class="main-content-wrapper">
    <?php include 'panel/navbar.php'; ?>

    <div class="admin-main-content"> 
        <div class="page-header-controls">
            <h1><i class="fas fa-gavel" style="color: var(--mantap-blue-main); margin-right: 6px;"></i> Manajemen Sidang PKL</h1>
            
            <div class="header-actions-group">
                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchSidang" class="search-input" placeholder="Cari Siswa / Penguji / Ruang..." onkeyup="filterTabelSidang()">
                </div>
                <?php if ($current_user_level === 'admin'): ?>
                    <button class="btn-add secondary" onclick="openModalAuto()"><i class="fas fa-magic"></i> Buat Acak (Auto)</button>
                    <button class="btn-add" onclick="openModal()"><i class="fas fa-calendar-plus"></i> Manual</button>
                    <button class="btn-print-jadwal" onclick="openModalCetak()"><i class="fas fa-print"></i> Cetak Jadwal</button>
                    <button class="btn-kriteria" onclick="openModalKriteria()"><i class="fas fa-list-ol"></i> Kriteria</button>
                    <button class="btn-add danger" onclick="konfirmasiHapusSemuaSidang()"><i class="fas fa-trash-alt"></i> Hapus Semua</button>
                <?php endif; ?>
            </div>
        </div>

        <?php echo $error_session; ?>
        <?php echo $message; ?>
        
        <div class="glass-panel-table">
            <div class="table-container-fixed">
                <table class="custom-table-core" id="tabelSidangData">
                    <thead>
                        <tr>
                            <th class="col-no">NO</th>
                            <th class="col-waktu">TANGGAL & WAKTU</th>
                            <th class="col-siswa">SISWA, LOKASI & BERKAS</th>
                            <th class="col-penguji">PENGUJI SIDANG</th>
                            <th class="col-nilai">HASIL PENILAIAN</th>
                            <th class="col-ruang-ket">RUANGAN & KET</th>
                            <th class="col-aksi">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($jadwal_sidang && $jadwal_sidang->num_rows > 0): ?>
                            <?php $no = 1; while ($row = $jadwal_sidang->fetch_assoc()): 
                                
                                // ---- VALIDASI PENGUJI YG SANGAT AKURAT ----
                                $is_penguji = false;
                                if ($guru_id > 0 && ($row['id_p1'] == $guru_id || $row['id_p2'] == $guru_id)) {
                                    $is_penguji = true;
                                } elseif (!empty($user_full_name) && ($row['nama_p1'] == $user_full_name || $row['nama_p2'] == $user_full_name)) {
                                    $is_penguji = true;
                                }
                                // -------------------------------------------
                            ?>
                            <tr class="data-row-sidang">
                                <td style="font-weight: 700; color: #64748b; text-align: center;"><?php echo $no++; ?></td>
                                <td style="text-align: center;">
                                    <strong style="color: var(--mantap-blue-dark); font-size: 13.5px;"><?php echo formatTanggal($row['tanggal_ujian']); ?></strong><br>
                                    <span style="color: #ef4444; font-weight: 600;"><i class="far fa-clock"></i> <?php echo htmlspecialchars($row['waktu_sidang']); ?></span>
                                </td>
                                <td style="text-align: left; padding-left: 15px;">
                                    <strong style="color: var(--mantap-blue-main); font-size: 13.5px;"><?php echo htmlspecialchars($row['nama_siswa']); ?></strong><br>
                                    <small style="color: #64748b;"><?php echo htmlspecialchars($row['kelas']); ?></small><br>
                                    <span style="font-size: 11.5px; color: #475569; font-style: italic;"><i class="fas fa-building opacity-50"></i> <?php echo htmlspecialchars($row['nama_lokasi'] ?? '-'); ?></span>
                                    
                                    <div style="margin-top: 6px; display: flex; gap: 5px; flex-wrap: wrap;">
                                        <?php if (!empty($row['file_laporan']) || !empty($row['file_ppt'])): ?>
                                            <?php if (!empty($row['file_laporan'])): ?>
                                                <a href="../uploads/laporan_akhir/<?php echo urlencode($row['file_laporan']); ?>" target="_blank" class="btn-small-link" style="background:#fee2e2; color:#b91c1c;"><i class="fas fa-file-pdf"></i> PDF</a>
                                            <?php endif; ?>
                                            <?php if (!empty($row['file_ppt'])): ?>
                                                <a href="../uploads/laporan_akhir/<?php echo urlencode($row['file_ppt']); ?>" target="_blank" class="btn-small-link" style="background:#fef3c7; color:#b45309;"><i class="fas fa-file-powerpoint"></i> PPT</a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="font-size:10px; color:#ef4444; font-style:italic; font-weight:600;"><i class="fas fa-times-circle"></i> Berkas belum dikumpulkan</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="text-align: left; padding-left: 15px;">
                                    <div style="font-size: 12.5px; color: #475569; margin-bottom: 2px;"><strong>Penguji 1:</strong> <?php echo htmlspecialchars($row['nama_p1'] ?? '-'); ?></div>
                                    <div style="font-size: 12.5px; color: #475569; margin-bottom: 2px;"><strong>Penguji 2:</strong> <?php echo htmlspecialchars($row['nama_p2'] ?? '-'); ?></div>
                                    <div style="font-size: 11px; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 4px; color: #64748b;">
                                        Pembimbing: <?php echo htmlspecialchars($row['nama_pembimbing'] ?? '-'); ?>
                                    </div>
                                </td>
                                
                                <td style="text-align: left; padding-left: 10px; font-size: 12px; white-space: nowrap;">
                                    <div style="margin-bottom: 4px;">
                                        <span style="display:inline-block; min-width: 68px; font-weight:bold; color:#475569;">Penguji 1:</span> 
                                        <?php if ($row['nilai_p1'] !== null): ?>
                                            <strong style="color:var(--mantap-blue-main); font-size:13px;"><?php echo number_format($row['nilai_p1'], 2); ?></strong> 
                                            <?php echo ($row['kep_p1'] == 'Lulus') ? '<span class="text-success" title="Lulus"><i class="fas fa-check-circle"></i></span>' : '<span class="text-danger" title="Tidak Lulus"><i class="fas fa-times-circle"></i></span>'; ?>
                                        <?php else: ?>
                                            <span style="color:#94a3b8; font-style:italic;">Belum dinilai</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="margin-bottom: 6px;">
                                        <span style="display:inline-block; min-width: 68px; font-weight:bold; color:#475569;">Penguji 2:</span> 
                                        <?php if ($row['nilai_p2'] !== null): ?>
                                            <strong style="color:var(--mantap-blue-main); font-size:13px;"><?php echo number_format($row['nilai_p2'], 2); ?></strong> 
                                            <?php echo ($row['kep_p2'] == 'Lulus') ? '<span class="text-success" title="Lulus"><i class="fas fa-check-circle"></i></span>' : '<span class="text-danger" title="Tidak Lulus"><i class="fas fa-times-circle"></i></span>'; ?>
                                        <?php else: ?>
                                            <span style="color:#94a3b8; font-style:italic;">Belum dinilai</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div style="border-top: 1px dashed #cbd5e1; padding-top: 6px; margin-top: 4px;">
                                        <?php if($row['nilai_p1'] !== null && $row['nilai_p2'] !== null): ?>
                                            <?php 
                                            $rata = ($row['nilai_p1'] + $row['nilai_p2']) / 2; 
                                            $status_akhir = ($row['kep_p1'] == 'Lulus' && $row['kep_p2'] == 'Lulus') ? 'LULUS' : 'TIDAK LULUS';
                                            $badge_class = ($status_akhir == 'LULUS') ? 'badge-lulus' : 'badge-tidaklulus';
                                            ?>
                                            <div style="display:flex; justify-content:space-between; align-items:center; gap: 10px;">
                                                <strong style="color:#0f172a;">Rata: <?php echo number_format($rata, 2); ?></strong>
                                                <span class="badge <?php echo $badge_class; ?>"><?php echo $status_akhir; ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge badge-menunggu" style="width: 100%; text-align: center; box-sizing: border-box;">MENUNGGU NILAI</span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td style="text-align: center;">
                                    <strong style="color: #334155;"><i class="fas fa-door-open" style="color: #94a3b8;"></i> <?php echo htmlspecialchars($row['ruangan_ujian']); ?></strong>
                                    <?php if(!empty($row['keterangan'])): ?>
                                        <div style="font-size: 10.5px; color: #64748b; font-style: italic; margin-top: 5px; line-height: 1.3; background: #f8fafc; padding: 4px; border-radius: 4px; border: 1px solid #e2e8f0;">
                                            <?php echo htmlspecialchars($row['keterangan']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                
                                <td style="text-align: center;">
                                    <div class="dashboard-btn-group">
                                        <?php if ($current_user_level === 'admin'): ?>
                                            <button class="btn-edit-inline" onclick="openModal('<?php echo $row['id_sidang']; ?>', '<?php echo $row['siswa_id']; ?>', '<?php echo $row['tanggal_ujian']; ?>', '<?php echo htmlspecialchars($row['waktu_sidang'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['ruangan_ujian'], ENT_QUOTES); ?>', '<?php echo $row['id_p1']; ?>', '<?php echo $row['id_p2']; ?>', '<?php echo htmlspecialchars($row['keterangan'] ?? '', ENT_QUOTES); ?>')"><i class="fas fa-edit"></i> Edit</button>
                                            <button class="btn-delete-inline" onclick="konfirmasiHapusSidang('<?php echo $row['id_sidang']; ?>')"><i class="fas fa-trash"></i> Hapus</button>
                                        <?php endif; ?>

                                        <?php 
                                        $show_nilai_btn = false;
                                        $btn_text = "Form Nilai";
                                        $btn_style = "";

                                        if ($current_user_level === 'admin') {
                                            $show_nilai_btn = true;
                                            if ($row['nilai_p1'] !== null || $row['nilai_p2'] !== null) {
                                                $btn_text = "Lihat Nilai";
                                                $btn_style = "background-color: #3b82f6;"; 
                                            } else {
                                                $btn_text = "Beri Nilai";
                                            }
                                        } elseif ($is_penguji) {
                                            $show_nilai_btn = true;
                                            $sudah_menilai = false;
                                            
                                            // Cek by ID atau Nama
                                            if (($row['id_p1'] == $guru_id || $row['nama_p1'] == $user_full_name) && $row['nilai_p1'] !== null) $sudah_menilai = true;
                                            if (($row['id_p2'] == $guru_id || $row['nama_p2'] == $user_full_name) && $row['nilai_p2'] !== null) $sudah_menilai = true;

                                            if ($sudah_menilai) {
                                                $btn_text = "Edit Nilai";
                                                $btn_style = "background-color: #3b82f6;"; 
                                            } else {
                                                $btn_text = "Beri Nilai";
                                            }
                                        }
                                        ?>
                                        
                                        <?php if ($show_nilai_btn): ?>
                                            <a href="penilaian-sidang.php" class="btn-nilai-inline" style="<?php echo $btn_style; ?>">
                                                <i class="fas fa-star"></i> <?php echo $btn_text; ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="text-align: center; color: #ef4444; font-style: italic; padding: 25px;">Belum ada jadwal pelaksanaan sidang PKL yang dirilis.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($current_user_level === 'admin'): ?>
<!-- MODAL CETAK FILTER -->
<div class="mantap-modal" id="modalCetak">
    <div class="mantap-modal-dialog" style="max-width: 450px;">
        <div class="mantap-modal-content">
            <div class="mantap-modal-header">
                <h5><i class="fas fa-print" style="color: var(--mantap-purple); margin-right: 6px;"></i> Cetak Laporan Jadwal</h5>
                <button type="button" class="close-modal-btn" onclick="closeModalCetak()">&times;</button>
            </div>
            <form method="GET" action="sidang-pkl.php" target="_blank">
                <input type="hidden" name="action" value="print">
                <div class="mantap-modal-body">
                    
                    <label for="filter_type">Pilih Kriteria Filter Cetak:</label>
                    <select name="filter_type" id="filter_type" required onchange="changeFilterCetak()">
                        <option value="semua">Tampilkan Semua Jadwal</option>
                        <option value="ruang">Berdasarkan Ruangan Ujian</option>
                        <option value="tanggal">Berdasarkan Tanggal Ujian</option>
                        <option value="guru">Berdasarkan Guru (Penguji/Pem.)</option>
                    </select>

                    <div id="filter_ruang_box" style="display:none; margin-top:15px; padding:10px; background:#f8fafc; border-radius:8px; border:1px solid #cbd5e1;">
                        <label for="filter_ruang" style="margin-top:0;">Pilih Ruangan:</label>
                        <select name="filter_ruang" id="filter_ruang">
                            <?php foreach($distinct_rooms as $rm): ?>
                                <option value="<?php echo htmlspecialchars($rm); ?>"><?php echo htmlspecialchars($rm); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="filter_tanggal_box" style="display:none; margin-top:15px; padding:10px; background:#f8fafc; border-radius:8px; border:1px solid #cbd5e1;">
                        <label for="filter_tanggal" style="margin-top:0;">Pilih Tanggal:</label>
                        <select name="filter_tanggal" id="filter_tanggal">
                            <?php foreach($distinct_dates as $dt): ?>
                                <option value="<?php echo $dt; ?>"><?php echo formatTanggal($dt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="filter_guru_box" style="display:none; margin-top:15px; padding:10px; background:#f8fafc; border-radius:8px; border:1px solid #cbd5e1;">
                        <label for="filter_guru" style="margin-top:0;">Pilih Nama Guru:</label>
                        <select name="filter_guru" id="filter_guru">
                            <?php foreach($guru_data as $g): ?>
                                <option value="<?php echo $g['guru_id']; ?>"><?php echo htmlspecialchars($g['nama_guru']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn-modal-submit" style="background-color: var(--mantap-purple); margin-top:20px;" onclick="closeModalCetak()"><i class="fas fa-print"></i> Tampilkan & Cetak PDF</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL KRITERIA -->
<div class="mantap-modal" id="modalKriteria">
    <div class="mantap-modal-dialog" style="max-width: 500px;">
        <div class="mantap-modal-content">
            <div class="mantap-modal-header">
                <h5 id="modalTitle"><i class="fas fa-list-ol" style="color: #3b82f6; margin-right: 6px;"></i> Kriteria Penilaian Sidang</h5>
                <button type="button" class="close-modal-btn" onclick="closeModalKriteria()">&times;</button>
            </div>
            <form method="POST" action="sidang-pkl.php">
                <div class="mantap-modal-body" style="margin-top:-10px;">
                    <input type="hidden" name="submit_kriteria" value="1">
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom:15px;">
                        <p style="font-size:12px; color:#64748b; margin:0; line-height:1.4;">Atur komponen utama penilaian (Total 100%).</p>
                        <button type="button" class="btn-bagi-rata" onclick="bagiRataBobot()"><i class="fas fa-balance-scale"></i> Bagi Rata (100%)</button>
                    </div>
                    
                    <div id="kriteria_container">
                        <?php $no = 1; foreach($kriteria as $k): ?>
                        <div class="kriteria-row" style="display:flex; gap:10px; margin-bottom:10px; align-items:center;">
                            <span class="nomor-kriteria" style="font-weight:bold; width:20px; color:#1e40af;"><?php echo $no++; ?>.</span>
                            <input type="text" name="nama_k[]" value="<?php echo htmlspecialchars($k['nama_kriteria'] ?? ''); ?>" placeholder="Nama Kriteria" required style="flex:1;">
                            <input type="number" name="bobot_k[]" value="<?php echo htmlspecialchars($k['bobot'] ?? 0); ?>" placeholder="Bobot" required style="width:80px; text-align:center;" step="any" onkeyup="hitungTotalBobot()" onchange="hitungTotalBobot()">
                            <span style="font-weight:bold; color:#64748b;">%</span>
                            <button type="button" class="btn-delete-inline" style="padding: 10px 12px;" onclick="hapusBarisKriteria(this)"><i class="fas fa-trash"></i></button>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="background: #f8fafc; padding: 10px; border-radius: 6px; text-align: right; border: 1px solid #cbd5e1; margin-bottom: 15px;">
                        <span style="font-weight: 600; color: #475569; font-size: 13px;">Total Bobot: </span>
                        <strong id="totalBobotDisplay" style="font-size: 16px; color: #10b981;">0%</strong>
                    </div>

                    <button type="button" class="btn-add" style="margin-bottom: 15px; width:100%; justify-content:center;" onclick="tambahBarisKriteria()"><i class="fas fa-plus"></i> Tambah Kriteria</button>
                    <button type="submit" class="btn-modal-submit" style="background-color: #3b82f6;"><i class="fas fa-save"></i> Simpan Format</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL AUTO GENERATE ACAK -->
<div class="mantap-modal" id="modalAutoSidang">
    <div class="mantap-modal-dialog" style="max-width: 450px;">
        <div class="mantap-modal-content">
            <div class="mantap-modal-header">
                <h5><i class="fas fa-magic" style="color: var(--mantap-blue-main); margin-right: 6px;"></i> Generate Jadwal Acak</h5>
                <button type="button" class="close-modal-btn" onclick="closeModalAuto()">&times;</button>
            </div>
            <form method="POST" action="sidang-pkl.php">
                <div class="mantap-modal-body">
                    <input type="hidden" name="submit_auto_sidang" value="1">
                    <div style="background: #eff6ff; padding: 12px; border-radius: 8px; border: 1px solid #bfdbfe; margin-bottom: 20px; font-size: 12px; color: #1e40af; line-height: 1.5;">
                        <i class="fas fa-info-circle me-1"></i> Sistem akan memposisikan siswa dari Tempat PKL yang sama secara <b>Berurutan Waktunya</b>. Pengacakan hanya dilakukan pada hari <b>Senin - Jumat</b>.
                    </div>
                    
                    <label for="start_date">Dari Tanggal:</label>
                    <input type="date" name="start_date" id="start_date" required>

                    <label for="end_date">Sampai Tanggal:</label>
                    <input type="date" name="end_date" id="end_date" required>

                    <button type="submit" class="btn-modal-submit" style="background-color: #0ea5e9;"><i class="fas fa-cogs"></i> Proses Generate Acak</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL JADWAL MANUAL -->
<div class="mantap-modal" id="modalSidang">
    <div class="mantap-modal-dialog">
        <div class="mantap-modal-content">
            <div class="mantap-modal-header">
                <h5 id="modalTitle"><i class="fas fa-calendar-alt" style="color: var(--mantap-blue-main); margin-right: 6px;"></i> Form Jadwal Sidang PKL</h5>
                <button type="button" class="close-modal-btn" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" action="sidang-pkl.php">
                <div class="mantap-modal-body">
                    <input type="hidden" name="submit_sidang" value="1">
                    <input type="hidden" name="id_sidang" id="id_sidang" value="0">
                    <input type="hidden" id="hidden_pembimbing_id" value="">

                    <label for="siswa_id">Pilih Siswa Peserta Sidang:</label>
                    <select name="siswa_id" id="siswa_id" required onchange="handleSiswaChange()">
                        <option value="">-- Cari Nama Siswa --</option>
                        <?php foreach($siswa_data as $s): ?>
                            <option value="<?php echo $s['id']; ?>" 
                                    data-pem="<?php echo $s['id_pembimbing']; ?>" 
                                    data-namapem="<?php echo htmlspecialchars($s['nama_pembimbing'] ?? ''); ?>"
                                    data-laporan="<?php echo htmlspecialchars($s['file_laporan'] ?? ''); ?>"
                                    data-ppt="<?php echo htmlspecialchars($s['file_ppt'] ?? ''); ?>"
                                    data-scheduled="<?php echo $s['is_scheduled']; ?>">
                                <?php echo htmlspecialchars($s['nama']) . ' (' . htmlspecialchars($s['kelas']) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="info-laporan" id="info_laporan_box">
                        <i class="fas fa-check-circle me-1"></i> Berkas Laporan Tersedia:<br>
                        <div style="margin-top: 6px; display: flex; gap: 8px;">
                            <a id="link_laporan" href="#" target="_blank" class="btn-link-laporan" style="display:none;"><i class="fas fa-file-pdf text-danger"></i> PDF Laporan</a>
                            <a id="link_ppt" href="#" target="_blank" class="btn-link-laporan" style="display:none;"><i class="fas fa-file-powerpoint text-warning"></i> File PPT</a>
                        </div>
                    </div>
                    <div class="info-laporan-belum" id="info_laporan_belum_box">
                        <i class="fas fa-exclamation-triangle me-1"></i> Peringatan: Siswa ini belum mengunggah Laporan Akhir!
                    </div>

                    <div class="info-pembimbing" id="info_pem_box">
                        <i class="fas fa-info-circle me-1"></i> Guru Pembimbing: <strong id="nama_pem_display">-</strong> 
                        <br><span style="font-size: 11px; opacity: 0.8;">(Otomatis diblokir dari pilihan dewan penguji)</span>
                    </div>
                    
                    <div class="grid-2-col">
                        <div>
                            <label for="tanggal_ujian">Tanggal Pelaksanaan:</label>
                            <input type="date" name="tanggal_ujian" id="tanggal_ujian" required>
                        </div>
                        <div>
                            <label for="waktu_sidang">Waktu Sidang:</label>
                            <select name="waktu_sidang" id="waktu_sidang" required>
                                <option value="">-- Pilih Jam --</option>
                                <?php foreach($waktu_options as $wkt): ?>
                                    <option value="<?php echo $wkt; ?>"><?php echo $wkt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <label for="ruangan_ujian">Ruang / Tempat Ujian:</label>
                    <select name="ruangan_ujian" id="ruangan_ujian" required>
                        <option value="">-- Pilih Ruang --</option>
                        <?php foreach($ruang_options as $rng): ?>
                            <option value="<?php echo $rng; ?>"><?php echo $rng; ?></option>
                        <?php endforeach; ?>
                    </select>

                    <div class="grid-2-col">
                        <div>
                            <label for="penguji_1">Penguji 1:</label>
                            <select name="penguji_1" id="penguji_1" required onchange="handlePengujiChange()">
                                <option value="">-- Pilih Penguji 1 --</option>
                                <?php foreach($guru_data as $g): ?>
                                    <option value="<?php echo $g['guru_id']; ?>"><?php echo htmlspecialchars($g['nama_guru']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="penguji_2">Penguji 2:</label>
                            <select name="penguji_2" id="penguji_2" required onchange="handlePengujiChange()">
                                <option value="">-- Pilih Penguji 2 --</option>
                                <?php foreach($guru_data as $g): ?>
                                    <option value="<?php echo $g['guru_id']; ?>"><?php echo htmlspecialchars($g['nama_guru']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <label for="keterangan">Keterangan Khusus (Opsional):</label>
                    <textarea name="keterangan" id="keterangan" rows="2" placeholder="Contoh: Siswa wajib membawa laporan tercetak 3 rangkap..."></textarea>
                    
                    <button type="submit" class="btn-modal-submit"><i class="fas fa-save"></i> Simpan Jadwal Sidang</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const modal = document.getElementById('modalSidang');
    const modalKriteria = document.getElementById('modalKriteria');
    const modalAuto = document.getElementById('modalAutoSidang');
    const modalCetak = document.getElementById('modalCetak');

    // --- FITUR PENCARIAN REAL-TIME ---
    function filterTabelSidang() {
        let input = document.getElementById("searchSidang");
        let filter = input.value.toUpperCase();
        let table = document.getElementById("tabelSidangData");
        let tr = table.getElementsByClassName("data-row-sidang");

        for (let i = 0; i < tr.length; i++) {
            let textValue = tr[i].textContent || tr[i].innerText;
            if (textValue.toUpperCase().indexOf(filter) > -1) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }

    // Modal Cetak
    function openModalCetak() { if(modalCetak) modalCetak.style.display = 'block'; }
    function closeModalCetak() { if(modalCetak) modalCetak.style.display = 'none'; }
    function changeFilterCetak() {
        let val = document.getElementById('filter_type').value;
        document.getElementById('filter_ruang_box').style.display = (val === 'ruang') ? 'block' : 'none';
        document.getElementById('filter_tanggal_box').style.display = (val === 'tanggal') ? 'block' : 'none';
        document.getElementById('filter_guru_box').style.display = (val === 'guru') ? 'block' : 'none';
    }

    // Modal Kriteria
    function openModalKriteria() { 
        if(modalKriteria) {
            modalKriteria.style.display = 'block'; 
            hitungTotalBobot();
        }
    }
    function closeModalKriteria() { if(modalKriteria) modalKriteria.style.display = 'none'; }
    
    function bagiRataBobot() {
        let inputs = document.querySelectorAll('input[name="bobot_k[]"]');
        let count = inputs.length;
        if (count === 0) return;
        let rata = 100 / count;
        inputs.forEach(input => {
            input.value = rata.toFixed(2).replace(/\.00$/, '');
        });
        hitungTotalBobot();
    }

    function hitungTotalBobot() {
        let inputs = document.querySelectorAll('input[name="bobot_k[]"]');
        let total = 0;
        inputs.forEach(i => total += parseFloat(i.value) || 0);
        
        let display = document.getElementById('totalBobotDisplay');
        display.innerText = total.toFixed(2).replace(/\.00$/, '') + '%';
        
        if (Math.round(total) !== 100) {
            display.style.color = '#ef4444'; // Merah jika tidak 100
        } else {
            display.style.color = '#10b981'; // Hijau jika 100
        }
    }

    function hapusBarisKriteria(btn) {
        const container = document.getElementById('kriteria_container');
        if(container.children.length > 1) {
            btn.closest('.kriteria-row').remove(); 
            updateNomorKriteria();
            hitungTotalBobot();
        } else {
            Swal.fire({ icon: 'warning', title: 'Peringatan', text: 'Minimal harus ada 1 kriteria penilaian!' });
        }
    }

    function tambahBarisKriteria() {
        const container = document.getElementById('kriteria_container');
        const newRow = document.createElement('div');
        newRow.className = 'kriteria-row';
        newRow.style.cssText = 'display:flex; gap:10px; margin-bottom:10px; align-items:center;';
        newRow.innerHTML = `<span class="nomor-kriteria" style="font-weight:bold; width:20px; color:#1e40af;"></span>
            <input type="text" name="nama_k[]" value="" placeholder="Nama Kriteria" required style="flex:1;">
            <input type="number" name="bobot_k[]" value="0" placeholder="Bobot" required style="width:80px; text-align:center;" step="any" onkeyup="hitungTotalBobot()" onchange="hitungTotalBobot()">
            <span style="font-weight:bold; color:#64748b;">%</span>
            <button type="button" class="btn-delete-inline" style="padding: 10px 12px;" onclick="hapusBarisKriteria(this)"><i class="fas fa-trash"></i></button>`;
        container.appendChild(newRow); 
        updateNomorKriteria();
        hitungTotalBobot();
    }

    function updateNomorKriteria() {
        document.querySelectorAll('.kriteria-row .nomor-kriteria').forEach((span, index) => { span.innerText = (index + 1) + '.'; });
    }

    // Modal Auto Generate
    function openModalAuto() { if(modalAuto) modalAuto.style.display = 'block'; }
    function closeModalAuto() { if(modalAuto) modalAuto.style.display = 'none'; }

    // SweetAlert Hapus Semua
    function konfirmasiHapusSemuaSidang() {
        Swal.fire({
            title: 'Hapus SEMUA Jadwal?',
            html: "<p style='color:#ef4444;font-size:14px;'>PERINGATAN KRITIS!<br>Semua jadwal sidang, termasuk nilai yang sudah diisi oleh dewan penguji akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.</p>",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Hapus Permanen',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `sidang-pkl.php?action=delete_all`;
            }
        });
    }

    function openModal(id = 0, siswa = '', tgl = '', wkt = '', ruang = '', p1 = '', p2 = '', ket = '') {
        document.getElementById('id_sidang').value = id;
        document.getElementById('tanggal_ujian').value = tgl;
        document.getElementById('waktu_sidang').value = wkt;
        document.getElementById('ruangan_ujian').value = ruang;
        document.getElementById('penguji_1').value = p1;
        document.getElementById('penguji_2').value = p2;
        document.getElementById('keterangan').value = ket;
        
        document.getElementById('modalTitle').innerHTML = id == 0 ? '<i class="fas fa-calendar-plus" style="color: var(--mantap-blue-main); margin-right: 6px;"></i> Buat Jadwal Sidang Baru' : '<i class="fas fa-edit" style="color: #f59e0b; margin-right: 6px;"></i> Edit Jadwal Sidang';
        
        let selSiswa = document.getElementById('siswa_id');
        Array.from(selSiswa.options).forEach(opt => {
            if (opt.value !== "") {
                if (opt.getAttribute('data-scheduled') === '1' && opt.value != siswa) {
                    opt.disabled = true; opt.style.display = 'none'; 
                } else {
                    opt.disabled = false; opt.style.display = '';
                }
            }
        });
        
        selSiswa.value = siswa; handleSiswaChange(); modal.style.display = 'block';
    }

    function closeModal() { modal.style.display = 'none'; }

    function handleSiswaChange() {
        let selSiswa = document.getElementById('siswa_id');
        let opt = selSiswa.options[selSiswa.selectedIndex];
        
        let id_pem = opt ? (opt.getAttribute('data-pem') || '') : '';
        let nm_pem = opt ? (opt.getAttribute('data-namapem') || '') : '';
        document.getElementById('hidden_pembimbing_id').value = id_pem;
        
        let infoBoxPem = document.getElementById('info_pem_box');
        if (id_pem && id_pem != '0') {
            document.getElementById('nama_pem_display').innerText = nm_pem; infoBoxPem.style.display = 'block';
        } else { infoBoxPem.style.display = 'none'; }
        
        let laporanPdf = opt ? (opt.getAttribute('data-laporan') || '') : '';
        let laporanPpt = opt ? (opt.getAttribute('data-ppt') || '') : '';
        
        let infoLaporanBox = document.getElementById('info_laporan_box');
        let infoLaporanBelum = document.getElementById('info_laporan_belum_box');
        let linkLaporan = document.getElementById('link_laporan');
        let linkPpt = document.getElementById('link_ppt');
        
        if (selSiswa.value === '') {
            infoLaporanBox.style.display = 'none'; infoLaporanBelum.style.display = 'none';
        } else if (laporanPdf || laporanPpt) {
            infoLaporanBelum.style.display = 'none'; infoLaporanBox.style.display = 'block';
            if(laporanPdf) { linkLaporan.href = '../uploads/laporan_akhir/' + encodeURIComponent(laporanPdf); linkLaporan.style.display = 'inline-flex'; } else { linkLaporan.style.display = 'none'; }
            if(laporanPpt) { linkPpt.href = '../uploads/laporan_akhir/' + encodeURIComponent(laporanPpt); linkPpt.style.display = 'inline-flex'; } else { linkPpt.style.display = 'none'; }
        } else {
            infoLaporanBox.style.display = 'none'; infoLaporanBelum.style.display = 'block';
        }
        handlePengujiChange(); 
    }

    function handlePengujiChange() {
        let p1 = document.getElementById('penguji_1'); let p2 = document.getElementById('penguji_2');
        let idPem = document.getElementById('hidden_pembimbing_id').value;

        Array.from(p1.options).forEach(o => { o.disabled = false; o.style.color = ""; });
        Array.from(p2.options).forEach(o => { o.disabled = false; o.style.color = ""; });

        if(idPem && idPem != '0') {
            let op1 = p1.querySelector(`option[value="${idPem}"]`); if(op1) { op1.disabled = true; op1.style.color = "#cbd5e1"; }
            let op2 = p2.querySelector(`option[value="${idPem}"]`); if(op2) { op2.disabled = true; op2.style.color = "#cbd5e1"; }
        }

        if(p1.value) { let op = p2.querySelector(`option[value="${p1.value}"]`); if(op) { op.disabled = true; op.style.color = "#cbd5e1"; } }
        if(p2.value) { let op = p1.querySelector(`option[value="${p2.value}"]`); if(op) { op.disabled = true; op.style.color = "#cbd5e1"; } }
    }

    function konfirmasiHapusSidang(id) {
        Swal.fire({
            title: 'Hapus Jadwal Sidang?', text: "Data jadwal yang telah dirilis akan dihapus dari daftar secara permanen.",
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc3545', cancelButtonColor: '#64748b', confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal'
        }).then((result) => { if (result.isConfirmed) window.location.href = `sidang-pkl.php?action=delete&id=${id}`; });
    }

    window.onclick = function(event) { 
        if (event.target == modal) closeModal(); 
        if (event.target == modalKriteria) closeModalKriteria(); 
        if (event.target == modalAuto) closeModalAuto();
        if (event.target == modalCetak) closeModalCetak();
    }
</script>
<?php endif; ?>

<?php include 'panel/footer.php'; ?>
</body>
</html>