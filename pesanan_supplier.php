<?php
$title = "Pesanan Bahan Baku (Supplier)";
$page_title = "Daftar Pesanan - Supplier";
require_once 'header.php';

// Validasi role supplier
if ($_SESSION['jabatan'] != 'Supplier') {
    $_SESSION['error'] = "Akses ditolak. Hanya supplier yang dapat mengakses halaman ini.";
    exit();
}

// Pastikan supplier_id ada di session
if (!isset($_SESSION['supplier_id'])) {
    // Jika tidak ada, coba dapatkan dari tabel users
    $sql = "SELECT supplier_id FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $_SESSION['supplier_id'] = $user['supplier_id'];
    } else {
        $_SESSION['error'] = "Supplier ID tidak ditemukan. Hubungi administrator.";
        header("Location: dashboard.php");
        exit();
    }
}

$supplier_id = $_SESSION['supplier_id'];

// Debug info - bisa dihapus setelah testing
error_log("Supplier Access - User ID: ".$_SESSION['user_id'].", Supplier ID: $supplier_id");

// Supplier updates status to "Dikirim"
if (isset($_POST['update_status'])) {
    $id = sanitize($_POST['id']);
    $catatan = sanitize($_POST['catatan']);
    $status = sanitize($_POST['status']);
    
    $conn->begin_transaction();
    
    try {
        // 1. Update status pesanan
        $sql = "UPDATE pesanan 
                SET status = ?, 
                    catatan_pengiriman = ?, 
                    tanggal_pengiriman = NOW() 
                WHERE id = ? AND supplier_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssii", $status, $catatan, $id, $supplier_id);
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
        
        $conn->commit();
        
        $_SESSION['success'] = "Status pesanan berhasil diperbarui ke Dikirim dan notifikasi telah dikirim ke admin!";
        
        // Catat aktivitas
        $log_action = "Mengubah status pesanan ke Dikirim";
        logActivity($_SESSION['user_id'], 'UPDATE', 'pesanan', $id, $log_action);
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Gagal memperbarui status pesanan: " . $e->getMessage();
    }
    
    exit();
}

// Query data pesanan untuk supplier
try {
    $sql = "SELECT 
                p.*, 
                b.nama_bahan, 
                u.nama as nama_user,
                b.stok, 
                IFNULL(b.buffer_stock, 0) as buffer_stock, 
                IFNULL(b.rop, 0) as rop,
                DATE_FORMAT(p.tanggal_pesanan, '%d %b %Y %H:%i') as tgl_pesanan_format,
                s.nama_supplier
            FROM pesanan p
            JOIN bahan_baku b ON p.bahan_id = b.id
            JOIN users u ON p.user_id = u.id
            JOIN suppliers s ON p.supplier_id = s.id
            WHERE p.supplier_id = ? 
            AND p.status IN ('Dipesan', 'Dikirim', 'Diterima')
            ORDER BY 
                CASE p.status
                    WHEN 'Dipesan' THEN 1
                    WHEN 'Dikirim' THEN 2
                    WHEN 'Diterima' THEN 3
                    ELSE 4
                END,
                p.tanggal_pesanan DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $supplier_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Debug: Log jumlah hasil
    error_log("Jumlah pesanan ditemukan: " . $result->num_rows);
    
    if ($result->num_rows === 0) {
        error_log("Query result empty. Supplier ID: $supplier_id");
        // Untuk debugging, tampilkan semua supplier_id yang ada di pesanan
        $debug_sql = "SELECT DISTINCT supplier_id FROM pesanan";
        $debug_result = $conn->query($debug_sql);
        $debug_ids = [];
        while ($row = $debug_result->fetch_assoc()) {
            $debug_ids[] = $row['supplier_id'];
        }
        error_log("Supplier IDs in pesanan table: " . implode(", ", $debug_ids));
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
    $result = false;
}

// Ambil notifikasi untuk supplier
$notifications = [];
$sql_notif = "SELECT * FROM notifications 
             WHERE user_id = ? AND is_read = 0
             ORDER BY created_at DESC
             LIMIT 5";
$stmt_notif = $conn->prepare($sql_notif);
$stmt_notif->bind_param("i", $_SESSION['user_id']);
$stmt_notif->execute();
$notifications = $stmt_notif->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Daftar Pesanan untuk Supplier</h5>
        </div>
    </div>
    <div class="card-body">
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
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
                        <th>Jumlah</th>
                        <th>Harga Satuan</th>
                        <th>Total Harga</th>
                        <th>Status</th>
                        <th>Dipesan Oleh</th>
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
                        <td><?php echo $row['tgl_pesanan_format']; ?></td>
                        <td><?php echo htmlspecialchars($row['nama_bahan']); ?></td>
                        <td><?php echo $row['jumlah']; ?></td>
                        <td><?php echo number_format($row['harga_satuan'], 0, ',', '.'); ?></td>
                        <td><?php echo number_format($row['total_harga'], 0, ',', '.'); ?></td>
                        <td>
                            <span class="badge bg-<?php 
                                echo $row['status'] == 'Dipesan' ? 'warning' : 
                                     ($row['status'] == 'Dikirim' ? 'info' : 
                                     ($row['status'] == 'Diterima' ? 'primary' : 'secondary')); 
                            ?>">
                                <?php echo $row['status']; ?>
                                <?php if (!empty($row['catatan_pengiriman'])): ?>
                                <i class="fas fa-info-circle ms-1" title="<?php echo htmlspecialchars($row['catatan_pengiriman']); ?>"></i>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($row['nama_user']); ?></td>
                        <td class="no-print">
                            <?php if ($row['status'] == 'Dipesan'): ?>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#statusModal<?php echo $row['id']; ?>">
                                    <i class="fas fa-truck"></i> Kirim
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- Modal untuk Supplier (Konfirmasi Pengiriman) -->
                    <?php if ($row['status'] == 'Dipesan'): ?>
                    <div class="modal fade" id="statusModal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title">Konfirmasi Pengiriman</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
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

                    <?php endwhile; ?>
                    <?php elseif ($result === false): ?>
                    <tr>
                        <td colspan="9" class="text-center text-danger">Terjadi kesalahan saat mengambil data pesanan</td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center">Tidak ada data pesanan</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Dark Mode Toggle
document.addEventListener('DOMContentLoaded', function() {
    const darkModeToggle = document.getElementById('darkModeToggle');
    
    // Load saved preference
    if (localStorage.getItem('darkMode') === 'enabled') {
        document.body.classList.add('dark-mode');
        darkModeToggle.checked = true;
    }
    
    // Toggle dark mode
    darkModeToggle.addEventListener('change', function() {
        if (this.checked) {
            document.body.classList.add('dark-mode');
            localStorage.setItem('darkMode', 'enabled');
        } else {
            document.body.classList.remove('dark-mode');
            localStorage.setItem('darkMode', 'disabled');
        }
    });
    
    // Logout handler
    document.querySelector('.logout-link').addEventListener('click', function(e) {
        e.preventDefault();
        if (confirm('Apakah Anda yakin ingin logout?')) {
            window.location.href = 'logout.php';
        }
    });
});
</script>

<?php require_once 'footer.php'; ?>