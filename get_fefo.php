<?php
require_once 'config.php';

if (!isset($_GET['bahan_id'])) {
    die(json_encode(['success' => false, 'message' => 'Bahan ID tidak valid']));
}

$bahan_id = sanitize($_GET['bahan_id']);
$fefo_data = getBahanByFEFO($bahan_id, $conn);

if ($fefo_data) {
    $expired_date = new DateTime($fefo_data['tanggal_expired']);
    $today = new DateTime();
    $days_left = $today->diff($expired_date)->days;
    
    echo json_encode([
        'success' => true,
        'expired_date' => $fefo_data['tanggal_expired'],
        'days_left' => $days_left,
        'sisa_stok' => $fefo_data['sisa']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Tidak ada stok tersedia untuk bahan ini'
    ]);
}
?>