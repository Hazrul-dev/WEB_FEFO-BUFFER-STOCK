<?php
$title = "Dashboard";
$page_title = "Dashboard Sistem Persediaan";
require_once 'header.php';

// Hitung total bahan baku
$sql_bahan = "SELECT COUNT(*) as total FROM bahan_baku";
$result_bahan = $conn->query($sql_bahan);
$total_bahan = $result_bahan ? $result_bahan->fetch_assoc()['total'] : 0;

// Hitung total barang masuk bulan ini
$sql_masuk = "SELECT SUM(kuantitas) as total FROM barang_masuk 
              WHERE MONTH(tanggal) = MONTH(CURRENT_DATE()) 
              AND YEAR(tanggal) = YEAR(CURRENT_DATE())";
$result_masuk = $conn->query($sql_masuk);
$total_masuk = $result_masuk ? ($result_masuk->fetch_assoc()['total'] ?: 0) : 0;

// Hitung total barang keluar bulan ini
$sql_keluar = "SELECT SUM(kuantitas) as total FROM barang_keluar 
               WHERE MONTH(tanggal) = MONTH(CURRENT_DATE()) 
               AND YEAR(tanggal) = YEAR(CURRENT_DATE())";
$result_keluar = $conn->query($sql_keluar);
$total_keluar = $result_keluar ? ($result_keluar->fetch_assoc()['total'] ?: 0) : 0;

// Ambil bahan baku yang stoknya di bawah safety stock
$sql_low_stock = "SELECT b.nama_bahan, b.stok, b.safety_stock, b.rop, j.nama_jenis 
                  FROM bahan_baku b
                  JOIN jenis_bahan j ON b.jenis_id = j.id
                  WHERE b.stok < b.safety_stock
                  ORDER BY (b.stok/b.safety_stock) ASC
                  LIMIT 5";
$result_low_stock = $conn->query($sql_low_stock);

// Ambil bahan baku yang akan kadaluarsa dalam 7 hari
$sql_expired = "SELECT bm.id, bb.nama_bahan, bm.tanggal_expired, 
                DATEDIFF(bm.tanggal_expired, CURDATE()) as hari_left
                FROM barang_masuk bm
                JOIN bahan_baku bb ON bm.bahan_id = bb.id
                WHERE bm.tanggal_expired BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                ORDER BY bm.tanggal_expired ASC
                LIMIT 5";
$result_expired = $conn->query($sql_expired);

// Ambil bahan yang perlu dipesan (stok <= ROP)
$sql_need_order = "SELECT b.*, j.nama_jenis 
                   FROM bahan_baku b
                   JOIN jenis_bahan j ON b.jenis_id = j.id
                   WHERE b.stok <= b.rop
                   ORDER BY (b.stok/b.rop) ASC
                   LIMIT 5";
$result_need_order = $conn->query($sql_need_order);

