<?php
// Aktifkan error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mulai output buffering dan session
ob_start();

require_once 'config.php';

$title = "Bahan Baku";
$page_title = "Manajemen Bahan Baku";
$show_print_button = true;

// Initialize variables
$error = '';
$bahan_data = []; // Array untuk menyimpan data bahan baku
$jenis_data = [];  // Array untuk menyimpan data jenis bahan

// Function to get all bahan baku
function getBahanBaku($conn) {
    $sql = "SELECT b.*, j.nama_jenis 
            FROM bahan_baku b
            JOIN jenis_bahan j ON b.jenis_id = j.id
            ORDER BY b.nama_bahan";
    $result = $conn->query($sql);
    $data = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

// Function to get jenis bahan
function getJenisBahan($conn) {
    $sql = "SELECT * FROM jenis_bahan ORDER BY nama_jenis";
    $result = $conn->query($sql);
    $data = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

// Process CRUD operations
processCRUDOperations();

// Now include header after all processing
require_once 'header.php';

// Display the main content
displayMainContent();

function processCRUDOperations() {
    global $conn, $error, $bahan_data, $jenis_data;
    
    // Tambah bahan baku
    if (isset($_POST['tambah'])) {
        $nama = sanitize($_POST['nama']);
        $jenis_id = sanitize($_POST['jenis_id']);
        $stok = sanitize($_POST['stok']);
        $satuan = sanitize($_POST['satuan']);
        $lead_time = sanitize($_POST['lead_time']);
        $safety_stock = sanitize($_POST['safety_stock']);
        $rop = sanitize($_POST['rop']);
        
        $sql = "INSERT INTO bahan_baku (nama_bahan, jenis_id, stok, satuan, lead_time, safety_stock, rop) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("siisiii", $nama, $jenis_id, $stok, $satuan, $lead_time, $safety_stock, $rop);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Bahan baku berhasil ditambahkan!";
            echo "<script>window.location.href='bahan_baku.php';</script>";
            exit();
        } else {
            $error = "Gagal menambahkan bahan baku: " . $conn->error;
        }
    }

    // Edit bahan baku
    if (isset($_POST['edit'])) {
        $id = sanitize($_POST['id']);
        $nama = sanitize($_POST['nama']);
        $jenis_id = sanitize($_POST['jenis_id']);
        $satuan = sanitize($_POST['satuan']);
        $lead_time = sanitize($_POST['lead_time']);
        $safety_stock = sanitize($_POST['safety_stock']);
        $rop = sanitize($_POST['rop']);
        
        $sql = "UPDATE bahan_baku SET 
                nama_bahan = ?, 
                jenis_id = ?, 
                satuan = ?, 
                lead_time = ?, 
                safety_stock = ?, 
                rop = ? 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sisiisi", $nama, $jenis_id, $satuan, $lead_time, $safety_stock, $rop, $id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Bahan baku berhasil diperbarui!";
            echo "<script>window.location.href='bahan_baku.php';</script>";
            exit();
        } else {
            $error = "Gagal memperbarui bahan baku: " . $conn->error;
        }
    }

    // Hapus bahan baku
    if (isset($_GET['hapus'])) {
        $id = sanitize($_GET['hapus']);
        
        // Cek apakah bahan baku digunakan di tabel lain
        $sql_check = "SELECT COUNT(*) as total FROM barang_masuk WHERE bahan_id = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("i", $id);
        
        if ($stmt_check->execute()) {
            $result_check = $stmt_check->get_result();
            $total = $result_check->fetch_assoc()['total'];
            
            if ($total > 0) {
                $_SESSION['error'] = "Bahan baku tidak dapat dihapus karena sudah digunakan dalam transaksi!";
            } else {
                $sql = "DELETE FROM bahan_baku WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Bahan baku berhasil dihapus!";
                } else {
                    $_SESSION['error'] = "Gagal menghapus bahan baku: " . $stmt->error;
                }
            }
        } else {
            $_SESSION['error'] = "Gagal memeriksa penggunaan bahan baku: " . $stmt_check->error;
        }
        
        echo "<script>window.location.href='bahan_baku.php';</script>";
        exit();
    }

    // Get data after processing CRUD operations
    $bahan_data = getBahanBaku($conn);
    $jenis_data = getJenisBahan($conn);
}

