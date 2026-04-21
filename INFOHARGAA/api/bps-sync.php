<?php
/**
 * bps-sync.php — Halaman Sinkronisasi Data dari API BPS
 * ─────────────────────────────────────────────────────────────
 * Hanya admin_master yang bisa akses.
 *
 * FITUR:
 * 1. Tampilkan daftar 38 provinsi dari BPS API (live)
 * 2. Sync data harga komoditas per provinsi ke database
 * 3. Preview data sebelum disimpan
 * 4. Log hasil sinkronisasi
 * ─────────────────────────────────────────────────────────────
 */
session_start();
if (!isset($_SESSION['login'])) { header("Location: login.php"); exit; }
if ($_SESSION['role'] !== 'admin_master') { header("Location: dashboard.php"); exit; }

require 'Server/koneksi.php';
require_once 'Server/bps_api.php';

$pageTitle = 'Sinkronisasi Data BPS';
$bps       = new BPS_API(BPS_API_KEY);
$myId      = (int)$_SESSION['user_id'];

$provinces = [];
$preview   = [];
$syncResult = null;
$error     = '';

// ── LOAD DAFTAR PROVINSI DARI BPS ────────────────────────────
$provinces = $bps->getProvinces();
if (empty($provinces)) {
    // Fallback: gunakan data hardcoded jika API tidak merespons
    $provinces = array_map(fn($name) => [
        'domain_id'   => BPS_API::getDomainIdByProvinsi($name),
        'domain_name' => $name,
        'domain_url'  => '',
    ], array_keys(PROVINSI_KOTA));
}

// ── PREVIEW DATA DARI BPS ─────────────────────────────────────
if (isset($_GET['preview'])) {
    $domainId    = htmlspecialchars($_GET['preview']);
    $provinsiName= htmlspecialchars($_GET['provinsi'] ?? '');
    $preview     = $bps->getKomoditasHarga($domainId);

    // Jika API tidak merespons, tampilkan error ramah
    if (empty($preview)) {
        $error = "Tidak ada data dari BPS untuk domain $domainId. "
               . "Pastikan koneksi internet tersedia dan API key valid.";
    }
}

// ── SINKRONISASI KE DATABASE ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync'])) {
    $domainId    = esc($conn, $_POST['domain_id']);
    $provinsiName= esc($conn, $_POST['provinsi_name']);
    $lokasiName  = esc($conn, $_POST['lokasi_name']  ?? $provinsiName);

    $syncResult = $bps->syncKomoditasToDB($conn, $domainId, $provinsiName, $lokasiName);

    // Log ke tabel pengaturan_sistem
    $logMsg = esc($conn, "Sync BPS {$provinsiName} ({$domainId}): {$syncResult['inserted']} inserted, {$syncResult['updated']} updated — " . date('Y-m-d H:i:s'));
    $conn->query("INSERT INTO pengaturan_sistem (kunci, nilai, label, kelompok, tipe)
                  VALUES ('bps_last_sync_" . time() . "','$logMsg','Log Sinkronisasi BPS','Log BPS','text')
                  ON DUPLICATE KEY UPDATE nilai='$logMsg'");
}

