<?php
// includes/db.php

// --- GÜVENLİK İÇİN ORTAM DEĞİŞKENLERİ YÜKLEME ---

/**
 * Basit .env okuyucu fonksiyonu
 * Composer kullanılmadığı senaryolar için manuel çözüm.
 */
function loadEnv($path) {
    if (!file_exists($path)) {
        // .env dosyası bulunamazsa sessizce hata günlüğüne yaz
        error_log(".env dosyası bulunamadı: " . $path);
        return false;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Yorum satırlarını (# ile başlayanları) atla
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Anahtar=Değer çiftini ayır
        list($name, $value) = explode('=', $line, 2);
        
        $name = trim($name);
        $value = trim($value);

        // Tırnak işaretlerini temizle (varsa)
        $value = trim($value, "\"'");

        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
    return true;
}

// .env dosyasını ara (panel klasörünün bir veya iki üst dizininde olabilir)
// __DIR__ -> panel/includes
// dirname(__DIR__) -> panel
// dirname(dirname(__DIR__)) -> kök dizin
$envPath = dirname(dirname(__DIR__)) . '/.env';

// Yüklemeyi dene
loadEnv($envPath);

// Verileri ortam değişkenlerinden (Environment Variables) al
$host = getenv('DB_HOST') ?: 'localhost'; 
$dbname = getenv('DB_NAME');      
$username = getenv('DB_USER');  
$password = getenv('DB_PASS');

// Eğer .env okunamazsa ve veriler boşsa sistemi durdur (Güvenlik önlemi)
if (!$dbname || !$username) {
    // Hata detayını sadece loga yaz, ekrana basma!
    error_log("Veritabanı bilgileri yüklenemedi. .env dosyasını kontrol edin.");
    die("Sistem yapılandırma hatası. Lütfen yönetici ile iletişime geçin.");
}

try {
    // DÜZELTME: charset=utf8mb4 olarak ayarlandı
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // MySQL karakter seti garantisi
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

// --- XSS KORUMASI ---
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// --- LOGLAMA FONKSİYONU ---
function logAdminAccess($pdo, $user_id) {
    $ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    $device = 'Bilinmiyor';
    if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $user_agent)) {
        $device = 'Mobil Telefon';
    } elseif (preg_match('/ipad|tablet/i', $user_agent)) {
        $device = 'Tablet';
    } elseif (preg_match('/linux|macintosh|windows/i', $user_agent)) {
        $device = 'Bilgisayar';
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO access_logs (user_id, ip_address, user_agent, device_info) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $ip, $user_agent, $device]);
    } catch (PDOException $e) {
        error_log("Loglama Hatası: " . $e->getMessage());
    }
}
?>