<?php
require_once '../includes/db.php';

if (!isset($_SESSION['master_id'])) { header("Location: login.php"); exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

// Veriyi Çek
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) die("Kullanıcı bulunamadı.");

// GÜNCELLEME İŞLEMİ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rest_name = trim($_POST['restaurant_name']);
    $slug = trim($_POST['slug']);
    $new_pass = trim($_POST['password']);
    $sub_date = !empty($_POST['subscription_end']) ? $_POST['subscription_end'] : NULL;
    
    // YENİ ALANLAR
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);

    // Link temizliği
    $slug = strtolower(str_replace(' ', '-', $slug));
    $slug = preg_replace('/[^a-z0-9-]/', '', $slug);

    // SQL Güncelleme (Email ve Phone eklendi)
    $sql = "UPDATE users SET restaurant_name=?, slug=?, subscription_end=?, email=?, phone=?";
    $params = [$rest_name, $slug, $sub_date, $email, $phone];

    if (!empty($new_pass)) {
        $sql .= ", password=?";
        $params[] = $new_pass;
    }

    $sql .= " WHERE id=?";
    $params[] = $id;

    $upd = $pdo->prepare($sql);
    $upd->execute($params);

    $message = "Bilgiler ve Süre güncellendi!";
    
    // Güncel veriyi tekrar çek (Formda görünsün diye)
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Düzenle</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f3f4f6; display: flex; justify-content: center; padding-top: 50px; }
        .box { background: white; padding: 30px; border-radius: 12px; width: 400px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        input { width: 100%; padding: 10px; margin: 5px 0 15px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box;}
        button { width: 100%; padding: 10px; background: #2563eb; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; }
        .back { display: block; text-align: center; margin-top: 15px; text-decoration: none; color: #666; font-size: 14px; }
        label { font-size: 13px; font-weight: 600; color: #4b5563; }
    </style>
</head>
<body>
    <div class="box">
        <h2 style="margin-top:0">Restoranı Düzenle</h2>
        <?php if($message) echo "<p style='color:green; font-weight:bold; text-align:center;'>$message</p>"; ?>
        
        <form method="POST">
            <label>Restoran Adı</label>
            <input type="text" name="restaurant_name" value="<?php echo htmlspecialchars($user['restaurant_name']); ?>" required>
            
            <label>Link (Slug)</label>
            <input type="text" name="slug" value="<?php echo htmlspecialchars($user['slug']); ?>" required>
            
            <label>E-Posta</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
            
            <label>Telefon</label>
            <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
            <label style="color:#2563eb;">Abonelik Bitiş Tarihi</label>
            <input type="date" name="subscription_end" value="<?php echo $user['subscription_end']; ?>">
            
            <label>Yeni Şifre (Boş bırakırsan değişmez)</label>
            <input type="text" name="password" placeholder="Yeni şifre...">
            
            <button type="submit">Bilgileri Kaydet</button>
        </form>
        <a href="index.php" class="back">← Geri Dön</a>
    </div>
</body>
</html>