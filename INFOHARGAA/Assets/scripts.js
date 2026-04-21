/**
 * Assets/scripts.js — InfoHarga Komoditi v4.0
 * ─────────────────────────────────────────────────────────────
 * File JavaScript bersama yang di-include di hampir semua halaman.
 *
 * BERISI:
 * 1. Theme Manager   — kelola dark/light mode, simpan ke localStorage
 * 2. Error Messages  — map kode error ke pesan ramah pengguna Indonesia
 * 3. readUrlMessages — baca ?error= atau ?success= dari URL dan tampilkan
 * 4. togglePassword  — tombol tampilkan/sembunyikan password
 * 5. Modal helpers   — openModal / closeModal / toggleModal
 * 6. formatRp        — format angka ke Rupiah
 * 7. confirmDelete   — dialog konfirmasi hapus
 * 8. getChartTheme   — ambil warna chart sesuai tema aktif
 * 9. DOM Ready       — inisialisasi semua komponen
 * ─────────────────────────────────────────────────────────────
 */

"use strict";

/* ══════════════════════════════════════════════════════════════
   1. THEME MANAGER
   Mengelola pergantian tema light/dark.
   - init()    : dipanggil saat halaman load (ada juga di head.php)
   - toggle()  : ganti tema, simpan ke localStorage
   - current() : kembalikan tema aktif ('dark' atau 'light')
══════════════════════════════════════════════════════════════ */
const Theme = (() => {
  const HTML = document.documentElement;
  const KEY  = 'ih-theme';

  function _apply(mode) {
    if (mode === 'dark') {
      HTML.classList.add('dark');
      HTML.setAttribute('data-theme', 'dark');
    } else {
      HTML.classList.remove('dark');
      HTML.setAttribute('data-theme', 'light');
    }
    // Update semua ikon toggle tema di halaman ini
    document.querySelectorAll('[data-theme-icon="toggle"]').forEach(el => {
      el.setAttribute('data-lucide', mode === 'dark' ? 'sun' : 'moon');
      if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [el] });
    });
  }

  return {
    init() {
      const saved   = localStorage.getItem(KEY);
      const prefers = window.matchMedia('(prefers-color-scheme: dark)').matches;
      _apply(saved ?? (prefers ? 'dark' : 'light'));
    },
    toggle() {
      const next = HTML.classList.contains('dark') ? 'light' : 'dark';
      localStorage.setItem(KEY, next);
      _apply(next);
      // Kirim event agar komponen lain (mis: Chart.js) bisa merespons
      document.dispatchEvent(new CustomEvent('themeChanged', { detail: next }));
      return next;
    },
    current() {
      return HTML.classList.contains('dark') ? 'dark' : 'light';
    }
  };
})();

/* ══════════════════════════════════════════════════════════════
   2. PESAN ERROR — Map kode ke teks ramah pengguna
   Kode ini dikirim via URL ?error=xxx atau ?success=xxx
   dari file-file di folder Proses/
══════════════════════════════════════════════════════════════ */
const MSGS = {
  // Autentikasi
  gagal:           '❌ Username atau password salah. Silakan coba lagi.',
  logout:          '✅ Anda telah berhasil keluar.',
  nonaktif:        '⚠️ Akun Anda telah dinonaktifkan. Hubungi admin.',
  // Registrasi
  empty:           '⚠️ Semua field wajib diisi.',
  email_invalid:   '⚠️ Format email tidak valid.',
  username_short:  '⚠️ Username minimal 4 karakter.',
  password_short:  '⚠️ Password minimal 6 karakter.',
  mismatch:        '⚠️ Konfirmasi password tidak cocok.',
  email_taken:     '⚠️ Email ini sudah terdaftar.',
  username_taken:  '⚠️ Username sudah digunakan, pilih yang lain.',
  // Kontributor
  already_pending: '⚠️ Data komoditas ini sudah dalam antrian verifikasi.',
  submit_empty:    '⚠️ Lengkapi semua field harga dengan benar.',
  // Sukses
  submitted:       '✅ Laporan berhasil dikirim! Menunggu verifikasi admin.',
  saved:           '✅ Data berhasil disimpan.',
  deleted:         '✅ Data berhasil dihapus.',
  updated:         '✅ Perubahan berhasil disimpan.',
  role_updated:    '✅ Role pengguna berhasil diperbarui.',
  pengumuman_saved:'✅ Pengumuman berhasil disimpan.',
  setting_saved:   '✅ Pengaturan berhasil disimpan.',
  artikel_saved:   '✅ Artikel berhasil disimpan.',
};

