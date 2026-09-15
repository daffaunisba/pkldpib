<?php
// admin/penilaian-sidang.php
include 'auth-check.php';
include '../config/db-koneksi.php';

$current_user_level = strtolower($_SESSION['level'] ?? 'user'); 
$current_user_id = $_SESSION['user_id'] ?? 0; // Ditambahkan untuk kebutuhan log

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
    die("<div class='alert error'>Akses Ditolak. Hanya Administrator atau Pembimbing/Penguji yang diizinkan.</div>");
}

$error_session = '';
if ($current_user_level === 'pembimbing' && $guru_id == 0) {
    $error_session = "<div class='alert error' style='margin-bottom:20px;'><i class='fas fa-exclamation-triangle'></i> <b>Perhatian:</b> Nama akun Anda (<b>$user_full_name</b>) tidak ditemukan di data Guru. Pastikan ejaan nama dan gelar di tabel Users sama dengan tabel Guru.</div>";
}

$message = '';

// TENTUKAN FILTER HAK AKSES UNTUK VIEW & EXPORT
$safe_user_full_name = $koneksi->real_escape_string($user_full_name);
$where_clause = "1=1";
if ($current_user_level !== 'admin') {
    $nama_filter = !empty($user_full_name) ? " OR g_p1.nama_guru = '$safe_user_full_name' OR g_p2.nama_guru = '$safe_user_full_name'" : "";
    $where_clause = "(s.penguji_1 = '$guru_id' OR s.penguji_2 = '$guru_id' $nama_filter)";
}

// ---------------------------------------------------------------------
// --- LOGIKA EKSPOR KE EXCEL (.XLS) ---
// ---------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'export_nilai') {
    
    // --- TRIGGER LOG AKTIVITAS (EXPORT) ---
    catatLog($koneksi, $current_user_id, "Mengekspor data rekap nilai sidang PKL ke format Excel");

    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Rekap_Nilai_Sidang_PKL_" . date('Ymd_His') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    $q_export = $koneksi->query("
        SELECT 
            s.tanggal_ujian, s.waktu_sidang, s.ruangan_ujian,
            p.nama AS nama_siswa, p.kelas, p.nisn,
            l.nama_lokasi,
            g_p1.nama_guru AS nama_p1, g_p2.nama_guru AS nama_p2,
            ps1.total_nilai AS nilai_p1, ps1.keputusan AS kep_p1, ps1.catatan AS cat_p1,
            ps2.total_nilai AS nilai_p2, ps2.keputusan AS kep_p2, ps2.catatan AS cat_p2
        FROM sidang_pkl s
        JOIN peserta_didik p ON s.siswa_id = p.id
        LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
        LEFT JOIN guru g_p1 ON s.penguji_1 = g_p1.guru_id
        LEFT JOIN guru g_p2 ON s.penguji_2 = g_p2.guru_id
        LEFT JOIN penilaian_sidang ps1 ON s.id_sidang = ps1.id_sidang AND ps1.penguji_id = s.penguji_1
        LEFT JOIN penilaian_sidang ps2 ON s.id_sidang = ps2.id_sidang AND ps2.penguji_id = s.penguji_2
        WHERE $where_clause
        GROUP BY s.id_sidang
        ORDER BY s.tanggal_ujian ASC, s.waktu_sidang ASC
    ");

    echo "<table border='1'>";
    echo "<tr>
            <th style='background-color:#1e40af; color:#fff;'>NO</th>
            <th style='background-color:#1e40af; color:#fff;'>TANGGAL</th>
            <th style='background-color:#1e40af; color:#fff;'>WAKTU SIDANG</th>
            <th style='background-color:#1e40af; color:#fff;'>RUANGAN</th>
            <th style='background-color:#1e40af; color:#fff;'>NAMA SISWA</th>
            <th style='background-color:#1e40af; color:#fff;'>NISN</th>
            <th style='background-color:#1e40af; color:#fff;'>KELAS</th>
            <th style='background-color:#1e40af; color:#fff;'>LOKASI PKL</th>
            <th style='background-color:#f59e0b; color:#fff;'>NAMA PENGUJI 1</th>
            <th style='background-color:#f59e0b; color:#fff;'>NILAI P1</th>
            <th style='background-color:#f59e0b; color:#fff;'>KEPUTUSAN P1</th>
            <th style='background-color:#f59e0b; color:#fff;'>CATATAN P1</th>
            <th style='background-color:#10b981; color:#fff;'>NAMA PENGUJI 2</th>
            <th style='background-color:#10b981; color:#fff;'>NILAI P2</th>
            <th style='background-color:#10b981; color:#fff;'>KEPUTUSAN P2</th>
            <th style='background-color:#10b981; color:#fff;'>CATATAN P2</th>
            <th style='background-color:#3b82f6; color:#fff;'>RATA-RATA NILAI</th>
            <th style='background-color:#3b82f6; color:#fff;'>STATUS KELULUSAN</th>
          </tr>";

    if ($q_export && $q_export->num_rows > 0) {
        $no = 1;
        while ($r = $q_export->fetch_assoc()) {
            $rata_rata = '';
            $status_akhir = 'MENUNGGU';
            
            if ($r['nilai_p1'] !== null && $r['nilai_p2'] !== null) {
                $rata = ($r['nilai_p1'] + $r['nilai_p2']) / 2;
                $rata_rata = number_format($rata, 2);
                $status_akhir = ($r['kep_p1'] == 'Lulus' && $r['kep_p2'] == 'Lulus') ? 'LULUS' : 'TIDAK LULUS';
            }

            echo "<tr>
                    <td align='center'>{$no}</td>
                    <td align='center'>" . date('d/m/Y', strtotime($r['tanggal_ujian'])) . "</td>
                    <td align='center'>{$r['waktu_sidang']}</td>
                    <td align='center'>{$r['ruangan_ujian']}</td>
                    <td>" . strtoupper($r['nama_siswa']) . "</td>
                    <td align='center'>{$r['nisn']}</td>
                    <td align='center'>{$r['kelas']}</td>
                    <td>{$r['nama_lokasi']}</td>
                    
                    <td>{$r['nama_p1']}</td>
                    <td align='center'>{$r['nilai_p1']}</td>
                    <td align='center'>{$r['kep_p1']}</td>
                    <td>{$r['cat_p1']}</td>
                    
                    <td>{$r['nama_p2']}</td>
                    <td align='center'>{$r['nilai_p2']}</td>
                    <td align='center'>{$r['kep_p2']}</td>
                    <td>{$r['cat_p2']}</td>
                    
                    <td align='center'><b>{$rata_rata}</b></td>
                    <td align='center'><b>{$status_akhir}</b></td>
                  </tr>";
            $no++;
        }
    }
    echo "</table>";
    exit();
}

