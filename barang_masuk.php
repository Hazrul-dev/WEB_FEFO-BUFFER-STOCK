<?php
$title = "Barang Masuk";
$page_title = "Manajemen Barang Masuk";
$show_print_button = true;
require_once 'header.php';

// Inisialisasi variabel result
$result = false;
$result_pesanan = false;
$result_bahan = false;
$result_supplier = false;
$error = null;

// Tambah barang masuk
if (isset($_POST['tambah'])) {
    $tanggal = sanitize($_POST['tanggal']);
    $pesanan_id = isset($_POST['pesanan_id']) ? sanitize($_POST['pesanan_id']) : null;
    $bahan_id = sanitize($_POST['bahan_id']);
    $no_faktur = sanitize($_POST['no_faktur']);
    $supplier_id = sanitize($_POST['supplier_id']);
    $tanggal_expired = sanitize($_POST['tanggal_expired']);
    $kuantitas = sanitize($_POST['kuantitas']);
    $harga = sanitize($_POST['harga']);
    $user_id = $_SESSION['user_id'];
    
    // Mulai transaksi
    $conn->begin_transaction();
    
    try {
        // Insert ke tabel barang_masuk
        $sql = "INSERT INTO barang_masuk (bahan_id, supplier_id, kuantitas, harga_satuan, total_harga, tanggal_masuk, tanggal_expired, catatan, user_id, pesanan_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $total_harga = $kuantitas * $harga;
        $catatan = isset($_POST['catatan']) ? sanitize($_POST['catatan']) : null;
        $stmt->bind_param("iiiddsssii", $bahan_id, $supplier_id, $kuantitas, $harga, $total_harga, $tanggal, $tanggal_expired, $catatan, $user_id, $pesanan_id);
        $stmt->execute();
        
        // Update stok di tabel bahan_baku
        $sql_update = "UPDATE bahan_baku SET stok = stok + ? WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("ii", $kuantitas, $bahan_id);
        $stmt_update->execute();
        
        // Insert ke kartu stok
        $sql_kartu = "INSERT INTO kartu_stok (bahan_id, tanggal, stok_awal, masuk, stok_akhir, keterangan) 
                      SELECT ?, ?, stok, ?, stok + ?, CONCAT('Barang masuk - ', ?)
                      FROM bahan_baku WHERE id = ?";
        $stmt_kartu = $conn->prepare($sql_kartu);
        $stmt_kartu->bind_param("isiisi", $bahan_id, $tanggal, $kuantitas, $kuantitas, $no_faktur, $bahan_id);
        $stmt_kartu->execute();
        
        // Jika ada pesanan_id, update status pesanan menjadi "Selesai"
        if ($pesanan_id) {
            $sql_pesanan = "UPDATE pesanan SET status = 'Selesai' WHERE id = ?";
            $stmt_pesanan = $conn->prepare($sql_pesanan);
            $stmt_pesanan->bind_param("i", $pesanan_id);
            $stmt_pesanan->execute();
        }
        
        // Commit transaksi
        $conn->commit();
        
        $_SESSION['success'] = "Barang masuk berhasil dicatat!";
        header("Location: barang_masuk.php");
        exit();
    } catch (Exception $e) {
        // Rollback transaksi jika ada error
        $conn->rollback();
        $error = "Gagal mencatat barang masuk: " . $e->getMessage();
    }
}

