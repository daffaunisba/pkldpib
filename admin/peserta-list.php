<?php 
// admin/peserta-list.php
include 'auth-check.php'; // PENTING: Proteksi Login
include '../config/db-koneksi.php';

// Generate CSRF Token jika belum ada
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$current_user = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin';
$current_user_id = $_SESSION['user_id'] ?? 0; 
$user_level   = isset($_SESSION['level']) ? strtolower($_SESSION['level']) : 'pembimbing';

// Menangkap status pesan dari URL (Post-Redirect-Get Pattern)
$message = isset($_GET['status']) ? $_GET['status'] : '';

$guru_filter_id = 0;
$where_clause = "";
$conditions = [];

// ---------------------------------------------------------------------
// CEK DINAMIS KOLOM UPDATE SPESIFIK (ANTI-ERROR)
// ---------------------------------------------------------------------
$check_col = $koneksi->query("SHOW COLUMNS FROM peserta_didik LIKE 'update_email'");
$has_field_updates = ($check_col && $check_col->num_rows > 0);
$col_diupdate_select = $has_field_updates ? "p.update_email, p.update_no_hp" : "NULL AS update_email, NULL AS update_no_hp";

// ---------------------------------------------------------------------
// 1. AMBIL GURU_ID JIKA YANG LOGIN ADALAH PEMBIMBING/GURU
// ---------------------------------------------------------------------
if ($user_level == 'pembimbing' || $user_level == 'guru') {
    $stmt_get_guru_id = $koneksi->prepare("SELECT guru_id FROM guru WHERE user_id = ?");
    if ($stmt_get_guru_id) {
        $stmt_get_guru_id->bind_param("i", $current_user_id);
        $stmt_get_guru_id->execute();
        $result_guru_id = $stmt_get_guru_id->get_result();
        if ($result_guru_id->num_rows > 0) {
            $guru_filter_id = $result_guru_id->fetch_assoc()['guru_id'];
            $conditions[] = "l.guru_id = {$guru_filter_id}";
        }
        $stmt_get_guru_id->close();
    }
}

// =========================================================================================
// LOGIKA FILTER ARSIP: SEMBUNYIKAN PESERTA JIKA GELOMBANGNYA SUDAH SELESAI/DIARSIPKAN
// Ini memastikan mereka "hilang" dari tabel utama seolah-olah sudah dihapus
// =========================================================================================
$conditions[] = "(pr.is_archived = 0 OR pr.is_archived IS NULL)";

// =========================================================================================
// LOGIKA PROSES NAIK KELAS OTOMATIS (KHUSUS ADMIN)
// =========================================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['proses_naik_kelas'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Akses ilegal: Validasi token keamanan gagal.");
    }
    
    if ($user_level === 'admin') {
        $siswa_ids = isset($_POST['siswa_ids']) ? $_POST['siswa_ids'] : [];

        if (!empty($siswa_ids)) {
            $ids_string = implode(',', array_map('intval', $siswa_ids));
            
            // Ambil data kelas saat ini dari siswa yang dicentang
            $cek_kelas = $koneksi->query("SELECT id, kelas FROM peserta_didik WHERE id IN ($ids_string)");
            
            $berhasil = 0;
            if ($cek_kelas && $cek_kelas->num_rows > 0) {
                while ($row = $cek_kelas->fetch_assoc()) {
                    $id_siswa_nk = $row['id'];
                    $kelas_lama = trim($row['kelas']);
                    $kelas_baru = $kelas_lama; // Default tidak berubah

                    // Logika Parsing: X DPIB 1 -> XI DPIB 1 -> XII DPIB 1
                    $parts = explode(' ', $kelas_lama);
                    if (count($parts) >= 2) {
                        if ($parts[0] === 'X') {
                            $parts[0] = 'XI';
                            $kelas_baru = implode(' ', $parts);
                        } elseif ($parts[0] === 'XI') {
                            $parts[0] = 'XII';
                            $kelas_baru = implode(' ', $parts);
                        }
                    }

                    // Hanya update jika kelas benar-benar berubah
                    if ($kelas_baru !== $kelas_lama) {
                        $koneksi->query("UPDATE peserta_didik SET kelas = '$kelas_baru' WHERE id = $id_siswa_nk");
                        $berhasil++;
                    }
                }
                
                catatLog($koneksi, $current_user_id, "Melakukan Naik Kelas Otomatis massal terhadap " . $berhasil . " siswa yang dipilih.");
                header("Location: peserta-list.php?status=success_naik_kelas");
                exit;
            }
        }
    } else {
        header("Location: peserta-list.php?status=denied_update");
        exit;
    }
}

