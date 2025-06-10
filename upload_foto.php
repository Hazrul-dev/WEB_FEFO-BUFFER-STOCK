<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['foto'])) {
    $target_dir = "uploads/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_name = basename($_FILES["foto"]["name"]);
    $target_file = $target_dir . $file_name;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    $new_file_name = 'profile_' . $_SESSION['user_id'] . '.' . $imageFileType;
    $target_file = $target_dir . $new_file_name;
    
    // Check if image file is a actual image or fake image
    $check = getimagesize($_FILES["foto"]["tmp_name"]);
    if ($check === false) {
        $_SESSION['error'] = "File bukan gambar.";
        header("Location: profile.php");
        exit();
    }
    
    // Check file size (max 2MB)
    if ($_FILES["foto"]["size"] > 2000000) {
        $_SESSION['error'] = "Ukuran file terlalu besar (max 2MB).";
        header("Location: profile.php");
        exit();
    }
    
    // Allow certain file formats
    if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
        $_SESSION['error'] = "Hanya file JPG, JPEG, PNG & GIF yang diperbolehkan.";
        header("Location: profile.php");
        exit();
    }
    
    // Delete old photo if exists
    $sql = "SELECT foto FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $old_photo = $result->fetch_assoc()['foto'];
    
    if (!empty($old_photo) && file_exists($target_dir . $old_photo)) {
        unlink($target_dir . $old_photo);
    }
    
    // Upload file
    if (move_uploaded_file($_FILES["foto"]["tmp_name"], $target_file)) {
        // Update database
        $sql = "UPDATE users SET foto = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $new_file_name, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Foto profile berhasil diupload.";
        } else {
            $_SESSION['error'] = "Gagal menyimpan ke database: " . $conn->error;
        }
    } else {
        $_SESSION['error'] = "Maaf, terjadi error saat upload file.";
    }
    
    header("Location: profile.php");
    exit();
}

header("Location: profile.php");
exit();
?>