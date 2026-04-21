<?php
/**
 * bagas.php — Halaman Publik Utama InfoHarga Komoditi (UI Updated)
 * ─────────────────────────────────────────────────────────────
 */
session_start();
require 'Server/koneksi.php';

$pageTitle = 'Transparansi Harga Pangan Indonesia';
$pageDesc = 'Pantau harga komoditas pangan terkini dari 38 provinsi Indonesia. Data beras, cabai, bawang, minyak goreng secara real-time.';

// ── Query data approved untuk ticker dan statistik ───────────
$resKom = $conn->query("SELECT * FROM komoditas WHERE status='approved' ORDER BY nama ASC");
$allKom = [];
$ticker_html = '';
$namaUnik = [];

if ($resKom) {
    while ($r = $resKom->fetch_assoc()) {
        $allKom[] = $r;
        $namaUnik[] = $r['nama'];
        $s = (int) $r['harga_sekarang'];
        $k = (int) $r['harga_kemarin'];

        $trend_icon = "■";
        $trend_color = "text-slate-400 dark:text-slate-500";
        if ($s > $k) {
            $trend_icon = "▲";
            $trend_color = "text-emerald-500 dark:text-emerald-400";
        } elseif ($s < $k) {
            $trend_icon = "▼";
            $trend_color = "text-red-500 dark:text-red-400";
        }

        $harga_format = "Rp " . number_format($s, 0, ',', '.');

        // Elemen HTML Marquee ala index.php
        $ticker_html .= "<span class='inline-flex items-center gap-2 mx-6'>
                            <span class='font-medium text-slate-600 dark:text-slate-400'>{$r['nama']} ({$r['lokasi']})</span> 
                            <span class='text-slate-900 dark:text-white font-bold tracking-wide'>{$harga_format}</span>
                            <span class='{$trend_color} text-xs'>{$trend_icon}</span>
                         </span>";
    }
}
$namaUnik = array_unique($namaUnik);
$totalKom = count($allKom);
$totalProv = count(array_unique(array_filter(array_column($allKom, 'provinsi'))));
$totalKont = (int) ($conn->query("SELECT COUNT(*) c FROM users WHERE role IN ('kontributor','admin') AND is_active=1")?->fetch_assoc()['c'] ?? 0);

// ── Query artikel published dari database ────────────────────
$resArt = $conn->query("SELECT * FROM artikel WHERE is_publish=1 ORDER BY created_at DESC LIMIT 8");
$artikel = [];
if ($resArt) while ($a = $resArt->fetch_assoc()) $artikel[] = $a;

// ── Fetch artikel dari BPS Press Release API ──────────────────
// Selalu fetch dari BPS, gabungkan dengan artikel DB
// Endpoint: /v1/api/list/model/pressrelease/domain/0000/lang/ind/key/{KEY}
$bps_url = 'https://webapi.bps.go.id/v1/api/list/model/pressrelease/domain/0000/lang/ind/key/4e182f178f0d964814488d42593f2594/';
$ctx     = stream_context_create([
    'http' => ['timeout'=>8, 'user_agent'=>'InfoHarga-Komoditi/4.0', 'method'=>'GET'],
    'ssl'  => ['verify_peer'=>false, 'verify_peer_name'=>false],
]);
$bps_raw  = @file_get_contents($bps_url, false, $ctx);
$bps_artikel = [];
if ($bps_raw) {
    $bps_data = json_decode($bps_raw, true);
    if (($bps_data['status'] ?? '') === 'OK') {
        $bps_releases = $bps_data['data'][1] ?? [];
        // Ambil max 6 rilis terbaru BPS
        foreach (array_slice($bps_releases, 0, 6) as $rel) {
            $judul    = $rel['subject']  ?? $rel['title']    ?? 'Rilis Pers BPS';
            $tgl      = $rel['brs_date'] ?? $rel['rl_date']  ?? '';
            $pdf_url  = $rel['pdf']      ?? $rel['rl_url']   ?? '';
            $abstract = $rel['abstract'] ?? $rel['intro']    ?? '';
            // Format tanggal BPS: "dd-mm-yyyy" → simpan apa adanya
            $bps_artikel[] = [
                'id'         => 'bps_'.($rel['brs_id'] ?? uniqid()),
                'judul'      => $judul,
                'slug'       => '',
                'ringkasan'  => $abstract ?: 'Rilis resmi dari Badan Pusat Statistik Indonesia.',
                'kategori'   => 'Statistik BPS',
                'emoji'      => '📊',
                'menit_baca' => 3,
                'sumber_url' => $pdf_url,
                'sumber_nama'=> 'BPS Indonesia',
                'is_publish' => 1,
                'views'      => 0,
                'created_at' => $tgl,
                'is_bps'     => true,
            ];
        }
    }
}
// Gabungkan: artikel DB dulu, lalu artikel BPS di belakang
// Maksimal 12 artikel total agar halaman tidak terlalu panjang
$artikel = array_slice(array_merge($artikel, $bps_artikel), 0, 12);

