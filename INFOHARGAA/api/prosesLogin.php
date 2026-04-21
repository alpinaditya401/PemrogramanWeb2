<?php
/**
 * Proses/prosesLogin.php — Login dengan Rate Limiting
 * ─────────────────────────────────────────────────────────────
 * Proteksi brute-force: akun dikunci 15 menit setelah 5 kali
 * gagal login berturut-turut.
 *
 * Kolom yang dibutuhkan di tabel users:
 *   login_attempts  TINYINT UNSIGNED DEFAULT 0
 *   locked_until    DATETIME DEFAULT NULL
 * (Dibuat oleh database_update.sql)
 * ─────────────────────────────────────────────────────────────
 */
session_start();
require '../Server/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../login.php');

$username = esc($conn, trim($_POST['username'] ?? ''));
$password = trim($_POST['password'] ?? '');
$ip       = esc($conn, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

if (!$username || !$password) redirect('../login.php?pesan=empty');

// ── CARI USER ─────────────────────────────────────────────────
$res = $conn->query("SELECT id, username, password, role, is_active,
                            login_attempts, locked_until
                     FROM users WHERE username='$username' LIMIT 1");

if (!$res || $res->num_rows === 0) {
    // Username tidak ada — tidak beri info detail (security best practice)
    redirect('../login.php?pesan=gagal');
}

$u = $res->fetch_assoc();

// ── CEK AKUN AKTIF ────────────────────────────────────────────
if (!$u['is_active']) redirect('../login.php?pesan=nonaktif');

// ── CEK RATE LIMIT — Apakah akun sedang terkunci? ────────────
if (!empty($u['locked_until'])) {
    $lockedUntil = strtotime($u['locked_until']);
    if ($lockedUntil > time()) {
        $menitSisa = ceil(($lockedUntil - time()) / 60);
        // Redirect dengan pesan terkunci + waktu sisa
        redirect('../login.php?pesan=locked&menit='.$menitSisa);
    } else {
        // Waktu kunci sudah habis — reset counter
        $conn->query("UPDATE users SET login_attempts=0, locked_until=NULL WHERE id={$u['id']}");
        $u['login_attempts'] = 0;
        $u['locked_until']   = null;
    }
}

// ── VERIFIKASI PASSWORD ───────────────────────────────────────
if (password_verify($password, $u['password'])) {
    // ✅ LOGIN BERHASIL
    $conn->query("UPDATE users SET
        login_attempts=0, locked_until=NULL, last_login=NOW()
        WHERE id={$u['id']}");

    // Catat di activity log
    $conn->query("INSERT IGNORE INTO activity_log (user_id, username, aksi, deskripsi, ip_address)
        VALUES ({$u['id']}, '$username', 'login', 'Login berhasil', '$ip')");

    session_regenerate_id(true);
    $_SESSION['login']    = true;
    $_SESSION['user_id']  = (int)$u['id'];
    $_SESSION['username'] = $u['username'];
    $_SESSION['role']     = $u['role'];

    $dest = match($u['role']) {
        'admin_master', 'admin' => '../dashboard.php',
        'kontributor'           => '../dashboard-user.php?tab=laporan',
        default                 => '../dashboard-user.php',
    };
    redirect($dest);

} else {
    // ❌ PASSWORD SALAH — tambah counter
    $attempts = (int)$u['login_attempts'] + 1;
    $maxTry   = 5;     // Maksimal percobaan
    $lockMin  = 15;    // Lama kunci dalam menit

    if ($attempts >= $maxTry) {
        // Kunci akun selama $lockMin menit
        $lockedUntil = date('Y-m-d H:i:s', time() + ($lockMin * 60));
        $conn->query("UPDATE users SET login_attempts=$attempts, locked_until='$lockedUntil' WHERE id={$u['id']}");
        $conn->query("INSERT IGNORE INTO activity_log (user_id, username, aksi, deskripsi, ip_address)
            VALUES ({$u['id']}, '$username', 'login_gagal_terkunci', 'Akun dikunci setelah $maxTry percobaan gagal', '$ip')");
        redirect('../login.php?pesan=locked&menit='.$lockMin);
    } else {
        $sisaCoba = $maxTry - $attempts;
        $conn->query("UPDATE users SET login_attempts=$attempts WHERE id={$u['id']}");
        $conn->query("INSERT IGNORE INTO activity_log (user_id, username, aksi, deskripsi, ip_address)
            VALUES ({$u['id']}, '$username', 'login_gagal', 'Percobaan ke-$attempts, sisa $sisaCoba', '$ip')");
        redirect('../login.php?pesan=gagal&sisa='.$sisaCoba);
    }
}
