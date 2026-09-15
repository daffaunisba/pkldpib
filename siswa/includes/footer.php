<!-- ================= BOTTOM NAVIGATION MOBILE (FAB Style) ================= -->
        <div class="bottom-nav">
            
            <!-- 1. Menu Absen -->
            <a href="riwayat-presensi.php" class="bottom-nav-item <?= ($current_page == 'riwayat-presensi.php') ? 'active' : '' ?>">
                <i class="fas fa-clock-rotate-left"></i><span>Riwayat</span>
            </a>
            
            <!-- 2. Menu Izin -->
            <a href="pengajuan-izin.php" class="bottom-nav-item <?= ($current_page == 'pengajuan-izin.php') ? 'active' : '' ?>">
                <i class="fas fa-file-signature"></i><span>Izin</span>
            </a>
            
            <!-- 3. Tombol Tengah Utama (Beranda) -->
            <a href="index.php" class="bottom-nav-center-wrapper <?= ($current_page == 'index.php') ? 'active' : '' ?>">
                <div class="bottom-nav-center-btn">
                    <i class="fas fa-home"></i>
                </div>
                <span class="bottom-nav-center-text">Beranda</span>
            </a>
            
            <!-- 4. Menu Info -->
            <a href="pengumuman.php" class="bottom-nav-item position-relative <?= ($current_page == 'pengumuman.php') ? 'active' : '' ?>">
                <i class="fas fa-bullhorn"></i><span>Info</span>
                <?php if (isset($unread_count) && $unread_count > 0): ?>
                    <span class="position-absolute top-0 start-50 translate-middle badge rounded-pill bg-danger" style="font-size:0.6rem; margin-top: -3px;"><?= $unread_count ?></span>
                <?php endif; ?>
            </a>
            
            <!-- 5. Menu Lainnya (Offcanvas) -->
            <a href="#" class="bottom-nav-item" data-bs-toggle="offcanvas" data-bs-target="#offcanvasBottomMenu">
                <i class="fas fa-border-all"></i><span>Lainnya</span>
            </a>

        </div>

        <!-- ================= OFFCANVAS MENU LAINNYA (Tampilan Modern) ================= -->
        <div class="offcanvas offcanvas-bottom custom-offcanvas h-auto" tabindex="-1" id="offcanvasBottomMenu" style="background: #ffffff;">
            <!-- Header dengan Drag Indicator -->
            <div class="offcanvas-header flex-column border-bottom-0 pb-0 pt-2">
                <div class="drag-indicator" style="width: 50px; height: 5px; background-color: #cbd5e1; border-radius: 10px; margin: 0 auto 10px;"></div>
            </div>
            
            <div class="offcanvas-body p-0 pb-4 mt-2">
                <!-- Info Profil -->
                <div class="px-4 text-center mb-4">
                    <h6 class="mb-1 fw-bold" style="color: #1e293b;"><?= htmlspecialchars($nama_tampilan ?? 'Murid') ?></h6>
                    <small style="color: #64748b; font-size: 0.75rem;">
                        <i class="fas fa-map-marker-alt me-1 text-primary"></i> <?= htmlspecialchars($lokasi_tampilan ?? 'Lokasi Belum Plotting') ?>
                    </small>
                </div>

                <!-- Grid Card Menu -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; padding: 0 1.5rem; margin-bottom: 2rem;">
                    
                    <a href="biodata.php" style="display: flex; flex-direction: column; align-items: center; text-decoration: none; color: #334155; transition: 0.2s;">
                        <div style="width: 55px; height: 55px; border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 8px; box-shadow: 0 8px 15px rgba(0,0,0,0.06); background: linear-gradient(135deg, #e0c3fc 0%, #8ec5fc 100%); color: #5a67d8;">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <span style="font-size: 0.7rem; font-weight: 600; text-align: center;">Profil Saya</span>
                    </a>

                    <a href="kartu-kendali.php" style="display: flex; flex-direction: column; align-items: center; text-decoration: none; color: #334155; transition: 0.2s;">
                        <div style="width: 55px; height: 55px; border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 8px; box-shadow: 0 8px 15px rgba(0,0,0,0.06); background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); color: #dd6b20;">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <span style="font-size: 0.7rem; font-weight: 600; text-align: center;">Kartu Kendali</span>
                    </a>

                    <a href="upload-laporan.php" style="display: flex; flex-direction: column; align-items: center; text-decoration: none; color: #334155; transition: 0.2s;">
                        <div style="width: 55px; height: 55px; border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 8px; box-shadow: 0 8px 15px rgba(0,0,0,0.06); background: linear-gradient(135deg, #d4fc79 0%, #96e6a1 100%); color: #2f855a;">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <span style="font-size: 0.7rem; font-weight: 600; text-align: center;">Laporan PKL</span>
                    </a>

                </div>
                
                <!-- Tombol Keluar -->
                <div class="px-4">
                    <a href="logout.php" class="btn w-100 rounded-pill fw-bold py-2 shadow-sm text-danger" style="border: 1px solid #fee2e2; background-color: #fef2f2; transition: 0.3s;">
                        <i class="fas fa-sign-out-alt me-2"></i> Keluar Aplikasi
                    </a>
                </div>
            </div>
        </div>

    </div> 
    <!-- PENUTUP DIV #page-content-wrapper -->

    <!-- ================= MODAL FOTO GLOBAL ================= -->
    <div id="fotoModal" style="display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.8); backdrop-filter: blur(5px);">
        <div style="background: transparent; margin: 25% auto; padding: 10px; border-radius: 20px; width: 90%; max-width: 400px; position: relative;">
            <button onclick="closeModal()" style="position: absolute; right: 0px; top: -40px; background: #e53e3e; color: white; border: none; width: 35px; height: 35px; border-radius: 50%; font-weight: bold; font-size: 1.2rem; cursor: pointer; box-shadow: 0 4px 10px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center;">&times;</button>
            <img id="modalImage" src="" style="width: 100%; border-radius: 15px; display: block; box-shadow: 0 10px 25px rgba(0,0,0,0.5);">
        </div>
    </div>

    <!-- SCRIPT UTAMA -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openModal(src) {
            let modal = document.getElementById('fotoModal');
            if(modal) {
                modal.style.display = 'block';
                document.getElementById('modalImage').src = src;
                document.body.style.overflow = 'hidden';
            }
        }
        function closeModal() {
            let modal = document.getElementById('fotoModal');
            if(modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
        window.onclick = function(event) {
            let modal = document.getElementById('fotoModal');
            if (event.target == modal) closeModal();
        }
    </script>
</body>
</html>