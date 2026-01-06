<?php
require_once '../includes/db.php';

// 1. ADIM: GÜVENLİK VE KİMLİK TANIMI (EN TEPEDE OLMALI)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$user_id = $_SESSION['user_id'];
$message = '';

// 2. ADIM: SIRALAMA GÜNCELLEME (Artık $user_id tanımlı olduğu için hata vermez)
if (isset($_POST['update_orders'])) {
    // Demo modu kontrolü
    if (isset($_SESSION['username']) && $_SESSION['username'] === 'demo') {
        $message = '<div class="alert success" style="background:#fff7ed; color:#ea580c;">Demo Modu: Sıralama güncellendi! (Simülasyon)</div>';
    } else {
        // Gerçek güncelleme
        if (isset($_POST['order']) && is_array($_POST['order'])) {
            $update_stmt = $pdo->prepare("UPDATE products SET sort_order = ? WHERE id = ? AND user_id = ?");
            foreach ($_POST['order'] as $prod_id => $order_val) {
                $update_stmt->execute([(int)$order_val, (int)$prod_id, $user_id]);
            }
            $message = '<div class="alert success">Ürün sıralaması başarıyla güncellendi!</div>';
        }
    }
}

// --- KATEGORİLERİ ÇEK ---
$cat_stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY sort_order ASC, id DESC");
$cat_stmt->execute([$user_id]);
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

// --- İŞLEM: ÜRÜN EKLEME ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['prod_name'])) {
    
    // Form verilerini al
    $category_id = $_POST['category_id'];
    $name = trim($_POST['prod_name']);
    $desc = trim($_POST['description']);
    $price = $_POST['price'];
    // Rozet verisini al (YENİ)
    $badges = !empty($_POST['badges']) ? $_POST['badges'] : NULL;

    // --- DEMO KORUMASI ---
    if (isset($_SESSION['username']) && $_SESSION['username'] === 'demo') {
        $message = '<div class="alert success" style="background:#fff7ed; color:#ea580c;">Demo Modu: Ürün başarıyla eklendi! (Simülasyon)</div>';
        goto skip_product_add; 
    }
    // ---------------------
    
    // Resim Yükleme
    $image_path = ""; 
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $upload_dir = '../assets/uploads/';
        $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($file_ext, $allowed)) {
            $new_name = time() . '_' . rand(1000,9999) . '.' . $file_ext;
            $target = $upload_dir . $new_name;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                $image_path = 'assets/uploads/' . $new_name;
            }
        }
    }

    if (!empty($name) && !empty($price)) {
        // DÜZELTİLEN SQL SORGUSU (Badges eklendi)
        // Sıra: user_id, category_id, name, description, price, image, badges (7 tane)
        // Soru işareti: ?, ?, ?, ?, ?, ?, ? (7 tane)
        $stmt = $pdo->prepare("INSERT INTO products (user_id, category_id, name, description, price, image, badges) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$user_id, $category_id, $name, $desc, $price, $image_path, $badges])) {
            $message = '<div class="alert success">Ürün başarıyla eklendi!</div>';
        } else {
            $message = '<div class="alert error">Veritabanı hatası oluştu.</div>';
        }
    }
    skip_product_add:
}

// --- İŞLEM: SİLME ---
if (isset($_GET['delete'])) {
    if (isset($_SESSION['username']) && $_SESSION['username'] === 'demo') {
        header("Location: products.php"); exit;
    }

    $prod_id = $_GET['delete'];
    
    // Resmi sil
    $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ? AND user_id = ?");
    $stmt->execute([$prod_id, $user_id]);
    $prod = $stmt->fetch();
    
    if ($prod && !empty($prod['image'])) {
        $file_to_delete = '../' . $prod['image'];
        if (file_exists($file_to_delete)) unlink($file_to_delete);
    }

    // Kaydı sil
    $del_stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND user_id = ?");
    $del_stmt->execute([$prod_id, $user_id]);
    
    header("Location: products.php");
    exit;
}

