<?php
// admin/rekap-otomatis.php
// File ini HANYA boleh dipanggil oleh sistem Cron Job

include '../config/db-koneksi.php';

// Mencegah server timeout saat broadcast WhatsApp ke banyak guru
set_time_limit(0);
date_default_timezone_set('Asia/Jakarta');
$date_today = date('Y-m-d');

// Map Index Hari (Untuk Logika Rentang Hari Kerja)
$map_hari_idx = [
    'Senin' => 1, 'Selasa' => 2, 'Rabu' => 3, 'Kamis' => 4, 
    'Jumat' => 5, 'Sabtu' => 6, 'Minggu' => 7
];
$hari_ini_idx = (int)date('N', strtotime($date_today));

// --- CEK TANGGAL MERAH DARI TABEL HARI LIBUR ---
$q_libur = $koneksi->query("SELECT keterangan FROM hari_libur WHERE tanggal_libur = '$date_today'");
$ket_libur_nasional = ($q_libur && $q_libur->num_rows > 0) ? $q_libur->fetch_assoc()['keterangan'] : null;
$is_libur_nasional = !empty($ket_libur_nasional);

// --- AUTO-CREATE TABEL LOG WHATSAPP JIKA BELUM ADA ---
$koneksi->query("
    CREATE TABLE IF NOT EXISTS log_whatsapp (
        id INT AUTO_INCREMENT PRIMARY KEY,
        waktu DATETIME DEFAULT CURRENT_TIMESTAMP,
        target_nomor VARCHAR(100),
        jenis_pesan VARCHAR(100),
        pesan TEXT,
        status VARCHAR(50),
        response_api TEXT
    )
");

$token_fonnte = 'enmpN6YNngTwkYpWzzcf';
$log_output = ""; // Untuk menyimpan pesan log jika dijalankan manual

// ====================================================================================
// BAGIAN 1: KIRIM REKAP GLOBAL KE ADMIN & KAJUR (SEMUA SISWA)
// ====================================================================================

// 1. Ambil Data Rekapan Global
$q_rekap = $koneksi->query("
    SELECT 
        l.nama_lokasi, l.hari_mulai, l.hari_selesai,
        p.id as siswa_id, 
        p.nama as nama_siswa, 
        p.kelas,
        (SELECT COUNT(*) FROM presensi_pkl pr WHERE pr.siswa_id = p.id AND pr.tanggal = '$date_today') as is_hadir,
        (SELECT jenis_izin FROM pengajuan_izin i WHERE i.siswa_id = p.id AND i.status = 'Disetujui' AND '$date_today' BETWEEN i.tgl_mulai AND i.tgl_selesai LIMIT 1) as keterangan_izin
    FROM lokasi_pkl l
    JOIN peserta_didik p ON l.lokasi_id = p.lokasi_id
    ORDER BY l.nama_lokasi ASC, p.nama ASC
");

$rekap_data = [];
if ($q_rekap) {
    while ($r = $q_rekap->fetch_assoc()) {
        $lok = $r['nama_lokasi'];
        
        // Cek apakah lokasi tersebut sedang hari libur (Akhir Pekan / Libur Nasional)
        $h_mulai = $r['hari_mulai'] ?? 'Senin';
        $h_selesai = $r['hari_selesai'] ?? 'Jumat';
        $idx_mulai = $map_hari_idx[$h_mulai] ?? 1;
        $idx_selesai = $map_hari_idx[$h_selesai] ?? 5;
        
        $is_weekend = true;
        if ($idx_mulai <= $idx_selesai) {
            if ($hari_ini_idx >= $idx_mulai && $hari_ini_idx <= $idx_selesai) { $is_weekend = false; }
        } else {
            if ($hari_ini_idx >= $idx_mulai || $hari_ini_idx <= $idx_selesai) { $is_weekend = false; }
        }

        $is_libur_today = $is_weekend || $is_libur_nasional;
        
        // Menentukan Judul Teks Libur yang presisi
        if ($is_libur_nasional) {
            $teks_libur = "🌴 Libur Nasional: {$ket_libur_nasional}";
        } elseif ($is_weekend) {
            $teks_libur = "🌴 Libur Akhir Pekan";
        } else {
            $teks_libur = "";
        }

        if (!isset($rekap_data[$lok])) {
            $rekap_data[$lok] = [
                'total' => 0, 'hadir' => 0, 'izin' => 0, 'alpa' => 0, 'libur' => 0,
                'is_libur' => $is_libur_today, 'teks_libur' => $teks_libur, 'absen_list' => []
            ];
        }
        $rekap_data[$lok]['total']++;
        
        $is_hadir = $r['is_hadir'] > 0;
        $ket_izin = $r['keterangan_izin']; // Berisi 'Izin' atau 'Sakit' jika ada

        if ($is_hadir) {
            $rekap_data[$lok]['hadir']++;
        } elseif (!empty($ket_izin)) {
            $rekap_data[$lok]['izin']++;
            $rekap_data[$lok]['absen_list'][] = "- " . $r['nama_siswa'] . " (" . $r['kelas'] . ") : *" . strtoupper($ket_izin) . "*";
        } else {
            if ($is_libur_today) {
                // Jika libur, tidak dihitung Alpa, dihitung sebagai Libur aman
                $rekap_data[$lok]['libur']++;
            } else {
                // Jika hari kerja normal tapi tidak ada data, maka Alpa
                $rekap_data[$lok]['alpa']++;
                $rekap_data[$lok]['absen_list'][] = "- " . $r['nama_siswa'] . " (" . $r['kelas'] . ") : *ALPA (Tanpa Keterangan)*";
            }
        }
    }
}

// 2. Susun Pesan Global
$pesan_wa_global = "*REKAP ABSEN PKL TGL " . date('d/m/Y') . "*\n\n";
$no = 1;
foreach ($rekap_data as $lokasi => $data) {
    $pesan_wa_global .= "{$no}. {$lokasi}\n";
    
    if ($data['is_libur']) {
        $pesan_wa_global .= "   {$data['teks_libur']}\n";
        if ($data['hadir'] > 0 || $data['izin'] > 0) {
            $pesan_wa_global .= "   📊 Status PKL: Hadir ({$data['hadir']}), Izin ({$data['izin']}), Libur ({$data['libur']})\n";
            foreach ($data['absen_list'] as $alpa_str) { 
                $pesan_wa_global .= "   " . $alpa_str . "\n"; 
            }
        }
        $pesan_wa_global .= "\n";
    } else {
        if ($data['hadir'] == $data['total'] && $data['total'] > 0) {
            $pesan_wa_global .= "   ✅ (Hadir Semua)\n\n";
        } else {
            $pesan_wa_global .= "   ⚠️ (Hadir: {$data['hadir']}, Izin/Sakit: {$data['izin']}, Alpa: {$data['alpa']})\n";
            foreach ($data['absen_list'] as $alpa_str) { 
                $pesan_wa_global .= "   " . $alpa_str . "\n"; 
            }
            $pesan_wa_global .= "\n";
        }
    }
    $no++;
}

// 3. Kirim via Fonnte (Global)
$target_numbers_global = '083875337282,081333681279';

$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://api.fonnte.com/send',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => array(
        'target' => $target_numbers_global,
        'message' => rtrim($pesan_wa_global),
    ),
    CURLOPT_HTTPHEADER => array(
        "Authorization: $token_fonnte"
    ),
));