function displayMainContent() {
    global $error, $bahan_data, $jenis_data;
    ?>
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Daftar Bahan Baku</h5>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#tambahModal">
                    <i class="fas fa-plus"></i> Tambah Bahan
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
            
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Bahan</th>
                            <th>Jenis</th>
                            <th>Stok</th>
                            <th>Satuan</th>
                            <th>Lead Time</th>
                            <th>Safety Stock</th>
                            <th>ROP</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($bahan_data)): 
                            $no = 1;
                            foreach ($bahan_data as $row): 
                                $stok_warning = $row['stok'] < $row['safety_stock'] ? 'text-danger fw-bold' : '';
                        ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo htmlspecialchars($row['nama_bahan']); ?></td>
                            <td><?php echo htmlspecialchars($row['nama_jenis']); ?></td>
                            <td class="<?php echo $stok_warning; ?>"><?php echo $row['stok']; ?></td>
                            <td><?php echo htmlspecialchars($row['satuan']); ?></td>
                            <td><?php echo $row['lead_time']; ?> hari</td>
                            <td><?php echo $row['safety_stock']; ?></td>
                            <td><?php echo $row['rop']; ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $row['id']; ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="bahan_baku.php?hapus=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus bahan baku ini?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>

                        <!-- Modal Edit -->
                        <div class="modal fade" id="editModal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Bahan Baku</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form method="POST" action="">
                                        <div class="modal-body">
                                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                            <div class="mb-3">
                                                <label class="form-label">Nama Bahan</label>
                                                <input type="text" class="form-control" name="nama" value="<?php echo htmlspecialchars($row['nama_bahan']); ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Jenis Bahan</label>
                                                <select class="form-select" name="jenis_id" required>
                                                    <?php foreach ($jenis_data as $jenis): 
                                                        $selected = $jenis['id'] == $row['jenis_id'] ? 'selected' : '';
                                                    ?>
                                                        <option value="<?php echo $jenis['id']; ?>" <?php echo $selected; ?>>
                                                            <?php echo htmlspecialchars($jenis['nama_jenis']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Stok</label>
                                                <input type="number" class="form-control" name="stok" value="<?php echo $row['stok']; ?>" readonly>
                                                <small class="text-muted">Stok hanya bisa diubah melalui transaksi</small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Satuan</label>
                                                <input type="text" class="form-control" name="satuan" value="<?php echo htmlspecialchars($row['satuan']); ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Lead Time (hari)</label>
                                                <input type="number" class="form-control" name="lead_time" value="<?php echo $row['lead_time']; ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Safety Stock</label>
                                                <input type="number" class="form-control" name="safety_stock" value="<?php echo $row['safety_stock']; ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Reorder Point (ROP)</label>
                                                <input type="number" class="form-control" name="rop" value="<?php echo $row['rop']; ?>" required>
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
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center">Tidak ada data bahan baku</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Tambah -->
    <div class="modal fade" id="tambahModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Bahan Baku</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nama Bahan</label>
                            <input type="text" class="form-control" name="nama" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jenis Bahan</label>
                            <select class="form-select" name="jenis_id" required>
                                <?php foreach ($jenis_data as $jenis): ?>
                                <option value="<?php echo $jenis['id']; ?>"><?php echo htmlspecialchars($jenis['nama_jenis']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Stok Awal</label>
                            <input type="number" class="form-control" name="stok" value="0" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Satuan</label>
                            <input type="text" class="form-control" name="satuan" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Lead Time (hari)</label>
                            <input type="number" class="form-control" name="lead_time" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Safety Stock</label>
                            <input type="number" class="form-control" name="safety_stock" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reorder Point (ROP)</label>
                            <input type="number" class="form-control" name="rop" required>
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
    <?php
}

require_once 'footer.php';
?>