<?php
$title = "Data User";
$page_title = "Manajemen Data User";
require_once 'header.php';

// Hanya admin yang bisa mengakses
if ($_SESSION['jabatan'] != 'Admin') {
    header("Location: dashboard.php");
    exit();
}

// Tambah user
if (isset($_POST['tambah'])) {
    $username = sanitize($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $nama = sanitize($_POST['nama']);
    $jabatan = sanitize($_POST['jabatan']);
    $email = sanitize($_POST['email']);
    
    // Cek username sudah ada atau belum
    $sql_check = "SELECT id FROM users WHERE username = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("s", $username);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows > 0) {
        $error = "Username sudah digunakan!";
    } else {
        $sql = "INSERT INTO users (username, password, nama, jabatan, email) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $username, $password, $nama, $jabatan, $email);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "User berhasil ditambahkan!";
            exit();
        } else {
            $error = "Gagal menambahkan user: " . $conn->error;
        }
    }
    // Di bagian tambah user, tambahkan penanganan supplier_id:
    if ($jabatan == 'Supplier') {
    // Cari supplier berdasarkan nama atau buat baru
    $supplier_name = sanitize($_POST['supplier_name'] ?? '');
    if (!empty($supplier_name)) {
        // Cek apakah supplier sudah ada
        $sql = "SELECT id FROM suppliers WHERE nama_supplier = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $supplier_name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $supplier = $result->fetch_assoc();
            $supplier_id = $supplier['id'];
        } else {
            // Buat supplier baru
            $sql = "INSERT INTO suppliers (nama_supplier) VALUES (?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $supplier_name);
            $stmt->execute();
            $supplier_id = $conn->insert_id;
        }
    }
}

// Sesuaikan query insert:
$insert_sql = "INSERT INTO users (username, password, nama, jabatan, email, supplier_id) 
               VALUES (?, ?, ?, ?, ?, ?)";
$insert_stmt = $conn->prepare($insert_sql);
$insert_stmt->bind_param("sssssi", $username, $hashed_password, $nama, $jabatan, $email, $supplier_id);
}

// Edit user
if (isset($_POST['edit'])) {
    $id = sanitize($_POST['id']);
    $username = sanitize($_POST['username']);
    $nama = sanitize($_POST['nama']);
    $jabatan = sanitize($_POST['jabatan']);
    $email = sanitize($_POST['email']);
    
    // Jika password diisi, update password
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $sql = "UPDATE users SET 
                username = ?, 
                password = ?, 
                nama = ?, 
                jabatan = ?, 
                email = ? 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $username, $password, $nama, $jabatan, $email, $id);
    } else {
        $sql = "UPDATE users SET 
                username = ?, 
                nama = ?, 
                jabatan = ?, 
                email = ? 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", $username, $nama, $jabatan, $email, $id);
    }
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "User berhasil diperbarui!";
        header("Location: users.php");
        exit();
    } else {
        $error = "Gagal memperbarui user: " . $conn->error;
    }
}

// Hapus user
if (isset($_GET['hapus'])) {
    $id = sanitize($_GET['hapus']);
    
    // Tidak boleh hapus diri sendiri
    if ($id == $_SESSION['user_id']) {
        $_SESSION['error'] = "Anda tidak dapat menghapus akun sendiri!";
    } else {
        $sql = "DELETE FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "User berhasil dihapus!";
        } else {
            $_SESSION['error'] = "Gagal menghapus user: " . $conn->error;
        }
    }
    header("Location: users.php");
    exit();
}

// Ambil data users
$sql = "SELECT * FROM users ORDER BY jabatan, nama";
$result = $conn->query($sql);
?>

<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Daftar User</h5>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#tambahModal">
                <i class="fas fa-plus"></i> Tambah User
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
        
        <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Username</th>
                        <th>Nama</th>
                        <th>Jabatan</th>
                        <th>Email</th>
                        <th>Tanggal Dibuat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): 
                        $no = 1;
                        while ($row = $result->fetch_assoc()): 
                    ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo $row['username']; ?></td>
                        <td><?php echo $row['nama']; ?></td>
                        <td>
                            <span class="badge bg-primary">
                                <?php echo $row['jabatan']; ?>
                            </span>
                        </td>
                        <td><?php echo $row['email']; ?></td>
                        <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                        <td>
                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $row['id']; ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="users.php?hapus=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirmDelete()">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>

                    <!-- Modal Edit -->
                    <div class="modal fade" id="editModal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit User</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="POST" action="">
                                    <div class="modal-body">
                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Username</label>
                                            <input type="text" class="form-control" name="username" value="<?php echo $row['username']; ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Password (Kosongkan jika tidak diubah)</label>
                                            <input type="password" class="form-control" name="password">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Nama Lengkap</label>
                                            <input type="text" class="form-control" name="nama" value="<?php echo $row['nama']; ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Jabatan</label>
                                            <select class="form-select" name="jabatan" required>
                                                <option value="Admin">Admin</option>
                                                <option value="Manager">Manager</option>
                                                <option value="Staff">Staff</option>
                                                <option value="Supplier">Supplier</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" class="form-control" name="email" value="<?php echo $row['email']; ?>" required>
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
                        <td colspan="7" class="text-center">Tidak ada data user</td>
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
                <h5 class="modal-title">Tambah User Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" name="nama" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jabatan</label>
                        <select class="form-select" name="jabatan" required>
                            <option value="Admin">Admin</option>
                            <option value="Manager">Manager</option>
                            <option value="Staff">Staff</option>
                            <option value="Supplier">Supplier</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" required>
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

<?php require_once 'footer.php'; ?>