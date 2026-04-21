<?php
/**
 * peta.php — Peta Harga Komoditas Indonesia (Choropleth)
 * ─────────────────────────────────────────────────────────────
 * Menggunakan D3.js + TopoJSON dari datamaps (idn.topo.json)
 * Memetakan nama provinsi Indonesia → nama dalam TopoJSON (Inggris)
 * ─────────────────────────────────────────────────────────────
 */
session_start();
require 'Server/koneksi.php';
cekLogin();

$resNama  = $conn->query("SELECT DISTINCT nama FROM komoditas WHERE status='approved' ORDER BY nama ASC");
$namaList = [];
if ($resNama) while ($r = $resNama->fetch_assoc()) $namaList[] = $r['nama'];

$defaultKom = $namaList[0] ?? '';
$selKom     = trim(esc($conn, $_GET['komoditas'] ?? $defaultKom));
if ($selKom && !in_array($selKom, $namaList)) $selKom = $defaultKom;

// ── Data harga per provinsi ───────────────────────────────────
$petaData = [];
$maxHarga = 0;
$minHarga = PHP_INT_MAX;

if ($selKom) {
    $k   = esc($conn, $selKom);
    $res = $conn->query("SELECT provinsi, AVG(harga_sekarang) as avg_harga,
                                MIN(harga_sekarang) as min_harga,
                                MAX(harga_sekarang) as max_harga,
                                COUNT(*) as jumlah
                         FROM komoditas
                         WHERE status='approved' AND nama='$k' AND provinsi!=''
                         GROUP BY provinsi ORDER BY avg_harga DESC");
    if ($res) while ($r = $res->fetch_assoc()) {
        $avg = (int)round($r['avg_harga']);
        $petaData[$r['provinsi']] = [
            'avg'    => $avg,
            'min'    => (int)$r['min_harga'],
            'max'    => (int)$r['max_harga'],
            'jumlah' => (int)$r['jumlah'],
        ];
        if ($avg > $maxHarga) $maxHarga = $avg;
        if ($avg < $minHarga) $minHarga = $avg;
    }
}
if ($minHarga === PHP_INT_MAX) $minHarga = 0;

$totalProviDenganData = count($petaData);
$rataRata = $petaData ? (int)round(array_sum(array_column($petaData,'avg'))/count($petaData)) : 0;

$pageTitle = 'Peta Harga '.htmlspecialchars($selKom ?: 'Komoditas');
?>
<!doctype html>
<html lang="id">
<head><?php include 'Assets/head.php'; ?>
<style>
  body{overflow:hidden}
  .peta-wrap{display:flex;height:100vh}
  .peta-side{width:240px;flex-shrink:0;display:flex;flex-direction:column;height:100%;background:var(--bg-secondary);border-right:1px solid var(--border);overflow:hidden}
  .peta-main{flex:1;display:flex;flex-direction:column;overflow:hidden;background:var(--bg-primary)}
  .peta-header{padding:.875rem 1.5rem;border-bottom:1px solid var(--border);background:var(--bg-card);flex-shrink:0}
  .peta-body{flex:1;position:relative;overflow:hidden}
  #map-svg{width:100%;height:100%;display:block}
  /* Province path styles */
  .province{
    stroke-width:0.5px;
    transition:opacity .12s,stroke-width .12s;
    cursor:pointer;
  }
  .province:hover{stroke-width:1.8px;opacity:.88}
  .province.has-data:hover{stroke-width:2px}
  .province.no-data{opacity:.55}
  /* Tooltip */
  #peta-tooltip{
    position:absolute;
    pointer-events:none;
    background:var(--bg-card);
    border:1px solid var(--border);
    border-radius:.875rem;
    padding:.75rem 1rem;
    box-shadow:var(--shadow-lg);
    min-width:160px;
    max-width:220px;
    opacity:0;
    transition:opacity .12s;
    z-index:50;
    font-size:.75rem;
  }
  /* Zoom controls */
  .zoom-btn{display:flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:.5rem;background:var(--bg-card);border:1px solid var(--border);color:var(--text-muted);cursor:pointer;transition:background .15s,color .15s;font-size:18px;font-weight:500;line-height:1}
  .zoom-btn:hover{background:var(--surface);color:var(--text-primary)}
