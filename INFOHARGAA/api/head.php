<?php
/**
 * Assets/head.php
 * ─────────────────────────────────────────────────────────────
 * Shared <head> partial yang di-include di SEMUA halaman.
 *
 * Sebelum include, set variabel berikut di file pemanggil:
 *   $pageTitle    (string) — judul halaman
 *   $pageDesc     (string) — deskripsi SEO
 *   $pageKeywords (string) — kata kunci SEO
 *
 * File ini mengandung:
 * 1. Meta tags (SEO, OpenGraph, Twitter Card)
 * 2. Google Fonts (Cabinet Grotesk + Instrument Sans)
 * 3. Tailwind CSS via CDN
 * 4. Lucide Icons via CDN
 * 5. Script dark mode ANTI-FLICKER (harus sebelum body render)
 * 6. CSS Custom Properties untuk tema light/dark
 * 7. Komponen-komponen CSS reusable (card, badge, input, table, dll)
 * ─────────────────────────────────────────────────────────────
 */

$pageTitle    = isset($pageTitle)    ? htmlspecialchars($pageTitle)    : APP_NAME;
$pageDesc     = isset($pageDesc)     ? htmlspecialchars($pageDesc)     : 'Platform transparansi harga komoditas pangan Indonesia real-time.';
$pageKeywords = isset($pageKeywords) ? htmlspecialchars($pageKeywords) : 'harga komoditas, beras, cabai, minyak goreng, Indonesia';
?>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta http-equiv="X-UA-Compatible" content="IE=edge"/>

<!-- SEO -->
<title><?= $pageTitle === APP_NAME ? APP_NAME : $pageTitle . ' — ' . APP_NAME ?></title>
<meta name="description"  content="<?= $pageDesc ?>"/>
<meta name="keywords"     content="<?= $pageKeywords ?>"/>
<meta name="author"       content="<?= APP_NAME ?>"/>
<meta name="robots"       content="index, follow"/>

<!-- OpenGraph (untuk share di WhatsApp, Facebook, dll) -->
<meta property="og:title"       content="<?= $pageTitle ?> — <?= APP_NAME ?>"/>
<meta property="og:description" content="<?= $pageDesc ?>"/>
<meta property="og:type"        content="website"/>
<meta property="og:locale"      content="id_ID"/>

<!-- Fonts: Cabinet Grotesk (judul) + Instrument Sans (teks biasa) -->
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Cabinet+Grotesk:wght@400;500;600;700;800;900&family=Instrument+Sans:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet"/>

<!-- Tailwind CSS -->
<script src="https://cdn.tailwindcss.com"></script>
<!-- Lucide Icons (ikon SVG yang ringan) -->
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<!-- Chart.js untuk grafik (dimuat di HEAD agar siap sebelum script chart) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!--
  DARK MODE ANTI-FLICKER SCRIPT
  ─────────────────────────────
  Script ini HARUS dijalankan sebelum browser merender halaman.
  Tujuannya: membaca preferensi tema dari localStorage dan langsung
  menambahkan class 'dark' ke <html> SEBELUM halaman tampil,
  sehingga tidak ada "kedipan putih" saat halaman loading.
-->
<script>
(function(){
  var s = localStorage.getItem('ih-theme');
  var p = window.matchMedia('(prefers-color-scheme: dark)').matches;
  if (s === 'dark' || (!s && p)) {
    document.documentElement.classList.add('dark');
    document.documentElement.setAttribute('data-theme','dark');
  } else {
    document.documentElement.classList.remove('dark');
    document.documentElement.setAttribute('data-theme','light');
  }
})();
</script>

<!-- Tailwind Config: dark mode berbasis class, custom fonts & warna -->
<script>
tailwind.config = {
  darkMode: 'class',
  theme: {
    extend: {
      fontFamily: {
        display: ['"Cabinet Grotesk"', 'sans-serif'],
        body:    ['"Instrument Sans"', 'sans-serif'],
      },
      colors: {
        // Brand color (emerald)
        brand: {
          50:'#ecfdf5', 100:'#d1fae5', 200:'#a7f3d0', 300:'#6ee7b7',
          400:'#34d399', 500:'#10b981', 600:'#059669', 700:'#047857',
        }
      },
      animation: {
        'fade-up':  'fadeUp .45s ease both',
        'fade-in':  'fadeIn .3s ease both',
        'ticker':   'ticker 45s linear infinite',
        'pulse-dot':'pulseDot 2s ease-in-out infinite',
      },
      keyframes: {
        fadeUp:   {from:{opacity:'0',transform:'translateY(14px)'},to:{opacity:'1',transform:'translateY(0)'}},
        fadeIn:   {from:{opacity:'0'},to:{opacity:'1'}},
        ticker:   {'0%':{transform:'translateX(0)'},'100%':{transform:'translateX(-50%)'}},
        pulseDot: {'0%,100%':{opacity:'1',transform:'scale(1)'},'50%':{opacity:'.5',transform:'scale(.8)'}},
      }
    }
  }
};
</script>

