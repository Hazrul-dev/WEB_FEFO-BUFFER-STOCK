<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

// Register logic
if (isset($_GET['registered']) && $_GET['registered'] == 'success') {
    $success = "Akun berhasil dibuat! Silakan login.";
}

// Login logic
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    // Check database connection first
    if ($conn->connect_error) {
        $error = "Koneksi database gagal: " . $conn->connect_error;
    } else {
        $sql = "SELECT * FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
            $error = "Error dalam menyiapkan query: " . $conn->error;
            error_log("Login Error - Prepare failed: " . $conn->error);
        } else {
            // Bind parameters only if prepare succeeded
            $bind_result = $stmt->bind_param("s", $username);
            if ($bind_result === false) {
                $error = "Error binding parameters: " . $stmt->error;
                error_log("Login Error - Bind failed: " . $stmt->error);
            } else {
                $execute_result = $stmt->execute();
                if ($execute_result === false) {
                    $error = "Error executing query: " . $stmt->error;
                    error_log("Login Error - Execute failed: " . $stmt->error);
                } else {
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows == 1) {
                        $user = $result->fetch_assoc();
                        if (password_verify($password, $user['password'])) {
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['username'] = $user['username'];
                            $_SESSION['nama'] = $user['nama'];
                            $_SESSION['jabatan'] = $user['jabatan'];
                            
                            // If supplier, get supplier_id
                            if ($user['jabatan'] == 'Supplier') {
                                $sql_supplier = "SELECT id FROM suppliers WHERE user_id = ?";
                                $stmt_supplier = $conn->prepare($sql_supplier);
                                
                                if ($stmt_supplier === false) {
                                    $error = "Error preparing supplier query: " . $conn->error;
                                    error_log("Supplier Query Error: " . $conn->error);
                                } else {
                                    $bind_supplier = $stmt_supplier->bind_param("i", $user['id']);
                                    if ($bind_supplier === false) {
                                        $error = "Error binding supplier parameters: " . $stmt_supplier->error;
                                        error_log("Supplier Bind Error: " . $stmt_supplier->error);
                                    } else {
                                        $execute_supplier = $stmt_supplier->execute();
                                        if ($execute_supplier === false) {
                                            $error = "Error executing supplier query: " . $stmt_supplier->error;
                                            error_log("Supplier Execute Error: " . $stmt_supplier->error);
                                        } else {
                                            $result_supplier = $stmt_supplier->get_result();
                                            if ($result_supplier->num_rows > 0) {
                                                $supplier = $result_supplier->fetch_assoc();
                                                $_SESSION['supplier_id'] = $supplier['id'];
                                            } else {
                                                $error = "Data supplier tidak ditemukan untuk user ini!";
                                            }
                                        }
                                    }
                                }
                            }
                            
                            if (empty($error)) {
                                setcookie('last_login_username', $username, time() + (86400 * 30), "/");
                                header("Location: dashboard.php");
                                exit();
                            }
                        } else {
                            $error = "Username atau password salah!";
                        }
                    } else {
                        $error = "Username atau password salah!";
                    }
                }
            }
        }
    }
}

$last_username = isset($_COOKIE['last_login_username']) ? $_COOKIE['last_login_username'] : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>  
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - D'Fans Coffe</title>
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="images/BYU.jpg" type="image/x-icon">
    <link href="stylelogin.css" rel="stylesheet">
</head>
<body>
    <!-- Animated background -->
    <div class="floating-beans" id="floatingBeans"></div>
    
    <div class="container-fluid login-container">
        <div class="card login-card">
            <div class="card-header login-header">
                <div class="logo-container">
                <img src="images/BYU.jpg" alt="D'Fans Coffe Logo" class="logo-img">
                </div>
                <h3>D'Fans Coffe</h3>
                <p class="mb-0">Sistem Pengelolaan Persediaan</p>
            </div>
            <div class="card-body login-body">
                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="loginForm">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($last_username); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group password-container">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <span class="password-toggle" onclick="togglePassword()">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </span>
                        </div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="rememberMe" name="rememberMe">
                        <label class="form-check-label" for="rememberMe">Ingat Saya</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-sign-in-alt me-2"></i> Login
                    </button>
                </form>
                
                <div class="register-link">
                    <p>Belum punya akun? <a href="register.php">Daftar</a>
                    <a href="register.php?type=supplier" class="btn btn-outline-primary">Daftar sebagai Supplier</a></p>
                </div>
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
        
        // Show loading indicator when form is submitted
        document.getElementById('loginForm').addEventListener('submit', function() {
            document.getElementById('loadingSpinner').style.display = 'flex';
            setTimeout(function() {
                document.getElementById('loadingSpinner').style.display = 'none';
            }, 2000);
        });
    });
    
    // Toggle password visibility
    function togglePassword() {
        const passwordField = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');
        
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