// ── SYNC SEMUA SEKALIGUS ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_all'])) {
    $totalIns = 0; $totalUpd = 0;
    // Sync hanya beberapa provinsi utama agar tidak timeout
    $mainDomains = [
        ['0000','Nasional','Indonesia'],
        ['3100','DKI Jakarta','Jakarta Pusat'],
        ['3200','Jawa Barat','Kota Bandung'],
        ['3300','Jawa Tengah','Kota Semarang'],
        ['3500','Jawa Timur','Kota Surabaya'],
        ['3600','Banten','Kota Serang'],
        ['5100','Bali','Kota Denpasar'],
        ['1200','Sumatera Utara','Kota Medan'],
        ['7300','Sulawesi Selatan','Kota Makassar'],
    ];
    $allResults = [];
    foreach ($mainDomains as [$did, $prov, $lok]) {
        $r = $bps->syncKomoditasToDB($conn, $did, $prov, $lok);
        $totalIns += $r['inserted'];
        $totalUpd += $r['updated'];
        $allResults[] = "✓ {$prov}: {$r['inserted']} baru, {$r['updated']} update";
    }
    $syncResult = [
        'inserted' => $totalIns,
        'updated'  => $totalUpd,
        'total'    => $totalIns + $totalUpd,
        'detail'   => $allResults,
        'errors'   => [],
    ];
}
?>
<!doctype html>
<html lang="id">
<head><?php include 'Assets/head.php'; ?>
<style>
  body{font-family:'Instrument Sans',sans-serif;}
  .prov-card{transition:border-color .15s,background .15s;}
  .prov-card:hover{border-color:rgba(16,185,129,.3);background:var(--bg-card-hover);}
  .status-dot-ok{width:8px;height:8px;border-radius:50%;background:#10b981;display:inline-block;}
  .status-dot-no{width:8px;height:8px;border-radius:50%;background:#64748b;display:inline-block;}
</style>
</head>
<body class="bg-[var(--bg-primary)] min-h-screen">

<!-- Back bar -->
<div class="h-12 bg-[var(--bg-secondary)] border-b border-[var(--border)] flex items-center px-6 gap-4">
  <a href="dashboard-master.php?tab=bps" class="flex items-center gap-1.5 text-sm text-[var(--text-muted)] hover:text-[var(--text-primary)] transition">
    <i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali ke Master Panel
  </a>
  <span class="text-[var(--border)]">|</span>
  <h1 class="font-display font-black text-sm text-[var(--text-primary)]">
    🔄 Sinkronisasi Data Komoditas dari BPS
  </h1>
  <div class="ml-auto">
    <button data-action="toggle-theme" class="w-8 h-8 flex items-center justify-center rounded-lg bg-[var(--surface)] border border-[var(--border)] text-[var(--text-muted)] hover:text-[var(--text-primary)] transition">
      <i data-lucide="moon" data-theme-icon="toggle" class="w-3.5 h-3.5"></i>
    </button>
  </div>
</div>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

  <!-- Header info -->
  <div class="card p-5 mb-8 border-brand-500/20 bg-brand-500/4">
    <div class="flex items-start gap-4">
      <div class="w-10 h-10 bg-brand-500/15 rounded-xl flex items-center justify-center flex-shrink-0">
        <i data-lucide="database" class="w-5 h-5 text-brand-500"></i>
      </div>
      <div class="flex-1">
        <h2 class="font-display font-bold text-[var(--text-primary)] mb-1">
          Web API BPS — Harga Eceran Rata-Rata Komoditas (var=2310)
        </h2>
        <p class="text-sm text-[var(--text-secondary)] leading-relaxed mb-3">
          Data ini bersumber langsung dari Badan Pusat Statistik Indonesia.
          Variabel <code class="bg-[var(--surface)] px-1.5 py-0.5 rounded text-xs">2310</code>
          berisi harga eceran rata-rata komoditas pangan tahun 2024 (kode BPS: th=126).
        </p>
        <div class="flex flex-wrap gap-2 text-xs">
          <span class="px-2.5 py-1 rounded-lg bg-[var(--surface)] border border-[var(--border)] font-mono text-[var(--text-muted)]">
            API Key: <?= substr(BPS_API_KEY,0,8) ?>...
          </span>
          <a href="<?= $bps->buildApiUrl('province') ?>" target="_blank" rel="noopener"
             class="flex items-center gap-1 px-2.5 py-1 rounded-lg bg-blue-500/10 border border-blue-500/20 text-blue-400 hover:bg-blue-500/15 transition">
            <i data-lucide="external-link" class="w-3 h-3"></i> API Provinsi
          </a>
          <a href="<?= $bps->buildApiUrl('komoditas','0000') ?>" target="_blank" rel="noopener"
             class="flex items-center gap-1 px-2.5 py-1 rounded-lg bg-brand-500/10 border border-brand-500/20 text-brand-500 hover:bg-brand-500/15 transition">
            <i data-lucide="external-link" class="w-3 h-3"></i> API Komoditas (Nasional)
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Sync All button -->
  <div class="card p-5 mb-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
    <div>
      <h3 class="font-display font-bold text-[var(--text-primary)] mb-1">Sinkronisasi Massal</h3>
      <p class="text-xs text-[var(--text-muted)]">Sync otomatis 9 provinsi utama: Nasional, DKI Jakarta, Jawa Barat, Jawa Tengah, Jawa Timur, Banten, Bali, Sumatera Utara, Sulawesi Selatan.</p>
    </div>
    <form method="POST">
      <button type="submit" name="sync_all"
              onclick="return confirm('Sinkronisasi massal dari BPS?\nIni akan memakan waktu 30-60 detik.')"
              class="flex items-center gap-2 px-5 py-2.5 bg-brand-600 hover:bg-brand-500 text-white font-bold rounded-xl text-sm transition shadow shadow-brand-600/20 font-display whitespace-nowrap">
        <i data-lucide="refresh-cw" class="w-4 h-4"></i> Sync 9 Provinsi Utama
      </button>
    </form>
  </div>

  <!-- Sync result -->
  <?php if ($syncResult): ?>
  <div class="card p-5 mb-6 border-brand-500/20 bg-brand-500/4">
    <div class="flex items-center gap-3 mb-3">
      <i data-lucide="check-circle" class="w-5 h-5 text-brand-500 flex-shrink-0"></i>
      <h3 class="font-display font-bold text-[var(--text-primary)]">Sinkronisasi Selesai!</h3>
    </div>
    <div class="grid grid-cols-3 gap-4 mb-4">
      <div class="p-3 rounded-xl bg-brand-500/8 border border-brand-500/20 text-center">
        <div class="font-display font-black text-2xl text-brand-500"><?= $syncResult['inserted'] ?></div>
        <div class="text-xs text-[var(--text-muted)]">Data Baru</div>
      </div>
      <div class="p-3 rounded-xl bg-blue-500/8 border border-blue-500/20 text-center">
        <div class="font-display font-black text-2xl text-blue-400"><?= $syncResult['updated'] ?></div>
        <div class="text-xs text-[var(--text-muted)]">Data Diperbarui</div>
      </div>
      <div class="p-3 rounded-xl bg-[var(--surface)] border border-[var(--border)] text-center">
        <div class="font-display font-black text-2xl text-[var(--text-primary)]"><?= $syncResult['total'] ?></div>
        <div class="text-xs text-[var(--text-muted)]">Total Diproses</div>
      </div>
    </div>
    <?php if (!empty($syncResult['detail'])): ?>
    <div class="space-y-1">
      <?php foreach ($syncResult['detail'] as $d): ?>
      <div class="text-xs text-[var(--text-secondary)] flex items-center gap-1.5">
        <span class="status-dot-ok"></span> <?= htmlspecialchars($d) ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($syncResult['errors'])): ?>
    <div class="mt-3 text-xs text-red-400">
      Error: <?= implode(', ', array_map('htmlspecialchars', $syncResult['errors'])) ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Error -->
  <?php if ($error): ?>
  <div class="msg-error mb-6 flex items-center gap-3">
    <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
    <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <!-- Preview data -->
  <?php if (!empty($preview) && isset($_GET['preview'])): ?>
  <div class="card overflow-hidden mb-8">
    <div class="px-5 py-4 border-b border-[var(--border)] flex items-center justify-between">
      <h3 class="font-display font-bold text-[var(--text-primary)] flex items-center gap-2">
        <i data-lucide="eye" class="w-4 h-4 text-brand-500"></i>
        Preview: <?= count($preview) ?> komoditas dari <strong><?= htmlspecialchars($_GET['provinsi']??'') ?></strong>
      </h3>
      <!-- Form Sync untuk provinsi ini -->
      <form method="POST" class="flex items-center gap-2">
        <input type="hidden" name="domain_id"     value="<?= htmlspecialchars($_GET['preview']) ?>"/>
        <input type="hidden" name="provinsi_name" value="<?= htmlspecialchars($_GET['provinsi']??'') ?>"/>
        <input type="hidden" name="lokasi_name"   value="<?= htmlspecialchars($_GET['provinsi']??'') ?>"/>
        <button type="submit" name="sync"
                class="flex items-center gap-2 px-4 py-2 bg-brand-600 hover:bg-brand-500 text-white font-bold rounded-lg text-sm transition font-display">
          <i data-lucide="download" class="w-3.5 h-3.5"></i> Simpan ke Database
        </button>
      </form>
    </div>
    <div class="overflow-x-auto">
      <table class="data-table">
        <thead><tr><th>Nama Komoditas</th><th>Kategori</th><th>Harga (Rp)</th><th>Satuan</th></tr></thead>
        <tbody>
          <?php foreach ($preview as $item): ?>
          <tr>
            <td class="font-semibold text-[var(--text-primary)]"><?= htmlspecialchars($item['nama']) ?></td>
            <td><span class="badge badge-slate text-[10px]"><?= htmlspecialchars($item['kategori']) ?></span></td>
            <td class="font-display font-bold text-brand-500"><?= rupiah($item['harga']) ?></td>
            <td class="text-[var(--text-muted)] text-xs"><?= htmlspecialchars($item['satuan']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- Daftar provinsi -->
  <div>
    <h3 class="font-display font-bold text-[var(--text-primary)] mb-4 flex items-center gap-2">
      <i data-lucide="map" class="w-4 h-4 text-brand-500"></i>
      Daftar Provinsi (<?= count($provinces) ?> domain BPS)
    </h3>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
      <!-- Tambah Nasional/Pusat -->
      <div class="card prov-card p-4 flex items-center justify-between gap-3">
        <div>
          <div class="font-bold text-[var(--text-primary)] text-sm">🏛️ Nasional (Pusat)</div>
          <div class="text-[10px] text-[var(--text-muted)] mt-0.5">domain_id: 0000</div>
        </div>
        <div class="flex gap-2">
          <a href="?preview=0000&provinsi=Nasional"
             class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-[10px] font-bold bg-blue-500/10 border border-blue-500/20 text-blue-400 hover:bg-blue-500/15 transition">
            <i data-lucide="eye" class="w-3 h-3"></i> Preview
          </a>
          <form method="POST">
            <input type="hidden" name="domain_id"     value="0000"/>
            <input type="hidden" name="provinsi_name" value="Nasional"/>
            <input type="hidden" name="lokasi_name"   value="Indonesia"/>
            <button name="sync" class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-[10px] font-bold bg-brand-500/10 border border-brand-500/20 text-brand-500 hover:bg-brand-500/15 transition">
              <i data-lucide="download" class="w-3 h-3"></i> Sync
            </button>
          </form>
        </div>
      </div>

      <?php foreach ($provinces as $prov):
        $did  = $prov['domain_id']   ?? '';
        $name = $prov['domain_name'] ?? '';
        if (!$did || !$name) continue;
        $isPreviewing = (($_GET['preview'] ?? '') === $did);
      ?>
      <div class="card prov-card p-4 flex items-center justify-between gap-3 <?= $isPreviewing?'border-brand-500/30 bg-brand-500/4':'' ?>">
        <div>
          <div class="font-bold text-[var(--text-primary)] text-sm"><?= htmlspecialchars($name) ?></div>
          <div class="text-[10px] text-[var(--text-muted)] mt-0.5">domain_id: <?= htmlspecialchars($did) ?></div>
        </div>
        <div class="flex gap-2 flex-shrink-0">
          <a href="?preview=<?= urlencode($did) ?>&provinsi=<?= urlencode($name) ?>"
             class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-[10px] font-bold
                    bg-blue-500/10 border border-blue-500/20 text-blue-400 hover:bg-blue-500/15 transition">
            <i data-lucide="eye" class="w-3 h-3"></i> Preview
          </a>
          <form method="POST">
            <input type="hidden" name="domain_id"     value="<?= htmlspecialchars($did) ?>"/>
            <input type="hidden" name="provinsi_name" value="<?= htmlspecialchars($name) ?>"/>
            <input type="hidden" name="lokasi_name"   value="<?= htmlspecialchars($name) ?>"/>
            <button name="sync"
                    class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-[10px] font-bold
                           bg-brand-500/10 border border-brand-500/20 text-brand-500 hover:bg-brand-500/15 transition">
              <i data-lucide="download" class="w-3 h-3"></i> Sync
            </button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</div><!-- end container -->

<script src="Assets/scripts.js"></script>
<script>lucide.createIcons();</script>
</body>
</html>
