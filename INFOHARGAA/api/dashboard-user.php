<?php
/**
 * dashboard-user.php — Dashboard Pengguna (user & kontributor)
 * ─────────────────────────────────────────────────────────────
 * TAB YANG TERSEDIA:
 *   beranda   → Ringkasan + pengumuman + komoditas terpantau
 *   grafik    → Grafik harga interaktif
 *   artikel   → Daftar artikel edukasi
 *   laporan   → Form + riwayat laporan (khusus kontributor)
 *   info      → Info SMS, email, kontak dari admin
 * ─────────────────────────────────────────────────────────────
 */
session_start();
require 'Server/koneksi.php';
cekLogin();
cekRole(['user','kontributor']);

$uid       = (int)$_SESSION['user_id'];
$username  = htmlspecialchars($_SESSION['username']);
$role      = $_SESSION['role'];
$isKontrib = $role === 'kontributor';
$activeTab = $_GET['tab'] ?? 'beranda';

// ── DATA QUERY ────────────────────────────────────────────────

// Pengumuman aktif
$pengumumans = getPengumuman($conn);

// Komoditas approved (untuk grafik & beranda)
$resKom = $conn->query("SELECT * FROM komoditas WHERE status='approved' ORDER BY nama ASC");
$komoditas = []; $namaList = [];
while($r=$resKom->fetch_assoc()){ $komoditas[]=$r; $namaList[]=$r['nama']; }
$namaList = array_unique($namaList);

// Statistik cepat untuk beranda
$totalApproved = count($komoditas);
$naik  = count(array_filter($komoditas, fn($r)=>(int)$r['harga_sekarang']>(int)$r['harga_kemarin']));
$turun = count(array_filter($komoditas, fn($r)=>(int)$r['harga_sekarang']<(int)$r['harga_kemarin']));

// Artikel published
$resArt = $conn->query("SELECT * FROM artikel WHERE is_publish=1 ORDER BY created_at DESC LIMIT 12");
$artikels=[]; while($a=$resArt->fetch_assoc()) $artikels[]=$a;

// Laporan user ini (jika kontributor)
$laporan=[];
if ($isKontrib) {
    $res = $conn->query("SELECT * FROM komoditas WHERE submitted_by=$uid ORDER BY updated_at DESC");
    while($r=$res->fetch_assoc()) $laporan[]=$r;
}
$lTotal    = count($laporan);
$lApproved = count(array_filter($laporan,fn($r)=>$r['status']==='approved'));
$lPending  = count(array_filter($laporan,fn($r)=>$r['status']==='pending'));
$lRejected = count(array_filter($laporan,fn($r)=>$r['status']==='rejected'));

// Info sistem dari pengaturan
$infoSMS     = getSetting($conn,'sms_info','');
$infoEmail   = getSetting($conn,'email_admin','');
$infoTelp    = getSetting($conn,'telepon_admin','');
$infoWA      = getSetting($conn,'whatsapp_admin','');
$namaSitus   = getSetting($conn,'nama_situs',APP_NAME);

// Data grafik untuk tab grafik
$selProv = in_array($_GET['provinsi']??'', PROVINSI_LIST) ? $_GET['provinsi'] : '';
$selKota = esc($conn, trim($_GET['kota'] ?? ''));   // Kota/Kabupaten dari dropdown kota
// Default: pakai komoditas pertama jika tab grafik dibuka tanpa parameter
$defaultKom = !empty($namaList) ? $namaList[0] : '';
$selKom  = trim(esc($conn, $_GET['komoditas'] ?? ($activeTab==='grafik' ? $defaultKom : '')));

$chartData = null; $chartAll = [];

