<?php
/**
 * login.php — Halaman Login
 * Semua role (admin_master, admin, kontributor, user) login di sini.
 */
session_start();
require 'Server/koneksi.php';

if (isset($_SESSION['login']) && $_SESSION['login'] === true) {
    $dest = in_array($_SESSION['role'],['admin','admin_master']) ? 'dashboard.php' : 'dashboard-user.php';
    redirect($dest);
}

$pageTitle = 'Login';
$pageDesc  = 'Masuk ke panel InfoHarga Komoditi.';
?>
<!doctype html>
<html lang="id">
<head>
<?php include 'Assets/head.php'; ?>
<style>
/*
 * FIX DARK MODE BACKGROUND:
 * Masalah lama: .auth-bg hanya mendeklarasikan gradient tanpa base color,
 * sehingga body background dari head.php (var(--bg-primary)) tertimpa.
 *
 * Solusi: gunakan background-color dari var(--bg-primary) sebagai base,
 * lalu overlay gradient di atasnya dengan background-image.
 */
body {
  background-color: var(--bg-primary) !important;
  background-image:
    radial-gradient(ellipse 70% 60% at 30% 40%, rgba(16,185,129,.08) 0%, transparent 55%),
    radial-gradient(ellipse 50% 50% at 80% 70%, rgba(20,184,166,.06) 0%, transparent 50%);
  background-attachment: fixed;
  transition: background-color .25s !important;
}
/* Dark mode: gradient lebih subtle agar kontras dengan bg gelap */
.dark body, html.dark body {
  background-image:
    radial-gradient(ellipse 70% 60% at 30% 40%, rgba(16,185,129,.05) 0%, transparent 55%),
    radial-gradient(ellipse 50% 50% at 80% 70%, rgba(20,184,166,.04) 0%, transparent 50%);
}
</style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

  <a href="index.php" class="fixed top-5 left-5 flex items-center gap-1.5 text-sm text-[var(--text-muted)] hover:text-[var(--text-primary)] transition group">
    <i data-lucide="arrow-left" class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform"></i> Beranda
  </a>
  <button data-action="toggle-theme"
          class="fixed top-5 right-5 w-9 h-9 flex items-center justify-center rounded-lg bg-[var(--surface)] hover:bg-[var(--surface-hover)] border border-[var(--border)] text-[var(--text-muted)] hover:text-[var(--text-primary)] transition">
    <i data-lucide="moon" data-theme-icon="toggle" class="w-4 h-4"></i>
  </button>

  <div class="w-full max-w-sm animate-fade-up">
    <!-- Logo -->
    <div class="flex items-center justify-center gap-2.5 mb-8">
      <div class="w-9 h-9 bg-brand-500 rounded-xl flex items-center justify-center shadow-lg shadow-brand-500/30">
        <i data-lucide="trending-up" class="w-5 h-5 text-white"></i>
      </div>
      <span class="font-display font-black text-xl text-[var(--text-primary)]">
        InfoHarga<span class="text-brand-500">Komoditi</span>
      </span>
    </div>

    <div class="card p-7 shadow-2xl">
      <h1 class="font-display font-black text-2xl text-[var(--text-primary)] mb-1">Selamat Datang</h1>
      <p class="text-sm text-[var(--text-muted)] mb-6">Masuk untuk mengakses dashboard</p>

      <div id="msg-box" class="hidden mb-5 text-sm"></div>
    <?php
    // Tampilkan pesan terkunci langsung dari PHP (lebih akurat dari JS)
    $pesanUrl = $_GET['pesan'] ?? '';
    $sisaCoba = (int)($_GET['sisa'] ?? 0);
    $menitKunci= (int)($_GET['menit'] ?? 15);
    if ($pesanUrl === 'locked'):
    ?>
    <div class="mb-5 msg-error flex items-start gap-3">
      <i data-lucide="shield-alert" class="w-4 h-4 text-red-400 flex-shrink-0 mt-0.5"></i>
      <div>
        <strong>Akun Terkunci Sementara</strong><br/>
        <span class="text-xs">Terlalu banyak percobaan login gagal. Akun dikunci selama <strong><?= $menitKunci ?> menit</strong>. Coba lagi nanti atau hubungi admin.</span>
      </div>
    </div>
    <?php elseif ($pesanUrl === 'gagal' && $sisaCoba > 0): ?>
    <div class="mb-5 msg-error flex items-start gap-3">
      <i data-lucide="alert-circle" class="w-4 h-4 text-red-400 flex-shrink-0 mt-0.5"></i>
      <div>
        Username atau password salah. Sisa percobaan: <strong><?= $sisaCoba ?></strong>.
      </div>
    </div>
    <?php endif; ?>

      <form action="Proses/prosesLogin.php" method="POST" novalidate>
        <!-- Username -->
        <div class="mb-4">
          <label for="username" class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">Username</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-3.5 flex items-center text-[var(--text-muted)] pointer-events-none">
              <i data-lucide="user" class="w-4 h-4"></i>
            </span>
            <input id="username" type="text" name="username"
                   class="input-field input-icon"
                   placeholder="Masukkan username"
                   autocomplete="username" required maxlength="60"/>
          </div>
        </div>

        <!-- Password -->
        <div class="mb-6">
          <label for="pw" class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">Password</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-3.5 flex items-center text-[var(--text-muted)] pointer-events-none">
              <i data-lucide="lock" class="w-4 h-4"></i>
            </span>
            <input id="pw" type="password" name="password"
                   class="input-field input-icon pr-11"
                   placeholder="••••••••"
                   autocomplete="current-password" required/>
            <button type="button" id="togglePw" onclick="togglePassword('pw','togglePw')"
                    class="absolute inset-y-0 right-3.5 flex items-center text-[var(--text-muted)] hover:text-[var(--text-primary)] transition"
                    aria-label="Tampilkan password">
              <i data-lucide="eye" class="w-4 h-4"></i>
            </button>
          </div>
        </div>

        <button type="submit" name="login"
                class="w-full py-3 bg-brand-600 hover:bg-brand-500 text-white font-display font-bold rounded-xl text-sm transition shadow-lg shadow-brand-600/20 hover:-translate-y-0.5">
          Login Sekarang
        </button>
      </form>

      <div class="flex items-center gap-3 my-5">
        <div class="flex-1 h-px bg-[var(--border)]"></div>
        <span class="text-[var(--text-muted)] text-xs">atau</span>
        <div class="flex-1 h-px bg-[var(--border)]"></div>
      </div>

      <p class="text-center text-sm text-[var(--text-muted)]">
        Belum punya akun?
        <a href="register.php" class="text-brand-500 font-bold hover:text-brand-400 transition">Daftar sekarang</a>
      </p>
    </div>

    <!-- Demo accounts -->
    <div class="mt-4 card p-4 shadow-lg">
      <p class="text-xs font-bold text-[var(--text-muted)] uppercase tracking-wider mb-2">Demo Akun (password: password)</p>
      <div class="grid grid-cols-2 gap-2 text-xs text-[var(--text-muted)]">
        <div><span class="badge badge-purple mr-1.5">Master</span>admin_master</div>
        <div><span class="badge badge-green mr-1.5">Admin</span>admin</div>
        <div><span class="badge badge-blue mr-1.5">Kontributor</span>kontributor1</div>
        <div><span class="badge badge-slate mr-1.5">User</span>user_demo</div>
      </div>
    </div>

    <p class="text-center text-xs text-[var(--text-muted)] mt-5">&copy; <?= date('Y') ?> InfoHarga Komoditi</p>
  </div>

<script src="Assets/scripts.js"></script>
<script>lucide.createIcons();</script>
</body>
</html>
