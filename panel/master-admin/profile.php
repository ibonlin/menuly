<?php
require_once '../includes/db.php';
if (!isset($_SESSION['master_id'])) { header("Location: login.php"); exit; }

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_pass = trim($_POST['password']);
    if (!empty($new_pass)) {
        $pdo->prepare("UPDATE master_admin SET password = ? WHERE id = ?")->execute([$new_pass, $_SESSION['master_id']]);
        $msg = "Şifreniz değişti!";
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Profil</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f3f4f6; display: flex; justify-content: center; padding-top: 50px; }
        .box { background: white; padding: 30px; border-radius: 12px; width: 400px; }
        input { width: 100%; padding: 10px; margin: 10px 0 20px; border: 1px solid #ddd; border-radius: 6px; box-sizing:border-box;}
        button { width: 100%; padding: 10px; background: #111827; color: white; border: none; border-radius: 6px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="box">
        <h2 style="margin-top:0">Master Şifre Değiştir</h2>
        <?php if($msg) echo "<p style='color:green'>$msg</p>"; ?>
        <form method="POST">
            <label>Yeni Şifreniz</label>
            <input type="text" name="password" required placeholder="Yeni master şifresi...">
            <button type="submit">Güncelle</button>
        </form>
        <a href="index.php" style="display:block; text-align:center; margin-top:15px; color:#666; text-decoration:none;">← Geri Dön</a>
    </div>
</body>
</html>