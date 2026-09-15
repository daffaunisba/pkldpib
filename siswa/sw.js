// sw.js - Service Worker Standar untuk PWA
self.addEventListener('install', (e) => {
    console.log('[Service Worker] Terinstal');
});

self.addEventListener('fetch', (e) => {
    // Membiarkan semua request berjalan normal (syarat wajib PWA)
});