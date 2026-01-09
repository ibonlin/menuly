<?php
// includes/db.php

// --- GÜVENLİK İÇİN .ENV KULLANIMI TAVSİYE EDİLİR ---
// Şimdilik mevcut yapını koruyarak devam ediyoruz.

$host = 'localhost'; 
$dbname = 'menuly_login';      
$username = 'menuly_loginad';  
$password = 'axN{Zot]mk.900~%';      

try {
    // DÜZELTME: charset=utf8mb4 olarak ayarlandı (Emoji desteği için)
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Ekstra Garanti: MySQL tarafında da karakter setini zorla
    $pdo->exec("SET NAMES 'utf8mb4'");
    $pdo->exec("SET CHARACTER SET utf8mb4");
    $pdo->exec("SET COLLATION_CONNECTION = 'utf8mb4_unicode_ci'");
} catch (PDOException $e) {
    // Güvenlik: Hatayı ekrana basmak yerine loga yazıyoruz.
    error_log("Veritabanı Hatası: " . $e->getMessage());
    die("Sistemsel bir hata oluştu, lütfen daha sonra tekrar deneyiniz.");
}

// Oturum başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- XSS KORUMASI İÇİN GLOBAL FONKSİYON ---
// Bu fonksiyonu tüm echo işlemlerinde kullanacağız.
// Örn: echo e($row['name']);
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// --- GÜVENLİK LOGLAMA FONKSİYONU ---
// Admin girişlerini kaydetmek için kullanılır.
function logAdminAccess($pdo, $user_id) {
    $ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    // Basit Cihaz Tespiti
    $device = 'Bilinmiyor';
    if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $user_agent)) {
        $device = 'Mobil Telefon';
    } elseif (preg_match('/ipad|tablet/i', $user_agent)) {
        $device = 'Tablet';
    } elseif (preg_match('/linux|macintosh|windows/i', $user_agent)) {
        $device = 'Bilgisayar';
    }

    // Veritabanına Kaydet (Hata oluşursa sistemi durdurmaması için try-catch içinde)
    try {
        $stmt = $pdo->prepare("INSERT INTO access_logs (user_id, ip_address, user_agent, device_info) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $ip, $user_agent, $device]);
    } catch (PDOException $e) {
        // Log tablosu yoksa veya hata varsa sessizce devam et
        error_log("Loglama Hatası: " . $e->getMessage());
    }
}
?>