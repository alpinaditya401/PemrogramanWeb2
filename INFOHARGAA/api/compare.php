<?php
/**
 * compare.php — Perbandingan Harga Antar Kota
 * ─────────────────────────────────────────────────────────────
 * Wajib login.
 *
 * Konsep: pilih SATU komoditas, lalu bandingkan harganya
 * di beberapa kota/lokasi berbeda (max 4 kota) dalam satu grafik.
 *
 * Contoh penggunaan:
 *   Beras Premium di Jakarta vs Bandung vs Surabaya vs Medan
 *   → 4 garis di satu grafik, masing-masing mewakili satu kota
 * ─────────────────────────────────────────────────────────────
 */
session_start();
require 'Server/koneksi.php';
cekLogin();

// ── DAFTAR KOMODITAS yang tersedia ────────────────────────────
$resNama  = $conn->query("SELECT DISTINCT nama FROM komoditas WHERE status='approved' ORDER BY nama ASC");
$namaList = [];
if ($resNama) while ($r = $resNama->fetch_assoc()) $namaList[] = $r['nama'];

// Komoditas yang dipilih (1 komoditas)
$defaultKom = $namaList[0] ?? '';
$selKom     = trim(esc($conn, $_GET['komoditas'] ?? $defaultKom));
if ($selKom && !in_array($selKom, $namaList)) $selKom = $defaultKom;

// ── DAFTAR KOTA yang punya data untuk komoditas ini ───────────
$kotaList = [];
if ($selKom) {
    $k      = esc($conn, $selKom);
    $resKota = $conn->query("SELECT DISTINCT lokasi, provinsi FROM komoditas
                              WHERE status='approved' AND nama='$k'
                              ORDER BY lokasi ASC");
    if ($resKota) while ($r = $resKota->fetch_assoc()) {
        $kotaList[] = $r['lokasi'].' ('.$r['provinsi'].')';
    }
}

// ── KOTA-KOTA yang dipilih (max 4) ───────────────────────────
$selKotas = [];
if (isset($_GET['kota']) && is_array($_GET['kota'])) {
    foreach (array_slice($_GET['kota'], 0, 4) as $kt) {
        $kt = trim($kt);
        if ($kt && in_array($kt, $kotaList)) $selKotas[] = $kt;
    }
}
// Default: 2 kota pertama dari daftar
if ($selKom && empty($selKotas) && count($kotaList) >= 2) {
    $selKotas = array_slice($kotaList, 0, min(2, count($kotaList)));
}

// ── AMBIL DATA setiap kota yang dipilih ──────────────────────
$chartDatasets = [];
$warna         = ['#10b981','#3b82f6','#f59e0b','#ef4444'];

foreach ($selKotas as $idx => $kotaProvinsi) {
    // kotaProvinsi format: "Brebes (Jawa Tengah)"
    // Extract nama kota: ambil bagian sebelum " ("
    preg_match('/^(.+?)\s+\((.+)\)$/', $kotaProvinsi, $m);
    $namaKota = isset($m[1]) ? trim($m[1]) : $kotaProvinsi;
    $namaKotaEsc = esc($conn, $namaKota);
    $komEsc      = esc($conn, $selKom);

    $res = $conn->query("SELECT * FROM komoditas
                         WHERE status='approved' AND nama='$komEsc' AND lokasi='$namaKotaEsc'
                         ORDER BY updated_at DESC LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        $hist = json_decode($row['history'] ?? '[]', true);
        if (!is_array($hist)) $hist = [];
        while (count($hist) < 7) array_unshift($hist, $hist[0] ?? 0);
        $hist = array_slice($hist, -7);

        $chartDatasets[] = [
            'label'    => $namaKota.' ('.$row['provinsi'].')',
            'kota'     => $namaKota,
            'provinsi' => $row['provinsi'],
            'warna'    => $warna[$idx],
            'hist'     => $hist,
            'sekarang' => (int)$row['harga_sekarang'],
            'kemarin'  => (int)$row['harga_kemarin'],
            'satuan'   => $row['satuan'] ?? 'kg',
        ];
    }
}

$pageTitle = 'Perbandingan Harga Antar Kota';
?>
<!doctype html>
<html lang="id">

<head><?php include 'Assets/head.php'; ?>
    <style>
    body {
        overflow: hidden
    }

    .cmp-wrap {
        display: flex;
        height: 100vh
    }

    .cmp-side {
        width: 240px;
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        height: 100%;
        background: var(--bg-secondary);
        border-right: 1px solid var(--border)
    }

    .cmp-main {
        flex: 1;
        overflow-y: auto;
        padding: 1.5rem
    }

    .cmp-main::-webkit-scrollbar {
        width: 4px
    }

    .cmp-main::-webkit-scrollbar-thumb {
        background: var(--border);
        border-radius: 4px
    }

    #compareChart {
        position: relative;
        width: 100%;
        height: 380px
    }

    .color-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        flex-shrink: 0;
        display: inline-block
    }

    .city-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 10px;
        border-radius: 8px;
        border: 1px solid var(--border);
        font-size: .75rem;
        background: var(--surface)
    }
    </style>
