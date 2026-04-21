<?php
/**
 * 404.php — Halaman Error 404 Custom
 * ─────────────────────────────────────────────────────────────
 * Ditampilkan saat user mengakses URL yang tidak ada.
 * Dipanggil otomatis oleh Apache lewat konfigurasi .htaccess:
 *   ErrorDocument 404 /InfoHargaa/404.php
 * ─────────────────────────────────────────────────────────────
 */
session_start();
// Set HTTP response code 404 agar mesin pencari tidak index halaman ini
http_response_code(404);

// Coba load koneksi untuk APP_NAME — jika gagal, pakai fallback
$appName = 'InfoHarga Komoditi';
try {
    if (file_exists(__DIR__.'/Server/koneksi.php')) {
        require_once __DIR__.'/Server/koneksi.php';
        $appName = defined('APP_NAME') ? APP_NAME : $appName;
    }
} catch (Throwable $e) {
    // Koneksi DB gagal — 404 tetap tampil dengan data statis
}

$isLoggedIn = isset($_SESSION['login']) && $_SESSION['login'] === true;
$role       = $_SESSION['role'] ?? '';
$backLink   = $isLoggedIn
    ? (in_array($role,['admin','admin_master']) ? 'dashboard.php' : 'dashboard-user.php')
    : 'index.php';
