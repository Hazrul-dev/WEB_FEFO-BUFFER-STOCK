<?php
// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$host = "localhost";
$username = "root";
$password = "";
$database = "d_fans_coffe";

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Set timeout and charset
$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
$conn->set_charset("utf8mb4");

// Sanitize function
function sanitize($data) {
    global $conn;
    return htmlspecialchars(strip_tags(trim($conn->real_escape_string($data))));
}

// FEFO function
function getBahanByFEFO($bahan_id, $conn) {
    $sql = "SELECT bm.id, bm.kuantitas as sisa, bm.tanggal_expired
            FROM barang_masuk bm
            WHERE bm.bahan_id = ? 
            AND bm.kuantitas > 0
            ORDER BY bm.tanggal_expired ASC
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $bahan_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return false;
}

// Auto order function (ROP)
function checkAndCreateOrder($bahan_id, $conn) {
    $sql = "SELECT b.*, s.id as supplier_id 
            FROM bahan_baku b
            JOIN barang_masuk bm ON b.id = bm.bahan_id
            JOIN suppliers s ON bm.supplier_id = s.id
            WHERE b.id = ? AND b.stok <= b.rop
            GROUP BY s.id
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $bahan_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
        $jumlah_pesan = $data['safety_stock'] * 2; // Pesan 2x safety stock
        
        $sql_order = "INSERT INTO pesanan (tanggal_pesanan, supplier_id, bahan_id, jumlah, harga, total_harga, user_id)
                      VALUES (NOW(), ?, ?, ?, ?, ?, ?)";
        $stmt_order = $conn->prepare($sql_order);
        
        // Harga diasumsikan sama dengan harga terakhir
        $harga = $data['harga'] ?? 0;
        $total = $harga * $jumlah_pesan;
        
        $stmt_order->bind_param("iiiddi", 
            $data['supplier_id'],
            $bahan_id,
            $jumlah_pesan,
            $harga,
            $total,
            $_SESSION['user_id']
        );
        
        return $stmt_order->execute();
    }
    
    return false;
}

// Audit Trail function
function logAction($action, $table_name, $record_id, $old_value = null, $new_value = null) {
    global $conn;
    
    $sql = "INSERT INTO audit_trail (user_id, action, table_name, record_id, old_value, new_value)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    $old_value = $old_value ? json_encode($old_value) : null;
    $new_value = $new_value ? json_encode($new_value) : null;
    
    $stmt->bind_param("ississ", 
        $_SESSION['user_id'],
        $action,
        $table_name,
        $record_id,
        $old_value,
        $new_value
    );
    
    $stmt->execute();
}

function getLowStockNotifications($supplier_id, $conn) {
    $sql = "SELECT b.nama_bahan, k.sisa, b.stok_minimal, k.tanggal_expired
            FROM kartu_stok k
            JOIN bahan_baku b ON k.bahan_id = b.id
            JOIN barang_masuk m ON k.barang_masuk_id = m.id
            WHERE m.supplier_id = ? AND k.sisa < b.stok_minimal
            ORDER BY k.tanggal_expired";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $supplier_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function logActivity($user_id, $action, $table, $record_id, $description) {
    global $conn;
    
    $sql = "INSERT INTO activity_logs 
            (user_id, action, table_name, record_id, description, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())";
            
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("Failed to prepare activity log statement: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("issis", $user_id, $action, $table, $record_id, $description);
    $result = $stmt->execute();
    
    if (!$result) {
        error_log("Failed to log activity: " . $stmt->error);
    }
    
    return $result;
}
?>