// === LOGIKA PROSES UPDATE DATA PESERTA DARI MODAL POP-UP ===
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['proses_update_peserta'])) {
    // Validasi CSRF Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Akses ilegal: Validasi token keamanan gagal.");
    }

    $siswa_id = (int)$_POST['modal_siswa_id'];
    $nisn = trim($_POST['modal_nisn']);
    $nama = trim($_POST['modal_nama']);
    $email = trim($_POST['modal_email']);
    $no_hp = trim($_POST['modal_no_hp']);
    $kelas = trim($_POST['modal_kelas']);
    $periode_id = (int)$_POST['modal_periode_id'];
    $lokasi_id = (int)$_POST['modal_lokasi_id'];

    // PROTEKSI BACKEND: Cegah update jika siswa ini bagian dari periode yang sudah diarsipkan
    $cek_arsip_up = $koneksi->query("SELECT pr.is_archived FROM peserta_didik p LEFT JOIN periode_pkl pr ON p.periode_id = pr.periode_id WHERE p.id = $siswa_id");
    if ($cek_arsip_up && $cek_arsip_up->num_rows > 0) {
        $is_arch = $cek_arsip_up->fetch_assoc()['is_archived'];
        if ($is_arch == 1) {
            die("Akses ditolak: Data siswa ini berada di dalam periode yang sudah diarsipkan/selesai.");
        }
    }

    $boleh_update = false;
    if ($user_level === 'admin') {
        $boleh_update = true;
    } else {
        if ($lokasi_id > 0) {
            $check_auth = $koneksi->prepare("SELECT guru_id FROM lokasi_pkl WHERE lokasi_id = ?");
            $check_auth->bind_param("i", $lokasi_id);
            $check_auth->execute();
            $check_auth->bind_result($loc_guru_id);
            $check_auth->fetch();
            $check_auth->close();
            if ($loc_guru_id == $guru_filter_id) { $boleh_update = true; }
        }
    }

    if ($boleh_update) {
        $check_dup = $koneksi->prepare("SELECT COUNT(*) FROM peserta_didik WHERE nisn = ? AND id != ?");
        $check_dup->bind_param("si", $nisn, $siswa_id);
        $check_dup->execute();
        $check_dup->bind_result($dup_count);
        $check_dup->fetch();
        $check_dup->close();

        if ($dup_count > 0) {
            header("Location: peserta-list.php?status=duplicate_nisn");
            exit;
        } else {
            
            // --- DETEKSI PERUBAHAN DATA UNTUK LOG SUPER DETAIL ---
            $stmt_old = $koneksi->prepare("SELECT nisn, nama, email, no_hp, kelas, periode_id, lokasi_id FROM peserta_didik WHERE id = ?");
            $stmt_old->bind_param("i", $siswa_id);
            $stmt_old->execute();
            $old_data = $stmt_old->get_result()->fetch_assoc();
            $stmt_old->close();

            $perubahan = [];
            if ($old_data['nisn'] != $nisn) $perubahan[] = "NISN";
            if ($old_data['nama'] != $nama) $perubahan[] = "Nama Lengkap";
            if ($old_data['email'] != $email) $perubahan[] = "Email";
            if ($old_data['no_hp'] != $no_hp) $perubahan[] = "No. HP";
            if ($old_data['kelas'] != $kelas) $perubahan[] = "Kelas";
            if ($old_data['periode_id'] != $periode_id) $perubahan[] = "Gelombang/Periode";
            if ($old_data['lokasi_id'] != $lokasi_id) $perubahan[] = "Lokasi PKL";

            $pesan_log = "";
            if (count($perubahan) > 0) {
                $detail_ubah = implode(", ", $perubahan);
                $pesan_log = "Memperbarui data peserta didik: " . $nama . " (Detail yang diubah: " . $detail_ubah . ")";
            } else {
                $pesan_log = "Mengecek/menyimpan ulang data peserta didik: " . $nama . " (Tanpa perubahan data)";
            }
            // ------------------------------------------

            // Reset Notifikasi Spesifik Jika Admin Menyimpan Perubahan
            if ($has_field_updates) {
                $update_stmt = $koneksi->prepare("UPDATE peserta_didik SET nisn = ?, nama = ?, email = ?, no_hp = ?, kelas = ?, periode_id = ?, lokasi_id = ?, update_email = NULL, update_no_hp = NULL, update_alamat = NULL, update_foto = NULL WHERE id = ?");
            } else {
                $update_stmt = $koneksi->prepare("UPDATE peserta_didik SET nisn = ?, nama = ?, email = ?, no_hp = ?, kelas = ?, periode_id = ?, lokasi_id = ? WHERE id = ?");
            }
            $update_stmt->bind_param("sssssiii", $nisn, $nama, $email, $no_hp, $kelas, $periode_id, $lokasi_id, $siswa_id);
            
            if ($update_stmt->execute()) {
                // --- TRIGGER LOG AKTIVITAS (UPDATE DETAIL) ---
                catatLog($koneksi, $current_user_id, $pesan_log);

                header("Location: peserta-list.php?status=success_update");
                exit;
            } else {
                header("Location: peserta-list.php?status=error_update");
                exit;
            }
            $update_stmt->close();
        }
    } else {
        header("Location: peserta-list.php?status=denied_update");
        exit;
    }
}

