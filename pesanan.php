<?php
$title = "Pesanan Bahan Baku";
$page_title = "Manajemen Pemesanan Bahan Baku (Buffer Stock & FEFO)";
$show_print_button = true;
require_once 'header.php';

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
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiidii", $bahan_id, $supplier_id, $jumlah, $harga_satuan, $total_harga, $_SESSION['user_id']);
        $stmt->execute();
        $pesanan_id = $conn->insert_id;
        
        // 2. Kirim notifikasi ke supplier
        $sql_bahan = "SELECT nama_bahan FROM bahan_baku WHERE id = ?";
        $stmt_bahan = $conn->prepare($sql_bahan);
        $stmt_bahan->bind_param("i", $bahan_id);
        $stmt_bahan->execute();
        $bahan = $stmt_bahan->get_result()->fetch_assoc();
        
        $message = "Pesanan baru #$pesanan_id: " . $bahan['nama_bahan'] . " sebanyak $jumlah kg. Harap konfirmasi pengiriman.";
        
        $sql_notif = "INSERT INTO notifications (user_id, message, is_read, created_at)
                      SELECT u.id, ?, 0, NOW() 
                      FROM users u 
                      WHERE u.supplier_id = ? AND u.jabatan = 'Supplier'";
        $stmt_notif = $conn->prepare($sql_notif);
        $stmt_notif->bind_param("si", $message, $supplier_id);
        $stmt_notif->execute();
        
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
    header("Location: pesanan.php");
    exit();
}

// Supplier updates status to "Dikirim"
if (isset($_POST['update_status']) && $_SESSION['jabatan'] == 'Supplier') {
    $id = sanitize($_POST['id']);
    $catatan = sanitize($_POST['catatan']);
    $status = sanitize($_POST['status']); // Dikirim
    
    // Mulai transaksi
    $conn->begin_transaction();
    
    try {
        // 1. Update status pesanan
        $sql = "UPDATE pesanan 
                SET status = ?, 
                    catatan_pengiriman = ?, 
                    tanggal_pengiriman = NOW() 
                WHERE id = ? AND supplier_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssii", $status, $catatan, $id, $_SESSION['supplier_id']);
        $stmt->execute();
        
        // 2. Dapatkan detail pesanan untuk notifikasi
        $sql_pesanan = "SELECT p.*, b.nama_bahan, s.nama_supplier 
                        FROM pesanan p
                        JOIN bahan_baku b ON p.bahan_id = b.id
                        JOIN suppliers s ON p.supplier_id = s.id
                        WHERE p.id = ?";
        $stmt_pesanan = $conn->prepare($sql_pesanan);
        $stmt_pesanan->bind_param("i", $id);
        $stmt_pesanan->execute();
        $pesanan = $stmt_pesanan->get_result()->fetch_assoc();
        
        // 3. Kirim notifikasi ke admin
        $message = "Pesanan #$id (" . $pesanan['nama_bahan'] . " " . $pesanan['jumlah'] . "kg) telah dikirim oleh supplier " . $pesanan['nama_supplier'] . ". Catatan: $catatan";
        
        $sql_notif = "INSERT INTO notifications (user_id, message, is_read, created_at)
                      SELECT id, ?, 0, NOW() 
                      FROM users 
                      WHERE jabatan IN ('Admin', 'Manager')";
        $stmt_notif = $conn->prepare($sql_notif);
        $stmt_notif->bind_param("s", $message);
        $stmt_notif->execute();
        
        // Commit transaksi
        $conn->commit();
        
        $_SESSION['success'] = "Status pesanan berhasil diperbarui ke Dikirim dan notifikasi telah dikirim ke admin!";
        
        // Catat aktivitas
        $log_action = "Mengubah status pesanan ke Dikirim";
        logActivity($_SESSION['user_id'], 'UPDATE', 'pesanan', $id, $log_action);
        
    } catch (Exception $e) {
        // Rollback jika ada error
        $conn->rollback();
        $_SESSION['error'] = "Gagal memperbarui status pesanan: " . $e->getMessage();
    }
    
    header("Location: pesanan.php");
    exit();
}

