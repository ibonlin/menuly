<?php
// --- 1. DOSYA YOLLARI (Resimdeki Yapıya Göre) ---

// Veritabanı Dosyası (Panel > Includes içinde)
// Yol: cron -> master-admin -> panel -> includes -> db.php
$db_path = __DIR__ . '/../../includes/db.php';

if (file_exists($db_path)) {
    require_once $db_path;
} else {
    die("HATA: Veritabanı dosyası ($db_path) bulunamadı!");
}

// PHPMailer Dosyaları (Public_html -> Src klasörü içinde)
// Yol: cron -> master-admin -> panel -> public_html -> src
$phpmailer_path = __DIR__ . '/../../../src'; 

if (file_exists($phpmailer_path . '/PHPMailer.php')) {
    require $phpmailer_path . '/Exception.php';
    require $phpmailer_path . '/PHPMailer.php';
    require $phpmailer_path . '/SMTP.php';
} else {
    // Debug için tam yolu ekrana basalım
    die("HATA: PHPMailer dosyaları bulunamadı!<br>Aranan Yol: " . realpath($phpmailer_path) . "<br>Lütfen '/src' klasörünün yerini kontrol edin.");
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// --- 2. ZAMAN DİLİMİ ---
date_default_timezone_set('Europe/Istanbul');
$pdo->exec("SET time_zone = '+03:00'");

// --- 3. SMTP AYARLARI (Yandex) ---
$smtp_host = 'smtp.yandex.com.tr';
$smtp_user = 'info@menuly.net';
$smtp_pass = 'quvbldhnviyromwl'; // Buraya uygulama şifreni yazmayı unutma!
$smtp_port = 465;
$smtp_secure = PHPMailer::ENCRYPTION_SMTPS;
// ---------------------------------

try {
    // 1, 2 veya 3 günü kalanları bul
    $sql = "SELECT *, DATEDIFF(subscription_end, CURDATE()) as days_left 
            FROM users 
            WHERE is_active = 1 
            HAVING days_left IN (1, 2, 3)";
            
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h3>📊 SMTP Cron Raporu (" . date("d.m.Y H:i:s") . ")</h3>";

    if (count($users) > 0) {
        foreach ($users as $user) {
            $days = $user['days_left'];
            $to = $user['email'];
            
            echo "<div style='border:1px solid #ddd; padding:10px; margin-bottom:10px;'>";
            echo "<strong>İşletme:</strong> " . $user['restaurant_name'] . "<br>";
            
            if (!empty($to)) {
                $mail = new PHPMailer(true);

                try {
                    // Sunucu Ayarları
                    $mail->isSMTP();
                    $mail->Host       = $smtp_host;
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $smtp_user;
                    $mail->Password   = $smtp_pass;
                    $mail->SMTPSecure = $smtp_secure;
                    $mail->Port       = $smtp_port;
                    $mail->CharSet    = 'UTF-8';

                    // Gönderen ve Alıcı
                    $mail->setFrom($smtp_user, 'Menuly Bilgilendirme');
                    $mail->addAddress($to); 
                    $mail->addReplyTo($smtp_user, 'Menuly Destek');

                    // İçerik
                    $mail->isHTML(true);
                    $mail->Subject = "⚠️ Menuly Abonelik Uyarısı: $days Gün Kaldı!";
                    
                    $bodyContent = "
                    <div style='font-family:Arial, sans-serif; padding:20px; border:1px solid #eee; border-radius:10px; background-color:#f9f9f9;'>
                        <h2 style='color:#d32f2f; margin-top:0;'>Aboneliğiniz Bitiyor!</h2>
                        <p>Sayın <strong>" . htmlspecialchars($user['restaurant_name']) . "</strong>,</p>
                        <p>Dijital menü hizmet sürenizin dolmasına sadece <strong style='font-size:18px;'>$days gün</strong> kaldı.</p>
                        <p>QR menünüzün kapanmaması ve müşterilerinizin mağdur olmaması için lütfen sürenizi uzatınız.</p>
                        <div style='background:#fff; padding:15px; border-left:4px solid #d32f2f; margin:15px 0;'>
                            <strong>Bitiş Tarihi:</strong> " . date("d.m.Y", strtotime($user['subscription_end'])) . "
                        </div>
                        <p style='font-size:12px; color:#666;'>Bu otomatik bir hatırlatma mesajıdır.</p>
                        <br>
                        <a href='https://menuly.net' style='background:#2563eb; color:white; padding:10px 20px; text-decoration:none; border-radius:5px; font-weight:bold;'>Panele Git</a>
                    </div>";
                    
                    $mail->Body = $bodyContent;
                    $mail->AltBody = strip_tags($bodyContent);

                    $mail->send();
                    echo "<span style='color:green'>✅ E-posta başarıyla gönderildi: $to</span>";
                } catch (Exception $e) {
                    echo "<span style='color:red'>❌ Mail Hatası: {$mail->ErrorInfo}</span>";
                }
            } else {
                echo "<span style='color:orange'>⚠️ Bu kullanıcının e-posta adresi sistemde kayıtlı değil.</span>";
            }
            echo "</div>";
        }
    } else {
        echo "✅ Süresi kritik seviyede (1-3 gün) olan üyelik bulunamadı.<br>";
    }

} catch (PDOException $e) {
    echo "Veritabanı Hatası: " . $e->getMessage();
}
?>