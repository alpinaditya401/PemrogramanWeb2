<?php
/**
 * profil.php — Halaman Profil Pengguna
 * ─────────────────────────────────────────────────────────────
 * Semua role bisa akses halaman ini setelah login.
 * Fitur:
 *   1. Lihat & edit data diri (nama, email, bio, provinsi, kota, telepon)
 *   2. Ganti password (verifikasi password lama dulu)
 *   3. Upload foto profil
 *   4. Lihat statistik akun (tanggal daftar, login terakhir, total laporan)
 * ─────────────────────────────────────────────────────────────
 */
session_start();
require 'Server/koneksi.php';
require_once 'Server/bps_api.php';
cekLogin();

$uid      = (int)$_SESSION['user_id'];
$role     = $_SESSION['role'];
$isAdmin  = in_array($role, ['admin','admin_master']);

// ── AMBIL DATA USER TERKINI ───────────────────────────────────
$res  = $conn->query("SELECT * FROM users WHERE id=$uid LIMIT 1");
$user = $res ? $res->fetch_assoc() : [];

if (!$user) redirect('login.php');

$flash = ['type'=>'', 'msg'=>''];

// ── HANDLE UPDATE PROFIL ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    // ── A. Update data diri ───────────────────────────────────
    if ($aksi === 'update_profil') {
        $nama     = esc($conn, trim($_POST['nama_lengkap'] ?? ''));
        $email    = esc($conn, trim($_POST['email'] ?? ''));
        $telepon  = esc($conn, trim($_POST['telepon'] ?? ''));
        $bio      = esc($conn, trim($_POST['bio'] ?? ''));
        $provinsi = esc($conn, trim($_POST['provinsi'] ?? ''));
        $kota     = esc($conn, trim($_POST['kota'] ?? ''));
        $tgl      = esc($conn, trim($_POST['tgl_lahir'] ?? ''));
        $tgl_val  = $tgl ? "'$tgl'" : 'NULL';

        // Validasi email tidak duplikat (kecuali email sendiri)
        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $cek = $conn->query("SELECT id FROM users WHERE email='$email' AND id!=$uid LIMIT 1");
            if ($cek && $cek->num_rows > 0) {
                $flash = ['type'=>'error','msg'=>'Email sudah digunakan akun lain.'];
            } else {
                $conn->query("UPDATE users SET
                    nama_lengkap='$nama', email='$email', telepon='$telepon',
                    bio='$bio', provinsi='$provinsi', kota='$kota', tgl_lahir=$tgl_val
                    WHERE id=$uid");
                // Catat di activity log
                $conn->query("INSERT IGNORE INTO activity_log (user_id,username,aksi,deskripsi,ip_address)
                    VALUES ($uid,'{$_SESSION['username']}','update_profil','Update data diri','".esc($conn,$_SERVER['REMOTE_ADDR']??'')."')");
                $flash = ['type'=>'success','msg'=>'Profil berhasil diperbarui!'];
                // Refresh data
                $res  = $conn->query("SELECT * FROM users WHERE id=$uid LIMIT 1");
                $user = $res ? $res->fetch_assoc() : $user;
            }
        } else {
            $flash = ['type'=>'error','msg'=>'Format email tidak valid.'];
        }
    }

    // ── B. Ganti password ─────────────────────────────────────
    if ($aksi === 'ganti_password') {
        $pw_lama = trim($_POST['pw_lama'] ?? '');
        $pw_baru = trim($_POST['pw_baru'] ?? '');
        $pw_konfirm = trim($_POST['pw_konfirm'] ?? '');

        if (!$pw_lama || !$pw_baru || !$pw_konfirm) {
            $flash = ['type'=>'error','msg'=>'Semua field password wajib diisi.'];
        } elseif (!password_verify($pw_lama, $user['password'])) {
            $flash = ['type'=>'error','msg'=>'Password lama tidak cocok.'];
        } elseif (mb_strlen($pw_baru) < 6) {
            $flash = ['type'=>'error','msg'=>'Password baru minimal 6 karakter.'];
        } elseif ($pw_baru !== $pw_konfirm) {
            $flash = ['type'=>'error','msg'=>'Konfirmasi password tidak cocok.'];
        } elseif ($pw_baru === $pw_lama) {
            $flash = ['type'=>'error','msg'=>'Password baru tidak boleh sama dengan yang lama.'];
        } else {
            $hash = password_hash($pw_baru, PASSWORD_DEFAULT);
            $conn->query("UPDATE users SET password='$hash' WHERE id=$uid");
            $conn->query("INSERT IGNORE INTO activity_log (user_id,username,aksi,deskripsi,ip_address)
                VALUES ($uid,'{$_SESSION['username']}','ganti_password','Ganti password akun','".esc($conn,$_SERVER['REMOTE_ADDR']??'')."')");
            $flash = ['type'=>'success','msg'=>'Password berhasil diubah! Gunakan password baru saat login berikutnya.'];
        }
    }

    // ── C. Upload foto profil ─────────────────────────────────
    if ($aksi === 'upload_foto' && isset($_FILES['foto'])) {
        $file = $_FILES['foto'];
        $allowedTypes = ['image/jpeg','image/png','image/webp','image/gif'];
        $maxSize = 2 * 1024 * 1024; // 2MB

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $flash = ['type'=>'error','msg'=>'Upload gagal. Coba lagi.'];
        } elseif (!in_array($file['type'], $allowedTypes)) {
            $flash = ['type'=>'error','msg'=>'Format file tidak didukung. Gunakan JPG, PNG, atau WebP.'];
        } elseif ($file['size'] > $maxSize) {
            $flash = ['type'=>'error','msg'=>'Ukuran file maksimal 2MB.'];
        } else {
            $uploadDir = 'uploads/foto/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            // Hapus foto lama jika ada
            if (!empty($user['foto_profil']) && file_exists($uploadDir.$user['foto_profil'])) {
                @unlink($uploadDir.$user['foto_profil']);
            }

            $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'user_'.$uid.'_'.time().'.'.$ext;
            if (move_uploaded_file($file['tmp_name'], $uploadDir.$filename)) {
                $fn = esc($conn, $filename);
                $conn->query("UPDATE users SET foto_profil='$fn' WHERE id=$uid");
                $user['foto_profil'] = $filename;
                $flash = ['type'=>'success','msg'=>'Foto profil berhasil diperbarui!'];
            } else {
                $flash = ['type'=>'error','msg'=>'Gagal menyimpan foto. Pastikan folder uploads/foto/ ada dan writable.'];
            }
        }
    }
}

