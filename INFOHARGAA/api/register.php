<?php
/**
 * register.php — Halaman Registrasi dengan pilihan Role
 * ─────────────────────────────────────────────────────────────
 * User dapat memilih role saat daftar:
 *   - user        → akses dashboard, grafik, artikel
 *   - kontributor → + bisa kirim laporan harga lapangan
 *
 * Provinsi & kota diambil dari mapping PROVINSI_KOTA (Server/bps_api.php)
 * yang bersumber dari API BPS.
 * ─────────────────────────────────────────────────────────────
 */
session_start();
require 'Server/koneksi.php';
require_once 'Server/bps_api.php';

if (isset($_SESSION['login'])) redirect('dashboard-user.php');

$pageTitle = 'Daftar Akun';
$pageDesc  = 'Buat akun untuk akses dashboard harga komoditas Indonesia.';
?>
<!doctype html>
<html lang="id">
<head>
<?php include 'Assets/head.php'; ?>
<style>
/*
 * FIX DARK MODE BACKGROUND — sama seperti login.php
 * Gunakan background-color dari CSS var agar ikut berganti tema
 */
body {
  background-color: var(--bg-primary) !important;
  background-image:
    radial-gradient(ellipse 70% 60% at 70% 30%, rgba(59,130,246,.06) 0%, transparent 55%),
    radial-gradient(ellipse 50% 50% at 20% 70%, rgba(16,185,129,.06) 0%, transparent 50%);
  background-attachment: fixed;
  transition: background-color .25s !important;
}
.dark body, html.dark body {
  background-image:
    radial-gradient(ellipse 70% 60% at 70% 30%, rgba(59,130,246,.04) 0%, transparent 55%),
    radial-gradient(ellipse 50% 50% at 20% 70%, rgba(16,185,129,.03) 0%, transparent 50%);
}

