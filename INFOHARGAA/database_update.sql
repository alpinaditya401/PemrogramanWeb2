-- ================================================================
--  InfoHarga Komoditi — database_update.sql v4.1
--  MIGRASI: Tambahkan kolom & tabel baru ke database yang sudah ada
--
--  Cara pakai:
--  phpMyAdmin → pilih database infoharga_db → tab SQL → paste → Go
--  Aman dijalankan berkali-kali (IF NOT EXISTS / IF EXISTS)
-- ================================================================

USE infoharga_db;

-- ── 1. TAMBAH KOLOM BARU ke tabel users ──────────────────────
-- Kolom untuk profil, keamanan login, dan lokasi

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS foto_profil     VARCHAR(255) DEFAULT NULL
    COMMENT 'Nama file foto profil di folder uploads/foto/',
  ADD COLUMN IF NOT EXISTS bio             TEXT         DEFAULT NULL
    COMMENT 'Deskripsi singkat tentang user',
  ADD COLUMN IF NOT EXISTS provinsi        VARCHAR(100) DEFAULT NULL
    COMMENT 'Provinsi asal user (dari PROVINSI_LIST)',
  ADD COLUMN IF NOT EXISTS kota            VARCHAR(120) DEFAULT NULL
    COMMENT 'Kota/Kabupaten asal user',
  ADD COLUMN IF NOT EXISTS login_attempts  TINYINT UNSIGNED NOT NULL DEFAULT 0
    COMMENT 'Hitungan gagal login berturut-turut',
  ADD COLUMN IF NOT EXISTS locked_until    DATETIME     DEFAULT NULL
    COMMENT 'Akun terkunci sampai waktu ini (NULL = tidak terkunci)';

-- ── 2. TAMBAH KOLOM ke tabel komoditas ───────────────────────
-- Kolom bps_id untuk mapping ke API BPS

ALTER TABLE komoditas
  ADD COLUMN IF NOT EXISTS bps_id          VARCHAR(20)  DEFAULT NULL
    COMMENT 'ID variabel di API BPS untuk mapping data'
    AFTER nama;

-- ── 3. BUAT TABEL activity_log ───────────────────────────────
-- Mencatat semua aktivitas penting: login, ubah data, hapus, dll.

CREATE TABLE IF NOT EXISTS activity_log (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED     DEFAULT NULL
    COMMENT 'User yang melakukan aksi (NULL = system)',
  username     VARCHAR(60)      DEFAULT NULL
    COMMENT 'Cache username saat aksi terjadi',
  aksi         VARCHAR(100)     NOT NULL
    COMMENT 'Jenis aksi: login, logout, tambah_komoditas, hapus_user, dll',
  target_tabel VARCHAR(60)      DEFAULT NULL
    COMMENT 'Nama tabel yang terpengaruh',
  target_id    INT UNSIGNED     DEFAULT NULL
    COMMENT 'ID record yang terpengaruh',
  deskripsi    VARCHAR(500)     DEFAULT NULL
    COMMENT 'Keterangan detail aksi',
  ip_address   VARCHAR(45)      DEFAULT NULL
    COMMENT 'IP address pelaku (support IPv6)',
  created_at   TIMESTAMP        DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user   (user_id),
  INDEX idx_aksi   (aksi),
  INDEX idx_created(created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Log semua aktivitas penting di sistem';

-- ── 4. BUAT TABEL user_favorites ────────────────────────────
-- Komoditas yang di-bookmark user untuk akses cepat

CREATE TABLE IF NOT EXISTS user_favorites (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED     NOT NULL,
  komoditas_nama VARCHAR(120)   NOT NULL
    COMMENT 'Nama komoditas yang difavoritkan',
  created_at   TIMESTAMP        DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY   uq_fav (user_id, komoditas_nama),
  FOREIGN KEY  (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Bookmark komoditas milik setiap user';

-- ── 5. BUAT FOLDER uploads jika belum ada ───────────────────
-- (Folder fisik harus dibuat manual: htdocs/InfoHargaa/uploads/foto/)

-- ── SELESAI ─────────────────────────────────────────────────
-- Verifikasi: jalankan SELECT untuk memastikan kolom sudah ada
-- SELECT COLUMN_NAME FROM information_schema.COLUMNS
-- WHERE TABLE_SCHEMA='infoharga_db' AND TABLE_NAME='users';
