<?php
/**
 * Proses/prosesSubmit.php
 * Kontributor kirim laporan harga → status pending, menunggu verifikasi admin
 */
session_start();
require '../Server/koneksi.php';

if (!isset($_SESSION['login'])) redirect('../login.php');
if ($_SESSION['role'] !== 'kontributor') redirect('../dashboard-user.php');

$uid      = (int)$_SESSION['user_id'];
$nama     = esc($conn, $_POST['nama']     ?? '');
$kategori = esc($conn, $_POST['kategori'] ?? 'Lainnya');
$lokasi   = esc($conn, $_POST['lokasi']   ?? '');
$provinsi = esc($conn, $_POST['provinsi'] ?? '');
$satuan   = esc($conn, $_POST['satuan']   ?? 'kg');
$kemarin  = max(0, (int)($_POST['kemarin']  ?? 0));
$sekarang = max(0, (int)($_POST['sekarang'] ?? 0));

if (!$nama || !$lokasi || !$provinsi || $kemarin <= 0 || $sekarang <= 0)
    redirect('../dashboard-user.php?error=submit_empty');

// Cegah duplikat pending
$dup = $conn->query("SELECT id FROM komoditas WHERE nama='$nama' AND lokasi='$lokasi' AND submitted_by=$uid AND status='pending' LIMIT 1");
if ($dup && $dup->num_rows > 0) redirect('../dashboard-user.php?error=already_pending');

$hist = esc($conn, json_encode(array_merge(array_fill(0,6,$kemarin), [$sekarang])));
$conn->query("INSERT INTO komoditas (nama,kategori,lokasi,provinsi,satuan,harga_kemarin,harga_sekarang,history,status,submitted_by)
              VALUES ('$nama','$kategori','$lokasi','$provinsi','$satuan',$kemarin,$sekarang,'$hist','pending',$uid)");

redirect('../dashboard-user.php?success=submitted');