</style>
</head>
<body>
<div class="peta-wrap">

<!-- ══ SIDEBAR ════════════════════════════════════════════════ -->
<aside class="peta-side">
  <div class="h-14 flex items-center px-4 border-b border-[var(--border)] flex-shrink-0">
    <a href="dashboard-user.php" class="flex items-center gap-2">
      <div class="w-6 h-6 bg-brand-500 rounded-lg flex items-center justify-center shadow shadow-brand-500/30">
        <i data-lucide="trending-up" class="w-3 h-3 text-white"></i>
      </div>
      <span class="font-display font-black text-sm text-[var(--text-primary)]">InfoHarga</span>
    </a>
  </div>

  <!-- Pilih komoditas -->
  <div class="px-4 py-3 border-b border-[var(--border)] flex-shrink-0">
    <label class="block text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-wider mb-1.5">Komoditas</label>
    <form method="GET">
      <select name="komoditas" class="input-field text-xs py-1.5" onchange="this.form.submit()">
        <?php foreach($namaList as $n): ?>
        <option value="<?= htmlspecialchars($n) ?>" <?= $selKom===$n?'selected':'' ?>><?= htmlspecialchars($n) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <!-- Statistik ringkas -->
  <div class="px-4 py-3 border-b border-[var(--border)] flex-shrink-0 space-y-1.5">
    <div class="flex justify-between text-xs">
      <span class="text-[var(--text-muted)]">Provinsi punya data</span>
      <span class="font-bold text-[var(--text-primary)]"><?= $totalProviDenganData ?>/38</span>
    </div>
    <div class="flex justify-between text-xs">
      <span class="text-[var(--text-muted)]">Rata-rata nasional</span>
      <span class="font-bold text-brand-500"><?= $rataRata ? rupiah($rataRata) : '—' ?></span>
    </div>
    <div class="flex justify-between text-xs">
      <span class="text-[var(--text-muted)]">Harga tertinggi</span>
      <span class="font-bold text-red-400"><?= $maxHarga ? rupiah($maxHarga) : '—' ?></span>
    </div>
    <div class="flex justify-between text-xs">
      <span class="text-[var(--text-muted)]">Harga terendah</span>
      <span class="font-bold text-brand-500"><?= ($minHarga && $minHarga!==PHP_INT_MAX) ? rupiah($minHarga) : '—' ?></span>
    </div>
  </div>

  <!-- Skala warna -->
  <div class="px-4 py-3 border-b border-[var(--border)] flex-shrink-0">
    <label class="block text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-wider mb-2">Skala Harga</label>
    <canvas id="legendCanvas" height="10" style="width:100%;border-radius:4px"></canvas>
    <div class="flex justify-between mt-1">
      <span class="text-[9px] text-[var(--text-muted)]"><?= $minHarga && $minHarga!==PHP_INT_MAX ? rupiah($minHarga) : 'Rendah' ?></span>
      <span class="text-[9px] text-[var(--text-muted)]"><?= $maxHarga ? rupiah($maxHarga) : 'Tinggi' ?></span>
    </div>
    <div class="flex items-center gap-1.5 mt-2">
      <div class="w-3 h-3 rounded" style="background:#334155;opacity:.6"></div>
      <span class="text-[9px] text-[var(--text-muted)]">Belum ada data</span>
    </div>
  </div>

  <!-- Ranking provinsi -->
  <div class="flex-1 overflow-y-auto slim-scroll px-2 py-2">
    <?php if (empty($petaData)): ?>
    <p class="text-xs text-[var(--text-muted)] p-3 text-center">Belum ada data provinsi.</p>
    <?php else: $rank=1; foreach($petaData as $prov=>$d): ?>
    <a href="chart.php?provinsi=<?= urlencode($prov) ?>&komoditas=<?= urlencode($selKom) ?>"
       class="flex items-center justify-between px-2.5 py-2 rounded-lg hover:bg-[var(--surface)] transition text-xs group">
      <div class="flex items-center gap-2 min-w-0">
        <span class="text-[10px] text-[var(--text-muted)] w-4 text-right flex-shrink-0"><?= $rank++ ?></span>
        <span class="text-[var(--text-secondary)] group-hover:text-[var(--text-primary)] transition truncate"><?= htmlspecialchars($prov) ?></span>
      </div>
      <span class="font-bold font-display text-[var(--text-primary)] flex-shrink-0 ml-2 text-[11px]"><?= rupiah($d['avg']) ?></span>
    </a>
    <?php endforeach; endif; ?>
  </div>

  <!-- Nav -->
  <div class="border-t border-[var(--border)] px-3 py-2 space-y-0.5 sidebar-nav flex-shrink-0">
    <a href="dashboard-user.php" class="text-xs"><i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Dashboard</a>
    <a href="compare.php" class="text-xs"><i data-lucide="git-compare" class="w-3.5 h-3.5"></i> Perbandingan</a>
    <a href="#" data-action="toggle-theme" class="text-xs"><i data-lucide="moon" data-theme-icon="toggle" class="w-3.5 h-3.5"></i> Ganti Tema</a>
  </div>
