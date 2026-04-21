<?php
/**
 * kelola-artikel.php — Manajemen Artikel Edukasi & Berita (CRUD & Scraping)
 */
session_start();
require 'Server/koneksi.php';

// 1. KEAMANAN: Hanya Admin dan Admin Master yang bisa mengakses halaman ini
if (!isset($_SESSION['login']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'admin_master')) {
    header("Location: login.php?pesan=akses_ditolak");
    exit;
}

$pesan = "";
$user_id = $_SESSION['user_id'];

// 2. LOGIKA HAPUS ARTIKEL
if (isset($_GET['hapus'])) {
    $id_hapus = (int) $_GET['hapus'];
    mysqli_query($conn, "DELETE FROM artikel WHERE id = $id_hapus");
    $pesan = "success|Artikel berhasil dihapus!";
}

// 3. LOGIKA TAMBAH MANUAL
if (isset($_POST['simpan_manual'])) {
    $judul = mysqli_real_escape_string($conn, $_POST['judul']);
    $konten = mysqli_real_escape_string($conn, $_POST['konten']);

    $query = "INSERT INTO artikel (judul, konten, tipe_sumber, dibuat_oleh) VALUES ('$judul', '$konten', 'internal', '$user_id')";
    if (mysqli_query($conn, $query)) {
        $pesan = "success|Artikel manual berhasil diterbitkan!";
    } else {
        $pesan = "error|Gagal menyimpan artikel: " . mysqli_error($conn);
    }
}

// 4. LOGIKA AMBIL DARI LINK (SCRAPING)
if (isset($_POST['fetch_artikel'])) {
    $url = filter_var($_POST['url_sumber'], FILTER_SANITIZE_URL);

    // Validasi format URL
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        // Menambahkan User-Agent agar tidak diblokir oleh sistem keamanan website target (anti-bot)
        $options = [
            "http" => [
                "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n"
            ]
        ];
        $context = stream_context_create($options);

        // Suppress warning (@) jika target web sedang down / menolak koneksi
        $html = @file_get_contents($url, false, $context);

        if ($html !== false) {
            // Mengambil teks di antara tag <title> menggunakan Regex
            preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches);

            // Bersihkan judul dari nama website asal (Opsional, memotong setelah tanda '-' atau '|')
            $judul_mentah = isset($matches[1]) ? trim($matches[1]) : 'Judul Tidak Ditemukan';
            $judul_bersih = mysqli_real_escape_string($conn, strip_tags($judul_mentah));

            $query = "INSERT INTO artikel (judul, sumber_link, tipe_sumber, dibuat_oleh) VALUES ('$judul_bersih', '$url', 'eksternal', '$user_id')";
            if (mysqli_query($conn, $query)) {
                $pesan = "success|Berhasil menarik artikel: $judul_bersih";
            } else {
                $pesan = "error|Gagal menyimpan ke database.";
            }
        } else {
            $pesan = "error|Gagal mengakses link target. Pastikan URL aktif dan bisa diakses publik.";
        }
    } else {
        $pesan = "error|Format URL tidak valid! Jangan lupa gunakan http:// atau https://";
    }
}

// 5. AMBIL DATA DAFTAR ARTIKEL UNTUK DITAMPILKAN DI TABEL
$result = mysqli_query($conn, "SELECT a.*, u.nama as penulis FROM artikel a LEFT JOIN users u ON a.dibuat_oleh = u.id ORDER BY a.id DESC");
?>


<!DOCTYPE html>
<html lang="id">

<head>
    <?php
    $pageTitle = "Kelola Artikel & Edukasi";
    include 'Assets/head.php';
    ?>
</head>