// Hapus barang masuk
if (isset($_GET['hapus'])) {
    $id = sanitize($_GET['hapus']);
    
    // Mulai transaksi
    $conn->begin_transaction();
    
    try {
        // Dapatkan data barang masuk yang akan dihapus
        $sql_select = "SELECT bahan_id, kuantitas, pesanan_id FROM barang_masuk WHERE id = ?";
        $stmt_select = $conn->prepare($sql_select);
        $stmt_select->bind_param("i", $id);
        $stmt_select->execute();
        $result_select = $stmt_select->get_result();
        
        if ($result_select->num_rows === 0) {
            throw new Exception("Data barang masuk tidak ditemukan");
        }
        
        $barang_masuk = $result_select->fetch_assoc();
        
        // Update stok bahan baku
        $sql_update = "UPDATE bahan_baku SET stok = stok - ? WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("ii", $barang_masuk['kuantitas'], $barang_masuk['bahan_id']);
        $stmt_update->execute();
        
        // Hapus dari kartu stok
        $sql_delete_kartu = "DELETE FROM kartu_stok WHERE keterangan LIKE ? AND bahan_id = ?";
        $stmt_delete_kartu = $conn->prepare($sql_delete_kartu);
        $keterangan = '%Barang masuk -%';
        $stmt_delete_kartu->bind_param("si", $keterangan, $barang_masuk['bahan_id']);
        $stmt_delete_kartu->execute();
        
        // Jika ada pesanan_id, kembalikan status ke "Diterima"
        if ($barang_masuk['pesanan_id']) {
            $sql_pesanan = "UPDATE pesanan SET status = 'Diterima' WHERE id = ?";
            $stmt_pesanan = $conn->prepare($sql_pesanan);
            $stmt_pesanan->bind_param("i", $barang_masuk['pesanan_id']);
            $stmt_pesanan->execute();
        }
        
        // Hapus barang masuk
        $sql_delete = "DELETE FROM barang_masuk WHERE id = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bind_param("i", $id);
        $stmt_delete->execute();
        
        // Commit transaksi
        $conn->commit();
        
        $_SESSION['success'] = "Barang masuk berhasil dihapus!";
        header("Location: barang_masuk.php");
        exit();
    } catch (Exception $e) {
        // Rollback transaksi jika ada error
        $conn->rollback();
        $_SESSION['error'] = "Gagal menghapus barang masuk: " . $e->getMessage();
        header("Location: barang_masuk.php");
        exit();
    }
}

// Edit barang masuk
if (isset($_POST['edit'])) {
    $id = sanitize($_POST['id']);
    $tanggal = sanitize($_POST['tanggal']);
    $bahan_id = sanitize($_POST['bahan_id']);
    $no_faktur = sanitize($_POST['no_faktur']);
    $supplier_id = sanitize($_POST['supplier_id']);
    $tanggal_expired = sanitize($_POST['tanggal_expired']);
    $kuantitas = sanitize($_POST['kuantitas']);
    $harga = sanitize($_POST['harga']);
    
    // Mulai transaksi
    $conn->begin_transaction();
    
    try {
        // Dapatkan data lama
        $sql_select = "SELECT bahan_id, kuantitas as kuantitas_lama FROM barang_masuk WHERE id = ?";
        $stmt_select = $conn->prepare($sql_select);
        $stmt_select->bind_param("i", $id);
        $stmt_select->execute();
        $result_select = $stmt_select->get_result();
        
        if ($result_select->num_rows === 0) {
            throw new Exception("Data barang masuk tidak ditemukan");
        }
        
        $data_lama = $result_select->fetch_assoc();
        
        // Update barang masuk
        $sql = "UPDATE barang_masuk SET 
                tanggal_masuk = ?, 
                bahan_id = ?, 
                no_faktur = ?, 
                supplier_id = ?, 
                tanggal_expired = ?, 
                kuantitas = ?, 
                harga_satuan = ?,
                total_harga = ?
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $total_harga = $kuantitas * $harga;
        $stmt->bind_param("sisssidii", $tanggal, $bahan_id, $no_faktur, $supplier_id, $tanggal_expired, $kuantitas, $harga, $total_harga, $id);
        $stmt->execute();
        
        // Hitung selisih kuantitas
        $selisih = $kuantitas - $data_lama['kuantitas_lama'];
        
        // Update stok bahan baku jika ada perubahan
        if ($selisih != 0) {
            $sql_update = "UPDATE bahan_baku SET stok = stok + ? WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("ii", $selisih, $bahan_id);
            $stmt_update->execute();
            
            // Update kartu stok
            $sql_kartu = "UPDATE kartu_stok 
                          SET stok_akhir = stok_akhir + ?, 
                              masuk = CASE WHEN masuk > 0 THEN masuk + ? ELSE masuk END,
                              keluar = CASE WHEN keluar > 0 THEN keluar - ? ELSE keluar END
                          WHERE bahan_id = ? AND keterangan LIKE ?";
            $stmt_kartu = $conn->prepare($sql_kartu);
            $keterangan = '%Barang masuk - ' . $no_faktur . '%';
            $stmt_kartu->bind_param("iiiis", $selisih, $selisih, $selisih, $bahan_id, $keterangan);
            $stmt_kartu->execute();
        }
        
        // Commit transaksi
        $conn->commit();
        
        $_SESSION['success'] = "Barang masuk berhasil diperbarui!";
        header("Location: barang_masuk.php");
        exit();
    } catch (Exception $e) {
        // Rollback transaksi jika ada error
        $conn->rollback();
        $error = "Gagal memperbarui barang masuk: " . $e->getMessage();
    }
}   