// ---------------------------------------------------------------------
// 1. LOGIKA SIMPAN PENILAIAN
// ---------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_nilai'])) {
    $id_sidang = (int)$_POST['id_sidang'];
    $target_role = $_POST['target_role'] ?? ''; 
    $catatan = trim($_POST['catatan']);
    $keputusan = trim($_POST['keputusan']);
    
    $kriteria_db = [];
    $qk = $koneksi->query("SELECT id, bobot FROM kriteria_sidang");
    while($k = $qk->fetch_assoc()) { $kriteria_db[$k['id']] = (float)$k['bobot']; }

    $total_nilai = 0;
    $nilai_input = $_POST['nilai_kriteria'] ?? []; 
    foreach ($nilai_input as $id_k => $nilai) {
        $n = (float)$nilai; $bobot = $kriteria_db[$id_k] ?? 0;
        $total_nilai += ($n * ($bobot / 100));
    }
    $nilai_json = json_encode($nilai_input);

    $q_role = $koneksi->query("SELECT s.penguji_1, s.penguji_2, g1.nama_guru AS n1, g2.nama_guru AS n2 FROM sidang_pkl s LEFT JOIN guru g1 ON s.penguji_1 = g1.guru_id LEFT JOIN guru g2 ON s.penguji_2 = g2.guru_id WHERE s.id_sidang = $id_sidang");
    $row_role = $q_role->fetch_assoc();
    
    $role_penguji = '';
    $target_guru_id = 0;

    if ($current_user_level === 'admin' && !empty($target_role)) {
        $role_penguji = $target_role;
        $target_guru_id = ($target_role === 'Penguji 1') ? $row_role['penguji_1'] : $row_role['penguji_2'];
    } else {
        if ($row_role['penguji_1'] == $guru_id || $row_role['n1'] == $user_full_name) { 
            $role_penguji = 'Penguji 1'; 
            $target_guru_id = $row_role['penguji_1'];
        } elseif ($row_role['penguji_2'] == $guru_id || $row_role['n2'] == $user_full_name) { 
            $role_penguji = 'Penguji 2'; 
            $target_guru_id = $row_role['penguji_2'];
        }
    }

    if (!empty($role_penguji) && $target_guru_id > 0) {
        
        // Ambil nama siswa untuk keperluan LOG
        $nama_siswa_log = "Siswa (ID Sidang " . $id_sidang . ")";
        $q_siswa_log = $koneksi->query("SELECT p.nama FROM sidang_pkl s JOIN peserta_didik p ON s.siswa_id = p.id WHERE s.id_sidang = $id_sidang");
        if ($q_siswa_log && $q_siswa_log->num_rows > 0) {
            $nama_siswa_log = $q_siswa_log->fetch_assoc()['nama'];
        }

        $cek = $koneksi->query("SELECT id_penilaian FROM penilaian_sidang WHERE id_sidang = $id_sidang AND penguji_id = $target_guru_id");
        
        if ($cek && $cek->num_rows > 0) {
            $id_penilaian = $cek->fetch_assoc()['id_penilaian'];
            $stmt = $koneksi->prepare("UPDATE penilaian_sidang SET role_penguji=?, nilai_json=?, catatan=?, keputusan=?, total_nilai=? WHERE id_penilaian=?");
            $stmt->bind_param("ssssdi", $role_penguji, $nilai_json, $catatan, $keputusan, $total_nilai, $id_penilaian);
            $stmt->execute(); 
            $stmt->close();

            // --- TRIGGER LOG AKTIVITAS (UPDATE NILAI) ---
            catatLog($koneksi, $current_user_id, "Memperbarui penilaian sidang PKL untuk siswa: {$nama_siswa_log} (Sebagai: {$role_penguji}, Total Nilai: " . number_format($total_nilai, 2) . ")");
        } else {
            $stmt = $koneksi->prepare("INSERT INTO penilaian_sidang (id_sidang, penguji_id, role_penguji, nilai_json, catatan, keputusan, total_nilai) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissssd", $id_sidang, $target_guru_id, $role_penguji, $nilai_json, $catatan, $keputusan, $total_nilai);
            $stmt->execute(); 
            $stmt->close();

            // --- TRIGGER LOG AKTIVITAS (INPUT NILAI) ---
            catatLog($koneksi, $current_user_id, "Memberikan penilaian sidang PKL untuk siswa: {$nama_siswa_log} (Sebagai: {$role_penguji}, Total Nilai: " . number_format($total_nilai, 2) . ")");
        }
    }
    header("Location: penilaian-sidang.php?status=success"); exit();
}

