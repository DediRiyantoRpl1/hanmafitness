/**
 * Efek suara notifikasi "Benar" (berhasil), "Salah" (gagal/error),
 * dan "Login" (khusus setelah login sukses).
 *
 * Cara pakai:
 * 1. Taruh file audiomu di:
 *      assets/audio/benar.wav
 *      assets/audio/salah.wav
 *      assets/audio/login.wav
 * 2. Panggil manual di halaman mana pun (setelah script ini dimuat):
 *      mainkanSuaraBenar();
 *      mainkanSuaraSalah();
 *      mainkanSuaraLogin();
 *
 * Otomatis juga akan berbunyi sendiri kalau di halaman ada elemen
 * dengan class "alert-success" (bunyi benar) atau "alert-danger" (bunyi salah)
 * saat halaman selesai dimuat -- jadi tidak perlu ubah kode di form manapun.
 *
 * Suara LOGIN dipicu manual lewat mainkanSuaraLogin() -- dipanggil dari
 * includes/notif_login.php yang di-include di dashboard admin/petugas/owner/member
 * setelah login berhasil (berdasarkan $_SESSION['notif_login']).
 *
 * Kalau file audio belum ada / gagal dimuat, otomatis fallback ke nada
 * sintesis bawaan (Web Audio API) supaya tidak diam saja.
 */

// ==== GANTI PATH INI KALAU NAMA FOLDER PROJECT KAMU BEDA DARI BASE_URL DI koneksi.php ====
const SUARA_BENAR_URL = '/parkir_hanmafitness/assets/audio/benar.wav';
const SUARA_SALAH_URL = '/parkir_hanmafitness/assets/audio/haki.mpeg';
const SUARA_LOGIN_URL = '/parkir_hanmafitness/assets/audio/aizen.mpeg';
const SUARA_PEMBAYARAN_URL = '/parkir_hanmafitness/assets/audio/dana.mpeg';

function _mainkanFileAtauFallback(url, fallbackFn) {
    const audio = new Audio(url);
    let sudahFallback = false;
    audio.addEventListener('error', function () {
        if (!sudahFallback) { sudahFallback = true; fallbackFn(); }
    });
    audio.play().catch(function () {
        if (!sudahFallback) { sudahFallback = true; fallbackFn(); }
    });
}

function mainkanSuaraBenar() {
    _mainkanFileAtauFallback(SUARA_BENAR_URL, _fallbackSuaraBenar);
}

function mainkanSuaraSalah() {
    _mainkanFileAtauFallback(SUARA_SALAH_URL, _fallbackSuaraSalah);
}

function mainkanSuaraLogin() {
    _mainkanFileAtauFallback(SUARA_LOGIN_URL, _fallbackSuaraLogin);
}

function mainkanSuaraPembayaran() {
    _mainkanFileAtauFallback(SUARA_PEMBAYARAN_URL, _fallbackSuaraPembayaran);
}

// ---- Nada sintesis cadangan (dipakai otomatis kalau file audio belum ada) ----
function _fallbackSuaraBenar() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const now = ctx.currentTime;
        [523.25, 659.25, 783.99].forEach(function (freq, i) {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.value = freq;
            const start = now + i * 0.09;
            gain.gain.setValueAtTime(0.001, start);
            gain.gain.exponentialRampToValueAtTime(0.18, start + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.28);
            osc.connect(gain).connect(ctx.destination);
            osc.start(start);
            osc.stop(start + 0.3);
        });
    } catch (e) { /* abaikan */ }
}

function _fallbackSuaraSalah() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const now = ctx.currentTime;
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'square';
        osc.frequency.setValueAtTime(220, now);
        osc.frequency.linearRampToValueAtTime(110, now + 0.35);
        gain.gain.setValueAtTime(0.001, now);
        gain.gain.exponentialRampToValueAtTime(0.15, now + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.4);
        osc.connect(gain).connect(ctx.destination);
        osc.start(now);
        osc.stop(now + 0.4);
    } catch (e) { /* abaikan */ }
}

// ---- Nada sintesis cadangan khusus login (nada "welcome chime") ----
function _fallbackSuaraLogin() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const now = ctx.currentTime;
        // Nada naik: C5 - E5 - G5 - C6 (kesan "selamat datang")
        [523.25, 659.25, 783.99, 1046.50].forEach(function (freq, i) {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'triangle';
            osc.frequency.value = freq;
            const start = now + i * 0.1;
            gain.gain.setValueAtTime(0.001, start);
            gain.gain.exponentialRampToValueAtTime(0.2, start + 0.03);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.35);
            osc.connect(gain).connect(ctx.destination);
            osc.start(start);
            osc.stop(start + 0.4);
        });
    } catch (e) { /* abaikan */ }
}

// ---- Nada sintesis cadangan khusus pembayaran (nada "cha-ching" pendek) ----
function _fallbackSuaraPembayaran() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const now = ctx.currentTime;
        // Dua nada cepat naik lalu satu nada tinggi menahan, kesan "transaksi selesai"
        [880, 1174.66, 1567.98].forEach(function (freq, i) {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.value = freq;
            const start = now + i * 0.07;
            gain.gain.setValueAtTime(0.001, start);
            gain.gain.exponentialRampToValueAtTime(0.22, start + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.25);
            osc.connect(gain).connect(ctx.destination);
            osc.start(start);
            osc.stop(start + 0.26);
        });
    } catch (e) { /* abaikan */ }
}

// Deteksi otomatis alert Bootstrap yang sudah ada di halaman (alert-success / alert-danger)
document.addEventListener('DOMContentLoaded', function () {
    if (document.querySelector('.alert-danger')) {
        mainkanSuaraSalah();
    } else if (document.querySelector('.alert-success')) {
        mainkanSuaraBenar();
    }
});