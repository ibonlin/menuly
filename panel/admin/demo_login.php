<?php
require_once '../includes/db.php';

// Demo kullanıcısının verilerini çek (Kullanıcı adı 'demo' olan)
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'demo'");
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    // Şifre sormadan direkt oturum açtırıyoruz
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['restaurant_name'] = $user['restaurant_name'];
    
    // Panele fırlat
    header("Location: index.php");
    exit;
} else {
    die("Demo kullanıcısı veritabanında bulunamadı! Lütfen önce Master Admin'den 'demo' kullanıcısını oluşturun.");
}
?>