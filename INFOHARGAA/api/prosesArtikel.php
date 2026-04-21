<?php
/**
 * Proses/prosesArtikel.php — CRUD Artikel oleh Admin/Admin Master
 * Mendukung: tambah, edit, hapus, toggle publish, fetch dari URL
 */
session_start();
require '../Server/koneksi.php';
if (!isset($_SESSION['login']) || !in_array($_SESSION['role'],['admin','admin_master'])) redirect('../login.php');

$aksi = $_POST['aksi'] ?? $_GET['aksi'] ?? '';
$uid  = (int)$_SESSION['user_id'];

// ── HAPUS ─────────────────────────────────────────────────────
if ($aksi === 'hapus' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $conn->query("DELETE FROM artikel WHERE id=$id");
    redirect('../dashboard.php?tab=artikel&success=deleted');
}

// ── TOGGLE PUBLISH ─────────────────────────────────────────────
if ($aksi === 'toggle' && isset($_GET['id'])) {
    $id  = (int)$_GET['id'];
    $res = $conn->query("SELECT is_publish FROM artikel WHERE id=$id LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        $baru = $row['is_publish'] ? 0 : 1;
        $conn->query("UPDATE artikel SET is_publish=$baru WHERE id=$id");
    }
    redirect('../dashboard.php?tab=artikel&success=updated');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../dashboard.php?tab=artikel');

// ── TAMBAH / EDIT ─────────────────────────────────────────────
$id        = (int)($_POST['id'] ?? 0);
$judul     = esc($conn, $_POST['judul']      ?? '');
$ringkasan = esc($conn, $_POST['ringkasan']  ?? '');
$konten    = esc($conn, $_POST['konten']     ?? '');
$kategori  = esc($conn, $_POST['kategori']   ?? 'Umum');
$emoji     = esc($conn, $_POST['emoji']      ?? '📰');
$menit     = max(1, (int)($_POST['menit_baca'] ?? 5));
$sumber    = esc($conn, $_POST['sumber_url'] ?? '');
$sumber_nm = esc($conn, $_POST['sumber_nama'] ?? '');
$publish   = isset($_POST['is_publish']) ? 1 : 0;

if (!$judul) redirect('../dashboard.php?tab=artikel&error=empty');

// Buat slug unik
$slug_base = slugify($judul);
$slug      = $slug_base;
$i = 1;
while (true) {
    $q = $conn->query("SELECT id FROM artikel WHERE slug='$slug'" . ($id ? " AND id!=$id" : "") . " LIMIT 1");
    if (!$q || $q->num_rows === 0) break;
    $slug = $slug_base . '-' . $i++;
}
$slug = esc($conn, $slug);

// ── FETCH DARI URL ─────────────────────────────────────────────
// Jika admin isi field sumber_url, coba ambil konten dari URL tersebut
// Menggunakan DOMDocument untuk parsing HTML sederhana
if ($sumber && empty($_POST['konten'])) {
    $ctx = stream_context_create(['http'=>['timeout'=>10,'user_agent'=>'Mozilla/5.0']]);
    $html = @file_get_contents(urldecode($_POST['sumber_url'] ?? ''), false, $ctx);
    if ($html) {
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        // Ambil semua <p> dan gabungkan
        $paragraphs = $doc->getElementsByTagName('p');
        $texts = [];
        foreach ($paragraphs as $p) {
            $t = trim($p->textContent);
            if (strlen($t) > 50) $texts[] = $t;
        }
        if (!empty($texts)) {
            $fetched_konten = implode("\n\n", array_slice($texts, 0, 20));
            $konten = esc($conn, $fetched_konten);
            if (!$ringkasan && isset($texts[0])) {
                $ringkasan = esc($conn, mb_substr($texts[0], 0, 200));
            }
        }
    }
}

if ($id) {
    // Edit artikel yang sudah ada
    $conn->query("UPDATE artikel SET judul='$judul',slug='$slug',ringkasan='$ringkasan',
                  konten='$konten',kategori='$kategori',emoji='$emoji',menit_baca=$menit,
                  sumber_url='$sumber',sumber_nama='$sumber_nm',is_publish=$publish
                  WHERE id=$id");
} else {
    // Artikel baru
    $conn->query("INSERT INTO artikel (judul,slug,ringkasan,konten,kategori,emoji,menit_baca,sumber_url,sumber_nama,penulis_id,is_publish)
                  VALUES ('$judul','$slug','$ringkasan','$konten','$kategori','$emoji',$menit,'$sumber','$sumber_nm',$uid,$publish)");
}

redirect('../dashboard.php?tab=artikel&success=artikel_saved');