if ($selKom) {
    $k = esc($conn, $selKom);

    if ($selKota) {
        // Kota spesifik dipilih → cari berdasarkan nama lokasi atau kota
        $r = $conn->query("SELECT * FROM komoditas WHERE status='approved' AND nama='$k'
                           AND (lokasi LIKE '%{$selKota}%' OR lokasi='$selKota') LIMIT 1");
        if ($r && $r->num_rows > 0) $chartData = $r->fetch_assoc();
        // Fallback: cari berdasarkan provinsi jika kota tidak cocok
        if (!$chartData && $selProv) {
            $p = esc($conn, $selProv);
            $r2 = $conn->query("SELECT * FROM komoditas WHERE status='approved' AND nama='$k' AND provinsi='$p' LIMIT 1");
            if ($r2 && $r2->num_rows > 0) $chartData = $r2->fetch_assoc();
        }
    } elseif ($selProv) {
        // Hanya provinsi dipilih → cari di provinsi itu
        $p = esc($conn, $selProv);
        $r = $conn->query("SELECT * FROM komoditas WHERE status='approved' AND nama='$k' AND provinsi='$p' LIMIT 1");
        if ($r && $r->num_rows > 0) $chartData = $r->fetch_assoc();
    }

    // Selalu ambil semua lokasi untuk tabel perbandingan
    $whereExtra = $selProv ? " AND provinsi='".esc($conn,$selProv)."'" : '';
    $res = $conn->query("SELECT * FROM komoditas WHERE status='approved' AND nama='$k'{$whereExtra} ORDER BY provinsi ASC, lokasi ASC");
    if ($res) while ($r = $res->fetch_assoc()) $chartAll[] = $r;

    // Jika tidak ada chartData spesifik, gunakan baris pertama dari chartAll
    if (!$chartData && !empty($chartAll)) $chartData = $chartAll[0];
}

$kategoris  = ['Beras & Serealia','Hortikultura','Bumbu & Rempah','Peternakan','Minyak & Lemak','Perikanan','Lainnya'];
$satuans    = ['kg','gram','liter','ml','butir','ikat','buah'];
$pageTitle  = 'Dashboard';
?>
<!doctype html>
<html lang="id">
<head><?php include 'Assets/head.php'; ?>
<style>
  .dash-wrap   { display:flex; height:100vh; }
  .dash-sidebar{ width:220px; flex-shrink:0; display:flex; flex-direction:column; height:100%; background:var(--bg-secondary); border-right:1px solid var(--border); }
  .dash-main   { flex:1; display:flex; flex-direction:column; height:100%; overflow:hidden; }
  .dash-body   { flex:1; overflow-y:auto; padding:1.5rem; }
  .dash-body::-webkit-scrollbar { width:4px; }
  .dash-body::-webkit-scrollbar-thumb { background:var(--border); border-radius:4px; }
  #userChartWrapper { position:relative; width:100%; height:280px; }
  .pengumuman-info     { border-left:3px solid #3b82f6; background:rgba(59,130,246,.06); }
  .pengumuman-peringatan{ border-left:3px solid #f59e0b; background:rgba(245,158,11,.06); }
  .pengumuman-darurat  { border-left:3px solid #ef4444; background:rgba(239,68,68,.06); }
</style>
</head>
<body>
<div class="dash-wrap">

<!-- ══ SIDEBAR USER ══════════════════════════════════════ -->
<aside class="dash-sidebar">
  <div class="h-16 flex items-center px-5 border-b border-[var(--border)] flex-shrink-0">
    <a href="index.php" class="flex items-center gap-2">
      <div class="w-7 h-7 bg-brand-500 rounded-lg flex items-center justify-center shadow shadow-brand-500/30">
        <i data-lucide="trending-up" class="w-3.5 h-3.5 text-white"></i>
      </div>
      <span class="font-display font-black text-sm text-[var(--text-primary)]">InfoHarga<span class="text-brand-500">User</span></span>
    </a>
  </div>

  <nav class="flex-1 py-4 px-3 space-y-0.5 sidebar-nav slim-scroll overflow-y-auto">
    <div class="nav-section">Dashboard</div>
    <a href="?tab=beranda"  class="<?= $activeTab==='beranda'?'active':'' ?>"><i data-lucide="layout-dashboard" class="w-4 h-4"></i> Home</a>
    <a href="chart.php"><i data-lucide="bar-chart-2" class="w-4 h-4"></i> Grafik Harga</a>
    <a href="index.php#artikel"><i data-lucide="file-text" class="w-4 h-4"></i> Artikel</a>
    <?php if ($isKontrib): ?>
    <div class="nav-section">Kontributor</div>
    <a href="?tab=laporan"  class="<?= $activeTab==='laporan'?'active':'' ?> relative">
      <i data-lucide="send" class="w-4 h-4"></i> Laporan Harga
      <?php if($lPending>0): ?><span class="ml-auto text-[10px] font-black bg-amber-500 text-white px-1.5 py-0.5 rounded-full font-display"><?= $lPending ?></span><?php endif; ?>
    </a>
    <?php endif; ?>
    <div class="nav-section">Info & Lainnya</div>
    <a href="?tab=info" class="<?= $activeTab==='info'?'active':'' ?>"><i data-lucide="info" class="w-4 h-4"></i> Info & Kontak</a>
    <a href="diskusi.php"><i data-lucide="message-circle" class="w-4 h-4"></i> Forum Diskusi</a>
    <a href="index.php" target="_blank"><i data-lucide="globe" class="w-4 h-4"></i> Lihat Website</a>
    <a href="compare.php"><i data-lucide="git-compare" class="w-4 h-4"></i> Bandingkan Harga</a>
    <a href="profil.php"><i data-lucide="user-circle" class="w-4 h-4"></i> Profil Saya</a>
    <a href="#" data-action="toggle-theme"><i data-lucide="moon" data-theme-icon="toggle" class="w-4 h-4"></i> Ganti Tema</a>
  </nav>

  <div class="p-3 border-t border-[var(--border)] flex-shrink-0">
    <div class="flex items-center gap-2.5 px-3 py-2 rounded-lg mb-1 bg-[var(--surface)]">
      <div class="w-7 h-7 rounded-full bg-brand-500/20 flex items-center justify-center text-[10px] font-black text-brand-500 font-display flex-shrink-0"><?= strtoupper(substr($username,0,1)) ?></div>
      <div class="min-w-0">
        <div class="text-xs font-bold text-[var(--text-primary)] truncate"><?= $username ?></div>
        <div class="text-[10px] text-[var(--text-muted)]"><?= $isKontrib?'Kontributor':'Pengguna' ?></div>
      </div>
    </div>
    <a href="Proses/logout.php" onclick="return confirm('Yakin keluar?')"
       class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-medium text-red-400 hover:bg-red-500/8 transition sidebar-nav">
      <i data-lucide="log-out" class="w-3.5 h-3.5"></i> Logout
    </a>
  </div>
</aside>

<!-- ══ MAIN AREA ══════════════════════════════════════════ -->
<div class="dash-main">
  <header class="h-16 bg-[var(--bg-card)] border-b border-[var(--border)] flex items-center justify-between px-6 flex-shrink-0">
    <?php $tabNames=['beranda'=>'Home','grafik'=>'Grafik Harga','artikel'=>'Artikel Edukasi','laporan'=>'Laporan Harga','info'=>'Info & Kontak']; ?>
    <h1 class="font-display font-black text-[var(--text-primary)]"><?= $tabNames[$activeTab]??'Dashboard' ?></h1>
    <?php if ($isKontrib && $activeTab!=='laporan'): ?>
    <a href="?tab=laporan" class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm font-bold rounded-lg transition font-display">
      <i data-lucide="send" class="w-3.5 h-3.5"></i> Kirim Laporan
    </a>
    <?php endif; ?>
  </header>

  <div class="dash-body">
    <!-- Pesan -->
    <div id="msg-box" class="hidden mb-5 text-sm"></div>
    <?php if (isset($_GET['welcome'])): ?>
    <div class="mb-5 flex items-center gap-3 px-4 py-3.5 rounded-xl bg-brand-500/8 border border-brand-500/20 animate-fade-up">
      <span class="text-xl">🎉</span>
      <div>
        <p class="text-sm font-bold text-brand-500">Selamat datang, <?= $username ?>!</p>
        <p class="text-xs text-[var(--text-muted)]">
          <?= $isKontrib ? 'Akun kontributor Anda siap. Mulai kirim laporan harga dari tab Laporan.' : 'Akun Anda berhasil dibuat. Jelajahi grafik harga komoditas Indonesia.' ?>
        </p>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($activeTab === 'beranda'): ?>
    <!-- ── BERANDA ── -->

    <!-- Pengumuman dari admin -->
    <?php if (!empty($pengumumans)): ?>
    <div class="space-y-3 mb-6">
      <?php foreach($pengumumans as $p): ?>
      <div class="pengumuman-<?= $p['tipe'] ?> rounded-xl p-4">
        <div class="flex items-start gap-3">
          <i data-lucide="<?= $p['tipe']==='darurat'?'alert-triangle':($p['tipe']==='peringatan'?'alert-circle':'info') ?>"
             class="w-4 h-4 flex-shrink-0 mt-0.5 <?= $p['tipe']==='darurat'?'text-red-400':($p['tipe']==='peringatan'?'text-amber-400':'text-blue-400') ?>"></i>
          <div>
            <h4 class="font-display font-bold text-sm text-[var(--text-primary)] mb-0.5"><?= htmlspecialchars($p['judul']) ?></h4>
            <p class="text-xs text-[var(--text-secondary)] leading-relaxed"><?= nl2br(htmlspecialchars($p['isi'])) ?></p>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Sambutan -->
    <div class="card p-5 mb-6 flex items-center gap-4 bg-gradient-to-r from-brand-500/10 to-transparent border-brand-500/20">
      <div class="w-12 h-12 bg-brand-500/20 rounded-full flex items-center justify-center font-display font-black text-xl text-brand-500"><?= strtoupper(substr($username,0,1)) ?></div>
      <div>
        <h2 class="font-display font-bold text-[var(--text-primary)]">Halo, <?= $username ?>! 👋</h2>
        <p class="text-sm text-[var(--text-muted)]"><?= $isKontrib ? 'Terima kasih atas kontribusi laporan harga Anda.' : 'Selamat datang di dashboard InfoHarga Komoditi.' ?></p>
      </div>
    </div>

    <!-- Stats komoditas -->
    <div class="grid grid-cols-3 gap-4 mb-6">
      <div class="card p-4 text-center"><div class="font-display font-black text-2xl text-[var(--text-primary)] mb-1"><?= $totalApproved ?></div><div class="text-xs text-[var(--text-muted)]">Total Komoditas</div></div>
      <div class="card p-4 text-center border-brand-500/15"><div class="font-display font-black text-2xl text-brand-500 mb-1"><?= $naik ?></div><div class="text-xs text-[var(--text-muted)]">Harga Naik</div></div>
      <div class="card p-4 text-center border-red-500/15"><div class="font-display font-black text-2xl text-red-400 mb-1"><?= $turun ?></div><div class="text-xs text-[var(--text-muted)]">Harga Turun</div></div>
    </div>

    <?php if ($isKontrib): ?>
    <!-- Ringkasan kontributor -->
    <div class="card p-5 mb-6">
      <h3 class="font-display font-bold text-[var(--text-primary)] mb-4 flex items-center gap-2"><i data-lucide="activity" class="w-4 h-4 text-blue-400"></i> Ringkasan Laporan Saya</h3>
      <div class="grid grid-cols-4 gap-3">
        <?php foreach([[$lTotal,'Total','slate'],[$lApproved,'Disetujui','emerald'],[$lPending,'Menunggu','amber'],[$lRejected,'Ditolak','red']] as [$v,$l,$c]): ?>
        <div class="text-center p-3 rounded-xl bg-<?= $c ?>-500/8 border border-<?= $c ?>-500/15">
          <div class="font-display font-black text-xl text-<?= $c ?>-400"><?= $v ?></div>
          <div class="text-[10px] text-[var(--text-muted)] mt-0.5"><?= $l ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <a href="?tab=laporan" class="flex items-center gap-2 mt-4 text-sm text-blue-400 hover:text-blue-300 transition font-semibold">
        <i data-lucide="arrow-right" class="w-4 h-4"></i> Lihat semua laporan
      </a>
    </div>
    <?php endif; ?>

    <!-- 5 komoditas terbaru -->
    <div class="card overflow-hidden">
      <div class="px-5 py-4 border-b border-[var(--border)] flex items-center justify-between">
        <h3 class="font-display font-bold text-[var(--text-primary)] flex items-center gap-2"><i data-lucide="trending-up" class="w-4 h-4 text-brand-500"></i> Data Harga Terkini</h3>
        <a href="?tab=grafik" class="text-xs text-brand-500 hover:text-brand-400 font-semibold transition">Lihat semua →</a>
      </div>
      <table class="data-table">
        <thead><tr><th>Komoditas</th><th>Lokasi</th><th>Kemarin</th><th>Sekarang</th><th>Tren</th></tr></thead>
        <tbody>
          <?php foreach(array_slice($komoditas,0,6) as $r):
            $n=(int)$r['harga_sekarang']>(int)$r['harga_kemarin']; $t=(int)$r['harga_sekarang']<(int)$r['harga_kemarin'];
          ?>
          <tr>
            <td class="font-bold text-[var(--text-primary)]"><?= htmlspecialchars($r['nama']) ?></td>
            <td class="text-xs text-[var(--text-muted)]"><?= htmlspecialchars($r['lokasi']) ?></td>
            <td class="text-[var(--text-muted)]"><?= rupiah((int)$r['harga_kemarin']) ?></td>
            <td class="font-bold <?= $n?'text-brand-500':($t?'text-red-400':'text-[var(--text-primary)]') ?>"><?= rupiah((int)$r['harga_sekarang']) ?></td>
            <td><?= $n?'<span class="badge badge-green">▲</span>':($t?'<span class="badge badge-red">▼</span>':'<span class="badge badge-slate">■</span>') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php elseif ($activeTab === 'grafik'): ?>
    <!-- ── GRAFIK ── -->
    <div class="card p-5 mb-5">
      <form method="GET" action="dashboard-user.php" id="grafikForm">
        <input type="hidden" name="tab" value="grafik"/>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
          <!-- Provinsi -->
          <div>
            <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">
              <i data-lucide="map" class="w-3 h-3 inline -mt-0.5 mr-1"></i> Provinsi
            </label>
            <select id="grafikProvinsi" name="provinsi" class="input-field">
              <option value="">— Semua Provinsi —</option>
              <?php foreach(PROVINSI_LIST as $p): ?>
              <option value="<?= htmlspecialchars($p) ?>" <?= $selProv===$p?'selected':'' ?>><?= htmlspecialchars($p) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <!-- Kota (dinamis via JS) -->
          <div>
            <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">
              <i data-lucide="map-pin" class="w-3 h-3 inline -mt-0.5 mr-1"></i> Kota/Kabupaten
              <span class="font-normal normal-case text-[var(--text-muted)] ml-1" id="kotaHint">(opsional)</span>
            </label>
            <select id="grafikKota" name="kota" class="input-field">
              <option value="">— Semua Kota —</option>
              <?php if ($selProv && isset(PROVINSI_KOTA[$selProv])): foreach(PROVINSI_KOTA[$selProv] as $k): ?>
              <option value="<?= htmlspecialchars($k) ?>" <?= (($_GET['kota']??'')===$k)?'selected':'' ?>><?= htmlspecialchars($k) ?></option>
              <?php endforeach; endif; ?>
            </select>
          </div>
          <!-- Komoditas -->
          <div>
            <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">
              <i data-lucide="layers" class="w-3 h-3 inline -mt-0.5 mr-1"></i> Komoditas
            </label>
            <select name="komoditas" class="input-field">
              <?php foreach($namaList as $n): ?>
              <option value="<?= htmlspecialchars($n) ?>" <?= $selKom===$n?'selected':'' ?>><?= htmlspecialchars($n) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="flex justify-end mt-4">
          <button type="submit" class="flex items-center gap-2 px-6 py-2.5 bg-brand-600 hover:bg-brand-500 text-white font-bold rounded-xl text-sm transition font-display shadow shadow-brand-600/20">
            <i data-lucide="search" class="w-4 h-4"></i> Tampilkan Grafik
          </button>
        </div>
      </form>
    </div>

    <?php if ($chartData):
      $hist=json_decode($chartData['history']??'[]',true);
      $sel=(int)$chartData['harga_sekarang']-(int)$chartData['harga_kemarin'];
      $pct=$chartData['harga_kemarin']>0?round(abs($sel)/$chartData['harga_kemarin']*100,2):0;
      $nk=$sel>0; $tn=$sel<0;
      $chartLabel = $chartData['nama'].' — '.($selProv ?: $chartData['lokasi'].', '.$chartData['provinsi']);
    ?>
    <!-- Stat cards -->
    <div class="grid grid-cols-3 gap-4 mb-5">
      <div class="card p-4">
        <p class="text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-wider mb-1.5 flex items-center gap-1"><i data-lucide="trending-up" class="w-3 h-3 text-brand-500"></i> Harga Sekarang</p>
        <div class="font-display font-black text-xl <?= $nk?'text-brand-500':($tn?'text-red-400':'text-[var(--text-primary)]') ?>"><?= rupiah((int)$chartData['harga_sekarang']) ?></div>
        <div class="text-[10px] text-[var(--text-muted)] mt-0.5">per <?= htmlspecialchars($chartData['satuan']??'kg') ?></div>
      </div>
      <div class="card p-4">
        <p class="text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-wider mb-1.5">Harga Kemarin</p>
        <div class="font-display font-black text-xl text-[var(--text-secondary)]"><?= rupiah((int)$chartData['harga_kemarin']) ?></div>
      </div>
      <div class="card p-4">
        <p class="text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-wider mb-1.5">Perubahan</p>
        <div class="font-display font-black text-xl <?= $nk?'text-brand-500':($tn?'text-red-400':'text-[var(--text-muted)]') ?>">
          <?= $nk?'▲':($tn?'▼':'■') ?> <?= $nk?'+':'' ?><?= number_format($sel,0,',','.') ?>
          <span class="text-sm font-normal">(<?= $pct ?>%)</span>
        </div>
      </div>
    </div>
    <!-- Chart -->
    <div class="card p-5 mb-5">
      <div class="flex items-center justify-between mb-1">
        <h2 class="font-display font-bold text-[var(--text-primary)]">Grafik Pergerakan 7 Hari</h2>
        <span class="text-xs text-[var(--text-muted)]"><?= htmlspecialchars($chartLabel) ?></span>
      </div>
      <div id="userChartWrapper" style="position:relative;height:260px"><canvas id="userChart"></canvas></div>
    </div>
    <?php endif; ?>

    <?php if (!empty($chartAll) && count($chartAll) > 1): ?>
    <!-- Tabel semua lokasi -->
    <div class="card overflow-hidden">
      <div class="px-5 py-4 border-b border-[var(--border)]">
        <h2 class="font-display font-bold text-[var(--text-primary)]"><span class="text-brand-500"><?= htmlspecialchars($selKom) ?></span> — Semua Lokasi (<?= count($chartAll) ?>)</h2>
      </div>
      <table class="data-table">
        <thead><tr><th>Lokasi</th><th>Provinsi</th><th>Kemarin</th><th>Sekarang</th><th>Tren</th></tr></thead>
        <tbody>
          <?php foreach($chartAll as $rowIdx=>$r): $n=(int)$r['harga_sekarang']>(int)$r['harga_kemarin']; $t=(int)$r['harga_sekarang']<(int)$r['harga_kemarin']; ?>
          <tr class="<?= $rowIdx===0?'bg-brand-500/[0.02]':'' ?>" style="cursor:pointer" onclick="highlightRow(<?= $rowIdx ?>)" title="Klik untuk lihat grafik lokasi ini">
            <td class="font-bold text-[var(--text-primary)] flex items-center gap-2">
              <?php if($rowIdx===0): ?><span class="w-1.5 h-1.5 rounded-full bg-brand-500 inline-block flex-shrink-0"></span><?php endif; ?>
              <?= htmlspecialchars($r['lokasi']) ?>
            </td>
            <td class="text-xs text-[var(--text-muted)]"><?= htmlspecialchars($r['provinsi']?:'—') ?></td>
            <td class="text-[var(--text-muted)]"><?= rupiah((int)$r['harga_kemarin']) ?></td>
            <td class="font-bold <?= $n?'text-brand-500':($t?'text-red-400':'text-[var(--text-primary)]') ?>"><?= rupiah((int)$r['harga_sekarang']) ?></td>
            <td><?= $n?'<span class="badge badge-green">▲ Naik</span>':($t?'<span class="badge badge-red">▼ Turun</span>':'<span class="badge badge-slate">■ Stabil</span>') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php elseif (!$chartData && isset($_GET['komoditas'])): ?>
    <!-- Data dicari tapi tidak ketemu di database -->
    <div class="card p-12 text-center border-amber-500/20">
      <div class="w-16 h-16 bg-amber-500/10 rounded-2xl flex items-center justify-center mx-auto mb-4">
        <i data-lucide="search-x" class="w-8 h-8 text-amber-400"></i>
      </div>
      <h3 class="font-display font-black text-xl text-[var(--text-primary)] mb-2">Data Tidak Ada</h3>
      <p class="text-[var(--text-secondary)] text-sm max-w-sm mx-auto mb-1">
        Tidak ditemukan data harga untuk
        <strong class="text-amber-400"><?= htmlspecialchars($selKom) ?></strong>
        <?php if($selProv): ?>di <strong><?= htmlspecialchars($selProv) ?></strong><?php endif; ?>.
      </p>
      <p class="text-[var(--text-muted)] text-xs mb-6">Coba pilih komoditas atau provinsi lain, atau kontributor lapangan belum menginput data untuk wilayah ini.</p>
      <div class="flex flex-wrap justify-center gap-2">
        <a href="?tab=grafik" class="px-4 py-2 bg-[var(--surface)] hover:bg-[var(--surface-hover)] border border-[var(--border)] rounded-lg text-sm font-semibold text-[var(--text-secondary)] transition">
          Tampilkan Semua Komoditas
        </a>
        <?php if ($isKontrib): ?>
        <a href="?tab=laporan" class="flex items-center gap-2 px-4 py-2 bg-brand-600 hover:bg-brand-500 text-white rounded-lg text-sm font-bold transition">
          <i data-lucide="plus" class="w-3.5 h-3.5"></i> Kirim Data Ini
        </a>
        <?php endif; ?>
      </div>
    </div>
    <?php elseif (!$chartData): ?>
    <!-- Belum pilih apapun, tampilkan prompt -->
    <div class="card p-12 text-center">
      <div class="w-16 h-16 bg-brand-500/10 rounded-2xl flex items-center justify-center mx-auto mb-4">
        <i data-lucide="bar-chart-2" class="w-8 h-8 text-brand-500 opacity-60"></i>
      </div>
      <h3 class="font-display font-bold text-lg text-[var(--text-primary)] mb-2">Pilih Komoditas</h3>
      <p class="text-sm text-[var(--text-muted)]">Pilih komoditas dan klik <strong>Tampilkan Grafik</strong> untuk melihat pergerakan harga.</p>
    </div>
    <?php endif; ?>

    <?php elseif ($activeTab === 'artikel'): ?>
    <!-- ── ARTIKEL ── -->
    <?php if (empty($artikels)): ?>
    <div class="card p-10 text-center text-sm text-[var(--text-muted)]">Belum ada artikel.</div>
    <?php else: ?>
    <div class="space-y-3">
      <?php foreach($artikels as $a): ?>
      <article class="card card-hover p-5 flex gap-4 cursor-pointer group" onclick="location.href='artikel.php?slug=<?= urlencode($a['slug']) ?>'">
        <div class="w-12 h-12 flex-shrink-0 rounded-xl bg-[var(--surface)] flex items-center justify-center text-2xl"><?= htmlspecialchars($a['emoji']) ?></div>
        <div class="flex-1 min-w-0">
          <div class="flex flex-wrap items-center gap-2 mb-1.5"><span class="badge badge-green text-[10px]"><?= htmlspecialchars($a['kategori']) ?></span><span class="text-[10px] text-[var(--text-muted)] flex items-center gap-1"><i data-lucide="clock" class="w-2.5 h-2.5"></i><?= (int)$a['menit_baca'] ?> menit</span></div>
          <h3 class="font-display font-bold text-sm text-[var(--text-primary)] leading-snug mb-1 group-hover:text-brand-500 transition-colors"><?= htmlspecialchars($a['judul']) ?></h3>
          <p class="text-[var(--text-muted)] text-xs leading-relaxed line-clamp-2"><?= htmlspecialchars($a['ringkasan']) ?></p>
        </div>
        <div class="flex-shrink-0 self-center"><div class="w-7 h-7 rounded-full bg-[var(--surface)] group-hover:bg-brand-500/15 flex items-center justify-center transition-colors"><i data-lucide="arrow-right" class="w-3.5 h-3.5 text-[var(--text-muted)] group-hover:text-brand-500 transition-colors"></i></div></div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php elseif ($activeTab === 'laporan' && $isKontrib): ?>
    <!-- ── LAPORAN KONTRIBUTOR ── -->
    <div class="grid lg:grid-cols-5 gap-6">
      <!-- Form kirim laporan -->
      <div class="lg:col-span-2">
        <div class="card p-5">
          <h2 class="font-display font-bold text-[var(--text-primary)] mb-5 flex items-center gap-2"><i data-lucide="send" class="w-4 h-4 text-blue-400"></i> Kirim Laporan Harga</h2>
          <form action="Proses/prosesSubmit.php" method="POST" novalidate class="space-y-3.5">
            <!-- Nama Komoditas -->
            <div>
              <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">Nama Komoditas <span class="text-red-400">*</span></label>
              <input type="text" name="nama" class="input-field" placeholder="Beras Premium, Cabai Merah, ..." required/>
            </div>
            <!-- Kategori + Satuan -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">Kategori</label>
                <select name="kategori" class="input-field">
                  <?php foreach($kategoris as $k): ?><option><?= htmlspecialchars($k) ?></option><?php endforeach; ?>
                </select>
              </div>
              <div>
                <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">Satuan</label>
                <select name="satuan" class="input-field">
                  <?php foreach($satuans as $s): ?><option><?= $s ?></option><?php endforeach; ?>
                </select>
              </div>
            </div>
            <!-- Provinsi (dari BPS API) -->
            <div>
              <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">
                Provinsi <span class="text-red-400">*</span>
                <span class="font-normal normal-case text-[var(--text-muted)] ml-1">— data BPS</span>
              </label>
              <div class="relative">
                <span class="absolute inset-y-0 left-3.5 flex items-center text-[var(--text-muted)] pointer-events-none"><i data-lucide="map" class="w-3.5 h-3.5"></i></span>
                <select id="laporanProvinsi" name="provinsi" class="input-field input-icon" required>
                  <option value="">— Pilih Provinsi —</option>
                  <?php foreach(PROVINSI_LIST as $p): ?>
                  <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <!-- Kota/Kabupaten (dinamis dari PROVINSI_KOTA) -->
            <div>
              <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">
                Kota / Kabupaten <span class="text-red-400">*</span>
              </label>
              <div class="relative">
                <span class="absolute inset-y-0 left-3.5 flex items-center text-[var(--text-muted)] pointer-events-none"><i data-lucide="map-pin" class="w-3.5 h-3.5"></i></span>
                <select id="laporanKota" name="lokasi" class="input-field input-icon" required disabled>
                  <option value="">— Pilih Provinsi dulu —</option>
                </select>
              </div>
              <p class="text-[10px] text-[var(--text-muted)] mt-1">Pilih provinsi untuk memuat daftar kota.</p>
            </div>
            <!-- Harga -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">Harga Kemarin (Rp) <span class="text-red-400">*</span></label>
                <input type="number" name="kemarin" class="input-field" placeholder="15000" required min="1"/>
              </div>
              <div>
                <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">Harga Sekarang (Rp) <span class="text-red-400">*</span></label>
                <input type="number" name="sekarang" class="input-field" placeholder="15500" required min="1"/>
              </div>
            </div>
            <!-- Info -->
            <div class="flex items-start gap-2 p-3 rounded-lg bg-blue-500/6 border border-blue-500/15 text-xs text-[var(--text-secondary)]">
              <i data-lucide="info" class="w-3.5 h-3.5 text-blue-400 flex-shrink-0 mt-0.5"></i>
              Data akan ditinjau admin sebelum tampil publik. Pastikan harga sesuai kondisi pasar setempat.
            </div>
            <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-display font-bold rounded-xl text-sm transition shadow shadow-blue-600/20 flex items-center justify-center gap-2">
              <i data-lucide="send" class="w-4 h-4"></i> Kirim Laporan Harga
            </button>
          </form>
        </div>
      </div>
      <!-- Riwayat -->
      <div class="lg:col-span-3">
        <div class="card overflow-hidden">
          <div class="px-5 py-4 border-b border-[var(--border)] flex items-center justify-between">
            <h2 class="font-display font-bold text-[var(--text-primary)] flex items-center gap-2"><i data-lucide="clock" class="w-4 h-4 text-blue-400"></i> Riwayat Laporan</h2>
            <a href="export.php?type=laporan" class="flex items-center gap-1.5 text-xs text-brand-500 hover:text-brand-400 font-semibold transition"><i data-lucide="download" class="w-3.5 h-3.5"></i> Export CSV</a>
          </div>
          <table class="data-table">
            <thead><tr><th>Komoditas</th><th>Lokasi</th><th>Harga</th><th>Status</th><th>Catatan</th></tr></thead>
            <tbody>
              <?php if(empty($laporan)): ?>
              <tr><td colspan="5" class="text-center py-12 text-[var(--text-muted)]"><i data-lucide="inbox" class="w-10 h-10 mx-auto mb-2 opacity-20"></i><br>Belum ada laporan.</td></tr>
              <?php else: foreach($laporan as $r):
                $b=$r['status']==='approved'?'<span class="badge badge-green">✓ Disetujui</span>':($r['status']==='pending'?'<span class="badge badge-amber">⏳ Menunggu</span>':'<span class="badge badge-red">✕ Ditolak</span>');
              ?>
              <tr>
                <td><div class="font-bold text-[var(--text-primary)] text-sm"><?= htmlspecialchars($r['nama']) ?></div><div class="text-[10px] text-[var(--text-muted)]"><?= htmlspecialchars($r['kategori']) ?></div></td>
                <td><div class="text-sm"><?= htmlspecialchars($r['lokasi']) ?></div><div class="text-[10px] text-[var(--text-muted)]"><?= htmlspecialchars($r['provinsi']?:'—') ?></div></td>
                <td><div class="text-[10px] text-[var(--text-muted)]">Kem: <?= rupiah((int)$r['harga_kemarin']) ?></div><div class="font-bold text-[var(--text-primary)]"><?= rupiah((int)$r['harga_sekarang']) ?></div></td>
                <td><?= $b ?></td>
                <td class="text-xs text-[var(--text-muted)] max-w-[100px] truncate" title="<?= htmlspecialchars($r['catatan_admin']??'') ?>"><?= htmlspecialchars($r['catatan_admin']?:'—') ?></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <?php elseif ($activeTab === 'info'): ?>
    <!-- ── INFO & KONTAK ── -->
    <div class="space-y-5">

      <!-- Pengumuman -->
      <?php if (!empty($pengumumans)): ?>
      <div class="card overflow-hidden">
        <div class="px-5 py-4 border-b border-[var(--border)] flex items-center gap-2"><i data-lucide="bell" class="w-4 h-4 text-amber-400"></i><h2 class="font-display font-bold text-[var(--text-primary)]">Pengumuman dari Admin</h2></div>
        <div class="p-5 space-y-3">
          <?php foreach($pengumumans as $p): $bColors=['info'=>'bg-blue-500/6 border-blue-500/20','peringatan'=>'bg-amber-500/6 border-amber-500/20','darurat'=>'bg-red-500/6 border-red-500/20']; ?>
          <div class="p-4 rounded-xl <?= $bColors[$p['tipe']] ?> border">
            <h4 class="font-display font-bold text-sm text-[var(--text-primary)] mb-1"><?= htmlspecialchars($p['judul']) ?></h4>
            <p class="text-xs text-[var(--text-secondary)] leading-relaxed"><?= nl2br(htmlspecialchars($p['isi'])) ?></p>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Info SMS -->
      <?php if ($infoSMS): ?>
      <div class="card p-5">
        <h3 class="font-display font-bold text-[var(--text-primary)] mb-3 flex items-center gap-2"><i data-lucide="message-square" class="w-4 h-4 text-green-400"></i> Info Layanan SMS</h3>
        <div class="p-4 rounded-xl bg-green-500/6 border border-green-500/20">
          <p class="text-sm text-[var(--text-secondary)] leading-relaxed"><?= nl2br(htmlspecialchars($infoSMS)) ?></p>
        </div>
      </div>
      <?php endif; ?>

      <!-- Info Kontak -->
      <div class="card p-5">
        <h3 class="font-display font-bold text-[var(--text-primary)] mb-4 flex items-center gap-2"><i data-lucide="phone" class="w-4 h-4 text-brand-500"></i> Hubungi Kami</h3>
        <div class="space-y-3">
          <?php if ($infoTelp): ?>
          <div class="flex items-center gap-3 p-3 rounded-xl bg-[var(--surface)] border border-[var(--border)]">
            <div class="w-9 h-9 bg-brand-500/15 rounded-lg flex items-center justify-center flex-shrink-0"><i data-lucide="phone" class="w-4 h-4 text-brand-500"></i></div>
            <div><div class="text-xs text-[var(--text-muted)] mb-0.5">Telepon Layanan</div><div class="font-bold text-[var(--text-primary)] text-sm"><?= htmlspecialchars($infoTelp) ?></div></div>
          </div>
          <?php endif; ?>
          <?php if ($infoEmail): ?>
          <div class="flex items-center gap-3 p-3 rounded-xl bg-[var(--surface)] border border-[var(--border)]">
            <div class="w-9 h-9 bg-blue-500/15 rounded-lg flex items-center justify-center flex-shrink-0"><i data-lucide="mail" class="w-4 h-4 text-blue-400"></i></div>
            <div><div class="text-xs text-[var(--text-muted)] mb-0.5">Email Admin</div><a href="mailto:<?= htmlspecialchars($infoEmail) ?>" class="font-bold text-brand-500 hover:underline text-sm"><?= htmlspecialchars($infoEmail) ?></a></div>
          </div>
          <?php endif; ?>
          <?php if ($infoWA): ?>
          <a href="https://wa.me/<?= htmlspecialchars($infoWA) ?>" target="_blank" rel="noopener"
             class="flex items-center gap-3 p-3 rounded-xl bg-green-500/8 border border-green-500/20 hover:bg-green-500/12 transition">
            <div class="w-9 h-9 bg-green-500/20 rounded-lg flex items-center justify-center flex-shrink-0"><i data-lucide="message-circle" class="w-4 h-4 text-green-400"></i></div>
            <div><div class="text-xs text-[var(--text-muted)] mb-0.5">WhatsApp</div><div class="font-bold text-green-400 text-sm">Chat via WhatsApp</div></div>
            <i data-lucide="external-link" class="w-3.5 h-3.5 text-green-400 ml-auto"></i>
          </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Referensi data -->
      <div class="card p-5">
        <h3 class="font-display font-bold text-[var(--text-primary)] mb-3 flex items-center gap-2"><i data-lucide="database" class="w-4 h-4 text-[var(--text-muted)]"></i> Sumber Data</h3>
        <p class="text-sm text-[var(--text-secondary)] leading-relaxed">Data harga komoditas pada platform ini diperoleh dari kontributor lapangan terverifikasi dan merujuk pada data publik dari <a href="https://infoharga.bappebti.go.id" target="_blank" rel="noopener" class="text-brand-500 hover:underline">Bappebti</a>.</p>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- end dash-body -->
</div><!-- end dash-main -->
</div><!-- end dash-wrap -->

<!-- Inject mapping provinsi→kota untuk grafik filter -->
<script>
window.PROVINSI_KOTA_JS = <?= json_encode(PROVINSI_KOTA, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="Assets/scripts.js"></script>
<script>
lucide.createIcons();

// ── Provinsi → Kota cascade untuk TAB GRAFIK ─────────────────
(function() {
  const provSel = document.getElementById('grafikProvinsi');
  const kotaSel = document.getElementById('grafikKota');
  if (!provSel || !kotaSel) return;

  function updateKota(selectedKota) {
    const prov   = provSel.value;
    const cities = (window.PROVINSI_KOTA_JS || {})[prov] || [];

    kotaSel.innerHTML = '';

    const defaultOpt = document.createElement('option');
    defaultOpt.value = '';
    defaultOpt.textContent = cities.length ? '— Semua Kota —' : '— (pilih provinsi dulu) —';
    kotaSel.appendChild(defaultOpt);
    kotaSel.disabled = cities.length === 0;

    cities.forEach(function(kota) {
      const opt = document.createElement('option');
      opt.value = kota;
      opt.textContent = kota;
      if (selectedKota && kota === selectedKota) opt.selected = true;
      kotaSel.appendChild(opt);
    });
  }

  // Load on init (if provinsi already selected from URL)
  const currentKota = "<?= htmlspecialchars($_GET['kota']??'') ?>";
  if (provSel.value) updateKota(currentKota);

  provSel.addEventListener('change', function() { updateKota(''); });
})();

// ── Provinsi → Kota cascade untuk TAB LAPORAN (form kontributor) ──────────────
(function() {
  const provSel = document.getElementById('laporanProvinsi');
  const kotaSel = document.getElementById('laporanKota');
  if (!provSel || !kotaSel) return;

  provSel.addEventListener('change', function() {
    const prov   = this.value;
    const cities = (window.PROVINSI_KOTA_JS || {})[prov] || [];

    kotaSel.innerHTML = '';
    kotaSel.disabled  = cities.length === 0;

    if (cities.length === 0) {
      kotaSel.innerHTML = '<option value="">— Pilih Provinsi dulu —</option>';
      return;
    }

    const ph = document.createElement('option');
    ph.value = ''; ph.textContent = '— Pilih Kota/Kabupaten —';
    kotaSel.appendChild(ph);

    cities.forEach(function(kota) {
      const opt = document.createElement('option');
      opt.value = kota; opt.textContent = kota;
      kotaSel.appendChild(opt);
    });

    kotaSel.disabled = false;
  });
})();
</script>
<?php if ($activeTab==='grafik' && $chartData): ?>
<script>
// All chart data rows (for multi-location switching)
const allChartRows = <?= json_encode(array_map(function($r){
    return [
        'nama'    => $r['nama'],
        'lokasi'  => $r['lokasi'],
        'provinsi'=> $r['provinsi'],
        'history' => json_decode($r['history']??'[]',true),
        'harga'   => (int)$r['harga_sekarang'],
        'kemarin' => (int)$r['harga_kemarin'],
        'satuan'  => $r['satuan']??'kg',
    ];
}, !empty($chartAll) ? $chartAll : [$chartData]), JSON_UNESCAPED_UNICODE) ?>;

const LABELS = ['H-6','H-5','H-4','H-3','H-2','Kemarin','Hari Ini'];
let activeChart = null;

(function initUserChart() {
  const canvas = document.getElementById('userChart');
  if (!canvas) { console.error('Canvas #userChart tidak ditemukan'); return; }
  const ctx = canvas.getContext('2d');
  const t   = getChartTheme();

  function makeGradient() {
    let g = ctx.createLinearGradient(0,0,0,260);
    g.addColorStop(0,'rgba(16,185,129,.35)');
    g.addColorStop(1,'rgba(16,185,129,0)');
    return g;
  }

  const initialData = allChartRows[0]?.history ?? [];

  activeChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: LABELS,
      datasets: [{
        data: initialData,
        label: 'Harga',
        borderColor: '#10b981',
        backgroundColor: makeGradient(),
        fill: true,
        tension: .4,
        borderWidth: 2.5,
        pointBackgroundColor: t.bgColor,
        pointBorderColor: '#10b981',
        pointRadius: 5,
        pointHoverRadius: 8,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: c => 'Rp ' + c.parsed.y.toLocaleString('id-ID'),
            title: labels => labels[0],
          }
        }
      },
      scales: {
        y: {
          beginAtZero: false,
          ticks: { color: t.textColor, callback: v => 'Rp ' + v.toLocaleString('id-ID') },
          grid: { color: t.gridColor }
        },
        x: { ticks: { color: t.textColor }, grid: { display: false } }
      }
    }
  });
})();

// Switch chart when user clicks a table row
function highlightRow(idx) {
  const row = allChartRows[idx];
  if (!row || !activeChart) return;
  const hist = Array.isArray(row.history) ? row.history : [];
  // Pad to 7 points
  while (hist.length < 7) hist.unshift(hist[0] ?? 0);
  activeChart.data.datasets[0].data = hist.slice(-7);
  activeChart.update('active');
  // Scroll to chart
  document.getElementById('userChartWrapper')?.scrollIntoView({behavior:'smooth', block:'center'});
}

document.addEventListener('themeChanged', () => {
  if (!activeChart) return;
  const nt = getChartTheme();
  activeChart.options.scales.y.ticks.color = nt.textColor;
  activeChart.options.scales.y.grid.color  = nt.gridColor;
  activeChart.options.scales.x.ticks.color = nt.textColor;
  activeChart.data.datasets[0].pointBackgroundColor = nt.bgColor;
  activeChart.update();
});
</script>
<?php endif; ?>
</body>
</html>
