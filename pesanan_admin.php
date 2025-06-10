<?php
$title = "Pesanan Bahan Baku (Admin)";
$page_title = "Manajemen Pemesanan Bahan Baku (Buffer Stock & FEFO) - Admin";
$show_print_button = true;
require_once 'header.php';

// Verify database connection first
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Hanya admin yang bisa mengakses
if ($_SESSION['jabatan'] != 'Admin' && $_SESSION['jabatan'] != 'Manager') {
    exit();
}

// Function to handle database operations with error checking
function executeQuery($conn, $sql, $params = [], $types = "") {
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    if (!empty($params)) {
        if (!$stmt->bind_param($types, ...$params)) {
            throw new Exception("Bind failed: " . $stmt->error);
        }
    }

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    return $stmt;
}

// Proses tambah pesanan baru
if (isset($_POST['tambah'])) {
    $bahan_id = sanitize($_POST['bahan_id']);
    $supplier_id = sanitize($_POST['supplier_id']);
    $jumlah = sanitize($_POST['jumlah']);
    $harga_satuan = sanitize($_POST['harga_satuan']);
    $total_harga = $jumlah * $harga_satuan;
    
    // Mulai transaksi
    $conn->begin_transaction();
    
    try {
        // 1. Simpan pesanan
        $sql = "INSERT INTO pesanan (bahan_id, supplier_id, jumlah, harga_satuan, total_harga, status, user_id, tanggal_pesanan)
                VALUES (?, ?, ?, ?, ?, 'Dipesan', ?, NOW())";
        $stmt = executeQuery($conn, $sql, [$bahan_id, $supplier_id, $jumlah, $harga_satuan, $total_harga, $_SESSION['user_id']], "iiidii");
        $pesanan_id = $conn->insert_id;
        $stmt->close();
        
        // 2. Kirim notifikasi ke supplier
        $sql_bahan = "SELECT nama_bahan FROM bahan_baku WHERE id = ?";
        $stmt_bahan = executeQuery($conn, $sql_bahan, [$bahan_id], "i");
        $bahan = $stmt_bahan->get_result()->fetch_assoc();
        $stmt_bahan->close();
        
        $message = "Pesanan baru #$pesanan_id: " . $bahan['nama_bahan'] . " sebanyak $jumlah kg. Harap konfirmasi pengiriman.";
        
        $sql_notif = "INSERT INTO notifications (user_id, message, is_read, created_at)
                      SELECT u.id, ?, 0, NOW() 
                      FROM users u 
                      WHERE u.supplier_id = ? AND u.jabatan = 'Supplier'";
        $stmt_notif = executeQuery($conn, $sql_notif, [$message, $supplier_id], "si");
        $stmt_notif->close();
        
        // Commit transaksi
        $conn->commit();
        
        $_SESSION['success'] = "Pesanan berhasil dibuat dan notifikasi telah dikirim ke supplier!";
        
        // Catat aktivitas
        $log_action = "Membuat pesanan baru #$pesanan_id";
        logActivity($_SESSION['user_id'], 'INSERT', 'pesanan', $pesanan_id, $log_action);
        
    } catch (Exception $e) {
        // Rollback jika ada error
        $conn->rollback();
        $_SESSION['error'] = "Gagal membuat pesanan: " . $e->getMessage();
    }
    exit();
}