<!--
  CSS GLOBAL & KOMPONEN REUSABLE
  ──────────────────────────────
  Semua style di sini menggunakan CSS Custom Properties (variables)
  yang otomatis berubah saat tema berganti light/dark.

  Cara kerja dark mode:
  - Light mode: variabel diset ke nilai terang (--bg-primary: #f8fafc)
  - Dark mode:  variabel diset ke nilai gelap  (--bg-primary: #0b0e14)
  - Tailwind class 'dark' di <html> mengaktifkan blok .dark {...}
-->
<style>
  /* ── Font defaults ── */
  body { font-family: 'Instrument Sans', sans-serif; }
  h1,h2,h3,h4,h5,h6,.font-display { font-family: 'Cabinet Grotesk', sans-serif; }

  /* ── CSS Variables: Light Mode ── */
  :root {
    --bg-primary:    #f8fafc;   /* latar utama halaman */
    --bg-secondary:  #f1f5f9;   /* latar section sekunder */
    --bg-card:       #ffffff;   /* latar card / panel */
    --bg-card-hover: #f8fafc;   /* latar card saat hover */
    --border:        rgba(0,0,0,.08);
    --border-hover:  rgba(0,0,0,.15);
    --text-primary:  #0f172a;   /* teks utama */
    --text-secondary:#475569;   /* teks sekunder */
    --text-muted:    #94a3b8;   /* teks abu-abu */
    --surface:       rgba(0,0,0,.04);       /* lapisan tipis */
    --surface-hover: rgba(0,0,0,.07);
    --shadow-sm:     0 1px 3px rgba(0,0,0,.08);
    --shadow-md:     0 4px 16px rgba(0,0,0,.10);
    --shadow-lg:     0 10px 40px rgba(0,0,0,.12);
  }

  /* ── CSS Variables: Dark Mode ── */
  .dark {
    --bg-primary:    #0b0e14;
    --bg-secondary:  #060810;
    --bg-card:       #0f1318;
    --bg-card-hover: #141a22;
    --border:        rgba(255,255,255,.07);
    --border-hover:  rgba(255,255,255,.13);
    --text-primary:  #f1f5f9;
    --text-secondary:#94a3b8;
    --text-muted:    #475569;
    --surface:       rgba(255,255,255,.04);
    --surface-hover: rgba(255,255,255,.07);
    --shadow-sm:     0 1px 3px rgba(0,0,0,.4);
    --shadow-md:     0 4px 16px rgba(0,0,0,.5);
    --shadow-lg:     0 10px 40px rgba(0,0,0,.6);
  }

  /* ── Body base ── */
  body {
    background-color: var(--bg-primary);  /* Pakai background-color bukan background */
    color: var(--text-primary);
    -webkit-font-smoothing: antialiased;
    transition: background-color .25s, color .25s;
    overflow-x: hidden;
  }

  /* ── Custom @keyframes (Tailwind CDN tidak generate keyframes dari config) ── */
  @keyframes ticker   { 0%{transform:translateX(0)} 100%{transform:translateX(-50%)} }
  @keyframes fadeUp   { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
  @keyframes fadeIn   { from{opacity:0} to{opacity:1} }
  @keyframes pulseDot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(.8)} }

  /* ── Custom Scrollbar ── */
  ::-webkit-scrollbar { width:6px; height:6px; }
  ::-webkit-scrollbar-track { background:transparent; }
  ::-webkit-scrollbar-thumb { background:var(--border); border-radius:6px; }
  ::-webkit-scrollbar-thumb:hover { background:var(--border-hover); }

  /* ── KOMPONEN: Card ──
     .card       → card dasar dengan border
     .card-hover → card yang bergerak saat hover */
  .card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 1rem;
    transition: background .2s, border-color .2s, box-shadow .2s;
  }
  .card-hover:hover {
    background: var(--bg-card-hover);
    border-color: var(--border-hover);
    box-shadow: var(--shadow-md);
    transform: translateY(-1px);
  }

  /* ── KOMPONEN: Input Field ──
     Dipakai di form login, register, tambah data, dll.
     .input-icon → tambahkan jika ada ikon di kiri */
  .input-field {
    width:100%; padding:.625rem 1rem; border-radius:.75rem;
    border:1px solid var(--border); background:var(--surface);
    color:var(--text-primary); font-size:.875rem; outline:none;
    transition:border-color .2s, background .2s, box-shadow .2s;
    font-family:'Instrument Sans',sans-serif;
  }
  .input-field:focus {
    border-color:#10b981;
    box-shadow:0 0 0 3px rgba(16,185,129,.12);
    background:var(--bg-card);
  }
  .input-field::placeholder { color:var(--text-muted); }
  .input-icon { padding-left:2.75rem; }

  /* ── KOMPONEN: Badge / Label kecil ──
     Dipakai untuk status, kategori, tipe pengumuman, dll. */
  .badge { display:inline-flex; align-items:center; gap:.25rem; padding:.2rem .65rem; border-radius:999px; font-size:.7rem; font-weight:700; font-family:'Cabinet Grotesk',sans-serif; }
  .badge-green  { background:rgba(16,185,129,.1); color:#10b981; border:1px solid rgba(16,185,129,.2); }
  .badge-red    { background:rgba(239,68,68,.1);  color:#ef4444; border:1px solid rgba(239,68,68,.2); }
  .badge-amber  { background:rgba(245,158,11,.1); color:#f59e0b; border:1px solid rgba(245,158,11,.2); }
  .badge-blue   { background:rgba(59,130,246,.1); color:#3b82f6; border:1px solid rgba(59,130,246,.2); }
  .badge-purple { background:rgba(168,85,247,.1); color:#a855f7; border:1px solid rgba(168,85,247,.2); }
  .badge-slate  { background:var(--surface); color:var(--text-secondary); border:1px solid var(--border); }

  /* ── KOMPONEN: Pesan Error/Sukses ── */
  .msg-error   { background:rgba(239,68,68,.08);  color:#ef4444; border:1px solid rgba(239,68,68,.2);  border-radius:.75rem; padding:.75rem 1rem; font-size:.875rem; }
  .msg-success { background:rgba(16,185,129,.08); color:#10b981; border:1px solid rgba(16,185,129,.2); border-radius:.75rem; padding:.75rem 1rem; font-size:.875rem; }
  .msg-warning { background:rgba(245,158,11,.08); color:#f59e0b; border:1px solid rgba(245,158,11,.2); border-radius:.75rem; padding:.75rem 1rem; font-size:.875rem; }

  /* ── KOMPONEN: Tabel Data ──
     Dipakai di semua halaman yang menampilkan data tabular */
  .data-table { border-collapse:collapse; width:100%; }
  .data-table th { background:var(--surface); color:var(--text-muted); font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; padding:.75rem 1.25rem; text-align:left; border-bottom:1px solid var(--border); }
  .data-table td { padding:.875rem 1.25rem; border-bottom:1px solid var(--border); font-size:.875rem; color:var(--text-secondary); vertical-align:middle; }
  .data-table tbody tr:hover td { background:var(--surface); }
  .data-table tbody tr:last-child td { border-bottom:none; }

  /* ── KOMPONEN: Sidebar Admin ── */
  .sidebar-nav a { display:flex; align-items:center; gap:.75rem; padding:.6rem .875rem; border-radius:.625rem; font-size:.875rem; font-weight:500; color:var(--text-secondary); transition:background .15s,color .15s; text-decoration:none; }
  .sidebar-nav a:hover { background:var(--surface); color:var(--text-primary); }
  .sidebar-nav a.active { background:rgba(16,185,129,.1); color:#10b981; }
  .sidebar-nav .nav-section { padding:.5rem .875rem .25rem; font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--text-muted); margin-top:.5rem; }

  /* ── Ticker (live harga) ── */
  .ticker-track { animation: ticker 45s linear infinite; }
  .ticker-track:hover { animation-play-state:paused; }

  /* ── Efek Glow ── */
  .text-glow { text-shadow:0 0 40px rgba(16,185,129,.35); }

  /* ── Noise texture untuk hero ── */
  .noise-bg::before {
    content:''; position:absolute; inset:0; opacity:.025; pointer-events:none; z-index:0;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
  }

  /* ── Stagger animation delays ── */
  .delay-1{animation-delay:.08s}.delay-2{animation-delay:.16s}.delay-3{animation-delay:.24s}.delay-4{animation-delay:.32s}

  /* ── Focus ring ── */
  *:focus-visible { outline:2px solid #10b981; outline-offset:2px; border-radius:4px; }

  /* ── Scrollbar tipis untuk sidebar ── */
  .slim-scroll { scrollbar-width:thin; scrollbar-color:var(--border) transparent; }
  .slim-scroll::-webkit-scrollbar { width:3px; }
  .slim-scroll::-webkit-scrollbar-thumb { background:var(--border); border-radius:4px; }
</style>
