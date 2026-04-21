<?php
/**
 * Proses/prosesSetting.php — Admin Master simpan pengaturan sistem
 */
session_start();
require '../Server/koneksi.php';
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'admin_master') redirect('../login.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../dashboard.php?tab=settings');

// Loop semua field yang dikirim dari form settings
foreach ($_POST as $kunci => $nilai) {
    if ($kunci === 'aksi') continue;
    $k = esc($conn, $kunci);
    $v = esc($conn, $nilai);
    $conn->query("UPDATE pengaturan_sistem SET nilai='$v' WHERE kunci='$k'");
}

redirect('../dashboard.php?tab=settings&success=setting_saved');