// Admin confirms receipt in "Barang Masuk"
if (isset($_POST['terima_barang'])) {
    $id = sanitize($_POST['id']);
    $catatan = isset($_POST['catatan']) ? sanitize($_POST['catatan']) : '';
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // 1. Update order status
        $sql = "UPDATE pesanan 
                SET status = 'Diterima', 
                    tanggal_diterima = NOW() 
                WHERE id = ?";
        $stmt = executeQuery($conn, $sql, [$id], "i");
        $stmt->close();
        
        // 2. Get order details
        $sql_order = "SELECT p.*, b.nama_bahan, s.nama_supplier 
                     FROM pesanan p
                     JOIN bahan_baku b ON p.bahan_id = b.id
                     JOIN suppliers s ON p.supplier_id = s.id
                     WHERE p.id = ?";
        $stmt_order = executeQuery($conn, $sql_order, [$id], "i");
        $order = $stmt_order->get_result()->fetch_assoc();
        $stmt_order->close();
        
        // 3. Add to barang_masuk (FEFO implementation)
        $sql_masuk = "INSERT INTO barang_masuk (
                        bahan_id, 
                        supplier_id, 
                        kuantitas, 
                        harga_satuan, 
                        total_harga, 
                        tanggal_masuk, 
                        tanggal_expired,
                        catatan,
                        user_id,
                        pesanan_id
                      ) VALUES (?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), ?, ?, ?)";
        $stmt_masuk = executeQuery($conn, $sql_masuk, [
            $order['bahan_id'], 
            $order['supplier_id'], 
            $order['jumlah'], 
            $order['harga_satuan'], 
            $order['total_harga'],
            $catatan,
            $_SESSION['user_id'],
            $id
        ], "iiiddsii");
        $stmt_masuk->close();
        
        // 4. Update stock
        $sql_stock = "UPDATE bahan_baku 
             SET stok = stok + ?
             WHERE id = ?";
        $stmt_stock = executeQuery($conn, $sql_stock, [$order['jumlah'], $order['bahan_id']], "ii");
        $stmt_stock->close();
        
        // 5. Update buffer stock
        $sql_buffer = "UPDATE bahan_baku 
                      SET buffer_stock = GREATEST(rop, stok * 0.2) 
                      WHERE id = ?";
        $stmt_buffer = executeQuery($conn, $sql_buffer, [$order['bahan_id']], "i");
        $stmt_buffer->close();
        
        // 6. Kirim notifikasi ke supplier
        $message = "Pesanan #$id (" . $order['nama_bahan'] . " " . $order['jumlah'] . "kg) telah diterima oleh admin.";
        
        $sql_notif = "INSERT INTO notifications (user_id, message, is_read, created_at)
                      SELECT u.id, ?, 0, NOW() 
                      FROM users u
                      WHERE u.supplier_id = ? AND u.jabatan = 'Supplier'";
        $stmt_notif = executeQuery($conn, $sql_notif, [$message, $order['supplier_id']], "si");
        $stmt_notif->close();
        
        // Commit transaksi
        $conn->commit();
        
        $_SESSION['success'] = "Barang berhasil diterima, stok diperbarui, dan notifikasi dikirim ke supplier!";
        
        // Catat aktivitas
        $log_action = "Menerima pesanan #$id dan menambahkan ke barang masuk";
        logActivity($_SESSION['user_id'], 'UPDATE', 'pesanan', $id, $log_action);
        
    } catch (Exception $e) {
        // Rollback jika ada error
        $conn->rollback();
        $_SESSION['error'] = "Gagal memproses penerimaan barang: " . $e->getMessage();
    }
    exit();
}

// Admin cancels order
if (isset($_POST['batalkan_pesanan'])) {
    $id = sanitize($_POST['id']);
    $catatan = sanitize($_POST['catatan']);
    
    // Mulai transaksi
    $conn->begin_transaction();
    
    try {
        // 1. Update status pesanan
        $sql = "UPDATE pesanan 
                SET status = 'Dibatalkan', 
                    catatan_pengiriman = ?,
                    tanggal_diterima = NOW() 
                WHERE id = ?";
        $stmt = executeQuery($conn, $sql, [$catatan, $id], "si");
        $stmt->close();
        
        // 2. Dapatkan detail pesanan untuk notifikasi
        $sql_pesanan = "SELECT p.*, b.nama_bahan, s.nama_supplier 
                        FROM pesanan p
                        JOIN bahan_baku b ON p.bahan_id = b.id
                        JOIN suppliers s ON p.supplier_id = s.id
                        WHERE p.id = ?";
        $stmt_pesanan = executeQuery($conn, $sql_pesanan, [$id], "i");
        $pesanan = $stmt_pesanan->get_result()->fetch_assoc();
        $stmt_pesanan->close();
        
        // 3. Kirim notifikasi ke supplier
        $message = "Pesanan #$id (" . $pesanan['nama_bahan'] . " " . $pesanan['jumlah'] . "kg) telah dibatalkan oleh admin. Alasan: $catatan";
        
        $sql_notif = "INSERT INTO notifications (user_id, message, is_read, created_at)
                      SELECT u.id, ?, 0, NOW() 
                      FROM users u
                      WHERE u.supplier_id = ? AND u.jabatan = 'Supplier'";
        $stmt_notif = executeQuery($conn, $sql_notif, [$message, $pesanan['supplier_id']], "si");
        $stmt_notif->close();
        
        // Commit transaksi
        $conn->commit();
        
        $_SESSION['success'] = "Pesanan berhasil dibatalkan dan notifikasi telah dikirim ke supplier!";
        
        // Catat aktivitas
        $log_action = "Membatalkan pesanan #$id";
        logActivity($_SESSION['user_id'], 'UPDATE', 'pesanan', $id, $log_action);
        
    } catch (Exception $e) {
        // Rollback jika ada error
        $conn->rollback();
        $_SESSION['error'] = "Gagal membatalkan pesanan: " . $e->getMessage();
    }
    header("Location: pesanan_admin.php");
    exit();
}

