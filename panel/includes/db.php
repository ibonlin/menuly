<?php
// includes/db.php

// --- BURAYI KENDİ HOSTİNG BİLGİLERİNLE DOLDUR ---
$host = 'localhost'; 
$dbname = 'menuly_login';      // Hostingde oluşturduğun DB adı
$username = 'menuly_loginad';  // DB Kullanıcı adı
$password = 'axN{Zot]mk.900~%';      // DB Şifresi

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Bağlantı olmazsa hatayı ekrana bas
    die("Veritabanı Bağlantı Hatası: " . $e->getMessage());
}

// Oturum başlat (Giriş çıkış işlemleri için şart)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>