<?php
$title = "Laporan";
$page_title = "Laporan Persediaan";
$show_print_button = true;
require_once 'header.php';

// Hanya admin dan manager yang bisa mengakses
if ($_SESSION['jabatan'] != 'Admin' && $_SESSION['jabatan'] != 'Manager') {
    header("Location: dashboard.php");
    exit();
}

// Filter laporan
$filter = isset($_GET['filter']) ? sanitize($_GET['filter']) : 'hari_ini';
$start_date = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : '';

// Set tanggal berdasarkan filter
switch ($filter) {
    case 'hari_ini':
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d');
        break;
    case 'minggu_ini':
        $start_date = date('Y-m-d', strtotime('monday this week'));
        $end_date = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'bulan_ini':
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        break;
    case 'tahun_ini':
        $start_date = date('Y-01-01');
        $end_date = date('Y-12-31');
        break;
    case 'custom':
        // Gunakan tanggal yang sudah diinput
        break;
}

// Query untuk laporan barang masuk
$sql_masuk = "SELECT bm.*, bb.nama_bahan, s.nama_supplier 
              FROM barang_masuk bm
              JOIN bahan_baku bb ON bm.bahan_id = bb.id
              JOIN suppliers s ON bm.supplier_id = s.id
              WHERE bm.tanggal_masuk BETWEEN ? AND ?  
              ORDER BY bm.tanggal_masuk DESC";       
$stmt_masuk = $conn->prepare($sql_masuk);
$stmt_masuk->bind_param("ss", $start_date, $end_date);
$stmt_masuk->execute();
$result_masuk = $stmt_masuk->get_result();

// Query untuk laporan barang keluar
$sql_keluar = "SELECT bk.*, bb.nama_bahan 
               FROM barang_keluar bk
               JOIN bahan_baku bb ON bk.bahan_id = bb.id
               WHERE bk.tanggal BETWEEN ? AND ?
               ORDER BY bk.tanggal DESC";
$stmt_keluar = $conn->prepare($sql_keluar);
$stmt_keluar->bind_param("ss", $start_date, $end_date);
$stmt_keluar->execute();
$result_keluar = $stmt_keluar->get_result();

// Query untuk stok bahan baku
$sql_stok = "SELECT b.*, j.nama_jenis 
             FROM bahan_baku b
             JOIN jenis_bahan j ON b.jenis_id = j.id
             ORDER BY b.nama_bahan";
$result_stok = $conn->query($sql_stok);

// Query untuk bahan yang akan kadaluarsa
$sql_expired = "SELECT bm.id, bb.nama_bahan, bm.tanggal_expired, 
                DATEDIFF(bm.tanggal_expired, CURDATE()) as hari_left
                FROM barang_masuk bm
                JOIN bahan_baku bb ON bm.bahan_id = bb.id
                WHERE bm.tanggal_expired BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                ORDER BY bm.tanggal_expired ASC";
$result_expired = $conn->query($sql_expired);

// Query untuk bahan dengan stok rendah
$sql_low_stock = "SELECT b.*, j.nama_jenis 
                  FROM bahan_baku b
                  JOIN jenis_bahan j ON b.jenis_id = j.id
                  WHERE b.stok < b.safety_stock
                  ORDER BY (b.stok/b.safety_stock) ASC";