// Admin completes order
if (isset($_POST['selesaikan_pesanan'])) {
    $id = sanitize($_POST['id']);
    
    // Mulai transaksi
    $conn->begin_transaction();
    
    try {
        // 1. Update status pesanan (versi dengan pengecekan kolom)
        $sql = "UPDATE pesanan 
                SET status = 'Selesai'";
        
        // Cek apakah kolom tanggal_selesai ada di tabel
        $check_column = $conn->query("SHOW COLUMNS FROM pesanan LIKE 'tanggal_selesai'");
        if ($check_column && $check_column->num_rows > 0) {
            $sql .= ", tanggal_selesai = NOW()";
        }
        
        $sql .= " WHERE id = ? AND status = 'Diterima'";
        
        $stmt = executeQuery($conn, $sql, [$id], "i");
        $stmt->close();
        
        // 2. Dapatkan detail pesanan untuk notifikasi
        $sql_pesanan = "SELECT p.*, b.nama_bahan, s.nama_supplier 
                        FROM pesanan p
                        JOIN bahan_baku b ON p.bahan_id = b.id
                        JOIN suppliers s ON p.supplier_id = s.id
                        WHERE p.id = ?";
        $stmt_pesanan = executeQuery($conn, $sql_pesanan, [$id], "i");
        $pesanan = $stmt_pesanan->get_result()->fetch_assoc();
        $stmt_pesanan->close();
        
        // 3. Kirim notifikasi ke supplier
        $message = "Pesanan #$id (" . $pesanan['nama_bahan'] . " " . $pesanan['jumlah'] . "kg) telah diselesaikan oleh admin.";
        
        $sql_notif = "INSERT INTO notifications (user_id, message, is_read, created_at)
                      SELECT u.id, ?, 0, NOW() 
                      FROM users u
                      WHERE u.supplier_id = ? AND u.jabatan = 'Supplier'";
        $stmt_notif = executeQuery($conn, $sql_notif, [$message, $pesanan['supplier_id']], "si");
        $stmt_notif->close();
        
        // Commit transaksi
        $conn->commit();
        
        $_SESSION['success'] = "Pesanan berhasil diselesaikan dan diarsipkan!";
        
        // Catat aktivitas
        $log_action = "Menyelesaikan pesanan #$id";
        logActivity($_SESSION['user_id'], 'UPDATE', 'pesanan', $id, $log_action);
        
    } catch (Exception $e) {
        // Rollback jika ada error
        $conn->rollback();
        $_SESSION['error'] = "Gagal menyelesaikan pesanan: " . $e->getMessage();
    }
    
    exit();
}