// Data untuk grafik penggunaan bahan baku (30 hari terakhir)
$sql_chart = "SELECT 
                bb.nama_bahan,
                SUM(CASE WHEN bm.tanggal BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE() THEN bm.kuantitas ELSE 0 END) as masuk,
                SUM(CASE WHEN bk.tanggal BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE() THEN bk.kuantitas ELSE 0 END) as keluar,
                (SUM(CASE WHEN bm.tanggal BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE() THEN bm.kuantitas ELSE 0 END) + 
                (SUM(CASE WHEN bk.tanggal BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE() THEN bk.kuantitas ELSE 0 END)) as total
              FROM bahan_baku bb
              LEFT JOIN barang_masuk bm ON bb.id = bm.bahan_id
              LEFT JOIN barang_keluar bk ON bb.id = bk.bahan_id
              GROUP BY bb.id
              ORDER BY total DESC
              LIMIT 6";
$result_chart = $conn->query($sql_chart);

$chart_labels = [];
$chart_masuk = [];
$chart_keluar = [];

if ($result_chart) {
    while ($row = $result_chart->fetch_assoc()) {
        $chart_labels[] = $row['nama_bahan'];
        $chart_masuk[] = $row['masuk'] ?? 0;
        $chart_keluar[] = $row['keluar'] ?? 0;
    }
}

// Cek dan buat pesanan otomatis untuk bahan yang stok <= ROP
if ($_SESSION['jabatan'] == 'Admin' || $_SESSION['jabatan'] == 'Manager') {
    $sql_check_rop = "SELECT id FROM bahan_baku WHERE stok <= rop";
    $result_check_rop = $conn->query($sql_check_rop);
    
    if ($result_check_rop && $result_check_rop->num_rows > 0) {
        while ($row = $result_check_rop->fetch_assoc()) {
            checkAndCreateOrder($row['id'], $conn);
        }
    }
}
?>

<div class="row fade-in">
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body">
                <div class="card-icon">
                    <i class="fas fa-boxes"></i>
                </div>
                <h2><?php echo $total_bahan; ?></h2>
                <p class="card-text">Total Bahan Baku</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body">
                <div class="card-icon">
                    <i class="fas fa-arrow-down"></i>
                </div>
                <h2><?php echo $total_masuk; ?></h2>
                <p class="card-text">Barang Masuk Bulan Ini</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body">
                <div class="card-icon">
                    <i class="fas fa-arrow-up"></i>
                </div>
                <h2><?php echo $total_keluar; ?></h2>
                <p class="card-text">Barang Keluar Bulan Ini</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body">
                <div class="card-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <h2><?php echo $result_need_order ? $result_need_order->num_rows : 0; ?></h2>
                <p class="card-text">Bahan Perlu Dipesan</p>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4 slide-up">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-shopping-cart"></i> Bahan Perlu Dipesan (ROP)
            </div>
            <div class="card-body">
                <?php if ($result_need_order && $result_need_order->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Bahan Baku</th>
                                <th>Stok</th>
                                <th>ROP</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result_need_order->fetch_assoc()): 
                                $persentase = round(($row['stok'] / $row['rop']) * 100, 2);
                            ?>
                            <tr class="table-danger">
                                <td><?php echo $row['nama_bahan']; ?></td>
                                <td><?php echo $row['stok']; ?></td>
                                <td><?php echo $row['rop']; ?></td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-danger" role="progressbar" 
                                             style="width: <?php echo $persentase > 100 ? 100 : $persentase; ?>%" 
                                             aria-valuenow="<?php echo $persentase; ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                            <?php echo $persentase; ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <a href="<?php echo $_SESSION['jabatan'] == 'Supplier' ? 'pesanan_supplier.php' : 'pesanan_admin.php'; ?>" 
                class="btn btn-primary btn-sm mt-2">
                    <i class="fas fa-eye"></i> Lihat Semua Pesanan
                </a>
                <?php else: ?>
                <div class="alert alert-success">
                    Tidak ada bahan baku yang perlu dipesan saat ini.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-exclamation-triangle"></i> Bahan Baku Stok Rendah
            </div>
            <div class="card-body">
                <?php if ($result_low_stock && $result_low_stock->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Bahan Baku</th>
                                <th>Jenis</th>
                                <th>Stok</th>
                                <th>Safety Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result_low_stock->fetch_assoc()): ?>
                            <tr class="table-warning">
                                <td><?php echo $row['nama_bahan']; ?></td>
                                <td><?php echo $row['nama_jenis']; ?></td>
                                <td><?php echo $row['stok']; ?></td>
                                <td><?php echo $row['safety_stock']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <a href="bahan_baku.php" class="btn btn-primary btn-sm mt-2">
                    <i class="fas fa-eye"></i> Lihat Semua Bahan
                </a>
                <?php else: ?>
                <div class="alert alert-success">
                    Tidak ada bahan baku dengan stok di bawah safety stock.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-clock"></i> Bahan Akan Kadaluarsa (7 Hari)
            </div>
            <div class="card-body">
                <?php if ($result_expired && $result_expired->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Bahan Baku</th>
                                <th>Tanggal Expired</th>
                                <th>Sisa Hari</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result_expired->fetch_assoc()): 
                                $class = $row['hari_left'] <= 3 ? 'expired-soon' : '';
                            ?>
                            <tr class="<?php echo $class; ?>">
                                <td><?php echo $row['nama_bahan']; ?></td>
                                <td><?php echo date('d M Y', strtotime($row['tanggal_expired'])); ?></td>
                                <td><?php echo $row['hari_left']; ?> hari</td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <a href="barang_masuk.php" class="btn btn-primary btn-sm mt-2">
                    <i class="fas fa-eye"></i> Lihat Semua Barang Masuk
                </a>
                <?php else: ?>
                <div class="alert alert-success">
                    Tidak ada bahan baku yang akan kadaluarsa dalam 7 hari ke depan.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4 scale-up">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-line"></i> Grafik Penggunaan Bahan Baku (30 Hari Terakhir)
            </div>
            <div class="card-body">
                <canvas id="usageChart" height="100"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
    // Data untuk chart
    const ctx = document.getElementById('usageChart').getContext('2d');
    
    const usageChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [
                {
                    label: 'Barang Masuk',
                    data: <?php echo json_encode($chart_masuk); ?>,
                    backgroundColor: '#6F4E37',
                },
                {
                    label: 'Barang Keluar',
                    data: <?php echo json_encode($chart_keluar); ?>,
                    backgroundColor: '#D2B48C',
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Jumlah'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Bahan Baku'
                    }
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Penggunaan Bahan Baku 30 Hari Terakhir'
                },
                tooltip: {
                    callbacks: {
                        afterLabel: function(context) {
                            const total = context.dataset.data[context.dataIndex] + 
                                         context.chart.data.datasets
                                         .filter(d => d.label !== context.dataset.label)
                                         .map(d => d.data[context.dataIndex])[0];
                            return `Total: ${total}`;
                        }
                    }
                }
            }
        }
    });

    // Auto refresh dashboard setiap 5 menit
    setTimeout(function() {
        window.location.reload();
    }, 300000);
</script>

<?php require_once 'footer.php'; ?>