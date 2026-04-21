-- ================================================================
--  InfoHarga Komoditi — database.sql v4.0
--  SKEMA LENGKAP: users, komoditas, artikel, pengumuman, info_kontak
--  Cara pakai: phpMyAdmin > tab SQL > paste semua > klik Go
-- ================================================================

CREATE DATABASE IF NOT EXISTS infoharga_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE infoharga_db;

-- ================================================================
-- TABEL 1: users
-- Menyimpan semua pengguna sistem dengan 4 role berbeda:
--   admin_master → akses penuh, kelola role & pengaturan sistem
--   admin        → kelola komoditas, artikel, verifikasi laporan
--   kontributor  → kirim laporan harga
--   user         → pengguna terdaftar, akses dashboard user
-- ================================================================
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(120) NOT NULL UNIQUE,
  username      VARCHAR(60)  NOT NULL UNIQUE,
  password      VARCHAR(255) NOT NULL,
  nama_lengkap  VARCHAR(120) DEFAULT NULL,
  tgl_lahir     DATE         DEFAULT NULL,
  telepon       VARCHAR(20)  DEFAULT NULL,
  role          ENUM('admin_master','admin','kontributor','user')
                NOT NULL DEFAULT 'user',
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  last_login    DATETIME     DEFAULT NULL,
  created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================================
