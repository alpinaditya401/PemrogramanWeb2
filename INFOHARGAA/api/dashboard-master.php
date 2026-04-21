<?php
/**
 * dashboard-master.php — Panel Admin Master
 * Hanya role: admin_master
 * Tab: users (kelola + HAPUS), bps (API BPS), settings (pengaturan)
 */
session_start();
if (!isset($_SESSION['login'])) { header("Location: login.php"); exit; }
if ($_SESSION['role'] !== 'admin_master') {
    header("Location: " . ($_SESSION['role']==='admin' ? 'dashboard.php' : 'dashboard-user.php'));
    exit;
}

require 'Server/koneksi.php';
require_once 'Server/bps_api.php';

$myId      = (int)$_SESSION['user_id'];
$myName    = htmlspecialchars($_SESSION['username']);
$activeTab = $_GET['tab'] ?? 'users';
$pageTitle = 'Dashboard Admin Master';

// ── PESAN FLASH ───────────────────────────────────────────────
$msgs = [
    'role_updated'  => ['success','✅ Role pengguna berhasil diperbarui.'],
    'user_deleted'  => ['success','✅ Akun pengguna berhasil dihapus.'],
    'cannot_self'   => ['error',  '❌ Anda tidak bisa mengubah akun Anda sendiri.'],
    'cannot_delete' => ['error',  '❌ Tidak bisa menghapus sesama admin_master.'],
    'setting_saved' => ['success','✅ Pengaturan sistem berhasil disimpan.'],
    'bps_imported'  => ['success','✅ Artikel dari BPS berhasil diimpor!'],
    'bps_nodata'    => ['error',  '❌ Tidak ada data baru dari BPS, atau API key belum dikonfigurasi.'],
];
$fkey  = $_GET['msg'] ?? '';
$ftype = $msgs[$fkey][0] ?? '';
$ftext = $msgs[$fkey][1] ?? '';

// ── HAPUS USER ────────────────────────────────────────────────
if (isset($_GET['hapus_user'])) {
    $tid = (int)$_GET['hapus_user'];
    if ($tid === $myId) redirect('dashboard-master.php?tab=users&msg=cannot_self');
    $tr = $conn->query("SELECT role FROM users WHERE id=$tid LIMIT 1");
    if ($tr && $row = $tr->fetch_assoc()) {
        if ($row['role'] === 'admin_master') redirect('dashboard-master.php?tab=users&msg=cannot_delete');
        // Nullify references before delete
        $conn->query("UPDATE komoditas SET submitted_by=NULL WHERE submitted_by=$tid");
        $conn->query("UPDATE artikel    SET penulis_id=NULL  WHERE penulis_id=$tid");
        $conn->query("UPDATE pengumuman SET dibuat_oleh=NULL WHERE dibuat_oleh=$tid");
        $conn->query("DELETE FROM users WHERE id=$tid");
        redirect('dashboard-master.php?tab=users&msg=user_deleted');
    }
    redirect('dashboard-master.php?tab=users');
}

// ── UPDATE ROLE ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_role'])) {
    $tid  = (int)$_POST['user_id'];
    $nr   = esc($conn, $_POST['new_role']??'user');
    if ($tid===$myId) redirect('dashboard-master.php?tab=users&msg=cannot_self');
    if (in_array($nr,['admin_master','admin','kontributor','user'],true))
        $conn->query("UPDATE users SET role='$nr' WHERE id=$tid");
    redirect('dashboard-master.php?tab=users&msg=role_updated');
}

// ── SAVE SETTINGS ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_settings'])) {
    foreach ($_POST as $k => $v) {
        if (in_array($k,['save_settings'])) continue;
        $kk = esc($conn,$k); $vv = esc($conn,$v);
        // Upsert: update jika ada, insert jika belum
        $ex = $conn->query("SELECT id FROM pengaturan_sistem WHERE kunci='$kk' LIMIT 1");
        if ($ex && $ex->num_rows>0) {
            $conn->query("UPDATE pengaturan_sistem SET nilai='$vv' WHERE kunci='$kk'");
        } else {
            $conn->query("INSERT INTO pengaturan_sistem (kunci,nilai,label,kelompok,tipe) VALUES ('$kk','$vv','$kk','API','text')");
        }
    }
    redirect('dashboard-master.php?tab=settings&msg=setting_saved');
}