if (isset($_GET['status']) && $_GET['status'] == 'success') {
    $message = "<div class='alert success'>✅ Penilaian Sidang berhasil disimpan.</div>";
}

// ---------------------------------------------------------------------
// 2. AMBIL DATA JADWAL SIDANG & NILAI P1, P2
// ---------------------------------------------------------------------
$jadwal_query = "
    SELECT 
        s.id_sidang, s.tanggal_ujian, s.waktu_sidang, s.ruangan_ujian, s.penguji_1, s.penguji_2,
        p.nama AS nama_siswa, p.kelas,
        g_p1.nama_guru AS nama_p1,
        g_p2.nama_guru AS nama_p2,
        ps1.id_penilaian AS id_pen_p1, ps1.total_nilai AS nilai_p1, ps1.keputusan AS kep_p1, ps1.nilai_json AS json_p1, ps1.catatan AS cat_p1,
        ps2.id_penilaian AS id_pen_p2, ps2.total_nilai AS nilai_p2, ps2.keputusan AS kep_p2, ps2.nilai_json AS json_p2, ps2.catatan AS cat_p2
    FROM sidang_pkl s
    JOIN peserta_didik p ON s.siswa_id = p.id
    LEFT JOIN guru g_p1 ON s.penguji_1 = g_p1.guru_id
    LEFT JOIN guru g_p2 ON s.penguji_2 = g_p2.guru_id
    LEFT JOIN penilaian_sidang ps1 ON s.id_sidang = ps1.id_sidang AND ps1.penguji_id = s.penguji_1
    LEFT JOIN penilaian_sidang ps2 ON s.id_sidang = ps2.id_sidang AND ps2.penguji_id = s.penguji_2
    WHERE $where_clause
    GROUP BY s.id_sidang
    ORDER BY s.tanggal_ujian ASC, s.waktu_sidang ASC
";
$jadwal_sidang = $koneksi->query($jadwal_query);

