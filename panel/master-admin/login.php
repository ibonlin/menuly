<?php
// includes/db.php yoluna dikkat (bir üst klasörde)
require_once '../includes/db.php';

if (isset($_SESSION['master_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Master Admin tablosuna bak
    $stmt = $pdo->prepare("SELECT * FROM master_admin WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && $admin['password'] === $password) {
        $_SESSION['master_id'] = $admin['id'];
        $_SESSION['master_user'] = $admin['username'];
        header("Location: index.php");
        exit;
    } else {
        $error = "Hatalı patron bilgisi.";
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Patron Girişi</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #111827; color: white; font-family: 'Plus Jakarta Sans', sans-serif; height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; }
        .login-box { background: #1f2937; padding: 40px; border-radius: 16px; width: 100%; max-width: 350px; text-align: center; border: 1px solid #374151; }
        input { width: 100%; padding: 12px; margin-bottom: 12px; background: #374151; border: 1px solid #4b5563; color: white; border-radius: 8px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #2563eb; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; }
        button:hover { background: #1d4ed8; }
        .error { color: #f87171; font-size: 14px; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2 style="margin-top:0;">Patron Paneli 🕶️</h2>
        <?php if($error) echo "<div class='error'>$error</div>"; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Kullanıcı Adı" required>
            <input type="password" name="password" placeholder="Şifre" required>
            <button type="submit">Giriş</button>
        </form>
    </div>
</body>
</html>