/* Role selection cards */
.role-card {
  border: 2px solid var(--border);
  border-radius: .875rem;
  padding: 1rem;
  cursor: pointer;
  transition: border-color .15s, background .15s, transform .1s;
  background: var(--surface);
}
.role-card:hover {
  border-color: var(--border-hover);
  background: var(--surface-hover);
}
.role-card.selected-user {
  border-color: #3b82f6;
  background: rgba(59,130,246,.06);
}
.role-card.selected-kontributor {
  border-color: #10b981;
  background: rgba(16,185,129,.06);
}
input[type="radio"] { display: none; }
</style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

  <a href="login.php" class="fixed top-5 left-5 flex items-center gap-1.5 text-sm text-[var(--text-muted)] hover:text-[var(--text-primary)] transition group">
    <i data-lucide="arrow-left" class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform"></i> Login
  </a>
  <button data-action="toggle-theme"
          class="fixed top-5 right-5 w-9 h-9 flex items-center justify-center rounded-lg bg-[var(--surface)] hover:bg-[var(--surface-hover)] border border-[var(--border)] text-[var(--text-muted)] hover:text-[var(--text-primary)] transition">
    <i data-lucide="moon" data-theme-icon="toggle" class="w-4 h-4"></i>
  </button>

  <div class="w-full max-w-lg animate-fade-up py-10">

    <!-- Logo -->
    <div class="flex items-center justify-center gap-2.5 mb-7">
      <div class="w-9 h-9 bg-brand-500 rounded-xl flex items-center justify-center shadow-lg shadow-brand-500/30">
        <i data-lucide="trending-up" class="w-5 h-5 text-white"></i>
      </div>
      <span class="font-display font-black text-xl text-[var(--text-primary)]">
        InfoHarga<span class="text-brand-500">Komoditi</span>
      </span>
    </div>

    <div class="card p-7 shadow-2xl">
      <h1 class="font-display font-black text-2xl text-[var(--text-primary)] mb-1">Buat Akun</h1>
      <p class="text-sm text-[var(--text-muted)] mb-6">Pilih peran dan lengkapi data Anda</p>

      <div id="msg-box" class="hidden mb-5 text-sm"></div>

      <form action="Proses/prosesRegister.php" method="POST" novalidate class="space-y-4">

        <!-- ── STEP 1: PILIH ROLE ── -->
        <div>
          <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-3">
            Daftar sebagai <span class="text-red-400">*</span>
          </label>
          <div class="grid grid-cols-2 gap-3">
            <!-- User biasa -->
            <label class="role-card selected-user" id="cardUser" for="roleUser" onclick="selectRole('user')">
              <input type="radio" name="role" id="roleUser" value="user" checked/>
              <div class="flex items-center gap-2.5 mb-2">
                <div class="w-8 h-8 rounded-lg bg-blue-500/15 flex items-center justify-center flex-shrink-0">
                  <i data-lucide="user" class="w-4 h-4 text-blue-400"></i>
                </div>
                <div>
                  <div class="font-display font-bold text-sm text-[var(--text-primary)]">Pengguna</div>
                  <span class="badge badge-blue text-[9px]">User</span>
                </div>
              </div>
              <p class="text-[11px] text-[var(--text-muted)] leading-relaxed">
                Akses grafik harga, artikel edukasi, dan pantau komoditas di seluruh Indonesia.
              </p>
            </label>

            <!-- Kontributor -->
            <label class="role-card" id="cardKontributor" for="roleKontributor" onclick="selectRole('kontributor')">
              <input type="radio" name="role" id="roleKontributor" value="kontributor"/>
              <div class="flex items-center gap-2.5 mb-2">
                <div class="w-8 h-8 rounded-lg bg-brand-500/15 flex items-center justify-center flex-shrink-0">
                  <i data-lucide="send" class="w-4 h-4 text-brand-500"></i>
                </div>
                <div>
                  <div class="font-display font-bold text-sm text-[var(--text-primary)]">Kontributor</div>
                  <span class="badge badge-green text-[9px]">Lapangan</span>
                </div>
              </div>
              <p class="text-[11px] text-[var(--text-muted)] leading-relaxed">
                + Kirim laporan harga komoditas dari lapangan. Data Anda membantu transparansi harga nasional.
              </p>
            </label>
          </div>
        </div>

        <!-- Divider -->
        <div class="flex items-center gap-3 py-1">
          <div class="flex-1 h-px bg-[var(--border)]"></div>
          <span class="text-[10px] text-[var(--text-muted)] uppercase tracking-wider font-bold">Data Akun</span>
          <div class="flex-1 h-px bg-[var(--border)]"></div>
        </div>

        <!-- Nama lengkap -->
        <div>
          <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">Nama Lengkap</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-3.5 flex items-center text-[var(--text-muted)] pointer-events-none">
              <i data-lucide="user-check" class="w-4 h-4"></i>
            </span>
            <input type="text" name="nama_lengkap" class="input-field input-icon"
                   placeholder="Nama lengkap Anda" maxlength="120"/>
          </div>
        </div>

        <!-- Email + Username (2 kolom) -->
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">Email <span class="text-red-400">*</span></label>
            <div class="relative">
              <span class="absolute inset-y-0 left-3.5 flex items-center text-[var(--text-muted)] pointer-events-none">
                <i data-lucide="mail" class="w-4 h-4"></i>
              </span>
              <input type="email" name="email" class="input-field input-icon"
                     placeholder="nama@email.com" autocomplete="email" required maxlength="120"/>
            </div>
          </div>
          <div>
            <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">Username <span class="text-red-400">*</span></label>
            <div class="relative">
              <span class="absolute inset-y-0 left-3.5 flex items-center text-[var(--text-muted)] pointer-events-none">
                <i data-lucide="at-sign" class="w-4 h-4"></i>
              </span>
              <input type="text" name="username" class="input-field input-icon"
                     placeholder="Min. 4 karakter" required minlength="4" maxlength="60" autocomplete="username"/>
            </div>
          </div>
        </div>

        <!-- Tanggal lahir -->
        <div>
          <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">
            Tanggal Lahir <span class="text-[var(--text-muted)] font-normal normal-case">(opsional)</span>
          </label>
          <div class="relative">
            <span class="absolute inset-y-0 left-3.5 flex items-center text-[var(--text-muted)] pointer-events-none">
              <i data-lucide="calendar" class="w-4 h-4"></i>
            </span>
            <input type="date" name="tgl_lahir" class="input-field input-icon" style="color-scheme:dark light"/>
          </div>
        </div>

        <!-- Divider lokasi -->
        <div class="flex items-center gap-3 py-1">
          <div class="flex-1 h-px bg-[var(--border)]"></div>
          <span class="text-[10px] text-[var(--text-muted)] uppercase tracking-wider font-bold">Lokasi Asal</span>
          <div class="flex-1 h-px bg-[var(--border)]"></div>
        </div>

        <!-- Provinsi (dari BPS API mapping) -->
        <div>
          <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">
            Provinsi
            <span class="font-normal normal-case text-[var(--text-muted)] ml-1">— data dari BPS</span>
          </label>
          <div class="relative">
            <span class="absolute inset-y-0 left-3.5 flex items-center text-[var(--text-muted)] pointer-events-none">
              <i data-lucide="map" class="w-4 h-4"></i>
            </span>
            <select id="selProvinsi" name="provinsi" class="input-field input-icon">
              <option value="">— Pilih Provinsi —</option>
              <?php foreach (PROVINSI_LIST as $prov): ?>
              <option value="<?= htmlspecialchars($prov) ?>"><?= htmlspecialchars($prov) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Kota (dinamis berdasarkan provinsi → dari PROVINSI_KOTA) -->
        <div>
          <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">
            Kota / Kabupaten
          </label>
          <div class="relative">
            <span class="absolute inset-y-0 left-3.5 flex items-center text-[var(--text-muted)] pointer-events-none">
              <i data-lucide="map-pin" class="w-4 h-4"></i>
            </span>
            <select id="selKota" name="kota" class="input-field input-icon" disabled>
              <option value="">— Pilih Provinsi dulu —</option>
            </select>
          </div>
          <p class="text-[10px] text-[var(--text-muted)] mt-1 ml-0.5">Pilih provinsi terlebih dahulu untuk memuat daftar kota.</p>
        </div>

        <!-- Telepon -->
        <div>
          <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">
            No. Telepon <span class="text-[var(--text-muted)] font-normal normal-case">(opsional)</span>
          </label>
          <div class="relative">
            <span class="absolute inset-y-0 left-3.5 flex items-center text-[var(--text-muted)] pointer-events-none">
              <i data-lucide="phone" class="w-4 h-4"></i>
            </span>
            <input type="tel" name="telepon" class="input-field input-icon"
                   placeholder="08xx-xxxx-xxxx" maxlength="20"/>
          </div>
        </div>

        <!-- Password -->
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">Password <span class="text-red-400">*</span></label>
            <div class="relative">
              <span class="absolute inset-y-0 left-3.5 flex items-center text-[var(--text-muted)] pointer-events-none">
                <i data-lucide="lock" class="w-4 h-4"></i>
              </span>
              <input id="pw1" type="password" name="password" class="input-field input-icon pr-10"
                     placeholder="Min. 6 karakter" required minlength="6" autocomplete="new-password"/>
              <button type="button" id="tgl1" onclick="togglePassword('pw1','tgl1')"
                      class="absolute inset-y-0 right-3 flex items-center text-[var(--text-muted)] hover:text-[var(--text-primary)] transition">
                <i data-lucide="eye" class="w-3.5 h-3.5"></i>
              </button>
            </div>
          </div>
          <div>
            <label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">Konfirmasi <span class="text-red-400">*</span></label>
            <div class="relative">
              <span class="absolute inset-y-0 left-3.5 flex items-center text-[var(--text-muted)] pointer-events-none">
                <i data-lucide="lock" class="w-4 h-4"></i>
              </span>
              <input id="pw2" type="password" name="konfirmasi" class="input-field input-icon pr-10"
                     placeholder="Ulangi password" required autocomplete="new-password"/>
              <button type="button" id="tgl2" onclick="togglePassword('pw2','tgl2')"
                      class="absolute inset-y-0 right-3 flex items-center text-[var(--text-muted)] hover:text-[var(--text-primary)] transition">
                <i data-lucide="eye" class="w-3.5 h-3.5"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Syarat & ketentuan -->
        <div class="flex items-start gap-2.5 p-3 rounded-xl bg-[var(--surface)] border border-[var(--border)]">
          <input type="checkbox" id="agree" name="agree" required class="mt-0.5 flex-shrink-0 w-4 h-4 accent-brand-500"/>
          <label for="agree" class="text-xs text-[var(--text-muted)] leading-relaxed cursor-pointer">
            Saya menyetujui bahwa data yang saya masukkan adalah benar dan akan digunakan sesuai kebijakan platform InfoHarga Komoditi.
          </label>
        </div>

        <button type="submit" name="register"
                class="w-full py-3 bg-brand-600 hover:bg-brand-500 text-white font-display font-bold rounded-xl text-sm transition shadow-lg shadow-brand-600/20 hover:-translate-y-0.5 flex items-center justify-center gap-2">
          <i data-lucide="user-plus" class="w-4 h-4"></i> Daftar &amp; Masuk
        </button>
      </form>

      <div class="flex items-center gap-3 my-5">
        <div class="flex-1 h-px bg-[var(--border)]"></div>
        <span class="text-[var(--text-muted)] text-xs">atau</span>
        <div class="flex-1 h-px bg-[var(--border)]"></div>
      </div>
      <p class="text-center text-sm text-[var(--text-muted)]">
        Sudah punya akun?
        <a href="login.php" class="text-brand-500 font-bold hover:text-brand-400 transition">Login di sini</a>
      </p>
    </div>

    <p class="text-center text-xs text-[var(--text-muted)] mt-5">&copy; <?= date('Y') ?> InfoHarga Komoditi</p>
  </div>

