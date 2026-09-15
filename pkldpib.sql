-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 27 Okt 2025 pada 11.17
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pkl_dpib`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `asistensi_pkl`
--

CREATE TABLE `asistensi_pkl` (
  `asistensi_id` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL,
  `tanggal_asistensi` date NOT NULL,
  `catatan` text DEFAULT NULL,
  `status` enum('Selesai','Revisi','Disetujui','Ditolak') DEFAULT 'Revisi'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `asistensi_pkl`
--

INSERT INTO `asistensi_pkl` (`asistensi_id`, `siswa_id`, `tanggal_asistensi`, `catatan`, `status`) VALUES
(10, 17, '2025-10-14', 'Sudah rajin mengisi buku jurnal harian dan presensi manual', 'Disetujui'),
(11, 12, '2025-10-14', 'asek', 'Revisi'),
(12, 17, '2025-10-28', 'Sudah rajin mengisi buku jurnal harian dan presensi manual', 'Disetujui'),
(13, 24, '2025-10-17', 'ssss', 'Ditolak');

-- --------------------------------------------------------

--
-- Struktur dari tabel `format_laporan`
--

CREATE TABLE `format_laporan` (
  `id` int(11) NOT NULL,
  `tipe` enum('proposal','pelaporan') NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `format_laporan`
--

INSERT INTO `format_laporan` (`id`, `tipe`, `deskripsi`, `file_path`, `created_at`) VALUES
(1, 'proposal', 'Silakan download proposal dengan menekan tombol yang ada dibawah, jangan lupa untuk memberi nama sesuai dengan ketentuan yang ada.', 'format_proposal_20251017021832.docx', '2025-10-17 00:16:03'),
(2, 'pelaporan', 'Silakan download contoh format pelaporan sidang PKL dengan menekan tombol yang ada dibawah ini, selalu konsultasi dengan pembimbing PKL.', 'format_pelaporan_20251017022015.docx', '2025-10-17 00:16:03');

-- --------------------------------------------------------

--
-- Struktur dari tabel `guru`
--

CREATE TABLE `guru` (
  `guru_id` int(11) NOT NULL,
  `nama_guru` varchar(100) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `guru`
--

INSERT INTO `guru` (`guru_id`, `nama_guru`, `user_id`, `email`) VALUES
(21, 'Mochamad Ade Satria, S.T.', 13, 'ade@gmail.com'),
(22, 'Laoren Septi Nur Azizah, S.Pd.', 14, 'laoren.septi50@guru.smk.belajar.id'),
(23, 'Lucky Prabawati, S.Pd., M.T.', 15, 'luckyprabawati@gmail.com');

-- --------------------------------------------------------

--
-- Struktur dari tabel `hari_kerja_def`
--

CREATE TABLE `hari_kerja_def` (
  `id` int(11) NOT NULL,
  `hari_nama` varchar(10) NOT NULL,
  `is_kerja` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `hari_kerja_def`
--

INSERT INTO `hari_kerja_def` (`id`, `hari_nama`, `is_kerja`) VALUES
(1, 'Monday', 1),
(2, 'Tuesday', 1),
(3, 'Wednesday', 1),
(4, 'Thursday', 1),
(5, 'Friday', 1),
(6, 'Saturday', 0),
(7, 'Sunday', 0);

-- --------------------------------------------------------

--
-- Struktur dari tabel `izin_pkl`
--

CREATE TABLE `izin_pkl` (
  `id_izin` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_akhir` date NOT NULL,
  `tanggal_kembali` date NOT NULL,
  `keperluan` varchar(50) NOT NULL,
  `tanggal_pengajuan` datetime NOT NULL,
  `alasan_izin` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `izin_pkl`
--

INSERT INTO `izin_pkl` (`id_izin`, `siswa_id`, `tanggal_mulai`, `tanggal_akhir`, `tanggal_kembali`, `keperluan`, `tanggal_pengajuan`, `alasan_izin`) VALUES
(31, 16, '2025-10-15', '2025-10-16', '2025-10-18', 'Sakit', '2025-10-15 11:37:52', ''),
(32, 24, '2025-10-17', '2025-10-18', '2025-10-20', 'Sakit', '2025-10-17 07:16:07', ''),
(33, 24, '2025-10-17', '2025-10-18', '2025-10-20', 'Sakit', '2025-10-17 07:16:56', ''),
(34, 24, '2025-10-17', '2025-10-18', '2025-10-20', 'Sakit', '2025-10-17 08:14:22', ''),
(35, 16, '2025-10-17', '2025-10-18', '2025-10-20', 'Sakit', '2025-10-17 08:14:39', '');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kartu_kendali`
--

CREATE TABLE `kartu_kendali` (
  `id` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL,
  `kegiatan_id` int(11) NOT NULL,
  `status` tinyint(1) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kartu_kendali`
--

