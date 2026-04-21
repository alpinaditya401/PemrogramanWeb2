<?php
/**
 * pusat-informasi.php — Pengaturan Pengumuman Global & API Gateway
 */
session_start();
require 'Server/koneksi.php';

// 1. KEAMANAN SANGAT KETAT: Hanya Admin Master yang boleh mengakses konfigurasi API
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'admin_master') {
    header("Location: dashboard-master.php?pesan=akses_ditolak");
    exit;
}

$pesan = "";

// --- FUNGSI BANTUAN UNTUK MENGAMBIL DATA ---
function getInfo($conn, $tipe) {
    $query = mysqli_query($conn, "SELECT * FROM pusat_informasi WHERE tipe='$tipe' LIMIT 1");
    return mysqli_fetch_assoc($query);
}

// 2. PROSES POST: SIMPAN PENGUMUMAN
if (isset($_POST['simpan_pengumuman'])) {
    $isi_data = mysqli_real_escape_string($conn, $_POST['isi_pengumuman']);
    $status = mysqli_real_escape_string($conn, $_POST['status_pengumuman']);
    
    $cek = getInfo($conn, 'pengumuman');
    if ($cek) {
        $q = "UPDATE pusat_informasi SET isi_data='$isi_data', status='$status' WHERE tipe='pengumuman'";
    } else {
        $q = "INSERT INTO pusat_informasi (tipe, judul, isi_data, status) VALUES ('pengumuman', 'Pengumuman Sistem', '$isi_data', '$status')";
    }
    
    if(mysqli_query($conn, $q)) $pesan = "success|Pengumuman berhasil diperbarui!";
    else $pesan = "error|Gagal memperbarui pengumuman.";
}

// 3. PROSES POST: SIMPAN SMS GATEWAY (Disimpan sebagai JSON)
if (isset($_POST['simpan_sms'])) {
    $api_key = $_POST['sms_api_key'];
    $sender = $_POST['sms_sender'];
    
    // Format menjadi JSON array
    $json_data = mysqli_real_escape_string($conn, json_encode([
        'api_key' => $api_key,
        'sender' => $sender
    ]));
    $status = $_POST['status_sms'];

    $cek = getInfo($conn, 'sms_gateway_api');
    if ($cek) {
        $q = "UPDATE pusat_informasi SET isi_data='$json_data', status='$status' WHERE tipe='sms_gateway_api'";
    } else {
        $q = "INSERT INTO pusat_informasi (tipe, judul, isi_data, status) VALUES ('sms_gateway_api', 'Konfigurasi SMS', '$json_data', '$status')";
    }
    
    if(mysqli_query($conn, $q)) $pesan = "success|Konfigurasi SMS Gateway tersimpan!";
}

// 4. PROSES POST: SIMPAN EMAIL GATEWAY (SMTP)
if (isset($_POST['simpan_email'])) {
    $json_data = mysqli_real_escape_string($conn, json_encode([
        'host' => $_POST['smtp_host'],
        'port' => $_POST['smtp_port'],
        'user' => $_POST['smtp_user'],
        'pass' => $_POST['smtp_pass']
    ]));
    $status = $_POST['status_email'];

    $cek = getInfo($conn, 'email_gateway_api');
    if ($cek) {
        $q = "UPDATE pusat_informasi SET isi_data='$json_data', status='$status' WHERE tipe='email_gateway_api'";
    } else {
        $q = "INSERT INTO pusat_informasi (tipe, judul, isi_data, status) VALUES ('email_gateway_api', 'Konfigurasi Email', '$json_data', '$status')";
    }
    
    if(mysqli_query($conn, $q)) $pesan = "success|Konfigurasi Email SMTP tersimpan!";
}

// AMBIL DATA TERKINI UNTUK DITAMPILKAN DI FORM
$data_pengumuman = getInfo($conn, 'pengumuman');
$data_sms = getInfo($conn, 'sms_gateway_api');
$data_email = getInfo($conn, 'email_gateway_api');

// Decode JSON untuk SMS dan Email agar bisa diisi ke input value
$sms_config = $data_sms ? json_decode($data_sms['isi_data'], true) : ['api_key'=>'', 'sender'=>''];
$email_config = $data_email ? json_decode($data_email['isi_data'], true) : ['host'=>'', 'port'=>'', 'user'=>'', 'pass'=>''];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <?php 
    $pageTitle = "Pusat Informasi & Gateway";
    include 'Assets/head.php'; 
    ?>