// ---------------------------------------------------------------------
// 2. HANDLE DELETE ACTION
// ---------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    if (!isset($_GET['token']) || $_GET['token'] !== $_SESSION['csrf_token']) {
        die("Akses ilegal: Validasi token keamanan gagal.");
    }

    $id_peserta = (int)$_GET['id'];
    
    if ($id_peserta > 0) {
        
        // PROTEKSI BACKEND: Cegah Hapus jika siswa bagian dari periode yang diarsipkan
        $cek_arsip_del = $koneksi->query("SELECT pr.is_archived FROM peserta_didik p LEFT JOIN periode_pkl pr ON p.periode_id = pr.periode_id WHERE p.id = $id_peserta");
        if ($cek_arsip_del && $cek_arsip_del->num_rows > 0) {
            if ($cek_arsip_del->fetch_assoc()['is_archived'] == 1) {
                die("Akses ditolak: Data siswa ini tidak bisa dihapus karena merupakan bagian dari arsip.");
            }
        }

        $boleh_hapus = false;

        if ($user_level === 'admin') {
            $boleh_hapus = true;
        } else {
            $check_bimbingan_stmt = $koneksi->prepare("SELECT COUNT(*) FROM peserta_didik p LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id WHERE p.id = ? AND l.guru_id = ?");
            $check_bimbingan_stmt->bind_param("ii", $id_peserta, $guru_filter_id);
            $check_bimbingan_stmt->execute();
            $check_bimbingan_stmt->bind_result($is_my_student);
            $check_bimbingan_stmt->fetch();
            $check_bimbingan_stmt->close();
            
            if ($is_my_student > 0) { $boleh_hapus = true; }
        }

        if ($boleh_hapus) {
            // --- AMBIL NAMA SISWA SEBELUM DIHAPUS UNTUK LOG ---
            $nama_dihapus = "ID " . $id_peserta;
            $cek_nama = $koneksi->query("SELECT nama FROM peserta_didik WHERE id = $id_peserta");
            if ($cek_nama && $cek_nama->num_rows > 0) {
                $nama_dihapus = $cek_nama->fetch_assoc()['nama'];
            }

            $delete_stmt = $koneksi->prepare("DELETE FROM peserta_didik WHERE id = ?");
            $delete_stmt->bind_param("i", $id_peserta);
            
            if ($delete_stmt->execute()) {
                // --- TRIGGER LOG AKTIVITAS (DELETE) ---
                catatLog($koneksi, $current_user_id, "Menghapus permanen data peserta didik: " . $nama_dihapus);

                header("Location: peserta-list.php?status=success_delete");
                exit;
            } else {
                header("Location: peserta-list.php?status=error_delete");
                exit;
            }
            $delete_stmt->close();
        } else {
            header("Location: peserta-list.php?status=denied_delete");
            exit;
        }
    }
}

if (count($conditions) > 0) {
    $where_clause = "WHERE " . implode(' AND ', $conditions);
}

// OPTIMASI: Saring lokasi dropdown
$lokasi_query = "SELECT l.lokasi_id, l.nama_lokasi, l.kuota_max, (SELECT COUNT(id) FROM peserta_didik WHERE lokasi_id = l.lokasi_id) AS terisi FROM lokasi_pkl l";
if ($user_level !== 'admin' && $guru_filter_id > 0) {
    $lokasi_query .= " WHERE l.guru_id = {$guru_filter_id}";
}
$lokasi_query .= " ORDER BY l.nama_lokasi ASC";
$lokasi_options_res = $koneksi->query($lokasi_query);
$lokasi_options = $lokasi_options_res ? $lokasi_options_res->fetch_all(MYSQLI_ASSOC) : [];

// HANYA MENGAMBIL PERIODE YANG BELUM DIARSIPKAN UNTUK FILTER DAN DROPDOWN EDIT
$periode_options_res = $koneksi->query("SELECT periode_id, nama_periode FROM periode_pkl WHERE is_archived = 0 OR is_archived IS NULL ORDER BY tgl_mulai DESC");
$periode_options = $periode_options_res ? $periode_options_res->fetch_all(MYSQLI_ASSOC) : [];

$daftar_kelas = ["X DPIB 1", "X DPIB 2", "XI DPIB 1", "XI DPIB 2", "XI DPIB 3", "XII DPIB 1", "XII DPIB 2", "XII DPIB 3"];

// Ambil Data Peserta Didik Utama
$peserta_query = "
    SELECT 
        p.id, p.nisn, p.nama, p.email, p.kelas, p.no_hp, p.periode_id, p.lokasi_id,
        {$col_diupdate_select},
        l.nama_lokasi, l.guru_id AS lokasi_guru_id,
        pr.nama_periode, pr.tgl_mulai, pr.tgl_akhir,
        g.nama_guru
    FROM peserta_didik p
    LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
    LEFT JOIN guru g ON l.guru_id = g.guru_id
    LEFT JOIN periode_pkl pr ON p.periode_id = pr.periode_id 
    {$where_clause}
    ORDER BY p.id ASC