-- TABEL 2: komoditas
-- Menyimpan data harga komoditas yang dilaporkan kontributor.
-- Kolom 'history' berisi JSON array 7 harga terakhir untuk grafik.
-- Kolom 'status': pending (belum diverifikasi), approved (tampil publik),
--                 rejected (ditolak admin)
-- ================================================================
CREATE TABLE IF NOT EXISTS komoditas (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama            VARCHAR(120) NOT NULL,
  kategori        ENUM(
                    'Beras & Serealia','Hortikultura','Bumbu & Rempah',
                    'Peternakan','Minyak & Lemak','Perikanan','Lainnya'
                  ) NOT NULL DEFAULT 'Lainnya',
  lokasi          VARCHAR(120) NOT NULL,
  provinsi        VARCHAR(100) NOT NULL DEFAULT '',
  satuan          VARCHAR(30)  NOT NULL DEFAULT 'kg',
  harga_kemarin   BIGINT UNSIGNED NOT NULL DEFAULT 0,
  harga_sekarang  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  history         JSON         DEFAULT NULL,
  status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  submitted_by    INT UNSIGNED DEFAULT NULL,
  catatan_admin   VARCHAR(255) DEFAULT NULL,
  created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_status   (status),
  INDEX idx_nama     (nama),
  INDEX idx_provinsi (provinsi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================================
-- TABEL 3: artikel
-- Menyimpan artikel edukasi/berita yang dibuat atau di-fetch admin.
-- Kolom 'sumber_url': jika artikel diambil dari link luar, URL-nya disimpan di sini
-- Kolom 'is_publish': 1 = tampil, 0 = draft
-- ================================================================
CREATE TABLE IF NOT EXISTS artikel (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  judul       VARCHAR(255) NOT NULL,
  slug        VARCHAR(255) NOT NULL UNIQUE,
  ringkasan   TEXT         DEFAULT NULL,
  konten      LONGTEXT     DEFAULT NULL,
  kategori    VARCHAR(60)  NOT NULL DEFAULT 'Umum',
  emoji       VARCHAR(10)  DEFAULT '📰',
  menit_baca  TINYINT UNSIGNED DEFAULT 5,
  sumber_url  VARCHAR(500) DEFAULT NULL,   -- URL sumber jika di-fetch dari luar
  sumber_nama VARCHAR(120) DEFAULT NULL,   -- Nama media sumber
  penulis_id  INT UNSIGNED DEFAULT NULL,
  is_publish  TINYINT(1)   NOT NULL DEFAULT 1,
  views       INT UNSIGNED NOT NULL DEFAULT 0,
  created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (penulis_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_publish (is_publish),
  FULLTEXT idx_search (judul, ringkasan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================================
-- TABEL 4: pengumuman
-- Dikelola oleh Admin Master. Isinya bisa berupa:
--   - info        : informasi umum biasa
--   - peringatan  : alert penting berwarna kuning
--   - darurat     : alert merah mendesak
-- Tampil di dashboard user jika is_active = 1
-- ================================================================
CREATE TABLE IF NOT EXISTS pengumuman (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  judul       VARCHAR(255) NOT NULL,
  isi         TEXT         NOT NULL,
  tipe        ENUM('info','peringatan','darurat') NOT NULL DEFAULT 'info',
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  dibuat_oleh INT UNSIGNED DEFAULT NULL,
  berlaku_hingga DATE       DEFAULT NULL,   -- NULL = tidak ada batas waktu
  created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (dibuat_oleh) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================================
-- TABEL 5: pengaturan_sistem
-- Menyimpan konfigurasi dinamis yang bisa diubah Admin Master:
-- Key-value store. Contoh:
--   'sms_gateway'    → URL gateway SMS
--   'email_smtp'     → konfigurasi SMTP
--   'info_kontak'    → nomor telepon / email layanan
--   'nama_situs'     → nama tampilan website
-- ================================================================
CREATE TABLE IF NOT EXISTS pengaturan_sistem (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kunci       VARCHAR(100) NOT NULL UNIQUE,  -- nama setting, mis: 'sms_gateway'
  nilai       TEXT         DEFAULT NULL,     -- nilai setting
  label       VARCHAR(150) NOT NULL,         -- label ramah untuk ditampilkan di UI
  kelompok    VARCHAR(60)  NOT NULL DEFAULT 'Umum',  -- grup: Umum, SMS, Email, dll
  tipe        ENUM('text','textarea','url','email','number','toggle')
              NOT NULL DEFAULT 'text',
  keterangan  VARCHAR(255) DEFAULT NULL,     -- penjelasan singkat untuk admin
  updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================================
-- DATA AWAL: USERS
-- password untuk semua akun = "password"
-- ================================================================

-- Admin Master: hak akses tertinggi
INSERT INTO users (email, username, password, nama_lengkap, role) VALUES
('master@infoharga.com', 'admin_master',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'Admin Master', 'admin_master');

-- Admin biasa: kelola data, verifikasi laporan, kelola artikel
INSERT INTO users (email, username, password, nama_lengkap, role) VALUES
('admin@infoharga.com', 'admin',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'Administrator', 'admin');

-- Kontributor: kirim laporan harga lapangan
INSERT INTO users (email, username, password, nama_lengkap, role) VALUES
('kontributor@infoharga.com', 'kontributor1',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'Kontributor Pertama', 'kontributor');

-- User biasa: akses dashboard user
INSERT INTO users (email, username, password, nama_lengkap, role) VALUES
('user@infoharga.com', 'user_demo',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'Demo User', 'user');

-- ================================================================
-- DATA AWAL: KOMODITAS
-- ================================================================
INSERT INTO komoditas (nama, kategori, lokasi, provinsi, satuan, harga_kemarin, harga_sekarang, history, status) VALUES
('Beras Premium',  'Beras & Serealia', 'Jakarta',    'DKI Jakarta',      'kg',    15000, 15500,  '[14200,14500,14300,14800,15200,15000,15500]', 'approved'),
('Beras Medium',   'Beras & Serealia', 'Bandung',    'Jawa Barat',       'kg',    14500, 14500,  '[14000,14100,14200,14300,14400,14500,14500]', 'approved'),
('Cabai Merah',    'Hortikultura',     'Surabaya',   'Jawa Timur',       'kg',    65000, 62000,  '[68000,67000,69000,68000,66000,65000,62000]', 'approved'),
('Bawang Merah',   'Bumbu & Rempah',   'Brebes',     'Jawa Tengah',      'kg',    23000, 24500,  '[20000,21000,22000,22500,23000,23000,24500]', 'approved'),
('Bawang Putih',   'Bumbu & Rempah',   'Medan',      'Sumatera Utara',   'kg',    35000, 33000,  '[38000,37000,36500,36000,35500,35000,33000]', 'approved'),
('Minyak Goreng',  'Minyak & Lemak',   'Makassar',   'Sulawesi Selatan', 'liter', 18000, 17500,  '[19000,18500,18000,18200,18000,18000,17500]', 'approved'),
('Gula Pasir',     'Lainnya',          'Yogyakarta', 'DI Yogyakarta',    'kg',    16000, 16000,  '[15500,15500,15800,16000,16000,16000,16000]', 'approved'),
('Daging Sapi',    'Peternakan',       'Surabaya',   'Jawa Timur',       'kg',   130000,135000, '[125000,127000,128000,130000,130000,130000,135000]','approved'),
('Telur Ayam',     'Peternakan',       'Jakarta',    'DKI Jakarta',      'butir',  2000,  2200,  '[1800,1900,1950,2000,2000,2000,2200]', 'approved'),
('Ikan Bandeng',   'Perikanan',        'Semarang',   'Jawa Tengah',      'kg',    32000, 30000,  '[35000,34000,33000,33000,32000,32000,30000]', 'approved');

-- ================================================================
-- DATA AWAL: ARTIKEL
-- ================================================================
INSERT INTO artikel (judul, slug, ringkasan, kategori, emoji, menit_baca, is_publish) VALUES
('Mengenal Jenis Beras dan Pengaruhnya terhadap Harga Pasar',
 'mengenal-jenis-beras-harga-pasar',
 'Beras premium, medium, dan IR64 memiliki karakteristik berbeda yang memengaruhi harga jual di pasar.',
 'Beras & Serealia', '🌾', 5, 1),
('Mengapa Harga Cabai Sangat Fluktuatif?',
 'mengapa-harga-cabai-fluktuatif',
 'Cabai merah dan rawit dikenal sebagai komoditas dengan volatilitas harga tertinggi di Indonesia.',
 'Hortikultura', '🌶️', 4, 1),
('Bawang Merah & Putih: Komoditas Strategis',
 'bawang-merah-putih-komoditas-strategis',
 'Bawang merupakan indikator penting ketahanan pangan nasional.',
 'Bumbu & Rempah', '🧅', 6, 1),
('Dinamika Harga Daging Sapi di Pasar Tradisional',
 'dinamika-harga-daging-sapi',
 'Harga daging sapi dipengaruhi biaya pakan, rantai distribusi, dan kebijakan impor.',
 'Peternakan', '🥩', 5, 1),
('Harga Minyak Goreng vs Harga CPO Global',
 'harga-minyak-goreng-cpo-global',
 'Indonesia produsen CPO terbesar namun tetap rentan guncangan harga domestik.',
 'Minyak & Lemak', '🛢️', 7, 1),
('Komoditas Ikan Tangkap yang Sering Dipantau',
 'komoditas-ikan-tangkap-budidaya',
 'Ikan bandeng, lele, dan udang windu — komoditas yang dipantau pemerintah.',
 'Perikanan', '🐟', 5, 1),
('Rantai Distribusi Pangan dari Petani ke Konsumen',
 'rantai-distribusi-pangan',
 'Rantai distribusi panjang menjadi penyebab mahalnya harga pangan di tingkat konsumen.',
 'Umum', '🚜', 6, 1),
('Teknologi Digital dan Transparansi Harga Komoditas',
 'teknologi-digital-transparansi-harga',
 'Platform digital memungkinkan petani dan konsumen akses informasi harga secara real-time.',
 'Umum', '💻', 4, 1);

-- ================================================================
-- DATA AWAL: PENGUMUMAN
-- ================================================================
INSERT INTO pengumuman (judul, isi, tipe, is_active) VALUES
('Selamat Datang di InfoHarga Komoditi',
 'Platform ini menyediakan data harga komoditas pangan dari seluruh 38 provinsi Indonesia secara real-time. Data diperbarui setiap hari oleh kontributor lapangan terpercaya.',
 'info', 1),
('Cara Melaporkan Harga yang Akurat',
 'Pastikan data harga yang Anda laporkan sesuai dengan harga aktual di pasar setempat. Sertakan lokasi dan provinsi yang tepat agar data dapat diverifikasi.',
 'peringatan', 1);

-- ================================================================
-- DATA AWAL: PENGATURAN SISTEM
-- ================================================================
INSERT INTO pengaturan_sistem (kunci, nilai, label, kelompok, tipe, keterangan) VALUES
('nama_situs',      'InfoHarga Komoditi',        'Nama Website',            'Umum',  'text',     'Nama website yang tampil di navbar dan judul halaman'),
('tagline_situs',   'Transparansi Harga Pangan Indonesia', 'Tagline Website', 'Umum', 'text',    'Deskripsi singkat website'),
('email_admin',     'admin@infoharga.com',        'Email Admin',             'Kontak','email',    'Email utama admin untuk kontak'),
('telepon_admin',   '0800-1234-5678',             'Telepon Layanan',         'Kontak','text',     'Nomor telepon layanan pelanggan'),
('whatsapp_admin',  '6281234567890',              'WhatsApp Admin',          'Kontak','text',     'Nomor WhatsApp untuk pengaduan (format: 628xxx)'),
('sms_info',        'Kirim SMS: HARGA [KOMODITAS] ke 1234 untuk info harga terkini', 'Info SMS', 'SMS', 'textarea', 'Petunjuk layanan info via SMS'),
('smtp_host',       'smtp.gmail.com',             'SMTP Host',               'Email', 'text',     'Server SMTP untuk kirim email'),
('smtp_port',       '587',                        'SMTP Port',               'Email', 'number',   'Port SMTP (biasanya 587 atau 465)'),
('smtp_user',       '',                           'SMTP Username/Email',     'Email', 'email',    'Username atau email akun SMTP'),
('smtp_pass',       '',                           'SMTP Password',           'Email', 'text',     'Password atau app password akun SMTP'),
('maintenance_mode','0',                          'Mode Maintenance',        'Umum',  'toggle',   'Aktifkan untuk menutup akses publik sementara');

-- ================================================================
--  RINGKASAN AKUN:
--  Admin Master → username: admin_master  | password: password
--  Admin        → username: admin         | password: password
--  Kontributor  → username: kontributor1  | password: password
--  User         → username: user_demo     | password: password
-- ================================================================

-- BPS API Key (ditambahkan setelah setup awal)
INSERT IGNORE INTO pengaturan_sistem (kunci, nilai, label, kelompok, tipe, keterangan) VALUES
('bps_api_key', '', 'BPS API Key', 'API', 'text', 'Dapatkan gratis di https://webapi.bps.go.id/developer/');