/* ══════════════════════════════════════════════════════════════
   3. READ URL MESSAGES
   Baca parameter ?error= atau ?success= dari URL
   dan tampilkan di elemen #msg-box
══════════════════════════════════════════════════════════════ */
function readUrlMessages() {
  const p  = new URLSearchParams(location.search);
  const e  = p.get('error') || p.get('pesan');
  const s  = p.get('success');
  const el = document.getElementById('msg-box');
  if (!el) return;

  if (e && MSGS[e]) {
    el.textContent = MSGS[e];
    el.classList.remove('hidden', 'msg-success', 'msg-warning');
    el.classList.add(e === 'logout' ? 'msg-success' : 'msg-error');
  }
  if (s && MSGS[s]) {
    el.textContent = MSGS[s];
    el.classList.remove('hidden', 'msg-error', 'msg-warning');
    el.classList.add('msg-success');
  }
}

/* ══════════════════════════════════════════════════════════════
   4. PASSWORD TOGGLE
   Klik ikon mata → ubah input type password ↔ text
══════════════════════════════════════════════════════════════ */
function togglePassword(inputId, btnId) {
  const inp = document.getElementById(inputId);
  const btn = document.getElementById(btnId);
  if (!inp) return;
  const isHidden = inp.type === 'password';
  inp.type = isHidden ? 'text' : 'password';
  if (btn) {
    const icon = btn.querySelector('[data-lucide]');
    if (icon) {
      icon.setAttribute('data-lucide', isHidden ? 'eye-off' : 'eye');
      lucide.createIcons({ nodes: [icon] });
    }
  }
}

/* ══════════════════════════════════════════════════════════════
   5. MODAL HELPERS
══════════════════════════════════════════════════════════════ */
function openModal(id)   { document.getElementById(id)?.classList.remove('hidden'); }
function closeModal(id)  { document.getElementById(id)?.classList.add('hidden');    }
function toggleModal(id) { document.getElementById(id)?.classList.toggle('hidden'); }

/* ══════════════════════════════════════════════════════════════
   6. FORMAT RUPIAH
══════════════════════════════════════════════════════════════ */
function formatRp(n) {
  return new Intl.NumberFormat('id-ID', {
    style:'currency', currency:'IDR', minimumFractionDigits:0
  }).format(n);
}

/* ══════════════════════════════════════════════════════════════
   7. CONFIRM DELETE
══════════════════════════════════════════════════════════════ */
function confirmDelete(msg) {
  return confirm(msg || 'Hapus data ini?\n\nTindakan tidak dapat dibatalkan.');
}

/* ══════════════════════════════════════════════════════════════
   8. CHART THEME
   Kembalikan objek warna sesuai tema aktif,
   dipakai untuk styling Chart.js
══════════════════════════════════════════════════════════════ */
function getChartTheme() {
  const dark = Theme.current() === 'dark';
  return {
    textColor:  dark ? '#64748b' : '#94a3b8',
    gridColor:  dark ? 'rgba(255,255,255,.05)' : 'rgba(0,0,0,.06)',
    titleColor: dark ? '#f1f5f9' : '#0f172a',
    bgColor:    dark ? '#0f1318'  : '#ffffff',
  };
}

/* ══════════════════════════════════════════════════════════════
   9. DOM READY — inisialisasi semua komponen
══════════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
  // Inisialisasi Lucide Icons
  if (typeof lucide !== 'undefined') lucide.createIcons();

  // Baca pesan dari URL
  readUrlMessages();

  // Tombol toggle tema (semua elemen dengan data-action="toggle-theme")
  document.querySelectorAll('[data-action="toggle-theme"]').forEach(btn => {
    btn.addEventListener('click', () => Theme.toggle());
  });

  // Tutup modal saat klik backdrop (elemen dengan data-modal-close="modalId")
  document.querySelectorAll('[data-modal-close]').forEach(el => {
    el.addEventListener('click', () => closeModal(el.dataset.modalClose));
  });

  // Form dengan data-confirm → konfirmasi sebelum submit
  document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', e => {
      if (!confirmDelete(form.dataset.confirm)) e.preventDefault();
    });
  });

  // Mobile menu toggle
  document.getElementById('mobileMenuBtn')?.addEventListener('click', () => {
    document.getElementById('mobileMenu')?.classList.toggle('hidden');
  });
});
