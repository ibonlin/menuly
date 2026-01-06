<?php
// Session başlat (CSRF için gerekli)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once '../includes/db.php';

// Güvenlik: Sadece patron girebilir
if (!isset($_SESSION['master_id'])) { header("Location: login.php"); exit; }

// CSRF Token Oluştur
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. CSRF KONTROLÜ
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Güvenlik Hatası: Geçersiz istek.");
    }

    $rest_name = trim($_POST['restaurant_name']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $sub_end = !empty($_POST['subscription_end']) ? $_POST['subscription_end'] : NULL;
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);

    // Slug (Link) Oluştur
    $slug = strtolower(str_replace(' ', '-', $rest_name));
    $slug = preg_replace('/[^a-z0-9-]/', '', $slug);

    // Kullanıcı adı veya Link dolu mu?
    $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR slug = ?");
    $check->execute([$username, $slug]);
    
    if ($check->fetchColumn() > 0) {
        $message = '<div style="color:red; margin-bottom:15px;">Bu kullanıcı adı veya restoran ismi zaten var!</div>';
    } else {
        // 2. ŞİFREYİ HASHLE (GÜVENLİ HALE GETİR)
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // KAYDET
        $sql = "INSERT INTO users (username, password, restaurant_name, slug, subscription_end, email, phone, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
        $stmt = $pdo->prepare($sql);
        
        // Hashed password kullanıyoruz
        if ($stmt->execute([$username, $hashed_password, $rest_name, $slug, $sub_end, $email, $phone])) {
            header("Location: index.php?msg=added");
            exit;
        } else {
            $message = '<div style="color:red;">Veritabanı hatası oluştu.</div>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Yeni Restoran Ekle</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f3f4f6; display: flex; justify-content: center; padding-top: 50px; }
        .box { background: white; padding: 30px; border-radius: 12px; width: 400px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        input { width: 100%; padding: 10px; margin: 5px 0 15px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box;}
        button { width: 100%; padding: 10px; background: #16a34a; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 15px; }
        button:hover { background: #15803d; }
        .back { display: block; text-align: center; margin-top: 15px; text-decoration: none; color: #666; font-size: 14px; }
        label { font-size: 13px; font-weight: 600; color: #4b5563; }
        h2 { margin-top: 0; color: #111827; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Yeni Restoran Ekle</h2>
        <p style="font-size:13px; color:#6b7280; margin-bottom:20px;">Müşteriyi sisteme manuel olarak kaydedin.</p>
        
        <?php echo $message; ?>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <label>Restoran Adı (Örn: Paşa Döner)</label>
            <input type="text" name="restaurant_name" required>
            
            <label>Kullanıcı Adı (Giriş için)</label>
            <input type="text" name="username" required>
            
            <label>Şifre</label>
            <input type="text" name="password" required>
            
            <label>E-Posta Adresi</label>
            <input type="email" name="email" placeholder="musteri@mail.com">
            
            <label>Telefon Numarası</label>
            <input type="text" name="phone" placeholder="0555 123 45 67">
            
            <label style="color:#2563eb;">Abonelik Bitiş Tarihi</label>
            <input type="date" name="subscription_end" value="<?php echo date('Y-m-d', strtotime('+1 year')); ?>">
            
            <button type="submit">+ Restoranı Oluştur</button>
        </form>
        <a href="index.php" class="back">← İptal</a>
    </div>
</body>
</html>