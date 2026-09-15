<?php
// templates/header.php

// Variabel untuk data Periode, Lokasi, Kelas sudah diambil di index.php
// Pastikan variabel $siswa_list sudah didefinisikan jika ingin menggunakan JS updateStudentInfo
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pendaftaran PKL DPIB - SMK</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
    
    <header class="navbar">
        <div class="nav-brand">
            <a href="#">PKL DPIB | SMK ISLAM 1 BLITAR</a>
        </div>
        <nav class="main-nav">
            <a href="#home">Beranda</a>
            <a href="#about">Tentang PKL</a> 
            <a href="#status">Cek Kuota</a>
            
            <div class="dropdown">
                <a href="#" class="dropbtn">Info PKL ▼</a>
                <div class="dropdown-content">
                    <a href="asistensi-list.php">Asistensi</a>
                    <a href="izin.php">Perizinan</a>
                    <a href="#">Format Laporan</a>
                    <a href="#">Rekab Pengajuan PKL</a>
                </div>
            </div>
            <a href="#form-daftar" class="btn-nav daftar">Daftar Sekarang</a>
            <a href="admin/login.php" class="btn-nav admin">Admin Login</a>
        </nav>
    </header>

    <section id="home" class="hero-section">
        
        <div class="slide-item active" style="background-image: url('img/');">
            <div class="hero-content">
                <h1 class="hero-title-main">DPIB SMK ISLAM 1 BLITAR</h1>
                <h2 class="hero-subtitle">Terbaik, Terdepan, Luar Biasa</h2>
                <p>SMK ISLAM 1 BLITAR telah mendapatkan kepercayaan dari Direktorat SMK sebagai SMK Pusat Keunggulan, yang mencerminkan dedikasinya dalam memberikan pendidikan vokasi yang berkualitas dan relevan dengan kebutuhan dunia kerja.</p>
                <a href="#form-daftar" class="btn-primary-alt">DAFTAR SEKARANG</a>
            </div>
            <div class="hero-visual">
                <img src="img/slide1.jpg" alt="Siswa 1" class="slide-img img-1">
                <img src="img/slide2.jpg" alt="Siswa 2" class="slide-img img-2">
                <img src="img/slide3.jpg" alt="Siswa 3" class="slide-img img-3">
            </div>
        </div>

    </section>

    <div class="container">