";
$peserta_data = $koneksi->query($peserta_query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin - Daftar Peserta | Si Mantap PKL</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        
        .table-periode {
            font-size: 13px;
            font-weight: 700;
            color: var(--mantap-blue-main);
            display: block;
            margin-bottom: 4px;
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

        .header-actions-group {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        /* --- STYLING PENCARIAN & FILTER --- */
        .search-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-wrapper i {
            position: absolute;
            left: 12px;
            color: #64748b;
            font-size: 14px;
        }

        .search-input {
            padding: 8px 15px 8px 35px;
            border: 1px solid #cbd5e1;
            border-radius: 20px;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            width: 220px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            outline: none;
        }
        
        select.search-input {
            padding-left: 15px;
            cursor: pointer;
        }

        .search-input:focus {
            border-color: var(--mantap-blue-light);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
            width: 260px;
        }

        .header-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .btn-manage {
            color: white !important;
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08);
            transition: 0.2s;
        }
        .btn-manage:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 10px rgba(0,0,0,0.15);
        }

        .glass-panel-table {
            background: white !important;
            padding: 25px !important;
            border-radius: 16px !important;
            border: 2px solid #e2e8f0 !important; 
            box-sizing: border-box !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -2px rgba(0, 0, 0, 0.02);
            width: 100%;
        }

        .table-container-fixed {
            width: 100%;
            overflow-x: auto; 
            box-sizing: border-box !important;
            border: none !important;
        }

        .admin-main-content table {
            width: 100%;
            table-layout: fixed; 
            border-collapse: collapse;
            background: white;
            border-radius: 4px;
            overflow: hidden;
            border: 2px solid #1e40af; 
        }

        .admin-main-content table th { 
            background: #1e40af; 
            color: white; 
            padding: 14px 6px; 
            font-size: 13px; 
            text-transform: uppercase; 
            font-weight: 700;
            text-align: center;
            border: 1px solid #cbd5e1;
            box-sizing: border-box;
        }

        .admin-main-content table th.w-cb { width: 45px; text-align: center; padding: 14px 6px; }
        .admin-main-content table th.w-no { width: 60px; }
        .admin-main-content table th.w-periode { width: 14%; }
        .admin-main-content table th.w-nisn { width: 13%; }
        .admin-main-content table th.w-detail { width: 27%; text-align: left; }
        .admin-main-content table th.w-kelas { width: 11%; }
        .admin-main-content table th.w-lokasi { width: 23%; text-align: left; }
        .admin-main-content table th.w-aksi { width: 95px; } 

        .admin-main-content table td {
            padding: 12px 10px;
            font-size: 13px;
            vertical-align: middle;
            word-wrap: break-word;
            overflow-wrap: break-word;
            text-align: center;
            border: 1px solid #cbd5e1;
            color: #0f172a;
            background-color: white !important; 
            box-sizing: border-box;
        }

        .admin-main-content tr:hover td { 
            background-color: #f8fafc !important; 
        }

        .student-meta-block { text-align: left; }
        .student-name-title { font-weight: 700; color: #0f172a; font-size: 13.5px; margin-bottom: 5px; text-transform: uppercase; display: block; }
        
        /* Modifikasi Struktur Info & Badge Update */
        .student-sub-info { font-size: 11px; color: #64748b; display: flex; flex-direction: column; gap: 4px; }
        .student-sub-info > div { display: flex; align-items: center; flex-wrap: wrap; }
        .student-sub-info i { width: 14px; color: #3b82f6; }
        
        .badge-inline-update {
            background: #fff7ed; 
            color: #ea580c; 
            padding: 2px 6px; 
            border-radius: 4px; 
            font-size: 9px; 
            font-weight: 700; 
            border: 1px solid #fdba74;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            white-space: nowrap;
            margin-left: 8px;
        }

        .dashboard-btn-group { display: flex; flex-direction: column; gap: 5px; align-items: center; justify-content: center; }
        
        .btn-edit { 
            background-color: var(--mantap-blue-main); color: white !important; padding: 5px 0; border-radius: 4px; 
            text-decoration: none; font-size: 11px; font-weight: 600; border: none; cursor: pointer; 
            width: 75px; text-align: center; box-shadow: 0 2px 4px rgba(30, 64, 175, 0.15);
        }
        .btn-edit:hover { background-color: var(--mantap-blue-light); }
        
        .btn-delete { 
            background-color: #dc3545; color: white !important; padding: 5px 0; border-radius: 4px; 
            text-decoration: none; font-size: 11px; font-weight: 600; border: none; cursor: pointer;
            width: 75px; text-align: center; box-shadow: 0 2px 4px rgba(220, 53, 69, 0.15);
        }
        .btn-delete:hover { background-color: #ef4444; }

        .mantap-modal {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(15, 23, 42, 0.5); z-index: 999999 !important; overflow-x: hidden; overflow-y: auto; box-sizing: border-box !important;
        }
        .mantap-modal-dialog { position: relative; width: 90%; max-width: 740px; margin: 4.5rem auto; box-sizing: border-box !important; }
        .mantap-modal-content { 
            background-color: white; padding: 28px; border-radius: 12px; border: 2px solid #cbd5e1; width: 100%; height: auto !important; 
            box-sizing: border-box !important; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15);
        }
        .mantap-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; box-sizing: border-box !important; }
        .mantap-modal-header h2 { font-size: 1.4rem; margin: 0; color: #0f172a; font-weight: 700; }
        .close-modal-btn { background: none; border: none; font-size: 1.7rem; color: #64748b; cursor: pointer; line-height: 1; }
        
        .modal-grid-landscape { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; box-sizing: border-box !important; }
        .modal-form-group { text-align: left; margin-bottom: 14px; box-sizing: border-box !important; }
        .modal-form-group label { display: block; font-weight: 600; font-size: 13px; margin-bottom: 5px; color: #0f172a; }
        .modal-form-group input, .modal-form-group select { width: 100%; padding: 11px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 13px; box-sizing: border-box !important; background-color: #f8fafc; }
        .modal-form-group input:focus, .modal-form-group select:focus { outline: none; border-color: #1e40af; background-color: white; }
        .modal-action-btn { background-color: #1e40af; color: white; padding: 11px 24px; border: none; border-radius: 20px; font-weight: 600; font-size: 13px; cursor: pointer; font-family: 'Poppins', sans-serif; box-sizing: border-box !important; }

        @media (max-width: 768px) { 
            body { padding-top: 60px !important; } 
            .admin-main-content { padding: 15px 12px 25px 12px !important; } 
            
            .page-header-controls { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; padding: 5px 0px !important; }
            .page-header-controls h1 { font-size: 1.4rem !important; margin-bottom: 10px; }
            
            .header-actions-group { width: 100%; flex-direction: column; align-items: stretch; gap: 12px; }
            .search-wrapper { width: 100%; }
            .search-input { width: 100%; }
            .search-input:focus { width: 100%; }
            
            .header-buttons { width: 100% !important; display: flex !important; flex-direction: row !important; flex-wrap: wrap !important; gap: 8px !important; }
            .header-buttons .btn-manage { width: auto !important; flex: 1 1 auto !important; text-align: center !important; justify-content: center !important; padding: 8px 14px !important; font-size: 11.5px !important; border-radius: 6px !important; }

            .glass-panel-table { padding: 16px 10px !important; border-radius: 12px !important; border: 2px solid #cbd5e1 !important; }
            .table-container-fixed { display: block !important; width: 100% !important; overflow-x: auto !important; border: none !important; }

            .admin-main-content table { table-layout: auto !important; min-width: 980px !important; }
            .admin-main-content table th, .admin-main-content table td { padding: 12px 10px !important; font-size: 13px !important; text-align: center !important; }
            
            .dashboard-btn-group { flex-direction: row !important; gap: 6px !important; }
            .btn-edit, .btn-delete { width: 75px !important; padding: 6px 0 !important; font-size: 12px !important; }

            .mantap-modal { padding: 12px !important; box-sizing: border-box !important; } 
            .mantap-modal-dialog { margin: 1.0rem auto !important; width: 100% !important; max-width: 100% !important; box-sizing: border-box !important; }
            .mantap-modal-content { 
                width: 100% !important; max-width: 100% !important; padding: 16px 14px !important; border-radius: 10px !important; height: auto !important; 
                max-height: calc(100vh - 40px) !important; overflow-y: auto !important; box-sizing: border-box !important;
            }
            .modal-grid-landscape { display: flex !important; flex-direction: column !important; gap: 0px !important; box-sizing: border-box !important; }
            .modal-form-group { margin-bottom: 10px !important; box-sizing: border-box !important; width: 100% !important; }
            .modal-form-group label { font-size: 13px !important; margin-bottom: 3px !important; }
            .modal-form-group input, .modal-form-group select { width: 100% !important; font-size: 13.5px !important; padding: 10px !important; box-sizing: border-box !important; }
            .modal-action-btn { width: 100% !important; padding: 12px !important; font-size: 14.5px !important; border-radius: 8px !important; margin-top: 10px !important; box-sizing: border-box !important; }
        }
    </style>
</head>
<body class="admin-body">

<?php include 'panel/sidebar.php'; ?>

<div class="main-content-wrapper">

    <?php include 'panel/navbar.php'; ?>

    <div class="admin-main-content"> 
        <div class="page-header-controls">
            <h1>Data Peserta Didik PKL <?php echo ($user_level !== 'admin') ? '(Bimbingan Anda)' : ''; ?></h1>
            
            <div class="header-actions-group">
                <div class="search-wrapper">
                    <select id="filterPeriode" class="search-input" onchange="filterTable()">
                        <option value="">Semua Gelombang</option>
                        <?php foreach ($periode_options as $per): ?>
                            <option value="<?php echo htmlspecialchars($per['nama_periode']); ?>"><?php echo htmlspecialchars($per['nama_periode']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" class="search-input" placeholder="Cari nama peserta..." onkeyup="filterTable()">
                </div>

                <div class="header-buttons">
                    <?php if ($user_level === 'admin'): ?>
                        <button type="button" class="btn-manage" style="background-color: #f59e0b;" id="btnNaikKelasModal">
                            <i class="fas fa-level-up-alt"></i> Naik Kelas
                        </button>
                        <a href="peserta-excel.php" class="btn-manage" style="background-color: #28a745;">
                            <i class="fas fa-file-excel"></i> Cetak Excel
                        </a>
                        <a href="periode-manage.php" class="btn-manage" style="background-color: #1e40af;">
                            <i class="fas fa-calendar-alt"></i> Kelola Gelombang
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="glass-panel-table">
            <div class="table-container-fixed">
                <table id="pesertaTable">
                    <thead>
                        <tr>
                            <?php if ($user_level === 'admin'): ?>
                                <th class="w-cb"><input type="checkbox" id="checkAllSiswa" style="cursor:pointer; width:15px; height:15px;"></th>
                            <?php endif; ?>
                            <th class="w-no">NO</th>
                            <th class="w-periode">PERIODE</th>
                            <th class="w-nisn">NISN</th>
                            <th class="w-detail" style="text-align: left;">DATA DETAIL SISWA</th> 
                            <th class="w-kelas">KELAS</th>
                            <th class="w-lokasi" style="text-align: left;">LOKASI TEMPAT PKL</th>
                            <th class="w-aksi">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($peserta_data->num_rows == 0): ?>
                            <tr>
                                <?php $col_count = ($user_level === 'admin') ? '8' : '7'; ?>
                                <td colspan="<?php echo $col_count; ?>" style="text-align: center; color: #dc3545; font-style: italic; padding: 25px;">
                                    Tidak ada data peserta didik yang ditemukan (Atau semua data berada di Arsip).
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php $no = 1; while ($row = $peserta_data->fetch_assoc()): ?>
                        <tr>
                            <?php if ($user_level === 'admin'): ?>
                                <td>
                                    <input type="checkbox" class="check-siswa" value="<?php echo $row['id']; ?>" style="cursor:pointer; width:15px; height:15px;">
                                </td>
                            <?php endif; ?>
                            <td style="font-weight: 700;"><?php echo $no++; ?></td>
                            <td>
                                <span class="table-periode"><?php echo $row['nama_periode'] ? htmlspecialchars($row['nama_periode']) : '-'; ?></span>
                                <?php if (!empty($row['tgl_mulai']) && !empty($row['tgl_akhir'])): ?>
                                    <div style="font-size: 11px; color: #64748b; font-weight: 500; line-height: 1.4;">
                                        <span style="color: #10b981; font-weight: 700;">Mulai:</span> <?php echo date('d/m/Y', strtotime($row['tgl_mulai'])); ?><br>
                                        <span style="color: #ef4444; font-weight: 700;">Selesai:</span> <?php echo date('d/m/Y', strtotime($row['tgl_akhir'])); ?>
                                    </div>
                                <?php endif; ?>
                            </td> 
                            <td style="font-weight: 500; color: #475569;"><?php echo htmlspecialchars($row['nisn']); ?></td>
                            
                            <td>
                                <div class="student-meta-block">
                                    <span class="student-name-title"><?php echo htmlspecialchars($row['nama']); ?></span>
                                    <div class="student-sub-info">
                                        <div>
                                            <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($row['email']); ?></span>
                                            <?php if (!empty($row['update_email'])): ?>
                                                <span class="badge-inline-update" title="Email telah diubah siswa">
                                                    <i class="fas fa-pencil-alt" style="color: #ea580c; width:auto;"></i> 
                                                    Diperbarui: <?php echo date('d/m/y H:i', strtotime($row['update_email'])); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div>
                                            <span><i class="fab fa-whatsapp"></i> <?php echo htmlspecialchars($row['no_hp']); ?></span>
                                            <?php if (!empty($row['update_no_hp'])): ?>
                                                <span class="badge-inline-update" title="WhatsApp telah diubah siswa">
                                                    <i class="fas fa-pencil-alt" style="color: #ea580c; width:auto;"></i> 
                                                    Diperbarui: <?php echo date('d/m/y H:i', strtotime($row['update_no_hp'])); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            
                            <td style="font-weight: 700; color: #0f172a;"><?php echo htmlspecialchars($row['kelas']); ?></td>
                            <td style="text-align: left; font-weight: 700; color: #1e40af;">
                                <?php echo $row['nama_lokasi'] ? htmlspecialchars($row['nama_lokasi']) : '<span style="color:#ef4444; font-weight:400; font-style:italic;">Lokasi Dihapus/Belum Pilih</span>'; ?>
                                <?php if ($row['nama_guru']): ?>
                                    <span style="display:block; font-size:11px; color:#64748b; font-weight:400; margin-top:2px;"><i class="fas fa-user-tie fa-fw" style="color:#94a3b8;"></i> Pem: <?php echo htmlspecialchars($row['nama_guru']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="dashboard-btn-group">
                                    <?php if ($user_level === 'admin' || (($user_level === 'pembimbing' || $user_level === 'guru') && $row['lokasi_guru_id'] == $guru_filter_id)): ?>
                                        <button type="button" class="btn-edit open-peserta-modal"
                                                data-id="<?php echo $row['id']; ?>"
                                                data-nisn="<?php echo htmlspecialchars($row['nisn']); ?>"
                                                data-nama="<?php echo htmlspecialchars($row['nama']); ?>"
                                                data-email="<?php echo htmlspecialchars($row['email']); ?>"
                                                data-nohp="<?php echo htmlspecialchars($row['no_hp']); ?>"
                                                data-kelas="<?php echo htmlspecialchars($row['kelas']); ?>"
                                                data-lokasi="<?php echo $row['lokasi_id']; ?>"
                                                data-periode="<?php echo $row['periode_id']; ?>">
                                            Ubah
                                        </button>
                                        <button type="button" class="btn-delete" onclick="konfirmasiHapus('<?php echo $row['id']; ?>', '<?php echo $_SESSION['csrf_token']; ?>')">Hapus</button>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size: 11px; font-style: italic; color: #94a3b8;"><i class="fas fa-lock me-1"></i>Locked</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div> 

<div class="mantap-modal" id="editPesertaModal">
    <div class="mantap-modal-dialog">
        <div class="mantap-modal-content">
            <div class="mantap-modal-header">
                <h2>Edit Data Peserta Didik</h2>
                <button type="button" class="close-modal-btn" id="closePesertaModalBtn">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="proses_update_peserta" value="1">
                <input type="hidden" name="modal_siswa_id" id="modal_siswa_id">

                <div class="modal-grid-landscape">
                    <div class="modal-form-group">
                        <label for="modal_nisn">NISN</label>
                        <input type="text" id="modal_nisn" name="modal_nisn" required>
                    </div>
                    <div class="modal-form-group">
                        <label for="modal_nama">Nama Lengkap</label>
                        <input type="text" id="modal_nama" name="modal_nama" required>
                    </div>
                    <div class="modal-form-group">
                        <label for="modal_email">Email</label>
                        <input type="email" id="modal_email" name="modal_email" required>
                    </div>
                    <div class="modal-form-group">
                        <label for="modal_no_hp">Nomor HP (WhatsApp)</label>
                        <input type="text" id="modal_no_hp" name="modal_no_hp" required placeholder="Cth: 0812xxxxxxxx">
                    </div>
                    <div class="modal-form-group">
                        <label for="modal_kelas">Kelas</label>
                        <select id="modal_kelas" name="modal_kelas" required>
                            <?php foreach ($daftar_kelas as $k_opt): ?>
                                <option value="<?php echo htmlspecialchars($k_opt); ?>"><?php echo htmlspecialchars($k_opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="modal-form-group">
                        <label for="modal_periode_id">Periode Gelombang PKL</label>
                        <select id="modal_periode_id" name="modal_periode_id" required>
                            <option value="">-- Pilih Periode --</option>
                            <?php foreach ($periode_options as $per): ?>
                                <option value="<?php echo $per['periode_id']; ?>"><?php echo htmlspecialchars($per['nama_periode']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="modal-form-group" style="margin-top: 10px;">
                    <label for="modal_lokasi_id">Lokasi Tempat PKL</label>
                    <select id="modal_lokasi_id" name="modal_lokasi_id" required>
                        <option value="">-- Pilih Lokasi --</option>
                        <?php foreach ($lokasi_options as $lok): 
                            $display_txt = htmlspecialchars($lok['nama_lokasi']) . " (Kuota: " . $lok['terisi'] . "/" . $lok['kuota_max'] . ")";
                        ?>
                            <option value="<?php echo $lok['lokasi_id']; ?>"><?php echo $display_txt; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="text-align: right;">
                    <button type="submit" class="modal-action-btn"><i class="fas fa-save me-1"></i> Simpan Perubahan Peserta</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($user_level === 'admin'): ?>
<!-- Modal Kenaikan Kelas Otomatis -->
<div class="mantap-modal" id="naikKelasModal">
    <div class="mantap-modal-dialog" style="max-width: 450px;">
        <div class="mantap-modal-content">
            <div class="mantap-modal-header">
                <h2><i class="fas fa-level-up-alt text-warning me-2"></i> Kenaikan Kelas Otomatis</h2>
                <button type="button" class="close-modal-btn" id="closeNaikKelasBtn">&times;</button>
            </div>
            <form method="POST" action="" id="formNaikKelas">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="proses_naik_kelas" value="1">
                
                <!-- Container untuk menampung input hidden ID siswa yang dipilih -->
                <div id="hiddenSiswaIdsContainer"></div>

                <div style="background: #eff6ff; border: 1px solid #bfdbfe; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
                    <i class="fas fa-info-circle fa-2x mb-2" style="color: #3b82f6;"></i>
                    <p style="margin: 0; font-size: 13.5px; color: #1e3a8a; line-height: 1.5;">
                        Anda memilih <strong id="countSelectedSiswa" style="font-size: 16px;">0</strong> siswa.<br><br>
                        Sistem akan <b>otomatis menaikkan kelas</b> siswa yang dipilih satu tingkat lebih tinggi.<br>
                        (Contoh: <span style="font-family:monospace; background:white; padding:2px 4px; border-radius:3px;">X DPIB 1</span> ➔ <span style="font-family:monospace; background:white; padding:2px 4px; border-radius:3px;">XI DPIB 1</span>)
                    </p>
                </div>

                <div style="text-align: right; margin-top:15px;">
                    <button type="submit" class="modal-action-btn" style="background-color: #f59e0b; width: 100%; padding: 12px; font-size:14px;">
                        <i class="fas fa-check-circle me-1"></i> Ya, Naikkan Kelas
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'panel/footer.php'; ?>

<script>
    // --- FITUR PENCARIAN & FILTER REAL-TIME ---
    function filterTable() {
        var input, filter, selectPeriode, filterPeriode, table, tr, tdDetail, tdPeriode, i, txtValueDetail, txtValuePeriode;
        
        // Ambil nilai dari input text pencarian nama
        input = document.getElementById("searchInput");
        filter = input.value.toUpperCase();
        
        // Ambil nilai dari dropdown filter periode
        selectPeriode = document.getElementById("filterPeriode");
        filterPeriode = selectPeriode.value.toUpperCase();

        table = document.getElementById("pesertaTable");
        tr = table.getElementsByTagName("tr");

        // Offset index jika ada kolom checkbox (untuk admin = 1, untuk pembimbing = 0)
        let is_admin = <?php echo ($user_level === 'admin') ? 'true' : 'false'; ?>;
        let colOffset = is_admin ? 1 : 0;

        // Loop mulai dari indeks 1 untuk melewati bagian THEAD
        for (i = 1; i < tr.length; i++) {
            tdPeriode = tr[i].getElementsByTagName("td")[1 + colOffset]; // Kolom PERIODE
            tdDetail = tr[i].getElementsByTagName("td")[3 + colOffset];  // Kolom DATA DETAIL SISWA
            
            if (tdDetail && tdPeriode) {
                // Ambil Teks Nama
                let nameElement = tdDetail.querySelector('.student-name-title'); 
                txtValueDetail = nameElement ? nameElement.textContent || nameElement.innerText : tdDetail.textContent || tdDetail.innerText;
                
                // Ambil Teks Periode
                let periodeElement = tdPeriode.querySelector('.table-periode');
                txtValuePeriode = periodeElement ? periodeElement.textContent || periodeElement.innerText : tdPeriode.textContent || tdPeriode.innerText;
                
                // Cek kecocokan
                let matchText = txtValueDetail.toUpperCase().indexOf(filter) > -1;
                let matchPeriode = (filterPeriode === "") || (txtValuePeriode.toUpperCase().indexOf(filterPeriode) > -1);

                // Tampilkan baris jika KEDUA kondisi terpenuhi
                if (matchText && matchPeriode) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                    // Hapus centangan jika disembunyikan filter agar aman
                    let cb = tr[i].querySelector('.check-siswa');
                    if(cb) cb.checked = false;
                }
            }
        }
        
        // Pastikan checkbox header "Check All" di-uncheck saat memfilter
        let checkAllBtn = document.getElementById('checkAllSiswa');
        if(checkAllBtn) checkAllBtn.checked = false;
    }

    // --- Fungsi SweetAlert2 untuk konfirmasi hapus ---
    function konfirmasiHapus(id, token) {
        Swal.fire({
            title: 'PERINGATAN MUTLAK!',
            text: "Yakin ingin menghapus data peserta didik ini? Kuota lokasi terisi akan otomatis dikembalikan.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `?action=delete&id=${id}&token=${token}`;
            }
        });
    }

    document.addEventListener("DOMContentLoaded", function() {
        const statusMessage = "<?php echo $message; ?>";
        if (statusMessage === "success_update") {
            Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Data peserta berhasil diperbarui!', confirmButtonColor: '#1e40af' });
        } else if (statusMessage === "success_naik_kelas") {
            Swal.fire({ icon: 'success', title: 'Berhasil!', text: 'Kelas siswa yang dipilih berhasil dinaikkan secara otomatis!', confirmButtonColor: '#1e40af' });
        } else if (statusMessage === "duplicate_nisn") {
            Swal.fire({ icon: 'error', title: 'Gagal Update!', text: 'NISN sudah digunakan oleh peserta lain.', confirmButtonColor: '#1e40af' });
        } else if (statusMessage === "error_update" || statusMessage === "error_delete" || statusMessage === "error_naik_kelas") {
            Swal.fire({ icon: 'error', title: 'Terjadi Kesalahan!', text: 'Proses gagal dieksekusi database.', confirmButtonColor: '#1e40af' });
        } else if (statusMessage === "denied_update" || statusMessage === "denied_delete") {
            Swal.fire({ icon: 'warning', title: 'Akses Ditolak!', text: 'Anda tidak memiliki hak akses atas tindakan ini atau data sudah diarsipkan.', confirmButtonColor: '#1e40af' });
        } else if (statusMessage === "success_delete") {
            Swal.fire({ icon: 'success', title: 'Terhapus!', text: 'Data peserta berhasil dihapus dari sistem.', confirmButtonColor: '#1e40af' });
        }

        // --- Logika Modal Edit Individual ---
        const modalEdit = document.getElementById('editPesertaModal');
        const closeBtnEdit = document.getElementById('closePesertaModalBtn');
        const triggersEdit = document.querySelectorAll('.open-peserta-modal');

        triggersEdit.forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('modal_siswa_id').value = this.getAttribute('data-id');
                document.getElementById('modal_nisn').value = this.getAttribute('data-nisn');
                document.getElementById('modal_nama').value = this.getAttribute('data-nama');
                document.getElementById('modal_email').value = this.getAttribute('data-email');
                document.getElementById('modal_no_hp').value = this.getAttribute('data-nohp');
                document.getElementById('modal_kelas').value = this.getAttribute('data-kelas');
                document.getElementById('modal_lokasi_id').value = this.getAttribute('data-lokasi');
                document.getElementById('modal_periode_id').value = this.getAttribute('data-periode');
                
                modalEdit.style.display = 'block';
            });
        });

        if (closeBtnEdit) {
            closeBtnEdit.addEventListener('click', () => { modalEdit.style.display = 'none'; });
        }
        
        window.addEventListener('click', (e) => { 
            if (e.target === modalEdit) { modalEdit.style.display = 'none'; }
        });

        <?php if ($user_level === 'admin'): ?>
        // --- Logika Checkbox Kenaikan Kelas Massal ---
        const checkAll = document.getElementById('checkAllSiswa');
        const checkSiswa = document.querySelectorAll('.check-siswa');
        
        if (checkAll) {
            checkAll.addEventListener('change', function() {
                checkSiswa.forEach(cb => {
                    // Hanya centang baris yang sedang terlihat (tidak ter-filter)
                    if (cb.closest('tr').style.display !== 'none') {
                        cb.checked = this.checked;
                    }
                });
            });
        }

        const btnNaikKelas = document.getElementById('btnNaikKelasModal');
        const modalNaikKelas = document.getElementById('naikKelasModal');
        const closeNaikKelasBtn = document.getElementById('closeNaikKelasBtn');

        if (btnNaikKelas) {
            btnNaikKelas.addEventListener('click', function() {
                let selectedIds = [];
                document.querySelectorAll('.check-siswa:checked').forEach(cb => {
                    selectedIds.push(cb.value);
                });

                if (selectedIds.length === 0) {
                    Swal.fire({ 
                        icon: 'warning', 
                        title: 'Perhatian', 
                        text: 'Pilih minimal 1 siswa terlebih dahulu (dengan mencentang kotak di tabel) untuk dinaikkan kelasnya.', 
                        confirmButtonColor: '#1e40af' 
                    });
                    return;
                }

                // Setup modal
                document.getElementById('countSelectedSiswa').textContent = selectedIds.length;
                let container = document.getElementById('hiddenSiswaIdsContainer');
                container.innerHTML = ''; // Bersihkan input tersembunyi sebelumnya
                
                selectedIds.forEach(id => {
                    let input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'siswa_ids[]';
                    input.value = id;
                    container.appendChild(input);
                });

                modalNaikKelas.style.display = 'block';
            });
        }

        if (closeNaikKelasBtn) {
            closeNaikKelasBtn.addEventListener('click', () => modalNaikKelas.style.display = 'none');
        }

        window.addEventListener('click', (e) => { 
            if (e.target === modalNaikKelas) { modalNaikKelas.style.display = 'none'; }
        });
        <?php endif; ?>

    });
</script>
</body>
</html>