// ── IMPORT BPS ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['import_bps'])) {
    $apiKey = getSetting($conn,'bps_api_key',BPS_API_KEY);
    $bpsObj = new BPS_API($apiKey);
    $domain = esc($conn,$_POST['bps_domain']??'0000');
    $items  = $bpsObj->fetchArtikelFromBPS($domain,5);
    $imp    = 0;
    foreach ($items as $it) {
        $judul  = esc($conn,$it['judul']);
        $ring   = esc($conn,mb_substr($it['ringkasan']??'',0,300));
        $srcUrl = esc($conn,$it['sumber_url']??'');
        $srcNm  = esc($conn,$it['sumber_nama']??'BPS Indonesia');
        $slug   = esc($conn,slugify($it['judul']));
        $kat    = esc($conn,$it['kategori']??'Statistik BPS');
        $ex2    = $conn->query("SELECT id FROM artikel WHERE slug='$slug' LIMIT 1");
        if ($ex2 && $ex2->num_rows>0) continue;
        $conn->query("INSERT INTO artikel (judul,slug,ringkasan,konten,kategori,emoji,sumber_url,sumber_nama,penulis_id,is_publish)
                      VALUES ('$judul','$slug','$ring','','$kat','📊','$srcUrl','$srcNm',$myId,1)");
        $imp++;
    }
    redirect('dashboard-master.php?tab=bps&msg='.($imp>0?'bps_imported':'bps_nodata'));
}

// ── DATA ──────────────────────────────────────────────────────
$resU = $conn->query("SELECT id,email,username,nama_lengkap,role,is_active,last_login,created_at FROM users ORDER BY FIELD(role,'admin_master','admin','kontributor','user'),created_at DESC");
$allU = []; $tc = ['admin_master'=>0,'admin'=>0,'kontributor'=>0,'user'=>0];
while($r=$resU->fetch_assoc()){ $allU[]=$r; $tc[$r['role']]++; }
$totalU = count($allU);

$resS = $conn->query("SELECT * FROM pengaturan_sistem ORDER BY kelompok ASC,id ASC");
$settings = []; while($r=$resS->fetch_assoc()) $settings[$r['kelompok']][]=$r;

$bpsKey    = getSetting($conn,'bps_api_key','');
$keyActive = !empty($bpsKey) && $bpsKey!=='YOUR_BPS_API_KEY_HERE';
$bpsDoms   = $activeTab==='bps' ? (new BPS_API($bpsKey))->getDomains('prov') : [];

$totalKom  = (int)($conn->query("SELECT COUNT(*) c FROM komoditas WHERE status='approved'")?->fetch_assoc()['c']??0);
$totalPend = (int)($conn->query("SELECT COUNT(*) c FROM komoditas WHERE status='pending'")?->fetch_assoc()['c']??0);
$totalArt  = (int)($conn->query("SELECT COUNT(*) c FROM artikel WHERE is_publish=1")?->fetch_assoc()['c']??0);