</head>

<body>
    <div class="cmp-wrap">

        <!-- ══ SIDEBAR ════════════════════════════════════════════════ -->
        <aside class="cmp-side">
            <div class="h-16 flex items-center px-5 border-b border-[var(--border)] flex-shrink-0">
                <a href="dashboard-user.php" class="flex items-center gap-2">
                    <div
                        class="w-7 h-7 bg-brand-500 rounded-lg flex items-center justify-center shadow shadow-brand-500/30">
                        <i data-lucide="trending-up" class="w-3.5 h-3.5 text-white"></i>
                    </div>
                    <span class="font-display font-black text-sm text-[var(--text-primary)]">InfoHarga</span>
                </a>
            </div>

            <!-- Info panel -->
            <div class="px-4 py-4 border-b border-[var(--border)]">
                <div class="flex items-start gap-2 p-3 rounded-xl bg-brand-500/6 border border-brand-500/15">
                    <i data-lucide="info" class="w-3.5 h-3.5 text-brand-500 flex-shrink-0 mt-0.5"></i>
                    <p class="text-[11px] text-[var(--text-secondary)] leading-relaxed">
                        Pilih <strong>1 komoditas</strong>, lalu bandingkan harganya di <strong>beberapa kota
                            berbeda</strong>.
                    </p>
                </div>
            </div>

            <!-- Daftar kota yg punya data -->
            <?php if ($selKom && !empty($kotaList)): ?>
            <div class="px-4 py-3 border-b border-[var(--border)]">
                <p class="text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-wider mb-1.5">
                    Kota dengan data (<?= count($kotaList) ?>)
                </p>
                <div class="space-y-0.5 max-h-52 overflow-y-auto slim-scroll">
                    <?php foreach ($kotaList as $kt): ?>
                    <div
                        class="flex items-center justify-between py-1.5 px-2 rounded-lg hover:bg-[var(--surface)] text-xs text-[var(--text-secondary)]">
                        <span><?= htmlspecialchars($kt) ?></span>
                        <?php if (in_array($kt, $selKotas)): ?>
                        <span class="w-1.5 h-1.5 rounded-full bg-brand-500"></span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <nav class="flex-1 py-3 px-3 space-y-0.5 sidebar-nav">
                <div class="nav-section">Navigasi</div>
                <a href="dashboard-user.php"><i data-lucide="arrow-left" class="w-4 h-4"></i> Dashboard</a>
                <a href="chart.php"><i data-lucide="bar-chart-2" class="w-4 h-4"></i> Grafik Tunggal</a>
                <a href="peta.php"><i data-lucide="map" class="w-4 h-4"></i> Peta Indonesia</a>
                <a href="#" data-action="toggle-theme"><i data-lucide="moon" data-theme-icon="toggle"
                        class="w-4 h-4"></i> Ganti Tema</a>
            </nav>

            <div class="p-3 border-t border-[var(--border)] flex-shrink-0">
                <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-[var(--surface)]">
                    <div
                        class="w-6 h-6 rounded-full bg-brand-500/20 flex items-center justify-center text-[9px] font-black text-brand-500 font-display flex-shrink-0">
                        <?= strtoupper(substr($_SESSION['username'],0,1)) ?>
                    </div>
                    <div class="min-w-0">
                        <div class="text-xs font-bold text-[var(--text-primary)] truncate">
                            <?= htmlspecialchars($_SESSION['username']) ?></div>
                        <div class="text-[10px] text-[var(--text-muted)]"><?= htmlspecialchars($_SESSION['role']) ?>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- ══ MAIN ════════════════════════════════════════════════════ -->
        <div class="cmp-main">

            <!-- Header -->
            <div class="mb-6">
                <h1 class="font-display font-black text-2xl text-[var(--text-primary)] mb-1">
                    Perbandingan Harga Antar Kota
                </h1>
                <p class="text-sm text-[var(--text-muted)]">
                    Pilih satu komoditas, lalu pilih 2–4 kota untuk melihat perbandingan harga dalam satu grafik.
                </p>
            </div>

            <!-- ── FORM FILTER ─────────────────────────────────────────── -->
            <div class="card p-5 mb-6">
                <form method="GET" action="compare.php" id="compareForm">

                    <!-- Step 1: Pilih komoditas -->
                    <div class="mb-5">
                        <label
                            class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-2">
                            <span class="inline-flex items-center gap-1.5">
                                <span
                                    class="w-5 h-5 rounded-full bg-brand-500 text-white text-[10px] font-black flex items-center justify-center flex-shrink-0">1</span>
                                Pilih Komoditas
                            </span>
                        </label>
                        <select name="komoditas" id="selKomoditas" class="input-field max-w-xs"
                            onchange="this.form.submit()">
                            <?php if (empty($namaList)): ?>
                            <option value="">Belum ada data komoditas</option>
                            <?php else: foreach ($namaList as $n): ?>
                            <option value="<?= htmlspecialchars($n) ?>" <?= $selKom===$n?'selected':'' ?>>
                                <?= htmlspecialchars($n) ?>
                            </option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>

                    <!-- Step 2: Pilih kota-kota -->
                    <div class="mb-5">
                        <label
                            class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-2">
                            <span class="inline-flex items-center gap-1.5">
                                <span
                                    class="w-5 h-5 rounded-full bg-brand-500 text-white text-[10px] font-black flex items-center justify-center flex-shrink-0">2</span>
                                Pilih Kota yang Dibandingkan (maksimal 4)
                            </span>
                        </label>

                        <?php if (empty($kotaList)): ?>
                        <div
                            class="flex items-center gap-2 p-3 rounded-xl bg-amber-500/6 border border-amber-500/20 text-xs text-[var(--text-secondary)]">
                            <i data-lucide="alert-triangle" class="w-3.5 h-3.5 text-amber-400 flex-shrink-0"></i>
                            Belum ada data kota untuk komoditas
                            <strong><?= htmlspecialchars($selKom ?: 'yang dipilih') ?></strong>.
                            Pilih komoditas lain atau tambahkan data lewat Laporan Harga.
                        </div>
                        <?php else: ?>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <?php for ($i = 0; $i < 4; $i++): ?>
                            <div>
                                <label
                                    class="block text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-wider mb-1.5 flex items-center gap-1.5">
                                    <span class="color-dot" style="background:<?= $warna[$i] ?>"></span>
                                    Kota <?= $i+1 ?><?= $i===0?' *':' (opsional)' ?>
                                </label>
                                <select name="kota[]" class="input-field text-sm">
                                    <?php if ($i > 0): ?>
                                    <option value="">— Tidak dibandingkan —</option>
                                    <?php endif; ?>
                                    <?php foreach ($kotaList as $kt): ?>
                                    <option value="<?= htmlspecialchars($kt) ?>"
                                        <?= (isset($selKotas[$i]) && $selKotas[$i]===$kt)?'selected':'' ?>>
                                        <?= htmlspecialchars($kt) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endfor; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Tombol -->
                    <?php if (!empty($kotaList)): ?>
                    <div class="flex items-center gap-3">
                        <button type="submit"
                            class="flex items-center gap-2 px-6 py-2.5 bg-brand-600 hover:bg-brand-500 text-white font-display font-bold rounded-xl text-sm transition shadow shadow-brand-600/20">
                            <i data-lucide="git-compare" class="w-4 h-4"></i> Tampilkan Perbandingan
                        </button>
                        <?php if (!empty($chartDatasets)): ?>
                        <a href="export.php?type=komoditas&nama=<?= urlencode($selKom) ?>"
                            class="flex items-center gap-1.5 text-xs text-[var(--text-muted)] hover:text-brand-500 transition font-semibold">
                            <i data-lucide="download" class="w-3.5 h-3.5"></i> Export CSV
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                </form>
            </div>

            <!-- ── HASIL PERBANDINGAN ────────────────────────────────────── -->
            <?php if (!empty($chartDatasets)): ?>

            <!-- Legend kota terpilih -->
            <div class="flex flex-wrap gap-2 mb-5">
                <?php foreach ($chartDatasets as $ds): ?>
                <div class="city-tag">
                    <span class="color-dot" style="background:<?= $ds['warna'] ?>"></span>
                    <div>
                        <span class="font-bold text-[var(--text-primary)]"><?= htmlspecialchars($ds['kota']) ?></span>
                        <span
                            class="text-[var(--text-muted)] ml-1 text-[10px]"><?= htmlspecialchars($ds['provinsi']) ?></span>
                    </div>
                    <span class="font-display font-black ml-1" style="color:<?= $ds['warna'] ?>">
                        <?= rupiah($ds['sekarang']) ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Grafik utama -->
            <div class="card p-5 mb-5">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h2 class="font-display font-bold text-[var(--text-primary)]">
                            Grafik Pergerakan 7 Hari —
                            <span class="text-brand-500"><?= htmlspecialchars($selKom) ?></span>
                        </h2>
                        <p class="text-xs text-[var(--text-muted)] mt-0.5">
                            <?= implode(' vs ', array_map(fn($d)=>$d['kota'], $chartDatasets)) ?>
                        </p>
                    </div>
                    <span class="badge badge-green text-[10px]">per
                        <?= htmlspecialchars($chartDatasets[0]['satuan'] ?? 'kg') ?></span>
                </div>
                <div id="compareChart"><canvas id="compareCanvas" role="img"
                        aria-label="Grafik perbandingan harga <?= htmlspecialchars($selKom) ?> antar kota 7 hari terakhir">
                    </canvas></div>
            </div>

            <!-- Tabel perbandingan -->
            <div class="card overflow-hidden">
                <div class="px-5 py-4 border-b border-[var(--border)] flex items-center justify-between">
                    <h2 class="font-display font-bold text-[var(--text-primary)] flex items-center gap-2">
                        <i data-lucide="table" class="w-4 h-4 text-brand-500"></i>
                        Ringkasan Perbandingan
                    </h2>
                    <?php
      // Kota termahal & termurah
      $sorted = $chartDatasets;
      usort($sorted, fn($a,$b) => $b['sekarang'] - $a['sekarang']);
      $termahal  = $sorted[0]  ?? null;
      $termurah  = end($sorted) ?: null;
      $selisihMax = ($termahal && $termurah && count($sorted) > 1)
                    ? $termahal['sekarang'] - $termurah['sekarang'] : 0;
      ?>
                    <?php if ($selisihMax > 0): ?>
                    <span class="text-xs text-[var(--text-muted)]">
                        Selisih tertinggi–terendah:
                        <strong class="text-[var(--text-primary)]"><?= rupiah($selisihMax) ?></strong>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Kota</th>
                                <th>Provinsi</th>
                                <th>Kemarin</th>
                                <th>Sekarang</th>
                                <th>Perubahan</th>
                                <th>Tren</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($chartDatasets as $idx => $ds):
            $sel  = $ds['sekarang'] - $ds['kemarin'];
            $pct  = $ds['kemarin'] > 0 ? round(abs($sel)/$ds['kemarin']*100,2) : 0;
            $naik = $sel > 0; $turun = $sel < 0;
            $isTermahal = ($termahal && $ds['kota'] === $termahal['kota']);
            $isTermurah = ($termurah && $ds['kota'] === $termurah['kota'] && count($sorted) > 1);
          ?>
                            <tr>
                                <td>
                                    <div class="flex items-center gap-2.5">
                                        <span class="color-dot" style="background:<?= $ds['warna'] ?>"></span>
                                        <span
                                            class="font-bold text-[var(--text-primary)]"><?= htmlspecialchars($ds['kota']) ?></span>
                                    </div>
                                </td>
                                <td class="text-xs text-[var(--text-muted)]"><?= htmlspecialchars($ds['provinsi']) ?>
                                </td>
                                <td class="text-[var(--text-muted)]"><?= rupiah($ds['kemarin']) ?></td>
                                <td>
                                    <span class="font-display font-black"
                                        style="color:<?= $naik?$warna[0]:($turun?$warna[3]:'var(--text-primary)') ?>">
                                        <?= rupiah($ds['sekarang']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span
                                        class="text-sm font-medium <?= $naik?'text-brand-500':($turun?'text-red-400':'text-[var(--text-muted)]') ?>">
                                        <?= $naik?'+':'' ?><?= number_format($sel,0,',','.') ?>
                                        <span class="text-xs font-normal">(<?= $pct ?>%)</span>
                                    </span>
                                </td>
                                <td>
                                    <?= $naik
                ? '<span class="badge badge-green">▲ Naik</span>'
                : ($turun ? '<span class="badge badge-red">▼ Turun</span>' : '<span class="badge badge-slate">■ Stabil</span>') ?>
                                </td>
                                <td>
                                    <?php if ($isTermahal): ?>
                                    <span class="badge badge-red text-[10px]">Termahal</span>
                                    <?php elseif ($isTermurah): ?>
                                    <span class="badge badge-green text-[10px]">Termurah</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Bar chart horizontal mini untuk perbandingan visual langsung -->
                <?php if (count($chartDatasets) > 1 && $maxHarga = max(array_column($chartDatasets,'sekarang'))): ?>
                <div class="px-6 py-5 border-t border-[var(--border)]">
                    <h3 class="font-display font-bold text-[var(--text-primary)] text-sm mb-4 flex items-center gap-2">
                        <i data-lucide="bar-chart-horizontal" class="w-4 h-4 text-brand-500"></i>
                        Bar Perbandingan Harga Hari Ini
                    </h3>
                    <div class="space-y-3">
                        <?php foreach ($chartDatasets as $ds): ?>
                        <?php $pct = $maxHarga > 0 ? round($ds['sekarang'] / $maxHarga * 100) : 0; ?>
                        <div class="flex items-center gap-3">
                            <div class="w-28 flex-shrink-0">
                                <span
                                    class="text-xs font-bold text-[var(--text-primary)] truncate block"><?= htmlspecialchars($ds['kota']) ?></span>
                                <span
                                    class="text-[10px] text-[var(--text-muted)]"><?= htmlspecialchars($ds['provinsi']) ?></span>
                            </div>
                            <div class="flex-1 flex items-center gap-2">
                                <div class="flex-1 bg-[var(--surface)] rounded-full h-3 overflow-hidden">
                                    <div class="h-full rounded-full transition-all duration-700"
                                        style="width:<?= $pct ?>%;background:<?= $ds['warna'] ?>"></div>
                                </div>
                                <span class="text-xs font-display font-black w-28 text-right flex-shrink-0"
                                    style="color:<?= $ds['warna'] ?>">
                                    <?= rupiah($ds['sekarang']) ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php elseif ($selKom && !empty($kotaList)): ?>
            <!-- Belum pilih kota -->
            <div class="card p-12 text-center">
                <div class="w-16 h-16 bg-brand-500/10 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="git-compare" class="w-8 h-8 text-brand-500 opacity-60"></i>
                </div>
                <h3 class="font-display font-bold text-lg text-[var(--text-primary)] mb-2">Pilih Kota yang Dibandingkan
                </h3>
                <p class="text-sm text-[var(--text-muted)] max-w-sm mx-auto">
                    Komoditas <strong class="text-brand-500"><?= htmlspecialchars($selKom) ?></strong> tersedia di
                    <strong><?= count($kotaList) ?> kota</strong>. Pilih minimal 2 kota di atas lalu klik Tampilkan.
                </p>
            </div>

            <?php else: ?>
            <!-- Belum ada data sama sekali -->
            <div class="card p-12 text-center">
                <i data-lucide="database" class="w-12 h-12 mx-auto opacity-20 mb-3"></i>
                <h3 class="font-display font-bold text-lg text-[var(--text-primary)] mb-2">Belum Ada Data</h3>
                <p class="text-sm text-[var(--text-muted)]">Belum ada data komoditas. Tambahkan data lewat halaman
                    Laporan Harga.</p>
            </div>
            <?php endif; ?>

        </div><!-- end cmp-main -->
    </div><!-- end cmp-wrap -->

    <!-- ── CHART SCRIPT ────────────────────────────────────────── -->
    <?php if (!empty($chartDatasets)): ?>
    <script>
    const DS = <?= json_encode(array_map(fn($d) => [
    'label'                => $d['label'],
    'data'                 => $d['hist'],
    'borderColor'          => $d['warna'],
    'backgroundColor'      => $d['warna'].'18',
    'fill'                 => false,
    'tension'              => .4,
    'borderWidth'          => 2.5,
    'pointBackgroundColor' => $d['warna'],
    'pointBorderColor'     => $d['warna'],
    'pointRadius'          => 4,
    'pointHoverRadius'     => 7,
], $chartDatasets), JSON_UNESCAPED_UNICODE) ?>;
    const LABELS = ['H-6', 'H-5', 'H-4', 'H-3', 'H-2', 'Kemarin', 'Hari Ini'];
    </script>
    <?php endif; ?>

    <script src="Assets/scripts.js"></script>
    <script>
    lucide.createIcons();
    <?php if (!empty($chartDatasets)): ?>
        (function() {
            const ctx = document.getElementById('compareCanvas')?.getContext('2d');
            if (!ctx) return;
            const t = getChartTheme();

            const chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: LABELS,
                    datasets: DS
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: {
                                color: t.textColor,
                                boxWidth: 12,
                                padding: 20,
                                font: {
                                    family: 'Instrument Sans',
                                    size: 12
                                },
                            }
                        },
                        tooltip: {
                            callbacks: {
                                title: (items) => 'Hari: ' + items[0].label,
                                label: (c) => '  ' + c.dataset.label + ': ' + 'Rp ' + c.parsed.y
                                    .toLocaleString('id-ID'),
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            ticks: {
                                color: t.textColor,
                                callback: v => 'Rp ' + v.toLocaleString('id-ID'),
                            },
                            grid: {
                                color: t.gridColor
                            },
                        },
                        x: {
                            ticks: {
                                color: t.textColor
                            },
                            grid: {
                                display: false
                            },
                        }
                    }
                }
            });

            document.addEventListener('themeChanged', () => {
                const nt = getChartTheme();
                chart.options.scales.y.ticks.color = nt.textColor;
                chart.options.scales.y.grid.color = nt.gridColor;
                chart.options.scales.x.ticks.color = nt.textColor;
                chart.options.plugins.legend.labels.color = nt.textColor;
                chart.update();
            });
        })();
    <?php endif; ?>
    </script>
</body>

</html>