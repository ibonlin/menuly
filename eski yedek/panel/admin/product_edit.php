<?php
require_once '../includes/db.php';

// Güvenlik
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// --- MEVCUT ÜRÜNÜ ÇEK ---
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $user_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

// Eğer ürün yoksa veya başkasınınsa geri at
if (!$product) {
    header("Location: products.php");
    exit;
}

// --- KATEGORİLERİ ÇEK (Select kutusu için) ---
$cat_stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY sort_order ASC, id DESC");
$cat_stmt->execute([$user_id]);
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

// --- GÜNCELLEME İŞLEMİ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Form verilerini al
    $category_id = $_POST['category_id'];
    $name = trim($_POST['prod_name']);
    $desc = trim($_POST['description']);
    $price = $_POST['price'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    // YENİ: Rozet verisini al (Boşsa NULL olsun)
    $badges = !empty($_POST['badges']) ? $_POST['badges'] : NULL;

    // --- DEMO KORUMASI BAŞLANGIÇ ---
    if (isset($_SESSION['username']) && $_SESSION['username'] === 'demo') {
        $message = '<div class="alert success" style="background:#fff7ed; color:#ea580c; border:1px solid #fed7aa;">
            <i class="ph-bold ph-warning"></i> Demo Modu: Ürün güncellendi! (Simülasyon)
        </div>';
        
        // Ekranda değişmiş gibi gözüksün
        $product['name'] = $name;
        $product['description'] = $desc;
        $product['price'] = $price;
        $product['category_id'] = $category_id;
        $product['is_active'] = $is_active;
        $product['badges'] = $badges; // Rozeti de simüle et
        
        goto skip_edit;
    }
    // --- DEMO KORUMASI BİTİŞ ---

    // Varsayılan olarak eski resim kalsın
    $image_path = $product['image']; 

    // Yeni resim yüklendi mi?
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $upload_dir = '../assets/uploads/';
        $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($file_ext, $allowed)) {
            // Eski resmi sunucudan sil
            if (!empty($product['image']) && file_exists('../' . $product['image'])) {
                unlink('../' . $product['image']);
            }

            // Yeni resmi yükle
            $new_name = time() . '_' . rand(1000,9999) . '.' . $file_ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $new_name)) {
                $image_path = 'assets/uploads/' . $new_name;
            }
        }
    }

    // Veritabanını Güncelle (DÜZELTİLEN KISIM)
    // SQL Sırası: category_id, badges, name, description, price, image, is_active --- id, user_id
    $update_stmt = $pdo->prepare("UPDATE products SET category_id=?, badges=?, name=?, description=?, price=?, image=?, is_active=? WHERE id=? AND user_id=?");
    
    // Veri Sırası (SQL ile birebir aynı olmalı):
    $params = [
        $category_id, 
        $badges,       // Eksik olan buydu, ekledik!
        $name, 
        $desc, 
        $price, 
        $image_path, 
        $is_active, 
        $id, 
        $user_id
    ];

    if ($update_stmt->execute($params)) {
        $message = '<div class="alert success">Ürün başarıyla güncellendi! <a href="products.php">Geri Dön</a></div>';
        
        // Yeni bilgileri ekranda göstermek için product dizisini güncelle
        $product['name'] = $name;
        $product['description'] = $desc;
        $product['price'] = $price;
        $product['category_id'] = $category_id;
        $product['image'] = $image_path;
        $product['is_active'] = $is_active;
        $product['badges'] = $badges;
    } else {
        $message = '<div class="alert error">Hata oluştu.</div>';
    }

    skip_edit:
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Ürün Düzenle - Menuly</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../assets/css/admin.css">
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
        <button class="mobile-toggle" onclick="toggleSidebar()">
        <i class="ph-bold ph-list" style="font-size:20px;"></i> Menü
    </button>
    
    <div class="overlay" onclick="toggleSidebar()"></div>

    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.querySelector('.overlay').classList.toggle('active');
        }
    </script>
        <div class="header">
            <div class="welcome">
                <h1>Ürün Düzenle</h1>
                <p><?php echo htmlspecialchars($product['name']); ?> ürününü düzenliyorsunuz.</p>
            </div>
            <a href="products.php" class="btn btn-outline"><i class="ph-bold ph-arrow-left"></i> Geri Dön</a>
        </div>

        <?php echo $message; ?>

        <div class="form-card" style="display:block;">
            <form method="POST" enctype="multipart/form-data">
                <div style="display:grid; grid-template-columns: 2fr 1fr; gap:20px;">
                    
                    <div style="display:grid; gap:16px;">
                        <div class="form-group">
                            <label>Ürün Adı</label>
                            <input type="text" name="prod_name" class="form-input" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Açıklama</label>
                            <textarea name="description" class="form-input"><?php echo htmlspecialchars($product['description']); ?></textarea>
                        </div>
                         <div class="form-group">
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                <input type="checkbox" name="is_active" <?php echo $product['is_active'] ? 'checked' : ''; ?> style="width:auto;">
                                <span>Bu ürün menüde görünsün (Stokta Var)</span>
                            </label>
                        </div>
                    </div>
                    
                    <div style="display:grid; gap:16px;">
                        <div class="form-group">
                            <label>Kategori</label>
                            <select name="category_id" class="form-input" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $cat['id'] == $product['category_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Fiyat (₺)</label>
                            <input type="number" step="0.01" name="price" class="form-input" value="<?php echo $product['price']; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Ürün Fotoğrafı</label>
                            <?php if(!empty($product['image'])): ?>
                                <div style="margin-bottom:10px;">
                                    <img src="../<?php echo $product['image']; ?>" style="width:100%; height:150px; object-fit:cover; border-radius:12px; border:1px solid #eee;">
                                    <p style="font-size:12px; color:#64748b;">Mevcut Resim</p>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="image" accept="image/*" class="form-input">
                            <p style="font-size:11px; color:#64748b; margin-top:4px;">Değiştirmek istemiyorsanız boş bırakın.</p>
                        </div>
                    </div>
                </div>

                <select name="badges" class="form-input">
    <option value="">Rozet Seçiniz</option>
    <option value="">Yok</option>
    <option value="new" <?php echo $product['badges']=='new'?'selected':''; ?>>🌟 Yeni</option>
    <option value="hot" <?php echo $product['badges']=='hot'?'selected':''; ?>>🌶️ Acı</option>
    <option value="vegan" <?php echo $product['badges']=='vegan'?'selected':''; ?>>🌱 Vegan</option>
    <option value="chef" <?php echo $product['badges']=='chef'?'selected':''; ?>>👨‍🍳 Şefin Tavsiyesi</option>
</select>
                
                <button type="submit" class="btn btn-primary" style="margin-top:20px; width:100%">
                    <i class="ph-bold ph-floppy-disk"></i> Değişiklikleri Kaydet
                </button>
            </form>
        </div>
    </main>

</body>
</html>