// Admin confirms receipt in "Barang Masuk"
if (isset($_POST['terima_barang']) && ($_SESSION['jabatan'] == 'Admin' || $_SESSION['jabatan'] == 'Manager')) {
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
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        // 2. Get order details
        $sql_order = "SELECT p.*, b.nama_bahan, s.nama_supplier 
                     FROM pesanan p
                     JOIN bahan_baku b ON p.bahan_id = b.id
                     JOIN suppliers s ON p.supplier_id = s.id
                     WHERE p.id = ?";
        $stmt_order = $conn->prepare($sql_order);
        $stmt_order->bind_param("i", $id);
        $stmt_order->execute();
        $order = $stmt_order->get_result()->fetch_assoc();
        
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
        $stmt_masuk = $conn->prepare($sql_masuk);
        $stmt_masuk->bind_param("iiiddsii", 
            $order['bahan_id'], 
            $order['supplier_id'], 
            $order['jumlah'], 
            $order['harga_satuan'], 
            $order['total_harga'],
            $catatan,
            $_SESSION['user_id'],
            $id
        );
        $stmt_masuk->execute();
        
        // 4. Update stock
        $sql_stock = "UPDATE bahan_baku 
                     SET stok = stok + ?,
                         last_restock_date = NOW()
                     WHERE id = ?";
        $stmt_stock = $conn->prepare($sql_stock);
        $stmt_stock->bind_param("ii", $order['jumlah'], $order['bahan_id']);
        $stmt_stock->execute();
        
        // 5. Update buffer stock
        $sql_buffer = "UPDATE bahan_baku 
                      SET buffer_stock = GREATEST(rop, stok * 0.2) 
                      WHERE id = ?";
        $stmt_buffer = $conn->prepare($sql_buffer);
        $stmt_buffer->bind_param("i", $order['bahan_id']);
        $stmt_buffer->execute();
        
        // 6. Kirim notifikasi ke supplier
        $message = "Pesanan #$id (" . $order['nama_bahan'] . " " . $order['jumlah'] . "kg) telah diterima oleh admin.";
        
        $sql_notif = "INSERT INTO notifications (user_id, message, is_read, created_at)
                      SELECT u.id, ?, 0, NOW() 
                      FROM users u
                      WHERE u.supplier_id = ? AND u.jabatan = 'Supplier'";
        $stmt_notif = $conn->prepare($sql_notif);
        $stmt_notif->bind_param("si", $message, $order['supplier_id']);
        $stmt_notif->execute();
        
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
    
    header("Location: pesanan.php");
    exit();
}

// Admin cancels order
if (isset($_POST['batalkan_pesanan']) && ($_SESSION['jabatan'] == 'Admin' || $_SESSION['jabatan'] == 'Manager')) {
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
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $catatan, $id);
        $stmt->execute();
        
        // 2. Dapatkan detail pesanan untuk notifikasi
        $sql_pesanan = "SELECT p.*, b.nama_bahan, s.nama_supplier 
                        FROM pesanan p
                        JOIN bahan_baku b ON p.bahan_id = b.id
                        JOIN suppliers s ON p.supplier_id = s.id
                        WHERE p.id = ?";
        $stmt_pesanan = $conn->prepare($sql_pesanan);
        $stmt_pesanan->bind_param("i", $id);
        $stmt_pesanan->execute();
        $pesanan = $stmt_pesanan->get_result()->fetch_assoc();
        
        // 3. Kirim notifikasi ke supplier
        $message = "Pesanan #$id (" . $pesanan['nama_bahan'] . " " . $pesanan['jumlah'] . "kg) telah dibatalkan oleh admin. Alasan: $catatan";
        
        $sql_notif = "INSERT INTO notifications (user_id, message, is_read, created_at)
                      SELECT u.id, ?, 0, NOW() 
                      FROM users u
                      WHERE u.supplier_id = ? AND u.jabatan = 'Supplier'";
        $stmt_notif = $conn->prepare($sql_notif);
        $stmt_notif->bind_param("si", $message, $pesanan['supplier_id']);
        $stmt_notif->execute();
        
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
    exit();
}