</aside>

<!-- ══ MAIN MAP AREA ══════════════════════════════════════════ -->
<div class="peta-main">
  <div class="peta-header flex items-center justify-between">
    <div>
      <h1 class="font-display font-bold text-base text-[var(--text-primary)]">
        Peta Harga — <span class="text-brand-500"><?= htmlspecialchars($selKom ?: 'Komoditas') ?></span>
      </h1>
      <p class="text-[11px] text-[var(--text-muted)] mt-0.5">
        Klik provinsi untuk grafik detail · Gradien warna = level harga · Scroll/pinch untuk zoom
      </p>
    </div>
    <div class="flex gap-2">
      <a href="compare.php?kom[]=<?= urlencode($selKom) ?>"
         class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-semibold text-[var(--text-secondary)] hover:text-brand-500 hover:bg-brand-500/8 transition">
        <i data-lucide="git-compare" class="w-3.5 h-3.5"></i> Bandingkan
      </a>
      <a href="export.php?type=komoditas&nama=<?= urlencode($selKom) ?>"
         class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-semibold text-[var(--text-secondary)] hover:text-brand-500 hover:bg-brand-500/8 transition">
        <i data-lucide="download" class="w-3.5 h-3.5"></i> Export CSV
      </a>
    </div>
  </div>

  <!-- Zoom controls -->
  <div class="absolute right-4 top-16 flex flex-col gap-1 z-20">
    <button class="zoom-btn" id="zoomIn"  title="Zoom in">+</button>
    <button class="zoom-btn" id="zoomOut" title="Zoom out">−</button>
    <button class="zoom-btn" id="zoomReset" title="Reset" style="font-size:12px">⤢</button>
  </div>

  <div class="peta-body">
    <!-- Loading state -->
    <div id="mapLoading" class="absolute inset-0 flex items-center justify-center z-30 bg-[var(--bg-primary)]">
      <div class="text-center">
        <div class="w-12 h-12 border-2 border-brand-500 border-t-transparent rounded-full mx-auto mb-3" style="animation:spin 1s linear infinite"></div>
        <p class="text-sm text-[var(--text-muted)]">Memuat peta Indonesia...</p>
      </div>
    </div>
    <svg id="map-svg" role="img" aria-label="Peta harga komoditas Indonesia"></svg>
    <!-- Tooltip -->
    <div id="peta-tooltip">
      <div class="font-display font-bold text-[var(--text-primary)] text-sm mb-1" id="tt-nama"></div>
      <div class="font-display font-black text-base text-brand-500" id="tt-harga"></div>
      <div class="text-[var(--text-muted)] mt-1 space-y-0.5 text-[11px]" id="tt-detail"></div>
    </div>
  </div>
</div>

</div><!-- end peta-wrap -->

<style>@keyframes spin{to{transform:rotate(360deg)}}</style>

<!-- PHP data ke JavaScript -->
<script>
// Data harga per provinsi (nama Bahasa Indonesia dari database)
const PETA_DATA = <?= json_encode($petaData, JSON_UNESCAPED_UNICODE) ?>;
const PETA_MIN  = <?= $minHarga === PHP_INT_MAX ? 0 : (int)$minHarga ?>;
const PETA_MAX  = <?= (int)$maxHarga ?>;
const SEL_KOM   = <?= json_encode($selKom) ?>;
const IS_DARK   = () => document.documentElement.classList.contains('dark');

