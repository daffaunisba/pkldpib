<?php
session_start();
include '../config/db-koneksi.php';

if (!isset($_SESSION['siswa_id'])) {
    header("Location: login.php");
    exit();
}

$siswa_id = $_SESSION['siswa_id'];

// Menangkap jenis absen dari URL (datang / pulang)
$jenis_absen = isset($_GET['jenis']) ? $_GET['jenis'] : 'datang';

// Setup teks dinamis berdasarkan jenis absen
if ($jenis_absen == 'pulang') {
    $judul_absen = "Presensi Pulang";
    $icon_absen = "fa-sign-out-alt";
    $btn_color = "linear-gradient(135deg, #f59e0b 0%, #d97706 100%)";
    $btn_shadow = "rgba(245, 158, 11, 0.4)";
} else {
    $judul_absen = "Presensi Datang";
    $icon_absen = "fa-sign-in-alt";
    $btn_color = "linear-gradient(135deg, #1cc88a 0%, #0d8a5a 100%)";
    $btn_shadow = "rgba(28, 200, 138, 0.4)";
}

// Ambil juga l.radius dari database
$query = "SELECT l.latitude, l.longitude, l.nama_lokasi, l.radius 
          FROM peserta_didik p 
          JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id 
          WHERE p.id = '$siswa_id'";
$res_target = $koneksi->query($query)->fetch_assoc();

// Jika radius tidak disetting atau null, beri nilai default
$radius_target = !empty($res_target['radius']) ? $res_target['radius'] : 50;
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $judul_absen ?> | Si Mantap</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        :root {
            --primary-grad: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #fafbfe;
            min-height: 100vh;
            padding: 2rem 1rem;
            color: #1e293b;
        }

        .bg-top-accent {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 300px;
            background: var(--primary-grad);
            z-index: -1;
            border-bottom-left-radius: 50px;
            border-bottom-right-radius: 50px;
        }

        .container-wrapper {
            max-width: 550px;
            margin: 0 auto;
            position: relative;
        }

        .btn-back {
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            font-weight: 600;
            margin-bottom: 1.5rem;
            transition: 0.3s;
        }
        .btn-back:hover {
            color: #e2e8f0;
            transform: translateX(-5px);
        }

        .header-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            text-align: center;
            animation: slideDown 0.6s ease-out;
            border: 1px solid #f1f5f9;
        }

        .header-card h4 {
            color: #1e293b;
            font-size: 1.6rem;
            margin-bottom: 0.5rem;
            font-weight: 800;
        }

        .header-card .badge-location {
            background: #f1f5f9;
            color: #64748b;
            padding: 6px 15px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 5px;
        }

        .status-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .status-item {
            background: white;
            border-radius: 15px;
            padding: 1rem;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 8px;
        }

        .status-item.active {
            background: #ecfdf5;
            border-color: #10b981;
            color: #059669;
        }

        .status-item.inactive {
            background: #fef2f2;
            border-color: #ef4444;
            color: #dc2626;
        }

        .status-item i { font-size: 1.5rem; }
        .status-item p { font-size: 0.8rem; font-weight: 700; margin: 0; letter-spacing: 0.5px; }

        #video-container {
            width: 100%;
            background: #000;
            border-radius: 20px;
            overflow: hidden;
            position: relative;
            margin-bottom: 1rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            aspect-ratio: 3/4;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 4px solid white;
        }

        video, #preview {
            width: 100%;
            height: 100%;
            transform: scaleX(-1);
            object-fit: cover;
            display: block;
        }

        #preview { display: none; }
        #canvas { display: none; }

        .btn-foto {
            background: var(--primary-grad);
            border: none;
            color: white;
            padding: 1rem;
            font-weight: 700;
            border-radius: 15px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            font-size: 1rem;
            cursor: pointer;
            width: 100%;
            margin-bottom: 1.5rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-foto:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6); color: white; }

        #map {
            height: 200px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            border: 4px solid white;
            margin-bottom: 1rem;
        }

        .map-info {
            background: white;
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            text-align: center;
            border: 1px solid #f1f5f9;
        }

        #info-jarak { font-size: 1rem; font-weight: 700; transition: all 0.3s ease; margin:0;}

        .form-submit {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid #f1f5f9;
            animation: slideUp 0.6s ease-out;
        }

        #btn-submit {
            background: <?= $btn_color ?>;
            border: none;
            color: white;
            padding: 1.1rem;
            font-weight: 800;
            border-radius: 15px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px <?= $btn_shadow ?>;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        #btn-submit:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px <?= $btn_shadow ?>;
            color: white;
        }

        #btn-submit:disabled {
            background: #cbd5e1;
            cursor: not-allowed;
            box-shadow: none;
            color: #94a3b8;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 480px) {
            .bg-top-accent { height: 260px; }
            .header-card h4 { font-size: 1.4rem; }
        }
    </style>
