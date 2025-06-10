<?php
require_once 'config.php';

if (!isset($_GET['id'])) {
    die(json_encode(['error' => 'ID tidak valid']));
}

$id = sanitize($_GET['id']);

$sql = "SELECT stok, safety_stock, rop FROM bahan_baku WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die(json_encode(['error' => 'Bahan baku tidak ditemukan']));
}

$data = $result->fetch_assoc();

header('Content-Type: application/json');
echo json_encode($data);
?>