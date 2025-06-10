<?php
$title = "Data Supplier";
$page_title = "Manajemen Data Supplier";
require_once 'header.php';

/// Cek akses berdasarkan role
if ($_SESSION['jabatan'] == 'Supplier') {
    // Jika supplier, hanya bisa melihat data sendiri
    if (!isset($_SESSION['supplier_id'])) {
        $_SESSION['error'] = "Akses supplier tidak valid";
        header("Location: dashboard.php");
        exit();
    }
    
    $supplier_id = $_SESSION['supplier_id'];
    $sql = "SELECT * FROM suppliers WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $supplier_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Disable semua aksi untuk supplier
    $supplier_mode = true;
} elseif ($_SESSION['jabatan'] == 'Admin') {
    // Admin bisa melihat semua dan melakukan semua aksi
    $sql = "SELECT * FROM suppliers ORDER BY nama_supplier";
    $result = $conn->query($sql);
    $supplier_mode = false;
} else {
    // Role lain tidak diizinkan
    $_SESSION['error'] = "Anda tidak memiliki akses ke halaman ini";
    header("Location: dashboard.php");
    exit();
}

// Tambah supplier (hanya admin)
if (isset($_POST['tambah']) && $_SESSION['jabatan'] == 'Admin') {
    $nama = sanitize($_POST['nama_supplier']);
    $kontak = sanitize($_POST['kontak']);
    $alamat = sanitize($_POST['alamat']);
    $provinsi = sanitize($_POST['provinsi']);
    $negara = sanitize($_POST['negara']);
    $kode_pos = sanitize($_POST['kode_pos']);
    
    $sql = "INSERT INTO suppliers (nama_supplier, kontak, alamat, provinsi, negara, kode_pos) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $nama, $kontak, $alamat, $provinsi, $negara, $kode_pos);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Supplier berhasil ditambahkan!";
        exit();
    } else {
        $error = "Gagal menambahkan supplier: " . $conn->error;
    }
}

// Edit supplier 
if (isset($_POST['edit'])) {
    $id = sanitize($_POST['id']);
    $nama = sanitize($_POST['nama_supplier']);
    $kontak = sanitize($_POST['kontak']);
    $alamat = sanitize($_POST['alamat']);
    $provinsi = sanitize($_POST['provinsi']);
    $negara = sanitize($_POST['negara']);
    $kode_pos = sanitize($_POST['kode_pos']);
    
    // Jika supplier, hanya bisa edit data sendiri
    if ($_SESSION['jabatan'] == 'Supplier' && $id != $_SESSION['supplier_id']) {
        $_SESSION['error'] = "Anda hanya bisa mengedit data supplier Anda sendiri";
        header("Location: suppliers.php");
        exit();
    }
    
    $sql = "UPDATE suppliers SET 
            nama_supplier = ?, 
            kontak = ?, 
            alamat = ?, 
            provinsi = ?, 
            negara = ?, 
            kode_pos = ? 
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssi", $nama, $kontak, $alamat, $provinsi, $negara, $kode_pos, $id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Supplier berhasil diperbarui!";
        header("Location: suppliers.php");
        exit();
    } else {
        $error = "Gagal memperbarui supplier: " . $conn->error;
    }
}

// Hapus supplier (hanya admin)
if (isset($_GET['hapus']) && $_SESSION['jabatan'] == 'Admin') {
    $id = sanitize($_GET['hapus']);
    
    // Cek apakah supplier digunakan di barang masuk
    $sql_check = "SELECT COUNT(*) as total FROM barang_masuk WHERE supplier_id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $total = $result_check->fetch_assoc()['total'];
    
    if ($total > 0) {
        $_SESSION['error'] = "Supplier tidak dapat dihapus karena sudah digunakan dalam transaksi!";
    } else {
        $sql = "DELETE FROM suppliers WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Supplier berhasil dihapus!";
        } else {
            $_SESSION['error'] = "Gagal menghapus supplier: " . $conn->error;
        }
    }
    exit();
}

