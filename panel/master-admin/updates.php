<?php
session_start();
require_once '../includes/db.php';

// Güvenlik Kontrolü (Master Admin girişi yapılmış mı?)
if (!isset($_SESSION['master_id'])) {
    header("Location: login.php");
    exit;
}

$message = '';

// YENİ GÜNCELLEME EKLEME
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_update'])) {
    $title = trim($_POST['title']);
    $msg_content = trim($_POST['message']);
    $type = $_POST['type'];

    if (!empty($title) && !empty($msg_content)) {
        $stmt = $pdo->prepare("INSERT INTO system_updates (title, message, type) VALUES (?, ?, ?)");
        if ($stmt->execute([$title, $msg_content, $type])) {
            $message = '<div class="alert success">Güncelleme yayınlandı!</div>';
        }
    }
}

// SİLME İŞLEMİ
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM system_updates WHERE id = ?")->execute([$del_id]);
    header("Location: updates.php");
    exit;
}

// LİSTELEME
$updates = $pdo->query("SELECT * FROM system_updates ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Sistem Güncellemeleri Yönetimi</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .update-card { background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        .type-badge { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .type-feature { background: #eff6ff; color: #2563eb; }
        .type-fix { background: #fef2f2; color: #dc2626; }
        .type-info { background: #f8fafc; color: #475569; }
    </style>
</head>
<body>
    <div style="max-width: 800px; margin: 40px auto; padding: 20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h1>Güncelleme Yönetimi</h1>
            <a href="index.php" class="btn">Geri Dön</a>
        </div>

        <?php echo $message; ?>

        <div class="form-card" style="margin-bottom: 30px;">
            <h3>Yeni Güncelleme Yayınla</h3>
            <form method="POST">
                <input type="hidden" name="add_update" value="1">
                <div style="margin-bottom: 15px;">
                    <label>Başlık</label>
                    <input type="text" name="title" class="form-input" placeholder="Örn: Yeni QR Menü Teması Eklendi" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label>Tür</label>
                        <select name="type" class="form-input">
                            <option value="feature">✨ Yeni Özellik</option>
                            <option value="fix">🔧 Düzeltme (Fix)</option>
                            <option value="info">📢 Duyuru</option>
                        </select>
                    </div>
                </div>
                <div style="margin-bottom: 15px;">
                    <label>Açıklama</label>
                    <textarea name="message" class="form-input" style="height: 100px;" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Yayınla</button>
            </form>
        </div>

        <h3>Yayınlanan Güncellemeler</h3>
        <?php foreach ($updates as $up): ?>
            <div class="update-card">
                <div>
                    <span class="type-badge type-<?php echo $up['type']; ?>">
                        <?php echo $up['type'] == 'feature' ? 'YENİ' : ($up['type'] == 'fix' ? 'DÜZELTME' : 'DUYURU'); ?>
                    </span>
                    <strong style="margin-left: 10px;"><?php echo htmlspecialchars($up['title']); ?></strong>
                    <div style="font-size: 13px; color: #64748b; margin-top: 4px;">
                        <?php echo date('d.m.Y H:i', strtotime($up['created_at'])); ?>
                    </div>
                </div>
                <a href="?delete=<?php echo $up['id']; ?>" onclick="return confirm('Silmek istediğine emin misin?')" style="color:red;"><i class="ph-bold ph-trash"></i></a>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>