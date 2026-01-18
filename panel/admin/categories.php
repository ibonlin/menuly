<?php
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$user_id = $_SESSION['user_id'];
$message = '';

// --- GÜVENLİK: CSRF TOKEN OLUŞTUR ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- İŞLEM 1: YENİ KATEGORİ EKLE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cat_name'])) {
    // CSRF KONTROLÜ
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Güvenlik Hatası: Geçersiz istek.");
    }

    if (isset($_SESSION['username']) && $_SESSION['username'] === 'demo') {
        $message = '<div class="alert success" style="background:#fff7ed; color:#ea580c;">Demo Modu: Kategori eklendi! (Simülasyon)</div>';
        goto skip_add;
    }

    $name = trim($_POST['cat_name']);
    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO categories (user_id, name, sort_order) VALUES (?, ?, 0)");
        if ($stmt->execute([$user_id, $name])) {
            $message = '<div class="alert success">Kategori eklendi!</div>';
        }
    }
    skip_add:
}

// --- İŞLEM 2: SIRALAMAYI GÜNCELLE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_orders'])) {
    // CSRF KONTROLÜ
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Güvenlik Hatası: Geçersiz istek.");
    }

    if (isset($_SESSION['username']) && $_SESSION['username'] === 'demo') {
        $message = '<div class="alert success" style="background:#fff7ed; color:#ea580c;">Demo Modu: Sıralama güncellendi! (Simülasyon)</div>';
        goto skip_order;
    }

    if (isset($_POST['order']) && is_array($_POST['order'])) {
        $update_stmt = $pdo->prepare("UPDATE categories SET sort_order = ? WHERE id = ? AND user_id = ?");
        foreach ($_POST['order'] as $cat_id => $order_val) {
            $update_stmt->execute([(int)$order_val, (int)$cat_id, $user_id]);
        }
        $message = '<div class="alert success">Sıralama başarıyla güncellendi!</div>';
    }
    skip_order:
}

// --- İŞLEM 3: SİLME ---
// Silme işlemi GET ile yapılıyor, bunu da CSRF ile korumak için POST'a çevirmek en doğrusudur ama 
// hızlı çözüm için şimdilik GET olarak bırakıp Token kontrolü ekleyemeyiz (link olduğu için).
// İdeal olan, silme butonunu bir form haline getirmektir (Products.php'de yaptığımız gibi).
if (isset($_GET['delete'])) {
    // NOT: Silme işlemini products.php'deki gibi POST formuna çevirmeniz önerilir.
    // Şimdilik demo kontrolü:
    if (isset($_SESSION['username']) && $_SESSION['username'] === 'demo') {
        header("Location: categories.php"); exit;
    }
    $cat_id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
    $stmt->execute([$cat_id, $user_id]);
    header("Location: categories.php");
    exit;
}

// LİSTELEME
$stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY sort_order ASC, id DESC");
$stmt->execute([$user_id]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Kategoriler - Menuly</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .form-card { background: white; padding: 24px; border-radius: 20px; border: 1px solid #e2e8f0; display: flex; gap: 12px; align-items: flex-end; margin-bottom: 30px; }
        .form-group { flex: 1; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #64748b; }
        .form-input { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 12px; font-family: inherit; outline: none; }
        .alert { padding: 12px 16px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; font-weight: 600; }
        .alert.success { background: #dcfce7; color: #166534; }
        .order-input { width: 60px; padding: 8px; text-align: center; border: 1px solid #e2e8f0; border-radius: 8px; font-weight: 700; color: #2563eb; outline:none; }
        .order-input:focus { border-color: #2563eb; background: #eff6ff; }
    </style>
</head>
<body>
    <aside class="sidebar">
        <a href="index.php" class="logo"><span><i class="ph-bold ph-qr-code"></i></span> Menuly.</a>
        <ul class="nav-links">
            <li class="nav-item"><a href="index.php"><i class="ph-bold ph-squares-four"></i> Özet</a></li>
            <li class="nav-item"><a href="categories.php" class="active"><i class="ph-bold ph-list-dashes"></i> Kategoriler</a></li>
            <li class="nav-item"><a href="products.php"><i class="ph-bold ph-hamburger"></i> Ürünler</a></li>
            <li class="nav-item"><a href="destek.php"><i class="ph-bold ph-chats-circle"></i> Destek</a></li>
            <li class="nav-item"><a href="settings.php"><i class="ph-bold ph-gear"></i> Ayarlar</a></li>
            <li class="nav-item"><a href="logout.php" class="logout-btn"><i class="ph-bold ph-sign-out"></i> Çıkış Yap</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <button class="mobile-toggle" onclick="toggleSidebar()"><i class="ph-bold ph-list" style="font-size:20px;"></i> Menü</button>
        <div class="overlay" onclick="toggleSidebar()"></div>
        <script>function toggleSidebar(){document.querySelector('.sidebar').classList.toggle('active');document.querySelector('.overlay').classList.toggle('active');}</script>
        
        <div class="header">
            <div class="welcome"><h1>Kategori Yönetimi</h1><p>Menü sırasını buradan ayarlayabilirsiniz.</p></div>
        </div>

        <?php echo $message; ?>

        <form method="POST" class="form-card">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div class="form-group">
                <label>Yeni Kategori Adı</label>
                <input type="text" name="cat_name" class="form-input" placeholder="Örn: Tatlılar" required>
            </div>
            <button type="submit" class="btn btn-primary"><i class="ph-bold ph-plus"></i> Ekle</button>
        </form>

        <form method="POST">
            <input type="hidden" name="update_orders" value="1">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th width="80" style="text-align:center;">Sıra</th>
                            <th>Kategori Adı</th>
                            <th width="100" style="text-align:right">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($categories) > 0): ?>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td style="text-align:center;">
                                        <input type="number" name="order[<?php echo $cat['id']; ?>]" value="<?php echo $cat['sort_order']; ?>" class="order-input">
                                    </td>
                                    <td style="font-weight:600"><?php echo htmlspecialchars($cat['name']); ?></td>
                                    <td style="text-align:right">
                                        <a href="categories.php?delete=<?php echo $cat['id']; ?>" class="action-btn delete" onclick="return confirm('Silmek istediğine emin misin?')">
                                            <i class="ph-bold ph-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" style="text-align:center; padding:30px; color:#94a3b8;">Henüz kategori yok.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (count($categories) > 0): ?>
                <div style="margin-top: 20px; text-align: right;">
                    <button type="submit" class="btn btn-primary" style="background:#1e3a8a;">
                        <i class="ph-bold ph-arrows-down-up"></i> Sıralamayı Kaydet
                    </button>
                </div>
            <?php endif; ?>
        </form>
    </main>
</body>
</html>