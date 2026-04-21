<?php
/**
 * profil_publik.php — Halaman Profil Publik User
 * ─────────────────────────────────────────────────────────────
 * Bisa dilihat oleh semua user yang sudah login.
 * Parameter: ?u=username  ATAU  ?id=user_id
 * ─────────────────────────────────────────────────────────────
 */
session_start();
require 'Server/koneksi.php';
cekLogin();

$myUid = (int)$_SESSION['user_id'];

// Ambil user target
$targetUsername = esc($conn, trim($_GET['u'] ?? ''));
$targetId       = (int)($_GET['id'] ?? 0);

if ($targetUsername) {
    $res = $conn->query("SELECT * FROM users WHERE username='$targetUsername' AND is_active=1 LIMIT 1");
} elseif ($targetId) {
    $res = $conn->query("SELECT * FROM users WHERE id=$targetId AND is_active=1 LIMIT 1");
} else {
    redirect('dashboard-user.php');
}

$user = $res?->fetch_assoc();
if (!$user) {
    // User tidak ditemukan
    http_response_code(404);
    redirect('404.php');
}

$isMyself = ($user['id'] == $myUid);
$role     = $user['role'];

// Statistik user
$totalLaporan  = (int)$conn->query("SELECT COUNT(*) c FROM komoditas WHERE submitted_by={$user['id']}")?->fetch_assoc()['c'];
$totalApproved = (int)$conn->query("SELECT COUNT(*) c FROM komoditas WHERE submitted_by={$user['id']} AND status='approved'")?->fetch_assoc()['c'];
$totalArtikel  = (int)$conn->query("SELECT COUNT(*) c FROM artikel WHERE penulis_id={$user['id']} AND is_publish=1")?->fetch_assoc()['c'];
$totalDiskusi  = (int)$conn->query("SELECT COUNT(*) c FROM diskusi WHERE user_id={$user['id']} AND is_deleted=0")?->fetch_assoc()['c'];