$response_global = curl_exec($curl);
$error_global = curl_error($curl);
curl_close($curl);

// Simpan Log Global
$status_kirim = ($error_global || strpos(strtolower($response_global), 'false') !== false) ? 'Gagal' : 'Terkirim';
$stmt_log = $koneksi->prepare("INSERT INTO log_whatsapp (target_nomor, jenis_pesan, pesan, status, response_api) VALUES (?, ?, ?, ?, ?)");
if ($stmt_log) {
    $jenis_pesan = "Rekap Harian Global";
    $pesan_simpan = rtrim($pesan_wa_global);
    $response_db = $error_global ? $error_global : $response_global;
    $stmt_log->bind_param("sssss", $target_numbers_global, $jenis_pesan, $pesan_simpan, $status_kirim, $response_db);
    $stmt_log->execute();
    $stmt_log->close();
}

$log_output .= "GLOBAL REPORT:\n" . ($error_global ? "Gagal: $error_global" : "Sukses: $response_global") . "\n\n";

// ====================================================================================
// BAGIAN 2: KIRIM REKAP SPESIFIK KE MASING-MASING GURU PEMBIMBING
// ====================================================================================

// 1. Ambil Data Siswa di-Group berdasarkan Guru
$q_guru = $koneksi->query("
    SELECT 
        g.guru_id, g.nama_guru, g.no_hp,
        l.nama_lokasi, l.hari_mulai, l.hari_selesai,
        p.id as siswa_id, 
        p.nama as nama_siswa, 
        p.kelas,
        (SELECT COUNT(*) FROM presensi_pkl pr WHERE pr.siswa_id = p.id AND pr.tanggal = '$date_today') as is_hadir,
        (SELECT jenis_izin FROM pengajuan_izin i WHERE i.siswa_id = p.id AND i.status = 'Disetujui' AND '$date_today' BETWEEN i.tgl_mulai AND i.tgl_selesai LIMIT 1) as keterangan_izin
    FROM guru g
    JOIN lokasi_pkl l ON g.guru_id = l.guru_id
    JOIN peserta_didik p ON l.lokasi_id = p.lokasi_id
    WHERE g.no_hp IS NOT NULL AND g.no_hp != ''
    ORDER BY g.nama_guru ASC, l.nama_lokasi ASC, p.nama ASC
");

$data_per_guru = [];
if ($q_guru) {
    while ($r = $q_guru->fetch_assoc()) {
        $g_id = $r['guru_id'];
        if (!isset($data_per_guru[$g_id])) {
            $data_per_guru[$g_id] = [
                'nama_guru' => $r['nama_guru'],
                'no_hp' => $r['no_hp'],
                'lokasi' => []
            ];
        }
        
        $lok = $r['nama_lokasi'];
        
        // Cek Libur
        $h_mulai = $r['hari_mulai'] ?? 'Senin';
        $h_selesai = $r['hari_selesai'] ?? 'Jumat';
        $idx_mulai = $map_hari_idx[$h_mulai] ?? 1;
        $idx_selesai = $map_hari_idx[$h_selesai] ?? 5;
        
        $is_weekend = true;
        if ($idx_mulai <= $idx_selesai) {
            if ($hari_ini_idx >= $idx_mulai && $hari_ini_idx <= $idx_selesai) { $is_weekend = false; }
        } else {
            if ($hari_ini_idx >= $idx_mulai || $hari_ini_idx <= $idx_selesai) { $is_weekend = false; }
        }

        $is_libur_today = $is_weekend || $is_libur_nasional;
        
        if ($is_libur_nasional) {
            $teks_libur = "🌴 *Libur Nasional: {$ket_libur_nasional}*";
        } elseif ($is_weekend) {
            $teks_libur = "🌴 *Libur Akhir Pekan*";
        } else {
            $teks_libur = "";
        }

        if (!isset($data_per_guru[$g_id]['lokasi'][$lok])) {
            $data_per_guru[$g_id]['lokasi'][$lok] = [
                'total' => 0, 'hadir' => 0, 'izin' => 0, 'alpa' => 0, 'libur' => 0,
                'is_libur' => $is_libur_today, 'teks_libur' => $teks_libur, 'absen_list' => []
            ];
        }
        
        $data_per_guru[$g_id]['lokasi'][$lok]['total']++;
        
        $is_hadir = $r['is_hadir'] > 0;
        $ket_izin = $r['keterangan_izin'];

        if ($is_hadir) {
            $data_per_guru[$g_id]['lokasi'][$lok]['hadir']++;
        } elseif (!empty($ket_izin)) {
            $data_per_guru[$g_id]['lokasi'][$lok]['izin']++;
            $data_per_guru[$g_id]['lokasi'][$lok]['absen_list'][] = "- " . $r['nama_siswa'] . " (" . $r['kelas'] . ") : *" . strtoupper($ket_izin) . "*";
        } else {
            if ($is_libur_today) {
                $data_per_guru[$g_id]['lokasi'][$lok]['libur']++;
            } else {
                $data_per_guru[$g_id]['lokasi'][$lok]['alpa']++;
                $data_per_guru[$g_id]['lokasi'][$lok]['absen_list'][] = "- " . $r['nama_siswa'] . " (" . $r['kelas'] . ") : *ALPA (Tanpa Keterangan)*";
            }
        }
    }
}

// 2. Loop & Kirim Pesan ke Masing-Masing Guru
foreach ($data_per_guru as $g_id => $dg) {
    // Format Nomor HP
    $no_wa = preg_replace('/[^0-9]/', '', $dg['no_hp']);
    if (strpos($no_wa, '0') === 0) {
        $no_wa = '62' . substr($no_wa, 1);
    }
    
    // Jika tidak ada nomor valid, skip guru ini
    if (empty($no_wa) || strlen($no_wa) < 9) continue;

    // Susun Pesan Khusus Pembimbing
    $pesan_guru = "Halo Bpk/Ibu *" . $dg['nama_guru'] . "*,\n\n";
    $pesan_guru .= "Berikut adalah rekap kehadiran siswa bimbingan Anda hari ini (" . date('d/m/Y') . "):\n\n";

    $no_lok = 1;
    $ada_absen = false;
    foreach ($dg['lokasi'] as $lokasi => $data) {
        $pesan_guru .= "{$no_lok}. *{$lokasi}*\n";
        
        if ($data['is_libur']) {
            $pesan_guru .= "{$data['teks_libur']}\n";
            if ($data['hadir'] > 0 || $data['izin'] > 0) {
                $pesan_guru .= "📊 Status PKL: Hadir ({$data['hadir']}) | Izin ({$data['izin']}) | Libur ({$data['libur']})\n";
                if (!empty($data['absen_list'])) {
                    $pesan_guru .= "📌 Keterangan Siswa:\n";
                    foreach ($data['absen_list'] as $alpa) { $pesan_guru .= $alpa . "\n"; }
                }
            } else {
                 $pesan_guru .= "✅ (Semua siswa diliburkan)\n";
            }
            $pesan_guru .= "\n";
        } else {
            if ($data['hadir'] == $data['total'] && $data['total'] > 0) {
                $pesan_guru .= "✅ Hadir Semua ({$data['total']} Siswa)\n\n";
            } else {
                $pesan_guru .= "⚠️ Hadir: {$data['hadir']} | Izin/Sakit: {$data['izin']} | Alpa: {$data['alpa']}\n";
                $pesan_guru .= "📌 *Daftar Siswa Tidak Hadir:*\n";
                foreach ($data['absen_list'] as $alpa) {
                    $pesan_guru .= $alpa . "\n";
                }
                $pesan_guru .= "\n";
                $ada_absen = true;
            }
        }
        $no_lok++;
    }
    
    if ($ada_absen) {
        $pesan_guru .= "_Mohon pantau siswa yang tidak hadir hari ini. Terima kasih._";
    } else {
        $pesan_guru .= "_Terima kasih telah memantau kedisiplinan siswa._";
    }
    
    // Eksekusi CURL Fonnte Khusus Guru
    $curl_g = curl_init();
    curl_setopt_array($curl_g, array(
        CURLOPT_URL => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array(
            'target' => $no_wa,
            'message' => $pesan_guru,
            'countryCode' => '62',
        ),
        CURLOPT_HTTPHEADER => array(
            "Authorization: $token_fonnte"
        ),
    ));

    $resp_guru = curl_exec($curl_g);
    $err_guru = curl_error($curl_g);
    curl_close($curl_g);
    
    // Simpan Log Individual Guru
    $status_kirim_g = ($err_guru || strpos(strtolower($resp_guru), 'false') !== false) ? 'Gagal' : 'Terkirim';
    $stmt_log_g = $koneksi->prepare("INSERT INTO log_whatsapp (target_nomor, jenis_pesan, pesan, status, response_api) VALUES (?, ?, ?, ?, ?)");
    if ($stmt_log_g) {
        $jenis_pesan_g = "Rekap Harian Pembimbing";
        $response_db_g = $err_guru ? $err_guru : $resp_guru;
        $stmt_log_g->bind_param("sssss", $no_wa, $jenis_pesan_g, $pesan_guru, $status_kirim_g, $response_db_g);
        $stmt_log_g->execute();
        $stmt_log_g->close();
    }
    
    $log_output .= "PEMBIMBING (" . $dg['nama_guru'] . "): " . ($err_guru ? "Gagal" : "Sukses") . "\n";
    
    // Beri jeda 15 detik antar pengiriman pesan ke pembimbing untuk mencegah pembatasan limit API
    sleep(15); 
}

// ------------------------------------------------------------------------------------
// Tampilkan Output Eksekusi (Jika dibuka dari Browser/Console)
// ------------------------------------------------------------------------------------
echo nl2br($log_output);
?>