// Ambil data pesanan yang sudah diterima tapi belum selesai
try {
    $sql_pesanan = "SELECT p.id, p.bahan_id, p.supplier_id, p.jumlah, p.harga_satuan, 
                    b.nama_bahan, s.nama_supplier 
                    FROM pesanan p
                    JOIN bahan_baku b ON p.bahan_id = b.id
                    JOIN suppliers s ON p.supplier_id = s.id
                    WHERE p.status = 'Diterima' AND NOT EXISTS (
                        SELECT 1 FROM barang_masuk WHERE pesanan_id = p.id
                    )";
    $result_pesanan = $conn->query($sql_pesanan);
    if (!$result_pesanan) {
        throw new Exception("Error mengambil data pesanan: " . $conn->error);
    }
} catch (Exception $e) {
    $error = $e->getMessage();
    $result_pesanan = false;
}

// Ambil data barang masuk
try {
    $sql = "SELECT bm.*, bb.nama_bahan, s.nama_supplier, u.nama as nama_user 
            FROM barang_masuk bm
            JOIN bahan_baku bb ON bm.bahan_id = bb.id
            JOIN suppliers s ON bm.supplier_id = s.id
            JOIN users u ON bm.user_id = u.id
            ORDER BY bm.tanggal_masuk DESC";
    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception("Error mengambil data barang masuk: " . $conn->error);
    }
} catch (Exception $e) {
    $error = $e->getMessage();
    $result = false;
}

// Ambil data bahan baku untuk dropdown
try {
    $sql_bahan = "SELECT * FROM bahan_baku ORDER BY nama_bahan";
    $result_bahan = $conn->query($sql_bahan);
    if (!$result_bahan) {
        throw new Exception("Error mengambil data bahan baku: " . $conn->error);
    }
} catch (Exception $e) {
    $error = $e->getMessage();
    $result_bahan = false;
}

// Ambil data supplier untuk dropdown
try {
    $sql_supplier = "SELECT * FROM suppliers ORDER BY nama_supplier";
    $result_supplier = $conn->query($sql_supplier);
    if (!$result_supplier) {
        throw new Exception("Error mengambil data supplier: " . $conn->error);
    }
} catch (Exception $e) {
    $error = $e->getMessage();
    $result_supplier = false;
}

// Bersihkan output buffer sebelum redirect
if (isset($_SESSION['success'])) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    header("Location: barang_masuk.php");
    exit();
}
?>

