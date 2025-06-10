<?php
$title = "Barang Keluar";
$page_title = "Manajemen Barang Keluar";
$show_print_button = true;
require_once 'header.php';

// Tambah barang keluar
if (isset($_POST['tambah'])) {
    $tanggal = sanitize($_POST['tanggal']);
    $bahan_id = sanitize($_POST['bahan_id']);
    $no_faktur = sanitize($_POST['no_faktur']);
    $kuantitas = sanitize($_POST['kuantitas']);
    $satuan = sanitize($_POST['satuan']);
    $user_id = $_SESSION['user_id'];
    
    // Mulai transaksi
    $conn->begin_transaction();
    
    try {
        // Cek stok tersedia
        $sql_check = "SELECT stok FROM bahan_baku WHERE id = ? FOR UPDATE";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("i", $bahan_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $stok = $result_check->fetch_assoc()['stok'];
        
        if ($stok < $kuantitas) {
            throw new Exception("Stok tidak mencukupi! Stok tersedia: $stok");
        }
        
        // Insert ke tabel barang_keluar
        $sql = "INSERT INTO barang_keluar (tanggal, bahan_id, no_faktur, kuantitas, satuan, user_id) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sisisi", $tanggal, $bahan_id, $no_faktur, $kuantitas, $satuan, $user_id);
        $stmt->execute();
        
        // Update stok di tabel bahan_baku
        $sql_update = "UPDATE bahan_baku SET stok = stok - ? WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("ii", $kuantitas, $bahan_id);
        $stmt_update->execute();
        
        // Insert ke kartu stok
        $sql_kartu = "INSERT INTO kartu_stok (bahan_id, tanggal, stok_awal, keluar, stok_akhir, keterangan) 
                      SELECT ?, ?, stok, ?, stok - ?, CONCAT('Barang keluar - ', ?)
                      FROM bahan_baku WHERE id = ?";
        $stmt_kartu = $conn->prepare($sql_kartu);
        $stmt_kartu->bind_param("isiisi", $bahan_id, $tanggal, $kuantitas, $kuantitas, $no_faktur, $bahan_id);
        $stmt_kartu->execute();
        
        // Commit transaksi
        $conn->commit();
        
        $_SESSION['success'] = "Barang keluar berhasil dicatat!";
        exit();
    } catch (Exception $e) {
        // Rollback transaksi jika ada error
        $conn->rollback();
        $error = "Gagal mencatat barang keluar: " . $e->getMessage();
    }
}

// Ambil data barang keluar
$sql = "SELECT bk.*, bb.nama_bahan, u.nama as nama_user 
        FROM barang_keluar bk
        JOIN bahan_baku bb ON bk.bahan_id = bb.id
        JOIN users u ON bk.user_id = u.id
        ORDER BY bk.tanggal DESC";
$result = $conn->query($sql);

// Ambil data bahan baku untuk dropdown
$sql_bahan = "SELECT * FROM bahan_baku ORDER BY nama_bahan";
$result_bahan = $conn->query($sql_bahan);
?>

<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Daftar Barang Keluar</h5>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#tambahModal">
                <i class="fas fa-plus"></i> Tambah Barang Keluar
            </button>
        </div>
    </div>
    <div class="card-body">
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
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
                        <th>Qty</th>
                        <th>Satuan</th>
                        <th>Input Oleh</th>
                        <th class="no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): 
                        $no = 1;
                        while ($row = $result->fetch_assoc()): 
                    ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo date('d M Y', strtotime($row['tanggal'])); ?></td>
                        <td><?php echo $row['no_faktur']; ?></td>
                        <td><?php echo $row['nama_bahan']; ?></td>
                        <td><?php echo $row['kuantitas']; ?></td>
                        <td><?php echo $row['satuan']; ?></td>
                        <td><?php echo $row['nama_user']; ?></td>
                        <td class="no-print">
                            <a href="cetak_barang_keluar.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info" target="_blank">
                                <i class="fas fa-print"></i> Cetak
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center">Tidak ada data barang keluar</td>
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
                <h5 class="modal-title">Tambah Barang Keluar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tanggal</label>
                        <input type="date" class="form-control" name="tanggal" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Bahan Baku</label>
                        <select class="form-select" name="bahan_id" id="bahanSelect" required>
                            <option value="">Pilih Bahan Baku</option>
                            <?php while ($bahan = $result_bahan->fetch_assoc()): ?>
                            <option value="<?php echo $bahan['id']; ?>" 
                                    data-stok="<?php echo $bahan['stok']; ?>"
                                    data-satuan="<?php echo $bahan['satuan']; ?>">
                                <?php echo $bahan['nama_bahan']; ?> (Stok: <?php echo $bahan['stok']; ?>)
                            </option>
                            <?php endwhile; 
                            $result_bahan->data_seek(0); // Reset pointer
                            ?>
                        </select>
                        <small id="stokInfo" class="text-muted"></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">No Faktur</label>
                        <input type="text" class="form-control" name="no_faktur" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kuantitas</label>
                        <input type="number" class="form-control" name="kuantitas" id="kuantitasInput" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Satuan</label>
                        <input type="text" class="form-control" name="satuan" id="satuanInput" required>
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
    // Update info stok saat bahan baku dipilih
    document.getElementById('bahanSelect').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const stok = selectedOption.getAttribute('data-stok');
        const satuan = selectedOption.getAttribute('data-satuan');
        
        document.getElementById('stokInfo').textContent = `Stok tersedia: ${stok} ${satuan}`;
        document.getElementById('satuanInput').value = satuan;
        
        // Set max value untuk input kuantitas
        document.getElementById('kuantitasInput').max = stok;
    });
</script>

<?php require_once 'footer.php'; ?>