<?php
session_start();
require_once 'config.php';

// Verify database connection first
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Initialize notifications array
$notifications = [];

// Get notifications with full error handling
try {
    // First verify the notifications table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($table_check === false || $table_check->num_rows == 0) {
        throw new Exception("Notifications table doesn't exist or can't be accessed");
    }

    // Prepare the notification query
    $sql_notif = "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5";
    
    if (!($stmt_notif = $conn->prepare($sql_notif))) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // Bind parameters
    if (!$stmt_notif->bind_param("i", $_SESSION['user_id'])) {
        throw new Exception("Bind failed: " . $stmt_notif->error);
    }

    // Execute query
    if (!$stmt_notif->execute()) {
        throw new Exception("Execute failed: " . $stmt_notif->error);
    }

    // Get results
    $result = $stmt_notif->get_result();
    if ($result === false) {
        throw new Exception("Get result failed: " . $stmt_notif->error);
    }

    $notifications = $result->fetch_all(MYSQLI_ASSOC);
    
    // Free result
    $result->free();
    $stmt_notif->close();

} catch (Exception $e) {
    // Log error but don't show to user
    error_log("Notification system error: " . $e->getMessage());
    $notifications = []; // Return empty array
}

// Get current page name
$current_page = basename($_SERVER['PHP_SELF']);

// Set default timezone if not set
if (!ini_get('date.timezone')) {
    date_default_timezone_set('Asia/Jakarta');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($title) ? $title : 'D\'Fans Coffe'; ?></title>
    <link rel="icon" href="images/BYU.jpg" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="p-3">
                <a href="dashboard.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
                    <img src="images/BYU.jpg" alt="D'Fans Coffe Logo" style="width: 40px; height: 40px; object-fit: contain;">
                    <span class="fs-4">D'Fans Coffe</span>
                </a>
                <hr>
                <ul class="nav nav-pills flex-column">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="bahan_baku.php" class="nav-link <?php echo $current_page == 'bahan_baku.php' ? 'active' : ''; ?>">
                            <i class="fas fa-boxes me-2"></i> Bahan Baku
                        </a>
                    </li>
                    <li>
                        <a href="barang_masuk.php" class="nav-link <?php echo $current_page == 'barang_masuk.php' ? 'active' : ''; ?>">
                            <i class="fas fa-arrow-down me-2"></i> Barang Masuk
                        </a>
                    </li>
                    <li>
                        <a href="barang_keluar.php" class="nav-link <?php echo $current_page == 'barang_keluar.php' ? 'active' : ''; ?>">
                            <i class="fas fa-arrow-up me-2"></i> Barang Keluar
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $_SESSION['jabatan'] == 'Supplier' ? 'pesanan_supplier.php' : 'pesanan_admin.php'; ?>" 
                        class="nav-link <?php echo ($current_page == 'pesanan_admin.php' || $current_page == 'pesanan_supplier.php') ? 'active' : ''; ?>">
                            <i class="fas fa-shopping-cart me-2"></i> Pesanan
                        </a>
                    </li>
                    <?php if ($_SESSION['jabatan'] == 'Admin' || $_SESSION['jabatan'] == 'Manager'): ?>
                    <li>
                        <a href="laporan.php" class="nav-link <?php echo $current_page == 'laporan.php' ? 'active' : ''; ?>">
                            <i class="fas fa-file-alt me-2"></i> Laporan
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($_SESSION['jabatan'] == 'Admin'): ?>
                    <li>
                        <a href="suppliers.php" class="nav-link <?php echo $current_page == 'suppliers.php' ? 'active' : ''; ?>">
                            <i class="fas fa-truck me-2"></i> Supplier
                        </a>
                    </li>
                    <li>
                        <a href="users.php" class="nav-link <?php echo $current_page == 'users.php' ? 'active' : ''; ?>">
                            <i class="fas fa-users me-2"></i> Data User
                        </a>
                    </li>
                    <!-- Notification Bell -->
                    <li class="nav-item dropdown">
                        <a class="nav-link" href="#" id="notifDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <?php if (!empty($notifications)): ?>
                            <span class="badge bg-danger"><?php echo count($notifications); ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if (!empty($notifications)): ?>
                                <?php foreach ($notifications as $notif): ?>
                                <li><a class="dropdown-item" href="pesanan.php"><?php echo htmlspecialchars($notif['message']); ?></a></li>
                                <?php endforeach; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-center" href="mark_notif_read.php">Tandai sudah dibaca</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item">Tidak ada notifikasi baru</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>
                    <?php if ($_SESSION['jabatan'] == 'Supplier' && isset($_SESSION['supplier_id'])): ?>
                    <li class="nav-item">
                        <a href="suppliers.php" class="nav-link <?= $current_page == 'suppliers.php' ? 'active' : '' ?>">
                            <i class="fas fa-box me-2"></i> Stok Saya
                            <?php
                            $sql = "SELECT COUNT(*) as total FROM kartu_stok k 
                                    JOIN barang_masuk b ON k.barang_masuk_id = b.id
                                    WHERE b.supplier_id = ? AND k.sisa < 10";
                            if ($stmt = $conn->prepare($sql)) {
                                $stmt->bind_param("i", $_SESSION['supplier_id']);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                $row = $result->fetch_assoc();
                                
                                if ($row['total'] > 0): ?>
                                    <span class="badge bg-danger float-end"><?= $row['total'] ?></span>
                                <?php endif;
                                $stmt->close();
                            }
                            ?>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                <hr>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="darkModeToggle">
                    <label class="form-check-label" for="darkModeToggle">Dark Mode</label>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content w-100">
            <nav class="navbar navbar-expand-lg navbar-dark">
                <div class="container-fluid">
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="navbarNav">
                        <ul class="navbar-nav ms-auto">
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-user-circle me-1"></i> <?php echo $_SESSION['nama']; ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>

            <div class="container-fluid py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if (isset($show_print_button) && $show_print_button): ?>
                        <button class="btn btn-sm btn-outline-secondary me-2 no-print" onclick="window.print()">
                            <i class="fas fa-print"></i> Cetak
                        </button>
                        <button class="btn btn-sm btn-outline-primary no-print" onclick="exportToPDF()">
                            <i class="fas fa-file-pdf"></i> Export PDF
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

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