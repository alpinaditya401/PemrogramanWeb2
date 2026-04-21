<?php
/**
 * Proses/prosesVerifikasi.php — Admin approve/reject laporan kontributor
 */
session_start();
require '../Server/koneksi.php';
if (!isset($_SESSION['login']) || !in_array($_SESSION['role'],['admin','admin_master'])) redirect('../login.php');

$id      = (int)($_POST['id']     ?? 0);
$aksi    = $_POST['aksi']          ?? '';
$catatan = esc($conn, $_POST['catatan'] ?? '');

if (!$id || !in_array($aksi, ['approve','reject'], true)) redirect('../dashboard.php');

$status = $aksi === 'approve' ? 'approved' : 'rejected';
$conn->query("UPDATE komoditas SET status='$status', catatan_admin='$catatan' WHERE id=$id");
redirect('../dashboard.php?tab=verifikasi&success=updated');
