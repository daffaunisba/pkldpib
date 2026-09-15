<?php
session_start();
include '../config/db-koneksi.php';
$siswa_id = $_SESSION['siswa_id'] ?? 1;

$query_target = "SELECT l.latitude, l.longitude, l.nama_lokasi 
                 FROM peserta_didik p 
                 JOIN lokasi_pkl l ON p.lokasi_id = l.lokasi_id 
                 WHERE p.id = $siswa_id";
$res_target = $koneksi->query($query_target)->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presensi Manual & GPS | Si Mantap</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --simantap-blue: #224ebe;
        }

        body {
            background-color: #f4f7f9;
            font-family: 'Poppins', sans-serif;
        }

        .absensi-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        #map {
            height: 200px;
            border-radius: 10px;
            margin-bottom: 15px;
            border: 2px solid #ddd;
        }

        #video {
            width: 100%;
            border-radius: 10px;
            background: #000;
            transform: scaleX(-1);
        }

        #photo-preview {
            width: 100%;
            border-radius: 10px;
            display: none;
            transform: scaleX(-1);
        }

        .btn-absen {
            background: linear-gradient(135deg, #4e73df 0%, #224ebe 100%);
            border: none;
            padding: 12px;
            font-weight: 700;
            border-radius: 10px;
        }
    </style>
</head>

<body>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card absensi-card p-4">
                    <h4 class="text-center fw-bold mb-3" style="color: var(--simantap-blue);">Presensi PKL</h4>

                    <div class="text-center mb-3">
                        <video id="video" autoplay playsinline></video>
                        <canvas id="canvas" style="display:none;"></canvas>
                        <img id="photo-preview" src="">

                        <button type="button" id="btn-capture" class="btn btn-sm btn-outline-primary mt-2">
                            <i class="fas fa-camera me-1"></i> Ambil Foto Wajah
                        </button>
                    </div>

                    <div id="map"></div>

                    <form action="proses-absen.php" method="POST" id="formAbsen">
                        <input type="hidden" name="lat" id="lat">
                        <input type="hidden" name="lng" id="lng">
                        <input type="hidden" name="image_data" id="image_data">

                        <button type="button" id="btn-submit" onclick="submitAbsen()" class="btn btn-primary w-100 btn-absen shadow" disabled>
                            Kirim Kehadiran
                        </button>
                    </form>

                    <div id="status-info" class="mt-3 text-center small fw-bold"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const targetLat = <?= $res_target['latitude'] ?>;
        const targetLng = <?= $res_target['longitude'] ?>;
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const photoPreview = document.getElementById('photo-preview');
        const btnCapture = document.getElementById('btn-capture');
        const btnSubmit = document.getElementById('btn-submit');
        const imageInput = document.getElementById('image_data');

        // 1. Inisialisasi Kamera
        navigator.mediaDevices.getUserMedia({
            video: true
        }).then(stream => {
            video.srcObject = stream;
        }).catch(err => alert("Izin kamera ditolak!"));

        // 2. Ambil Foto Manual
        btnCapture.addEventListener('click', () => {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);

            const dataUrl = canvas.toDataURL('image/jpeg');
            imageInput.value = dataUrl;

            video.style.display = 'none';
            photoPreview.src = dataUrl;
            photoPreview.style.display = 'block';
            btnCapture.innerHTML = "<i class='fas fa-sync'></i> Foto Ulang";

            // Cek lokasi setelah foto diambil
            getLocation();
        });

        // 3. Leaflet Map
        let map = L.map('map').setView([targetLat, targetLng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
        L.marker([targetLat, targetLng]).addTo(map).bindPopup("Lokasi Kantor");

        function getLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(pos => {
                    document.getElementById('lat').value = pos.coords.latitude;
                    document.getElementById('lng').value = pos.coords.longitude;

                    L.circle([pos.coords.latitude, pos.coords.longitude], {
                        radius: 20,
                        color: 'blue'
                    }).addTo(map);

                    if (imageInput.value !== "") {
                        btnSubmit.disabled = false;
                        document.getElementById('status-info').innerHTML = "<span class='text-success'>Siap Kirim!</span>";
                    }
                }, () => alert("Gagal dapet GPS"));
            }
        }

        function submitAbsen() {
            btnSubmit.innerHTML = "Mengirim...";
            btnSubmit.disabled = true;
            document.getElementById('formAbsen').submit();
        }
    </script>
</body>

</html>