// --- LİSTELEME ---
// Rozet ve Kategori ismini de çekiyoruz
// Önce Kategori Sırasına (c.sort_order), Sonra Ürün Sırasına (p.sort_order) göre diziyoruz
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.user_id = ? 
        ORDER BY c.sort_order ASC, p.sort_order ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ürünler - Menuly Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../assets/css/admin.css?v=2">
    
    <style>
        .prod-grid-form { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        .prod-img-preview { width: 50px; height: 50px; border-radius: 8px; object-fit: cover; background: #eee; }
        textarea { resize: vertical; min-height: 80px; font-family: inherit; }
        input[type="file"]::file-selector-button { border: 1px solid #e2e8f0; padding: 8px 12px; border-radius: 8px; background: #f8fafc; cursor: pointer; margin-right: 10px; }
        @media (max-width: 768px) { .prod-grid-form { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

    <aside class="sidebar">
        <a href="index.php" class="logo">
            <span><i class="ph-bold ph-qr-code"></i></span> Menuly.
        </a>
        <ul class="nav-links">
            <li class="nav-item">
                <a href="index.php"><i class="ph-bold ph-squares-four"></i> Özet</a>
            </li>
            <li class="nav-item">
                <a href="categories.php"><i class="ph-bold ph-list-dashes"></i> Kategoriler</a>
            </li>
            <li class="nav-item">
                <a href="products.php" class="active"><i class="ph-bold ph-hamburger"></i> Ürünler</a>
            </li>
            <li class="nav-item">
                <a href="destek.php"><i class="ph-bold ph-chats-circle"></i> Destek</a>
            </li>
            <li class="nav-item">
                <a href="settings.php"><i class="ph-bold ph-gear"></i> Ayarlar</a>
            </li>
            <li class="nav-item">
                <a href="logout.php" class="logout-btn"><i class="ph-bold ph-sign-out"></i> Çıkış Yap</a>
            </li>
        </ul>
    </aside>

    <main class="main-content">
        <button class="mobile-toggle" onclick="toggleSidebar()"><i class="ph-bold ph-list" style="font-size:20px;"></i> Menü</button>
        <div class="overlay" onclick="toggleSidebar()"></div>
        <script>function toggleSidebar(){document.querySelector('.sidebar').classList.toggle('active');document.querySelector('.overlay').classList.toggle('active');}</script>

        <div class="header">
            <div class="welcome">
                <h1>Ürün Yönetimi</h1>
                <p>Menünüzdeki yemekleri ve içecekleri buradan yönetin.</p>
            </div>
        </div>

        <?php echo $message; ?>

        <div class="form-card">
            <form method="POST" enctype="multipart/form-data" style="width:100%">
                <div class="prod-grid-form">
                    <div style="display:grid; gap:16px;">
                        <div class="form-group">
                            <label>Ürün Adı</label>
                            <input type="text" name="prod_name" class="form-input" placeholder="Örn: İskender Kebap" required>
                        </div>
                        <div class="form-group">
                            <label>Açıklama (İçindekiler)</label>
                            <textarea name="description" class="form-input" placeholder="Örn: Yoğurt, domates sosu ve tereyağı ile..."></textarea>
                        </div>
                    </div>
                    
                    <div style="display:grid; gap:16px;">
                        <div class="form-group">
                            <label>Kategori</label>
                            <select name="category_id" class="form-input" required>
                                <option value="">Seçiniz...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Fiyat (₺)</label>
                            <input type="number" step="0.01" name="price" class="form-input" placeholder="0.00" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Ürün Rozeti (Opsiyonel)</label>
                            <select name="badges" class="form-input">
                                <option value="">Yok</option>
                                <option value="new">🌟 Yeni</option>
                                <option value="hot">🌶️ Acı</option>
                                <option value="vegan">🌱 Vegan</option>
                                <option value="chef">👨‍🍳 Şefin Tavsiyesi</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Ürün Fotoğrafı</label>
                            <input type="file" name="image" accept="image/*" class="form-input">
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary" style="margin-top:20px; width:100%">
                    <i class="ph-bold ph-plus"></i> Ürünü Kaydet
                </button>
            </form>
        </div>

        <form method="POST">
            <input type="hidden" name="update_orders" value="1">
            
            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th width="70">Sıra</th>
                            <th width="70">Görsel</th>
                            <th>Ürün Adı</th>
                            <th>Kategori</th>
                            <th>Fiyat</th>
                            <th width="80" style="text-align:right">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($products) > 0): ?>
                            <?php foreach ($products as $prod): ?>
                                <tr>
                                    <td style="text-align:center;">
                                        <input type="number" name="order[<?php echo $prod['id']; ?>]" value="<?php echo $prod['sort_order']; ?>" class="form-input" style="padding:5px; text-align:center;">
                                    </td>
                                    <td>
                                        <?php if(!empty($prod['image'])): ?>
                                            <img src="../<?php echo $prod['image']; ?>" class="prod-img-preview">
                                        <?php else: ?>
                                            <div class="prod-img-preview" style="display:flex;align-items:center;justify-content:center;color:#ccc"><i class="ph-fill ph-image"></i></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-weight:600">
                                        <?php echo htmlspecialchars($prod['name']); ?>
                                            <?php if(!empty($prod['badges'])): ?>
                                            <?php 
                                                // Çeviri Sözlüğü
                                                $badge_names = [
                                                    'new' => 'YENİ',
                                                    'hot' => 'ACI',
                                                    'vegan' => 'VEGAN',
                                                    'chef' => 'ŞEFİN SEÇİMİ'
                                                ];
                                                // Varsa Türkçesini al, yoksa olduğu gibi yaz
                                                $badge_label = isset($badge_names[$prod['badges']]) ? $badge_names[$prod['badges']] : strtoupper($prod['badges']);
                                            ?>
                                            <span style="font-size:10px; background:#eff6ff; color:#2563eb; padding:2px 6px; border-radius:4px; margin-left:5px; border:1px solid #dbeafe; font-weight:700;">
                                                <?php echo $badge_label; ?>
                                            </span>
                                        <?php endif; ?>
                                        <div style="font-size:12px; color:#64748b; font-weight:400"><?php echo htmlspecialchars(mb_strimwidth($prod['description'], 0, 40, "...")); ?></div>
                                    </td>
                                    <td><span class="section-pill"><?php echo htmlspecialchars($prod['category_name']); ?></span></td>
                                    <td style="font-weight:700; color:var(--primary)">₺<?php echo number_format($prod['price'], 2); ?></td>
                                    <td style="text-align:right">
                                        <a href="product_edit.php?id=<?php echo $prod['id']; ?>" class="action-btn" style="color:#2563eb; background:#eff6ff; margin-right:4px;">
                                            <i class="ph-bold ph-pencil-simple"></i>
                                        </a>
                                        <a href="products.php?delete=<?php echo $prod['id']; ?>" class="action-btn delete" onclick="return confirm('Silmek istediğine emin misin?')">
                                            <i class="ph-bold ph-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding:30px; color:#94a3b8;">
                                    Henüz ürün eklenmemiş.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (count($products) > 0): ?>
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