// ── MAPPING: Nama provinsi Indonesia → Nama di file TopoJSON datamaps ──
// datamaps/idn.topo.json menggunakan nama Inggris di field properties.name
const ID_TO_TOPO = {
  'Aceh':                        'Aceh',
  'Sumatera Utara':              'North Sumatra',
  'Sumatera Barat':              'West Sumatra',
  'Riau':                        'Riau',
  'Kepulauan Riau':              'Riau Islands',
  'Jambi':                       'Jambi',
  'Bengkulu':                    'Bengkulu',
  'Sumatera Selatan':            'South Sumatra',
  'Kepulauan Bangka Belitung':   'Bangka-Belitung Islands',
  'Lampung':                     'Lampung',
  'Banten':                      'Banten',
  'DKI Jakarta':                 'Jakarta',
  'Jawa Barat':                  'West Java',
  'Jawa Tengah':                 'Central Java',
  'DI Yogyakarta':               'Yogyakarta',
  'Jawa Timur':                  'East Java',
  'Bali':                        'Bali',
  'Nusa Tenggara Barat':         'West Nusa Tenggara',
  'Nusa Tenggara Timur':         'East Nusa Tenggara',
  'Kalimantan Barat':            'West Kalimantan',
  'Kalimantan Tengah':           'Central Kalimantan',
  'Kalimantan Selatan':          'South Kalimantan',
  'Kalimantan Timur':            'East Kalimantan',
  'Kalimantan Utara':            'North Kalimantan',
  'Sulawesi Utara':              'North Sulawesi',
  'Gorontalo':                   'Gorontalo',
  'Sulawesi Tengah':             'Central Sulawesi',
  'Sulawesi Barat':              'West Sulawesi',
  'Sulawesi Selatan':            'South Sulawesi',
  'Sulawesi Tenggara':           'Southeast Sulawesi',
  'Maluku':                      'Maluku',
  'Maluku Utara':                'North Maluku',
  'Papua Barat':                 'West Papua',
  'Papua Barat Daya':            'West Papua',
  'Papua':                       'Papua',
  'Papua Selatan':               'Papua',
  'Papua Tengah':                'Papua',
  'Papua Pegunungan':            'Papua',
};

// Reverse: nama TopoJSON → nama Indonesia (untuk lookup saat hover)
const TOPO_TO_ID = {};
Object.entries(ID_TO_TOPO).forEach(([id, topo]) => {
  // Jika beberapa provinsi Indonesia map ke satu topo name,
  // gabungkan data harganya
  if (!TOPO_TO_ID[topo]) TOPO_TO_ID[topo] = id;
});

// Fungsi format rupiah
function rupiah(n) {
  if (!n) return '—';
  return 'Rp ' + Math.round(n).toLocaleString('id-ID');
}

// Fungsi ambil data harga berdasarkan nama TopoJSON
function getDataByTopoName(topoName) {
  const idName = TOPO_TO_ID[topoName];
  if (idName && PETA_DATA[idName]) return { ...PETA_DATA[idName], provName: idName };
  // Coba cari manual jika tidak ketemu reverse map
  for (const [idN, topoN] of Object.entries(ID_TO_TOPO)) {
    if (topoN === topoName && PETA_DATA[idN]) return { ...PETA_DATA[idN], provName: idN };
  }
  return null;
}

// Color scale: interpolasi biru → hijau → merah
function getColor(avg) {
  if (!avg || PETA_MAX === PETA_MIN) return IS_DARK() ? '#1e293b' : '#e2e8f0';
  const t = Math.max(0, Math.min(1, (avg - PETA_MIN) / (PETA_MAX - PETA_MIN)));
  // Biru (rendah) → Hijau (tengah) → Merah (tinggi)
  let r, g, b;
  if (t < 0.5) {
    const s = t * 2;
    r = Math.round(29  + (16 - 29)  * s);
    g = Math.round(78  + (185 - 78) * s);
    b = Math.round(216 + (129 - 216)* s);
  } else {
    const s = (t - 0.5) * 2;
    r = Math.round(16  + (239 - 16)  * s);
    g = Math.round(185 + (68  - 185) * s);
    b = Math.round(129 + (68  - 129) * s);
  }
  return `rgb(${r},${g},${b})`;
}

