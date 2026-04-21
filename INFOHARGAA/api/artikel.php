<?php
/**
 * artikel.php — Halaman Baca Artikel
 * ─────────────────────────────────────────────────────────────
 * Menerima parameter: ?slug=nama-slug-artikel
 * Menampilkan konten artikel lengkap kepada pembaca.
 * Setiap kunjungan ke halaman ini menaikkan kolom 'views'.
 * ─────────────────────────────────────────────────────────────
 */
session_start();
require 'Server/koneksi.php';

// Ambil slug dari URL, sanitasi
$slug = esc($conn, $_GET['slug'] ?? '');

if (!$slug) {
    redirect('index.php');
}

// Handle artikel BPS: slug kosong → redirect ke halaman BPS
// (artikel BPS tidak disimpan di DB, hanya virtual dari API)
if (empty($slug)) redirect('index.php');

// Cari artikel di database
$res = $conn->query("SELECT a.*, u.username AS penulis FROM artikel a LEFT JOIN users u ON a.penulis_id=u.id WHERE a.slug='$slug' AND a.is_publish=1 LIMIT 1");

if (!$res || $res->num_rows === 0) {
    // Artikel tidak ditemukan → redirect ke index dengan pesan
    redirect('index.php');
}

$artikel = $res->fetch_assoc();

// Tambah hitungan views
$conn->query("UPDATE artikel SET views = views + 1 WHERE id=" . (int)$artikel['id']);

// Ambil artikel terkait (kategori sama, bukan artikel ini)
$kat     = esc($conn, $artikel['kategori']);
$resRel  = $conn->query("SELECT id, judul, slug, emoji, ringkasan, menit_baca FROM artikel WHERE kategori='$kat' AND slug!='$slug' AND is_publish=1 ORDER BY created_at DESC LIMIT 3");
$related = [];
if ($resRel) while ($r = $resRel->fetch_assoc()) $related[] = $r;