INSERT INTO `kartu_kendali` (`id`, `siswa_id`, `kegiatan_id`, `status`, `updated_at`) VALUES
(3, 9, 1, 0, '2025-10-22 09:30:37'),
(4, 24, 1, 1, '2025-10-17 12:50:45'),
(5, 20, 1, 0, '2025-10-15 06:39:07'),
(6, 20, 2, 0, '2025-10-15 06:39:09'),
(7, 20, 4, 0, '2025-10-15 06:39:06'),
(8, 12, 1, 0, '2025-10-17 03:50:32'),
(9, 12, 2, 0, '2025-10-17 03:50:34'),
(10, 12, 4, 0, '2025-10-17 03:50:35'),
(11, 24, 2, 1, '2025-10-17 12:50:46'),
(12, 24, 4, 1, '2025-10-17 12:50:47'),
(13, 24, 5, 1, '2025-10-17 12:51:04'),
(14, 24, 6, 1, '2025-10-22 05:42:41'),
(15, 25, 6, 1, '2025-10-22 05:28:22'),
(16, 25, 5, 1, '2025-10-22 05:28:29'),
(17, 25, 4, 1, '2025-10-22 05:28:30'),
(18, 25, 2, 1, '2025-10-22 05:28:31'),
(19, 25, 1, 1, '2025-10-22 05:28:33'),
(20, 25, 7, 1, '2025-10-22 05:28:59'),
(21, 24, 7, 1, '2025-10-22 05:42:44'),
(22, 24, 8, 1, '2025-10-22 09:29:40'),
(23, 24, 9, 1, '2025-10-22 09:29:42'),
(24, 24, 10, 1, '2025-10-22 09:29:45'),
(25, 24, 11, 1, '2025-10-22 09:29:46'),
(26, 24, 12, 1, '2025-10-22 09:29:47'),
(27, 24, 13, 1, '2025-10-22 09:29:49'),
(28, 25, 8, 1, '2025-10-27 02:51:23'),
(29, 25, 9, 1, '2025-10-27 02:51:25');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kendali_kegiatan_def`
--

CREATE TABLE `kendali_kegiatan_def` (
  `id` int(11) NOT NULL,
  `urutan` int(11) NOT NULL,
  `deskripsi` varchar(500) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kendali_kegiatan_def`
--

INSERT INTO `kendali_kegiatan_def` (`id`, `urutan`, `deskripsi`, `is_active`, `created_at`) VALUES
(1, 1, 'Mengikuti Pembekalan PKL dari sekolah (materi, tata tertib, K3).', 1, '2025-10-14 08:18:16'),
(2, 2, 'Pengajuan Surat Pengantar Resmi dari sekolah ke instansi/perusahaan.', 1, '2025-10-15 06:38:14'),
(4, 3, 'Mendapatkan Surat Penerimaan/Konfirmasi dari tempat PKL.', 1, '2025-10-15 06:38:30'),
(5, 4, 'Melaksanakan PKL sesuai durasi waktu yang ditetapkan.', 1, '2025-10-17 12:50:36'),
(6, 5, 'Mengisi Jurnal Harian/Kartu Kendali dan meminta paraf Pembimbing Industri secara rutin.', 1, '2025-10-17 12:53:22'),
(7, 6, 'Menyusun draf Laporan Akhir PKL selama masa pelaksanaan PKL.', 1, '2025-10-22 05:28:47'),
(8, 7, 'Melakukan Asistensi dengan Guru Pembimbing Sekolah minimal 4x (saat PKL).', 1, '2025-10-22 09:28:33'),
(9, 8, 'Tempat PKL (Pembimbing Industri) memberikan Nilai Akhir kepada sekolah.', 1, '2025-10-22 09:28:42'),
(10, 9, 'Mengumpulkan Jurnal Harian dan seluruh dokumentasi kepada pihak sekolah.', 1, '2025-10-22 09:28:51'),
(11, 10, 'Menyelesaikan Revisi Laporan dan disetujui oleh Guru Pembimbing.', 1, '2025-10-22 09:29:00'),
(12, 11, 'Mengikuti Ujian/Sidang Laporan PKL (presentasi dan tanya jawab).', 1, '2025-10-22 09:29:08'),
(13, 12, 'Menerima Sertifikat/Surat Keterangan Selesai PKL dari perusahaan.', 1, '2025-10-22 09:29:16');

-- --------------------------------------------------------

--
-- Struktur dari tabel `klasifikasi_surat`
--

