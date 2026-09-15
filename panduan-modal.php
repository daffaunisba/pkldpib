<?php
// panduan_modal.php

// Konten Modal Panduan Pendaftaran
// Perhatikan bahwa ini hanyalah konten HTML, tidak perlu tag <html>, <body>, dll.
?>

<div class="modal fade" id="panduanModal" tabindex="-1" aria-labelledby="panduanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-biru text-white">
                <h5 class="modal-title" id="panduanModalLabel"><i class="fas fa-info-circle me-2"></i> Panduan Pendaftaran PKL DPIB</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h4 class="text-primary fw-bold">Selamat Datang Calon Peserta PKL!</h4>
                <p>Ikuti langkah-langkah berikut untuk melakukan pendaftaran Praktik Kerja Lapangan:</p>
                
                <ol>
                    <li>Cek Ketersediaan Kuota: Lihat bagian "Status Kuota Lokasi PKL" di bawah. Pastikan lokasi yang Anda inginkan masih memiliki kuota (status Tersedia).</li>
                    <li>Cek Kuota Periode: Pastikan periode (gelombang) yang Anda pilih masih memiliki kuota global. Jika kuota global penuh, pendaftaran tidak dapat dilanjutkan.</li>
                    <li>Siapkan Data Diri: Siapkan NISN, Nama Lengkap, Kelas, dan **Email aktif**.</li>
                    <li>Pilih Lokasi dan Periode: Gulir ke bawah ke bagian "Formulir Pendaftaran" dan isi semua kolom yang diperlukan, **termasuk Nomor HP (WhatsApp) yang aktif**.</li>
                    <li>Konfirmasi: Setelah menekan tombol "Daftar PKL Sekarang", Anda akan menerima notifikasi status pendaftaran. Pastikan data sudah benar.</li>
                </ol>
                
                <h5 class="text-danger fw-bold mt-4">Perhatian!</h5>
                <p>Pendaftaran hanya dapat dilakukan satu kali per NISN. Pastikan Anda sudah berdiskusi dengan orang tua/wali dan guru pembimbing sebelum memilih lokasi PKL.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Saya Mengerti</button>
            </div>
        </div>
    </div>
</div>