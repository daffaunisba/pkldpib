<?php
session_start();
include '../config/db-koneksi.php';

if (!isset($_SESSION['siswa_id'])) {
    header("Location: login.php");
    exit();
}

$siswa_id = $_SESSION['siswa_id'];

// 1. AMBIL DATA SISWA UNTUK ID CARD (TERMASUK PERIODE)
$query = "SELECT p.nama, p.nisn, p.kelas, p.foto_profil, l.nama_lokasi, g.nama_guru, per.tgl_mulai, per.tgl_akhir
          FROM peserta_didik p
          LEFT JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id
          LEFT JOIN guru g ON l.guru_id = g.guru_id
          LEFT JOIN periode_pkl per ON p.periode_id = per.periode_id
          WHERE p.id = ?";

$stmt = $koneksi->prepare($query);
$stmt->bind_param("i", $siswa_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

// 2. VALIDASI FOTO PROFIL (WAJIB ADA UNTUK ID CARD)
$foto_db = $data['foto_profil'] ?? '';
$foto_path = "../assets/img/profile/" . $foto_db;

if (empty($foto_db) || !file_exists($foto_path)) {
    echo "<!DOCTYPE html>
    <html lang='id'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Peringatan ID Card</title>
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <link href='https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap' rel='stylesheet'>
        <style>body { font-family: 'Poppins', sans-serif; background: #f8fafc; }</style>
    </head>
    <body>
        <script>
            Swal.fire({
                title: 'Foto Profil Belum Ada!',
                text: 'Silakan perbarui biodata dan unggah foto profil wajah Anda terlebih dahulu untuk dapat mengunduh ID Card.',
                icon: 'warning',
                confirmButtonText: 'Kembali ke Biodata',
                confirmButtonColor: '#764ba2',
                allowOutsideClick: false
            }).then(() => {
                window.location.href = 'biodata.php';
            });
        </script>
    </body>
    </html>";
    exit();
}

// Format Tanggal Indonesia untuk ID Card
function formatTanggalIndo($tanggal) {
    if (empty($tanggal)) return '-';
    $bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];
    $pecah = explode('-', date('Y-m-d', strtotime($tanggal)));
    return $pecah[2] . ' ' . $bulan[(int)$pecah[1]] . ' ' . $pecah[0];
}

$tgl_mulai_format = formatTanggalIndo($data['tgl_mulai']);
$tgl_akhir_format = formatTanggalIndo($data['tgl_akhir']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID Card PKL - <?= htmlspecialchars($data['nama']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    
    <style>
        body { 
            font-family: 'Poppins', sans-serif; 
            background-color: #f1f5f9; 
            margin: 0; 
            padding: 30px 20px; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
        }
        
        .action-area { 
            margin-bottom: 35px; 
            text-align: center; 
            background: white;
            padding: 20px 30px;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            border: 1px solid #e2e8f0;
            max-width: 600px;
        }
        
        .action-area h2 { color: #1e293b; margin-top: 0; margin-bottom: 8px; font-weight: 800; }
        .action-area p { color: #64748b; font-size: 13.5px; margin-bottom: 20px; }
        
        .btn-group { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        
        .btn-download { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            border: none; 
            padding: 12px 20px; 
            border-radius: 8px; 
            font-weight: 600; 
            cursor: pointer; 
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.2); 
            transition: 0.2s;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-download:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(102, 126, 234, 0.3); }
        
        .btn-back {
            background: white;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 12px 20px; 
            border-radius: 8px; 
            font-weight: 600; 
            cursor: pointer;
            transition: 0.2s;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-back:hover { background: #f8fafc; color: #1e293b; }

        /* ID CARD CONTAINER - STANDAR PVC (CR80) */
        .id-card-wrapper { 
            display: flex; 
            gap: 30px; 
            flex-wrap: wrap; 
            justify-content: center; 
        }

        .card { 
            width: 340px; 
            height: 539px; 
            background: white; 
            border-radius: 20px; 
            position: relative; 
            overflow: hidden; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.12); 
            box-sizing: border-box;
        }

        /* --- FRONT CARD --- */
        .card-front { background: white; }
        .header-bg { 
            height: 195px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            position: relative; 
            clip-path: polygon(0 0, 100% 0, 100% 80%, 0% 100%); 
        }
        .header-content { 
            position: absolute; 
            top: 20px; 
            width: 100%; 
            text-align: center; 
            color: white; 
        }
        .school-logo { 
            width: 50px; 
            margin-bottom: 2px; 
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2)); 
        }
        
        .header-content h4 { 
            margin: 0; 
            font-size: 18px; 
            font-weight: 800; 
            letter-spacing: 1px; 
            line-height: 1.1; 
        }
        .header-content p { 
            margin: 0; 
            font-size: 12px; 
            font-weight: 600; 
            letter-spacing: 0.5px; 
            opacity: 0.95; 
            line-height: 1.1; 
        }

        .photo-container { 
            position: absolute; 
            top: 125px; 
            left: 50%; 
            transform: translateX(-50%); 
            z-index: 5; 
        }
        .student-photo { 
            width: 115px; 
            height: 115px; 
            border-radius: 50%; 
            border: 5px solid white; 
            object-fit: cover; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.15); 
            background: #eee; 
        }

        .identity-section { 
            margin-top: 55px; 
            text-align: center; 
            padding: 0 25px; 
        }
        .student-name { 
            font-size: 19px; 
            font-weight: 800; 
            color: #1e293b; 
            margin-bottom: 2px; 
            text-transform: uppercase; 
            line-height: 1.2; 
        }
        .student-class { 
            font-size: 12.5px; 
            font-weight: 700; 
            color: #667eea; 
            margin-bottom: 18px; 
        }

        .info-box { 
            background: #f8fafc; 
            border-radius: 12px; 
            padding: 10px 8px; 
            margin-bottom: 10px; 
            border: 1px solid #e2e8f0; 
        }
        .info-label { 
            font-size: 9px; 
            color: #94a3b8; 
            text-transform: uppercase; 
            font-weight: 700; 
            margin: 0 0 2px 0; 
        }
        .info-value { 
            font-size: 11.5px; 
            color: #334155; 
            font-weight: 700; 
            margin: 0; 
        }

        .footer-front { 
            position: absolute; 
            bottom: 20px; 
            width: 100%; 
            text-align: center; 
            font-size: 10px; 
            font-weight: 700; 
            color: #94a3b8; 
            letter-spacing: 1px; 
        }

        /* --- BACK CARD --- */
        .card-back { 
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%); 
            color: white; 
            padding: 30px 25px; 
            display: flex; 
            flex-direction: column; 
        }
        
        .card-back::before { 
            content: ''; 
            position: absolute; 
            top: 0; left: 0; right: 0; bottom: 0; 
            background-image: radial-gradient(rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 15px 15px;
            opacity: 0.5;
            pointer-events: none;
        }

        .back-title { 
            font-weight: 800; 
            font-size: 16px; 
            text-align: center; 
            border-bottom: 2px solid rgba(255,255,255,0.3); 
            padding-bottom: 12px; 
            margin-bottom: 15px; 
            position: relative; 
            z-index: 2;
        }
        .rules-list { 
            padding-left: 18px; 
            margin: 0; 
            position: relative; 
            z-index: 2;
        }
        .rules-list li { 
            font-size: 11.5px; 
            margin-bottom: 10px; 
            line-height: 1.4; 
            font-weight: 500; 
            text-align: justify;
        }
        
        .footer-sign { 
            margin-top: auto;
            margin-bottom: 25px; /* Memberikan ruang ekstra di bawah QR Code */
            text-align: center; 
            position: relative; 
            z-index: 2;
        }
        
        .qr-container { 
            display: flex; 
            justify-content: center; 
        }
        .qr-box { 
            background: white; 
            padding: 8px; 
            border-radius: 12px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.2); 
        }
    </style>
</head>
<body>

    <div class="action-area">
        <h2>ID Card Digital Peserta PKL</h2>
        <p>Kartu ini dirancang khusus dengan ukuran standar (CR80) siap cetak. Silakan unduh bagian depan dan belakang untuk dicetak pada kartu PVC.</p>
        
        <div class="btn-group">
            <button class="btn-download" onclick="downloadImage('cardFront', 'ID_Card_Depan_<?= $data['nisn'] ?>')">
                <i class="fas fa-download"></i> Unduh Depan (PNG)
            </button>
            <button class="btn-download" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);" onclick="downloadImage('cardBack', 'ID_Card_Belakang_<?= $data['nisn'] ?>')">
                <i class="fas fa-download"></i> Unduh Belakang (PNG)
            </button>
            <a href="biodata.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
    </div>

    <div class="id-card-wrapper">
        <div class="card card-front" id="cardFront">
            <div class="header-bg">
                <div class="header-content">
                    <img src="../img/logosekolah.png" class="school-logo" alt="Logo">
                    <h4>SI MANTAP PKL</h4>
                    <p>SMK ISLAM 1 BLITAR</p>
                </div>
            </div>
            
            <div class="photo-container">
                <img src="<?= $foto_path ?>" class="student-photo" alt="Foto Siswa">
            </div>

            <div class="identity-section">
                <div class="student-name"><?= htmlspecialchars($data['nama']) ?></div>
                <div class="student-class">
                    <?= htmlspecialchars($data['kelas']) ?> &bull; NISN: <?= htmlspecialchars($data['nisn']) ?>
                </div>
                
                <div class="info-box">
                    <p class="info-label">TEMPAT PENEMPATAN PKL</p>
                    <p class="info-value"><?= htmlspecialchars($data['nama_lokasi'] ?? 'BELUM DITENTUKAN') ?></p>
                </div>

                <div class="info-box">
                    <p class="info-label">GURU PEMBIMBING</p>
                    <p class="info-value"><?= htmlspecialchars($data['nama_guru'] ?? '-') ?></p>
                </div>
                
                <div class="info-box">
                    <p class="info-label">WAKTU PELAKSANAAN</p>
                    <p class="info-value"><?= $tgl_mulai_format ?> &ndash; <?= $tgl_akhir_format ?></p>
                </div>
            </div>

            <div class="footer-front">
                IDENTITAS DIGITAL SISWA
            </div>
        </div>

        <div class="card card-back" id="cardBack">
            <div class="back-title">TATA TERTIB PESERTA PKL</div>
            <ul class="rules-list">
                <li>Wajib mematuhi seluruh peraturan dan jam kerja yang ditetapkan oleh Mitra Industri/DUDI.</li>
                <li>Wajib mengisi Jurnal Kegiatan Harian dan melakukan Presensi kehadiran melalui aplikasi tepat waktu.</li>
                <li>Menjaga nama baik almamater sekolah selama berada di lingkungan industri.</li>
                <li>Berperilaku sopan, jujur, bertanggung jawab, dan disiplin dalam melaksanakan tugas.</li>
                <li>Dilarang meninggalkan lokasi PKL tanpa izin resmi dari supervisor lapangan.</li>
                <li>Apabila tidak masuk karena sakit/izin, wajib melampirkan surat keterangan sah di aplikasi.</li>
            </ul>

            <div class="footer-sign">
                <div class="qr-container">
                    <div id="qrcode" class="qr-box"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Generate QR Code Berdasarkan NISN
        new QRCode(document.getElementById("qrcode"), {
            text: "<?= htmlspecialchars($data['nisn']) ?>",
            width: 140,  
            height: 140,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.H 
        });

        function downloadImage(elementId, filename) {
            const element = document.getElementById(elementId);
            
            // Ubah text tombol saat memproses
            const btn = event.currentTarget;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            btn.style.pointerEvents = 'none';

            // html2canvas memotret div menjadi canvas gambar dengan resolusi (scale) 3x lipat
            html2canvas(element, { 
                scale: 3, 
                useCORS: true,
                backgroundColor: null
            }).then(canvas => {
                let link = document.createElement('a');
                link.download = filename + '.png';
                link.href = canvas.toDataURL('image/png', 1.0);
                link.click();
                
                // Kembalikan tombol
                btn.innerHTML = originalText;
                btn.style.pointerEvents = 'auto';
            }).catch(err => {
                console.error("Error generating image: ", err);
                alert("Terjadi kesalahan saat mengunduh gambar. Pastikan koneksi stabil.");
                btn.innerHTML = originalText;
                btn.style.pointerEvents = 'auto';
            });
        }
    </script>
</body>
</html>