CREATE TABLE `klasifikasi_surat` (
  `id` int(11) NOT NULL,
  `kode` varchar(10) NOT NULL,
  `nama_klasifikasi` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `klasifikasi_surat`
--

INSERT INTO `klasifikasi_surat` (`id`, `kode`, `nama_klasifikasi`, `is_active`) VALUES
(1, '421.1', 'PROGRAM PKL (Permohonan/Balasan)', 1),
(2, '421.2', 'NILAI / SERTIFIKAT KELULUSAN PKL', 1),
(3, '421.5', 'DISPENSASI / IZIN SISWA', 1),
(4, '800', 'KEPEGAWAIAN/SDM (Surat Tugas Guru/Pegawai)', 1),
(5, '070', 'UNDANGAN (Rapat, Wali Murid, Dsb.)', 1),
(6, '600', 'KEUANGAN', 1),
(7, 'PILIH', '-- Pilih Klasifikasi --', 0),
(9, '354', 'MoU', 1);

-- --------------------------------------------------------

--
-- Struktur dari tabel `lokasi_pkl`
--

CREATE TABLE `lokasi_pkl` (
  `lokasi_id` int(11) NOT NULL,
  `alamat` varchar(255) DEFAULT NULL,
  `guru_id` int(11) DEFAULT NULL,
  `nama_lokasi` varchar(100) NOT NULL,
  `jam_kerja` varchar(100) DEFAULT NULL,
  `kuota_max` int(11) NOT NULL DEFAULT 5
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `lokasi_pkl`
--

INSERT INTO `lokasi_pkl` (`lokasi_id`, `alamat`, `guru_id`, `nama_lokasi`, `jam_kerja`, `kuota_max`) VALUES
(1, 'Blitar', 23, 'CV. GRAHA ADI KARYA', 'Pagi - Sore (08.00 s.d. 16.00 WIB)', 5),
(2, 'Jl.  Bengawan Solo, RT 2 RW 7 Kelurahan Sukosewo Kecamatan Ngasem Kabupaten Malang Jawa Timur, 66117', 22, 'CV. TERATE MANUNGGAL', 'Pagi - Sore (09.00 s.d. 16.00 WIB)', 5),
(3, 'Lowokwaru', 22, 'CV. PUTRA INDAH', 'Pagi - Sore (09.00 s.d. 16.00 WIB)', 3),
(4, 'BTN Asabri O.25 RT.3/RW.14 Gedog Kota Blitar', 21, 'BIMASENA STUDIO', 'Pagi - Sore (07.00 s.d. 16.00 WIB)', 4),
(5, 'Blitar', 22, 'CV. CREATIVE DESIGN STUDIO', 'Pagi - Sore (08.30 s.d. 16.00 WIB)', 2),
(6, 'Jl. Sungai  Hilir Timur No. 1 Blitar', 21, 'Dopota Studio', 'Pagi - Sore (08.00 s.d. 16.00 WIB)', 2),
(7, 'Jl. Mawar RT 002 RW 005 Ds. Jingglong Sutojayan', 23, 'Brawijaya Desain', 'Pagi - Sore (08.00 s.d. 16.00 WIB)', 2),
(8, 'Lingk. Dadapan RT 3 RW 2 Sumberdiren awaw', 21, 'Adana Makmur', 'Pagi - Sore (08.00 s.d. 15.00 WIB)', 3),
(9, 'RT. 3 RW. 2 Kauman Kec. Srengat Kab.Blitar', 23, 'Indana Puta', 'Pagi - Sore (08.00 s.d. 16.00 WIB)', 2),
(10, 'Jl. Mawar RT 002 RW 005 Ds. Jingglong Sutojayan', NULL, 'Sky view', 'Pagi - Sore (08.00 s.d. 16.00 WIB)', 2),
(11, 'BTN Asabri O.25 RT.3/RW.14 Gedog Kota Blitar', 23, 'PT. Wisma', 'Pagi - Sore (08.00 s.d. 16.00 WIB)', 5),
(12, 'Jl. Taman Industri Bukit Semarang Baru No.Kav. 8 Blok D.1, Mijen, Kec. Mijen, Kota Semarang, Jawa Tengah 50219', 23, 'Nawasena Studio', 'Pagi - Sore (08.00 s.d. 16.00 WIB)', 5),
(13, 'Jakarta', 21, 'Anugerah Indah', 'Pagi - Sore (06.00 s.d. 11.00 WIB)', 3),
(14, 'Jl. Musi No. 6 Blitar', 21, 'DAFF STUDIO', 'Pagi - Sore (08.00 s.d. 15.00 WIB)', 2);

-- --------------------------------------------------------

--
-- Struktur dari tabel `lokasi_presensi`
--

CREATE TABLE `lokasi_presensi` (
  `lokasi_id` int(11) NOT NULL,
  `target_latitude` decimal(10,8) NOT NULL,
  `target_longitude` decimal(11,8) NOT NULL,
  `radius_tolerance_m` int(11) NOT NULL DEFAULT 100
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `monitoring_kunjungan`
--

CREATE TABLE `monitoring_kunjungan` (
  `kunjungan_id` int(11) NOT NULL,
  `guru_id` int(11) NOT NULL,
  `lokasi_id` int(11) NOT NULL,
  `tanggal_kunjungan` date NOT NULL,
  `catatan_guru` text DEFAULT NULL,
  `kritik_saran_hrd` text NOT NULL,
  `bukti_foto` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `monitoring_kunjungan`
--

INSERT INTO `monitoring_kunjungan` (`kunjungan_id`, `guru_id`, `lokasi_id`, `tanggal_kunjungan`, `catatan_guru`, `kritik_saran_hrd`, `bukti_foto`, `created_at`) VALUES
(5, 22, 12, '2025-10-14', 'Siswa an. Resa Mitra sering bolos', '', 'mon_12_1760403568.jpeg', '2025-10-14 07:59:28'),
(6, 22, 12, '2025-10-14', 'aw', '', 'mon_12_1760404164.png', '2025-10-14 08:09:24'),
(7, 22, 12, '2025-10-14', 'aaa', 'aw', 'mon_12_1760404962.webp', '2025-10-14 08:22:42');

-- --------------------------------------------------------

--
-- Struktur dari tabel `nilai_siswa_pkl`
--

CREATE TABLE `nilai_siswa_pkl` (
  `nilai_id` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL,
  `komponen_id` int(11) NOT NULL,
  `nilai_angka` decimal(5,2) DEFAULT NULL,
  `predikat` enum('Sangat Baik','Baik','Cukup','Kurang') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `nilai_siswa_pkl`
--

INSERT INTO `nilai_siswa_pkl` (`nilai_id`, `siswa_id`, `komponen_id`, `nilai_angka`, `predikat`) VALUES
(1, 24, 23, 91.00, 'Sangat Baik'),
(2, 24, 24, 85.00, 'Baik'),
(3, 24, 25, 91.00, 'Sangat Baik'),
(28, 24, 26, 87.00, 'Baik'),
(33, 24, 28, 95.00, 'Sangat Baik'),
(44, 24, 29, 96.00, 'Sangat Baik'),
(45, 24, 30, 85.00, 'Baik'),
(128, 24, 31, 85.00, 'Baik'),
(239, 24, 32, 91.00, 'Sangat Baik');

-- --------------------------------------------------------

--
-- Struktur dari tabel `penilaian_aspek`
--

CREATE TABLE `penilaian_aspek` (
  `aspek_id` int(11) NOT NULL,
  `urutan` int(11) NOT NULL,
  `nama_aspek` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `penilaian_aspek`
--

INSERT INTO `penilaian_aspek` (`aspek_id`, `urutan`, `nama_aspek`) VALUES
(1, 1, 'Aspek Sikap'),
(2, 2, 'Aspek Keterampilan'),
(3, 3, 'Aspek Keterampilan Khusus');

-- --------------------------------------------------------

--
-- Struktur dari tabel `penilaian_komponen`
--

CREATE TABLE `penilaian_komponen` (
  `komponen_id` int(11) NOT NULL,
  `aspek_id` int(11) NOT NULL,
  `urutan` int(11) NOT NULL,
  `nama_komponen` varchar(255) NOT NULL,
  `bobot` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `penilaian_komponen`
--

INSERT INTO `penilaian_komponen` (`komponen_id`, `aspek_id`, `urutan`, `nama_komponen`, `bobot`) VALUES
(23, 1, 1, 'Disiplin', 15),
(24, 1, 2, 'Etika', 20),
(25, 1, 3, 'Inisiatif', 15),
(26, 1, 4, 'Kepekaan', 0),
(28, 2, 1, 'Mengerjakan laporan perusahaan', 0),
(29, 3, 1, 'Pelatihan Batu Baata', 0),
(30, 3, 2, 'Sebagai Mandor pada rumah Bu Anass aw', 0),
(31, 2, 2, 'Bisa Gambar Teknik', 0),
(32, 1, 5, 'Kreatif', 0);

-- --------------------------------------------------------

--
-- Struktur dari tabel `periode_pkl`
--

CREATE TABLE `periode_pkl` (
  `periode_id` int(11) NOT NULL,
  `nama_periode` varchar(100) NOT NULL,
  `tgl_mulai` date NOT NULL,
  `tgl_akhir` date NOT NULL,
  `total_jam` int(11) DEFAULT 872
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `periode_pkl`
--

INSERT INTO `periode_pkl` (`periode_id`, `nama_periode`, `tgl_mulai`, `tgl_akhir`, `total_jam`) VALUES
(1, 'Gelombang 1', '2025-01-20', '2025-07-20', 872),
(2, 'Gelombang 2', '2025-07-01', '2025-11-03', 872),
(7, 'Gelombang 3', '2025-10-17', '2025-12-17', 872),
(9, 'Gelombang 4', '2026-01-01', '2026-12-31', 872);

-- --------------------------------------------------------

--
-- Struktur dari tabel `peserta_didik`
--

CREATE TABLE `peserta_didik` (
  `id` int(11) NOT NULL,
  `nisn` varchar(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `kelas` varchar(10) NOT NULL,
  `alamat_siswa` text DEFAULT NULL,
  `lokasi_id` int(11) DEFAULT NULL,
  `periode_id` int(11) DEFAULT NULL,
  `tgl_daftar` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `peserta_didik`
--

INSERT INTO `peserta_didik` (`id`, `nisn`, `nama`, `email`, `kelas`, `alamat_siswa`, `lokasi_id`, `periode_id`, `tgl_daftar`) VALUES
(5, '0069', 'Alfredo Rizki Saputra', 'anas@gmail.com', 'XII DPIB 3', NULL, 4, 2, '2025-10-10 07:11:14'),
(7, '012', 'Amila Nazwa Saputri', 'laoren.septi50@guru.smk.belajar.id', 'XI DPIB 2', NULL, 5, 7, '2025-10-10 08:43:53'),
(9, '089', 'Amru Abdillah', 'laoren.septi50@guru.smk.belajar.id', 'XII DPIB 1', NULL, 6, 2, '2025-10-10 08:46:42'),
(10, '056', 'Siti Nur Indana', 'siti@gmail.com', 'XI DPIB 3', NULL, 6, 2, '2025-10-10 08:47:21'),
(11, '009', 'Bayu Akbar', 'bay@gmail.com', 'X DPIB 1', NULL, 3, NULL, '2025-10-10 09:03:02'),
(12, '002563', 'Resa Mitra', 'rmh@gmail.com', 'XII DPIB 2', NULL, 12, NULL, '2025-10-13 03:17:23'),
(13, '025586264', 'Bryan Mavendra', 'by@gmail.com', 'XII DPIB 2', NULL, 8, NULL, '2025-10-13 05:07:59'),
(14, '1125352', 'Dhimas Fajar', 'dmsfjr@gmail.com', 'XI DPIB 1', NULL, 2, 1, '2025-10-13 05:20:19'),
(15, '5656134', 'Marvelano Lutfi', 'marvel@gmail.com', 'XI DPIB 3', NULL, 2, 7, '2025-10-13 05:21:28'),
(16, '155541', 'Budi Luhur', 'budi@gmail.com', 'XII DPIB 2', NULL, 7, NULL, '2025-10-13 06:23:17'),
(17, '69', 'Faish Hadi Wijaya', 'pais@gmail.com', 'XI DPIB 2', NULL, 12, NULL, '2025-10-13 07:19:57'),
(18, '0085', 'Andin Maesaroh', 'andini@gmail.com', 'X DPIB 1', NULL, 8, NULL, '2025-10-13 07:40:04'),
(19, '00696969', 'Daffa Dareline Rafinata', 'daffa@gmail.com', 'X DPIB 1', NULL, 4, NULL, '2025-10-13 08:40:01'),
(20, '05222222', 'Arik Prasetyo', 'arik@gmail.com', 'XI DPIB 3', NULL, 7, NULL, '2025-10-13 10:11:33'),
(21, '000000', 'Ida Nurfarida', 'ida@gmail.com', 'XII DPIB 2', NULL, 9, NULL, '2025-10-13 10:15:43'),
(22, '44', 'kepooo', 'aw@gmail.com', 'X DPIB 1', NULL, 3, 1, '2025-10-14 02:02:00'),
(23, '55', 'Dafafafa Rafafifi', 'dadap@gmail.com', 'X DPIB 1', NULL, 4, 1, '2025-10-15 03:09:47'),
(24, '08888', 'Akbar Sadewa', 'akbr@gmail.com', 'XII DPIB 3', NULL, 12, 2, '2025-10-15 06:32:03'),
(25, '202221213', 'Alfinanda Putra', 'alpin@gmail.com', 'XII DPIB 2', NULL, 12, 1, '2025-10-17 02:08:36'),
(26, '77', 'Riko nakal', 'aw@gmail.com', 'XI DPIB 3', NULL, 4, 1, '2025-10-24 10:20:15'),
(29, '083875337282', 'Alkisah', 'kisah@smk.id', 'XII DPIB 1', NULL, 8, 2, '2025-10-25 10:45:26');

-- --------------------------------------------------------

--
-- Struktur dari tabel `presensi`
--

CREATE TABLE `presensi` (
  `presensi_id` int(11) NOT NULL,
  `nis` varchar(15) NOT NULL,
  `tanggal` date NOT NULL,
  `waktu_masuk` time DEFAULT NULL,
  `waktu_pulang` time DEFAULT NULL,
  `status` enum('Hadir','Izin','Sakit','Terlambat','Alpha') NOT NULL DEFAULT 'Alpha',
  `koordinat_in` varchar(255) DEFAULT NULL,
  `bukti_foto_path` varchar(255) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `is_validated` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `profil_perusahaan`
--

CREATE TABLE `profil_perusahaan` (
  `lokasi_id` int(11) NOT NULL,
  `bidang_usaha` varchar(100) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `telp_hrd` varchar(20) DEFAULT NULL,
  `deskripsi_detail` text DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `profil_perusahaan`
--

INSERT INTO `profil_perusahaan` (`lokasi_id`, `bidang_usaha`, `website`, `telp_hrd`, `deskripsi_detail`, `last_updated`) VALUES
(12, 'Konstruksi Sipil, Multimedia', 'https://dapodik.smkislam1blitar.sch.id/', '069', 'Abhipraya Pancasakra merupakan perusahaan yang bergerak di bidang Konstruksi Sipil dan Multimedia. Berlokasi di Tawangsari, perusahaan ini menjadi salah satu mitra praktik kerja industri bagi siswa SMK Islam 1 Blitar. Dengan jam kerja dari pukul 08.00 hingga 16.00 WIB, Abhipraya Pancasakra berkomitmen untuk memberikan pengalaman kerja yang profesional dan relevan di bidang konstruksi serta multimedia.', '2025-10-15 04:56:26');

-- --------------------------------------------------------

--
-- Struktur dari tabel `sertifikat_config`
--

CREATE TABLE `sertifikat_config` (
  `id` int(11) NOT NULL DEFAULT 1,
  `nama_penanda_tangan` varchar(100) DEFAULT NULL,
  `jabatan_penanda_tangan` varchar(100) DEFAULT NULL,
  `nip_penanda_tangan` varchar(50) DEFAULT NULL,
  `path_ttd_digital` varchar(255) DEFAULT NULL,
  `path_stempel` varchar(255) DEFAULT NULL,
  `template_teks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `sertifikat_config`
--

INSERT INTO `sertifikat_config` (`id`, `nama_penanda_tangan`, `jabatan_penanda_tangan`, `nip_penanda_tangan`, `path_ttd_digital`, `path_stempel`, `template_teks`, `created_at`) VALUES
(1, 'M. Daffa Rafi Naufal, S.Kom., M.T.', 'Daffie Photo Studio', '20020420062646893', 'ttd_1760684197.png', 'stempel_1760700509.png', 'sdsdsdsdsd', '2025-10-17 06:54:50');

-- --------------------------------------------------------

--
-- Struktur dari tabel `sertifikat_terbit`
--

CREATE TABLE `sertifikat_terbit` (
  `terbit_id` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL,
  `nomor_sertifikat` varchar(100) NOT NULL,
  `tanggal_terbit` date NOT NULL,
  `nilai_rata_rata` decimal(5,2) DEFAULT NULL,
  `nama_ttd_dudi` varchar(150) DEFAULT NULL,
  `jabatan_ttd_dudi` varchar(100) DEFAULT NULL,
  `nip_ttd_dudi` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `sertifikat_terbit`
--

INSERT INTO `sertifikat_terbit` (`terbit_id`, `siswa_id`, `nomor_sertifikat`, `tanggal_terbit`, `nilai_rata_rata`, `nama_ttd_dudi`, `jabatan_ttd_dudi`, `nip_ttd_dudi`) VALUES
(1, 15, '42213', '2025-10-17', NULL, NULL, NULL, NULL),
(3, 21, '421.dpib.smk sasas', '2025-10-17', NULL, NULL, NULL, NULL),
(4, 24, '251/01/X/SMEKISA.PKL-DPIB/2025', '2025-10-17', 88.60, 'Dr. (H.C.) H. M. Daffa Rafi Naufal, S.Kom. M.T., Ph.d.', 'Pimpinan Nawasena Studio', '200203071995121002'),
(30, 25, '258/01/X/SMEKISA.PKL-DPIB/2025', '2025-10-22', 0.00, 'Ajeng Novita, S.Pd.', 'Pimpinan Nawasena Studio', '');

-- --------------------------------------------------------

--
-- Struktur dari tabel `surat_keluar_pkl`
--

CREATE TABLE `surat_keluar_pkl` (
  `surat_id` int(11) NOT NULL,
  `nomor_surat` varchar(50) NOT NULL,
  `tanggal_surat` date NOT NULL,
  `kode_surat` varchar(50) DEFAULT NULL,
  `tujuan_perusahaan` varchar(255) NOT NULL,
  `alamat_tujuan` varchar(255) DEFAULT NULL,
  `perihal` varchar(255) NOT NULL,
  `lampiran` varchar(100) DEFAULT NULL,
  `isi_surat` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `surat_keluar_pkl`
--

INSERT INTO `surat_keluar_pkl` (`surat_id`, `nomor_surat`, `tanggal_surat`, `kode_surat`, `tujuan_perusahaan`, `alamat_tujuan`, `perihal`, `lampiran`, `isi_surat`, `file_path`, `created_at`) VALUES
(90, '600/01/X/SMEKISA.PKL-DPIB/2025', '2025-10-22', '600', 'Pilar Mas Group', 'Jl. Sungai Hilir Timur No 1 Blitar', 'Kerjasama PKL', 'Satu Berkas', 'ok', NULL, '2025-10-22 02:13:34');

-- --------------------------------------------------------

--
-- Struktur dari tabel `surat_masuk_pkl`
--

CREATE TABLE `surat_masuk_pkl` (
  `masuk_id` int(11) NOT NULL,
  `nomor_surat_masuk` varchar(50) NOT NULL,
  `tanggal_terima` date NOT NULL,
  `asal_perusahaan` varchar(255) NOT NULL,
  `perihal` varchar(255) NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `surat_masuk_pkl`
--

INSERT INTO `surat_masuk_pkl` (`masuk_id`, `nomor_surat_masuk`, `tanggal_terima`, `asal_perusahaan`, `perihal`, `file_path`, `created_at`) VALUES
(1, '12', '2025-10-14', 'UT', 'Permohonan Lowker', 'SM_1760416469_12.docx', '2025-10-14 04:34:29'),
(2, '42506asas', '2025-10-17', 'STMI', 'Sertifikat Talkshow Alumni Berbagi Tahun 2024', 'SM_1760669182_42506asas.docx', '2025-10-17 02:46:22');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','pembimbing','user') NOT NULL DEFAULT 'user',
  `profile_photo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `full_name`, `password`, `role`, `profile_photo`) VALUES
(1, 'admin', 'admin', 'admin123', 'admin', NULL),
(6, 'daffa', 'M. Daffa Rafi Naufal', '$2y$10$gcYRAhJSTzqQwT9uSBI95eBSYUFbNBa/lvk64wfF/BlNxPsZgFUmO', 'admin', 'prof_6_1761193953.png'),
(13, 'ade', 'Mochamad Ade Satria, S.T.', '$2y$10$186CXVpIQ2q1uVDagyXEx.o6ermUxdVoLcWvmUy0wlkXOtbTZXjVK', 'pembimbing', NULL),
(14, 'laoren', 'Laoren Septi Nur Azizah, S.,Pd.', '$2y$10$CMaJzH8SOP6SGzD4lmE5ZeQzvIn84k2VaDQiknMOlKvV94jwr/lbO', 'pembimbing', NULL),
(15, 'lucky', 'Lucky Prabawati, S.Pd., M.T.', '$2y$10$p4KgHCbf.ZDHcewpZ6CHMeqjWr/FcdtnbtwnNTnt9FQVubBHhfvNi', 'pembimbing', 'prof_15_1760493819.jpg');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `asistensi_pkl`
--
ALTER TABLE `asistensi_pkl`
  ADD PRIMARY KEY (`asistensi_id`),
  ADD KEY `siswa_id` (`siswa_id`);

--
-- Indeks untuk tabel `format_laporan`
--
ALTER TABLE `format_laporan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tipe` (`tipe`);

--
-- Indeks untuk tabel `guru`
--
ALTER TABLE `guru`
  ADD PRIMARY KEY (`guru_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indeks untuk tabel `hari_kerja_def`
--
ALTER TABLE `hari_kerja_def`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `hari_nama` (`hari_nama`);

--
-- Indeks untuk tabel `izin_pkl`
--
ALTER TABLE `izin_pkl`
  ADD PRIMARY KEY (`id_izin`),
  ADD KEY `siswa_id` (`siswa_id`);

--
-- Indeks untuk tabel `kartu_kendali`
--
ALTER TABLE `kartu_kendali`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_siswa_kegiatan` (`siswa_id`,`kegiatan_id`);

--
-- Indeks untuk tabel `kendali_kegiatan_def`
--
ALTER TABLE `kendali_kegiatan_def`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `urutan` (`urutan`);

--
-- Indeks untuk tabel `klasifikasi_surat`
--
ALTER TABLE `klasifikasi_surat`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`);

--
-- Indeks untuk tabel `lokasi_pkl`
--
ALTER TABLE `lokasi_pkl`
  ADD PRIMARY KEY (`lokasi_id`),
  ADD KEY `fk_guru_lokasi` (`guru_id`);

--
-- Indeks untuk tabel `lokasi_presensi`
--
ALTER TABLE `lokasi_presensi`
  ADD PRIMARY KEY (`lokasi_id`);

--
-- Indeks untuk tabel `monitoring_kunjungan`
--
ALTER TABLE `monitoring_kunjungan`
  ADD PRIMARY KEY (`kunjungan_id`),
  ADD KEY `guru_id` (`guru_id`),
  ADD KEY `lokasi_id` (`lokasi_id`);

--
-- Indeks untuk tabel `nilai_siswa_pkl`
--
ALTER TABLE `nilai_siswa_pkl`
  ADD PRIMARY KEY (`nilai_id`),
  ADD UNIQUE KEY `siswa_komponen_unique` (`siswa_id`,`komponen_id`),
  ADD KEY `komponen_id` (`komponen_id`);

--
-- Indeks untuk tabel `penilaian_aspek`
--
ALTER TABLE `penilaian_aspek`
  ADD PRIMARY KEY (`aspek_id`),
  ADD UNIQUE KEY `urutan` (`urutan`),
  ADD UNIQUE KEY `nama_aspek` (`nama_aspek`);

--
-- Indeks untuk tabel `penilaian_komponen`
--
ALTER TABLE `penilaian_komponen`
  ADD PRIMARY KEY (`komponen_id`),
  ADD KEY `aspek_id` (`aspek_id`);

--
-- Indeks untuk tabel `periode_pkl`
--
ALTER TABLE `periode_pkl`
  ADD PRIMARY KEY (`periode_id`);

--
-- Indeks untuk tabel `peserta_didik`
--
ALTER TABLE `peserta_didik`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nisn` (`nisn`),
  ADD KEY `lokasi_id` (`lokasi_id`),
  ADD KEY `periode_id` (`periode_id`);

--
-- Indeks untuk tabel `presensi`
--
ALTER TABLE `presensi`
  ADD PRIMARY KEY (`presensi_id`),
  ADD UNIQUE KEY `nis_tanggal` (`nis`,`tanggal`);

--
-- Indeks untuk tabel `profil_perusahaan`
--
ALTER TABLE `profil_perusahaan`
  ADD PRIMARY KEY (`lokasi_id`);

--
-- Indeks untuk tabel `sertifikat_config`
--
ALTER TABLE `sertifikat_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `single_row_constraint` (`id`);

--
-- Indeks untuk tabel `sertifikat_terbit`
--
ALTER TABLE `sertifikat_terbit`
  ADD PRIMARY KEY (`terbit_id`),
  ADD UNIQUE KEY `siswa_id` (`siswa_id`),
  ADD UNIQUE KEY `nomor_sertifikat` (`nomor_sertifikat`);

--
-- Indeks untuk tabel `surat_keluar_pkl`
--
ALTER TABLE `surat_keluar_pkl`
  ADD PRIMARY KEY (`surat_id`),
  ADD UNIQUE KEY `nomor_surat` (`nomor_surat`);

--
-- Indeks untuk tabel `surat_masuk_pkl`
--
ALTER TABLE `surat_masuk_pkl`
  ADD PRIMARY KEY (`masuk_id`),
  ADD UNIQUE KEY `nomor_surat_masuk` (`nomor_surat_masuk`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `asistensi_pkl`
--
ALTER TABLE `asistensi_pkl`
  MODIFY `asistensi_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT untuk tabel `format_laporan`
--
ALTER TABLE `format_laporan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `guru`
--
ALTER TABLE `guru`
  MODIFY `guru_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT untuk tabel `hari_kerja_def`
--
ALTER TABLE `hari_kerja_def`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `izin_pkl`
--
ALTER TABLE `izin_pkl`
  MODIFY `id_izin` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT untuk tabel `kartu_kendali`
--
ALTER TABLE `kartu_kendali`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT untuk tabel `kendali_kegiatan_def`
--
ALTER TABLE `kendali_kegiatan_def`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT untuk tabel `klasifikasi_surat`
--
ALTER TABLE `klasifikasi_surat`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `lokasi_pkl`
--
ALTER TABLE `lokasi_pkl`
  MODIFY `lokasi_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT untuk tabel `monitoring_kunjungan`
--
ALTER TABLE `monitoring_kunjungan`
  MODIFY `kunjungan_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `nilai_siswa_pkl`
--
ALTER TABLE `nilai_siswa_pkl`
  MODIFY `nilai_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=325;

--
-- AUTO_INCREMENT untuk tabel `penilaian_aspek`
--
ALTER TABLE `penilaian_aspek`
  MODIFY `aspek_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `penilaian_komponen`
--
ALTER TABLE `penilaian_komponen`
  MODIFY `komponen_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT untuk tabel `periode_pkl`
--
ALTER TABLE `periode_pkl`
  MODIFY `periode_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `peserta_didik`
--
ALTER TABLE `peserta_didik`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT untuk tabel `presensi`
--
ALTER TABLE `presensi`
  MODIFY `presensi_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `sertifikat_terbit`
--
ALTER TABLE `sertifikat_terbit`
  MODIFY `terbit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT untuk tabel `surat_keluar_pkl`
--
ALTER TABLE `surat_keluar_pkl`
  MODIFY `surat_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT untuk tabel `surat_masuk_pkl`
--
ALTER TABLE `surat_masuk_pkl`
  MODIFY `masuk_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `asistensi_pkl`
--
ALTER TABLE `asistensi_pkl`
  ADD CONSTRAINT `asistensi_pkl_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `peserta_didik` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `izin_pkl`
--
ALTER TABLE `izin_pkl`
  ADD CONSTRAINT `izin_pkl_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `peserta_didik` (`id`);

--
-- Ketidakleluasaan untuk tabel `kartu_kendali`
--
ALTER TABLE `kartu_kendali`
  ADD CONSTRAINT `kartu_kendali_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `peserta_didik` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `lokasi_pkl`
--
ALTER TABLE `lokasi_pkl`
  ADD CONSTRAINT `fk_guru_lokasi` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`guru_id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `lokasi_presensi`
--
ALTER TABLE `lokasi_presensi`
  ADD CONSTRAINT `lokasi_presensi_ibfk_1` FOREIGN KEY (`lokasi_id`) REFERENCES `lokasi_pkl` (`lokasi_id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `monitoring_kunjungan`
--
ALTER TABLE `monitoring_kunjungan`
  ADD CONSTRAINT `monitoring_kunjungan_ibfk_1` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`guru_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `monitoring_kunjungan_ibfk_2` FOREIGN KEY (`lokasi_id`) REFERENCES `lokasi_pkl` (`lokasi_id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `nilai_siswa_pkl`
--
ALTER TABLE `nilai_siswa_pkl`
  ADD CONSTRAINT `nilai_siswa_pkl_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `peserta_didik` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `nilai_siswa_pkl_ibfk_2` FOREIGN KEY (`komponen_id`) REFERENCES `penilaian_komponen` (`komponen_id`);

--
-- Ketidakleluasaan untuk tabel `penilaian_komponen`
--
ALTER TABLE `penilaian_komponen`
  ADD CONSTRAINT `penilaian_komponen_ibfk_1` FOREIGN KEY (`aspek_id`) REFERENCES `penilaian_aspek` (`aspek_id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `peserta_didik`
--
ALTER TABLE `peserta_didik`
  ADD CONSTRAINT `peserta_didik_ibfk_1` FOREIGN KEY (`lokasi_id`) REFERENCES `lokasi_pkl` (`lokasi_id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `presensi`
--
ALTER TABLE `presensi`
  ADD CONSTRAINT `presensi_ibfk_1` FOREIGN KEY (`nis`) REFERENCES `peserta_didik` (`nisn`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `profil_perusahaan`
--
ALTER TABLE `profil_perusahaan`
  ADD CONSTRAINT `profil_perusahaan_ibfk_1` FOREIGN KEY (`lokasi_id`) REFERENCES `lokasi_pkl` (`lokasi_id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `sertifikat_terbit`
--
ALTER TABLE `sertifikat_terbit`
  ADD CONSTRAINT `sertifikat_terbit_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `peserta_didik` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