?>
<!doctype html>
<html lang="id" class="scroll-smooth">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>404 — Halaman Tidak Ditemukan | <?= htmlspecialchars($appName) ?></title>
  <meta name="robots" content="noindex,nofollow"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Cabinet+Grotesk:wght@400;700;900&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet"/>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
  <script>
    // Anti-flicker dark mode
    (function(){
      var t=localStorage.getItem('ih-theme');
      var p=window.matchMedia('(prefers-color-scheme:dark)').matches;
      if(t==='dark'||(!t&&p)) document.documentElement.classList.add('dark');
    })();
    tailwind.config={darkMode:'class',theme:{extend:{fontFamily:{display:['Cabinet Grotesk','sans-serif'],body:['Instrument Sans','sans-serif']}}}};
  </script>
  <style>
    body{font-family:'Instrument Sans',sans-serif;background-color:var(--bg,#f8fafc);transition:background-color .25s}
    .dark{--bg:#0b0e14}
    .dark body{background-color:#0b0e14}
    @keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-12px)}}
    @keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
    .float{animation:float 4s ease-in-out infinite}
    .fade-up{animation:fadeUp .5s ease both}
    .fade-up-1{animation:fadeUp .5s .1s ease both;opacity:0}
    .fade-up-2{animation:fadeUp .5s .2s ease both;opacity:0}
    .fade-up-3{animation:fadeUp .5s .3s ease both;opacity:0}
  </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center text-slate-800 dark:text-slate-200 px-4 py-16">

  <!-- Background glow -->
  <div class="fixed inset-0 pointer-events-none overflow-hidden" aria-hidden="true">
    <div class="absolute top-1/4 left-1/2 -translate-x-1/2 w-[500px] h-[300px] bg-emerald-500/8 dark:bg-emerald-500/5 blur-[80px] rounded-full"></div>
  </div>

  <!-- Logo -->
  <a href="index.php" class="flex items-center gap-2.5 mb-12 fade-up group">
    <div class="w-9 h-9 bg-emerald-500 rounded-xl flex items-center justify-center shadow-lg shadow-emerald-500/30 group-hover:scale-105 transition-transform">
      <i data-lucide="trending-up" class="w-5 h-5 text-white"></i>
    </div>
    <span class="font-display font-black text-xl text-slate-900 dark:text-white">
      InfoHarga<span class="text-emerald-500">Komoditi</span>
    </span>
  </a>

  <!-- Angka 404 besar -->
  <div class="float mb-6 fade-up">
    <div class="relative select-none">
      <span class="font-display font-black text-[9rem] md:text-[12rem] leading-none text-slate-100 dark:text-white/5">404</span>
      <div class="absolute inset-0 flex items-center justify-center">
        <div class="w-20 h-20 bg-white dark:bg-slate-800 rounded-2xl shadow-2xl dark:shadow-black/50 border border-slate-200 dark:border-white/10 flex items-center justify-center">
          <i data-lucide="search-x" class="w-10 h-10 text-emerald-500"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Pesan utama -->
  <h1 class="font-display font-black text-3xl md:text-4xl text-slate-900 dark:text-white text-center mb-3 fade-up-1">
    Halaman Tidak Ditemukan
  </h1>
  <p class="text-slate-500 dark:text-slate-400 text-center max-w-md leading-relaxed mb-10 fade-up-2">
    URL yang kamu akses tidak tersedia atau sudah dipindahkan.
    Mungkin ada salah ketik? Coba kembali ke halaman utama.
  </p>

  <!-- URL yang dicoba (untuk debugging) -->
  <?php
  $requestUri = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '');
  if ($requestUri && $requestUri !== '/404.php'):
  ?>
  <div class="mb-8 px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 font-mono text-xs text-slate-500 dark:text-slate-400 max-w-sm truncate fade-up-2">
    <?= $requestUri ?>
  </div>
  <?php endif; ?>

  <!-- Tombol aksi -->
  <div class="flex flex-col sm:flex-row gap-3 fade-up-3">
    <a href="<?= htmlspecialchars($backLink) ?>"
       class="flex items-center justify-center gap-2.5 px-7 py-3.5 bg-emerald-600 hover:bg-emerald-500 text-white font-display font-bold rounded-xl text-sm transition shadow-lg shadow-emerald-600/20 hover:-translate-y-0.5">
      <i data-lucide="home" class="w-4 h-4"></i>
      <?= $isLoggedIn ? 'Kembali ke Dashboard' : 'Kembali ke Beranda' ?>
    </a>
    <a href="javascript:history.back()"
       class="flex items-center justify-center gap-2.5 px-7 py-3.5 bg-slate-100 dark:bg-white/8 hover:bg-slate-200 dark:hover:bg-white/12 border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300 font-display font-semibold rounded-xl text-sm transition hover:-translate-y-0.5">
      <i data-lucide="arrow-left" class="w-4 h-4"></i> Halaman Sebelumnya
    </a>
    <a href="index.php#artikel"
       class="flex items-center justify-center gap-2.5 px-7 py-3.5 bg-slate-100 dark:bg-white/8 hover:bg-slate-200 dark:hover:bg-white/12 border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300 font-display font-semibold rounded-xl text-sm transition hover:-translate-y-0.5">
      <i data-lucide="book-open" class="w-4 h-4"></i> Baca Artikel
    </a>
  </div>

  <!-- Link cepat -->
  <div class="mt-14 fade-up-3">
    <p class="text-xs text-slate-400 dark:text-slate-500 text-center mb-4 uppercase tracking-wider font-semibold">
      Halaman Populer
    </p>
    <div class="flex flex-wrap justify-center gap-2">
      <?php foreach([
        ['index.php','home','Beranda'],
        ['chart.php','bar-chart-2','Grafik Harga'],
        ['register.php','user-plus','Daftar'],
        ['login.php','log-in','Login'],
      ] as [$url,$ic,$label]): ?>
      <a href="<?= $url ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-100 dark:bg-white/5 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 border border-slate-200 dark:border-white/10 hover:border-emerald-300 dark:hover:border-emerald-500/30 text-slate-600 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition">
        <i data-lucide="<?= $ic ?>" class="w-3 h-3"></i> <?= $label ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <p class="mt-12 text-xs text-slate-400 dark:text-slate-600">&copy; <?= date('Y') ?> <?= htmlspecialchars($appName) ?></p>

  <!-- Dark mode toggle -->
  <button onclick="(function(){const d=document.documentElement;const isDark=d.classList.toggle('dark');localStorage.setItem('ih-theme',isDark?'dark':'light')})()"
          class="fixed top-5 right-5 w-9 h-9 flex items-center justify-center rounded-lg bg-slate-100 dark:bg-white/8 border border-slate-200 dark:border-white/10 text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-white transition"
          aria-label="Ganti tema">
    <i data-lucide="moon" class="w-4 h-4 dark:hidden"></i>
    <i data-lucide="sun"  class="w-4 h-4 hidden dark:block"></i>
  </button>

<script>lucide.createIcons();</script>
</body>
</html>
