<?php
/**
 * Server/koneksi.php
 * ─────────────────────────────────────────────────────────────
 * File ini adalah JANTUNG koneksi database.
 * Setiap file PHP yang membutuhkan akses database harus:
 *   require 'Server/koneksi.php';  (atau require '../Server/koneksi.php' dari subfolder)
 *
 * Selain koneksi, file ini menyediakan fungsi-fungsi HELPER
 * yang bisa dipakai di seluruh project tanpa perlu di-include ulang.
 * ─────────────────────────────────────────────────────────────
 */

// ── KONFIGURASI DATABASE ─────────────────────────────────────
define('DB_HOST',    'localhost');   // Ganti jika host berbeda
define('DB_USER',    'root');        // Username MySQL (default XAMPP: root)
define('DB_PASS',    '');            // Password MySQL (default XAMPP: kosong)
define('DB_NAME',    'infoharga_db');// Nama database

// ── KONEKSI ───────────────────────────────────────────────────
mysqli_report(MYSQLI_REPORT_OFF); // Kita handle error secara manual
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    // Tampilkan halaman error yang ramah jika DB tidak bisa konek
    http_response_code(503);
    die('<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">
    <title>Koneksi Gagal</title>
    <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;
    min-height:100vh;margin:0;background:#0b0e14;color:#94a3b8;}
    .box{text-align:center;padding:2rem;max-width:400px;}
    h2{color:#fff;font-size:1.5rem;} p{color:#64748b;font-size:.875rem;}</style></head>
    <body><div class="box"><div style="font-size:3rem">⚠️</div>
    <h2>Koneksi Database Gagal</h2>
    <p>Periksa konfigurasi di Server/koneksi.php dan pastikan MySQL sudah berjalan.</p>
    </div></body></html>');
}

$conn->set_charset('utf8mb4');

// ── KONSTANTA APLIKASI ────────────────────────────────────────
define('APP_NAME',    'InfoHarga Komoditi');
define('APP_VERSION', '4.0.0');

// ── DAFTAR 38 PROVINSI INDONESIA ─────────────────────────────
// Disimpan di sini agar tidak perlu ditulis ulang di setiap file
define('PROVINSI_LIST', [
    'Aceh','Sumatera Utara','Sumatera Barat','Riau','Kepulauan Riau',
    'Jambi','Bengkulu','Sumatera Selatan','Kepulauan Bangka Belitung',
    'Lampung','Banten','DKI Jakarta','Jawa Barat','Jawa Tengah',
    'DI Yogyakarta','Jawa Timur','Bali','Nusa Tenggara Barat',
    'Nusa Tenggara Timur','Kalimantan Barat','Kalimantan Tengah',
    'Kalimantan Selatan','Kalimantan Timur','Kalimantan Utara',
    'Sulawesi Utara','Gorontalo','Sulawesi Tengah','Sulawesi Barat',
    'Sulawesi Selatan','Sulawesi Tenggara','Maluku','Maluku Utara',
    'Papua','Papua Barat','Papua Selatan','Papua Tengah',
    'Papua Pegunungan','Papua Barat Daya',
]);

// ── HELPER FUNCTIONS ─────────────────────────────────────────

/**
 * esc() — Sanitasi string untuk query SQL
 * Mencegah SQL Injection dengan membersihkan karakter berbahaya
 */
function esc(mysqli $conn, string $val): string {
    return $conn->real_escape_string(trim($val));
}

/**
 * rupiah() — Format angka ke format mata uang Rupiah
 * Contoh: rupiah(15000) → "Rp 15.000"
 */
function rupiah(int $n): string {
    return 'Rp ' . number_format($n, 0, ',', '.');
}

/**
 * redirect() — Redirect ke URL dan hentikan script
 * Menggunakan return type 'never' karena fungsi selalu exit()
 */
function redirect(string $url): never {
    header("Location: $url");
    exit();
}

/**
 * cekLogin() — Pastikan user sudah login, redirect jika belum
 * Dipanggil di bagian atas halaman yang perlu autentikasi
 */
function cekLogin(): void {
    if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
        redirect('login.php');
    }
}

/**
 * cekRole() — Pastikan user memiliki role tertentu
 * Contoh: cekRole(['admin','admin_master']) → hanya admin dan admin_master yang boleh
 */
function cekRole(array $roles): void {
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        // Role tidak sesuai, redirect ke halaman yang tepat
        $role = $_SESSION['role'] ?? '';
        if ($role === 'admin_master' || $role === 'admin') redirect('dashboard.php');
        elseif ($role === 'kontributor') redirect('dashboard-user.php?tab=laporan');
        else redirect('dashboard-user.php');
    }
}

/**
 * getSetting() — Ambil nilai pengaturan sistem dari database
 * Contoh: getSetting($conn, 'nama_situs') → 'InfoHarga Komoditi'
 */
function getSetting(mysqli $conn, string $kunci, string $default = ''): string {
    $k   = esc($conn, $kunci);
    $res = $conn->query("SELECT nilai FROM pengaturan_sistem WHERE kunci='$k' LIMIT 1");
    if ($res && $res->num_rows > 0) {
        return $res->fetch_assoc()['nilai'] ?? $default;
    }
    return $default;
}

/**
 * getPengumuman() — Ambil pengumuman aktif dari database
 * Dipakai di dashboard user untuk menampilkan notifikasi
 */
function getPengumuman(mysqli $conn): array {
    $res  = $conn->query(
        "SELECT * FROM pengumuman WHERE is_active=1
         AND (berlaku_hingga IS NULL OR berlaku_hingga >= CURDATE())
         ORDER BY tipe DESC, created_at DESC LIMIT 5"
    );
    $list = [];
    if ($res) while ($r = $res->fetch_assoc()) $list[] = $r;
    return $list;
}

/**
 * slugify() — Buat URL slug dari judul artikel
 * Contoh: slugify('Harga Beras Naik!') → 'harga-beras-naik'
 */
function slugify(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

// Integrasi API BPS & mapping Provinsi → Kota
require_once __DIR__ . '/bps_api.php';
