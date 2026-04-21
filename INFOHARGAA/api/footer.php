<?php
/**
 * Assets/footer.php — Footer publik
 */
?>
<footer class="border-t border-[var(--border)] bg-[var(--bg-secondary)] mt-16">
  <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-10 mb-10">
      <div>
        <div class="flex items-center gap-2 mb-3">
          <div class="w-7 h-7 bg-brand-500 rounded-lg flex items-center justify-center"><i data-lucide="trending-up" class="w-3.5 h-3.5 text-white"></i></div>
          <span class="font-display font-black text-[var(--text-primary)]">InfoHarga<span class="text-brand-500">Komoditi</span></span>
        </div>
        <p class="text-sm text-[var(--text-muted)] leading-relaxed max-w-xs">Platform transparansi harga komoditas pangan Indonesia. Data real-time dari kontributor lapangan di 38 provinsi.</p>
        <div class="flex items-center gap-1.5 mt-4">
          <span class="w-1.5 h-1.5 bg-brand-500 rounded-full" style="animation:pulseDot 2s ease-in-out infinite"></span>
          <span class="text-xs text-brand-500 font-semibold">Data diperbarui setiap hari</span>
        </div>
      </div>
      <div>
        <h4 class="font-display font-bold text-[var(--text-primary)] text-sm mb-4 uppercase tracking-wider">Navigasi</h4>
        <ul class="space-y-2.5">
          <li><a href="index.php"         class="text-sm text-[var(--text-muted)] hover:text-brand-500 transition">Beranda</a></li>
          <li><a href="chart.php"         class="text-sm text-[var(--text-muted)] hover:text-brand-500 transition">Grafik Harga</a></li>
          <li><a href="index.php#artikel" class="text-sm text-[var(--text-muted)] hover:text-brand-500 transition">Artikel Edukasi</a></li>
          <li><a href="login.php"         class="text-sm text-[var(--text-muted)] hover:text-brand-500 transition">Login</a></li>
        </ul>
      </div>
      <div>
        <h4 class="font-display font-bold text-[var(--text-primary)] text-sm mb-4 uppercase tracking-wider">Kontribusi</h4>
        <ul class="space-y-2.5">
          <li><a href="register.php" class="text-sm text-[var(--text-muted)] hover:text-brand-500 transition">Daftar sebagai Kontributor</a></li>
          <li><a href="login.php"    class="text-sm text-[var(--text-muted)] hover:text-brand-500 transition">Login Admin</a></li>
        </ul>
        <div class="mt-5 p-3.5 rounded-xl bg-brand-500/8 border border-brand-500/15">
          <p class="text-xs text-[var(--text-secondary)] leading-relaxed">
            <strong class="text-brand-500">Jadilah kontributor.</strong><br/>
            Bantu sediakan data harga yang akurat untuk seluruh Indonesia.
          </p>
        </div>
      </div>
    </div>
    <div class="pt-6 border-t border-[var(--border)] flex flex-col sm:flex-row justify-between items-center gap-3">
      <p class="text-xs text-[var(--text-muted)]">&copy; <?= date('Y') ?> InfoHarga Komoditi. Seluruh hak cipta dilindungi.</p>
      <p class="text-xs text-[var(--text-muted)]">Referensi: <a href="https://infoharga.bappebti.go.id" target="_blank" rel="noopener" class="text-brand-500 hover:underline">Bappebti</a></p>
    </div>
  </div>
</footer>