// Ambil notifikasi stok menipis untuk supplier
$low_stock_notifications = [];
if ($_SESSION['jabatan'] == 'Supplier' && isset($_SESSION['supplier_id'])) {
    $supplier_id = $_SESSION['supplier_id'];
    
    $sql = "SELECT b.nama_bahan, k.sisa, k.tanggal_expired, b.stok_minimal
            FROM kartu_stok k
            JOIN bahan_baku b ON k.bahan_id = b.id
            JOIN barang_masuk m ON k.barang_masuk_id = m.id
            WHERE m.supplier_id = ? AND k.sisa < b.stok_minimal
            ORDER BY k.tanggal_expired";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $supplier_id);
    $stmt->execute();
    $low_stock_notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

?>

<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <?php echo ($supplier_mode) ? "Profil Supplier" : "Daftar Supplier"; ?>
            </h5>
            <?php if (!$supplier_mode): ?>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#tambahModal">
                <i class="fas fa-plus"></i> Tambah Supplier
            </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <?php if (!empty($low_stock_notifications)): ?>
        <div class="alert alert-warning mb-4">
            <h5><i class="fas fa-exclamation-triangle me-2"></i>Peringatan Stok Menipis</h5>
            <p>Berikut daftar bahan baku yang stoknya mulai menipis:</p>
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Nama Bahan</th>
                            <th>Stok Tersedia</th>
                            <th>Stok Minimal</th>
                            <th>Tanggal Expired</th>
                            <th>Hari Lagi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($low_stock_notifications as $notif): 
                            $expired_date = new DateTime($notif['tanggal_expired']);
                            $today = new DateTime();
                            $days_left = $today->diff($expired_date)->days;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($notif['nama_bahan']); ?></td>
                            <td class="text-danger fw-bold"><?php echo $notif['sisa']; ?></td>
                            <td><?php echo $notif['stok_minimal']; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($notif['tanggal_expired'])); ?></td>
                            <td><?php echo $days_left; ?> hari</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="mb-0">Segera lakukan pengisian ulang stok.</p>
        </div>
        <?php endif; ?>
        
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
        
        <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead class="table-primary">
                    <tr>
                        <th width="5%">#</th>
                        <th>Nama Supplier</th>
                        <th>Kontak</th>
                        <th>Alamat</th>
                        <th>Provinsi</th>
                        <?php if (!$supplier_mode): ?>
                        <th width="15%">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): 
                        $no = 1;
                        while ($row = $result->fetch_assoc()): 
                    ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo htmlspecialchars($row['nama_supplier']); ?></td>
                        <td><?php echo htmlspecialchars($row['kontak']); ?></td>
                        <td><?php echo htmlspecialchars($row['alamat']); ?></td>
                        <td><?php echo htmlspecialchars($row['provinsi']); ?></td>
                        <?php if (!$supplier_mode): ?>
                        <td>
                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $row['id']; ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="suppliers.php?hapus=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus supplier ini?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                        <?php endif; ?>
                    </tr>

                    <!-- Modal Edit -->
                    <div class="modal fade" id="editModal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit Supplier</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="POST" action="">
                                    <div class="modal-body">
                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Nama Supplier <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="nama_supplier" value="<?php echo htmlspecialchars($row['nama_supplier']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Kontak <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="kontak" value="<?php echo htmlspecialchars($row['kontak']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Alamat <span class="text-danger">*</span></label>
                                            <textarea class="form-control" name="alamat" rows="3" required><?php echo htmlspecialchars($row['alamat']); ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Provinsi <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="provinsi" value="<?php echo htmlspecialchars($row['provinsi']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Negara <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="negara" value="<?php echo htmlspecialchars($row['negara']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Kode Pos <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="kode_pos" value="<?php echo htmlspecialchars($row['kode_pos']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                        <button type="submit" name="edit" class="btn btn-primary">Simpan Perubahan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="<?php echo ($supplier_mode) ? '5' : '6'; ?>" class="text-center">Tidak ada data supplier</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah (hanya untuk admin) -->
<?php if (!$supplier_mode): ?>
<div class="modal fade" id="tambahModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Supplier Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Supplier <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_supplier" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kontak <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="kontak" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="alamat" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Provinsi <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="provinsi" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Negara <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="negara" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kode Pos <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="kode_pos" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" name="tambah" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Fungsi untuk refresh otomatis notifikasi stok setiap 5 menit
<?php if ($_SESSION['jabatan'] == 'Supplier'): ?>
setInterval(function() {
    fetch(window.location.href)
        .then(response => response.text())
        .then(data => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(data, 'text/html');
            const notificationDiv = doc.querySelector('.alert.alert-warning');
            
            if (notificationDiv) {
                document.querySelector('.alert.alert-warning').outerHTML = notificationDiv.outerHTML;
            } else {
                const existingNotif = document.querySelector('.alert.alert-warning');
                if (existingNotif) {
                    existingNotif.remove();
                }
            }
        });
}, 300000); // 5 menit
<?php endif; ?>
</script>

<?php require_once 'footer.php'; ?>