function getNoDataColor() {
  return IS_DARK() ? '#1e293b' : '#cbd5e1';
}
function getStrokeColor() {
  return IS_DARK() ? '#0f172a' : '#ffffff';
}
</script>

<!-- D3.js + TopoJSON dari CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/d3/7.8.5/d3.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/topojson/3.0.2/topojson.min.js"></script>
<script src="Assets/scripts.js"></script>
<script>
lucide.createIcons();

// ── LEGEND GRADIENT ──────────────────────────────────────────
(function() {
  const canvas = document.getElementById('legendCanvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const w   = canvas.offsetWidth || 200;
  const grad= ctx.createLinearGradient(0,0,w,0);
  grad.addColorStop(0,   '#1d4ed8');
  grad.addColorStop(0.5, '#10b981');
  grad.addColorStop(1,   '#ef4444');
  ctx.fillStyle = grad;
  ctx.fillRect(0,0,w,10);
})();

// ── RENDER PETA ──────────────────────────────────────────────
const mapSvg      = document.getElementById('map-svg');
const tooltip     = document.getElementById('peta-tooltip');
const ttNama      = document.getElementById('tt-nama');
const ttHarga     = document.getElementById('tt-harga');
const ttDetail    = document.getElementById('tt-detail');
const mapLoading  = document.getElementById('mapLoading');

let svg, g, projection, path, zoom;

function initMap(topology) {
  // Sembunyikan loading
  if (mapLoading) mapLoading.style.display = 'none';

  const W = mapSvg.clientWidth  || mapSvg.parentElement.clientWidth;
  const H = mapSvg.clientHeight || mapSvg.parentElement.clientHeight;

  svg = d3.select('#map-svg')
    .attr('viewBox', `0 0 ${W} ${H}`)
    .attr('preserveAspectRatio', 'xMidYMid meet');

  g = svg.append('g');

  // ── Zoom & Pan ──────────────────────────────────────────
  zoom = d3.zoom()
    .scaleExtent([1, 8])
    .on('zoom', e => g.attr('transform', e.transform));
  svg.call(zoom).on('dblclick.zoom', null);

  // Tombol zoom
  document.getElementById('zoomIn')?.addEventListener('click', () =>
    svg.transition().call(zoom.scaleBy, 1.5));
  document.getElementById('zoomOut')?.addEventListener('click', () =>
    svg.transition().call(zoom.scaleBy, 0.7));
  document.getElementById('zoomReset')?.addEventListener('click', () =>
    svg.transition().call(zoom.transform, d3.zoomIdentity));

  // ── Ambil features ──────────────────────────────────────
  // datamaps idn.topo.json menggunakan object key 'idn'
  const objKey  = Object.keys(topology.objects)[0]; // 'idn'
  const features= topojson.feature(topology, topology.objects[objKey]).features;

  // ── Proyeksi peta ───────────────────────────────────────
  projection = d3.geoMercator()
    .fitSize([W * 0.97, H * 0.92],
      { type: 'FeatureCollection', features })
    .translate([W / 2, H / 2]);

  path = d3.geoPath().projection(projection);

  // ── Gambar provinsi ─────────────────────────────────────
  g.selectAll('.province')
    .data(features)
    .join('path')
    .attr('class', d => {
      const topoName = d.properties.name || '';
      const data     = getDataByTopoName(topoName);
      return 'province ' + (data ? 'has-data' : 'no-data');
    })
    .attr('d', path)
    .attr('fill', d => {
      const topoName = d.properties.name || '';
      const data     = getDataByTopoName(topoName);
      return data ? getColor(data.avg) : getNoDataColor();
    })
    .attr('stroke', getStrokeColor())
    .on('mousemove', function(event, d) {
      const topoName = d.properties.name || '';
      const data     = getDataByTopoName(topoName);
      const dispName = data?.provName || topoName;

      ttNama.textContent = dispName;

      if (data) {
        ttHarga.textContent  = rupiah(data.avg);
        ttHarga.style.color  = getColor(data.avg);
        ttDetail.innerHTML   = [
          `<div>Min: ${rupiah(data.min)}</div>`,
          `<div>Maks: ${rupiah(data.max)}</div>`,
          `<div>${data.jumlah} titik data</div>`,
          `<div style="color:var(--text-muted);margin-top:4px;font-size:10px">Klik untuk lihat grafik →</div>`,
        ].join('');
      } else {
        ttHarga.textContent = 'Belum ada data';
        ttHarga.style.color = 'var(--text-muted)';
        ttDetail.innerHTML  = '<div style="font-size:10px">Kontributor belum melaporkan harga di wilayah ini.</div>';
      }

      // Posisi tooltip
      const rect  = mapSvg.getBoundingClientRect();
      let   tx    = event.clientX - rect.left + 14;
      let   ty    = event.clientY - rect.top  - 10;
      const ttW   = 220;
      if (tx + ttW > rect.width)  tx = tx - ttW - 28;
      if (ty + 120 > rect.height) ty = ty - 120;
      tooltip.style.left    = tx + 'px';
      tooltip.style.top     = ty + 'px';
      tooltip.style.opacity = '1';
    })
    .on('mouseleave', () => { tooltip.style.opacity = '0'; })
    .on('click', (event, d) => {
      const topoName = d.properties.name || '';
      const data     = getDataByTopoName(topoName);
      const provName = data?.provName;
      if (provName) {
        window.location.href = `chart.php?provinsi=${encodeURIComponent(provName)}&komoditas=${encodeURIComponent(SEL_KOM)}`;
      }
    });
}

// ── Fetch TopoJSON ────────────────────────────────────────────
fetch('https://cdn.jsdelivr.net/npm/datamaps@0.5.10/src/js/data/idn.topo.json')
  .then(r => {
    if (!r.ok) throw new Error('HTTP ' + r.status);
    return r.json();
  })
  .then(topology => initMap(topology))
  .catch(err => {
    console.error('Gagal memuat peta:', err);
    if (mapLoading) mapLoading.style.display = 'none';
    document.getElementById('peta-tooltip')?.remove();
    document.getElementById('map-svg').insertAdjacentHTML('afterend', `
      <div class="absolute inset-0 flex items-center justify-center">
        <div class="text-center p-10 max-w-sm">
          <div class="w-14 h-14 bg-amber-500/10 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <i data-lucide="wifi-off" class="w-7 h-7 text-amber-400"></i>
          </div>
          <h3 class="font-display font-bold text-lg text-[var(--text-primary)] mb-2">Peta Tidak Dapat Dimuat</h3>
          <p class="text-sm text-[var(--text-muted)] mb-5 leading-relaxed">
            File peta gagal diunduh dari CDN.<br/>
            Pastikan XAMPP terhubung ke internet lalu refresh halaman.
          </p>
          <button onclick="location.reload()"
            class="px-5 py-2.5 bg-brand-600 hover:bg-brand-500 text-white rounded-xl text-sm font-bold transition">
            Coba Lagi
          </button>
        </div>
      </div>`);
    lucide.createIcons();
  });

// ── Update warna saat tema berubah ────────────────────────────
document.addEventListener('themeChanged', () => {
  if (!g) return;
  g.selectAll('.province')
    .attr('fill', d => {
      const data = getDataByTopoName(d.properties.name || '');
      return data ? getColor(data.avg) : getNoDataColor();
    })
    .attr('stroke', getStrokeColor());
  // Update legend
  (function() {
    const canvas = document.getElementById('legendCanvas');
    if (!canvas) return;
    const ctx  = canvas.getContext('2d');
    const w    = canvas.offsetWidth || 200;
    const grad = ctx.createLinearGradient(0,0,w,0);
    grad.addColorStop(0,'#1d4ed8'); grad.addColorStop(0.5,'#10b981'); grad.addColorStop(1,'#ef4444');
    ctx.clearRect(0,0,w,10); ctx.fillStyle=grad; ctx.fillRect(0,0,w,10);
  })();
});
</script>
</body>
</html>