// Query data pesanan untuk admin
try {
    $sql = "SELECT p.*, b.nama_bahan, s.nama_supplier, u.nama as nama_user,
                   b.stok, IFNULL(b.buffer_stock, 0) as buffer_stock, 
                   IFNULL(b.rop, 0) as rop,
                   (SELECT MIN(tanggal_expired) 
                    FROM barang_masuk 
                    WHERE bahan_id = p.bahan_id AND kuantitas > 0) as next_expired
            FROM pesanan p
            JOIN bahan_baku b ON p.bahan_id = b.id
            JOIN suppliers s ON p.supplier_id = s.id
            JOIN users u ON p.user_id = u.id
            WHERE p.status != 'Selesai'
            ORDER BY 
                CASE 
                    WHEN p.status = 'Dipesan' THEN 1
                    WHEN p.status = 'Dikirim' THEN 2
                    ELSE 3
                END,
                p.tanggal_pesanan DESC";
    
    $result = $conn->query($sql);
    if ($result === false) {
        throw new Exception("Query failed: " . $conn->error);
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
    $result = false;
}

// Ambil data bahan baku untuk dropdown
$sql_bahan = "SELECT *, 
             IFNULL((stok <= buffer_stock), 0) as needs_restock,
             IFNULL((stok <= rop), 0) as below_rop
             FROM bahan_baku 
             ORDER BY 
                below_rop DESC,
                needs_restock DESC,
                nama_bahan";
$result_bahan = $conn->query($sql_bahan);
if ($result_bahan === false) {
    $_SESSION['error'] = "Gagal mengambil data bahan baku: " . $conn->error;
    $result_bahan = false;
}

// Ambil data supplier untuk dropdown
$sql_supplier = "SELECT * FROM suppliers ORDER BY nama_supplier";
$result_supplier = $conn->query($sql_supplier);
if ($result_supplier === false) {
    $_SESSION['error'] = "Gagal mengambil data supplier: " . $conn->error;
    $result_supplier = false;
}

// Ambil notifikasi untuk admin
$notifications = [];
try {
    $sql_notif = "SELECT * FROM notifications 
                 WHERE user_id = ? AND is_read = 0
                 ORDER BY created_at DESC
                 LIMIT 5";
    $stmt_notif = $conn->prepare($sql_notif);
    if ($stmt_notif) {
        if ($stmt_notif->bind_param("i", $_SESSION['user_id'])) {
            if ($stmt_notif->execute()) {
                $notifications = $stmt_notif->get_result()->fetch_all(MYSQLI_ASSOC);
            }
        }
        $stmt_notif->close();
    }
} catch (Exception $e) {
    error_log("Notification error: " . $e->getMessage());
}
?>

<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Daftar Pesanan Bahan Baku</h5>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#tambahModal">
                <i class="fas fa-plus"></i> Buat Pesanan
            </button>
        </div>
    </div>
    <div class="card-body">
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($notifications)): ?>
        <div class="alert alert-info">
            <h6><i class="fas fa-bell me-2"></i>Notifikasi Terbaru</h6>
            <ul class="mb-0">
                <?php foreach ($notifications as $notif): ?>
                <li><?php echo htmlspecialchars($notif['message']); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal Pesan</th>
                        <th>Bahan Baku</th>
                        <th>Supplier</th>
                        <th>Jumlah</th>
                        <th>Harga Satuan</th>
                        <th>Total Harga</th>
                        <th>Status</th>
                        <th>Input Oleh</th>
                        <th>Info Stok</th>
                        <th class="no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result !== false && $result->num_rows > 0): 
                        $no = 1;
                        while ($row = $result->fetch_assoc()): 
                            $stok_class = '';
                            if ($row['stok'] <= $row['rop']) {
                                $stok_class = 'bg-danger text-white';
                            } elseif ($row['stok'] <= $row['buffer_stock']) {
                                $stok_class = 'bg-warning';
                            }
                    ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo date('d M Y H:i', strtotime($row['tanggal_pesanan'])); ?></td>
                        <td><?php echo htmlspecialchars($row['nama_bahan']); ?></td>
                        <td><?php echo htmlspecialchars($row['nama_supplier']); ?></td>
                        <td><?php echo $row['jumlah']; ?></td>
                        <td><?php echo number_format($row['harga_satuan'], 0, ',', '.'); ?></td>
                        <td><?php echo number_format($row['total_harga'], 0, ',', '.'); ?></td>
                        <td>
                            <span class="badge bg-<?php 
                                echo $row['status'] == 'Dipesan' ? 'warning' : 
                                     ($row['status'] == 'Dikirim' ? 'info' : 
                                     ($row['status'] == 'Diterima' ? 'primary' : 
                                     ($row['status'] == 'Dibatalkan' ? 'danger' : 'success'))); 
                            ?>">
                                <?php echo $row['status']; ?>
                                <?php if (($row['status'] == 'Dikirim' || $row['status'] == 'Dibatalkan') && !empty($row['catatan_pengiriman'])): ?>
                                <i class="fas fa-info-circle ms-1" title="<?php echo htmlspecialchars($row['catatan_pengiriman']); ?>"></i>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($row['nama_user']); ?></td>
                        <td class="<?php echo $stok_class; ?>">
                            <small>
                                Stok: <?php echo $row['stok']; ?><br>
                                Buffer: <?php echo $row['buffer_stock']; ?><br>
                                Exp: <?php echo $row['next_expired'] ? date('d/m/y', strtotime($row['next_expired'])) : '-'; ?>
                            </small>
                        </td>
                        <td class="no-print">
                            <?php if ($row['status'] == 'Dikirim'): ?>
                                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#statusModal<?php echo $row['id']; ?>">
                                    <i class="fas fa-check"></i> Terima
                                </button>
                            
                            <?php elseif ($row['status'] == 'Dipesan'): ?>
                                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#statusModal<?php echo $row['id']; ?>">
                                    <i class="fas fa-times"></i> Batalkan
                                </button>
                            
                            <?php elseif ($row['status'] == 'Diterima'): ?>
                                <button class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#statusModal<?php echo $row['id']; ?>">
                                    <i class="fas fa-check-circle"></i> Selesaikan
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- Modal untuk Admin (Terima Barang) -->
                    <?php if ($row['status'] == 'Dikirim'): ?>
                    <div class="modal fade" id="statusModal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Konfirmasi Penerimaan Barang</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="POST" action="">
                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Status Saat Ini</label>
                                            <input type="text" class="form-control" value="<?php echo $row['status']; ?>" readonly>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Status Baru</label>
                                            <input type="text" class="form-control" value="Diterima" readonly>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Catatan Penerimaan</label>
                                            <textarea class="form-control" name="catatan" placeholder="Masukkan catatan penerimaan (kondisi barang, dll)"></textarea>
                                        </div>
                                        <div class="alert alert-info">
                                            <strong>Proses FEFO:</strong> Barang akan dimasukkan ke stok dengan tanggal expired 30 hari dari sekarang.
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                        <button type="submit" name="terima_barang" class="btn btn-success">
                                            <i class="fas fa-check"></i> Konfirmasi Penerimaan
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Modal untuk Admin (Batalkan Pesanan) -->
                    <?php if ($row['status'] == 'Dipesan'): ?>
                    <div class="modal fade" id="statusModal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Batalkan Pesanan</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="POST" action="">
                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Status Saat Ini</label>
                                            <input type="text" class="form-control" value="<?php echo $row['status']; ?>" readonly>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Status Baru</label>
                                            <input type="text" class="form-control" value="Dibatalkan" readonly>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Alasan Pembatalan <span class="text-danger">*</span></label>
                                            <textarea class="form-control" name="catatan" placeholder="Masukkan alasan pembatalan pesanan" required></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                        <button type="submit" name="batalkan_pesanan" class="btn btn-danger">
                                            <i class="fas fa-times"></i> Batalkan Pesanan
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Modal untuk Admin (Selesaikan Pesanan) -->
                    <?php if ($row['status'] == 'Diterima'): ?>
                    <div class="modal fade" id="statusModal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Selesaikan Pesanan</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="POST" action="">
                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Status Saat Ini</label>
                                            <input type="text" class="form-control" value="<?php echo $row['status']; ?>" readonly>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Status Baru</label>
                                            <input type="text" class="form-control" value="Selesai" readonly>
                                        </div>
                                        <div class="alert alert-success">
                                            Pesanan ini sudah diterima dan barang sudah masuk ke sistem. Menyelesaikan pesanan akan mengarsipkannya dari daftar aktif.
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                        <button type="submit" name="selesaikan_pesanan" class="btn btn-success">
                                            <i class="fas fa-check-circle"></i> Selesaikan Pesanan
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php endwhile; ?>
                    <?php elseif ($result === false): ?>
                    <tr>
                        <td colspan="11" class="text-center text-danger">Terjadi kesalahan saat mengambil data pesanan</td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <td colspan="11" class="text-center">Tidak ada data pesanan</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah Pesanan -->