</head>

<body>
    <div class="bg-top-accent"></div>

    <div class="container-wrapper">
        <a href="index.php" class="btn-back">
            <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
        </a>

        <div class="header-card">
            <h4><i class="fas <?= $icon_absen ?> me-2"></i> <?= $judul_absen ?></h4>
            <div class="badge-location">
                <i class="fas fa-map-marker-alt text-danger me-1"></i> <?= htmlspecialchars($res_target['nama_lokasi'] ?? 'Tidak diketahui') ?> (Max: <?= $radius_target ?>m)
            </div>
        </div>

        <div class="status-grid">
            <div class="status-item inactive" id="status-kamera">
                <i class="fas fa-camera"></i>
                <p id="label-kamera">KAMERA: OFF</p>
            </div>
            <div class="status-item inactive" id="status-gps">
                <i class="fas fa-location-dot"></i>
                <p id="label-gps">GPS: MENCARI...</p>
            </div>
        </div>

        <div id="video-container">
            <video id="video" autoplay playsinline muted></video>
            <img id="preview" src="">
        </div>

        <button type="button" id="btn-foto" class="btn-foto">
            <i class="fas fa-camera me-1"></i> AMBIL FOTO WAJAH
        </button>

        <div id="map"></div>

        <div class="map-info">
            <p id="info-jarak" class="text-muted"><i class="fas fa-spinner fa-spin me-2"></i> Menghitung jarak lokasi...</p>
        </div>

        <div class="form-submit">
            <form action="proses-absen.php" method="POST" id="formAbsen">
                <input type="hidden" name="jenis" value="<?= htmlspecialchars($jenis_absen) ?>">
                <input type="hidden" name="lat" id="lat">
                <input type="hidden" name="lng" id="lng">
                <input type="hidden" name="image_data" id="image_data">
                <input type="hidden" name="client_time" id="client_time">

                <button type="submit" id="btn-submit" class="btn-submit w-100" disabled>
                    <i class="fas fa-paper-plane me-2"></i> KIRIM ABSENSI
                </button>
            </form>
        </div>
    </div>

    <canvas id="canvas"></canvas>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const video = document.getElementById('video');
        const preview = document.getElementById('preview');
        const btnFoto = document.getElementById('btn-foto');
        const btnSubmit = document.getElementById('btn-submit');
        const imageInput = document.getElementById('image_data');
        const infoJarak = document.getElementById('info-jarak');
        const formAbsen = document.getElementById('formAbsen');

        let userMarker;
        let userCoords = null;

        const targetLat = <?= $res_target['latitude'] ?? 0 ?>;
        const targetLng = <?= $res_target['longitude'] ?? 0 ?>;
        const targetRadius = <?= $radius_target ?>; 

        let map = L.map('map').setView([targetLat, targetLng], 17);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
        L.marker([targetLat, targetLng]).addTo(map).bindPopup("Titik Pusat Absen");

        L.circle([targetLat, targetLng], {
            color: '#667eea',
            fillColor: '#667eea',
            fillOpacity: 0.15,
            radius: targetRadius
        }).addTo(map);

        async function initCamera() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: "user" },
                    audio: false
                });
                video.srcObject = stream;
                
                document.getElementById('label-kamera').innerText = "KAMERA: SIAP";
                const statusEl = document.getElementById('status-kamera');
                statusEl.classList.remove('inactive');
                statusEl.classList.add('active');
            } catch (err) {
                console.error("Kamera Error:", err);
                document.getElementById('label-kamera').innerText = "KAMERA: ERROR";
                alert("Gagal mengakses kamera. Pastikan izin kamera telah diberikan.");
            }
        }

        btnFoto.addEventListener('click', function() {
            if (video.style.display === 'none') {
                video.style.display = 'block';
                preview.style.display = 'none';
                imageInput.value = "";
                this.innerHTML = '<i class="fas fa-camera me-1"></i> AMBIL FOTO WAJAH';
            } else {
                const canvas = document.getElementById('canvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                canvas.getContext('2d').drawImage(video, 0, 0);

                const dataUrl = canvas.toDataURL('image/jpeg');
                imageInput.value = dataUrl;

                video.style.display = 'none';
                preview.src = dataUrl;
                preview.style.display = 'block';
                this.innerHTML = '<i class="fas fa-sync-alt me-1"></i> FOTO ULANG';
            }
            cekValidasi();
        });

        function calculateDistance(lat1, lon1, lat2, lon2) {
            const R = 6371e3; 
            const p1 = lat1 * Math.PI / 180;
            const p2 = lat2 * Math.PI / 180;
            const dp = (lat2 - lat1) * Math.PI / 180;
            const dl = (lon2 - lon1) * Math.PI / 180;
            const a = Math.sin(dp / 2) * Math.sin(dp / 2) +
                Math.cos(p1) * Math.cos(p2) *
                Math.sin(dl / 2) * Math.sin(dl / 2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            return R * c;
        }

        function startTracking() {
            if (navigator.geolocation) {
                navigator.geolocation.watchPosition(pos => {
                    userCoords = {
                        lat: pos.coords.latitude,
                        lng: pos.coords.longitude
                    };

                    document.getElementById('lat').value = userCoords.lat;
                    document.getElementById('lng').value = userCoords.lng;

                    document.getElementById('label-gps').innerText = "GPS: SIAP";
                    const statusEl = document.getElementById('status-gps');
                    statusEl.classList.remove('inactive');
                    statusEl.classList.add('active');

                    if (userMarker) {
                        userMarker.setLatLng([userCoords.lat, userCoords.lng]);
                    } else {
                        userMarker = L.circle([userCoords.lat, userCoords.lng], {
                            radius: 4,
                            color: '#e74a3b',
                            fillColor: '#e74a3b',
                            fillOpacity: 1
                        }).addTo(map);
                        map.invalidateSize(); 
                        map.setView([userCoords.lat, userCoords.lng]);
                    }

                    cekValidasi();
                }, err => {
                    console.error("GPS Error:", err);
                    document.getElementById('label-gps').innerText = "GPS: DITOLAK/ERROR";
                }, {
                    enableHighAccuracy: true
                });
            } else {
                document.getElementById('label-gps').innerText = "GPS TIDAK MENDUKUNG";
            }
        }

        function cekValidasi() {
            if (!userCoords) return;

            const jarak = calculateDistance(userCoords.lat, userCoords.lng, targetLat, targetLng);
            const jarakMeter = Math.round(jarak);

            const now = new Date();
            const jam = String(now.getHours()).padStart(2, '0');
            const menit = String(now.getMinutes()).padStart(2, '0');
            const detik = String(now.getSeconds()).padStart(2, '0');
            document.getElementById('client_time').value = `${jam}:${menit}:${detik}`;

            if (jarakMeter <= targetRadius) {
                infoJarak.innerHTML = `<span class="text-success"><i class="fas fa-check-circle me-1"></i> Jarak sesuai: <b>${jarakMeter}m</b></span>`;
                if (imageInput.value !== "") {
                    btnSubmit.disabled = false;
                } else {
                    btnSubmit.disabled = true;
                }
            } else {
                infoJarak.innerHTML = `<span class="text-danger"><i class="fas fa-times-circle me-1"></i> Terlalu jauh: <b>${jarakMeter}m</b> (Maks ${targetRadius}m)</span>`;
                btnSubmit.disabled = true;
            }
        }

        // FIX: Hapus SweetAlert yang dobel
        formAbsen.addEventListener('submit', function(e) {
            // Ubah tombol jadi status loading agar tidak diklik dua kali
            btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> MEMPROSES...';
            btnSubmit.style.pointerEvents = 'none';
        });

        window.onload = () => {
            initCamera();
            startTracking();
        };
    </script>
</body>

</html>