// Data untuk grafik di halaman master (tab users)
$resMChart = $conn->query("SELECT nama,lokasi,history FROM komoditas WHERE status='approved' ORDER BY updated_at DESC LIMIT 20");
$mChartRows = [];
if ($resMChart) while ($r = $resMChart->fetch_assoc()) $mChartRows[] = $r;
?>
<!doctype html>
<html lang="id">
<head>
<?php include 'Assets/head.php'; ?>
<style>
  body{overflow:hidden;}
  .mwrap{display:flex;height:100vh;}
  .mside{width:230px;flex-shrink:0;display:flex;flex-direction:column;height:100%;background:var(--bg-secondary);border-right:1px solid var(--border);overflow:hidden;}
  .mmain{flex:1;display:flex;flex-direction:column;height:100%;overflow:hidden;}
  .mbody{flex:1;overflow-y:auto;padding:1.5rem;}
  .mbody::-webkit-scrollbar{width:4px;}
  .mbody::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px;}
  .rb-admin_master{background:rgba(168,85,247,.12);color:#a855f7;border:1px solid rgba(168,85,247,.25);}
  .rb-admin{background:rgba(16,185,129,.12);color:#10b981;border:1px solid rgba(16,185,129,.25);}
  .rb-kontributor{background:rgba(59,130,246,.12);color:#3b82f6;border:1px solid rgba(59,130,246,.25);}
  .rb-user{background:rgba(148,163,184,.1);color:#94a3b8;border:1px solid rgba(148,163,184,.2);}
</style>
</head>
<body>
<div class="mwrap">

<!-- SIDEBAR -->
<aside class="mside">
  <div class="h-16 flex items-center px-5 border-b border-[var(--border)] flex-shrink-0">
    <div class="flex items-center gap-2">
      <div class="w-7 h-7 bg-purple-600 rounded-lg flex items-center justify-center shadow shadow-purple-600/30">
        <i data-lucide="shield-alert" class="w-3.5 h-3.5 text-white"></i>
      </div>
      <span class="font-display font-black text-sm text-[var(--text-primary)]">InfoHarga<span class="text-purple-500">Master</span></span>
    </div>
  </div>

  <nav class="flex-1 py-4 px-3 space-y-0.5 sidebar-nav slim-scroll overflow-y-auto">
    <div class="nav-section">Master Panel</div>
    <a href="?tab=users"    class="<?= $activeTab==='users'   ?'active':'' ?>"><i data-lucide="users" class="w-4 h-4"></i> Kelola Users</a>
    <a href="?tab=bps"      class="<?= $activeTab==='bps'     ?'active':'' ?>"><i data-lucide="database" class="w-4 h-4"></i> API BPS</a>
    <a href="?tab=settings" class="<?= $activeTab==='settings'?'active':'' ?>"><i data-lucide="settings" class="w-4 h-4"></i> Pengaturan</a>
    <div class="nav-section">Panel Lain</div>
    <a href="index.php" target="_blank"><i data-lucide="home" class="w-4 h-4"></i> Home (Website)</a>
    <a href="chart.php"><i data-lucide="bar-chart-2" class="w-4 h-4"></i> Grafik Harga</a>
    <a href="index.php#artikel" target="_blank"><i data-lucide="file-text" class="w-4 h-4"></i> Artikel</a>
    <a href="dashboard.php"       ><i data-lucide="layout-dashboard" class="w-4 h-4"></i> Panel Admin</a>
    <a href="bps-sync.php"        ><i data-lucide="refresh-cw" class="w-4 h-4"></i> Sync Data BPS</a>
    <a href="Kelola-Artikel.php"  ><i data-lucide="file-text" class="w-4 h-4"></i> Kelola Artikel</a>
    <a href="pusat-informasi.php" ><i data-lucide="bell" class="w-4 h-4"></i> Pusat Informasi</a>
    <a href="diskusi.php"><i data-lucide="message-circle" class="w-4 h-4"></i> Forum Diskusi</a>
    <a href="#" data-action="toggle-theme"><i data-lucide="moon" data-theme-icon="toggle" class="w-4 h-4"></i> Ganti Tema</a>
  </nav>

  <div class="p-3 border-t border-[var(--border)] flex-shrink-0">
    <div class="flex items-center gap-2.5 px-3 py-2 rounded-lg mb-1.5 bg-[var(--surface)]">
      <div class="w-7 h-7 rounded-full bg-purple-600/20 flex items-center justify-center text-[10px] font-black text-purple-400 font-display flex-shrink-0"><?= strtoupper(substr($myName,0,1)) ?></div>
      <div class="min-w-0">
        <div class="text-xs font-bold text-[var(--text-primary)] truncate"><?= $myName ?></div>
        <div class="text-[10px] text-purple-400 font-bold">Admin Master</div>
      </div>
    </div>
    <a href="Proses/logout.php" onclick="return confirm('Yakin keluar?')"
       class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-medium text-red-400 hover:bg-red-500/8 transition sidebar-nav">
      <i data-lucide="log-out" class="w-3.5 h-3.5"></i> Logout
    </a>
  </div>
</aside>

<!-- MAIN -->
<div class="mmain">
  <header class="h-16 bg-[var(--bg-card)] border-b border-[var(--border)] flex items-center justify-between px-6 flex-shrink-0">
    <?php $tl=['users'=>'Kelola Pengguna','bps'=>'Integrasi API BPS','settings'=>'Pengaturan Sistem']; ?>
    <h1 class="font-display font-black text-[var(--text-primary)]"><?= $tl[$activeTab]??'Dashboard' ?></h1>
    <div class="hidden md:flex items-center gap-4 text-xs text-[var(--text-muted)]">
      <span class="flex items-center gap-1"><i data-lucide="users" class="w-3.5 h-3.5"></i> <?= $totalU ?> users</span>
      <span class="flex items-center gap-1"><i data-lucide="layers" class="w-3.5 h-3.5"></i> <?= $totalKom ?> komoditas</span>
      <?php if($totalPend>0): ?><span class="flex items-center gap-1 text-amber-400"><i data-lucide="clock" class="w-3.5 h-3.5"></i> <?= $totalPend ?> pending</span><?php endif; ?>
    </div>
  </header>

  <div class="mbody">

    <!-- Flash -->
    <?php if ($ftext): ?>
    <div class="mb-5 flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium <?= $ftype==='success'?'msg-success':'msg-error' ?>">
      <i data-lucide="<?= $ftype==='success'?'check-circle':'alert-circle' ?>" class="w-4 h-4 flex-shrink-0"></i>
      <?= $ftext ?>
    </div>
    <?php endif; ?>

    <!-- ═══ TAB: USERS ═══ -->
    <?php if ($activeTab==='users'): ?>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <?php foreach([['Admin Master',$tc['admin_master'],'shield-alert','purple'],['Admin',$tc['admin'],'shield','emerald'],['Kontributor',$tc['kontributor'],'send','blue'],['User',$tc['user'],'user','slate']] as [$l,$v,$ic,$c]): ?>
      <div class="card p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-<?= $c ?>-500/10 flex items-center justify-center flex-shrink-0"><i data-lucide="<?= $ic ?>" class="w-4 h-4 text-<?= $c ?>-400"></i></div>
        <div><div class="font-display font-black text-xl text-[var(--text-primary)]"><?= $v ?></div><div class="text-[10px] text-[var(--text-muted)]"><?= $l ?></div></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Warning -->
    <div class="flex items-start gap-3 p-4 rounded-xl bg-amber-500/6 border border-amber-500/20 mb-5 text-xs text-[var(--text-secondary)]">
      <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-400 flex-shrink-0 mt-0.5"></i>
      <span><strong class="text-amber-400">Peringatan:</strong> Hapus user bersifat permanen. Anda tidak bisa mengubah/menghapus akun sendiri atau sesama admin_master.</span>
    </div>

    <!-- Grafik ringkas komoditas -->
    <?php if (!empty($mChartRows)): ?>
    <div class="card p-5 mb-5">
      <div class="flex items-center justify-between mb-3">
        <h2 class="font-display font-bold text-[var(--text-primary)] flex items-center gap-2">
          <i data-lucide="bar-chart-2" class="w-4 h-4 text-purple-400"></i> Grafik Pergerakan Harga
        </h2>
        <div class="flex gap-2">
          <select id="mfNama" class="input-field text-xs py-1.5" style="width:160px">
            <?php foreach(array_unique(array_column($mChartRows,'nama')) as $n): ?>
            <option><?= htmlspecialchars($n) ?></option>
            <?php endforeach; ?>
          </select>
          <select id="mfLokasi" class="input-field text-xs py-1.5" style="width:160px">
            <option>— Pilih komoditas —</option>
          </select>
        </div>
      </div>
      <div style="position:relative;height:200px"><canvas id="masterChart"></canvas></div>
    </div>
    <?php endif; ?>

    <!-- Table -->
    <div class="card overflow-hidden">
      <div class="px-5 py-4 border-b border-[var(--border)]">
        <h2 class="font-display font-bold text-[var(--text-primary)] flex items-center gap-2">
          <i data-lucide="users" class="w-4 h-4 text-purple-400"></i> Semua Pengguna (<?= $totalU ?>)
        </h2>
      </div>
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead>
            <tr><th>Pengguna</th><th>Email</th><th>Role</th><th>Status</th><th>Login Terakhir</th><th>Ubah Role</th><th class="text-center">Hapus</th></tr>
          </thead>
          <tbody>
            <?php if(empty($allU)): ?>
            <tr><td colspan="7" class="text-center py-12 text-[var(--text-muted)]">Belum ada pengguna.</td></tr>
            <?php else: foreach($allU as $u):
              $isSelf  = ($u['id']==$myId);
              $isMaster= ($u['role']==='admin_master');
            ?>
            <tr class="<?= $isSelf?'bg-purple-500/[0.03]':'' ?>">
              <td>
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-full bg-[var(--surface)] border border-[var(--border)] flex items-center justify-center text-xs font-black font-display text-[var(--text-muted)] flex-shrink-0"><?= strtoupper(substr($u['username'],0,1)) ?></div>
                  <div>
                    <div class="font-bold text-[var(--text-primary)] text-sm flex items-center gap-1.5">
                      <?= htmlspecialchars($u['username']) ?>
                      <?php if($isSelf): ?><span class="text-[9px] bg-purple-500/15 text-purple-400 px-1.5 py-0.5 rounded font-display">ANDA</span><?php endif; ?>
                    </div>
                    <div class="text-[10px] text-[var(--text-muted)]"><?= htmlspecialchars($u['nama_lengkap']?:'—') ?></div>
                  </div>
                </div>
              </td>
              <td class="text-xs text-[var(--text-muted)]"><?= htmlspecialchars($u['email']) ?></td>
              <td><span class="badge rb-<?= $u['role'] ?>"><?= $u['role'] ?></span></td>
              <td><?= $u['is_active'] ? '<span class="badge badge-green">Aktif</span>' : '<span class="badge badge-red">Nonaktif</span>' ?></td>
              <td class="text-xs text-[var(--text-muted)]"><?= $u['last_login'] ? date('d/m/Y H:i',strtotime($u['last_login'])) : 'Belum pernah' ?></td>
              <td>
                <?php if($isSelf): ?>
                <span class="text-[10px] text-[var(--text-muted)] italic">—</span>
                <?php else: ?>
                <form method="POST" class="flex items-center gap-2">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>"/>
                  <select name="new_role" class="input-field text-xs py-1.5 px-2" style="width:130px">
                    <?php foreach(['admin_master','admin','kontributor','user'] as $r): ?>
                    <option value="<?= $r ?>" <?= $u['role']===$r?'selected':'' ?>><?= $r ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" name="update_role"
                          class="p-1.5 rounded-lg bg-purple-600/10 hover:bg-purple-600/20 text-purple-400 border border-purple-600/20 transition" title="Simpan">
                    <i data-lucide="save" class="w-3.5 h-3.5"></i>
                  </button>
                </form>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <?php if($isSelf || ($isMaster && !$isSelf)): ?>
                <span class="text-[var(--text-muted)] opacity-30" title="<?= $isSelf?'Akun Anda':'Sesama master' ?>"><i data-lucide="minus" class="w-3.5 h-3.5 mx-auto"></i></span>
                <?php else: ?>
                <a href="dashboard-master.php?hapus_user=<?= $u['id'] ?>&tab=users"
                   onclick="return confirm('⚠️ HAPUS PERMANEN\n\nHapus akun &quot;<?= htmlspecialchars(addslashes($u['username'])) ?>&quot;?\n\nSemua laporan & artikel yang dibuat user ini akan ter-nullify.\nTindakan ini TIDAK BISA dibatalkan!')"
                   class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-[var(--text-muted)] hover:text-red-400 hover:bg-red-500/10 transition" title="Hapus akun ini">
                  <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                </a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ═══ TAB: BPS ═══ -->
    <?php elseif($activeTab==='bps'): ?>

    <div class="card p-5 mb-6 border-blue-500/20 bg-blue-500/4">
      <div class="flex items-start gap-4">
        <div class="w-10 h-10 bg-blue-500/15 rounded-xl flex items-center justify-center flex-shrink-0"><i data-lucide="database" class="w-5 h-5 text-blue-400"></i></div>
        <div>
          <h3 class="font-display font-bold text-[var(--text-primary)] mb-1">Web API BPS — Badan Pusat Statistik Indonesia</h3>
          <p class="text-sm text-[var(--text-secondary)] leading-relaxed mb-3">Impor rilis pers dan publikasi statistik resmi dari BPS sebagai artikel edukasi di platform Anda.</p>
          <div class="flex flex-wrap gap-2">
            <a href="https://webapi.bps.go.id/developer/" target="_blank" rel="noopener"
               class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-500/10 border border-blue-500/20 text-blue-400 hover:bg-blue-500/15 transition text-xs">
              <i data-lucide="external-link" class="w-3 h-3"></i> Daftar API Key (Gratis)
            </a>
            <a href="https://webapi.bps.go.id/documentation/" target="_blank" rel="noopener"
               class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-[var(--text-muted)] hover:bg-[var(--surface-hover)] transition text-xs">
              <i data-lucide="book-open" class="w-3 h-3"></i> Dokumentasi
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Status key -->
    <div class="card p-5 mb-5">
      <h3 class="font-display font-bold text-[var(--text-primary)] mb-3 flex items-center gap-2"><i data-lucide="key" class="w-4 h-4 text-amber-400"></i> Status API Key</h3>
      <?php if(!$keyActive): ?>
      <div class="flex items-center gap-3 p-4 rounded-xl bg-amber-500/8 border border-amber-500/20 mb-4">
        <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-400 flex-shrink-0"></i>
        <p class="text-sm text-[var(--text-secondary)]">API Key belum dikonfigurasi. Buka tab <strong>Pengaturan</strong> dan isi field <em>BPS API Key</em>.</p>
      </div>
      <?php else: ?>
      <div class="flex items-center gap-3 p-4 rounded-xl bg-brand-500/8 border border-brand-500/20 mb-4">
        <i data-lucide="check-circle" class="w-4 h-4 text-brand-500 flex-shrink-0"></i>
        <p class="text-sm text-[var(--text-secondary)]">API Key aktif: <code class="text-xs bg-[var(--surface)] px-2 py-0.5 rounded">···<?= substr($bpsKey,-6) ?></code></p>
      </div>
      <?php endif; ?>

      <form method="POST" class="flex flex-col sm:flex-row gap-4 items-end">
        <div class="flex-1">
          <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">Domain BPS</label>
          <select name="bps_domain" class="input-field">
            <option value="0000">Pusat — Nasional</option>
            <?php foreach($bpsDoms as $d): ?>
            <option value="<?= htmlspecialchars($d['domain_id']??'') ?>"><?= htmlspecialchars($d['domain_name']??'') ?></option>
            <?php endforeach; ?>
            <?php if(empty($bpsDoms)): ?>
            <option value="3100">DKI Jakarta</option><option value="3200">Jawa Barat</option>
            <option value="3300">Jawa Tengah</option><option value="3500">Jawa Timur</option>
            <option value="1100">Aceh</option><option value="5100">Bali</option>
            <?php endif; ?>
          </select>
        </div>
        <button type="submit" name="import_bps" <?= !$keyActive?'disabled':'' ?>
                class="flex items-center gap-2 px-6 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-xl text-sm transition shadow shadow-blue-600/20 font-display <?= !$keyActive?'opacity-50 cursor-not-allowed':'' ?>">
          <i data-lucide="download" class="w-4 h-4"></i> Import Rilis BPS → Artikel
        </button>
        <a href="bps-sync.php"
           class="flex items-center gap-2 px-6 py-2.5 bg-brand-600 hover:bg-brand-500 text-white font-bold rounded-xl text-sm transition shadow shadow-brand-600/20 font-display whitespace-nowrap">
          <i data-lucide="refresh-cw" class="w-4 h-4"></i> Sync Harga Komoditas
        </a>
      </form>
    </div>

    <div class="card p-5">
      <h3 class="font-display font-bold text-[var(--text-primary)] mb-4 flex items-center gap-2"><i data-lucide="info" class="w-4 h-4 text-brand-500"></i> Tentang Data Komoditas</h3>
      <div class="space-y-3 text-sm text-[var(--text-secondary)]">
        <div class="flex items-start gap-3 p-3 rounded-lg bg-[var(--surface)] border border-[var(--border)]">
          <i data-lucide="trending-up" class="w-4 h-4 text-brand-500 flex-shrink-0 mt-0.5"></i>
          <div><strong class="text-[var(--text-primary)]">Harga Harian</strong> — Diinput kontributor lapangan. BPS tidak sediakan API harga harian real-time untuk publik.</div>
        </div>
        <div class="flex items-start gap-3 p-3 rounded-lg bg-[var(--surface)] border border-[var(--border)]">
          <i data-lucide="bar-chart-2" class="w-4 h-4 text-blue-400 flex-shrink-0 mt-0.5"></i>
          <div><strong class="text-[var(--text-primary)]">API BPS</strong> — Berisi rilis pers, IHK, tabel statistik harga produsen untuk artikel referensi.</div>
        </div>
        <div class="flex items-start gap-3 p-3 rounded-lg bg-[var(--surface)] border border-[var(--border)]">
          <i data-lucide="users" class="w-4 h-4 text-amber-400 flex-shrink-0 mt-0.5"></i>
          <div><strong class="text-[var(--text-primary)]">Perbanyak Kontributor</strong> — Daftarkan user baru → ubah role ke <em>kontributor</em> di tab Kelola Users.</div>
        </div>
      </div>
    </div>

    <!-- ═══ TAB: SETTINGS ═══ -->
    <?php elseif($activeTab==='settings'): ?>

    <form method="POST">
      <input type="hidden" name="save_settings" value="1"/>
      <?php foreach($settings as $grp => $items): ?>
      <div class="card overflow-hidden mb-5">
        <div class="px-5 py-4 border-b border-[var(--border)] bg-[var(--surface)] flex items-center gap-2">
          <i data-lucide="settings" class="w-4 h-4 text-purple-400"></i>
          <h2 class="font-display font-bold text-[var(--text-primary)]">Pengaturan: <?= htmlspecialchars($grp) ?></h2>
        </div>
        <div class="p-5 space-y-4">
          <?php foreach($items as $s): ?>
          <div>
            <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1">
              <?= htmlspecialchars($s['label']) ?>
              <?php if($s['keterangan']): ?><span class="font-normal normal-case text-[var(--text-muted)] ml-1">— <?= htmlspecialchars($s['keterangan']) ?></span><?php endif; ?>
            </label>
            <?php if($s['tipe']==='textarea'): ?>
            <textarea name="<?= htmlspecialchars($s['kunci']) ?>" rows="3" class="input-field"><?= htmlspecialchars($s['nilai']??'') ?></textarea>
            <?php elseif($s['tipe']==='toggle'): ?>
            <div class="flex items-center gap-3">
              <input type="hidden"   name="<?= htmlspecialchars($s['kunci']) ?>" value="0"/>
              <input type="checkbox" name="<?= htmlspecialchars($s['kunci']) ?>" value="1" <?= $s['nilai']==='1'?'checked':'' ?> class="w-4 h-4 accent-purple-500"/>
              <span class="text-sm text-[var(--text-secondary)]">Aktifkan</span>
            </div>
            <?php else: ?>
            <input type="<?= $s['tipe'] ?>" name="<?= htmlspecialchars($s['kunci']) ?>" value="<?= htmlspecialchars($s['nilai']??'') ?>" class="input-field" placeholder="<?= htmlspecialchars($s['label']) ?>"/>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <!-- BPS API Key -->
      <div class="card p-5 mb-5">
        <div class="mb-4 pb-4 border-b border-[var(--border)] flex items-center gap-2">
          <i data-lucide="key" class="w-4 h-4 text-blue-400"></i>
          <h2 class="font-display font-bold text-[var(--text-primary)]">API BPS</h2>
        </div>
        <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1">
          BPS API Key — <span class="font-normal normal-case text-[var(--text-muted)]">Daftar gratis: <a href="https://webapi.bps.go.id/developer/" target="_blank" class="text-blue-400 hover:underline">webapi.bps.go.id</a></span>
        </label>
        <input type="text" name="bps_api_key" value="<?= htmlspecialchars(getSetting($conn,'bps_api_key','')) ?>" class="input-field" placeholder="Paste API key BPS di sini..."/>
      </div>
      <div class="flex justify-end">
        <button type="submit" class="flex items-center gap-2 px-6 py-2.5 bg-purple-600 hover:bg-purple-500 text-white font-display font-bold rounded-xl text-sm transition shadow shadow-purple-600/20">
          <i data-lucide="save" class="w-4 h-4"></i> Simpan Semua
        </button>
      </div>
    </form>
    <?php endif; ?>

  </div><!-- end mbody -->
</div><!-- end mmain -->
</div><!-- end mwrap -->

<script>
const masterChartData = <?= json_encode(array_map(fn($r)=>['nama'=>$r['nama'],'lokasi'=>$r['lokasi'],'history'=>json_decode($r['history']??'[]',true)],$mChartRows??[]),JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="Assets/scripts.js"></script>
<script>
lucide.createIcons();

// ── CHART MASTER — IIFE ───────────────────────────────────────
let masterChart = null;
(function initMasterChart() {
  const canvas = document.getElementById('masterChart');
  if (!canvas || !masterChartData.length) return;

  const ct = canvas.getContext('2d');
  const t  = getChartTheme();
  let g    = ct.createLinearGradient(0,0,0,200);
  g.addColorStop(0,'rgba(168,85,247,.3)'); g.addColorStop(1,'rgba(168,85,247,0)');

  masterChart = new Chart(ct, {
    type:'line',
    data:{
      labels:['H-6','H-5','H-4','H-3','H-2','Kemarin','Hari Ini'],
      datasets:[{data:[], borderColor:'#a855f7', backgroundColor:g,
        fill:true, tension:.4, borderWidth:2.5,
        pointBackgroundColor:t.bgColor, pointBorderColor:'#a855f7', pointRadius:4, pointHoverRadius:7}]
    },
    options:{
      responsive:true, maintainAspectRatio:false,
      interaction:{mode:'index',intersect:false},
      plugins:{
        legend:{display:false},
        title:{display:true, text:'Memuat…', color:t.titleColor,
               font:{family:'Cabinet Grotesk',size:13,weight:'700'}, padding:{bottom:10}},
        tooltip:{callbacks:{label:c=>'Rp '+c.parsed.y.toLocaleString('id-ID')}}
      },
      scales:{
        y:{beginAtZero:false, ticks:{color:t.textColor,callback:v=>'Rp '+v.toLocaleString('id-ID')}, grid:{color:t.gridColor}},
        x:{ticks:{color:t.textColor}, grid:{display:false}}
      }
    }
  });

  function populateMasterLokasi(nama) {
    const sel = document.getElementById('mfLokasi');
    if (!sel) return;
    sel.innerHTML = '';
    masterChartData.filter(d=>d.nama===nama).forEach(d=>{
      const o=document.createElement('option'); o.value=d.lokasi; o.textContent=d.lokasi;
      sel.appendChild(o);
    });
  }

  function updateMasterChart() {
    if (!masterChart) return;
    const n = document.getElementById('mfNama')?.value;
    const l = document.getElementById('mfLokasi')?.value;
    const f = masterChartData.find(d=>d.nama===n&&d.lokasi===l);
    const t = getChartTheme();
    masterChart.options.plugins.title.text  = f ? `${n} — ${l}` : 'Tidak ada data';
    masterChart.options.plugins.title.color = t.titleColor;
    masterChart.options.scales.y.ticks.color= t.textColor;
    masterChart.options.scales.y.grid.color = t.gridColor;
    masterChart.options.scales.x.ticks.color= t.textColor;
    masterChart.data.datasets[0].data       = f && Array.isArray(f.history) ? f.history : [0,0,0,0,0,0,0];
    masterChart.data.datasets[0].pointBackgroundColor = t.bgColor;
    masterChart.update('active');
  }

  // Init dengan data pertama
  const first = masterChartData[0];
  if (first) {
    const fN = document.getElementById('mfNama');
    if (fN) fN.value = first.nama;
    populateMasterLokasi(first.nama);
    const fL = document.getElementById('mfLokasi');
    if (fL) fL.value = first.lokasi;
    updateMasterChart();
  }

  document.getElementById('mfNama')?.addEventListener('change', function(){
    populateMasterLokasi(this.value); updateMasterChart();
  });
  document.getElementById('mfLokasi')?.addEventListener('change', updateMasterChart);
  document.addEventListener('themeChanged', ()=>{ if(masterChart) updateMasterChart(); });
})();
</script>
</body>
</html>
