<?php
/**
 * Proses/prosesRegister.php
 * ─────────────────────────────────────────────────────────────
 * Memproses form registrasi dari register.php.
 * Role yang bisa dipilih user: 'user' atau 'kontributor'
 * (Admin/admin_master hanya bisa dibuat oleh admin_master)
 * ─────────────────────────────────────────────────────────────
 */
session_start();
require '../Server/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../register.php');

// Ambil & sanitasi semua input
$email      = esc($conn, $_POST['email']        ?? '');
$username   = esc($conn, $_POST['username']     ?? '');
$password   = trim($_POST['password']   ?? '');
$konfirmasi = trim($_POST['konfirmasi'] ?? '');
$nama       = esc($conn, $_POST['nama_lengkap'] ?? '');
$tgl_lahir  = esc($conn, $_POST['tgl_lahir']   ?? '');
$provinsi   = esc($conn, $_POST['provinsi']     ?? '');
$kota       = esc($conn, $_POST['kota']         ?? '');
$telepon    = esc($conn, $_POST['telepon']       ?? '');

// Role hanya boleh 'user' atau 'kontributor' — tidak bisa pilih admin via register
$roleRaw = $_POST['role'] ?? 'user';
$role    = in_array($roleRaw, ['user', 'kontributor'], true) ? $roleRaw : 'user';

// Validasi field wajib
if (!$email || !$username || !$password || !$konfirmasi)
    redirect('../register.php?error=empty');

if (!filter_var($email, FILTER_VALIDATE_EMAIL))
    redirect('../register.php?error=email_invalid');

if (mb_strlen($username) < 4)
    redirect('../register.php?error=username_short');

if (mb_strlen($password) < 6)
    redirect('../register.php?error=password_short');

if ($password !== $konfirmasi)
    redirect('../register.php?error=mismatch');

// Cek duplikat email
if ($conn->query("SELECT id FROM users WHERE email='$email' LIMIT 1")?->num_rows > 0)
    redirect('../register.php?error=email_taken');

// Cek duplikat username
if ($conn->query("SELECT id FROM users WHERE username='$username' LIMIT 1")?->num_rows > 0)
    redirect('../register.php?error=username_taken');

// Hash password dengan bcrypt
$hash = password_hash($password, PASSWORD_DEFAULT);

// Format tanggal lahir
$tgl = $tgl_lahir ? "'$tgl_lahir'" : 'NULL';

// Simpan ke database
$conn->query(
    "INSERT INTO users (email, username, password, nama_lengkap, tgl_lahir, telepon, role)
     VALUES ('$email','$username','$hash','$nama',$tgl,'$telepon','$role')"
);

$newId = (int)$conn->insert_id;

// Simpan juga provinsi & kota ke profil jika ada kolom — atau ke session
// (jika tabel users belum punya kolom provinsi, kita simpan di session saja)
// Cek apakah kolom provinsi ada di tabel users
$hasProvinsiCol = false;
$checkCol = $conn->query("SHOW COLUMNS FROM users LIKE 'provinsi'");
if ($checkCol && $checkCol->num_rows > 0) $hasProvinsiCol = true;

if ($hasProvinsiCol && ($provinsi || $kota)) {
    $kotaLokasi = $kota ?: $provinsi;
    $conn->query("UPDATE users SET provinsi='$provinsi' WHERE id=$newId");
}

// Auto-login setelah daftar
session_regenerate_id(true);
$_SESSION['login']    = true;
$_SESSION['user_id']  = $newId;
$_SESSION['username'] = $username;
$_SESSION['role']     = $role;
$_SESSION['provinsi'] = $provinsi; // simpan di session untuk prefill form
$_SESSION['kota']     = $kota;

// Redirect sesuai role
if ($role === 'kontributor') {
    redirect('../dashboard-user.php?tab=laporan&welcome=1');
} else {
    redirect('../dashboard-user.php?welcome=1');
}
