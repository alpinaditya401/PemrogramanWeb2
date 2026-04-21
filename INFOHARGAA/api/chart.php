<?php
/**
 * chart.php — Grafik Harga per Provinsi & Komoditas
 * ─────────────────────────────────────────────────────────────
 * RESTRICTED: Guest yang mencoba buka halaman ini TANPA login
 * akan diarahkan ke login.php.
 * ─────────────────────────────────────────────────────────────
 */
session_start();
require 'Server/koneksi.php';

// ── GATE: wajib login ────────────────────────────────────────
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    redirect('login.php?redirect=chart');
}

$resNama  = $conn->query("SELECT DISTINCT nama FROM komoditas WHERE status='approved' ORDER BY nama ASC");
$namaList = [];
if ($resNama) while ($r = $resNama->fetch_assoc()) $namaList[] = $r['nama'];

$selProv = in_array($_GET['provinsi'] ?? '', PROVINSI_LIST) ? $_GET['provinsi'] : '';
$selKota = esc($conn, trim($_GET['kota'] ?? ''));  // Kota/Kabupaten (dari BPS mapping)
$selKom  = trim(esc($conn, $_GET['komoditas'] ?? ($namaList[0] ?? '')));

$data    = null;
$dataAll = [];

if ($selKom) {
    $k = esc($conn, $selKom);

    if ($selKota) {
        // Kota spesifik dipilih → cari berdasarkan lokasi
        $r = $conn->query("SELECT * FROM komoditas WHERE status='approved' AND nama='$k'
                           AND (lokasi='$selKota' OR lokasi LIKE '%$selKota%') LIMIT 1");
        if ($r && $r->num_rows > 0) $data = $r->fetch_assoc();
    } elseif ($selProv) {
        // Provinsi dipilih → ambil data provinsi itu
        $p = esc($conn, $selProv);
        $r = $conn->query("SELECT * FROM komoditas WHERE status='approved' AND nama='$k' AND provinsi='$p' ORDER BY updated_at DESC LIMIT 1");
        if ($r && $r->num_rows > 0) $data = $r->fetch_assoc();
    }

    // Selalu muat semua lokasi untuk tabel perbandingan
    $whereP = $selProv ? " AND provinsi='".esc($conn,$selProv)."'" : '';
    $res = $conn->query("SELECT * FROM komoditas WHERE status='approved' AND nama='$k'{$whereP} ORDER BY provinsi ASC, lokasi ASC");
    if ($res) while ($r = $res->fetch_assoc()) $dataAll[] = $r;

    // Auto-select row pertama jika tidak ada pilihan spesifik
    if (!$data && !empty($dataAll)) $data = $dataAll[0];
}

$pageTitle = $selKom ? "Harga " . htmlspecialchars($selKom) . ($selProv ? " di " . htmlspecialchars($selProv) : '') : 'Grafik Harga';
$pageDesc  = "Pantau grafik harga " . ($selKom ?: 'komoditas') . " di Indonesia secara real-time.";
$activeNav = 'chart';
?>
<!doctype html>
<html lang="id">
<head><?php include 'Assets/head.php'; ?>
<style>
  .filter-card:focus-within { box-shadow:0 0 0 2px rgba(16,185,129,.25); }
  #chartWrapper { position:relative; width:100%; height:300px; }
</style>
</head>
<body>
<div class="h-9 bg-[var(--bg-secondary)] border-b border-[var(--border)]"></div>
<?php include 'Assets/navbar.php'; ?>

<!-- PAGE HEADER -->
<div class="pt-28 pb-8 bg-[var(--bg-secondary)] border-b border-[var(--border)]">
  <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
    <nav class="flex items-center gap-1.5 text-xs text-[var(--text-muted)] mb-3" aria-label="Breadcrumb">
      <a href="index.php" class="hover:text-brand-500 transition">Beranda</a>
      <i data-lucide="chevron-right" class="w-3 h-3"></i>
      <span>Grafik Harga</span>
      <?php if ($selProv): ?>
      <i data-lucide="chevron-right" class="w-3 h-3"></i>
      <span class="text-brand-500"><?= htmlspecialchars($selProv) ?></span>
      <?php endif; ?>
    </nav>
    <h1 class="font-display font-black text-2xl md:text-3xl text-[var(--text-primary)]">
      <?php if ($selProv): ?>Harga di <span class="text-brand-500"><?= htmlspecialchars($selProv) ?></span>
      <?php elseif ($selKom): ?>Harga <span class="text-brand-500"><?= htmlspecialchars($selKom) ?></span>
      <?php else: ?>Grafik <span class="text-brand-500">Harga Komoditas</span><?php endif; ?>
    </h1>
  </div>
</div>

<div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

  <!-- FILTER — Komoditas + Provinsi + Kota cascade dari BPS API -->
  <div class="card filter-card p-5 mb-8">
    <form method="GET" action="chart.php">
      <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
        <!-- Komoditas -->
        <div>
          <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">
            <i data-lucide="layers" class="w-3 h-3 inline mr-1"></i> Komoditas
          </label>
          <?php if (!empty($namaList)): ?>
          <select name="komoditas" id="chartKomoditas" class="input-field">
            <?php foreach ($namaList as $n): ?>
            <option value="<?= htmlspecialchars($n) ?>" <?= $selKom===$n?'selected':'' ?>><?= htmlspecialchars($n) ?></option>
            <?php endforeach; ?>
          </select>
          <?php else: ?>
          <div class="input-field text-[var(--text-muted)]">Belum ada data</div>
          <?php endif; ?>
        </div>
        <!-- Provinsi -->
        <div>
          <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">
            <i data-lucide="map" class="w-3 h-3 inline mr-1"></i> Provinsi
            <span class="font-normal normal-case text-[var(--text-muted)] ml-1">— BPS</span>
          </label>
          <select name="provinsi" id="chartProvinsi" class="input-field" onchange="updateChartKota(this.value)">
            <option value="">— Semua Provinsi —</option>
            <?php foreach (PROVINSI_LIST as $p): ?>
            <option value="<?= htmlspecialchars($p) ?>" <?= $selProv===$p?'selected':'' ?>><?= htmlspecialchars($p) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <!-- Kota/Kabupaten (dinamis dari PROVINSI_KOTA) -->
        <div>
          <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">
            <i data-lucide="map-pin" class="w-3 h-3 inline mr-1"></i> Kota / Kabupaten
          </label>
          <select name="kota" id="chartKota" class="input-field">
            <option value="">— Semua Kota —</option>
            <?php if ($selProv && isset(PROVINSI_KOTA[$selProv])): foreach(PROVINSI_KOTA[$selProv] as $kt): ?>
            <option value="<?= htmlspecialchars($kt) ?>" <?= $selKota===$kt?'selected':'' ?>><?= htmlspecialchars($kt) ?></option>
            <?php endforeach; endif; ?>
          </select>
          <p class="text-[10px] text-[var(--text-muted)] mt-1">Pilih provinsi → kota terisi otomatis</p>
        </div>
        <!-- Tombol -->
        <div class="flex items-end">
          <button type="submit" class="w-full flex items-center justify-center gap-2 px-5 py-2.5 bg-brand-600 hover:bg-brand-500 text-white font-display font-bold rounded-xl text-sm transition shadow shadow-brand-600/20">
            <i data-lucide="search" class="w-4 h-4"></i> Tampilkan
          </button>
        </div>
      </div>
    </form>
  </div>

  <?php if ($data):
    $hist    = json_decode($data['history'] ?? '[]', true);
    $selisih = (int)$data['harga_sekarang'] - (int)$data['harga_kemarin'];
    $persen  = $data['harga_kemarin'] > 0 ? round(abs($selisih)/$data['harga_kemarin']*100,2) : 0;
    $naik=$selisih>0; $turun=$selisih<0;
    $tc = $naik?'text-brand-500':($turun?'text-red-400':'text-[var(--text-muted)]');
    $ti = $naik?'▲':($turun?'▼':'■');
  ?>
  <!-- Stat cards -->
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="card p-5">
      <p class="text-xs font-bold text-[var(--text-muted)] uppercase tracking-wider mb-2 flex items-center gap-1.5"><i data-lucide="trending-up" class="w-3.5 h-3.5 text-brand-500"></i> Harga Sekarang</p>
      <div class="font-display font-black text-2xl <?= $tc ?>"><?= rupiah((int)$data['harga_sekarang']) ?></div>
      <div class="text-xs text-[var(--text-muted)] mt-1">per <?= htmlspecialchars($data['satuan']??'kg') ?> · <?= htmlspecialchars($data['lokasi']) ?>, <?= htmlspecialchars($data['provinsi']) ?></div>
    </div>
    <div class="card p-5">
      <p class="text-xs font-bold text-[var(--text-muted)] uppercase tracking-wider mb-2 flex items-center gap-1.5"><i data-lucide="calendar" class="w-3.5 h-3.5"></i> Harga Kemarin</p>
      <div class="font-display font-black text-2xl text-[var(--text-secondary)]"><?= rupiah((int)$data['harga_kemarin']) ?></div>
    </div>
    <div class="card p-5">
      <p class="text-xs font-bold text-[var(--text-muted)] uppercase tracking-wider mb-2 flex items-center gap-1.5"><i data-lucide="activity" class="w-3.5 h-3.5"></i> Perubahan</p>
      <div class="font-display font-black text-2xl <?= $tc ?>"><?= $ti ?> <?= $naik?'+':'' ?><?= number_format($selisih,0,',','.') ?> <span class="text-sm font-normal">(<?= $persen ?>%)</span></div>
    </div>
  </div>

  <!-- Grafik 7 hari -->
  <div class="card p-6 mb-6">
    <div class="flex items-center justify-between mb-1">
      <h2 class="font-display font-bold text-[var(--text-primary)]">Grafik Pergerakan 7 Hari</h2>
      <span class="text-xs text-[var(--text-muted)]"><?= htmlspecialchars($data['nama']) ?> · <?= htmlspecialchars($data['lokasi']) ?>, <?= htmlspecialchars($data['provinsi']) ?></span>
    </div>
    <div id="chartWrapper"><canvas id="mainChart"></canvas></div>
  </div>

  <!-- History table riwayat 7 hari -->
  <div class="card overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-[var(--border)] flex items-center gap-2">
      <i data-lucide="clock" class="w-4 h-4 text-brand-500"></i>
      <h2 class="font-display font-bold text-[var(--text-primary)]">Riwayat 7 Hari Terakhir</h2>
    </div>
    <table class="data-table">
      <thead><tr><th>Hari</th><th>Harga (Rp)</th><th>Perubahan</th><th>Indikator</th></tr></thead>
      <tbody id="histTbody"></tbody>
    </table>
  </div>
  <?php endif; ?>

  <?php if (!empty($dataAll) && count($dataAll) > 1): ?>
  <!-- Tabel semua lokasi -->
  <div class="card overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-[var(--border)] flex items-center justify-between">
      <div class="flex items-center gap-2">
        <i data-lucide="map" class="w-4 h-4 text-brand-500"></i>
        <h2 class="font-display font-bold text-[var(--text-primary)]"><span class="text-brand-500"><?= htmlspecialchars($selKom) ?></span> — Semua Lokasi (<?= count($dataAll) ?>)</h2>
      </div>
      <?php if ($selProv): ?>
      <a href="chart.php?komoditas=<?= urlencode($selKom) ?>" class="text-xs text-brand-500 hover:underline">Lihat semua provinsi →</a>
      <?php endif; ?>
    </div>
    <table class="data-table">
      <thead><tr><th>Lokasi</th><th>Provinsi</th><th>Kemarin</th><th>Sekarang</th><th>Tren</th></tr></thead>
      <tbody>
        <?php foreach ($dataAll as $ri => $r):
          $n=(int)$r['harga_sekarang']>(int)$r['harga_kemarin'];
          $t=(int)$r['harga_sekarang']<(int)$r['harga_kemarin'];
          $isActive = ($data && $r['id']==$data['id']);
        ?>
        <tr class="<?= $isActive?'bg-brand-500/[0.03]':'' ?>" style="cursor:pointer" onclick="switchChart(<?= $ri ?>)" title="Klik untuk lihat grafik">
          <td class="font-bold text-[var(--text-primary)] flex items-center gap-2">
            <?php if($isActive): ?><span class="w-1.5 h-1.5 rounded-full bg-brand-500 flex-shrink-0"></span><?php endif; ?>
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
  <?php endif; ?>

  <?php if (!$data && empty($dataAll) && $selKom): ?>
  <!-- Data Tidak Ada -->
  <div class="card p-14 text-center border-amber-500/20">
    <div class="w-16 h-16 bg-amber-500/10 rounded-2xl flex items-center justify-center mx-auto mb-4">
      <i data-lucide="search-x" class="w-8 h-8 text-amber-400"></i>
    </div>
    <h3 class="font-display font-black text-2xl text-[var(--text-primary)] mb-2">Data Tidak Ada</h3>
    <p class="text-[var(--text-secondary)] text-sm mb-1">Harga <strong class="text-amber-400"><?= htmlspecialchars($selKom) ?></strong><?= $selProv ? ' di <strong>'.$selProv.'</strong>' : '' ?> belum tersedia.</p>
    <p class="text-[var(--text-muted)] text-xs mb-7 max-w-sm mx-auto">Kontributor lapangan di wilayah ini belum menginput data. Jadilah kontributor untuk melengkapi data nasional.</p>
    <div class="flex flex-wrap justify-center gap-3">
      <a href="chart.php" class="px-5 py-2.5 bg-[var(--surface)] border border-[var(--border)] text-[var(--text-secondary)] rounded-xl text-sm font-semibold hover:bg-[var(--surface-hover)] transition">
        Coba Komoditas Lain
      </a>
      <a href="register.php" class="flex items-center gap-2 px-5 py-2.5 bg-brand-600 hover:bg-brand-500 text-white rounded-xl text-sm font-bold transition shadow shadow-brand-600/20">
        <i data-lucide="user-plus" class="w-4 h-4"></i> Jadilah Kontributor
      </a>
    </div>
  </div>
  <?php elseif (!$selKom): ?>
  <div class="card p-12 text-center">
    <i data-lucide="bar-chart-2" class="w-14 h-14 mx-auto opacity-20 mb-4"></i>
    <h3 class="font-display font-bold text-lg text-[var(--text-primary)] mb-2">Pilih Komoditas</h3>
    <p class="text-sm text-[var(--text-muted)]">Pilih komoditas dari dropdown di atas lalu klik Tampilkan.</p>
  </div>
  <?php endif; ?>
</div>

<?php include 'Assets/footer.php'; ?>

<script>
window.PROVINSI_KOTA_JS = <?= json_encode(PROVINSI_KOTA, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="Assets/scripts.js"></script>
<script>
lucide.createIcons();

// Provinsi → Kota cascade untuk filter chart.php
function updateChartKota(provinsi) {
  const sel    = document.getElementById('chartKota');
  const cities = (window.PROVINSI_KOTA_JS || {})[provinsi] || [];
  sel.innerHTML = '<option value="">— Semua Kota —</option>';
  cities.forEach(kota => {
    const o = document.createElement('option');
    o.value = kota; o.textContent = kota;
    sel.appendChild(o);
  });
  sel.disabled = cities.length === 0;
}

// Init: jika sudah ada provinsi terpilih dari URL, isi kotanya
(function(){
  const prov = document.getElementById('chartProvinsi')?.value;
  const curKota = <?= json_encode($selKota) ?>;
  if (prov) {
    updateChartKota(prov);
    if (curKota) document.getElementById('chartKota').value = curKota;
  }
})();
</script>
<?php if ($data): ?>
<script>
// All data rows for chart switching when user clicks table row
const allRows = <?= json_encode(array_map(fn($r)=>[
    'lokasi'   => $r['lokasi'],
    'provinsi' => $r['provinsi'],
    'history'  => json_decode($r['history']??'[]',true),
    'sekarang' => (int)$r['harga_sekarang'],
    'kemarin'  => (int)$r['harga_kemarin'],
    'satuan'   => $r['satuan']??'kg',
], $dataAll), JSON_UNESCAPED_UNICODE) ?>;

const LABELS = ['H-6','H-5','H-4','H-3','H-2','Kemarin','Hari Ini'];
let activeChart = null;

(function initChart() {
  const ctx = document.getElementById('mainChart')?.getContext('2d');
  if (!ctx) return;
  const t   = getChartTheme();
  let grad  = ctx.createLinearGradient(0,0,0,300);
  grad.addColorStop(0,'rgba(16,185,129,.35)'); grad.addColorStop(1,'rgba(16,185,129,0)');

  const initData = allRows[0]?.history ?? <?= json_encode(array_values(json_decode($data['history']??'[]',true)),JSON_UNESCAPED_UNICODE) ?>;

  activeChart = new Chart(ctx, {
    type:'line',
    data:{ labels:LABELS, datasets:[{ data:initData, borderColor:'#10b981', backgroundColor:grad,
      fill:true, tension:.4, borderWidth:2.5,
      pointBackgroundColor:t.bgColor, pointBorderColor:'#10b981', pointRadius:5, pointHoverRadius:7 }] },
    options:{ responsive:true, maintainAspectRatio:false,
      interaction:{ mode:'index', intersect:false },
      plugins:{ legend:{display:false}, tooltip:{callbacks:{label:c=>'Rp '+c.parsed.y.toLocaleString('id-ID')}} },
      scales:{ y:{beginAtZero:false, ticks:{color:t.textColor,callback:v=>'Rp '+v.toLocaleString('id-ID')}, grid:{color:t.gridColor}},
               x:{ticks:{color:t.textColor}, grid:{display:false}} } }
  });

  // Render history table from chart data
  function renderHistTable(histArr) {
    const tbody = document.getElementById('histTbody');
    if (!tbody) return;
    const maxH = Math.max(...histArr), minH = Math.min(...histArr);
    tbody.innerHTML = '';
    histArr.forEach((h,i) => {
    const prev=i>0?histData[i-1]:h, diff=h-prev, pct=prev>0?Math.abs(diff/prev*100).toFixed(2):0;
    const isToday=i===histData.length-1;
    const barPct = maxH>minH?Math.round((h-minH)/(maxH-minH)*100):50;
    const barCol = diff>0?'#10b981':diff<0?'#ef4444':'#94a3b8';
    let badge='';
    if(i===0) badge='<span class="text-[var(--text-muted)] text-xs">—</span>';
    else if(diff>0) badge=`<span class="badge badge-green">▲ +${diff.toLocaleString('id-ID')} (${pct}%)</span>`;
    else if(diff<0) badge=`<span class="badge badge-red">▼ ${diff.toLocaleString('id-ID')} (${pct}%)</span>`;
    else badge='<span class="badge badge-slate">■ Stabil</span>';
      tbody.innerHTML+=`<tr class="${isToday?'bg-brand-500/[0.03]':''}">
        <td><span class="font-medium ${isToday?'text-brand-500 font-bold':'text-[var(--text-secondary)]'}">${LABELS[i]}</span>${isToday?'<span class="ml-1.5 badge badge-green text-[9px]">HARI INI</span>':''}</td>
        <td class="font-display font-bold text-[var(--text-primary)]">Rp ${h.toLocaleString('id-ID')}</td>
        <td>${badge}</td>
        <td><div class="w-24 h-1.5 rounded-full bg-[var(--surface)] overflow-hidden"><div class="h-full rounded-full" style="width:${barPct}%;background:${barCol}"></div></div></td>
      </tr>`;
    });
  }
  renderHistTable(initData);

  document.addEventListener('themeChanged', () => {
    if (!activeChart) return;
    const nt = getChartTheme();
    activeChart.options.scales.y.ticks.color = nt.textColor;
    activeChart.options.scales.y.grid.color  = nt.gridColor;
    activeChart.options.scales.x.ticks.color = nt.textColor;
    activeChart.data.datasets[0].pointBackgroundColor = nt.bgColor;
    activeChart.update();
  });
})();

// switchChart: called when user clicks a row in the all-locations table
function switchChart(idx) {
  const row = allRows[idx];
  if (!row || !activeChart) return;
  const hist = Array.isArray(row.history) ? row.history : [];
  const padded = hist.slice(-7);
  while(padded.length < 7) padded.unshift(padded[0] ?? 0);
  activeChart.data.datasets[0].data = padded;
  activeChart.update('active');
  renderHistTable(padded);
  // Scroll to chart
  document.getElementById('chartWrapper')?.scrollIntoView({behavior:'smooth', block:'center'});
}
</script>
<?php endif; ?>
<script>lucide.createIcons();</script>
</body>
</html>