<body class="bg-[var(--bg-secondary)] text-[var(--text-primary)]">

    <?php include 'Assets/navbar.php'; ?>

    <div class="flex min-h-screen pt-24">
        <aside class="w-64 hidden lg:block border-r border-[var(--border)] p-6 space-y-2">
            <h3 class="text-xs font-bold text-[var(--text-muted)] uppercase tracking-widest mb-4">Menu Master</h3>
            <?php if ($_SESSION['role'] === 'admin_master'): ?>
                <a href="dashboard-master.php"
                    class="sidebar-nav flex items-center gap-3 p-3 rounded-xl hover:bg-[var(--surface)] transition">
                    <i data-lucide="shield-check" class="w-5 h-5"></i>
                    <span class="font-medium">Manajemen User</span>
                </a>
            <?php endif; ?>
            <a href="kelola-artikel.php"
                class="sidebar-nav active flex items-center gap-3 p-3 rounded-xl bg-brand-500/10 text-brand-500">
                <i data-lucide="file-text" class="w-5 h-5"></i>
                <span class="font-medium">Kelola Artikel</span>
            </a>
            <a href="pusat-informasi.php"
                class="sidebar-nav flex items-center gap-3 p-3 rounded-xl hover:bg-[var(--surface)] transition">
                <i data-lucide="bell" class="w-5 h-5"></i>
                <span class="font-medium">Pusat Informasi</span>
            </a>
        </aside>

        <main class="flex-1 p-6 lg:p-10">
            <div class="max-w-6xl mx-auto">

                <div class="mb-8">
                    <h1 class="text-3xl font-display font-bold">Pusat Artikel Edukasi</h1>
                    <p class="text-[var(--text-muted)]">Tulis artikel manual atau tarik berita terbaru dari link
                        eksternal.</p>
                </div>

                <?php if ($pesan):
                    $p = explode('|', $pesan);
                    $bgColor = $p[0] == 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                    ?>
                    <div class="<?= $bgColor ?> p-4 rounded-xl mb-6 flex items-center gap-3">
                        <i data-lucide="<?= $p[0] == 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-5 h-5"></i>
                        <p class="font-medium"><?= htmlspecialchars($p[1]) ?></p>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-10">

                    <div class="bg-[var(--bg-card)] border border-[var(--border)] rounded-2xl p-6 shadow-sm">
                        <div class="flex items-center gap-3 mb-4 text-brand-600">
                            <i data-lucide="link" class="w-6 h-6"></i>
                            <h2 class="font-bold text-lg text-[var(--text-primary)]">Tarik dari Link Luar</h2>
                        </div>
                        <p class="text-sm text-[var(--text-muted)] mb-5">Sistem akan otomatis membaca judul dari URL
                            yang Anda masukkan.</p>

                        <form action="" method="POST" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">URL Target (Website
                                    Berita/Edukasi)</label>
                                <input type="url" name="url_sumber"
                                    placeholder="https://kompas.com/berita-pertanian-..." required
                                    class="w-full bg-[var(--bg-primary)] border border-[var(--border)] rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-brand-500 outline-none">
                            </div>
                            <button type="submit" name="fetch_artikel"
                                class="w-full bg-gray-800 hover:bg-gray-700 text-white font-bold py-2.5 rounded-xl transition flex items-center justify-center gap-2">
                                <i data-lucide="download-cloud" class="w-4 h-4"></i> Ambil & Simpan
                            </button>
                        </form>
                    </div>

                    <div class="bg-[var(--bg-card)] border border-[var(--border)] rounded-2xl p-6 shadow-sm">
                        <div class="flex items-center gap-3 mb-4 text-brand-600">
                            <i data-lucide="edit-3" class="w-6 h-6"></i>
                            <h2 class="font-bold text-lg text-[var(--text-primary)]">Tulis Artikel Manual</h2>
                        </div>

                        <form action="" method="POST" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">Judul Artikel</label>
                                <input type="text" name="judul" required placeholder="Cth: Cara Mengatasi Hama Padi"
                                    class="w-full bg-[var(--bg-primary)] border border-[var(--border)] rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-brand-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">Konten (Teks)</label>
                                <textarea name="konten" rows="3" required placeholder="Tulis isi artikel di sini..."
                                    class="w-full bg-[var(--bg-primary)] border border-[var(--border)] rounded-xl px-4 py-2 focus:ring-2 focus:ring-brand-500 outline-none"></textarea>
                            </div>
                            <button type="submit" name="simpan_manual"
                                class="w-full bg-brand-600 hover:bg-brand-500 text-white font-bold py-2.5 rounded-xl transition flex items-center justify-center gap-2">
                                <i data-lucide="send" class="w-4 h-4"></i> Terbitkan Artikel
                            </button>
                        </form>
                    </div>

                </div>

                <div class="bg-[var(--bg-card)] border border-[var(--border)] rounded-2xl overflow-hidden shadow-sm">
                    <div class="p-5 border-b border-[var(--border)] bg-[var(--surface)]">
                        <h2 class="font-bold text-lg">Daftar Artikel Tersimpan</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead
                                class="bg-[var(--surface)] border-b border-[var(--border)] text-[var(--text-muted)] text-sm">
                                <tr>
                                    <th class="p-4 font-medium">Judul Artikel</th>
                                    <th class="p-4 font-medium">Tipe</th>
                                    <th class="p-4 font-medium">Penulis / Waktu</th>
                                    <th class="p-4 font-medium text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--border)] text-sm">
                                <?php if (mysqli_num_rows($result) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                        <tr class="hover:bg-[var(--surface)] transition">
                                            <td class="p-4">
                                                <p class="font-bold text-[var(--text-primary)] max-w-xs truncate"
                                                    title="<?= htmlspecialchars($row['judul']) ?>">
                                                    <?= htmlspecialchars($row['judul']) ?>
                                                </p>
                                                <?php if ($row['tipe_sumber'] == 'eksternal'): ?>
                                                    <a href="<?= $row['sumber_link'] ?>" target="_blank"
                                                        class="text-xs text-brand-500 hover:underline truncate inline-block max-w-[200px]">
                                                        <i data-lucide="external-link" class="w-3 h-3 inline"></i> Link Asli
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-4">
                                                <?php if ($row['tipe_sumber'] == 'eksternal'): ?>
                                                    <span
                                                        class="bg-indigo-100 text-indigo-700 px-2.5 py-1 rounded-full text-[10px] font-bold tracking-wide">EKSTERNAL</span>
                                                <?php else: ?>
                                                    <span
                                                        class="bg-emerald-100 text-emerald-700 px-2.5 py-1 rounded-full text-[10px] font-bold tracking-wide">INTERNAL</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-4">
                                                <div class="text-[var(--text-primary)]">
                                                    <?= htmlspecialchars($row['penulis'] ?? 'Sistem') ?>
                                                </div>
                                                <div class="text-xs text-[var(--text-muted)]">
                                                    <?= date('d M Y, H:i', strtotime($row['created_at'])) ?>
                                                </div>
                                            </td>
                                            <td class="p-4 text-center">
                                                <a href="?hapus=<?= $row['id'] ?>"
                                                    onclick="return confirm('Yakin ingin menghapus artikel ini?')"
                                                    class="inline-flex items-center justify-center bg-red-100 text-red-600 hover:bg-red-200 p-2 rounded-lg transition"
                                                    title="Hapus">
                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="p-8 text-center text-[var(--text-muted)]">Belum ada artikel
                                            yang ditambahkan.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script src="Assets/scripts.js"></script>
    <script>
        lucide.createIcons();
    </script>
</body>

</html>