// 5 laporan terbaru user (yang approved)
$resLaporan = $conn->query("SELECT nama,lokasi,provinsi,harga_sekarang,harga_kemarin,satuan,updated_at
                             FROM komoditas WHERE submitted_by={$user['id']} AND status='approved'
                             ORDER BY updated_at DESC LIMIT 5");
$laporanList = [];
if ($resLaporan) while ($r = $resLaporan->fetch_assoc()) $laporanList[] = $r;

// 5 komentar terbaru user di diskusi
$resDiskusi = $conn->query("SELECT d.pesan, d.created_at, k.nama as kom_nama, k.id as kom_id
                             FROM diskusi d
                             LEFT JOIN komoditas k ON d.komoditas_id=k.id
                             WHERE d.user_id={$user['id']} AND d.is_deleted=0
                             ORDER BY d.created_at DESC LIMIT 5");
$diskusiList = [];
if ($resDiskusi) while ($r = $resDiskusi->fetch_assoc()) $diskusiList[] = $r;

$roleBadge = ['admin_master'=>['badge-purple','Admin Master'],'admin'=>['badge-green','Admin'],'kontributor'=>['badge-blue','Kontributor'],'user'=>['badge-slate','Pengguna']];
[$bc,$bl] = $roleBadge[$role] ?? ['badge-slate','User'];

$dashBack = in_array($_SESSION['role'],['admin','admin_master']) ? 'dashboard.php' : 'dashboard-user.php';
$pageTitle = 'Profil '.htmlspecialchars($user['username']);
?>
<!doctype html>
<html lang="id">
<head><?php include 'Assets/head.php'; ?>
<style>
  .prof-cover{height:120px;background:linear-gradient(135deg,#059669 0%,#0891b2 100%);border-radius:1rem 1rem 0 0;position:relative}
  .avatar-xl{width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#10b981,#059669);display:flex;align-items:center;justify-content:center;font-family:'Cabinet Grotesk',sans-serif;font-size:2rem;font-weight:900;color:#fff;border:4px solid var(--bg-primary);position:absolute;bottom:-40px;left:1.5rem}
  .stat-pill{display:flex;flex-direction:column;align-items:center;padding:.75rem 1rem;background:var(--surface);border:1px solid var(--border);border-radius:.875rem;min-width:80px}
  .activity-row{display:flex;align-items:flex-start;gap:.75rem;padding:.75rem 0;border-bottom:1px solid var(--border)}
  .activity-row:last-child{border:none}
</style>
</head>
<body class="bg-[var(--bg-primary)]">

<!-- Navbar mini -->
<div class="fixed top-0 w-full z-40 h-14 bg-[var(--bg-card)] border-b border-[var(--border)] flex items-center px-5 gap-3">
  <a href="<?= $dashBack ?>" class="flex items-center gap-1.5 text-sm text-[var(--text-muted)] hover:text-[var(--text-primary)] transition group">
    <i data-lucide="arrow-left" class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform"></i> Kembali
  </a>
  <span class="text-[var(--border)]">|</span>
  <span class="text-sm font-bold text-[var(--text-primary)]">Profil <?= htmlspecialchars($user['username']) ?></span>
  <?php if ($isMyself): ?>
  <a href="profil.php" class="ml-auto flex items-center gap-1.5 px-4 py-1.5 rounded-lg bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold transition">
    <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit Profil
  </a>
  <?php endif; ?>
  <button data-action="toggle-theme" class="<?= $isMyself?'':'ml-auto' ?> w-8 h-8 flex items-center justify-center rounded-lg bg-[var(--surface)] border border-[var(--border)] text-[var(--text-muted)] hover:text-[var(--text-primary)] transition">
    <i data-lucide="moon" data-theme-icon="toggle" class="w-4 h-4"></i>
  </button>
</div>

<div class="max-w-2xl mx-auto px-4 pt-20 pb-16">

  <!-- Profile card -->
  <div class="card overflow-hidden mb-5 animate-fade-up">
    <div class="prof-cover">
      <div class="avatar-xl"><?= strtoupper(substr($user['username'],0,1)) ?></div>
    </div>
    <div class="pt-14 px-6 pb-6">
      <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <h1 class="font-display font-black text-2xl text-[var(--text-primary)]">
              <?= htmlspecialchars($user['nama_lengkap'] ?: $user['username']) ?>
            </h1>
            <span class="badge <?= $bc ?>"><?= $bl ?></span>
          </div>
          <p class="text-sm text-[var(--text-muted)] flex items-center gap-1.5 mb-1">
            <i data-lucide="at-sign" class="w-3.5 h-3.5"></i><?= htmlspecialchars($user['username']) ?>
          </p>
          <?php if ($user['provinsi']): ?>
          <p class="text-sm text-[var(--text-muted)] flex items-center gap-1.5 mb-1">
            <i data-lucide="map-pin" class="w-3.5 h-3.5"></i>
            <?= htmlspecialchars($user['kota'] ? $user['kota'].', '.$user['provinsi'] : $user['provinsi']) ?>
          </p>
          <?php endif; ?>
          <p class="text-xs text-[var(--text-muted)] mt-2">
            Bergabung <?= date('d F Y', strtotime($user['created_at'])) ?>
            <?php if ($user['last_login']): ?>
            · Terakhir aktif <?= date('d/m/Y', strtotime($user['last_login'])) ?>
            <?php endif; ?>
          </p>
        </div>
        <?php if ($isMyself): ?>
        <a href="profil.php" class="flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[var(--surface)] border border-[var(--border)] text-xs font-semibold text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition">
          <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit Profil
        </a>
        <?php endif; ?>
      </div>

      <?php if ($user['bio']): ?>
      <div class="mt-4 p-3.5 bg-[var(--surface)] rounded-xl border border-[var(--border)]">
        <p class="text-sm text-[var(--text-secondary)] leading-relaxed"><?= htmlspecialchars($user['bio']) ?></p>
      </div>
      <?php endif; ?>

      <!-- Statistik -->
      <div class="flex flex-wrap gap-3 mt-5">
        <div class="stat-pill">
          <span class="font-display font-black text-xl text-[var(--text-primary)]"><?= $totalLaporan ?></span>
          <span class="text-[10px] text-[var(--text-muted)] mt-0.5">Laporan</span>
        </div>
        <div class="stat-pill">
          <span class="font-display font-black text-xl text-brand-500"><?= $totalApproved ?></span>
          <span class="text-[10px] text-[var(--text-muted)] mt-0.5">Disetujui</span>
        </div>
        <?php if ($totalArtikel > 0): ?>
        <div class="stat-pill">
          <span class="font-display font-black text-xl text-blue-400"><?= $totalArtikel ?></span>
          <span class="text-[10px] text-[var(--text-muted)] mt-0.5">Artikel</span>
        </div>
        <?php endif; ?>
        <div class="stat-pill">
          <span class="font-display font-black text-xl text-purple-400"><?= $totalDiskusi ?></span>
          <span class="text-[10px] text-[var(--text-muted)] mt-0.5">Komentar</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Laporan terbaru -->
  <?php if (!empty($laporanList)): ?>
  <div class="card overflow-hidden mb-5">
    <div class="px-5 py-4 border-b border-[var(--border)]">
      <h2 class="font-display font-bold text-[var(--text-primary)] flex items-center gap-2">
        <i data-lucide="send" class="w-4 h-4 text-brand-500"></i>
        Laporan Harga Terbaru
      </h2>
    </div>
    <div class="divide-y divide-[var(--border)]">
      <?php foreach ($laporanList as $lp):
        $naik=(int)$lp['harga_sekarang']>(int)$lp['harga_kemarin'];
        $turun=(int)$lp['harga_sekarang']<(int)$lp['harga_kemarin'];
      ?>
      <div class="activity-row px-5">
        <div class="w-8 h-8 rounded-lg bg-brand-500/10 flex items-center justify-center flex-shrink-0">
          <i data-lucide="tag" class="w-3.5 h-3.5 text-brand-500"></i>
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center justify-between gap-2">
            <span class="font-bold text-sm text-[var(--text-primary)]"><?= htmlspecialchars($lp['nama']) ?></span>
            <span class="font-display font-black text-sm <?= $naik?'text-red-400':($turun?'text-brand-500':'text-[var(--text-muted)]') ?>">
              <?= rupiah((int)$lp['harga_sekarang']) ?>
            </span>
          </div>
          <div class="text-xs text-[var(--text-muted)]">
            <?= htmlspecialchars($lp['lokasi']) ?>, <?= htmlspecialchars($lp['provinsi']) ?>
            · <?= date('d/m/Y', strtotime($lp['updated_at'])) ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Komentar diskusi terbaru -->
  <?php if (!empty($diskusiList)): ?>
  <div class="card overflow-hidden mb-5">
    <div class="px-5 py-4 border-b border-[var(--border)]">
      <h2 class="font-display font-bold text-[var(--text-primary)] flex items-center gap-2">
        <i data-lucide="message-circle" class="w-4 h-4 text-purple-400"></i>
        Komentar Terbaru di Diskusi
      </h2>
    </div>
    <div class="divide-y divide-[var(--border)]">
      <?php foreach ($diskusiList as $dk): ?>
      <div class="activity-row px-5">
        <div class="w-8 h-8 rounded-lg bg-purple-500/10 flex items-center justify-center flex-shrink-0">
          <i data-lucide="message-circle" class="w-3.5 h-3.5 text-purple-400"></i>
        </div>
        <div class="flex-1 min-w-0">
          <div class="text-sm text-[var(--text-secondary)] leading-relaxed line-clamp-2">
            "<?= htmlspecialchars(mb_substr($dk['pesan'],0,120)) ?><?= mb_strlen($dk['pesan'])>120?'…':'' ?>"
          </div>
          <div class="text-xs text-[var(--text-muted)] mt-0.5 flex items-center gap-2">
            <?php if ($dk['kom_nama']): ?>
            <a href="diskusi.php?komoditas_id=<?= $dk['kom_id'] ?>"
               class="text-brand-500 hover:underline">
              <?= htmlspecialchars($dk['kom_nama']) ?>
            </a>
            <span>·</span>
            <?php endif; ?>
            <?= date('d/m/Y H:i', strtotime($dk['created_at'])) ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="px-5 py-3 border-t border-[var(--border)]">
      <a href="diskusi.php" class="text-xs text-brand-500 hover:underline font-semibold">
        Lihat semua diskusi →
      </a>
    </div>
  </div>
  <?php endif; ?>

  <!-- Kosong -->
  <?php if (empty($laporanList) && empty($diskusiList)): ?>
  <div class="card p-12 text-center">
    <i data-lucide="user" class="w-12 h-12 mx-auto opacity-20 mb-3"></i>
    <h3 class="font-display font-bold text-lg text-[var(--text-primary)] mb-2">Belum Ada Aktivitas</h3>
    <p class="text-sm text-[var(--text-muted)]">
      <?= $isMyself ? 'Mulai kirim laporan harga atau ikut diskusi!' : 'Pengguna ini belum memiliki aktivitas publik.' ?>
    </p>
    <?php if ($isMyself): ?>
    <div class="flex justify-center gap-3 mt-5">
      <a href="dashboard-user.php?tab=laporan" class="px-5 py-2.5 bg-brand-600 hover:bg-brand-500 text-white rounded-xl text-sm font-bold transition">
        Kirim Laporan
      </a>
      <a href="diskusi.php" class="px-5 py-2.5 bg-[var(--surface)] border border-[var(--border)] text-[var(--text-secondary)] rounded-xl text-sm font-semibold transition hover:text-[var(--text-primary)]">
        Ikut Diskusi
      </a>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>

<script src="Assets/scripts.js"></script>
<script>lucide.createIcons();</script>
</body>
</html>