<div class="modal fade" id="tambahModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Buat Pesanan Baru (Buffer Stock)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Bahan Baku <span class="text-danger">*</span></label>
                        <select class="form-select" name="bahan_id" id="bahanSelect" required>
                            <option value="">Pilih Bahan Baku</option>
                            <?php while ($bahan = $result_bahan->fetch_assoc()): 
                                $warning = '';
                                if ($bahan['below_rop']) {
                                    $warning = ' (STOK KRITIS!)';
                                } elseif ($bahan['needs_restock']) {
                                    $warning = ' (Perlu Restock)';
                                }
                            ?>
                            <option value="<?php echo $bahan['id']; ?>" 
                                    data-stok="<?php echo $bahan['stok']; ?>" 
                                    data-buffer="<?php echo $bahan['buffer_stock']; ?>"
                                    data-rop="<?php echo $bahan['rop']; ?>">
                                <?php echo htmlspecialchars($bahan['nama_bahan']); ?> 
                                - Stok: <?php echo $bahan['stok']; ?> 
                                - Buffer: <?php echo $bahan['buffer_stock']; ?>
                                - ROP: <?php echo $bahan['rop']; ?>
                                <?php echo $warning; ?>
                            </option>
                            <?php endwhile; 
                            $result_bahan->data_seek(0); // Reset pointer
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Supplier <span class="text-danger">*</span></label>
                        <select class="form-select" name="supplier_id" required>
                            <option value="">Pilih Supplier</option>
                            <?php while ($supplier = $result_supplier->fetch_assoc()): ?>
                            <option value="<?php echo $supplier['id']; ?>">
                                <?php echo htmlspecialchars($supplier['nama_supplier']); ?>
                            </option>
                            <?php endwhile; 
                            $result_supplier->data_seek(0); // Reset pointer
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jumlah <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="jumlah" id="jumlahPesan" required min="1">
                        <small class="text-muted">Jumlah yang disarankan: <span id="rekomendasiJumlah">-</span></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Harga Satuan <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="harga_satuan" required min="1">
                    </div>
                    <div class="alert alert-info">
                        <strong>Sistem Buffer Stock:</strong> Pesanan otomatis dibuat ketika stok mencapai ROP (Reorder Point)
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" name="tambah" class="btn btn-primary">Simpan Pesanan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Script untuk rekomendasi jumlah pesanan berdasarkan buffer stock
document.getElementById('bahanSelect').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const stok = parseFloat(selectedOption.getAttribute('data-stok')) || 0;
    const buffer = parseFloat(selectedOption.getAttribute('data-buffer')) || 0;
    const rop = parseFloat(selectedOption.getAttribute('data-rop')) || 0;
    
    const jumlahInput = document.getElementById('jumlahPesan');
    const rekomendasiSpan = document.getElementById('rekomendasiJumlah');
    
    if (buffer > 0) {
        // Hitung jumlah yang dibutuhkan untuk mencapai buffer stock
        const rekomendasi = Math.max(buffer - stok, rop - stok, 1);
        jumlahInput.value = rekomendasi;
        rekomendasiSpan.textContent = rekomendasi + ' (sesuai buffer stock)';
        
        // Jika stok di bawah ROP, tambahkan warning
        if (stok <= rop) {
            rekomendasiSpan.innerHTML += ' <span class="text-danger">(STOK KRITIS!)</span>';
        } else if (stok <= buffer) {
            rekomendasiSpan.innerHTML += ' <span class="text-warning">(Perlu Restock)</span>';
        }
    } else {
        jumlahInput.value = '';
        rekomendasiSpan.textContent = '-';
    }
});
</script>

<?php require_once 'footer.php'; ?>