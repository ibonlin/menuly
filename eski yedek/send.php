<?php
// Türkçe karakter sorunu olmaması ve JSON döneceğimizi belirtmek için
header('Content-Type: application/json; charset=utf-8');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Dosya yollarını kontrol et (src klasörü send.php ile aynı yerde olmalı)
require 'src/Exception.php';
require 'src/PHPMailer.php';
require 'src/SMTP.php';

// Sadece POST isteği gelirse çalış
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $adsoyad = htmlspecialchars(trim($_POST['adsoyad']));
    $restoran = htmlspecialchars(trim($_POST['restoran']));
    $telefon = htmlspecialchars(trim($_POST['telefon']));

    // Boş alan kontrolü
    if (empty($adsoyad) || empty($restoran) || empty($telefon)) {
        echo json_encode(['success' => false, 'message' => 'Lütfen tüm alanları doldurun.']);
        exit;
    }

    $mail = new PHPMailer(true);

    try {
        // --- SMTP AYARLARI (BURALARI KENDİ HOSTİNGİNE GÖRE DOLDUR) ---
        $mail->isSMTP();
        $mail->Host       = 'smtp.yandex.com';      // Örn: mail.siteadi.com
        $mail->SMTPAuth   = true;
        $mail->Username   = 'info@adda.net.tr';      // Mail adresi
        $mail->Password   = 'quvbldhnviyromwl';          // Mail şifresi
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // 465 portu için bunu kullan
        $mail->Port       = 465;                    

        // --- GÖNDERİM AYARLARI ---
        $mail->setFrom('info@adda.net.tr', 'Menuly Web Form'); // Kimden gidiyor
        $mail->addAddress('info@adda.net.tr'); // Kime gidecek (Kendi mailin)
        
        // Formu dolduran kişinin maili yok ama adı var, ReplyTo ayarına gerek yok şimdilik.

        // İçerik
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8'; // Türkçe karakterler bozulmasın
        $mail->Subject = 'Yeni Basvuru: ' . $restoran;
        $mail->Body    = "
            <h3>Web Sitesinden Yeni Bir Başvuru Var!</h3>
            <p>Aşağıdaki bilgiler ile iletişim formunu doldurdular:</p>
            <table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%; border-color: #ddd;'>
                <tr>
                    <td width='30%' style='background-color: #f9f9f9;'><b>Ad Soyad:</b></td>
                    <td>$adsoyad</td>
                </tr>
                <tr>
                    <td style='background-color: #f9f9f9;'><b>Restoran:</b></td>
                    <td>$restoran</td>
                </tr>
                <tr>
                    <td style='background-color: #f9f9f9;'><b>Telefon:</b></td>
                    <td>$telefon</td>
                </tr>
                <tr>
                    <td style='background-color: #f9f9f9;'><b>Tarih:</b></td>
                    <td>" . date("d.m.Y H:i") . "</td>
                </tr>
            </table>
        ";

        $mail->send();
        
        // KRİTİK NOKTA BURASI: Asla header("Location: ...") kullanma!
        // Sadece JSON cevabı dönüyoruz:
        echo json_encode(['success' => true, 'message' => 'Başvurunuz başarıyla alındı. Sizi arayacağız!']);

    } catch (Exception $e) {
        // Hata durumunda da JSON dönüyoruz
        echo json_encode(['success' => false, 'message' => 'Mail gönderilemedi. Hata: ' . $mail->ErrorInfo]);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek.']);
}
?>