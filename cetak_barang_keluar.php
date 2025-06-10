<?php
require_once 'config.php';

if (!isset($_GET['id'])) {
    header("Location: barang_keluar.php");
    exit();
}

$id = sanitize($_GET['id']);

$sql = "SELECT bk.*, bb.nama_bahan, u.nama as nama_user 
        FROM barang_keluar bk
        JOIN bahan_baku bb ON bk.bahan_id = bb.id
        JOIN users u ON bk.user_id = u.id
        WHERE bk.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: barang_keluar.php");
    exit();
}

$data = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Barang Keluar - D'Fans Coffe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        .invoice-container {
            width: 80mm;
            margin: 0 auto;
            padding: 10px;
        }
        .header {
            text-align: center;
            margin-bottom: 10px;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
        }
        .info {
            margin-bottom: 10px;
        }
        .table {
            width: 100%;
            margin-bottom: 10px;
        }
        .footer {
            text-align: center;
            margin-top: 10px;
            border-top: 1px dashed #000;
            padding-top: 5px;
            font-size: 12px;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                margin: 0;
                padding: 0;
            }
            .invoice-container {
                margin: 0;
                padding: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-3 no-print">
        <div class="d-flex justify-content-between mb-3">
            <a href="barang_keluar.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> Cetak
            </button>
        </div>
    </div>

    <div class="invoice-container">
        <div class="header">
            <h4>D'FANS COFFE</h4>
            <p>Jl. Bajak 2-H Harjosari II, Medan</p>
            <h5>BARANG KELUAR</h5>
        </div>
        
        <div class="info">
            <p><strong>Tanggal:</strong> <?php echo date('d/m/Y', strtotime($data['tanggal'])); ?></p>
            <p><strong>No Faktur:</strong> <?php echo $data['no_faktur']; ?></p>
            <p><strong>Input Oleh:</strong> <?php echo $data['nama_user']; ?></p>
        </div>
        
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Bahan Baku</th>
                    <th>Qty</th>
                    <th>Satuan</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo $data['nama_bahan']; ?></td>
                    <td><?php echo $data['kuantitas']; ?></td>
                    <td><?php echo $data['satuan']; ?></td>
                </tr>
            </tbody>
        </table>
        
        <div class="footer">
            <p>Terima kasih - Sistem Pengelolaan Persediaan D'Fans Coffe</p>
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