<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Daftar Barang Masuk</h5>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#tambahModal">
                <i class="fas fa-plus"></i> Tambah Barang Masuk
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
                        <th>Tanggal</th>
                        <th>No Faktur</th>
                        <th>Bahan Baku</th>
                        <th>Supplier</th>
                        <th>Tanggal Expired</th>
                        <th>Qty</th>
                        <th>Harga</th>
                        <th>Total</th>
                        <th>Input Oleh</th>
                        <th class="no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result !== false && $result->num_rows > 0): 
                        $no = 1;
                        while ($row = $result->fetch_assoc()): 
                            $expired_class = '';
                            $today = new DateTime();
                            $expired_date = new DateTime($row['tanggal_expired']);
                            $diff = $today->diff($expired_date);
                            
                            if ($expired_date < $today) {
                                $expired_class = 'bg-danger text-white';
                            } elseif ($diff->days <= 7) {
                                $expired_class = 'bg-warning';
                            }
                    ?>
                    <tr class="<?php echo $expired_class; ?>">
                        <td><?php echo $no++; ?></td>
                        <td><?php echo date('d M Y', strtotime($row['tanggal_masuk'])); ?></td>
                        <td><?php echo htmlspecialchars($row['no_faktur'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($row['nama_bahan'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($row['nama_supplier'] ?? '-'); ?></td>
                        <td><?php echo date('d M Y', strtotime($row['tanggal_expired'])); ?></td>
                        <td><?php echo $row['kuantitas'] ?? '0'; ?></td>
                        <td><?php echo isset($row['harga_satuan']) ? number_format($row['harga_satuan'], 0, ',', '.') : '0'; ?></td>
                        <td><?php echo isset($row['total_harga']) ? number_format($row['total_harga'], 0, ',', '.') : '0'; ?></td>
                        <td><?php echo htmlspecialchars($row['nama_user'] ?? '-'); ?></td>
                        <td class="no-print">
                            <a href="cetak_label.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info" target="_blank">
                                <i class="fas fa-tag"></i> Label FEFO
                            </a>
                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $row['id']; ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="barang_masuk.php?hapus=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus barang masuk ini?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>

                    <!-- Modal Edit -->
                    <div class="modal fade" id="editModal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit Barang Masuk</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="POST" action="">
                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Tanggal</label>
                                            <input type="date" class="form-control" name="tanggal" value="<?php echo date('Y-m-d', strtotime($row['tanggal_masuk'])); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Bahan Baku</label>
                                            <select class="form-select" name="bahan_id" required>
                                                <?php 
                                                if ($result_bahan !== false) {
                                                    $result_bahan->data_seek(0);
                                                    while ($bahan = $result_bahan->fetch_assoc()): 
                                                        $selected = $bahan['id'] == $row['bahan_id'] ? 'selected' : '';
                                                ?>
                                                <option value="<?php echo $bahan['id']; ?>" <?php echo $selected; ?>>
                                                    <?php echo htmlspecialchars($bahan['nama_bahan']); ?> (Stok: <?php echo $bahan['stok']; ?>)
                                                </option>
                                                <?php endwhile;
                                                } ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">No Faktur</label>
                                            <input type="text" class="form-control" name="no_faktur" value="<?php echo htmlspecialchars($row['no_faktur']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Supplier</label>
                                            <select class="form-select" name="supplier_id" required>
                                                <?php 
                                                if ($result_supplier !== false) {
                                                    $result_supplier->data_seek(0);
                                                    while ($supplier = $result_supplier->fetch_assoc()): 
                                                        $selected = $supplier['id'] == $row['supplier_id'] ? 'selected' : '';
                                                ?>
                                                <option value="<?php echo $supplier['id']; ?>" <?php echo $selected; ?>>
                                                    <?php echo htmlspecialchars($supplier['nama_supplier']); ?>
                                                </option>
                                                <?php endwhile;
                                                } ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Tanggal Expired</label>
                                            <input type="date" class="form-control" name="tanggal_expired" value="<?php echo date('Y-m-d', strtotime($row['tanggal_expired'])); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Kuantitas</label>
                                            <input type="number" class="form-control" name="kuantitas" value="<?php echo $row['kuantitas']; ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Harga Satuan</label>
                                            <input type="number" class="form-control" name="harga" value="<?php echo $row['harga_satuan']; ?>" required>
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
                    <?php elseif ($result === false): ?>
                    <tr>
                        <td colspan="11" class="text-center text-danger">Terjadi kesalahan saat mengambil data</td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <td colspan="11" class="text-center">Tidak ada data barang masuk</td>
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
                <h5 class="modal-title">Tambah Barang Masuk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Pesanan (Opsional)</label>
                        <select class="form-select" name="pesanan_id" id="pesananSelect">
                            <option value="">Pilih Pesanan (Jika ada)</option>
                            <?php if ($result_pesanan !== false && $result_pesanan->num_rows > 0): 
                                while ($pesanan = $result_pesanan->fetch_assoc()): ?>
                            <option value="<?php echo $pesanan['id']; ?>" 
                                    data-bahan="<?php echo $pesanan['bahan_id']; ?>"
                                    data-supplier="<?php echo $pesanan['supplier_id']; ?>"
                                    data-jumlah="<?php echo $pesanan['jumlah']; ?>"
                                    data-harga="<?php echo $pesanan['harga_satuan']; ?>">
                                <?php echo htmlspecialchars($pesanan['nama_bahan']); ?> - <?php echo htmlspecialchars($pesanan['nama_supplier']); ?> (<?php echo $pesanan['jumlah']; ?>)
                            </option>
                            <?php endwhile; 
                            $result_pesanan->data_seek(0);
                            endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tanggal</label>
                        <input type="date" class="form-control" name="tanggal" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Bahan Baku</label>
                        <select class="form-select" name="bahan_id" id="bahanSelect" required>
                            <?php if ($result_bahan !== false && $result_bahan->num_rows > 0): 
                                while ($bahan = $result_bahan->fetch_assoc()): ?>
                            <option value="<?php echo $bahan['id']; ?>">
                                <?php echo htmlspecialchars($bahan['nama_bahan']); ?> (Stok: <?php echo $bahan['stok']; ?>)
                            </option>
                            <?php endwhile; 
                            $result_bahan->data_seek(0);
                            else: ?>
                            <option value="" disabled>Tidak ada data bahan baku</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">No Faktur</label>
                        <input type="text" class="form-control" name="no_faktur" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Supplier</label>
                        <select class="form-select" name="supplier_id" id="supplierSelect" required>
                            <?php if ($result_supplier !== false && $result_supplier->num_rows > 0): 
                                while ($supplier = $result_supplier->fetch_assoc()): ?>
                            <option value="<?php echo $supplier['id']; ?>"><?php echo htmlspecialchars($supplier['nama_supplier']); ?></option>
                            <?php endwhile; 
                            $result_supplier->data_seek(0);
                            else: ?>
                            <option value="" disabled>Tidak ada data supplier</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tanggal Expired</label>
                        <input type="date" class="form-control" name="tanggal_expired" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kuantitas</label>
                        <input type="number" class="form-control" name="kuantitas" id="kuantitasInput" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Harga Satuan</label>
                        <input type="number" class="form-control" name="harga" id="hargaInput" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Catatan (Opsional)</label>
                        <textarea class="form-control" name="catatan" placeholder="Masukkan catatan tambahan"></textarea>
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

<script>
// Update form fields when pesanan is selected
document.getElementById('pesananSelect').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    if (selectedOption.value) {
        document.getElementById('bahanSelect').value = selectedOption.getAttribute('data-bahan');
        document.getElementById('supplierSelect').value = selectedOption.getAttribute('data-supplier');
        document.getElementById('kuantitasInput').value = selectedOption.getAttribute('data-jumlah');
        document.getElementById('hargaInput').value = selectedOption.getAttribute('data-harga');
    }
});
</script>

<?php require_once 'footer.php'; ?>