// ── STATISTIK AKUN ────────────────────────────────────────────
$statLaporan  = (int)($conn->query("SELECT COUNT(*) c FROM komoditas WHERE submitted_by=$uid")?->fetch_assoc()['c'] ?? 0);
$statApproved = (int)($conn->query("SELECT COUNT(*) c FROM komoditas WHERE submitted_by=$uid AND status='approved'")?->fetch_assoc()['c'] ?? 0);
$statArtikel  = (int)($conn->query("SELECT COUNT(*) c FROM artikel WHERE penulis_id=$uid AND is_publish=1")?->fetch_assoc()['c'] ?? 0);

// ── BACK LINK sesuai role ─────────────────────────────────────
$backLink = $isAdmin ? 'dashboard.php' : 'dashboard-user.php';
$pageTitle = 'Profil Saya';
?>
<!doctype html>
<html lang="id">
<head><?php include 'Assets/head.php'; ?>
<style>
  body{overflow:hidden}
  .prof-wrap{display:flex;height:100vh}
  .prof-side{width:220px;flex-shrink:0;display:flex;flex-direction:column;height:100%;background:var(--bg-secondary);border-right:1px solid var(--border)}
  .prof-main{flex:1;overflow-y:auto;padding:2rem}
  .prof-main::-webkit-scrollbar{width:4px}
  .prof-main::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px}
  .avatar-wrap{position:relative;width:96px;height:96px}
  .avatar-img{width:96px;height:96px;border-radius:50%;object-fit:cover;border:3px solid var(--border)}
  .avatar-initials{width:96px;height:96px;border-radius:50%;background:linear-gradient(135deg,#10b981,#059669);display:flex;align-items:center;justify-content:center;font-display:swap;font-family:'Cabinet Grotesk',sans-serif;font-size:2.25rem;font-weight:900;color:#fff;border:3px solid var(--border)}
  .avatar-upload-btn{position:absolute;bottom:2px;right:2px;width:28px;height:28px;border-radius:50%;background:var(--bg-card);border:2px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background .15s}
  .avatar-upload-btn:hover{background:var(--bg-secondary)}
  .stat-card{background:var(--surface);border:1px solid var(--border);border-radius:.75rem;padding:1rem;text-align:center}
  .section-card{background:var(--bg-card);border:1px solid var(--border);border-radius:1rem;overflow:hidden;margin-bottom:1.5rem}
  .section-header{padding:1.25rem 1.5rem;border-bottom:1px solid var(--border);background:var(--bg-secondary);display:flex;align-items:center;gap:.75rem}
</style>
</head>
<body>
<div class="prof-wrap">

<!-- SIDEBAR -->
<aside class="prof-side">
  <div class="h-16 flex items-center px-5 border-b border-[var(--border)] flex-shrink-0">
    <a href="<?= $backLink ?>" class="flex items-center gap-2 group">
      <div class="w-7 h-7 bg-brand-500 rounded-lg flex items-center justify-center shadow shadow-brand-500/30 group-hover:scale-105 transition-transform">
        <i data-lucide="trending-up" class="w-3.5 h-3.5 text-white"></i>
      </div>
      <span class="font-display font-black text-sm text-[var(--text-primary)]">InfoHarga</span>
    </a>
  </div>
  <nav class="flex-1 py-4 px-3 space-y-0.5 sidebar-nav">
    <a href="<?= $backLink ?>"><i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali</a>
    <a href="profil.php" class="active"><i data-lucide="user-circle" class="w-4 h-4"></i> Profil Saya</a>
    <a href="#edit-profil"><i data-lucide="pencil" class="w-4 h-4"></i> Edit Data Diri</a>
    <a href="#ganti-password"><i data-lucide="lock" class="w-4 h-4"></i> Ganti Password</a>
    <a href="#statistik"><i data-lucide="bar-chart-2" class="w-4 h-4"></i> Statistik Akun</a>
    <div class="nav-section">Aksi</div>
    <a href="Proses/logout.php" onclick="return confirm('Yakin keluar?')" class="text-red-400 hover:bg-red-500/8">
      <i data-lucide="log-out" class="w-4 h-4"></i> Logout
    </a>
  </nav>
  <div class="p-3 border-t border-[var(--border)]">
    <button data-action="toggle-theme" class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-medium text-[var(--text-muted)] hover:bg-[var(--surface)] transition sidebar-nav">
      <i data-lucide="moon" data-theme-icon="toggle" class="w-3.5 h-3.5"></i> Ganti Tema
    </button>
  </div>
</aside>

<!-- MAIN -->
<div class="prof-main">

  <!-- Flash message -->
  <?php if ($flash['msg']): ?>
  <div class="mb-6 flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium <?= $flash['type']==='success'?'msg-success':'msg-error' ?> animate-fade-up">
    <i data-lucide="<?= $flash['type']==='success'?'check-circle':'alert-circle' ?>" class="w-4 h-4 flex-shrink-0"></i>
    <?= htmlspecialchars($flash['msg']) ?>
  </div>
  <?php endif; ?>

  <!-- Header profil -->
  <div class="section-card mb-6">
    <div class="p-6 flex flex-col sm:flex-row items-start sm:items-center gap-6">
      <!-- Avatar + upload -->
      <form method="POST" enctype="multipart/form-data" id="fotoForm">
        <input type="hidden" name="aksi" value="upload_foto"/>
        <div class="avatar-wrap">
          <?php if (!empty($user['foto_profil']) && file_exists('uploads/foto/'.$user['foto_profil'])): ?>
          <img src="uploads/foto/<?= htmlspecialchars($user['foto_profil']) ?>" alt="Foto profil" class="avatar-img"/>
          <?php else: ?>
          <div class="avatar-initials"><?= strtoupper(substr($user['username'],0,1)) ?></div>
          <?php endif; ?>
          <label for="fotoInput" class="avatar-upload-btn" title="Ganti foto">
            <i data-lucide="camera" class="w-3.5 h-3.5 text-[var(--text-muted)]"></i>
          </label>
          <input type="file" id="fotoInput" name="foto" class="hidden" accept="image/jpeg,image/png,image/webp"
                 onchange="document.getElementById('fotoForm').submit()"/>
        </div>
      </form>

      <!-- Info user -->
      <div class="flex-1">
        <div class="flex items-center gap-2.5 mb-1">
          <h1 class="font-display font-black text-2xl text-[var(--text-primary)]">
            <?= htmlspecialchars($user['nama_lengkap'] ?: $user['username']) ?>
          </h1>
          <?php
          $roleBadge = ['admin_master'=>['badge-purple','Admin Master'],'admin'=>['badge-green','Admin'],'kontributor'=>['badge-blue','Kontributor'],'user'=>['badge-slate','Pengguna']];
          [$bc,$bl] = $roleBadge[$role] ?? ['badge-slate','User'];
          ?>
          <span class="badge <?= $bc ?>"><?= $bl ?></span>
        </div>
        <p class="text-sm text-[var(--text-muted)] flex items-center gap-1.5 mb-1">
          <i data-lucide="at-sign" class="w-3.5 h-3.5"></i><?= htmlspecialchars($user['username']) ?>
        </p>
        <p class="text-sm text-[var(--text-muted)] flex items-center gap-1.5 mb-1">
          <i data-lucide="mail" class="w-3.5 h-3.5"></i><?= htmlspecialchars($user['email']) ?>
        </p>
        <?php if ($user['provinsi']): ?>
        <p class="text-sm text-[var(--text-muted)] flex items-center gap-1.5 mb-1">
          <i data-lucide="map-pin" class="w-3.5 h-3.5"></i>
          <?= htmlspecialchars($user['kota'] ? $user['kota'].', '.$user['provinsi'] : $user['provinsi']) ?>
        </p>
        <?php endif; ?>
        <?php if ($user['bio']): ?>
        <p class="text-sm text-[var(--text-secondary)] mt-2 leading-relaxed max-w-lg"><?= htmlspecialchars($user['bio']) ?></p>
        <?php endif; ?>
        <p class="text-[10px] text-[var(--text-muted)] mt-2">
          Bergabung: <?= date('d F Y', strtotime($user['created_at'])) ?>
          <?php if($user['last_login']): ?> · Login terakhir: <?= date('d/m/Y H:i', strtotime($user['last_login'])) ?><?php endif; ?>
        </p>
      </div>
    </div>
  </div>

  <!-- Statistik -->
  <div id="statistik" class="grid grid-cols-3 gap-4 mb-6">
    <div class="stat-card">
      <div class="font-display font-black text-2xl text-[var(--text-primary)]"><?= $statLaporan ?></div>
      <div class="text-xs text-[var(--text-muted)] mt-0.5">Total Laporan</div>
    </div>
    <div class="stat-card">
      <div class="font-display font-black text-2xl text-brand-500"><?= $statApproved ?></div>
      <div class="text-xs text-[var(--text-muted)] mt-0.5">Disetujui</div>
    </div>
    <div class="stat-card">
      <div class="font-display font-black text-2xl text-blue-400"><?= $statArtikel ?></div>
      <div class="text-xs text-[var(--text-muted)] mt-0.5">Artikel Dipublish</div>
    </div>
  </div>

  <!-- Form Edit Profil -->
  <div id="edit-profil" class="section-card">
    <div class="section-header">
      <i data-lucide="pencil" class="w-4 h-4 text-brand-500"></i>
      <h2 class="font-display font-bold text-[var(--text-primary)]">Edit Data Diri</h2>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="aksi" value="update_profil"/>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">Nama Lengkap</label>
          <input type="text" name="nama_lengkap" class="input-field" value="<?= htmlspecialchars($user['nama_lengkap']??'') ?>" placeholder="Nama lengkap Anda" maxlength="120"/>
        </div>
        <div>
          <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">Email</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-3.5 flex items-center text-[var(--text-muted)] pointer-events-none"><i data-lucide="mail" class="w-4 h-4"></i></span>
            <input type="email" name="email" class="input-field input-icon" value="<?= htmlspecialchars($user['email']??'') ?>" required maxlength="120"/>
          </div>
        </div>
        <div>
          <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">Telepon</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-3.5 flex items-center text-[var(--text-muted)] pointer-events-none"><i data-lucide="phone" class="w-4 h-4"></i></span>
            <input type="tel" name="telepon" class="input-field input-icon" value="<?= htmlspecialchars($user['telepon']??'') ?>" placeholder="08xx-xxxx-xxxx" maxlength="20"/>
          </div>
        </div>
        <div>
          <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">Tanggal Lahir</label>
          <input type="date" name="tgl_lahir" class="input-field" value="<?= htmlspecialchars($user['tgl_lahir']??'') ?>" style="color-scheme:dark light"/>
        </div>
        <div>
          <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">Provinsi</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-3.5 flex items-center text-[var(--text-muted)] pointer-events-none"><i data-lucide="map" class="w-4 h-4"></i></span>
            <select id="selProvinsi" name="provinsi" class="input-field input-icon">
              <option value="">— Pilih Provinsi —</option>
              <?php foreach(PROVINSI_LIST as $p): ?>
              <option value="<?= htmlspecialchars($p) ?>" <?= ($user['provinsi']??'')===$p?'selected':'' ?>><?= htmlspecialchars($p) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">Kota / Kabupaten</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-3.5 flex items-center text-[var(--text-muted)] pointer-events-none"><i data-lucide="map-pin" class="w-4 h-4"></i></span>
            <select id="selKota" name="kota" class="input-field input-icon">
              <option value="<?= htmlspecialchars($user['kota']??'') ?>"><?= htmlspecialchars($user['kota'] ?: '— Pilih Provinsi dulu —') ?></option>
            </select>
          </div>
        </div>
      </div>
      <div>
        <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">Bio / Deskripsi Singkat</label>
        <textarea name="bio" rows="3" class="input-field" placeholder="Ceritakan sedikit tentang diri Anda..." maxlength="500"><?= htmlspecialchars($user['bio']??'') ?></textarea>
        <p class="text-[10px] text-[var(--text-muted)] mt-1">Maks. 500 karakter</p>
      </div>
      <div class="flex justify-end pt-2">
        <button type="submit" class="flex items-center gap-2 px-6 py-2.5 bg-brand-600 hover:bg-brand-500 text-white font-display font-bold rounded-xl text-sm transition shadow shadow-brand-600/20">
          <i data-lucide="save" class="w-4 h-4"></i> Simpan Perubahan
        </button>
      </div>
    </form>
  </div>

  <!-- Form Ganti Password -->
  <div id="ganti-password" class="section-card">
    <div class="section-header">
      <i data-lucide="lock" class="w-4 h-4 text-amber-400"></i>
      <h2 class="font-display font-bold text-[var(--text-primary)]">Ganti Password</h2>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="aksi" value="ganti_password"/>
      <div class="flex items-start gap-3 p-4 rounded-xl bg-amber-500/6 border border-amber-500/20 mb-4">
        <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-400 flex-shrink-0 mt-0.5"></i>
        <p class="text-xs text-[var(--text-secondary)] leading-relaxed">Gunakan password yang kuat — minimal 6 karakter, kombinasi huruf dan angka. Setelah ganti, gunakan password baru saat login berikutnya.</p>
      </div>
      <div>
        <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">Password Lama</label>
        <div class="relative">
          <span class="absolute inset-y-0 left-3.5 flex items-center text-[var(--text-muted)] pointer-events-none"><i data-lucide="lock" class="w-4 h-4"></i></span>
          <input id="pwLama" type="password" name="pw_lama" class="input-field input-icon pr-11" placeholder="••••••••" required autocomplete="current-password"/>
          <button type="button" onclick="togglePassword('pwLama','btnLama')" id="btnLama" class="absolute inset-y-0 right-3.5 flex items-center text-[var(--text-muted)] hover:text-[var(--text-primary)] transition">
            <i data-lucide="eye" class="w-4 h-4"></i>
          </button>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">Password Baru</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-3.5 flex items-center text-[var(--text-muted)] pointer-events-none"><i data-lucide="key" class="w-4 h-4"></i></span>
            <input id="pwBaru" type="password" name="pw_baru" class="input-field input-icon pr-11" placeholder="Min. 6 karakter" required minlength="6" autocomplete="new-password"/>
            <button type="button" onclick="togglePassword('pwBaru','btnBaru')" id="btnBaru" class="absolute inset-y-0 right-3.5 flex items-center text-[var(--text-muted)] hover:text-[var(--text-primary)] transition">
              <i data-lucide="eye" class="w-4 h-4"></i>
            </button>
          </div>
        </div>
        <div>
          <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">Konfirmasi Password Baru</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-3.5 flex items-center text-[var(--text-muted)] pointer-events-none"><i data-lucide="key" class="w-4 h-4"></i></span>
            <input id="pwKonfirm" type="password" name="pw_konfirm" class="input-field input-icon pr-11" placeholder="Ulangi password baru" required autocomplete="new-password"/>
            <button type="button" onclick="togglePassword('pwKonfirm','btnKonfirm')" id="btnKonfirm" class="absolute inset-y-0 right-3.5 flex items-center text-[var(--text-muted)] hover:text-[var(--text-primary)] transition">
              <i data-lucide="eye" class="w-4 h-4"></i>
            </button>
          </div>
        </div>
      </div>
      <div class="flex justify-end pt-2">
        <button type="submit" class="flex items-center gap-2 px-6 py-2.5 bg-amber-500 hover:bg-amber-400 text-white font-display font-bold rounded-xl text-sm transition shadow shadow-amber-500/20">
          <i data-lucide="shield-check" class="w-4 h-4"></i> Ganti Password
        </button>
      </div>
    </form>
  </div>

</div><!-- end prof-main -->
</div><!-- end prof-wrap -->

<script>
window.PROVINSI_KOTA_JS = <?= json_encode(PROVINSI_KOTA, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="Assets/scripts.js"></script>
<script>
lucide.createIcons();
// Province-city cascade for profil form
(function(){
  const ps = document.getElementById('selProvinsi');
  const ks = document.getElementById('selKota');
  const curKota = <?= json_encode($user['kota']??'') ?>;
  if(!ps||!ks) return;
  function update(sel){
    const cities=(window.PROVINSI_KOTA_JS||{})[ps.value]||[];
    ks.innerHTML='';
    const ph=document.createElement('option');
    ph.value=''; ph.textContent=cities.length?'— Pilih Kota —':'— (pilih provinsi dulu) —';
    ks.appendChild(ph);
    cities.forEach(c=>{
      const o=document.createElement('option');
      o.value=c; o.textContent=c;
      if(sel&&c===sel) o.selected=true;
      ks.appendChild(o);
    });
    ks.disabled=!cities.length;
  }
  if(ps.value) update(curKota);
  ps.addEventListener('change',()=>update(''));
})();
</script>
</body>
</html>
