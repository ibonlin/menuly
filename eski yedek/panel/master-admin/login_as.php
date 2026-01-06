<?php
session_start();
require_once '../includes/db.php';

// Güvenlik: Sadece Master Admin girebilir
if (!isset($_SESSION['master_id'])) {
    die("Yetkisiz Erişim! Lütfen Master Admin girişi yapın.");
}

if (isset($_GET['id'])) {
    $target_user_id = (int)$_GET['id'];

    // Kullanıcıyı çek
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$target_user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Kullanıcı oturumu başlat (Master oturumu korunur)
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['restaurant_name'] = $user['restaurant_name'];
        $_SESSION['role'] = 'user';
        
        // Yönlendir
        header("Location: ../admin/index.php");
        exit;
    } else {
        die("Kullanıcı bulunamadı.");
    }
}
?>