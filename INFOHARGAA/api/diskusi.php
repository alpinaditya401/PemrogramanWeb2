<?php
/**
 * diskusi.php — Halaman Diskusi Harga Komoditas
 * ─────────────────────────────────────────────────────────────
 * User bisa komentar tentang penyebab harga naik/turun,
 * balas komentar user lain, dan beri reaksi like/helpful.
 *
 * Parameter URL:
 *   ?komoditas_id=X  → diskusi spesifik satu komoditas
 *   (tanpa param)    → diskusi umum semua komoditas
 * ─────────────────────────────────────────────────────────────
 */
session_start();
require 'Server/koneksi.php';
cekLogin();

$uid       = (int)$_SESSION['user_id'];
$uname     = $_SESSION['username'];
$role      = $_SESSION['role'];
$isAdmin   = in_array($role, ['admin','admin_master']);
$isKontrib = in_array($role, ['admin','admin_master','kontributor']);

// Komoditas yang sedang didiskusikan
$komId   = (int)($_GET['komoditas_id'] ?? 0);
$komData = null;
$komList = []; // untuk dropdown pilih komoditas

$resKomList = $conn->query("SELECT id, nama, lokasi, provinsi, harga_sekarang, harga_kemarin
                             FROM komoditas WHERE status='approved'
                             ORDER BY nama ASC, lokasi ASC");
if ($resKomList) while ($r = $resKomList->fetch_assoc()) $komList[] = $r;

if ($komId) {
    $komData = $conn->query("SELECT * FROM komoditas WHERE id=$komId AND status='approved' LIMIT 1")?->fetch_assoc();
    if (!$komData) $komId = 0;
}

// Jumlah komentar
$totalKom = (int)$conn->query("SELECT COUNT(*) c FROM diskusi WHERE is_deleted=0"
    .($komId ? " AND komoditas_id=$komId" : " AND komoditas_id IS NULL"))?->fetch_assoc()['c'];

$pageTitle = $komData ? 'Diskusi: '.htmlspecialchars($komData['nama']) : 'Forum Diskusi Harga';
$dashBack  = in_array($role,['admin','admin_master']) ? 'dashboard.php' : 'dashboard-user.php';
?>
<!doctype html>
<html lang="id">
<head><?php include 'Assets/head.php'; ?>
<style>
  body{overflow:hidden}
  .dsk-wrap{display:flex;height:100vh}
  .dsk-side{width:240px;flex-shrink:0;display:flex;flex-direction:column;height:100%;background:var(--bg-secondary);border-right:1px solid var(--border)}
  .dsk-main{flex:1;display:flex;flex-direction:column;overflow:hidden}
  .dsk-header{padding:1rem 1.5rem;border-bottom:1px solid var(--border);background:var(--bg-card);flex-shrink:0}
  .dsk-body{flex:1;overflow-y:auto;padding:1.5rem}
  .dsk-body::-webkit-scrollbar{width:4px}
  .dsk-body::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px}
  .dsk-footer{border-top:1px solid var(--border);padding:1rem 1.5rem;background:var(--bg-card);flex-shrink:0}

  /* Komentar bubbles */
  .kom-card{background:var(--bg-card);border:1px solid var(--border);border-radius:1rem;padding:1rem 1.25rem;margin-bottom:.875rem;transition:border-color .15s}
  .kom-card:hover{border-color:var(--border-hover)}
  .kom-card.is-reply{margin-left:2.5rem;margin-top:.5rem;border-radius:.875rem;background:var(--surface)}
  .kom-card.deleted{opacity:.45;pointer-events:none}
  .avatar-sm{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#10b981,#059669);display:flex;align-items:center;justify-content:center;font-family:'Cabinet Grotesk',sans-serif;font-weight:900;font-size:.8rem;color:#fff;flex-shrink:0}
  .avatar-sm.admin{background:linear-gradient(135deg,#a855f7,#7c3aed)}
  .avatar-sm.admin_master{background:linear-gradient(135deg,#8b5cf6,#6d28d9)}
  .avatar-sm.kontributor{background:linear-gradient(135deg,#3b82f6,#1d4ed8)}
  .react-btn{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:99px;border:1px solid var(--border);font-size:.7rem;font-weight:600;color:var(--text-muted);cursor:pointer;transition:all .15s;background:transparent}
  .react-btn:hover{border-color:var(--border-hover);color:var(--text-secondary);background:var(--surface)}
  .react-btn.active-like{border-color:rgba(16,185,129,.4);color:#10b981;background:rgba(16,185,129,.08)}
  .react-btn.active-helpful{border-color:rgba(59,130,246,.4);color:#3b82f6;background:rgba(59,130,246,.08)}
  .reply-form{display:none;margin-top:.75rem;padding-top:.75rem;border-top:1px solid var(--border)}
  .reply-form.show{display:block}
  .kom-input{width:100%;background:var(--surface);border:1px solid var(--border);border-radius:.75rem;padding:.625rem .875rem;font-size:.875rem;color:var(--text-primary);outline:none;resize:vertical;min-height:80px;font-family:inherit;transition:border-color .15s}
  .kom-input:focus{border-color:var(--brand-500,#10b981);box-shadow:0 0 0 3px rgba(16,185,129,.1)}
  .badge-role-admin_master{background:rgba(139,92,246,.12);color:#8b5cf6;font-size:.65rem;padding:1px 6px;border-radius:4px;font-weight:700}
  .badge-role-admin{background:rgba(16,185,129,.12);color:#10b981;font-size:.65rem;padding:1px 6px;border-radius:4px;font-weight:700}
  .badge-role-kontributor{background:rgba(59,130,246,.12);color:#3b82f6;font-size:.65rem;padding:1px 6px;border-radius:4px;font-weight:700}
  #loadingSpinner{display:none}
  .kom-time{font-size:.7rem;color:var(--text-muted)}
  .empty-state{text-align:center;padding:3rem 1rem;color:var(--text-muted)}
</style>
</head>
<body>
<div class="dsk-wrap">

<!-- ══ SIDEBAR ═══════════════════════════════════════════════ -->
<aside class="dsk-side">
  <div class="h-14 flex items-center px-4 border-b border-[var(--border)] flex-shrink-0">
    <a href="<?= $dashBack ?>" class="flex items-center gap-2">
      <div class="w-6 h-6 bg-brand-500 rounded-lg flex items-center justify-center shadow shadow-brand-500/30">
        <i data-lucide="trending-up" class="w-3 h-3 text-white"></i>
      </div>
      <span class="font-display font-black text-sm text-[var(--text-primary)]">InfoHarga</span>
    </a>
  </div>

  <!-- Pilih komoditas untuk filter diskusi -->
  <div class="p-3 border-b border-[var(--border)]">
    <p class="text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-wider mb-2">Filter Diskusi</p>
    <a href="diskusi.php"
       class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-medium mb-1
              <?= !$komId ? 'bg-brand-500/10 text-brand-500 font-bold' : 'text-[var(--text-secondary)] hover:bg-[var(--surface)]' ?> transition">
      <i data-lucide="message-circle" class="w-3.5 h-3.5"></i> Semua Diskusi
    </a>
  </div>

  <div class="flex-1 overflow-y-auto slim-scroll px-2 py-2">
    <p class="text-[10px] font-bold text-[var(--text-muted)] uppercase tracking-wider px-2 mb-2">Per Komoditas</p>
    <?php foreach ($komList as $km):
      $sel = $komId === (int)$km['id'];
      $naik = (int)$km['harga_sekarang'] > (int)$km['harga_kemarin'];
      $turun= (int)$km['harga_sekarang'] < (int)$km['harga_kemarin'];
    ?>
    <a href="diskusi.php?komoditas_id=<?= $km['id'] ?>"
       class="flex items-center gap-2 px-2 py-2 rounded-lg text-xs transition group
              <?= $sel ? 'bg-brand-500/10' : 'hover:bg-[var(--surface)]' ?>">
      <div class="w-1.5 h-1.5 rounded-full flex-shrink-0
                  <?= $naik?'bg-brand-500':($turun?'bg-red-400':'bg-slate-400') ?>"></div>
      <div class="min-w-0 flex-1">
        <div class="font-medium <?= $sel?'text-brand-500':'text-[var(--text-secondary)]' ?> truncate">
          <?= htmlspecialchars($km['nama']) ?>
        </div>
        <div class="text-[var(--text-muted)] truncate text-[10px]"><?= htmlspecialchars($km['lokasi']) ?></div>
      </div>
      <span class="text-[10px] font-bold font-display <?= $naik?'text-brand-500':($turun?'text-red-400':'text-[var(--text-muted)]') ?> flex-shrink-0">
        <?= rupiah((int)$km['harga_sekarang']) ?>
      </span>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="border-t border-[var(--border)] px-3 py-2 sidebar-nav space-y-0.5 flex-shrink-0">
    <a href="<?= $dashBack ?>" class="text-xs"><i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Dashboard</a>
    <a href="profil.php" class="text-xs"><i data-lucide="user-circle" class="w-3.5 h-3.5"></i> Profil Saya</a>
    <a href="#" data-action="toggle-theme" class="text-xs"><i data-lucide="moon" data-theme-icon="toggle" class="w-3.5 h-3.5"></i> Ganti Tema</a>
  </div>
</aside>

<!-- ══ MAIN ══════════════════════════════════════════════════ -->
<div class="dsk-main">

  <!-- Header -->
  <div class="dsk-header">
    <div class="flex items-start justify-between">
      <div>
        <?php if ($komData): ?>
        <div class="flex items-center gap-2 mb-1">
          <h1 class="font-display font-bold text-lg text-[var(--text-primary)]">
            Diskusi: <span class="text-brand-500"><?= htmlspecialchars($komData['nama']) ?></span>
          </h1>
          <?php
            $s = (int)$komData['harga_sekarang']; $k = (int)$komData['harga_kemarin'];
            $naik=$s>$k; $turun=$s<$k;
          ?>
          <?= $naik?'<span class="badge badge-green">▲ Naik</span>':($turun?'<span class="badge badge-red">▼ Turun</span>':'<span class="badge badge-slate">■ Stabil</span>') ?>
        </div>
        <p class="text-xs text-[var(--text-muted)]">
          <?= htmlspecialchars($komData['lokasi']) ?>, <?= htmlspecialchars($komData['provinsi']) ?> ·
          Kemarin: <?= rupiah($k) ?> → Sekarang: <strong><?= rupiah($s) ?></strong>
          <?php if ($s !== $k): $sel=abs($s-$k); $pct=round($sel/$k*100,1); ?>
          · <span class="<?= $naik?'text-brand-500':'text-red-400' ?>"><?= $naik?'+':'-' ?><?= rupiah($sel) ?> (<?= $pct ?>%)</span>
          <?php endif; ?>
        </p>
        <?php else: ?>
        <h1 class="font-display font-bold text-lg text-[var(--text-primary)]">Forum Diskusi Harga</h1>
        <p class="text-xs text-[var(--text-muted)]">Diskusi umum tentang pergerakan harga komoditas Indonesia</p>
        <?php endif; ?>
      </div>
      <div class="flex items-center gap-2">
        <span class="text-xs text-[var(--text-muted)] flex items-center gap-1">
          <i data-lucide="message-circle" class="w-3.5 h-3.5"></i>
          <span id="totalKomLabel"><?= $totalKom ?> komentar</span>
        </span>
        <?php if ($komData): ?>
        <a href="chart.php?komoditas=<?= urlencode($komData['nama']) ?>&provinsi=<?= urlencode($komData['provinsi']) ?>"
           class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-semibold text-[var(--text-secondary)] hover:text-brand-500 hover:bg-brand-500/8 transition">
          <i data-lucide="bar-chart-2" class="w-3.5 h-3.5"></i> Lihat Grafik
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Komentar list -->
  <div class="dsk-body" id="komList">
    <div class="empty-state" id="loadingState">
      <div class="w-8 h-8 border-2 border-brand-500 border-t-transparent rounded-full mx-auto mb-3"
           style="animation:spin 1s linear infinite"></div>
      <p class="text-sm">Memuat diskusi...</p>
    </div>
  </div>

  <!-- Form kirim komentar baru -->
  <div class="dsk-footer">
    <div class="flex items-start gap-3">
      <div class="avatar-sm <?= $role ?> flex-shrink-0 mt-1">
        <?= strtoupper(substr($uname,0,1)) ?>
      </div>
      <div class="flex-1">
        <textarea id="mainInput" class="kom-input" rows="2"
          placeholder="<?= $komData ? 'Bagikan pendapat Anda tentang harga '.$komData['nama'].'...' : 'Tulis komentar atau pertanyaan tentang harga komoditas...' ?>"
          maxlength="1000"></textarea>
        <div class="flex items-center justify-between mt-2">
          <span class="text-[10px] text-[var(--text-muted)]">
            <span id="charCount">0</span>/1000 karakter · Sopan &amp; informatif ya 🙏
          </span>
          <button onclick="kirimKomentar()"
                  class="flex items-center gap-2 px-5 py-2 bg-brand-600 hover:bg-brand-500 text-white font-display font-bold rounded-xl text-sm transition shadow shadow-brand-600/20">
            <i data-lucide="send" class="w-3.5 h-3.5"></i> Kirim
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

</div><!-- end dsk-wrap -->

<style>@keyframes spin{to{transform:rotate(360deg)}}</style>

<script>
const KOM_ID      = <?= $komId ?: 'null' ?>;
const SELF_UID    = <?= $uid ?>;
const SELF_UNAME  = <?= json_encode($uname) ?>;
const SELF_ROLE   = <?= json_encode($role) ?>;
const IS_ADMIN    = <?= $isAdmin?'true':'false' ?>;

// ── FORMAT WAKTU RELATIF ─────────────────────────────────────
function timeAgo(dateStr) {
  const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
  if (diff < 60)    return 'baru saja';
  if (diff < 3600)  return Math.floor(diff/60) + ' menit lalu';
  if (diff < 86400) return Math.floor(diff/3600) + ' jam lalu';
  if (diff < 604800)return Math.floor(diff/86400) + ' hari lalu';
  return new Date(dateStr).toLocaleDateString('id-ID',{day:'numeric',month:'short',year:'numeric'});
}

// ── AVATAR ────────────────────────────────────────────────────
function makeAvatar(username, role) {
  const roleClass = {admin_master:'admin_master', admin:'admin', kontributor:'kontributor'}[role] || '';
  return `<div class="avatar-sm ${roleClass}">${username.charAt(0).toUpperCase()}</div>`;
}

// ── BADGE ROLE ────────────────────────────────────────────────
function roleBadge(role) {
  const map = {
    admin_master: ['badge-role-admin_master','Master'],
    admin:        ['badge-role-admin','Admin'],
    kontributor:  ['badge-role-kontributor','Kontributor'],
  };
  if (!map[role]) return '';
  return `<span class="${map[role][0]}">${map[role][1]}</span>`;
}

// ── RENDER KOMENTAR (rekursif — support nested reply tak terbatas) ─────
// depth: 0=komentar utama, 1=reply, 2=reply of reply, dst
function renderKom(d, depth=0) {
  const isNested = depth > 0;
  // Indent makin kecil di depth tinggi agar tidak terlalu sempit
  const indent   = Math.min(depth * 32, 80); // max indent 80px

  if (d.is_deleted == 1) {
    return `<div class="kom-card" style="${isNested?'margin-left:'+indent+'px;':''}" >
      <span style="font-size:.8rem;font-style:italic;color:var(--text-muted)">Komentar ini telah dihapus.</span>
    </div>`;
  }

  const canDelete = (d.uid == SELF_UID || IS_ADMIN);
  // Render balasan secara rekursif — tiap balasan indent lebih dalam
  const replies   = (d.replies || []).map(r => renderKom(r, depth + 1)).join('');
  const likeActive = d.saya_like   > 0 ? 'active-like'    : '';
  const helpActive = d.saya_helpful> 0 ? 'active-helpful' : '';

  // Warna garis kiri berdasarkan depth
  const depthColors = ['', 'border-l-2 border-brand-500/30', 'border-l-2 border-blue-400/30', 'border-l-2 border-purple-400/30'];
  const depthClass  = depthColors[Math.min(depth, depthColors.length-1)] || 'border-l-2 border-slate-400/20';

  return `
  <div class="kom-card ${isNested ? depthClass : ''}" id="kom-${d.id}"
       style="${isNested ? 'margin-left:'+indent+'px;margin-top:6px;border-radius:.75rem;background:var(--surface)' : ''}">
    <div class="flex items-start gap-2.5">
      ${makeAvatar(d.username, d.user_role)}
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 flex-wrap mb-1.5">
          <a href="profil_publik.php?u=${encodeURIComponent(d.username)}"
             class="font-bold text-sm text-[var(--text-primary)] hover:text-brand-500 transition">
            ${escHtml(d.username)}
          </a>
          ${roleBadge(d.user_role)}
          ${depth > 0 ? '<span style="font-size:.65rem;color:var(--text-muted);font-style:italic">↩ membalas</span>' : ''}
          <span class="kom-time">${timeAgo(d.created_at)}</span>
        </div>
        <p class="text-sm text-[var(--text-secondary)] leading-relaxed whitespace-pre-line">${escHtml(d.pesan)}</p>

        <div class="flex items-center gap-2 mt-2.5 flex-wrap">
          <button onclick="toggleReaksi(${d.id},'like',this)"
                  class="react-btn ${likeActive}">
            👍 <span class="like-ct">${d.likes||0}</span>
          </button>
          ${depth===0?`<button onclick="toggleReaksi(${d.id},'helpful',this)"
                  class="react-btn ${helpActive}">
            💡 <span class="helpful-ct">${d.helpful_ct||0}</span>
          </button>`:''}
          <!-- Tombol Balas ada di semua level (bukan hanya root) -->
          <button onclick="toggleReplyForm(${d.id})"
                  class="react-btn" style="gap:4px">
            <i data-lucide="corner-down-right" style="width:12px;height:12px"></i> Balas
          </button>
          ${canDelete?`<button onclick="hapusKom(${d.id})"
                  class="react-btn" style="margin-left:auto">
            <i data-lucide="trash-2" style="width:11px;height:11px"></i>
          </button>`:''}
        </div>

        <!-- Form balas (muncul saat klik tombol Balas) -->
        <div class="reply-form" id="replyForm-${d.id}">
          <div class="flex items-start gap-2.5">
            ${makeAvatar(SELF_UNAME, SELF_ROLE)}
            <div class="flex-1">
              <textarea id="replyInput-${d.id}" class="kom-input" rows="2"
                placeholder="Membalas @${escHtml(d.username)}..." maxlength="500"></textarea>
              <div class="flex justify-end gap-2 mt-1.5">
                <button onclick="toggleReplyForm(${d.id})"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold text-[var(--text-secondary)] bg-[var(--surface)] hover:bg-[var(--surface-hover)] transition">
                  Batal
                </button>
                <button onclick="kirimBalasan(${d.id})"
                        class="flex items-center gap-1.5 px-4 py-1.5 bg-brand-600 hover:bg-brand-500 text-white font-bold rounded-lg text-xs transition">
                  <i data-lucide="send" style="width:12px;height:12px"></i> Kirim Balasan
                </button>
              </div>
            </div>
          </div>
        </div>
        <!-- Balasan (rekursif) -->
        <div id="replies-${d.id}">${replies}</div>
      </div>
    </div>
  </div>`;
}

// ── ESCAPE HTML ───────────────────────────────────────────────
function escHtml(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── MUAT SEMUA KOMENTAR ───────────────────────────────────────
async function muatKomentar() {
  const list = document.getElementById('komList');
  try {
    const url = `Proses/prosesDiskusi.php?aksi=ambil${KOM_ID?'&komoditas_id='+KOM_ID:''}`;
    const res = await fetch(url);
    const dat = await res.json();

    if (!dat.ok) throw new Error(dat.msg);

    if (!dat.data.length) {
      list.innerHTML = `<div class="empty-state">
        <i data-lucide="message-circle" style="width:48px;height:48px;margin:0 auto 12px;opacity:.2;display:block"></i>
        <h3 style="font-weight:700;font-size:1rem;margin-bottom:6px">Belum ada diskusi</h3>
        <p style="font-size:.875rem">Jadilah yang pertama berkomentar! Bagikan pendapat Anda tentang harga ini.</p>
      </div>`;
      lucide.createIcons();
      return;
    }

    list.innerHTML = dat.data.map(d => renderKom(d, 0)).join('');
    lucide.createIcons();
    document.getElementById('totalKomLabel').textContent =
      (function countAll(items){return items.reduce((a,d)=>a+1+countAll(d.replies||[]),0)})(dat.data) + ' komentar';
  } catch(e) {
    list.innerHTML = `<div class="empty-state"><p>Gagal memuat diskusi. Coba refresh halaman.</p></div>`;
  }
}

// ── KIRIM KOMENTAR UTAMA ──────────────────────────────────────
async function kirimKomentar() {
  const input = document.getElementById('mainInput');
  const pesan = input.value.trim();
  if (!pesan) { input.focus(); return; }

  const fd = new FormData();
  fd.append('aksi','kirim');
  fd.append('pesan', pesan);
  if (KOM_ID) fd.append('komoditas_id', KOM_ID);

  input.disabled = true;
  try {
    const res = await fetch('Proses/prosesDiskusi.php', { method:'POST', body:fd });
    const dat = await res.json();
    if (dat.ok) {
      input.value = '';
      document.getElementById('charCount').textContent = '0';
      await muatKomentar(); // refresh list
    } else {
      alert(dat.msg);
    }
  } finally {
    input.disabled = false;
    input.focus();
  }
}

// ── KIRIM BALASAN ─────────────────────────────────────────────
async function kirimBalasan(parentId) {
  const input = document.getElementById('replyInput-'+parentId);
  const pesan = input?.value.trim();
  if (!pesan) { input?.focus(); return; }

  const fd = new FormData();
  fd.append('aksi','kirim');
  fd.append('pesan', pesan);
  fd.append('parent_id', parentId);
  if (KOM_ID) fd.append('komoditas_id', KOM_ID);

  input.disabled = true;
  try {
    const res = await fetch('Proses/prosesDiskusi.php', { method:'POST', body:fd });
    const dat = await res.json();
    if (dat.ok) {
      input.value = '';
      await muatKomentar();
    } else { alert(dat.msg); }
  } finally {
    input.disabled = false;
  }
}

// ── HAPUS KOMENTAR ────────────────────────────────────────────
async function hapusKom(id) {
  if (!confirm('Hapus komentar ini?')) return;
  const fd = new FormData();
  fd.append('aksi','hapus'); fd.append('id',id);
  const res = await fetch('Proses/prosesDiskusi.php',{method:'POST',body:fd});
  const dat = await res.json();
  if (dat.ok) {
    const el = document.getElementById('kom-'+id);
    if (el) el.classList.add('deleted');
    await muatKomentar();
  } else { alert(dat.msg); }
}

// ── TOGGLE REAKSI (like / helpful) ───────────────────────────
async function toggleReaksi(id, tipe, btn) {
  const fd = new FormData();
  fd.append('aksi','reaksi'); fd.append('id',id); fd.append('tipe',tipe);
  try {
    const res = await fetch('Proses/prosesDiskusi.php',{method:'POST',body:fd});
    const dat = await res.json();
    if (!dat.ok) return;
    // Update UI tanpa reload
    const activeClass = tipe==='like' ? 'active-like' : 'active-helpful';
    btn.classList.toggle(activeClass, dat.data.aktif);
    const ct = btn.querySelector(tipe==='like' ? '.like-ct' : '.helpful-ct');
    if (ct) ct.textContent = dat.data.jumlah;
  } catch(e) {}
}

// ── TOGGLE FORM BALAS ─────────────────────────────────────────
function toggleReplyForm(id) {
  const form = document.getElementById('replyForm-'+id);
  if (!form) return;
  form.classList.toggle('show');
  if (form.classList.contains('show')) {
    document.getElementById('replyInput-'+id)?.focus();
  }
}

// ── CHAR COUNTER ──────────────────────────────────────────────
document.getElementById('mainInput')?.addEventListener('input', function() {
  document.getElementById('charCount').textContent = this.value.length;
});

// ── CTRL+ENTER untuk kirim ────────────────────────────────────
document.getElementById('mainInput')?.addEventListener('keydown', function(e) {
  if (e.ctrlKey && e.key === 'Enter') kirimKomentar();
});

// Init
muatKomentar();
</script>

<script src="Assets/scripts.js"></script>
<script>lucide.createIcons();</script>
</body>
</html>
