<?php
// siswa/index.php
session_start();
include '../config/db-koneksi.php';

if (!isset($_SESSION['siswa_id'])) {
    header("Location: login.php");
    exit();
}

$siswa_id = $_SESSION['siswa_id'];
$siswa_id_session = $_SESSION['siswa_id']; // Dibutuhkan oleh header.php
$bulan_ini = date('m');
$tahun_ini = date('Y');
$tanggal_hari_ini = date('Y-m-d');

// --- FORMAT TANGGAL INDONESIA ---
$hari_array = array('Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu');
$bulan_array = array(1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember');
$nama_hari = $hari_array[date('w')];
$nama_bulan = $bulan_array[date('n')];
$tanggal_indo = $nama_hari . ', ' . date('j') . ' ' . $nama_bulan . ' ' . date('Y');

// --- 1. AMBIL DATA PRESENSI BULAN INI (HANYA UNTUK LEADERBOARD) ---
$query_all_presensi = "
    SELECT pr.siswa_id, pr.tanggal, pr.jam_masuk, l.jam_kerja, p.nama, p.kelas
    FROM presensi_pkl pr 
    JOIN peserta_didik p ON pr.siswa_id = p.id 
    JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id 
    WHERE MONTH(pr.tanggal) = '$bulan_ini' 
    AND YEAR(pr.tanggal) = '$tahun_ini'
";
$res_all = $koneksi->query($query_all_presensi);

$data_hadir_siswa = []; 
$nama_siswa_arr = [];
$kelas_siswa_arr = [];
$akumulasi_detik_masuk = []; 

if ($res_all && $res_all->num_rows > 0) {
    while ($row = $res_all->fetch_assoc()) {
        $s_id = $row['siswa_id'];
        $nama_siswa_arr[$s_id] = $row['nama'];
        $kelas_siswa_arr[$s_id] = $row['kelas'];
        
        if (!isset($data_hadir_siswa[$s_id])) {
            $data_hadir_siswa[$s_id] = 0;
            $akumulasi_detik_masuk[$s_id] = 0;
        }

        if (!empty($row['jam_masuk']) && !empty($row['jam_kerja'])) {
            $raw_target = trim(explode('s.d.', $row['jam_kerja'])[0]);
            $string_target = str_replace('.', ':', $raw_target);
            $string_masuk = str_replace('.', ':', trim($row['jam_masuk']));
            
            $time_target = strtotime($string_target);
            $time_masuk = strtotime($string_masuk);
            
            if ($time_target && $time_masuk) {
                $data_hadir_siswa[$s_id]++;
                $detik_absen = (date('H', $time_masuk) * 3600) + (date('i', $time_masuk) * 60) + date('s', $time_masuk);
                $akumulasi_detik_masuk[$s_id] += $detik_absen;
            }
        }
    }
}

// --- PROSES SORTING LEADERBOARD ---
uksort($data_hadir_siswa, function($a, $b) use ($data_hadir_siswa, $akumulasi_detik_masuk) {
    if ($data_hadir_siswa[$a] == $data_hadir_siswa[$b]) {
        return $akumulasi_detik_masuk[$a] <=> $akumulasi_detik_masuk[$b]; 
    }
    return $data_hadir_siswa[$b] <=> $data_hadir_siswa[$a]; 
});

$leaderboard_clean = [];
$peringkat = 0;
$rank_counter = 1;
$max_hadir_bulan_ini = (!empty($data_hadir_siswa)) ? max($data_hadir_siswa) : 25; 

foreach ($data_hadir_siswa as $id_mhs => $total_h) {
    if ($id_mhs == $siswa_id) $peringkat = $rank_counter;
    
    $nama_parts = explode(' ', trim($nama_siswa_arr[$id_mhs] ?? 'S'));
    $inisial = strtoupper(substr($nama_parts[0], 0, 2));

    $leaderboard_clean[] = [
        'siswa_id' => $id_mhs,
        'nama' => $nama_siswa_arr[$id_mhs] ?? 'Siswa SMEKISA',
        'inisial' => $inisial,
        'kelas' => $kelas_siswa_arr[$id_mhs] ?? '-',
        'total_hadir' => $total_h,
        'persen_bar' => ($max_hadir_bulan_ini > 0) ? round(($total_h / $max_hadir_bulan_ini) * 100) : 0
    ];
    $rank_counter++;
}

$top_5_leaderboard = array_slice($leaderboard_clean, 0, 5);
$podium_1 = $top_5_leaderboard[0] ?? null;
$podium_2 = $top_5_leaderboard[1] ?? null;
$podium_3 = $top_5_leaderboard[2] ?? null;
$normal_list = array_slice($top_5_leaderboard, 3, 2);

// --- 2. LOG ABSEN TERAKHIR ---
$res_recent = $koneksi->query("
    SELECT pr.*, l.jam_kerja FROM presensi_pkl pr 
    JOIN peserta_didik p ON pr.siswa_id = p.id JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
    WHERE pr.siswa_id = '$siswa_id' ORDER BY pr.tanggal DESC, pr.jam_masuk DESC LIMIT 3
");

// --- 3. CEK DATA PRESENSI HARI INI ---
$data_hari_ini = $koneksi->query("SELECT * FROM presensi_pkl WHERE siswa_id = '$siswa_id' AND DATE(tanggal) = '$tanggal_hari_ini'")->fetch_assoc();

$status_tombol = ($data_hari_ini) ? "PULANG" : "DATANG";
$jam_masuk_today = (!empty($data_hari_ini['jam_masuk'])) ? date('H:i', strtotime($data_hari_ini['jam_masuk'])) : '--:--';
$jam_pulang_today = (!empty($data_hari_ini['jam_pulang'])) ? date('H:i', strtotime($data_hari_ini['jam_pulang'])) : '--:--';

// --- 4. INFO GURU ---
$guru = $koneksi->query("SELECT g.nama_guru, g.no_hp FROM peserta_didik p JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id JOIN guru g ON l.guru_id = g.guru_id WHERE p.id = '$siswa_id'")->fetch_assoc();
$no_wa = $guru ? preg_replace('/[^0-9]/', '', $guru['no_hp']) : '';
if (strpos($no_wa, '0') === 0) { $no_wa = '62' . substr($no_wa, 1); }

// INCLUDE HEADER
include 'includes/header.php';
?>

<style>
    :root { 
        --primary-grad: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --card-radius: 25px;
        --item-radius: 20px;
    }
    
    .main-wrapper { padding: 1.5rem; width: 100%; overflow-x: hidden; background: #fafbfe; min-height: 100vh; }
    .dashboard-grid { display: grid; grid-template-columns: 1.4fr 1fr; gap: 1.5rem; width: 100%; }

    .floating-whatsapp { position: fixed; bottom: 30px; right: 30px; background: #25d366; color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 30px; box-shadow: 0 8px 25px rgba(37, 211, 102, 0.3); z-index: 9999; transition: all 0.3s ease; text-decoration: none; }
    .floating-whatsapp:hover { transform: scale(1.1) rotate(10deg); color: white; background: #1da851; }

    .greeting-card { background: var(--primary-grad); color: white; border-radius: var(--card-radius); padding: 2.5rem 2rem; box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2); width: 100%; margin-bottom: 1.5rem; position: relative; overflow: hidden; }
    .greeting-card::after { content: ''; position: absolute; width: 150px; height: 150px; background: rgba(255, 255, 255, 0.05); border-radius: 50%; right: -30px; top: -30px; }

    /* REALTIME CLOCK CARD */
    .realtime-card { background: white; border-radius: 15px; padding: 1rem 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: nowrap; margin-bottom: 1rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
    .realtime-date { font-weight: 700; color: #475569; font-size: 0.95rem; display: flex; align-items: center; gap: 8px; white-space: nowrap; }
    .realtime-time { font-weight: 800; color: #1e293b; font-size: 1.4rem; background: #f8fafc; padding: 4px 12px; border-radius: 10px; border: 1px solid #f1f5f9; letter-spacing: 1px; min-width: 120px; text-align: center; white-space: nowrap; }

    /* STATS ROW */
    .stats-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
    .stat-mini-card { background: white; padding: 1.25rem 1rem; border-radius: var(--item-radius); border: 1px solid #f1f5f9; text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.02); transition: 0.3s; }
    .stat-mini-card h3 { font-weight: 800; margin-bottom: 0; color: #1e293b; font-size: 1.8rem; }
    .stat-mini-card small { color: #94a3b8; font-weight: 800; text-transform: uppercase; font-size: 0.7rem; display: block; letter-spacing: 0.5px; margin-bottom: 4px; }

    .presensi-card { background: white; border-radius: var(--card-radius); padding: 2.5rem 2rem; text-align: center; box-shadow: 0 15px 40px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; }
    .icon-box { width: 75px; height: 75px; border-radius: 18px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; font-size: 2.2rem; }
    .icon-box-datang { background: rgba(102, 126, 234, 0.08); color: #667eea; }
    .icon-box-pulang { background: rgba(245, 158, 11, 0.08); color: #f59e0b; }

    .game-leaderboard-box { background: white; border-radius: var(--card-radius); padding: 1.8rem 1.2rem; border: 1px solid #f1f5f9; box-shadow: 0 15px 40px rgba(0,0,0,0.03); margin-bottom: 1.5rem; }
    
    .podium-3d-arena { display: none; align-items: flex-end; justify-content: center; height: 210px; margin-bottom: 25px; padding-bottom: 10px; border-bottom: 2px dashed #f1f5f9; gap: 8px; }
    @media (min-width: 481px) { .podium-3d-arena { display: flex; } .mobile-top3-list { display: none !important; } }

    .podium-column { flex: 1; display: flex; flex-direction: column; align-items: center; position: relative; }
    .podium-avatar { width: 46px; height: 46px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px; color: white; position: relative; margin-bottom: 8px; box-shadow: 0 8px 16px rgba(0,0,0,0.1); border: 2px solid white; z-index: 2; }
    .podium-name { font-size: 11px; font-weight: 800; color: #1e293b; text-align: center; width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 2px; padding: 0 4px; }
    .podium-score { font-size: 10px; font-weight: 800; color: #667eea; background: var(--purple-light); padding: 1px 7px; border-radius: 50px; margin-bottom: 6px; }
    .podium-step { width: 100%; border-radius: 12px 12px 6px 6px; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; padding-top: 10px; color: white; position: relative; box-shadow: inset 0 4px 10px rgba(255,255,255,0.2); }
    .podium-step-number { font-size: 1.8rem; font-weight: 900; opacity: 0.6; line-height: 1; }
    
    .p-rank-1 { height: 100px; background: linear-gradient(135deg, #fcd34d 0%, #b45309 100%); animation: flashGlow 2s infinite alternate; }
    .p-rank-1 .podium-avatar { background: #fef3c7; color: #b45309; border-color: #fcd34d; width: 54px; height: 54px; font-size: 15px; }
    .p-rank-1 .podium-score { background: #fef3c7; color: #b45309; }
    .crown-gold { position: absolute; top: -20px; font-size: 16px; color: #f59e0b; animation: floatY 1.5s infinite ease-in-out; z-index: 3; }

    .p-rank-2 { height: 75px; background: linear-gradient(135deg, #cbd5e1 0%, #475569 100%); }
    .p-rank-2 .podium-avatar { background: #e2e8f0; color: #475569; border-color: #cbd5e1; }
    .p-rank-3 { height: 55px; background: linear-gradient(135deg, #ffedd5 0%, #c2410c 100%); }
    .p-rank-3 .podium-avatar { background: #ffedd5; color: #c2410c; border-color: #f97316; }

    .game-list-row { position: relative; display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; margin-bottom: 10px; border-radius: var(--item-radius); background: #f8fafc; border: 1px solid #e2e8f0; overflow: hidden; z-index: 2; transition: 0.2s; }
    .game-list-row:hover { transform: translateX(3px); background: #f1f5f9; }
    .game-list-row:last-child { margin-bottom: 0; }
    .user-highlight-card { border: 2px solid #667eea !important; background: #f5f3ff !important; animation: userPulse 2.5s infinite; }
    .game-exp-fill { position: absolute; left: 0; top: 0; bottom: 0; background: rgba(102, 126, 234, 0.05); width: 0%; transition: width 1.2s cubic-bezier(0.4, 0, 0.2, 1); z-index: 1; }
    .user-highlight-card .game-exp-fill { background: rgba(118, 75, 162, 0.08); }
    .game-list-left { display: flex; align-items: center; gap: 12px; z-index: 2; position: relative; overflow: hidden; }
    .game-mini-badge { width: 28px; height: 28px; border-radius: 50%; background: #e2e8f0; color: #64748b; font-weight: 800; font-size: 11px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    
    .g-badge-1 { background: #fef3c7; color: #b45309; border: 1px solid #fcd34d; position: relative; }
    .g-badge-1::before { content: '👑'; position: absolute; top: -11px; font-size: 10px; }
    .g-badge-2 { background: #e2e8f0; color: #475569; }
    .g-badge-3 { background: #ffedd5; color: #c2410c; }
    .user-highlight-card .game-mini-badge:not(.g-badge-1):not(.g-badge-2):not(.g-badge-3) { background: var(--primary-grad); color: white; }
    .game-pts-pill { z-index: 2; background: #edf2f7; color: #4a5568; font-weight: 800; padding: 4px 12px; border-radius: 50px; font-size: 10px; flex-shrink: 0; }
    .user-highlight-card .game-pts-pill { background: var(--primary-grad); color: white; }

    @keyframes floatY { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-3px); } }
    @keyframes flashGlow { 0% { box-shadow: 0 0 5px rgba(245,158,11,0.2); } 100% { box-shadow: 0 0 15px rgba(245,158,11,0.5); } }
    @keyframes userPulse { 0% { box-shadow: 0 0 0px rgba(102,126,234,0); } 50% { box-shadow: 0 0 12px rgba(102,126,234,0.3); } 100% { box-shadow: 0 0 0px rgba(102,126,234,0); } }

    .recent-card { background: white; border-radius: var(--card-radius); padding: 1.8rem; border: 1px solid #f1f5f9; box-shadow: 0 15px 40px rgba(0,0,0,0.03); }
    .log-item { display: flex; align-items: center; padding: 1rem 0; border-bottom: 1px solid #f1f5f9; gap: 10px; }
    .log-item:last-child { border-bottom: none; padding-bottom: 0; }
    .log-date { width: 50px; height: 50px; background: #f8fafc; border-radius: 14px; display: flex; flex-direction: column; align-items: center; justify-content: center; margin-right: 5px; border: 1px solid #e2e8f0; flex-shrink: 0; }
    .log-date b { font-size: 1.1rem; line-height: 1; color: #1e293b; font-weight: 700; }
    .log-date small { font-size: 0.6rem; text-transform: uppercase; color: #94a3b8; font-weight: 700; }

    .btn-presensi { padding: 1rem 2rem; font-weight: 700; border-radius: 50px; text-decoration: none; display: inline-block; width: 100%; max-width: 300px; transition: 0.3s; border: none; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; box-sizing: border-box; }
    .btn-datang-style { background: var(--primary-grad); color: white; box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3); }
    .btn-datang-style:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4); color: white; }
    .btn-pulang-style { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; box-shadow: 0 6px 20px rgba(245, 158, 11, 0.3); }
    .btn-pulang-style:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(245, 158, 11, 0.4); color: white; }
    .btn-history-view { background: #f8fafc; color: #667eea; border: 1px solid #e2e8f0; font-weight: 700; border-radius: 15px; font-size: 0.8rem; padding: 0.75rem; transition: 0.3s; }
    .btn-history-view:hover { background: var(--purple-light); color: #764ba2; border-color: #cbd5e1; }
    
    .tip-box { border-radius: var(--item-radius); background: #fff5f5; color: #e53e3e; border: 1px solid #fed7d7; }
    .tip-box h6 { color: #c53030; font-weight: 800; }

    @media (max-width: 1100px) { .dashboard-grid { grid-template-columns: 1fr; gap: 1.5rem; } }
    @media (max-width: 992px) { 
        .floating-whatsapp { bottom: 90px; right: 20px; width: 50px; height: 50px; font-size: 24px; }
    }
    
    /* PENYESUAIAN KHUSUS LAYAR HP */
    @media (max-width: 480px) { 
        .game-leaderboard-box { padding: 1.25rem 0.75rem; }
        
        /* Modifikasi disini agar Tanggal dan Jam tetap 1 baris */
        .realtime-card { 
            padding: 0.8rem 1rem; /* Kurangi padding sedikit agar muat */
            gap: 5px; 
        }
        .realtime-date { 
            font-size: 0.75rem; /* Perkecil font tanggal */
        }
        .realtime-time { 
            font-size: 1.1rem; /* Perkecil font jam */
            min-width: 90px; 
            padding: 4px 8px; 
        }
    }
</style>

<div class="main-wrapper">

    <?php if ($guru && !empty($no_wa)): ?>
        <?php $pesan_wa = "Halo Pak/Bu " . $guru['nama_guru'] . ", saya " . $_SESSION['nama_siswa'] . " ingin bertanya terkait koordinasi program PKL..."; ?>
        <a href="https://wa.me/<?= $no_wa ?>?text=<?= urlencode($pesan_wa) ?>" class="floating-whatsapp" target="_blank"><i class="fab fa-whatsapp"></i></a>
    <?php endif; ?>

    <div class="dashboard-grid">
        
        <div class="left-col">
            <div class="greeting-card">
                <h3 class="fw-bold mb-1">Selamat Datang, <?= htmlspecialchars($_SESSION['nama_siswa']); ?>!</h3>
                <p class="opacity-75 mb-3 small">Disiplin dan konsistensi harian adalah kunci utama sukses Praktik Kerja Lapangan.</p>
                <div style="display:inline-block; padding: 8px 16px; background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.25); border-radius: 50px; font-weight: 800; font-size: 0.8rem; letter-spacing: 0.5px;">
                    <i class="fas fa-medal me-2"></i> Peringkat Kedisiplinan: #<?= $peringkat > 0 ? $peringkat : '-' ?> Bulan Ini
                </div>
            </div>

            <div class="realtime-card">
                <div class="realtime-date">
                    <i class="far fa-calendar-alt" style="color: #667eea; font-size: 1.2rem;"></i>
                    <?= $tanggal_indo; ?>
                </div>
                <div class="realtime-time" id="jam-realtime">
                    --:--:--
                </div>
            </div>

            <div class="stats-row">
                <div class="stat-mini-card" style="background: #f8fafc; border-color: #e2e8f0;">
                    <small style="color: #667eea;"><i class="fas fa-sign-in-alt me-1"></i> Jam Masuk</small>
                    <h3 style="color: #1e293b;"><?= $jam_masuk_today; ?> <span style="font-size: 0.8rem; font-weight: 600; color: #94a3b8;">WIB</span></h3>
                </div>
                <div class="stat-mini-card" style="background: #fffdf5; border-color: #fef3c7;">
                    <small style="color: #f59e0b;"><i class="fas fa-sign-out-alt me-1"></i> Jam Pulang</small>
                    <h3 style="color: #1e293b;"><?= $jam_pulang_today; ?> <span style="font-size: 0.8rem; font-weight: 600; color: #94a3b8;">WIB</span></h3>
                </div>
            </div>

            <div class="presensi-card">
                <?php if ($status_tombol === "DATANG"): ?>
                    <div class="icon-box icon-box-datang"><i class="fas fa-fingerprint"></i></div>
                    <h4 class="fw-bold mb-1" style="color: #1e293b;">Presensi Datang</h4>
                    <p class="text-muted small mb-4" style="max-width: 320px; margin: 0 auto 1.5rem;">Catat koordinat kehadiran Anda tepat waktu di gerbang lokasi mitra.</p>
                    <a href="absen-wajah.php?jenis=datang" class="btn-presensi btn-datang-style shadow-sm">Mulai Absen</a>
                <?php else: ?>
                    <div class="icon-box icon-box-pulang"><i class="fas fa-sign-out-alt"></i></div>
                    <h4 class="fw-bold mb-1" style="color: #1e293b;">Presensi Pulang</h4>
                    <p class="text-muted small mb-4" style="max-width: 320px; margin: 0 auto 1.5rem;">Pastikan ringkasan jurnal log harian Anda hari ini telah rampung.</p>
                    <a href="absen-wajah.php?jenis=pulang" class="btn-presensi btn-pulang-style shadow-sm">Selesaikan Tugas</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="right-col">
            
            <!-- <div class="game-leaderboard-box">
                <h5 class="fw-bold mb-4" style="color: #1e293b;"><i class="fas fa-trophy text-warning me-2"></i>Top 5 Disiplin</h5>
                
                <div class="podium-3d-arena">
                    <div class="podium-column">
                        <?php if($podium_2): ?>
                            <div class="podium-avatar"><?= $podium_2['inisial'] ?></div>
                            <div class="podium-name"><?= htmlspecialchars($podium_2['nama']) ?></div>
                            <div class="podium-score"><?= $podium_2['total_hadir'] ?> Hadir</div>
                            <div class="podium-step p-rank-2"><span class="podium-step-number">2</span></div>
                        <?php endif; ?>
                    </div>

                    <div class="podium-column">
                        <?php if($podium_1): ?>
                            <i class="fas fa-crown crown-gold"></i>
                            <div class="podium-avatar"><?= $podium_1['inisial'] ?></div>
                            <div class="podium-name" style="font-weight:900; color:#b45309;"><?= htmlspecialchars($podium_1['nama']) ?></div>
                            <div class="podium-score"><?= $podium_1['total_hadir'] ?> Hadir</div>
                            <div class="podium-step p-rank-1"><span class="podium-step-number">1</span></div>
                        <?php endif; ?>
                    </div>

                    <div class="podium-column">
                        <?php if($podium_3): ?>
                            <div class="podium-avatar"><?= $podium_3['inisial'] ?></div>
                            <div class="podium-name"><?= htmlspecialchars($podium_3['nama']) ?></div>
                            <div class="podium-score"><?= $podium_3['total_hadir'] ?> Hadir</div>
                            <div class="podium-step p-rank-3"><span class="podium-step-number">3</span></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mobile-top3-list d-flex flex-column gap-2 mb-2">
                    <?php 
                    $mobile_counter = 1;
                    foreach([$podium_1, $podium_2, $podium_3] as $p_row): 
                        if($p_row):
                            $isMe = ($p_row['siswa_id'] == $siswa_id);
                    ?>
                        <div class="game-list-row <?= $isMe ? 'user-highlight-card' : '' ?>">
                            <div class="game-exp-fill" data-width="<?= $p_row['persen_bar'] ?>%"></div>
                            <div class="game-list-left">
                                <div class="game-mini-badge g-badge-<?= $mobile_counter ?>"><?= $mobile_counter ?></div>
                                <div>
                                    <strong class="text-dark d-block" style="font-size: 12.5px; max-width:160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($p_row['nama']) ?></strong>
                                    <small class="text-muted d-block" style="font-size:10px; font-weight:600;"><?= htmlspecialchars($p_row['kelas']) ?> <?= $isMe ? '(Anda)' : '' ?></small>
                                </div>
                            </div>
                            <div class="game-pts-pill"><?= $p_row['total_hadir'] ?> Hadir</div>
                        </div>
                    <?php 
                        endif;
                        $mobile_counter++;
                    endforeach; ?>
                </div>

                <div class="game-list-container">
                    <?php if(!empty($normal_list)): ?>
                        <?php $current_r = 4; foreach($normal_list as $row): 
                            $isMe = ($row['siswa_id'] == $siswa_id);
                        ?>
                            <div class="game-list-row <?= $isMe ? 'user-highlight-card' : '' ?>">
                                <div class="game-exp-fill" data-width="<?= $row['persen_bar'] ?>%"></div>
                                <div class="game-list-left">
                                    <div class="game-mini-badge"><?= $current_r ?></div>
                                    <div>
                                        <strong class="text-dark d-block" style="font-size: 12.5px; max-width:160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($row['nama']) ?></strong>
                                        <small class="text-muted d-block" style="font-size:10px; font-weight:600;"><?= htmlspecialchars($row['kelas']) ?> <?= $isMe ? '(Anda)' : '' ?></small>
                                    </div>
                                </div>
                                <div class="game-pts-pill"><?= $row['total_hadir'] ?> Hadir</div>
                            </div>
                        <?php $current_r++; endforeach; ?>
                    <?php endif; ?>
                </div>
            </div> -->

            <div class="recent-card">
                <h5 class="fw-bold mb-3" style="color: #1e293b;"><i class="fas fa-history me-2" style="color: #667eea;"></i> Absen Terakhir</h5>
                <?php if ($res_recent && $res_recent->num_rows > 0): ?>
                    <?php while($log = $res_recent->fetch_assoc()): 
                        $is_log_telat = false;
                        if (!empty($log['jam_masuk']) && !empty($log['jam_kerja'])) {
                            $raw_t = trim(explode('s.d.', $log['jam_kerja'])[0]);
                            $time_t = strtotime(str_replace('.', ':', $raw_t));
                            $time_s = strtotime(str_replace('.', ':', trim($log['jam_masuk'])));
                            if ($time_t && $time_s && ($time_s - $time_t) > 0) { $is_log_telat = true; }
                        }
                    ?>
                        <div class="log-item">
                            <div class="log-date">
                                <b><?= date('d', strtotime($log['tanggal'])) ?></b>
                                <small><?= date('M', strtotime($log['tanggal'])) ?></small>
                            </div>
                            <div style="flex:1; min-width: 0;">
                                <div class="fw-bold text-truncate" style="font-size: 0.9rem; color:#1e293b;"><?= (!$is_log_telat) ? 'Hadir Tepat Waktu' : 'Terlambat Masuk' ?></div>
                                <div class="text-muted" style="font-size: 0.75rem; font-weight: 500;">Jam: <?= date('H:i', strtotime($log['jam_masuk'])) ?> WIB</div>
                            </div>
                            <div class="badge <?= (!$is_log_telat) ? 'bg-success' : 'bg-danger' ?> p-1 px-2" style="font-size: 0.6rem; font-weight:800; border-radius: 50px;">Verified</div>
                        </div>
                    <?php endwhile; ?>
                    <a href="riwayat-presensi.php" class="btn btn-history-view w-100 mt-3 fw-bold text-center d-block text-decoration-none">Lihat Semua Riwayat</a>
                <?php else: ?>
                    <p class="text-muted text-center py-4 small mb-0">Belum rekam log riwayat absensi pada bulan ini.</p>
                <?php endif; ?>
            </div>
            
            <div class="mt-4 p-3 tip-box shadow-sm">
                <h6 class="mb-1" style="font-size: 0.85rem;"><i class="fas fa-lightbulb me-2"></i>Tips Kedisiplinan Industri</h6>
                <p class="mb-0" style="font-size: 0.75rem; line-height:1.5; opacity: 0.95; font-weight: 500;">Biasakan standby di area lokasi PKL 15 menit sebelum jam operasional dimulai agar terhindar dari keterangan keterlambatan!</p>
            </div>
        </div>

    </div>
</div> 
<script>
    // FUNGSI JAM REALTIME
    function updateClock() {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        
        document.getElementById('jam-realtime').textContent = hours + ':' + minutes + ':' + seconds + ' WIB';
    }
    
    // Jalankan fungsi jam setiap detik
    setInterval(updateClock, 1000);
    updateClock(); // Panggil sekali di awal agar tidak delay 1 detik
    
    // Animasi Progress Bar Exp Leaderboard
    document.addEventListener("DOMContentLoaded", function() {
        setTimeout(() => {
            const elements = document.querySelectorAll('.game-exp-fill');
            elements.forEach(el => {
                el.style.width = el.getAttribute('data-width');
            });
        }, 200);
    });
</script>

<?php include 'includes/footer.php'; ?>