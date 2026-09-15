<?php 
// admin/panel/footer.php - VERSI FINAL RESPONSIVE

// Variabel $js_labels dan $js_values hanya digunakan di dashboard.php
$js_labels = isset($js_labels) ? $js_labels : '[]';
$js_values = isset($js_values) ? $js_values : '[]';
?>
        </div> </main>
    
    <footer class="admin-footer">
        &copy; <?php echo date("Y"); ?> Tim IT DPIB SMK Islam 1 Blitar
    </footer>
</div> 

<style>
    /* CSS Dasar Footer */
    .admin-footer {
        padding: 15px 30px;
        background-color: #f8f9fa;
        border-top: 1px solid #ddd;
        color: #7f8c8d;
        font-size: 14px;
        text-align: left;
    }

    /* Responsif untuk Layar HP (Maksimal 768px) */
    @media (max-width: 768px) {
        .admin-footer {
            padding: 15px;
            text-align: center; /* Buat teks rata tengah di HP agar lebih rapi */
            font-size: 12px;   /* Sedikit perkecil ukuran font di HP */
        }
    }
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // --- SKRIP JAVASCRIPT DROPDOWN SIDEBAR (PENTING) ---
    const infoPklToggle = document.getElementById('infoPklToggle');
    const infoPklMenu = document.getElementById('infoPklMenu');

    // Memastikan elemen ada sebelum memicu event listener
    if (infoPklToggle && infoPklMenu) {
        infoPklToggle.addEventListener('click', function(e) {
            e.preventDefault();
            // Toggle class 'active' untuk menampilkan/menyembunyikan menu
            infoPklMenu.classList.toggle('active');
            infoPklToggle.classList.toggle('active'); // Untuk memutar ikon panah
        });
    }

    // --- Script Chart JS hanya untuk Dashboard (Abaikan jika elemen tidak ada) ---
    const ctx = document.getElementById('pklChart');
    if (ctx) {
        // Logika ChartJS
        const labels = <?php echo $js_labels; ?>;
        const dataValues = <?php echo $js_values; ?>;
        
        const accentColor = '#28a745'; 
        const barColors = dataValues.map(value => value > 0 ? accentColor : '#ced4da');

        new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Jumlah Peserta Terdaftar',
                    data: dataValues,
                    backgroundColor: barColors,
                    borderColor: barColors.map(color => color.replace('0.2', '1')), 
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Jumlah Siswa' } },
                    x: { grid: { display: false } }
                },
                plugins: { legend: { display: false }, title: { display: false } }
            }
        });
    }
});
</script>

</body>
</html>