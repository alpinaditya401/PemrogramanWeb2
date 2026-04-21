<?php
/**
 * Proses/prosesPengumuman.php — CRUD Pengumuman oleh Admin Master
 */
session_start();
require '../Server/koneksi.php';
if (!isset($_SESSION['login']) || !in_array($_SESSION['role'],['admin','admin_master'])) redirect('../login.php');

$aksi = $_POST['aksi'] ?? $_GET['aksi'] ?? '';
$uid  = (int)$_SESSION['user_id'];

// Hapus
if ($aksi === 'hapus' && isset($_GET['id'])) {
    $conn->query("DELETE FROM pengumuman WHERE id=" . (int)$_GET['id']);
    redirect('../dashboard.php?tab=pengumuman&success=deleted');
}

// Toggle aktif
if ($aksi === 'toggle' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $r  = $conn->query("SELECT is_active FROM pengumuman WHERE id=$id LIMIT 1");
    if ($r && $row = $r->fetch_assoc()) {
        $conn->query("UPDATE pengumuman SET is_active=" . ($row['is_active'] ? 0 : 1) . " WHERE id=$id");
    }
    redirect('../dashboard.php?tab=pengumuman&success=updated');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../dashboard.php?tab=pengumuman');

$id      = (int)($_POST['id'] ?? 0);
$judul   = esc($conn, $_POST['judul'] ?? '');
$isi     = esc($conn, $_POST['isi']   ?? '');
$tipe    = esc($conn, $_POST['tipe']  ?? 'info');
$aktif   = isset($_POST['is_active']) ? 1 : 0;
$berlaku = esc($conn, $_POST['berlaku_hingga'] ?? '');
$berlaku_val = $berlaku ? "'$berlaku'" : 'NULL';

if (!$judul || !$isi) redirect('../dashboard.php?tab=pengumuman&error=empty');

if ($id) {
    $conn->query("UPDATE pengumuman SET judul='$judul',isi='$isi',tipe='$tipe',is_active=$aktif,berlaku_hingga=$berlaku_val WHERE id=$id");
} else {
    $conn->query("INSERT INTO pengumuman (judul,isi,tipe,is_active,berlaku_hingga,dibuat_oleh) VALUES ('$judul','$isi','$tipe',$aktif,$berlaku_val,$uid)");
}
redirect('../dashboard.php?tab=pengumuman&success=pengumuman_saved');
