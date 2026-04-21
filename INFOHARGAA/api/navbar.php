<?php
/**
 * Assets/navbar.php
 * ─────────────────────────────────────────────────────────────
 * Navbar publik yang digunakan di index.php dan chart.php.
 * Sebelum include, set $activeNav = 'home' | 'chart' | 'artikel'
 * ─────────────────────────────────────────────────────────────
 */
$activeNav  = $activeNav ?? '';
$isLoggedIn = isset($_SESSION['login']) && $_SESSION['login'] === true;
$userRole   = $_SESSION['role'] ?? '';
?>
<!-- Spacer untuk ticker bar -->
<div class="h-9"></div>

<header class="fixed w-full z-50 top-9" id="main-nav">
  <div class="absolute inset-0 bg-[var(--bg-primary)]/85 backdrop-blur-xl border-b border-[var(--border)] transition-all" id="nav-bg"></div>
  <div class="relative max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center h-16">

      <!-- Logo -->
      <a href="index.php" class="flex items-center gap-2.5 group">
        <div class="w-8 h-8 bg-brand-500 rounded-lg flex items-center justify-center shadow-lg shadow-brand-500/30 group-hover:scale-105 transition-transform">
          <i data-lucide="trending-up" class="w-4 h-4 text-white"></i>
        </div>
        <span class="font-display font-black text-lg text-[var(--text-primary)] tracking-tight">
          InfoHarga<span class="text-brand-500">Komoditi</span>
        </span>
      </a>

      <!-- Desktop Nav -->
      <nav class="hidden md:flex items-center gap-1" aria-label="Navigasi utama">
        <a href="index.php" class="px-3.5 py-2 rounded-lg text-sm font-medium transition
           <?= $activeNav==='home' ? 'text-brand-500 bg-brand-500/10' : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--surface)]' ?>">
          Beranda
        </a>
        <a href="chart.php" class="px-3.5 py-2 rounded-lg text-sm font-medium transition
           <?= $activeNav==='chart' ? 'text-brand-500 bg-brand-500/10' : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--surface)]' ?>">
          Grafik Harga
        </a>
        <a href="index.php#artikel" class="px-3.5 py-2 rounded-lg text-sm font-medium transition
           <?= $activeNav==='artikel' ? 'text-brand-500 bg-brand-500/10' : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-[var(--surface)]' ?>">
          Artikel
        </a>
      </nav>

      <!-- Right side -->
      <div class="flex items-center gap-2">
        <!-- Tombol dark mode -->
        <button data-action="toggle-theme" aria-label="Ganti tema"
                class="w-9 h-9 flex items-center justify-center rounded-lg bg-[var(--surface)] hover:bg-[var(--surface-hover)] text-[var(--text-muted)] hover:text-[var(--text-primary)] transition border border-[var(--border)]">
          <i data-lucide="moon" data-theme-icon="toggle" class="w-4 h-4"></i>
        </button>

        <?php if ($isLoggedIn): ?>
          <!-- User sudah login: tampilkan link ke dashboard -->
          <a href="<?= in_array($userRole,['admin','admin_master']) ? 'dashboard.php' : 'dashboard-user.php' ?>"
             class="hidden md:flex items-center gap-2 px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white text-sm font-bold rounded-lg transition shadow-md shadow-brand-500/25 font-display">
            <i data-lucide="layout-dashboard" class="w-3.5 h-3.5"></i> Dashboard
          </a>
        <?php else: ?>
          <!-- Guest: tampilkan tombol login -->
          <a href="login.php"
             class="hidden md:flex items-center gap-2 px-4 py-2 bg-[var(--surface)] hover:bg-[var(--surface-hover)] border border-[var(--border)] text-[var(--text-primary)] text-sm font-semibold rounded-lg transition font-display">
            <i data-lucide="user" class="w-3.5 h-3.5"></i> Login Admin
          </a>
        <?php endif; ?>

        <!-- Mobile hamburger -->
        <button id="mobileMenuBtn" aria-label="Buka menu"
                class="md:hidden w-9 h-9 flex items-center justify-center rounded-lg bg-[var(--surface)] text-[var(--text-muted)] hover:text-[var(--text-primary)] transition border border-[var(--border)]">
          <i data-lucide="menu" class="w-4 h-4"></i>
        </button>
      </div>
    </div>
  </div>

  <!-- Mobile menu -->
  <div id="mobileMenu" class="hidden md:hidden bg-[var(--bg-card)] border-b border-[var(--border)] px-4 pb-4 pt-2 space-y-1">
    <a href="index.php"         class="block px-3 py-2.5 rounded-lg text-sm font-medium text-[var(--text-secondary)] hover:bg-[var(--surface)] hover:text-[var(--text-primary)] transition">Beranda</a>
    <a href="chart.php"         class="block px-3 py-2.5 rounded-lg text-sm font-medium text-[var(--text-secondary)] hover:bg-[var(--surface)] hover:text-[var(--text-primary)] transition">Grafik Harga</a>
    <a href="index.php#artikel" class="block px-3 py-2.5 rounded-lg text-sm font-medium text-[var(--text-secondary)] hover:bg-[var(--surface)] hover:text-[var(--text-primary)] transition">Artikel</a>
    <?php if ($isLoggedIn): ?>
    <a href="<?= in_array($userRole,['admin','admin_master']) ? 'dashboard.php' : 'dashboard-user.php' ?>"
       class="block px-3 py-2.5 rounded-lg text-sm font-semibold text-brand-500">Dashboard</a>
    <?php else: ?>
    <a href="login.php" class="block px-3 py-2.5 rounded-lg text-sm font-semibold text-brand-500">Login</a>
    <?php endif; ?>
  </div>
</header>

<script>
// Scroll shadow effect untuk navbar
window.addEventListener('scroll', () => {
  const bg = document.getElementById('nav-bg');
  if (bg) bg.style.boxShadow = window.scrollY > 10 ? 'var(--shadow-md)' : 'none';
});
</script>
