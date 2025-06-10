<?php
$title = "Profile";
$page_title = "Profile Pengguna";
require_once 'header.php';

// Ambil data user
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Update profile
if (isset($_POST['update'])) {
    $nama = sanitize($_POST['nama']);
    $email = sanitize($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $error = '';
    
    // Validasi password jika diisi
    if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
        if (!password_verify($current_password, $user['password'])) {
            $error = "Password saat ini salah!";
        } elseif ($new_password != $confirm_password) {
            $error = "Password baru dan konfirmasi password tidak cocok!";
        } elseif (strlen($new_password) < 6) {
            $error = "Password baru minimal 6 karakter!";
        }
    }
    
    if (empty($error)) {
        // Jika password diubah
        if (!empty($new_password)) {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET nama = ?, email = ?, password = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $nama, $email, $password_hash, $_SESSION['user_id']);
        } else {
            $sql = "UPDATE users SET nama = ?, email = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $nama, $email, $_SESSION['user_id']);
        }
        
        if ($stmt->execute()) {
            $_SESSION['nama'] = $nama;
            $_SESSION['success'] = "Profile berhasil diperbarui!";
            exit();
        } else {
            $error = "Gagal memperbarui profile: " . $conn->error;
        }
    }
}
?>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Informasi Profile</h5>
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
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" value="<?php echo $user['username']; ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" name="nama" value="<?php echo $user['nama']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" value="<?php echo $user['email']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jabatan</label>
                        <input type="text" class="form-control" value="<?php echo $user['jabatan']; ?>" readonly>
                    </div>
                    
                    <hr>
                    <h6>Ubah Password</h6>
                    <div class="mb-3">
                        <label class="form-label">Password Saat Ini</label>
                        <input type="password" class="form-control" name="current_password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password Baru</label>
                        <input type="password" class="form-control" name="new_password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Konfirmasi Password Baru</label>
                        <input type="password" class="form-control" name="confirm_password">
                    </div>
                    
                    <button type="submit" name="update" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Foto Profile</h5>
            </div>
            <div class="card-body text-center">
                <?php if (!empty($user['foto'])): ?>
                <img src="uploads/<?php echo $user['foto']; ?>" class="img-thumbnail mb-3" style="max-width: 200px;">
                <?php else: ?>
                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 200px; height: 200px; margin: 0 auto;">
                    <i class="fas fa-user fa-5x text-secondary"></i>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="upload_foto.php" enctype="multipart/form-data">
                    <div class="mb-3">
                        <input type="file" class="form-control" name="foto" accept="image/*">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Upload Foto
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>