<!-- Inject PROVINSI_KOTA mapping dari PHP ke JavaScript -->
<script>
window.PROVINSI_KOTA_JS = <?= json_encode(PROVINSI_KOTA, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="Assets/scripts.js"></script>
<script>
lucide.createIcons();

// ── ROLE SELECTION ────────────────────────────────────────────
function selectRole(role) {
  const cardUser    = document.getElementById('cardUser');
  const cardKontrib = document.getElementById('cardKontributor');
  const radioUser   = document.getElementById('roleUser');
  const radioKontrib= document.getElementById('roleKontributor');

  if (role === 'user') {
    radioUser.checked   = true;
    cardUser.className   = 'role-card selected-user';
    cardKontrib.className= 'role-card';
  } else {
    radioKontrib.checked = true;
    cardKontrib.className= 'role-card selected-kontributor';
    cardUser.className   = 'role-card';
  }
}

// ── PROVINCE → CITY DROPDOWN ─────────────────────────────────
(function() {
  const selProv = document.getElementById('selProvinsi');
  const selKota = document.getElementById('selKota');

  selProv.addEventListener('change', function() {
    const prov   = this.value;
    const cities = (window.PROVINSI_KOTA_JS || {})[prov] || [];

    // Reset dropdown kota
    selKota.innerHTML = '';
    selKota.disabled  = (cities.length === 0);

    if (cities.length === 0) {
      selKota.innerHTML = '<option value="">— Pilih Provinsi dulu —</option>';
      return;
    }

    // Tambahkan placeholder
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = '— Pilih Kota/Kabupaten —';
    selKota.appendChild(placeholder);

    // Populate kota
    cities.forEach(function(kota) {
      const opt = document.createElement('option');
      opt.value = kota;
      opt.textContent = kota;
      selKota.appendChild(opt);
    });

    selKota.disabled = false;
  });
})();
</script>
</body>
</html>
