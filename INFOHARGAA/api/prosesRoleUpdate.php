<?php
/**
 * Proses/prosesRoleUpdate.php
 * ─────────────────────────────────────────────────────────────
 * Admin Master mengubah role user.
 * Hanya admin_master yang boleh akses endpoint ini.
 * Admin Master tidak boleh mengubah role dirinya sendiri.
 * ─────────────────────────────────────────────────────────────
 */
session_start();
require '../Server/koneksi.php';

// Guard: hanya admin_master
if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'admin_master') {
    redirect('../login.php');
}

$target_id = (int)($_POST['user_id']  ?? 0);
$new_role  = $_POST['new_role'] ?? '';
$valid_roles = ['admin_master','admin','kontributor','user'];

if (!$target_id || !in_array($new_role, $valid_roles, true)) {
    redirect('../dashboard.php?tab=users&error=invalid');
}

// Tidak boleh ubah role diri sendiri
if ($target_id === (int)$_SESSION['user_id']) {
    redirect('../dashboard.php?tab=users&error=self_edit');
}

$conn->query("UPDATE users SET role='$new_role' WHERE id=$target_id");
redirect('../dashboard.php?tab=users&success=role_updated');