$kriteria = $koneksi->query("SELECT * FROM kriteria_sidang ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);

function formatTanggal($tanggal) {
    if (empty($tanggal) || $tanggal == '0000-00-00') return '-';
    $timestamp = strtotime($tanggal);
    $bulan_indo = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return date('d', $timestamp) . ' ' . $bulan_indo[(int)date('m', $timestamp)] . ' ' . date('Y', $timestamp);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Penilaian Sidang PKL | Si Mantap PKL</title>
    <link rel="stylesheet" href="../css/style.css"> 
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --mantap-blue-dark: #0f172a;
            --mantap-blue-main: #1e40af;
            --mantap-blue-light: #3b82f6;
            --mantap-green: #22c55e;
            --mantap-red: #ef4444;
            --mantap-blue-soft: #eff6ff;
        }

        body { font-family: 'Poppins', sans-serif; background-color: #f8fafc; color: #334155; margin: 0; overflow-x: hidden !important; }
        .main-content-wrapper { max-width: 100% !important; width: 100% !important; box-sizing: border-box !important; box-shadow: none !important;}
        .admin-main-content { padding: 20px 25px 30px 25px !important; box-sizing: border-box !important; width: 100% !important; clear: both; }

        .page-header-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .page-header-controls h1 { font-weight: 700; color: #0f172a; font-size: 1.8rem; margin: 0; position: relative; }
        .page-header-controls h1::after { content: ''; position: absolute; left: 0; bottom: -5px; width: 50px; height: 4px; background: var(--mantap-blue-main); border-radius: 2px; }

        .header-actions-group { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .search-wrapper { position: relative; display: flex; align-items: center; }
        .search-wrapper i { position: absolute; left: 15px; color: #64748b; font-size: 14px; }
        .search-input { 
            padding: 9px 15px 9px 38px; border: 1px solid #cbd5e1; border-radius: 20px; 
            font-family: 'Poppins', sans-serif; font-size: 13px; width: 260px; transition: all 0.3s ease; 
            outline: none; background: #f8fafc;
        }
        .search-input:focus { border-color: var(--mantap-blue-main); width: 300px; background: white; box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.15); }

        .btn-back { background-color: #64748b; color: white !important; padding: 9px 20px; border: none; border-radius: 25px; cursor: pointer; font-size: 13px; font-weight: 600; font-family: 'Poppins', sans-serif; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 6px -1px rgba(100, 116, 139, 0.15); text-decoration: none; }
        .btn-back:hover { background-color: #475569; }

        .btn-export { background-color: #10b981; color: white !important; padding: 9px 20px; border: none; border-radius: 25px; cursor: pointer; font-size: 13px; font-weight: 600; font-family: 'Poppins', sans-serif; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.15); text-decoration: none; }
        .btn-export:hover { background-color: #059669; }

        .alert { padding: 12px 15px; margin-bottom: 20px; border-radius: 8px; border: 1px solid; font-size: 13.5px; font-weight: 500; text-align: left; }
        .alert.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert.error { background-color: #fef2f2; color: #ef4444; border-color: #fecaca; }

        .glass-panel-table { background: white !important; padding: 25px !important; border-radius: 16px !important; border: 2px solid #e2e8f0 !important; box-sizing: border-box; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02); width: 100%; }
        .table-container-fixed { width: 100%; overflow-x: auto; box-sizing: border-box; border: none !important; }

        .custom-table-core { width: 100%; table-layout: fixed; border-collapse: collapse; background: white; border-radius: 4px; overflow: hidden; border: 2px solid #1e40af; min-width: 1150px; }
        .custom-table-core th { background: #1e40af; color: white; padding: 14px 10px; font-size: 12px; text-transform: uppercase; font-weight: 700; text-align: center; border: 1px solid #cbd5e1; box-sizing: border-box; }
        .custom-table-core td { padding: 12px 10px; border: 1px solid #e2e8f0; font-size: 13px; vertical-align: top; background-color: white !important; box-sizing: border-box; line-height: 1.4; }
        .custom-table-core tbody tr:hover td { background-color: #f8fafc !important; }

        .custom-table-core th.col-no { width: 40px; }
        .custom-table-core th.col-waktu { width: 14%; }
        .custom-table-core th.col-siswa { width: 19%; text-align: left; padding-left: 15px; } 
        .custom-table-core th.col-catatan { width: 22%; text-align: left; padding-left: 15px; }
        .custom-table-core th.col-nilai { width: 32%; text-align: left; padding-left: 10px; } 
        .custom-table-core th.col-aksi { width: 115px; }

        /* INLINE RINCIAN TEXT */
        .rincian-inline { font-size: 11px; color: #64748b; line-height: 1.5; margin-top: 4px; }
        .rincian-inline b { color: #0f172a; }
        .separator { color: #cbd5e1; margin: 0 4px; }

        .dashboard-btn-group { display: flex; gap: 6px; justify-content: flex-start; flex-direction: column; }
        .btn-nilai, .btn-edit-nilai { color: white; padding: 6px 14px; border-radius: 6px; font-size: 11px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; width: 100%; justify-content: center; box-sizing: border-box; white-space: nowrap; }
        .btn-nilai { background-color: var(--mantap-blue-light); }
        .btn-nilai:hover { background-color: var(--mantap-blue-main); }
        .btn-edit-nilai { background-color: #f59e0b; }
        .btn-edit-nilai:hover { background-color: #d97706; }

        .text-success { color: #16a34a; }
        .text-danger { color: #dc2626; }

        .mantap-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.6); z-index: 9999; backdrop-filter: blur(4px); overflow-y: auto; }
        .mantap-modal-dialog { width: 90%; max-width: 650px; margin: 2rem auto; background: white; border-radius: 16px; padding: 25px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .mantap-modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px; margin-bottom: 20px; }
        .mantap-modal-header h5 { margin: 0; font-size: 1.3rem; font-weight: 700; color: var(--mantap-blue-dark); }
        .close-modal-btn { background: none; border: none; font-size: 1.8rem; cursor: pointer; color: #64748b; }

        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px; color: #334155; }
        .form-control { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Poppins'; font-size: 13px; box-sizing: border-box; }
        .form-control:focus { outline: none; border-color: var(--mantap-blue-main); }
        
        .kriteria-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px; }
        .kriteria-item { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 10px; }
        .kriteria-item:last-child { margin-bottom: 0; border-bottom: none; padding-bottom: 0; }
        .kriteria-nama { font-size: 13px; font-weight: 500; flex: 1; }
        .kriteria-bobot { font-size: 11px; color: #64748b; background: #e2e8f0; padding: 2px 6px; border-radius: 4px; margin-right: 15px; }
        .input-nilai-wrap input { width: 80px; text-align: center; font-weight: 600; color: var(--mantap-blue-main); }

        .total-box { display: flex; justify-content: space-between; align-items: center; background: var(--mantap-blue-soft); padding: 15px; border-radius: 8px; border: 2px solid #bfdbfe; margin-bottom: 20px; }
        .total-box h4 { margin: 0; color: var(--mantap-blue-dark); font-size: 15px; }
        .total-score { font-size: 24px; font-weight: 700; color: var(--mantap-blue-main); }

        .radio-group { display: flex; gap: 15px; }
        .radio-label { display: flex; align-items: center; gap: 6px; font-size: 14px; font-weight: 500; cursor: pointer; }
        
        .btn-submit { width: 100%; background: var(--mantap-blue-main); color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; margin-top: 10px; font-family: 'Poppins'; }
        .btn-submit:hover { background: var(--mantap-blue-dark); }

        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 
            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; }
            .header-actions-group { width: 100%; flex-direction: column; align-items: stretch; gap: 10px; }
            .search-wrapper { width: 100%; margin-top: 5px; }
            .search-input { width: 100%; box-sizing: border-box; }
            .search-input:focus { width: 100%; }
            .btn-back, .btn-export { width: 100% !important; justify-content: center; padding: 12px !important; border-radius: 8px !important; }
            .glass-panel-table { padding: 16px 10px !important; border-radius: 12px !important; border: 2px solid #cbd5e1 !important; }
            .table-container-fixed { display: block !important; width: 100% !important; overflow-x: auto !important; -webkit-overflow-scrolling: touch;}
            .custom-table-core { min-width: 1050px !important; }
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
            <h1>Penilaian Sidang PKL</h1>
            <div class="header-actions-group">
                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchSidang" class="search-input" placeholder="Cari Siswa / Penguji / Ruang..." onkeyup="filterTabelPenilaian()">
                </div>
                <a href="penilaian-sidang.php?action=export_nilai" class="btn-export"><i class="fas fa-file-excel"></i> Unduh Rekap Nilai</a>
                <a href="sidang-pkl.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali ke Jadwal</a>
            </div>
        </div>

        <?php echo $error_session; ?>
        <?php echo $message; ?>
        
        <div class="glass-panel-table">
            <div class="table-container-fixed">
                <table class="custom-table-core" id="tabelPenilaian">
                    <thead>
                        <tr>
                            <th class="col-no">NO</th>
                            <th class="col-waktu">TANGGAL & WAKTU</th>
                            <th class="col-siswa">SISWA</th>
                            <th class="col-catatan">CATATAN PENGUJI</th>
                            <th class="col-nilai">HASIL PENILAIAN</th>
                            <th class="col-aksi">AKSI SAYA</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($jadwal_sidang && $jadwal_sidang->num_rows > 0): ?>
                            <?php $no = 1; while ($row = $jadwal_sidang->fetch_assoc()): ?>
                            <tr class="baris-siswa">
                                <td style="text-align: center; font-weight: bold; color: #64748b;"><?php echo $no++; ?></td>
                                <td style="text-align: center;">
                                    <strong style="color: var(--mantap-blue-dark); font-size: 13.5px;"><?php echo formatTanggal($row['tanggal_ujian']); ?></strong><br>
                                    <span style="color: #ef4444; font-weight: 600;"><i class="far fa-clock"></i> <?php echo htmlspecialchars($row['waktu_sidang']); ?></span><br>
                                    <span style="font-size: 11.5px; color: #64748b;"><i class="fas fa-door-open"></i> <?php echo htmlspecialchars($row['ruangan_ujian']); ?></span>
                                </td>
                                <td style="text-align: left; padding-left: 15px;" class="nama-siswa-td">
                                    <strong style="color: var(--mantap-blue-main); font-size: 13.5px;"><?php echo htmlspecialchars($row['nama_siswa']); ?></strong><br>
                                    <span style="color: #64748b; font-size: 12px;"><?php echo htmlspecialchars($row['kelas']); ?></span>
                                </td>
                                
                                <!-- KOLOM CATATAN PENGUJI (PADAT NATIVE) -->
                                <td style="text-align: left;">
                                    <div style="margin-bottom: 8px;">
                                        <strong style="color:#475569; font-size: 12px;">P1 (<?php echo htmlspecialchars($row['nama_p1'] ?? '-'); ?>):</strong>
                                        <div style="font-style: italic; font-size: 11.5px; color: #334155; margin-top: 2px;">
                                            <?php echo !empty($row['cat_p1']) ? nl2br(htmlspecialchars($row['cat_p1'])) : '<span style="color:#94a3b8;">-</span>'; ?>
                                        </div>
                                    </div>
                                    <div>
                                        <strong style="color:#475569; font-size: 12px;">P2 (<?php echo htmlspecialchars($row['nama_p2'] ?? '-'); ?>):</strong>
                                        <div style="font-style: italic; font-size: 11.5px; color: #334155; margin-top: 2px;">
                                            <?php echo !empty($row['cat_p2']) ? nl2br(htmlspecialchars($row['cat_p2'])) : '<span style="color:#94a3b8;">-</span>'; ?>
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- KOLOM HASIL PENILAIAN (GABUNG NATIVE INLINE) -->
                                <td style="text-align: left;">
                                    
                                    <!-- Penguji 1 -->
                                    <div style="margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px dashed #cbd5e1;">
                                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 2px;">
                                            <strong style="color:#475569; font-size: 12px;">PENGUJI 1</strong>
                                            <?php if ($row['nilai_p1'] !== null): ?>
                                                <div style="font-size:13px;">
                                                    <strong style="color:var(--mantap-blue-main);"><?php echo number_format($row['nilai_p1'], 2); ?></strong> 
                                                    <?php echo ($row['kep_p1'] == 'Lulus') ? '<i class="fas fa-check-circle text-success" title="Lulus"></i>' : '<i class="fas fa-times-circle text-danger" title="Tidak Lulus"></i>'; ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="color:#94a3b8; font-style:italic; font-size:12px;">Belum dinilai</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($row['nilai_p1'] !== null): ?>
                                            <div class="rincian-inline">
                                                <?php 
                                                $arr1 = json_decode($row['json_p1'], true) ?: [];
                                                $rincian1 = [];
                                                foreach($kriteria as $k) {
                                                    $val = isset($arr1[$k['id']]) ? $arr1[$k['id']] : '-';
                                                    $nama_pendek = mb_strimwidth(trim($k['nama_kriteria']), 0, 15, '.');
                                                    $rincian1[] = "<span title='{$k['nama_kriteria']} (Bobot {$k['bobot']}%)'>{$nama_pendek}: <b>{$val}</b></span>";
                                                }
                                                echo implode(' <span class="separator">|</span> ', $rincian1);
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Penguji 2 -->
                                    <div>
                                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 2px;">
                                            <strong style="color:#475569; font-size: 12px;">PENGUJI 2</strong>
                                            <?php if ($row['nilai_p2'] !== null): ?>
                                                <div style="font-size:13px;">
                                                    <strong style="color:var(--mantap-blue-main);"><?php echo number_format($row['nilai_p2'], 2); ?></strong> 
                                                    <?php echo ($row['kep_p2'] == 'Lulus') ? '<i class="fas fa-check-circle text-success" title="Lulus"></i>' : '<i class="fas fa-times-circle text-danger" title="Tidak Lulus"></i>'; ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="color:#94a3b8; font-style:italic; font-size:12px;">Belum dinilai</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($row['nilai_p2'] !== null): ?>
                                            <div class="rincian-inline">
                                                <?php 
                                                $arr2 = json_decode($row['json_p2'], true) ?: [];
                                                $rincian2 = [];
                                                foreach($kriteria as $k) {
                                                    $val = isset($arr2[$k['id']]) ? $arr2[$k['id']] : '-';
                                                    $nama_pendek = mb_strimwidth(trim($k['nama_kriteria']), 0, 15, '.');
                                                    $rincian2[] = "<span title='{$k['nama_kriteria']} (Bobot {$k['bobot']}%)'>{$nama_pendek}: <b>{$val}</b></span>";
                                                }
                                                echo implode(' <span class="separator">|</span> ', $rincian2);
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                </td>

                                <!-- KOLOM AKSI SAYA -->
                                <td style="text-align: center;">
                                    <div class="dashboard-btn-group">
                                    <?php 
                                    $json_p1 = !empty($row['json_p1']) ? $row['json_p1'] : '{}';
                                    $json_p2 = !empty($row['json_p2']) ? $row['json_p2'] : '{}';
                                    $cat_p1  = htmlspecialchars($row['cat_p1'] ?? '', ENT_QUOTES);
                                    $cat_p2  = htmlspecialchars($row['cat_p2'] ?? '', ENT_QUOTES);
                                    $kep_p1  = htmlspecialchars($row['kep_p1'] ?? 'Lulus', ENT_QUOTES);
                                    $kep_p2  = htmlspecialchars($row['kep_p2'] ?? 'Lulus', ENT_QUOTES);
                                    $nama_sw = htmlspecialchars($row['nama_siswa'], ENT_QUOTES);

                                    if ($current_user_level === 'admin') {
                                        $btn1_text = $row['id_pen_p1'] ? "<i class='fas fa-edit'></i> Edit Nilai P1" : "<i class='fas fa-star'></i> Beri Nilai P1";
                                        $btn1_class = $row['id_pen_p1'] ? "btn-edit-nilai" : "btn-nilai";
                                        echo "<button class='$btn1_class' onclick='bukaModalNilai({$row['id_sidang']}, \"$nama_sw\", $json_p1, \"$cat_p1\", \"$kep_p1\", \"Penguji 1\")'>$btn1_text</button>";
                                        
                                        $btn2_text = $row['id_pen_p2'] ? "<i class='fas fa-edit'></i> Edit Nilai P2" : "<i class='fas fa-star'></i> Beri Nilai P2";
                                        $btn2_class = $row['id_pen_p2'] ? "btn-edit-nilai" : "btn-nilai";
                                        echo "<button class='$btn2_class' onclick='bukaModalNilai({$row['id_sidang']}, \"$nama_sw\", $json_p2, \"$cat_p2\", \"$kep_p2\", \"Penguji 2\")'>$btn2_text</button>";
                                    } else {
                                        $is_p1 = ($guru_id == $row['penguji_1'] || $user_full_name == $row['nama_p1']);
                                        $is_p2 = ($guru_id == $row['penguji_2'] || $user_full_name == $row['nama_p2']);

                                        if ($is_p1) {
                                            $btn_text = $row['id_pen_p1'] ? "<i class='fas fa-edit'></i> Edit Nilai Saya" : "<i class='fas fa-star'></i> Beri Nilai";
                                            $btn_class = $row['id_pen_p1'] ? "btn-edit-nilai" : "btn-nilai";
                                            echo "<button class='$btn_class' onclick='bukaModalNilai({$row['id_sidang']}, \"$nama_sw\", $json_p1, \"$cat_p1\", \"$kep_p1\", \"Penguji 1\")'>$btn_text</button>";
                                        } elseif ($is_p2) {
                                            $btn_text = $row['id_pen_p2'] ? "<i class='fas fa-edit'></i> Edit Nilai Saya" : "<i class='fas fa-star'></i> Beri Nilai";
                                            $btn_class = $row['id_pen_p2'] ? "btn-edit-nilai" : "btn-nilai";
                                            echo "<button class='$btn_class' onclick='bukaModalNilai({$row['id_sidang']}, \"$nama_sw\", $json_p2, \"$cat_p2\", \"$kep_p2\", \"Penguji 2\")'>$btn_text</button>";
                                        } else {
                                            echo "<span style='font-size:11px; color:#94a3b8; font-style:italic;'>Hanya Peninjau</span>";
                                        }
                                    }
                                    ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align: center; color: #ef4444; padding: 30px;">Tidak ada jadwal sidang untuk dinilai saat ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="mantap-modal" id="modalPenilaian">
    <div class="mantap-modal-dialog">
        <div class="mantap-modal-header">
            <h5 id="modalTitleText"><i class="fas fa-clipboard-check text-primary"></i> Form Penilaian Sidang</h5>
            <button class="close-modal-btn" onclick="tutupModal()">&times;</button>
        </div>
        <form method="POST" action="penilaian-sidang.php">
            <input type="hidden" name="submit_nilai" value="1">
            <input type="hidden" name="id_sidang" id="id_sidang" value="">
            <input type="hidden" name="target_role" id="target_role" value="">
            
            <div style="margin-bottom: 20px; padding: 10px 15px; background: #f1f5f9; border-radius: 8px; border-left: 4px solid var(--mantap-blue-main);">
                <span style="font-size: 12px; color: #64748b;">Nama Siswa:</span><br>
                <strong id="display_nama_siswa" style="font-size: 15px; color: var(--mantap-blue-dark);"></strong>
            </div>

            <div class="kriteria-box">
                <div style="margin-bottom: 15px; font-weight: 600; font-size: 14px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">Aspek Penilaian (Skala 0 - 100)</div>
                
                <?php if(empty($kriteria)): ?>
                    <div style="color:red; font-size: 12px;">Kriteria penilaian belum diatur oleh Admin.</div>
                <?php else: ?>
                    <?php foreach($kriteria as $k): ?>
                        <div class="kriteria-item">
                            <div class="kriteria-nama">
                                <?php echo htmlspecialchars($k['nama_kriteria']); ?>
                                <span class="kriteria-bobot">Bobot: <?php echo $k['bobot']; ?>%</span>
                            </div>
                            <div class="input-nilai-wrap">
                                <input type="number" name="nilai_kriteria[<?php echo $k['id']; ?>]" id="input_kriteria_<?php echo $k['id']; ?>" class="form-control input-kalkulasi" min="0" max="100" data-bobot="<?php echo $k['bobot']; ?>" required oninput="hitungTotal()" placeholder="0">
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="total-box">
                <h4>Total Nilai Akhir</h4>
                <div class="total-score" id="display_total">0.00</div>
            </div>

            <div class="form-group">
                <label>Rekomendasi Kelulusan Anda</label>
                <div class="radio-group">
                    <label class="radio-label">
                        <input type="radio" name="keputusan" id="status_lulus" value="Lulus" required>
                        <span style="color: #166534;"><i class="fas fa-check-circle"></i> Lulus</span>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="keputusan" id="status_tidaklulus" value="Tidak Lulus" required>
                        <span style="color: #991b1b;"><i class="fas fa-times-circle"></i> Tidak Lulus</span>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label for="catatan">Catatan / Revisi untuk Siswa</label>
                <textarea name="catatan" id="catatan" class="form-control" rows="3" placeholder="Tuliskan catatan perbaikan atau feedback untuk siswa..."></textarea>
            </div>

            <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Simpan Penilaian</button>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('modalPenilaian');
    const userLevel = "<?php echo $current_user_level; ?>";

    // FUNGSI FILTER/PENCARIAN (REAL-TIME MATCHING DENGAN SIDANG-PKL.PHP)
    function filterTabelPenilaian() {
        let input = document.getElementById("searchSidang");
        let filter = input.value.toUpperCase();
        let barisSiswa = document.getElementsByClassName("baris-siswa");

        for (let i = 0; i < barisSiswa.length; i++) {
            let textValue = barisSiswa[i].textContent || barisSiswa[i].innerText;
            if (textValue.toUpperCase().indexOf(filter) > -1) {
                barisSiswa[i].style.display = "";
            } else {
                barisSiswa[i].style.display = "none";
            }
        }
    }

    function bukaModalNilai(id_sidang, nama_siswa, detail_nilai, catatan, keputusan, rolePenguji) {
        document.getElementById('id_sidang').value = id_sidang;
        document.getElementById('display_nama_siswa').innerText = nama_siswa;
        document.getElementById('catatan').value = catatan;
        document.getElementById('target_role').value = rolePenguji;

        if (userLevel === 'admin') {
            document.getElementById('modalTitleText').innerHTML = `<i class="fas fa-clipboard-check text-primary"></i> Form Penilaian (${rolePenguji})`;
        }

        if (keputusan === 'Tidak Lulus') {
            document.getElementById('status_tidaklulus').checked = true;
        } else {
            document.getElementById('status_lulus').checked = true;
        }

        document.querySelectorAll('.input-kalkulasi').forEach(input => {
            let idKriteria = input.id.replace('input_kriteria_', '');
            if (detail_nilai[idKriteria] !== undefined) {
                input.value = detail_nilai[idKriteria];
            } else {
                input.value = '';
            }
        });

        hitungTotal(); 
        modal.style.display = 'block';
    }

    function tutupModal() {
        modal.style.display = 'none';
    }

    function hitungTotal() {
        let total = 0;
        document.querySelectorAll('.input-kalkulasi').forEach(input => {
            let nilai = parseFloat(input.value) || 0;
            if(nilai > 100) { nilai = 100; input.value = 100; }
            if(nilai < 0) { nilai = 0; input.value = 0; }
            
            let bobot = parseFloat(input.getAttribute('data-bobot')) || 0;
            total += nilai * (bobot / 100);
        });
        document.getElementById('display_total').innerText = total.toFixed(2);
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            tutupModal();
        }
    }
</script>

<?php include 'panel/footer.php'; ?>
</body>
</html>