</head>
<body class="bg-[var(--bg-secondary)] text-[var(--text-primary)]">

    <?php include 'Assets/navbar.php'; ?>

    <div class="flex min-h-screen pt-24">
        <aside class="w-64 hidden lg:block border-r border-[var(--border)] p-6 space-y-2">
            <h3 class="text-xs font-bold text-[var(--text-muted)] uppercase tracking-widest mb-4">Menu Master</h3>
            <a href="dashboard-master.php" class="sidebar-nav flex items-center gap-3 p-3 rounded-xl hover:bg-[var(--surface)] transition">
                <i data-lucide="shield-check" class="w-5 h-5"></i>
                <span class="font-medium">Manajemen User</span>
            </a>
            <a href="kelola-artikel.php" class="sidebar-nav flex items-center gap-3 p-3 rounded-xl hover:bg-[var(--surface)] transition">
                <i data-lucide="file-text" class="w-5 h-5"></i>
                <span class="font-medium">Kelola Artikel</span>
            </a>
            <a href="pusat-informasi.php" class="sidebar-nav active flex items-center gap-3 p-3 rounded-xl bg-brand-500/10 text-brand-500">
                <i data-lucide="settings" class="w-5 h-5"></i>
                <span class="font-medium">Pengaturan Sistem</span>
            </a>
        </aside>

        <main class="flex-1 p-6 lg:p-10">
            <div class="max-w-4xl mx-auto">
                
                <div class="mb-8">
                    <h1 class="text-3xl font-display font-bold">Pusat Informasi & Gateway</h1>
                    <p class="text-[var(--text-muted)]">Atur pengumuman dashboard dan integrasi pengiriman pesan.</p>
                </div>

                <?php if ($pesan): 
                    $p = explode('|', $pesan);
                    $bgColor = $p[0] == 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                ?>
                <div class="<?= $bgColor ?> p-4 rounded-xl mb-6 flex items-center gap-3">
                    <i data-lucide="<?= $p[0] == 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-5 h-5"></i>
                    <p class="font-medium"><?= htmlspecialchars($p[1]) ?></p>
                </div>
                <?php endif; ?>

                <div class="space-y-6">
                    
                    <div class="bg-[var(--bg-card)] border border-[var(--border)] rounded-2xl p-6 shadow-sm">
                        <div class="flex justify-between items-center mb-5">
                            <div class="flex items-center gap-3 text-yellow-500">
                                <i data-lucide="megaphone" class="w-6 h-6"></i>
                                <h2 class="font-bold text-lg text-[var(--text-primary)]">Pengumuman Dashboard User</h2>
                            </div>
                        </div>
                        <form action="" method="POST" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">Isi Pengumuman</label>
                                <textarea name="isi_pengumuman" rows="3" required placeholder="Ketik pengumuman penting di sini..."
                                          class="w-full bg-[var(--bg-primary)] border border-[var(--border)] rounded-xl px-4 py-3 focus:ring-2 focus:ring-brand-500 outline-none"><?= htmlspecialchars($data_pengumuman['isi_data'] ?? '') ?></textarea>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <label class="text-sm font-medium">Status Tampil:</label>
                                    <select name="status_pengumuman" class="bg-[var(--bg-primary)] border border-[var(--border)] rounded-lg px-3 py-1.5 text-sm outline-none">
                                        <option value="aktif" <?= ($data_pengumuman['status']??'') == 'aktif' ? 'selected' : '' ?>>Aktif (Tampilkan)</option>
                                        <option value="nonaktif" <?= ($data_pengumuman['status']??'') == 'nonaktif' ? 'selected' : '' ?>>Nonaktif (Sembunyikan)</option>
                                    </select>
                                </div>
                                <button type="submit" name="simpan_pengumuman" class="bg-gray-800 hover:bg-gray-700 text-white font-bold px-6 py-2 rounded-xl transition flex items-center gap-2">
                                    <i data-lucide="save" class="w-4 h-4"></i> Simpan
                               </button>
                            </div>
                        </form>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        
                        <div class="bg-[var(--bg-card)] border border-[var(--border)] rounded-2xl p-6 shadow-sm">
                            <div class="flex items-center gap-3 mb-5 text-blue-500">
                                <i data-lucide="message-square" class="w-6 h-6"></i>
                                <h2 class="font-bold text-lg text-[var(--text-primary)]">Konfigurasi SMS API</h2>
                            </div>
                            <form action="" method="POST" class="space-y-4">
                                <div>
                                    <label class="block text-xs font-medium mb-1 text-[var(--text-muted)]">API Key Service (Contoh: Twilio/Zenziva)</label>
                                    <input type="password" name="sms_api_key" value="<?= htmlspecialchars($sms_config['api_key'] ?? '') ?>"
                                           class="w-full bg-[var(--bg-primary)] border border-[var(--border)] rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-brand-500 outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium mb-1 text-[var(--text-muted)]">Sender ID / Masking</label>
                                    <input type="text" name="sms_sender" value="<?= htmlspecialchars($sms_config['sender'] ?? '') ?>" placeholder="Cth: INFOHARGA"
                                           class="w-full bg-[var(--bg-primary)] border border-[var(--border)] rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-brand-500 outline-none">
                                </div>
                                
                                <div class="pt-2 border-t border-[var(--border)] flex justify-between items-center">
                                    <select name="status_sms" class="bg-[var(--bg-primary)] border border-[var(--border)] rounded-lg px-2 py-1.5 text-xs outline-none">
                                        <option value="aktif" <?= ($data_sms['status']??'') == 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                        <option value="nonaktif" <?= ($data_sms['status']??'') == 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                                    </select>
                                    <button type="submit" name="simpan_sms" class="bg-blue-600 hover:bg-blue-500 text-white text-sm font-bold px-4 py-2 rounded-xl transition">
                                        Simpan SMS
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="bg-[var(--bg-card)] border border-[var(--border)] rounded-2xl p-6 shadow-sm">
                            <div class="flex items-center gap-3 mb-5 text-brand-500">
                                <i data-lucide="mail" class="w-6 h-6"></i>
                                <h2 class="font-bold text-lg text-[var(--text-primary)]">Konfigurasi SMTP Email</h2>
                            </div>
                            <form action="" method="POST" class="space-y-4">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium mb-1 text-[var(--text-muted)]">SMTP Host</label>
                                        <input type="text" name="smtp_host" value="<?= htmlspecialchars($email_config['host'] ?? '') ?>" placeholder="smtp.mail.com"
                                               class="w-full bg-[var(--bg-primary)] border border-[var(--border)] rounded-xl px-3 py-2 text-sm outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium mb-1 text-[var(--text-muted)]">Port</label>
                                        <input type="number" name="smtp_port" value="<?= htmlspecialchars($email_config['port'] ?? '') ?>" placeholder="465 / 587"
                                               class="w-full bg-[var(--bg-primary)] border border-[var(--border)] rounded-xl px-3 py-2 text-sm outline-none">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium mb-1 text-[var(--text-muted)]">Email User</label>
                                        <input type="email" name="smtp_user" value="<?= htmlspecialchars($email_config['user'] ?? '') ?>" placeholder="admin@domain.com"
                                               class="w-full bg-[var(--bg-primary)] border border-[var(--border)] rounded-xl px-3 py-2 text-sm outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium mb-1 text-[var(--text-muted)]">Password</label>
                                        <input type="password" name="smtp_pass" value="<?= htmlspecialchars($email_config['pass'] ?? '') ?>" placeholder="••••••••"
                                               class="w-full bg-[var(--bg-primary)] border border-[var(--border)] rounded-xl px-3 py-2 text-sm outline-none">
                                    </div>
                                </div>
                                
                                <div class="pt-2 border-t border-[var(--border)] flex justify-between items-center">
                                    <select name="status_email" class="bg-[var(--bg-primary)] border border-[var(--border)] rounded-lg px-2 py-1.5 text-xs outline-none">
                                        <option value="aktif" <?= ($data_email['status']??'') == 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                        <option value="nonaktif" <?= ($data_email['status']??'') == 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                                    </select>
                                    <button type="submit" name="simpan_email" class="bg-brand-600 hover:bg-brand-500 text-white text-sm font-bold px-4 py-2 rounded-xl transition">
                                        Simpan SMTP
                                    </button>
                                </div>
                            </form>
                        </div>

                    </div>

                </div>

            </div>
        </main>
    </div>

    <script src="Assets/scripts.js"></script>
    <script>lucide.createIcons();</script>
</body>
</html>