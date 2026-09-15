<?php
// templates/footer.php

// Variabel diperlukan untuk script JS
$total_rows = $total_rows ?? 0;
$start = $start ?? 0;
$limit = $limit ?? 10;
$page = $page ?? 1;
$total_pages = $total_pages ?? 1;
?>
</div> <footer class="main-footer">
    <div class="footer-content container">
        <div class="footer-info">
            <h4>SMK Jurusan DPIB</h4>
            <p>Membentuk profesional muda di bidang desain dan permodelan bangunan.</p>
        </div>
        <div class="footer-links">
            <h4>Tautan Cepat</h4>
            <a href="#home">Beranda</a>
            <a href="#about">Tentang PKL</a>
            <a href="admin/login.php">Admin Login</a>
        </div>
    </div>
    <div class="footer-copy">
        &copy; <?php echo date("Y"); ?> Pendaftaran PKL DPIB. Hak Cipta Dilindungi.
    </div>
</footer>

<script>
// Logika Slideshow
document.addEventListener("DOMContentLoaded", function() {
    let slideIndex = 0;
    const slides = document.querySelectorAll('.slide-item');
    
    if (slides.length === 0) return;

    // Inisialisasi: Tampilkan hanya slide pertama
    slides.forEach((slide, index) => {
        slide.style.display = index === 0 ? "flex" : "none";
    });

    function showSlides() {
        // Sembunyikan semua slide
        slides.forEach(slide => {
            slide.style.display = "none";
        });
        
        // Pindah ke slide berikutnya
        slideIndex++;
        if (slideIndex > slides.length) {slideIndex = 1}      
        
        // Tampilkan slide saat ini
        slides[slideIndex-1].style.display = "flex";      
        
        // Atur waktu tunggu (5 detik)
        setTimeout(showSlides, 5000); 
    }

    // Mulai slideshow setelah 5 detik
    setTimeout(showSlides, 5000); 
});


// Logika Search (Filter Tabel)
function filterTable() {
    var input, filter, table, tr, i;
    input = document.getElementById("searchInput");
    filter = input.value.toUpperCase();
    table = document.getElementById("lokasiTable");
    tr = table.getElementsByTagName("tr");

    // Perluas pencarian untuk menyertakan Lokasi (Index 0) dan Alamat (Index 2)
    var searching = filter.length > 0;
    
    // Sembunyikan/tampilkan pagination saat mencari
    // NOTE: Query selector disesuaikan karena struktur pagination di file utama
    const paginationNav = document.querySelector('.pagination-nav');
    const tableInfo = document.querySelector('.table-info');

    if (paginationNav) {
        paginationNav.style.display = searching ? 'none' : 'block';
    }
    if (tableInfo) {
        tableInfo.style.display = searching ? 'none' : 'block';
    }


    for (i = 1; i < tr.length; i++) { // Mulai dari 1 untuk lewati header
        tr[i].style.display = "none"; // Sembunyikan baris secara default
        
        var tdLokasi = tr[i].getElementsByTagName("td")[0];
        var tdAlamat = tr[i].getElementsByTagName("td")[2];
        
        if (tdLokasi || tdAlamat) {
            // Cek kecocokan di kolom Lokasi ATAU Alamat
          if (tdLokasi.textContent.toUpperCase().indexOf(filter) > -1 || 
              tdAlamat.textContent.toUpperCase().indexOf(filter) > -1) {
            tr[i].style.display = ""; // Tampilkan baris jika ada kecocokan
          }
        }       
    }
}
</script>
</body>
</html>