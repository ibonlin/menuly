<?php
// Telegram Test Dosyası
$token = "8589740930:AAELRmRrRIqal63joAnmHWdTIblI_oDyMc8";
$chat_id = "758649120";
$msg = "Deneme 1-2-3! Sunucun Telegrama erişebiliyor kuzen.";

$url = "https://api.telegram.org/bot$token/sendMessage?chat_id=$chat_id&text=" . urlencode($msg);

echo "<h3>Telegram Testi Başlıyor...</h3>";
echo "İstek atılan adres: $url <br><br>";

// Yöntem 1: File Get Contents
echo "<b>Yöntem 1 (file_get_contents):</b> ";
$res1 = @file_get_contents($url);
if($res1) { echo "<span style='color:green'>BAŞARILI!</span>"; } 
else { echo "<span style='color:red'>BAŞARISIZ! (Sunucuda allow_url_fopen kapalı olabilir)</span>"; }
echo "<br><br>";

// Yöntem 2: CURL
echo "<b>Yöntem 2 (CURL):</b> ";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$res2 = curl_exec($ch);
if($res2 && !curl_errno($ch)) { 
    echo "<span style='color:green'>BAŞARILI! Cevap: $res2</span>"; 
} else { 
    echo "<span style='color:red'>BAŞARISIZ! Curl Hatası: " . curl_error($ch) . "</span>"; 
}
curl_close($ch);
?>