// ── Daftar Provinsi ───────────────────────────────────────────
if (!defined('PROVINSI_LIST')) {
    define('PROVINSI_LIST', [
        'Aceh',
        'Sumatera Utara',
        'Sumatera Barat',
        'Riau',
        'Kepulauan Riau',
        'Jambi',
        'Bengkulu',
        'Sumatera Selatan',
        'Kepulauan Bangka Belitung',
        'Lampung',
        'Banten',
        'DKI Jakarta',
        'Jawa Barat',
        'Jawa Tengah',
        'DI Yogyakarta',
        'Jawa Timur',
        'Bali',
        'Nusa Tenggara Barat',
        'Nusa Tenggara Timur',
        'Kalimantan Barat',
        'Kalimantan Tengah',
        'Kalimantan Selatan',
        'Kalimantan Timur',
        'Kalimantan Utara',
        'Sulawesi Utara',
        'Gorontalo',
        'Sulawesi Tengah',
        'Sulawesi Barat',
        'Sulawesi Selatan',
        'Sulawesi Tenggara',
        'Maluku',
        'Maluku Utara',
        'Papua Barat',
        'Papua Barat Daya',
        'Papua',
        'Papua Selatan',
        'Papua Tengah',
        'Papua Pegunungan'
    ]);
}
?>
<!doctype html>
<html lang="id" class="scroll-smooth">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= $pageTitle ?> - InfoHarga</title>
    <meta name="description" content="<?= htmlspecialchars($pageDesc) ?>">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <script>
        // Konfigurasi awal Dark Mode (mencegah kedipan putih saat diload)
        if (localStorage.getItem('ih-theme') === 'dark' || (!localStorage.getItem('ih-theme') && window.matchMedia(
            '(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark')
        } else {
            document.documentElement.classList.remove('dark')
        }

        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ["Inter", "sans-serif"]
                    },
                    animation: {
                        'marquee': 'marquee 45s linear infinite',
                        'float': 'float 6s ease-in-out infinite',
                    },
                    keyframes: {
                        marquee: {
                            '0%': {
                                transform: 'translateX(0%)'
                            },
                            '100%': {
                                transform: 'translateX(-50%)'
                            },
                        },
                        float: {
                            '0%, 100%': {
                                transform: 'translateY(0)'
                            },
                            '50%': {
                                transform: 'translateY(-20px)'
                            },
                        }
                    }
                },
            },
        };
    </script>

    <style>
        /* FIX: @keyframes HARUS didefinisikan di CSS, bukan hanya di tailwind.config
           Tailwind CDN tidak men-generate custom keyframes dari config object */
        @keyframes marquee {
            0%   { transform: translateX(0%); }
            100% { transform: translateX(-50%); }
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50%       { transform: translateY(-20px); }
        }
        /* Apply animation class */
        .animate-marquee {
            animation: marquee 45s linear infinite;
            will-change: transform;
        }
        .animate-marquee:hover { animation-play-state: paused; }

        .slim-scroll::-webkit-scrollbar { width: 4px; }
        .slim-scroll::-webkit-scrollbar-track { background: transparent; }
        .slim-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .dark .slim-scroll::-webkit-scrollbar-thumb { background: #334155; }

        /* Smooth body bg transition */
        body { transition: background-color .25s, color .25s; }
    </style>
</head>

<body
    class="bg-slate-50 dark:bg-[#131722] text-slate-800 dark:text-slate-300 antialiased overflow-x-hidden selection:bg-emerald-500/30 transition-colors duration-300">

    <div
        class="fixed top-0 w-full z-50 bg-white dark:bg-[#0b0e14] h-10 flex items-center overflow-hidden border-b border-slate-200 dark:border-white/5 shadow-sm dark:shadow-md transition-colors duration-300">
        <div
            class="bg-emerald-600 h-full px-4 md:px-6 flex items-center justify-center z-10 relative shadow-[4px_0_10px_rgba(0,0,0,0.1)] dark:shadow-[4px_0_10px_rgba(0,0,0,0.5)] flex-shrink-0">
            <span class="font-bold text-xs md:text-sm tracking-wide flex items-center gap-2 text-white">
                <i data-lucide="radio" class="w-4 h-4 animate-pulse"></i> LIVE UPDATE
            </span>
            <div
                class="absolute right-[-10px] top-0 w-0 h-0 border-t-[20px] border-t-transparent border-b-[20px] border-b-transparent border-l-[10px] border-l-emerald-600">
            </div>
        </div>

        <div
            class="flex-1 overflow-hidden relative flex items-center h-full bg-slate-50 dark:bg-[#0b0e14] transition-colors duration-300">
            <div
                class="whitespace-nowrap text-sm flex items-center w-max <?php echo !empty($ticker_html) ? 'animate-marquee' : ''; ?>">
                <?php if ($ticker_html): ?>
                    <?= $ticker_html; ?>
                    <?= $ticker_html; ?> <?php else: ?>
                    <span class="mx-6 text-slate-500">Belum ada data komoditas.</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <nav
        class="fixed w-full z-40 top-10 bg-white/80 dark:bg-[#131722]/80 backdrop-blur-md border-b border-slate-200 dark:border-white/5 transition-all duration-300 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <div class="flex items-center gap-3 cursor-pointer">
                    <div class="bg-emerald-500 p-2.5 rounded-xl shadow-lg shadow-emerald-500/20">
                        <i data-lucide="line-chart" class="w-6 h-6 text-white"></i>
                    </div>
                    <span class="font-bold text-2xl tracking-tight text-slate-900 dark:text-white">InfoHarga</span>
                </div>

                <div class="hidden md:flex items-center space-x-6">
                    <a href="#artikel"
                        class="font-medium text-slate-600 dark:text-slate-400 hover:text-emerald-500 dark:hover:text-emerald-400 transition">Artikel</a>
                    <a href="chart.php"
                        class="font-medium text-slate-600 dark:text-slate-400 hover:text-emerald-500 dark:hover:text-emerald-400 transition">Grafik</a>

                    <button id="themeToggle"
                        class="p-2.5 rounded-full bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-white/20 transition-colors">
                        <i data-lucide="moon" class="w-5 h-5 dark:hidden"></i>
                        <i data-lucide="sun" class="w-5 h-5 hidden dark:block"></i>
                    </button>

                    <?php if (isset($_SESSION['login'])): ?>
                        <a href="dashboard-user.php"
                            class="bg-emerald-600 hover:bg-emerald-700 border border-transparent text-white px-5 py-2.5 rounded-full font-medium transition flex items-center gap-2 backdrop-blur-sm shadow-lg shadow-emerald-500/30">
                            <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard
                        </a>
                    <?php else: ?>
                        <a href="login.php"
                            class="bg-slate-900 dark:bg-white/10 hover:bg-slate-800 dark:hover:bg-white/20 border border-transparent dark:border-white/10 text-white px-5 py-2.5 rounded-full font-medium transition flex items-center gap-2 backdrop-blur-sm">
                            <i data-lucide="user" class="w-4 h-4"></i> Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <section class="relative pt-40 pb-16 lg:pt-48 lg:pb-24 overflow-hidden flex flex-col items-center text-center">
        <div
            class="absolute top-20 left-1/2 -translate-x-1/2 w-[600px] h-[400px] bg-emerald-500/10 dark:bg-emerald-500/20 blur-[120px] rounded-full pointer-events-none z-0">
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 w-full">
            <div
                class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-50 dark:bg-white/5 border border-emerald-200 dark:border-white/10 text-emerald-600 dark:text-emerald-400 text-sm font-semibold mb-8 backdrop-blur-sm">
                <i data-lucide="zap" class="w-4 h-4"></i> Update Harian Terverifikasi
            </div>

            <h1
                class="text-5xl md:text-7xl font-black text-slate-900 dark:text-white tracking-tight mb-6 leading-[1.1]">
                Pantau Harga Pangan.<br />
                <span
                    class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-500 to-cyan-500 dark:from-emerald-400 dark:to-cyan-400">Secara
                    Real-Time.</span>
            </h1>

            <p class="text-lg md:text-xl text-slate-600 dark:text-slate-400 mb-12 max-w-2xl mx-auto leading-relaxed">
                Bebaskan diri dari ketidakpastian pasar. InfoHarga memberikan data komoditas pertanian, peternakan &
                perikanan dari 38 provinsi Indonesia — akurat dan transparan.
            </p>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-4 w-full sm:w-auto mb-16">
                <a href="<?= isset($_SESSION['login']) ? 'dashboard-user.php' : 'register.php' ?>"
                    class="flex items-center justify-center gap-3 w-full sm:w-auto px-8 py-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-semibold transition shadow-[0_0_20px_rgba(16,185,129,0.3)] dark:shadow-[0_0_20px_rgba(16,185,129,0.4)]">
                    <i data-lucide="<?= isset($_SESSION['login']) ? 'layout-dashboard' : 'user-plus' ?>"
                        class="w-5 h-5"></i>
                    <?= isset($_SESSION['login']) ? 'Buka Dashboard' : 'Mulai Gratis' ?>
                </a>

                <a href="chart.php"
                    class="flex items-center justify-center gap-3 w-full sm:w-auto px-8 py-4 bg-white dark:bg-white/10 hover:bg-slate-50 dark:hover:bg-white/20 border border-slate-200 dark:border-white/10 text-slate-900 dark:text-white rounded-xl font-semibold transition backdrop-blur-sm shadow-sm dark:shadow-none">
                    <i data-lucide="bar-chart-2" class="w-5 h-5 text-emerald-500"></i>
                    Lihat Grafik Harga
                </a>
            </div>
        </div>
    </section>

    <section class="relative z-10 pb-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <div
                    class="bg-white dark:bg-[#1e222d] border border-slate-200 dark:border-white/10 rounded-2xl p-6 text-center shadow-lg transition-colors duration-300">
                    <div class="text-3xl md:text-4xl font-black text-slate-900 dark:text-white mb-2">38</div>
                    <div
                        class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider flex items-center justify-center gap-2">
                        <i data-lucide="map" class="w-4 h-4"></i> Provinsi
                    </div>
                </div>
                <div
                    class="bg-white dark:bg-[#1e222d] border border-slate-200 dark:border-white/10 rounded-2xl p-6 text-center shadow-lg transition-colors duration-300">
                    <div class="text-3xl md:text-4xl font-black text-slate-900 dark:text-white mb-2"><?= $totalKom ?>
                    </div>
                    <div
                        class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider flex items-center justify-center gap-2">
                        <i data-lucide="layers" class="w-4 h-4"></i> Komoditas
                    </div>
                </div>
                <div
                    class="bg-white dark:bg-[#1e222d] border border-slate-200 dark:border-white/10 rounded-2xl p-6 text-center shadow-lg transition-colors duration-300">
                    <div class="text-3xl md:text-4xl font-black text-slate-900 dark:text-white mb-2"><?= $totalKont ?>K+
                    </div>
                    <div
                        class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider flex items-center justify-center gap-2">
                        <i data-lucide="users" class="w-4 h-4"></i> Kontributor
                    </div>
                </div>
                <div
                    class="bg-white dark:bg-[#1e222d] border border-emerald-200 dark:border-emerald-500/30 bg-emerald-50/50 dark:bg-emerald-500/5 rounded-2xl p-6 text-center shadow-lg transition-colors duration-300">
                    <div class="text-3xl md:text-4xl font-black text-emerald-600 dark:text-emerald-400 mb-2">24/7</div>
                    <div
                        class="text-sm font-semibold text-emerald-600 dark:text-emerald-500 uppercase tracking-wider flex items-center justify-center gap-2">
                        <i data-lucide="zap" class="w-4 h-4"></i> Update Data
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ══ HARGA TERKINI CARDS ══════════════════════════════ -->
    <?php if (!empty($allKom)): ?>
    <section class="py-10 relative z-10">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
          <div>
            <p class="text-sm text-emerald-500 font-bold uppercase tracking-widest mb-1">Real-time</p>
            <h2 class="text-2xl font-black text-slate-900 dark:text-white">Harga Terkini</h2>
          </div>
          <a href="<?= isset($_SESSION['login']) ? 'chart.php' : 'login.php' ?>"
             class="text-sm font-semibold text-emerald-600 dark:text-emerald-400 hover:underline flex items-center gap-1">
            Lihat semua <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
          </a>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
          <?php foreach(array_slice($allKom,0,10) as $km):
            $ks=(int)$km['harga_sekarang']; $kk=(int)$km['harga_kemarin'];
            $naik=$ks>$kk; $turun=$ks<$kk;
          ?>
          <div class="bg-white dark:bg-[#1e222d] border border-slate-200 dark:border-white/10 rounded-2xl p-4 hover:border-emerald-500/40 dark:hover:border-emerald-500/40 hover:shadow-lg transition-all duration-200 group cursor-pointer"
               onclick="<?= isset($_SESSION['login']) ? "location.href='chart.php?komoditas=".urlencode($km['nama'])."'" : "location.href='login.php'" ?>">
            <div class="flex items-start justify-between mb-3">
              <span class="text-[10px] font-bold uppercase tracking-wider <?= $naik?'text-emerald-500':($turun?'text-red-400':'text-slate-400') ?> px-2 py-0.5 rounded-full <?= $naik?'bg-emerald-50 dark:bg-emerald-500/10':($turun?'bg-red-50 dark:bg-red-500/10':'bg-slate-100 dark:bg-slate-800') ?>">
                <?= $naik?'▲ Naik':($turun?'▼ Turun':'■ Stabil') ?>
              </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-0.5 truncate"><?= htmlspecialchars($km['nama']) ?></p>
            <p class="text-xs text-slate-400 dark:text-slate-500 mb-2 truncate"><?= htmlspecialchars($km['lokasi']) ?></p>
            <p class="font-black text-base <?= $naik?'text-emerald-600 dark:text-emerald-400':($turun?'text-red-500 dark:text-red-400':'text-slate-900 dark:text-white') ?>">
              Rp <?= number_format($ks,0,',','.') ?>
            </p>
            <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">per <?= htmlspecialchars($km['satuan']??'kg') ?></p>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <section id="artikel"
        class="py-16 bg-slate-50/50 dark:bg-[#0b0e14]/50 border-t border-slate-200 dark:border-white/5 relative z-10 transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

                <div class="lg:col-span-8 space-y-6">
                    <div class="flex items-end justify-between mb-6">
                        <div>
                            <p class="text-sm text-emerald-500 font-bold uppercase tracking-widest mb-1">Edukasi</p>
                            <h2 class="text-3xl font-black text-slate-900 dark:text-white">Artikel Komoditas</h2>
                        </div>
                        <span
                            class="text-sm font-medium text-slate-500 dark:text-slate-400 bg-slate-200 dark:bg-white/10 px-3 py-1 rounded-full"><?= count($artikel) ?>
                            artikel</span>
                    </div>

                    <?php if (!empty($artikel)): ?>
                        <div class="space-y-4">
                            <?php foreach ($artikel as $art): ?>
                                <article
                                    class="bg-white dark:bg-[#1e222d] border border-slate-200 dark:border-white/10 rounded-2xl p-5 flex gap-5 cursor-pointer hover:border-emerald-500/50 dark:hover:border-emerald-500/50 hover:shadow-xl transition-all duration-300 group"
                                    onclick="<?php if(!empty($art['is_bps']) && !empty($art['sumber_url'])): ?>window.open('<?= htmlspecialchars($art['sumber_url']) ?>','_blank')<?php elseif($art['slug']): ?>location.href='artikel.php?slug=<?= urlencode($art['slug']) ?>'<?php endif; ?>">
                                    <div
                                        class="w-16 h-16 flex-shrink-0 rounded-2xl bg-slate-100 dark:bg-[#131722] flex items-center justify-center text-3xl select-none shadow-inner border border-slate-200 dark:border-white/5">
                                        <?= htmlspecialchars($art['emoji']) ?>
                                    </div>
                                    <div class="flex-1 min-w-0 flex flex-col justify-center">
                                        <div class="flex flex-wrap items-center gap-3 mb-2">
                                            <span
                                                class="px-2.5 py-1 rounded-md <?= !empty($art['is_bps']) ? 'bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-400' : 'bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400' ?> text-xs font-bold tracking-wide"><?= htmlspecialchars($art['kategori']) ?></span>
                                            <?php if(!empty($art['is_bps'])): ?>
                                            <span class="px-2 py-0.5 rounded bg-blue-500/10 text-blue-500 text-[10px] font-bold uppercase tracking-wider flex items-center gap-1"><i data-lucide="external-link" class="w-2.5 h-2.5"></i> BPS</span>
                                            <?php endif; ?>
                                            <span
                                                class="text-xs font-medium text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                                                <i data-lucide="clock" class="w-3.5 h-3.5"></i> <?= (int) $art['menit_baca'] ?>
                                                menit baca
                                            </span>
                                            <?php if(!empty($art['sumber_nama'])): ?>
                                            <span class="text-xs text-slate-400 dark:text-slate-500 flex items-center gap-1">
                                                <i data-lucide="database" class="w-3 h-3"></i> <?= htmlspecialchars($art['sumber_nama']) ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        <h3
                                            class="text-lg font-bold text-slate-900 dark:text-white leading-snug mb-2 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">
                                            <?= htmlspecialchars($art['judul']) ?>
                                        </h3>
                                        <p class="text-slate-600 dark:text-slate-400 text-sm leading-relaxed line-clamp-2">
                                            <?= htmlspecialchars($art['ringkasan']) ?>
                                        </p>
                                    </div>
                                    <div class="flex-shrink-0 self-center hidden sm:flex">
                                        <div
                                            class="w-10 h-10 rounded-full bg-slate-50 dark:bg-white/5 group-hover:bg-emerald-500 group-hover:text-white flex items-center justify-center transition-colors shadow-sm">
                                            <i data-lucide="arrow-right"
                                                class="w-5 h-5 text-slate-400 group-hover:text-white transition-colors"></i>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div
                            class="bg-white dark:bg-[#1e222d] border border-slate-200 dark:border-white/10 rounded-2xl p-10 text-center text-slate-500 dark:text-slate-400 shadow-sm">
                            Belum ada artikel tersedia.
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($namaUnik)): ?>
                        <div
                            class="bg-white dark:bg-[#1e222d] border border-slate-200 dark:border-white/10 rounded-2xl p-6 shadow-sm mt-8 transition-colors duration-300">
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
                                <i data-lucide="tag" class="w-5 h-5 text-emerald-500"></i> Komoditas Tersedia
                            </h3>
                            <div class="flex flex-wrap gap-2.5">
                                <?php foreach ($namaUnik as $nm): ?>
                                    <a href="<?= isset($_SESSION['login']) ? 'chart.php?komoditas=' . urlencode($nm) : 'login.php' ?>"
                                        class="px-4 py-2 rounded-xl text-sm font-medium transition bg-slate-50 dark:bg-[#131722] hover:bg-emerald-50 dark:hover:bg-emerald-500/10 border border-slate-200 dark:border-white/10 hover:border-emerald-500/50 text-slate-700 dark:text-slate-300 hover:text-emerald-600 dark:hover:text-emerald-400 shadow-sm">
                                        <?= htmlspecialchars($nm) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            <?php if (!isset($_SESSION['login'])): ?>
                                <p class="mt-4 text-sm text-slate-500 dark:text-slate-400 flex items-center gap-2">
                                    <i data-lucide="lock" class="w-4 h-4 text-slate-400"></i>
                                    <span><a href="login.php"
                                            class="text-emerald-600 dark:text-emerald-400 font-semibold hover:underline">Login</a>
                                        untuk melihat grafik detail per komoditas.</span>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!isset($_SESSION['login'])): ?>
                        <div
                            class="bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 rounded-2xl p-6 mt-8 shadow-sm">
                            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5">
                                <div
                                    class="w-12 h-12 bg-emerald-100 dark:bg-emerald-500/20 rounded-xl flex items-center justify-center flex-shrink-0">
                                    <i data-lucide="shield-check"
                                        class="w-6 h-6 text-emerald-600 dark:text-emerald-400"></i>
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-1">Akses Data Lebih
                                        Lengkap</h3>
                                    <p class="text-slate-600 dark:text-slate-400 text-sm">Daftar gratis untuk melihat grafik
                                        harga detail, histori 7 hari, dan pengumuman eksklusif.</p>
                                </div>
                                <div class="flex flex-col w-full sm:w-auto gap-3 mt-4 sm:mt-0 shrink-0">
                                    <a href="register.php"
                                        class="flex items-center justify-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-xl transition shadow-md">
                                        <i data-lucide="user-plus" class="w-4 h-4"></i> Daftar Gratis
                                    </a>
                                    <a href="login.php"
                                        class="flex items-center justify-center gap-2 px-5 py-2.5 bg-white dark:bg-white/5 hover:bg-slate-50 dark:hover:bg-white/10 border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300 text-sm font-bold rounded-xl transition">
                                        Login
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="lg:col-span-4">
                    <div class="bg-white dark:bg-[#1e222d] border border-slate-200 dark:border-white/10 rounded-2xl shadow-lg sticky top-28 flex flex-col overflow-hidden transition-colors duration-300"
                        style="max-h: calc(100vh - 8rem);">
                        <div
                            class="p-5 border-b border-slate-200 dark:border-white/5 flex-shrink-0 bg-slate-50/50 dark:bg-[#131722]/50">
                            <h2 class="font-bold text-slate-900 dark:text-white text-lg flex items-center gap-2">
                                <i data-lucide="map-pin" class="w-5 h-5 text-emerald-500"></i> Harga per Provinsi
                            </h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">Data 38 Provinsi di
                                Indonesia</p>
                        </div>

                        <div class="p-4 border-b border-slate-200 dark:border-white/5 flex-shrink-0">
                            <div class="relative">
                                <i data-lucide="search"
                                    class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                                <input id="searchProv" type="search" placeholder="Cari provinsi..."
                                    class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-[#131722] text-slate-800 dark:text-white text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-colors duration-300"
                                    autocomplete="off" />
                            </div>
                        </div>

                        <div class="overflow-y-auto flex-1 slim-scroll" id="listProv">
                            <?php foreach (PROVINSI_LIST as $idx => $prov): ?>
                                <a href="<?= isset($_SESSION['login']) ? 'chart.php?provinsi=' . urlencode($prov) : 'login.php' ?>"
                                    class="prov-row flex items-center justify-between px-5 py-3 border-b border-slate-100 dark:border-white/5 hover:bg-slate-50 dark:hover:bg-white/5 transition-colors group"
                                    data-name="<?= strtolower($prov) ?>">
                                    <div class="flex items-center gap-3.5">
                                        <span
                                            class="w-6 h-6 rounded-md flex items-center justify-center text-[10px] font-bold text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-[#131722] group-hover:text-emerald-600 dark:group-hover:text-emerald-400 group-hover:bg-emerald-50 dark:group-hover:bg-emerald-500/10 transition-colors flex-shrink-0 border border-slate-200 dark:border-white/5"><?= $idx + 1 ?></span>
                                        <span
                                            class="text-sm font-medium text-slate-600 dark:text-slate-300 group-hover:text-slate-900 dark:group-hover:text-white transition-colors"><?= htmlspecialchars($prov) ?></span>
                                    </div>
                                    <i data-lucide="chevron-right"
                                        class="w-4 h-4 text-slate-300 dark:text-slate-600 group-hover:text-emerald-500 group-hover:translate-x-1 flex-shrink-0 transition-all duration-300"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>

                        <div
                            class="p-4 border-t border-slate-200 dark:border-white/5 flex-shrink-0 bg-slate-50 dark:bg-[#131722]">
                            <?php if (isset($_SESSION['login'])): ?>
                                <a href="chart.php"
                                    class="flex items-center justify-center gap-2 text-sm font-bold text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 transition-colors py-2 rounded-xl hover:bg-emerald-50 dark:hover:bg-emerald-500/10">
                                    <i data-lucide="bar-chart-2" class="w-4 h-4"></i> Lihat Semua Grafik
                                </a>
                            <?php else: ?>
                                <a href="login.php"
                                    class="flex items-center justify-center gap-2 text-sm font-bold text-slate-500 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors py-2 rounded-xl hover:bg-slate-100 dark:hover:bg-white/5">
                                    <i data-lucide="lock" class="w-4 h-4"></i> Login untuk Akses Penuh
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ══ CTA SECTION ══════════════════════════════════════ -->
    <?php if (!isset($_SESSION['login'])): ?>
    <section class="py-16 relative z-10">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-gradient-to-br from-emerald-600 to-teal-600 dark:from-emerald-700 dark:to-teal-700 rounded-3xl p-10 md:p-14 text-center relative overflow-hidden">
          <div class="absolute inset-0 opacity-10" style="background-image:radial-gradient(circle at 20% 50%,white 1px,transparent 1px),radial-gradient(circle at 80% 20%,white 1px,transparent 1px);background-size:30px 30px;"></div>
          <div class="relative z-10">
            <span class="inline-flex items-center gap-2 bg-white/20 text-white text-xs font-bold px-4 py-1.5 rounded-full mb-6 uppercase tracking-wider">
              <i data-lucide="users" class="w-3.5 h-3.5"></i> Bergabunglah Bersama Kami
            </span>
            <h2 class="text-4xl md:text-5xl font-black text-white mb-4 leading-tight">
              Mulai Pantau Harga<br/>Komoditas Sekarang
            </h2>
            <p class="text-white/80 text-lg mb-8 max-w-xl mx-auto">Daftar gratis dan dapatkan akses penuh ke grafik harga, histori 7 hari, dan data seluruh Indonesia.</p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
              <a href="register.php" class="flex items-center gap-2 px-8 py-4 bg-white text-emerald-700 rounded-xl font-bold text-sm hover:bg-emerald-50 transition shadow-xl">
                <i data-lucide="user-plus" class="w-4 h-4"></i> Daftar Gratis — Mulai Sekarang
              </a>
              <a href="login.php" class="flex items-center gap-2 px-8 py-4 bg-white/15 hover:bg-white/25 text-white rounded-xl font-semibold text-sm transition border border-white/20">
                Sudah punya akun? Login
              </a>
            </div>
          </div>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <footer class="bg-slate-950 dark:bg-[#0b0e14] text-slate-400 py-14 border-t border-slate-900 dark:border-white/5 relative z-10 transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="grid grid-cols-1 md:grid-cols-4 gap-10 mb-10">
            <!-- Brand -->
            <div class="md:col-span-2">
              <div class="flex items-center gap-2 mb-4">
                <div class="bg-emerald-500 p-2 rounded-xl shadow-lg shadow-emerald-500/30">
                  <i data-lucide="line-chart" class="w-5 h-5 text-white"></i>
                </div>
                <span class="font-bold text-xl text-white">InfoHarga<span class="text-emerald-400">Komoditi</span></span>
              </div>
              <p class="text-sm text-slate-500 leading-relaxed max-w-sm">Platform transparansi harga komoditas pangan Indonesia. Data real-time dari kontributor lapangan terverifikasi di 38 provinsi.</p>
              <div class="flex items-center gap-1.5 mt-4">
                <span class="w-2 h-2 bg-emerald-500 rounded-full" style="animation:pulse 2s infinite"></span>
                <span class="text-xs text-emerald-500 font-semibold">Data diperbarui setiap hari</span>
              </div>
            </div>
            <!-- Navigasi -->
            <div>
              <h4 class="text-white font-bold text-sm mb-4 uppercase tracking-wider">Navigasi</h4>
              <ul class="space-y-2.5 text-sm">
                <li><a href="index.php" class="text-slate-500 hover:text-emerald-400 transition">Beranda</a></li>
                <li><a href="chart.php" class="text-slate-500 hover:text-emerald-400 transition">Grafik Harga</a></li>
                <li><a href="#artikel" class="text-slate-500 hover:text-emerald-400 transition">Artikel Edukasi</a></li>
                <li><a href="login.php" class="text-slate-500 hover:text-emerald-400 transition">Login</a></li>
                <li><a href="register.php" class="text-slate-500 hover:text-emerald-400 transition">Daftar</a></li>
              </ul>
            </div>
            <!-- Referensi -->
            <div>
              <h4 class="text-white font-bold text-sm mb-4 uppercase tracking-wider">Sumber Data</h4>
              <ul class="space-y-2.5 text-sm">
                <li><a href="https://webapi.bps.go.id" target="_blank" rel="noopener" class="text-slate-500 hover:text-emerald-400 transition flex items-center gap-1.5"><i data-lucide="external-link" class="w-3 h-3"></i> Web API BPS</a></li>
                <li><a href="https://www.bps.go.id" target="_blank" rel="noopener" class="text-slate-500 hover:text-emerald-400 transition flex items-center gap-1.5"><i data-lucide="external-link" class="w-3 h-3"></i> BPS Indonesia</a></li>
                <li><a href="https://hargapangan.id" target="_blank" rel="noopener" class="text-slate-500 hover:text-emerald-400 transition flex items-center gap-1.5"><i data-lucide="external-link" class="w-3 h-3"></i> Harga Pangan</a></li>
              </ul>
            </div>
          </div>
          <div class="pt-8 border-t border-slate-900 dark:border-white/5 flex flex-col sm:flex-row justify-between items-center gap-3">
            <p class="text-xs text-slate-600">&copy; <?= date('Y') ?> InfoHarga Komoditi. Seluruh hak cipta dilindungi.</p>
            <p class="text-xs text-slate-600">Data bersumber dari <a href="https://webapi.bps.go.id" target="_blank" class="text-emerald-600 hover:underline">BPS Indonesia</a> & kontributor lapangan</p>
          </div>
        </div>
    </footer>

    <script>
        // Inisialisasi Icons
        lucide.createIcons();

        // Theme Toggle Logic — pakai kunci 'ih-theme' agar sync dengan semua halaman lain
        const themeToggleBtn = document.getElementById('themeToggle');
        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', function () {
                const isDark = document.documentElement.classList.contains('dark');
                if (isDark) {
                    document.documentElement.classList.remove('dark');
                    localStorage.setItem('ih-theme', 'light');
                } else {
                    document.documentElement.classList.add('dark');
                    localStorage.setItem('ih-theme', 'dark');
                }
                // Update icon: moon saat light, sun saat dark
                this.querySelectorAll('i[data-lucide]').forEach(function(ic) {
                    lucide.createIcons({ nodes: [ic] });
                });
            });
        }

        // Search Provinsi
        document.getElementById('searchProv')?.addEventListener('input', function () {
            const q = this.value.toLowerCase().trim();
            document.querySelectorAll('#listProv .prov-row').forEach(r => {
                r.style.display = !q || r.dataset.name?.includes(q) ? '' : 'none';
            });
        });
    </script>
</body>

</html>