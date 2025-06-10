<?php
session_start();
require_once 'config.php';

// Verifikasi apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Pastikan koneksi database berhasil
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

try {
    // Update semua notifikasi yang belum dibaca untuk user ini
    $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Notifikasi telah ditandai sebagai sudah dibaca";
        } else {
            throw new Exception("Gagal mengeksekusi query: " . $stmt->error);
        }
        
        $stmt->close();
    } else {
        throw new Exception("Gagal mempersiapkan statement: " . $conn->error);
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
    error_log("Error in mark_notif_read.php: " . $e->getMessage());
}

// Redirect kembali ke halaman sebelumnya atau ke dashboard
$redirect_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'dashboard.php';
header("Location: " . $redirect_url);
exit();
?>