$result_low_stock = $conn->query($sql_low_stock);
?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Filter Laporan</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">Periode</label>
                        <select class="form-select" name="filter" id="filterSelect">
                            <option value="hari_ini" <?php echo $filter == 'hari_ini' ? 'selected' : ''; ?>>Hari Ini</option>
                            <option value="minggu_ini" <?php echo $filter == 'minggu_ini' ? 'selected' : ''; ?>>Minggu Ini</option>
                            <option value="bulan_ini" <?php echo $filter == 'bulan_ini' ? 'selected' : ''; ?>>Bulan Ini</option>
                            <option value="tahun_ini" <?php echo $filter == 'tahun_ini' ? 'selected' : ''; ?>>Tahun Ini</option>
                            <option value="custom" <?php echo $filter == 'custom' ? 'selected' : ''; ?>>Custom</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">Tanggal Mulai</label>
                        <input type="date" class="form-control" name="start_date" id="startDate" 
                               value="<?php echo $start_date; ?>" <?php echo $filter != 'custom' ? 'disabled' : ''; ?>>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">Tanggal Selesai</label>
                        <input type="date" class="form-control" name="end_date" id="endDate" 
                               value="<?php echo $end_date; ?>" <?php echo $filter != 'custom' ? 'disabled' : ''; ?>>
                    </div>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Barang Masuk (<?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if ($result_masuk->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Bahan Baku</th>
                                <th>Supplier</th>
                                <th>Qty</th>
                                <th>Harga</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result_masuk->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($row['tanggal'])); ?></td>
                                <td><?php echo $row['nama_bahan']; ?></td>
                                <td><?php echo $row['nama_supplier']; ?></td>
                                <td><?php echo $row['kuantitas']; ?></td>
                                <td><?php echo number_format($row['harga'], 0, ',', '.'); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    Tidak ada data barang masuk pada periode ini.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Barang Keluar (<?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if ($result_keluar->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Bahan Baku</th>
                                <th>Qty</th>
                                <th>No Faktur</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result_keluar->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($row['tanggal'])); ?></td>
                                <td><?php echo $row['nama_bahan']; ?></td>
                                <td><?php echo $row['kuantitas']; ?></td>
                                <td><?php echo $row['no_faktur']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    Tidak ada data barang keluar pada periode ini.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Stok Bahan Baku</h5>
            </div>
            <div class="card-body">
                <?php if ($result_stok->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Bahan Baku</th>
                                <th>Jenis</th>
                                <th>Stok</th>
                                <th>Safety Stock</th>
                                <th>ROP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result_stok->fetch_assoc()): 
                                $stok_warning = $row['stok'] < $row['safety_stock'] ? 'text-danger fw-bold' : '';
                            ?>
                            <tr>
                                <td><?php echo $row['nama_bahan']; ?></td>
                                <td><?php echo $row['nama_jenis']; ?></td>
                                <td class="<?php echo $stok_warning; ?>"><?php echo $row['stok']; ?></td>
                                <td><?php echo $row['safety_stock']; ?></td>
                                <td><?php echo $row['rop']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    Tidak ada data bahan baku.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Bahan Akan Kadaluarsa (30 Hari)</h5>
            </div>
            <div class="card-body">
                <?php if ($result_expired->num_rows > 0): ?>
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
                                $class = $row['hari_left'] <= 7 ? 'expired-soon' : '';
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
                <?php else: ?>
                <div class="alert alert-success">
                    Tidak ada bahan baku yang akan kadaluarsa dalam 30 hari ke depan.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Bahan dengan Stok Rendah</h5>
            </div>
            <div class="card-body">
                <?php if ($result_low_stock->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Bahan Baku</th>
                                <th>Jenis</th>
                                <th>Stok</th>
                                <th>Safety Stock</th>
                                <th>ROP</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result_low_stock->fetch_assoc()): 
                                $persentase = round(($row['stok'] / $row['safety_stock']) * 100, 2);
                            ?>
                            <tr class="table-warning">
                                <td><?php echo $row['nama_bahan']; ?></td>
                                <td><?php echo $row['nama_jenis']; ?></td>
                                <td><?php echo $row['stok']; ?></td>
                                <td><?php echo $row['safety_stock']; ?></td>
                                <td><?php echo $row['rop']; ?></td>
                                <td>
                                    <div class="progress">
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
                <?php else: ?>
                <div class="alert alert-success">
                    Tidak ada bahan baku dengan stok di bawah safety stock.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Enable/disable date inputs based on filter selection
    document.getElementById('filterSelect').addEventListener('change', function() {
        const isCustom = this.value === 'custom';
        document.getElementById('startDate').disabled = !isCustom;
        document.getElementById('endDate').disabled = !isCustom;
    });
</script>

<?php require_once 'footer.php'; ?>