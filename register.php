<?php
session_start();
require_once 'config.php';

// Redirect jika sudah login
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';
$is_supplier_registration = isset($_GET['type']) && $_GET['type'] == 'supplier';

// Proses form pendaftaran
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Data umum
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $nama = sanitize($_POST['nama']);
    $email = sanitize($_POST['email']);
    
    // Data khusus supplier
    $jabatan = $is_supplier_registration ? 'Supplier' : sanitize($_POST['jabatan']);
    $nama_supplier = $is_supplier_registration ? sanitize($_POST['nama_supplier']) : null;
    $kontak = $is_supplier_registration ? sanitize($_POST['kontak']) : null;
    $alamat = $is_supplier_registration ? sanitize($_POST['alamat']) : null;
    $provinsi = $is_supplier_registration ? sanitize($_POST['provinsi']) : null;
    $negara = $is_supplier_registration ? sanitize($_POST['negara']) : null;
    $kode_pos = $is_supplier_registration ? sanitize($_POST['kode_pos']) : null;

    // Validasi data
    if (empty($username) || empty($password) || empty($nama) || empty($email)) {
        $error = "Semua bidang harus diisi";
    } elseif ($password != $confirm_password) {
        $error = "Password dan konfirmasi password tidak cocok";
    } elseif (strlen($password) < 6) {
        $error = "Password harus minimal 6 karakter";
    } elseif ($is_supplier_registration && (empty($nama_supplier) || empty($kontak) || empty($alamat))) {
        $error = "Semua informasi supplier harus diisi";
    } else {
        // Mulai transaksi database
        $conn->begin_transaction();

        try {
            // Jika mendaftarkan supplier, tambahkan data supplier terlebih dahulu
            $supplier_id = null;
            if ($is_supplier_registration) {
                $sql_supplier = "INSERT INTO suppliers (nama_supplier, kontak, alamat, provinsi, negara, kode_pos) 
                                VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_supplier = $conn->prepare($sql_supplier);
                $stmt_supplier->bind_param("ssssss", $nama_supplier, $kontak, $alamat, $provinsi, $negara, $kode_pos);
                
                if (!$stmt_supplier->execute()) {
                    throw new Exception("Gagal menambahkan supplier: " . $conn->error);
                }
                
                $supplier_id = $conn->insert_id;
            }

            // Cek ketersediaan username
            $check_sql = "SELECT id FROM users WHERE username = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $username);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                throw new Exception("Username sudah digunakan, silakan pilih username lain");
            }

            // Cek ketersediaan email
            $check_email_sql = "SELECT id FROM users WHERE email = ?";
            $check_email_stmt = $conn->prepare($check_email_sql);
            $check_email_stmt->bind_param("s", $email);
            $check_email_stmt->execute();
            $check_email_result = $check_email_stmt->get_result();
            
            if ($check_email_result->num_rows > 0) {
                throw new Exception("Email sudah digunakan, silakan gunakan email lain");
            }

            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            if ($is_supplier_registration) {
                $insert_sql = "INSERT INTO users (username, password, nama, jabatan, email, supplier_id) 
                              VALUES (?, ?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("sssssi", $username, $hashed_password, $nama, $jabatan, $email, $supplier_id);
            } else {
                $insert_sql = "INSERT INTO users (username, password, nama, jabatan, email) 
                              VALUES (?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("sssss", $username, $hashed_password, $nama, $jabatan, $email);
            }
            
            if (!$insert_stmt->execute()) {
                throw new Exception("Gagal membuat akun: " . $conn->error);
            }

            // Commit transaksi
            $conn->commit();
            
            // Redirect dengan pesan sukses
            $_SESSION['success'] = $is_supplier_registration 
                ? "Supplier dan akun berhasil didaftarkan!" 
                : "Akun berhasil dibuat! Silakan login.";
                
            header("Location: login.php");
            exit();
            
        } catch (Exception $e) {
            // Rollback jika ada error
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_supplier_registration ? 'Daftar Supplier' : 'Daftar Akun'; ?> - D'Fans Coffe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="images/BYU.jpg" type="image/x-icon">
    <link href="stylelogin.css" rel="stylesheet">
</head>
<body>
    <!-- Animated background -->
    <div class="floating-beans" id="floatingBeans"></div>
    
    <div class="container-fluid register-container">
        <div class="card login-card">
            <div class="card-header login-header">
                <div class="logo-container">
                    <img src="images/BYU.jpg" alt="D'Fans Coffe Logo" class="logo-img">
                </div>
                <h3>D'Fans Coffe</h3>
                <p class="mb-0"><?php echo $is_supplier_registration ? 'Pendaftaran Supplier Baru' : 'Pendaftaran Akun Baru'; ?></p>
            </div>
            <div class="card-body login-body">
                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="registerForm">
                    <h5 class="mb-3">Informasi Akun</h5>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nama" class="form-label">Nama Lengkap</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                            <input type="text" class="form-control" id="nama" name="nama" required>
                        </div>
                    </div>
                    
                    <?php if (!$is_supplier_registration): ?>
                    <div class="mb-3">
                        <label for="jabatan" class="form-label">Jabatan</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-briefcase"></i></span>
                            <select class="form-control" id="jabatan" name="jabatan" required>
                                <option value="">-- Pilih Jabatan --</option>
                                <option value="Staff">Staff</option>
                                <option value="Manager">Manager</option>
                                <option value="Admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group password-container">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <span class="password-toggle" onclick="togglePassword('password')">
                                <i class="fas fa-eye" id="toggleIcon1"></i>
                            </span>
                        </div>
                        <small class="text-muted">Minimal 6 karakter</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Konfirmasi Password</label>
                        <div class="input-group password-container">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            <span class="password-toggle" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye" id="toggleIcon2"></i>
                            </span>
                        </div>
                    </div>
                    
                    <?php if ($is_supplier_registration): ?>
                    <hr class="my-4">
                    <h5 class="mb-3">Informasi Supplier</h5>
                    
                    <div class="mb-3">
                        <label for="nama_supplier" class="form-label">Nama Supplier</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-truck"></i></span>
                            <input type="text" class="form-control" id="nama_supplier" name="nama_supplier" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="kontak" class="form-label">Kontak</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                            <input type="text" class="form-control" id="kontak" name="kontak" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="alamat" class="form-label">Alamat</label>
                        <textarea class="form-control" id="alamat" name="alamat" rows="3" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="provinsi" class="form-label">Provinsi</label>
                            <input type="text" class="form-control" id="provinsi" name="provinsi" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="negara" class="form-label">Negara</label>
                            <input type="text" class="form-control" id="negara" name="negara" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="kode_pos" class="form-label">Kode Pos</label>
                        <input type="text" class="form-control" id="kode_pos" name="kode_pos" required>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-user-plus me-2"></i> <?php echo $is_supplier_registration ? 'Daftarkan Supplier' : 'Daftar'; ?>
                        </button>
                        <a href="<?php echo $is_supplier_registration ? 'suppliers.php' : 'login.php'; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i> Kembali
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Loading indicator -->
    <div class="loading" id="loadingSpinner" style="display: none;">
        <div class="loading-spinner"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Generate floating coffee beans
    document.addEventListener("DOMContentLoaded", function() {
        const beansContainer = document.getElementById('floatingBeans');
        const beanCount = 15;
        
        for (let i = 0; i < beanCount; i++) {
            const bean = document.createElement('div');
            bean.classList.add('coffee-bean');
            
            // Random positioning
            bean.style.left = `${Math.random() * 100}%`;
            
            // Random size
            const size = 15 + Math.random() * 20;
            bean.style.width = `${size}px`;
            bean.style.height = `${size}px`;
            
            // Random animation duration
            const duration = 10 + Math.random() * 20;
            bean.style.animationDuration = `${duration}s`;
            
            // Random delay
            const delay = Math.random() * 10;
            bean.style.animationDelay = `${delay}s`;
            
            beansContainer.appendChild(bean);
        }
        
        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(event) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                event.preventDefault();
                alert('Password dan konfirmasi password tidak cocok!');
                return false;
            }
            
            if (password.length < 6) {
                event.preventDefault();
                alert('Password harus minimal 6 karakter!');
                return false;
            }
            
            // Show loading spinner
            document.getElementById('loadingSpinner').style.display = 'flex';
        });
    });
    
    // Toggle password visibility
    function togglePassword(fieldId) {
        const passwordField = document.getElementById(fieldId);
        const toggleIcon = document.getElementById(fieldId === 'password' ? 'toggleIcon1' : 'toggleIcon2');
        
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            passwordField.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }
    </script>
</body>
</html>