// Admin completes order
if (isset($_POST['selesaikan_pesanan']) && ($_SESSION['jabatan'] == 'Admin' || $_SESSION['jabatan'] == 'Manager')) {
    $id = sanitize($_POST['id']);
    
    // Mulai transaksi
    $conn->begin_transaction();
    
    try {
        // 1. Update status pesanan
        $sql = "UPDATE pesanan 
                SET status = 'Selesai',
                    tanggal_selesai = NOW() 
                WHERE id = ? AND status = 'Diterima'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        // 2. Dapatkan detail pesanan untuk notifikasi
        $sql_pesanan = "SELECT p.*, b.nama_bahan, s.nama_supplier 
                        FROM pesanan p
                        JOIN bahan_baku b ON p.bahan_id = b.id
                        JOIN suppliers s ON p.supplier_id = s.id
                        WHERE p.id = ?";
        $stmt_pesanan = $conn->prepare($sql_pesanan);
        $stmt_pesanan->bind_param("i", $id);
        $stmt_pesanan->execute();
        $pesanan = $stmt_pesanan->get_result()->fetch_assoc();
        
        // 3. Kirim notifikasi ke supplier
        $message = "Pesanan #$id (" . $pesanan['nama_bahan'] . " " . $pesanan['jumlah'] . "kg) telah diselesaikan oleh admin.";
        
        $sql_notif = "INSERT INTO notifications (user_id, message, is_read, created_at)
                      SELECT u.id, ?, 0, NOW() 
                      FROM users u
                      WHERE u.supplier_id = ? AND u.jabatan = 'Supplier'";
        $stmt_notif = $conn->prepare($sql_notif);
        $stmt_notif->bind_param("si", $message, $pesanan['supplier_id']);
        $stmt_notif->execute();
        
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
    
    header("Location: pesanan.php");
    exit();
}

// Query data pesanan berdasarkan role
try {
    if ($_SESSION['jabatan'] == 'Supplier' && isset($_SESSION['supplier_id'])) {
        $supplier_id = $_SESSION['supplier_id'];
        
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
                WHERE p.supplier_id = ? AND p.status != 'Selesai'
                ORDER BY 
                    CASE 
                        WHEN p.status = 'Dipesan' THEN 1
                        WHEN p.status = 'Dikirim' THEN 2
                        ELSE 3
                    END,
                    p.tanggal_pesanan DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $supplier_id);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
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
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
    $result = false;
}

// Ambil data bahan baku untuk dropdown (hanya admin)
$result_bahan = false;
if ($_SESSION['jabatan'] == 'Admin' || $_SESSION['jabatan'] == 'Manager') {
    $sql_bahan = "SELECT *, 
                 IFNULL((stok <= buffer_stock), 0) as needs_restock,
                 IFNULL((stok <= rop), 0) as below_rop
                 FROM bahan_baku 
                 ORDER BY 
                    below_rop DESC,
                    needs_restock DESC,
                    nama_bahan";
    $result_bahan = $conn->query($sql_bahan);
}

// Ambil data supplier untuk dropdown (hanya admin)
$result_supplier = false;
if ($_SESSION['jabatan'] == 'Admin' || $_SESSION['jabatan'] == 'Manager') {
    $sql_supplier = "SELECT * FROM suppliers ORDER BY nama_supplier";
    $result_supplier = $conn->query($sql_supplier);
}

// Ambil notifikasi untuk user
$notifications = [];
if (isset($_SESSION['user_id'])) {
    $sql_notif = "SELECT * FROM notifications 
                 WHERE user_id = ? AND is_read = 0
                 ORDER BY created_at DESC
                 LIMIT 5";
    $stmt_notif = $conn->prepare($sql_notif);
    $stmt_notif->bind_param("i", $_SESSION['user_id']);
    $stmt_notif->execute();
    $notifications = $stmt_notif->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Daftar Pesanan Bahan Baku</h5>
            <?php if (($_SESSION['jabatan'] == 'Admin' || $_SESSION['jabatan'] == 'Manager') && $result_bahan !== false && $result_supplier !== false): ?>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#tambahModal">
                <i class="fas fa-plus"></i> Buat Pesanan
            </button>
            <?php endif; ?>
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
        
        <?php if (!empty($notifications) && ($_SESSION['jabatan'] == 'Admin' || $_SESSION['jabatan'] == 'Manager')): ?>
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
                            if (isset($row['stok']) && isset($row['rop']) && $row['stok'] <= $row['rop']) {
                                $stok_class = 'bg-danger text-white';
                            } elseif (isset($row['stok']) && isset($row['buffer_stock']) && $row['stok'] <= $row['buffer_stock']) {
                                $stok_class = 'bg-warning';
                            }
                    ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo date('d M Y H:i', strtotime($row['tanggal_pesanan'])); ?></td>
                        <td><?php echo htmlspecialchars($row['nama_bahan'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($row['nama_supplier'] ?? '-'); ?></td>
                        <td><?php echo $row['jumlah'] ?? '0'; ?></td>
                        <td><?php echo isset($row['harga_satuan']) ? number_format($row['harga_satuan'], 0, ',', '.') : '0'; ?></td>
                        <td><?php echo isset($row['total_harga']) ? number_format($row['total_harga'], 0, ',', '.') : '0'; ?></td>
                        <td>
                            <span class="badge bg-<?php 
                                echo ($row['status'] ?? '') == 'Dipesan' ? 'warning' : 
                                     (($row['status'] ?? '') == 'Dikirim' ? 'info' : 
                                     (($row['status'] ?? '') == 'Diterima' ? 'primary' : 
                                     (($row['status'] ?? '') == 'Dibatalkan' ? 'danger' : 'success'))); 
                            ?>">
                                <?php echo $row['status'] ?? 'Unknown'; ?>
                                <?php if (($row['status'] == 'Dikirim' || $row['status'] == 'Dibatalkan') && !empty($row['catatan_pengiriman'])): ?>
                                <i class="fas fa-info-circle ms-1" title="<?php echo htmlspecialchars($row['catatan_pengiriman']); ?>"></i>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($row['nama_user'] ?? '-'); ?></td>
                        <td class="<?php echo $stok_class; ?>">
                            <small>
                                Stok: <?php echo $row['stok'] ?? '0'; ?><br>
                                Buffer: <?php echo $row['buffer_stock'] ?? '0'; ?><br>
                                Exp: <?php echo isset($row['next_expired']) && $row['next_expired'] ? date('d/m/y', strtotime($row['next_expired'])) : '-'; ?>
                            </small>
                        </td>
                        <td class="no-print">
                            <?php 
                            $is_admin = ($_SESSION['jabatan'] == 'Admin' || $_SESSION['jabatan'] == 'Manager');
                            $is_supplier = ($_SESSION['jabatan'] == 'Supplier' && isset($_SESSION['supplier_id']) && $_SESSION['supplier_id'] == ($row['supplier_id'] ?? 0));
                            $current_status = $row['status'] ?? '';
                            
                            // Tombol untuk Supplier
                            if ($is_supplier && $current_status == 'Dipesan'): ?>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#statusModal<?php echo $row['id']; ?>">
                                    <i class="fas fa-truck"></i> Kirim
                                </button>
                            
                            <!-- Tombol untuk Admin -->
                            <?php elseif ($is_admin && $current_status == 'Dikirim'): ?>
                                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#statusModal<?php echo $row['id']; ?>">
                                    <i class="fas fa-check"></i> Terima
                                </button>
                            
                            <?php elseif ($is_admin && $current_status == 'Dipesan'): ?>
                                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#statusModal<?php echo $row['id']; ?>">
                                    <i class="fas fa-times"></i> Batalkan
                                </button>
                            
                            <?php elseif ($is_admin && $current_status == 'Diterima'): ?>
                                <button class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#statusModal<?php echo $row['id']; ?>">
                                    <i class="fas fa-check-circle"></i> Selesaikan
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- Modal untuk Supplier (Konfirmasi Pengiriman) -->
                    <?php if ($is_supplier && isset($row['id']) && $current_status == 'Dipesan'): ?>
                    <div class="modal fade" id="statusModal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Konfirmasi Pengiriman</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="POST" action="">
                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                    <input type="hidden" name="status" value="Dikirim">
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Status Saat Ini</label>
                                            <input type="text" class="form-control" value="<?php echo $row['status']; ?>" readonly>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Status Baru</label>
                                            <input type="text" class="form-control" value="Dikirim" readonly>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Catatan Pengiriman <span class="text-danger">*</span></label>
                                            <textarea class="form-control" name="catatan" placeholder="Masukkan detail pengiriman (nomor resi, kurir, estimasi tiba)" required></textarea>
                                            <small class="text-muted">Contoh: JNE REG 1234567890, estimasi tiba 2-3 hari</small>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                        <button type="submit" name="update_status" class="btn btn-primary">
                                            <i class="fas fa-check"></i> Konfirmasi Pengiriman
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Modal untuk Admin (Terima Barang) -->
                    <?php if ($is_admin && isset($row['id']) && $current_status == 'Dikirim'): ?>
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
                    <?php if ($is_admin && isset($row['id']) && $current_status == 'Dipesan'): ?>
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
                    <?php if ($is_admin && isset($row['id']) && $current_status == 'Diterima'): ?>
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

<?php if (($_SESSION['jabatan'] == 'Admin' || $_SESSION['jabatan'] == 'Manager') && $result_bahan !== false && $result_supplier !== false): ?>
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
                                if ($bahan['below_rop'] ?? 0) {
                                    $warning = ' (STOK KRITIS!)';
                                } elseif ($bahan['needs_restock'] ?? 0) {
                                    $warning = ' (Perlu Restock)';
                                }
                            ?>
                            <option value="<?php echo $bahan['id'] ?? ''; ?>" 
                                    data-stok="<?php echo $bahan['stok'] ?? 0; ?>" 
                                    data-buffer="<?php echo $bahan['buffer_stock'] ?? 0; ?>"
                                    data-rop="<?php echo $bahan['rop'] ?? 0; ?>">
                                <?php echo htmlspecialchars($bahan['nama_bahan'] ?? ''); ?> 
                                - Stok: <?php echo $bahan['stok'] ?? 0; ?> 
                                - Buffer: <?php echo $bahan['buffer_stock'] ?? 0; ?>
                                - ROP: <?php echo $bahan['rop'] ?? 0; ?>
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
                            <option value="<?php echo $supplier['id'] ?? ''; ?>">
                                <?php echo htmlspecialchars($supplier['nama_supplier'] ?? ''); ?>
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
<?php endif; ?>

<?php require_once 'footer.php'; ?>