// SEO
$pageTitle    = $artikel['judul'];
$pageDesc     = $artikel['ringkasan'] ?: mb_substr(strip_tags($artikel['konten'] ?? ''), 0, 160);
$pageKeywords = $artikel['kategori'] . ', komoditas, harga pangan Indonesia';
$activeNav    = 'artikel';
?>
<!doctype html>
<html lang="id" class="scroll-smooth">
<head>
  <?php include 'Assets/head.php'; ?>
  <style>
    /* Prose styling untuk konten artikel */
    .prose p   { margin-bottom: 1.25rem; line-height: 1.8; color: var(--text-secondary); }
    .prose h2  { font-family:'Cabinet Grotesk',sans-serif; font-weight:800; font-size:1.35rem; color:var(--text-primary); margin:2rem 0 .75rem; }
    .prose h3  { font-family:'Cabinet Grotesk',sans-serif; font-weight:700; font-size:1.1rem; color:var(--text-primary); margin:1.5rem 0 .5rem; }
    .prose ul  { list-style:disc; padding-left:1.5rem; margin-bottom:1.25rem; color:var(--text-secondary); }
    .prose ul li { margin-bottom:.4rem; line-height:1.7; }
    .prose ol  { list-style:decimal; padding-left:1.5rem; margin-bottom:1.25rem; color:var(--text-secondary); }
    .prose blockquote { border-left:3px solid #10b981; padding:.75rem 1.25rem; background:rgba(16,185,129,.06); border-radius:0 .5rem .5rem 0; margin:1.5rem 0; font-style:italic; color:var(--text-secondary); }
    .prose strong { color:var(--text-primary); font-weight:700; }
    .prose a    { color:#10b981; text-decoration:underline; }
    .prose a:hover { color:#059669; }
  </style>
</head>
<body>

<!-- Ticker placeholder -->
<div class="h-9 bg-[var(--bg-secondary)] border-b border-[var(--border)]">
  <div class="max-w-screen-xl mx-auto px-4 h-full flex items-center">
    <span class="text-xs text-[var(--text-muted)] flex items-center gap-2">
      <span class="w-1.5 h-1.5 bg-brand-500 rounded-full" style="animation:pulseDot 2s ease-in-out infinite"></span>
      InfoHarga Komoditi — Data Harga Pangan Real-time
    </span>
  </div>
</div>

<?php include 'Assets/navbar.php'; ?>

<!-- BREADCRUMB + HEADER -->
<div class="pt-28 pb-8 bg-[var(--bg-secondary)] border-b border-[var(--border)]">
  <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
    <nav class="flex items-center gap-1.5 text-xs text-[var(--text-muted)] mb-4" aria-label="Breadcrumb">
      <a href="index.php" class="hover:text-brand-500 transition">Beranda</a>
      <i data-lucide="chevron-right" class="w-3 h-3"></i>
      <a href="index.php#artikel" class="hover:text-brand-500 transition">Artikel</a>
      <i data-lucide="chevron-right" class="w-3 h-3"></i>
      <span class="text-brand-500"><?= htmlspecialchars($artikel['kategori']) ?></span>
    </nav>

    <div class="max-w-3xl">
      <!-- Badge kategori & emoji -->
      <div class="flex items-center gap-3 mb-4">
        <span class="text-3xl"><?= htmlspecialchars($artikel['emoji']) ?></span>
        <span class="badge badge-green text-xs"><?= htmlspecialchars($artikel['kategori']) ?></span>
      </div>

      <!-- Judul -->
      <h1 class="font-display font-black text-3xl md:text-4xl text-[var(--text-primary)] leading-tight mb-4">
        <?= htmlspecialchars($artikel['judul']) ?>
      </h1>

      <!-- Meta info -->
      <div class="flex flex-wrap items-center gap-4 text-sm text-[var(--text-muted)]">
        <?php if ($artikel['penulis']): ?>
        <span class="flex items-center gap-1.5">
          <i data-lucide="user" class="w-3.5 h-3.5"></i>
          <?= htmlspecialchars($artikel['penulis']) ?>
        </span>
        <?php endif; ?>
        <span class="flex items-center gap-1.5">
          <i data-lucide="clock" class="w-3.5 h-3.5"></i>
          <?= (int)$artikel['menit_baca'] ?> menit baca
        </span>
        <span class="flex items-center gap-1.5">
          <i data-lucide="eye" class="w-3.5 h-3.5"></i>
          <?= number_format((int)$artikel['views']) ?> views
        </span>
        <span class="flex items-center gap-1.5">
          <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
          <?= date('d F Y', strtotime($artikel['created_at'])) ?>
        </span>
        <?php if ($artikel['sumber_url']): ?>
        <a href="<?= htmlspecialchars($artikel['sumber_url']) ?>" target="_blank" rel="noopener"
           class="flex items-center gap-1.5 text-brand-500 hover:text-brand-400 transition">
          <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
          Sumber: <?= htmlspecialchars($artikel['sumber_nama'] ?: 'Baca asli') ?>
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- MAIN CONTENT -->
<div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
  <div class="flex flex-col lg:flex-row gap-10">

    <!-- KONTEN ARTIKEL -->
    <article class="flex-1 min-w-0 max-w-3xl">

      <!-- Ringkasan / lead -->
      <?php if ($artikel['ringkasan']): ?>
      <div class="card p-5 mb-8 border-brand-500/20 bg-brand-500/4">
        <p class="text-[var(--text-secondary)] leading-relaxed font-medium">
          <?= htmlspecialchars($artikel['ringkasan']) ?>
        </p>
      </div>
      <?php endif; ?>

      <!-- Konten lengkap -->
      <?php if ($artikel['konten']): ?>
      <div class="prose max-w-none">
        <?= nl2br(htmlspecialchars($artikel['konten'])) ?>
      </div>
      <?php else: ?>
      <div class="card p-10 text-center text-[var(--text-muted)]">
        <i data-lucide="file-text" class="w-10 h-10 mx-auto mb-3 opacity-30"></i>
        <p class="text-sm">Konten artikel ini belum tersedia secara lengkap.</p>
        <?php if ($artikel['sumber_url']): ?>
        <a href="<?= htmlspecialchars($artikel['sumber_url']) ?>" target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 mt-4 px-5 py-2.5 bg-brand-600 hover:bg-brand-500 text-white rounded-xl text-sm font-bold transition">
          <i data-lucide="external-link" class="w-4 h-4"></i> Baca di Sumber Asli
        </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Tombol share -->
      <div class="mt-8 pt-6 border-t border-[var(--border)]">
        <p class="text-sm font-bold text-[var(--text-muted)] mb-3 uppercase tracking-wider">Bagikan Artikel</p>
        <div class="flex gap-2 flex-wrap">
          <a href="https://wa.me/?text=<?= urlencode($artikel['judul'].' - '.($artikel['sumber_url']?:('http://localhost/artikel.php?slug='.$artikel['slug']))) ?>"
             target="_blank" rel="noopener"
             class="flex items-center gap-2 px-4 py-2 rounded-lg bg-green-500/10 border border-green-500/20 text-green-400 text-sm font-semibold hover:bg-green-500/15 transition">
            <i data-lucide="message-circle" class="w-4 h-4"></i> WhatsApp
          </a>
          <a href="https://twitter.com/intent/tweet?text=<?= urlencode($artikel['judul']) ?>&url=<?= urlencode('http://localhost/artikel.php?slug='.$artikel['slug']) ?>"
             target="_blank" rel="noopener"
             class="flex items-center gap-2 px-4 py-2 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-[var(--text-secondary)] text-sm font-semibold hover:bg-[var(--surface-hover)] transition">
            <i data-lucide="share-2" class="w-4 h-4"></i> Share
          </a>
          <a href="index.php#artikel"
             class="flex items-center gap-2 px-4 py-2 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-[var(--text-secondary)] text-sm font-semibold hover:bg-[var(--surface-hover)] transition">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Semua Artikel
          </a>
        </div>
      </div>
    </article>

    <!-- SIDEBAR -->
    <aside class="lg:w-72 xl:w-80 flex-shrink-0">

      <!-- Artikel terkait -->
      <?php if (!empty($related)): ?>
      <div class="card p-5 mb-5">
        <h3 class="font-display font-bold text-[var(--text-primary)] text-sm mb-4 flex items-center gap-2">
          <i data-lucide="layers" class="w-4 h-4 text-brand-500"></i>
          Artikel Terkait
        </h3>
        <div class="space-y-3">
          <?php foreach ($related as $r): ?>
          <a href="artikel.php?slug=<?= urlencode($r['slug']) ?>"
             class="flex items-start gap-3 group">
            <span class="text-xl flex-shrink-0"><?= htmlspecialchars($r['emoji']) ?></span>
            <div>
              <p class="text-sm font-semibold text-[var(--text-primary)] group-hover:text-brand-500 transition-colors leading-snug">
                <?= htmlspecialchars($r['judul']) ?>
              </p>
              <span class="text-[10px] text-[var(--text-muted)] flex items-center gap-1 mt-0.5">
                <i data-lucide="clock" class="w-2.5 h-2.5"></i>
                <?= (int)$r['menit_baca'] ?> menit
              </span>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- CTA cek harga -->
      <div class="card p-5 border-brand-500/15 bg-brand-500/5">
        <i data-lucide="trending-up" class="w-8 h-8 text-brand-500 mb-3"></i>
        <h3 class="font-display font-bold text-[var(--text-primary)] mb-2">Pantau Harga Real-time</h3>
        <p class="text-xs text-[var(--text-muted)] mb-4 leading-relaxed">
          Lihat grafik pergerakan harga komoditas dari 38 provinsi Indonesia.
        </p>
        <a href="<?= isset($_SESSION['login']) ? 'chart.php' : 'login.php' ?>"
           class="flex items-center gap-2 px-4 py-2.5 bg-brand-600 hover:bg-brand-500 text-white text-sm font-bold rounded-xl transition w-full justify-center font-display">
          <i data-lucide="bar-chart-2" class="w-4 h-4"></i> Lihat Grafik
        </a>
      </div>
    </aside>

  </div>
</div>

<?php include 'Assets/footer.php'; ?>
<script src="Assets/scripts.js"></script>
<script>lucide.createIcons();</script>

<!-- Structured data artikel -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Article",
  "headline": "<?= addslashes(htmlspecialchars($artikel['judul'])) ?>",
  "description": "<?= addslashes(htmlspecialchars($pageDesc)) ?>",
  "author": { "@type": "Person", "name": "<?= addslashes(htmlspecialchars($artikel['penulis']??APP_NAME)) ?>" },
  "datePublished": "<?= date('Y-m-d', strtotime($artikel['created_at'])) ?>",
  "publisher": { "@type": "Organization", "name": "InfoHarga Komoditi" }
}
</script>
</body>
</html>
