<?php
/**
 * export.php — Export Data ke CSV
 * ─────────────────────────────────────────────────────────────
 * Hanya bisa diakses setelah login.
 *
 * Parameter URL:
 *   ?type=komoditas     → export semua komoditas approved
 *   ?type=laporan       → export laporan kontributor sendiri
 *   ?type=semua_laporan → export semua laporan (admin only)
 *   ?provinsi=X         → filter berdasarkan provinsi (opsional)
 *   ?nama=X             → filter berdasarkan nama komoditas (opsional)
 * ─────────────────────────────────────────────────────────────
 */
session_start();
require 'Server/koneksi.php';
cekLogin();

$type     = $_GET['type']     ?? 'komoditas';
$provinsi = trim(esc($conn, $_GET['provinsi'] ?? ''));
$nama     = trim(esc($conn, $_GET['nama']     ?? ''));
$uid      = (int)$_SESSION['user_id'];
$role     = $_SESSION['role'];
$isAdmin  = in_array($role, ['admin','admin_master']);

// Buat nama file CSV
$timestamp = date('Y-m-d_H-i-s');
$filename  = "infoharga_{$type}_{$timestamp}.csv";

// ── QUERY berdasarkan type ────────────────────────────────────
$rows  = [];
$heads = [];

switch ($type) {

    case 'komoditas':
        // Export semua komoditas approved (semua user bisa)
        $where = "WHERE k.status='approved'";
        if ($provinsi) $where .= " AND k.provinsi='$provinsi'";
        if ($nama)     $where .= " AND k.nama='$nama'";

        $res  = $conn->query("SELECT k.nama, k.kategori, k.lokasi, k.provinsi, k.satuan,
                                     k.harga_kemarin, k.harga_sekarang,
                                     ROUND((k.harga_sekarang - k.harga_kemarin) / k.harga_kemarin * 100, 2) AS perubahan_pct,
                                     u.username AS kontributor,
                                     k.updated_at
                              FROM komoditas k
                              LEFT JOIN users u ON k.submitted_by = u.id
                              $where
                              ORDER BY k.nama ASC, k.provinsi ASC");
        $heads = ['Nama Komoditas','Kategori','Kota/Lokasi','Provinsi','Satuan',
                  'Harga Kemarin (Rp)','Harga Sekarang (Rp)','Perubahan (%)','Kontributor','Terakhir Update'];
        break;

    case 'laporan':
        // Export laporan milik kontributor sendiri
        $res   = $conn->query("SELECT nama, kategori, lokasi, provinsi, satuan,
                                      harga_kemarin, harga_sekarang, status, catatan_admin, created_at
                               FROM komoditas
                               WHERE submitted_by=$uid
                               ORDER BY created_at DESC");
        $heads = ['Nama Komoditas','Kategori','Kota/Lokasi','Provinsi','Satuan',
                  'Harga Kemarin (Rp)','Harga Sekarang (Rp)','Status','Catatan Admin','Tanggal Kirim'];
        break;

    case 'semua_laporan':
        // Export semua laporan — hanya admin
        if (!$isAdmin) redirect('dashboard.php');
        $res  = $conn->query("SELECT k.nama, k.kategori, k.lokasi, k.provinsi, k.satuan,
                                     k.harga_kemarin, k.harga_sekarang, k.status, k.catatan_admin,
                                     u.username AS kontributor, k.created_at
                              FROM komoditas k
                              LEFT JOIN users u ON k.submitted_by = u.id
                              ORDER BY k.status ASC, k.created_at DESC");
        $heads = ['Nama Komoditas','Kategori','Kota/Lokasi','Provinsi','Satuan',
                  'Harga Kemarin (Rp)','Harga Sekarang (Rp)','Status','Catatan Admin','Kontributor','Tanggal Kirim'];
        break;

    default:
        redirect('dashboard.php');
}

if (!$res) redirect('dashboard.php');
while ($r = $res->fetch_assoc()) $rows[] = $r;

// ── LOG AKTIVITAS ─────────────────────────────────────────────
$jumlah = count($rows);
$conn->query("INSERT IGNORE INTO activity_log (user_id,username,aksi,deskripsi,ip_address)
    VALUES ($uid,'{$_SESSION['username']}','export_csv',
    'Export $type ($jumlah baris) ke CSV','".esc($conn,$_SERVER['REMOTE_ADDR']??'')."')");

// ── OUTPUT CSV ────────────────────────────────────────────────
// Set header HTTP agar browser download file
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// BOM UTF-8 agar Excel (Windows) baca karakter Indonesia dengan benar
echo "\xEF\xBB\xBF";

$fp = fopen('php://output', 'w');

// Baris judul file
fputcsv($fp, ['InfoHarga Komoditi — Export Data']);
fputcsv($fp, ['Tanggal Export:', date('d/m/Y H:i:s')]);
fputcsv($fp, ['Diekspor oleh:', $_SESSION['username'].' ('.$role.')']);
fputcsv($fp, ['Total baris:', $jumlah]);
fputcsv($fp, []); // Baris kosong

// Header kolom
fputcsv($fp, $heads);

// Data
foreach ($rows as $r) {
    $row = array_values($r);
    // Format rupiah untuk kolom harga
    foreach ($row as &$val) {
        if (is_numeric($val) && $val > 999) {
            $val = number_format((float)$val, 0, ',', '.');
        }
    }
    unset($val);
    fputcsv($fp, $row);
}

fclose($fp);
exit();
