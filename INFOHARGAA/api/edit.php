<?php
session_start();
require 'Server/koneksi.php';
cekLogin(); cekRole(['admin','admin_master']);

if (!isset($_GET['id'])) redirect('dashboard.php');
$id = (int)$_GET['id'];
$q  = $conn->query("SELECT * FROM komoditas WHERE id=$id LIMIT 1");
$row = $q?$q->fetch_assoc():null;
if (!$row) { echo "<script>alert('Data tidak ditemukan!');window.location='dashboard.php';</script>"; exit(); }

if (isset($_POST['update_data'])) {
    $nama     = esc($conn,$_POST['nama']     ?? '');
    $kategori = esc($conn,$_POST['kategori'] ?? 'Lainnya');
    $lokasi   = esc($conn,$_POST['lokasi']   ?? '');
    $provinsi = esc($conn,$_POST['provinsi'] ?? '');
    $satuan   = esc($conn,$_POST['satuan']   ?? 'kg');
    $kemarin  = max(0,(int)$_POST['kemarin']);
    $sekarang = max(0,(int)$_POST['sekarang']);
    $hist     = json_decode($row['history']??'[]',true);
    $hist[]   = $sekarang; if(count($hist)>7)array_shift($hist);
    $hj       = esc($conn,json_encode($hist));
    $conn->query("UPDATE komoditas SET nama='$nama',kategori='$kategori',lokasi='$lokasi',provinsi='$provinsi',satuan='$satuan',harga_kemarin=$kemarin,harga_sekarang=$sekarang,history='$hj' WHERE id=$id");
    redirect('dashboard.php?tab=data&success=updated');
}

$pageTitle = 'Edit Data Komoditas';
$kategoris = ['Beras & Serealia','Hortikultura','Bumbu & Rempah','Peternakan','Minyak & Lemak','Perikanan','Lainnya'];
$satuans   = ['kg','gram','liter','ml','butir','ikat','buah'];
?>
<!doctype html>
<html lang="id">
<head><?php include 'Assets/head.php'; ?></head>
<body class="min-h-screen flex items-center justify-center p-4" style="background:radial-gradient(ellipse 70% 60% at 30% 40%,rgba(16,185,129,.06) 0%,transparent 55%)">
  <a href="dashboard.php" class="fixed top-5 left-5 flex items-center gap-1.5 text-sm text-[var(--text-muted)] hover:text-[var(--text-primary)] transition group"><i data-lucide="arrow-left" class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform"></i> Dashboard</a>
  <button data-action="toggle-theme" class="fixed top-5 right-5 w-9 h-9 flex items-center justify-center rounded-lg bg-[var(--surface)] hover:bg-[var(--surface-hover)] border border-[var(--border)] text-[var(--text-muted)] hover:text-[var(--text-primary)] transition"><i data-lucide="moon" data-theme-icon="toggle" class="w-4 h-4"></i></button>
  <div class="w-full max-w-lg animate-fade-up">
    <div class="card overflow-hidden shadow-2xl">
      <div class="px-6 py-4 border-b border-[var(--border)] bg-[var(--bg-secondary)] flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg bg-blue-500/15 flex items-center justify-center"><i data-lucide="pencil" class="w-4 h-4 text-blue-400"></i></div>
        <div><h1 class="font-display font-bold text-[var(--text-primary)]">Edit Data Komoditas</h1><p class="text-[10px] text-[var(--text-muted)]">ID #<?= $id ?> · <?= htmlspecialchars($row['nama']) ?></p></div>
      </div>
      <form action="edit.php?id=<?= $id ?>" method="POST" class="px-6 py-5 space-y-4">
        <div><label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">Nama Komoditas</label><input type="text" name="nama" class="input-field" value="<?= htmlspecialchars($row['nama']) ?>" required/></div>
        <div class="grid grid-cols-2 gap-4">
          <div><label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">Kategori</label><select name="kategori" class="input-field"><?php foreach($kategoris as $k): ?><option <?= $row['kategori']===$k?'selected':'' ?>><?= htmlspecialchars($k) ?></option><?php endforeach; ?></select></div>
          <div><label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">Satuan</label><select name="satuan" class="input-field"><?php foreach($satuans as $s): ?><option <?= $row['satuan']===$s?'selected':'' ?>><?= htmlspecialchars($s) ?></option><?php endforeach; ?></select></div>
          <div><label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">Kota/Lokasi</label><input type="text" name="lokasi" class="input-field" value="<?= htmlspecialchars($row['lokasi']) ?>" required/></div>
          <div><label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">Provinsi</label><select name="provinsi" class="input-field"><option value="">— Pilih —</option><?php foreach(PROVINSI_LIST as $p): ?><option <?= $row['provinsi']===$p?'selected':'' ?>><?= htmlspecialchars($p) ?></option><?php endforeach; ?></select></div>
          <div><label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">Harga Kemarin (Rp)</label><input type="number" name="kemarin" class="input-field" value="<?= (int)$row['harga_kemarin'] ?>" required min="0"/></div>
          <div><label class="block text-xs font-bold text-[var(--text-secondary)] uppercase tracking-wider mb-1.5">Harga Sekarang (Rp)</label><input type="number" name="sekarang" class="input-field" value="<?= (int)$row['harga_sekarang'] ?>" required min="0"/></div>
        </div>
        <div class="flex items-start gap-2 p-3 rounded-xl bg-brand-500/6 border border-brand-500/15 text-xs text-[var(--text-secondary)]"><i data-lucide="info" class="w-3.5 h-3.5 text-brand-500 flex-shrink-0 mt-0.5"></i>Harga Sekarang otomatis ditambahkan ke histori grafik 7 hari.</div>
        <div class="flex gap-3 pt-2">
          <a href="dashboard.php" class="flex-1 py-2.5 rounded-xl text-sm font-semibold text-center text-[var(--text-secondary)] bg-[var(--surface)] hover:bg-[var(--surface-hover)] transition">Batal</a>
          <button type="submit" name="update_data" class="flex-1 py-2.5 rounded-xl text-sm font-display font-bold text-white bg-brand-600 hover:bg-brand-500 transition shadow shadow-brand-600/20 hover:-translate-y-0.5">Simpan Perubahan</button>
        </div>
      </form>
    </div>
  </div>
<script src="Assets/scripts.js"></script><script>lucide.createIcons();</script>
</body>
</html>
