<?php
require_once 'config.php';

if (!isset($_GET['id'])) {
    header("Location: barang_masuk.php");
    exit();
}

$id = sanitize($_GET['id']);

$sql = "SELECT bm.*, bb.nama_bahan, s.nama_supplier 
        FROM barang_masuk bm
        JOIN bahan_baku bb ON bm.bahan_id = bb.id
        JOIN suppliers s ON bm.supplier_id = s.id
        WHERE bm.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: barang_masuk.php");
    exit();
}

$data = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Label FEFO - D'Fans Coffe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        .label-container {
            width: 80mm;
            height: 50mm;
            border: 1px solid #000;
            padding: 5px;
            margin: 0 auto;
            position: relative;
        }
        .label-header {
            text-align: center;
            font-weight: bold;
            border-bottom: 1px dashed #000;
            padding-bottom: 3px;
            margin-bottom: 3px;
        }
        .label-content {
            font-size: 12px;
        }
        .label-footer {
            position: absolute;
            bottom: 5px;
            width: calc(100% - 10px);
            text-align: center;
            font-size: 10px;
            border-top: 1px dashed #000;
            padding-top: 3px;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                margin: 0;
                padding: 0;
            }
            .label-container {
                margin: 0;
                border: none;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-3 no-print">
        <div class="d-flex justify-content-between mb-3">
            <a href="barang_masuk.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> Cetak Label
            </button>
        </div>
    </div>

    <div class="label-container">
        <div class="label-header">
            D'FANS COFFE - LABEL FEFO
        </div>
        <div class="label-content">
            <div><strong>Bahan:</strong> <?php echo htmlspecialchars($data['nama_bahan']); ?></div>
            <div><strong>No Faktur:</strong> <?php echo isset($data['no_faktur']) ? htmlspecialchars($data['no_faktur']) : '-'; ?></div>
            <div><strong>Supplier:</strong> <?php echo htmlspecialchars($data['nama_supplier']); ?></div>
            <div><strong>Tanggal Masuk:</strong> 
                <?php 
                if (isset($data['tanggal_masuk']) && !empty($data['tanggal_masuk'])) {
                    echo date('d/m/Y', strtotime($data['tanggal_masuk']));
                } else {
                    echo '-';
                }
                ?>
            </div>
            <div><strong>Tanggal Expired:</strong> <?php echo date('d/m/Y', strtotime($data['tanggal_expired'])); ?></div>
            <div><strong>Qty:</strong> <?php echo $data['kuantitas']; ?></div>
        </div>
        <div class="label-footer">
            Gunakan sebelum tanggal expired - Sistem FEFO
        </div>
    </div>

    <script>
        // Auto print when page loads
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>