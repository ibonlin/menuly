<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$user_id = $_SESSION['user_id'];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

// ÜRÜNÜ ÇEK
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $user_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) { header("Location: products.php"); exit; }

// KATEGORİLERİ ÇEK
$cats = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY sort_order ASC");
$cats->execute([$user_id]);
$categories = $cats->fetchAll(PDO::FETCH_ASSOC);

// MEVCUT VARYASYONLARI ÇEK
$var_stmt = $pdo->prepare("SELECT * FROM product_variations WHERE product_id = ? ORDER BY sort_order ASC, id ASC");
$var_stmt->execute([$id]);
$variations = $var_stmt->fetchAll(PDO::FETCH_ASSOC);

// --- KAYDETME İŞLEMİ (DEMO KORUMALI) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // DEMO KONTROLÜ
    if (isset($_SESSION['username']) && $_SESSION['username'] === 'demo') {
        $message = '<div class="alert success">Demo Modu: Ürün ve varyasyonlar güncellendi! (Simülasyon)</div>';
    } else {
        // GERÇEK GÜNCELLEME İŞLEMİ
        $name = trim($_POST['name']);
        $desc = trim($_POST['description']);
        $price = trim($_POST['price']);
        $cat_id = (int)$_POST['category_id'];
        $sort = (int)$_POST['sort_order'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $badges = isset($_POST['badges']) ? implode(',', $_POST['badges']) : '';

        // Resim Yükleme (Basitleştirilmiş)
        $image_path = $product['image'];
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                if (!empty($product['image']) && file_exists('../' . $product['image'])) {
                    unlink('../' . $product['image']);
                }
                $new_name = 'prod_' . $user_id . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], '../assets/uploads/' . $new_name)) {
                    $image_path = 'assets/uploads/' . $new_name;
                }
            }
        }

        // 1. ÜRÜNÜ GÜNCELLE
        $sql = "UPDATE products SET category_id=?, name=?, description=?, price=?, image=?, sort_order=?, is_active=?, badges=? WHERE id=? AND user_id=?";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$cat_id, $name, $desc, $price, $image_path, $sort, $is_active, $badges, $id, $user_id])) {
            
            // 2. VARYASYONLARI KAYDET
            $pdo->prepare("DELETE FROM product_variations WHERE product_id = ?")->execute([$id]);

            if (isset($_POST['v_name']) && is_array($_POST['v_name'])) {
                $v_sql = "INSERT INTO product_variations (product_id, name, price, sort_order) VALUES (?, ?, ?, ?)";
                $v_stmt = $pdo->prepare($v_sql);
                
                for ($i = 0; $i < count($_POST['v_name']); $i++) {
                    $v_name = trim($_POST['v_name'][$i]);
                    $v_price = trim($_POST['v_price'][$i]);
                    if (!empty($v_name)) {
                        $v_stmt->execute([$id, $v_name, $v_price, $i]);
                    }
                }
            }

            $message = '<div class="alert success">Ürün ve varyasyonlar güncellendi!</div>';
            
            // Güncel veriyi tekrar çek
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $var_stmt->execute([$id]);
            $variations = $var_stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $message = '<div class="alert error">Hata oluştu.</div>';
        }
    }
}

$current_badges = !empty($product['badges']) ? explode(',', $product['badges']) : [];
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ürün Düzenle - Menuly</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .edit-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
        .img-preview { width: 100%; height: 200px; object-fit: cover; border-radius: 12px; margin-bottom: 15px; border: 1px solid #eee; }
        
        .variation-row { display: flex; gap: 10px; align-items: center; margin-bottom: 10px; background: #f8fafc; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .variation-row input { margin: 0 !important; }
        .btn-remove-var { background: #fee2e2; color: #ef4444; border: none; width: 36px; height: 36px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
        .btn-remove-var:hover { background: #ef4444; color: white; }
        .btn-add-var { background: #eff6ff; color: #2563eb; border: 1px dashed #2563eb; width: 100%; padding: 10px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; margin-top: 10px; }
        .btn-add-var:hover { background: #2563eb; color: white; border-style: solid; }

        .badge-options { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 5px; }
        .badge-check { display: none; }
        .badge-label { 
            padding: 6px 12px; border-radius: 20px; border: 1px solid #e2e8f0; 
            font-size: 12px; font-weight: 600; color: #64748b; cursor: pointer; transition: 0.2s; display: flex; align-items: center; gap: 5px;
        }
        .badge-check:checked + .badge-label { background: #eff6ff; color: #2563eb; border-color: #2563eb; }
        
        @media (max-width: 900px) { .edit-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

    <aside class="sidebar">
        <a href="index.php" class="logo"><span><i class="ph-bold ph-qr-code"></i></span> Menuly.</a>
        <ul class="nav-links">
            <li class="nav-item"><a href="index.php"><i class="ph-bold ph-squares-four"></i> Özet</a></li>
            <li class="nav-item"><a href="categories.php"><i class="ph-bold ph-list-dashes"></i> Kategoriler</a></li>
            <li class="nav-item"><a href="products.php" class="active"><i class="ph-bold ph-hamburger"></i> Ürünler</a></li>
            <li class="nav-item"><a href="destek.php"><i class="ph-bold ph-chats-circle"></i> Destek</a></li>
            <li class="nav-item"><a href="settings.php"><i class="ph-bold ph-gear"></i> Ayarlar</a></li>
            <li class="nav-item"><a href="logout.php" class="logout-btn"><i class="ph-bold ph-sign-out"></i> Çıkış Yap</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="header" style="justify-content: flex-start; gap: 15px;">
            <a href="products.php" class="btn btn-outline" style="width:40px; height:40px; padding:0; display:flex; align-items:center; justify-content:center;"><i class="ph-bold ph-arrow-left"></i></a>
            <div class="welcome"><h1>Ürün Düzenle</h1><p>Ürün detaylarını ve seçeneklerini güncelle.</p></div>
        </div>

        <?php echo $message; ?>

        <form method="POST" enctype="multipart/form-data" class="edit-grid">
            
            <div class="form-card" style="display:block">
                <div style="margin-bottom:20px;">
                    <label style="display:block; font-weight:600; margin-bottom:5px; font-size:13px;">Ürün Adı</label>
                    <input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:20px;">
                    <div>
                        <label style="display:block; font-weight:600; margin-bottom:5px; font-size:13px;">Fiyat (TL)</label>
                        <input type="number" step="0.01" name="price" class="form-input" value="<?php echo $product['price']; ?>" required>
                    </div>
                    <div>
                        <label style="display:block; font-weight:600; margin-bottom:5px; font-size:13px;">Kategori</label>
                        <select name="category_id" class="form-input">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $cat['id'] == $product['category_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom:20px;">
                    <label style="display:block; font-weight:600; margin-bottom:5px; font-size:13px;">Açıklama</label>
                    <textarea name="description" class="form-input" style="height:100px; resize:none;"><?php echo htmlspecialchars($product['description']); ?></textarea>
                </div>

                <div style="margin-bottom:20px;">
                    <label style="display:block; font-weight:600; margin-bottom:5px; font-size:13px;">Ürün Etiketleri (Rozetler)</label>
                    <div class="badge-options">
                        <label>
                            <input type="checkbox" name="badges[]" value="new" class="badge-check" <?php echo in_array('new', $current_badges) ? 'checked' : ''; ?>>
                            <span class="badge-label"><i class="ph-bold ph-star"></i> Yeni</span>
                        </label>
                        <label>
                            <input type="checkbox" name="badges[]" value="spicy" class="badge-check" <?php echo in_array('spicy', $current_badges) ? 'checked' : ''; ?>>
                            <span class="badge-label"><i class="ph-bold ph-fire"></i> Acı</span>
                        </label>
                        <label>
                            <input type="checkbox" name="badges[]" value="vegan" class="badge-check" <?php echo in_array('vegan', $current_badges) ? 'checked' : ''; ?>>
                            <span class="badge-label"><i class="ph-bold ph-plant"></i> Vegan</span>
                        </label>
                        <label>
                            <input type="checkbox" name="badges[]" value="chef" class="badge-check" <?php echo in_array('chef', $current_badges) ? 'checked' : ''; ?>>
                            <span class="badge-label"><i class="ph-bold ph-chef-hat"></i> Şefin Seçimi</span>
                        </label>
                        <label>
                            <input type="checkbox" name="badges[]" value="gluten_free" class="badge-check" <?php echo in_array('gluten_free', $current_badges) ? 'checked' : ''; ?>>
                            <span class="badge-label"><i class="ph-bold ph-grain-slash"></i> Glutensiz</span>
                        </label>
                    </div>
                </div>

                <div style="margin-top:30px; border-top:1px solid #eee; padding-top:20px;">
                    <label style="display:block; font-weight:700; margin-bottom:10px; font-size:14px; color:#1e293b;">
                        <i class="ph-bold ph-list-plus"></i> Porsiyon / Seçenekler
                    </label>
                    
                    <div id="variations-container">
                        <?php foreach($variations as $var): ?>
                            <div class="variation-row">
                                <input type="text" name="v_name[]" value="<?php echo htmlspecialchars($var['name']); ?>" placeholder="Örn: 1.5 Porsiyon" class="form-input" style="flex:2">
                                <input type="number" step="0.01" name="v_price[]" value="<?php echo $var['price']; ?>" placeholder="Fiyat" class="form-input" style="flex:1">
                                <button type="button" class="btn-remove-var" onclick="this.parentElement.remove()"><i class="ph-bold ph-trash"></i></button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="button" class="btn-add-var" onclick="addVariation()">+ Seçenek Ekle</button>
                </div>
            </div>

            <div class="form-card" style="display:block; height:fit-content;">
                <?php if (!empty($product['image'])): ?>
                    <img src="../<?php echo $product['image']; ?>" class="img-preview">
                <?php endif; ?>
                
                <div style="margin-bottom:20px;">
                    <label style="display:block; font-weight:600; margin-bottom:5px; font-size:13px;">Ürün Görseli</label>
                    <input type="file" name="image" class="form-input" accept="image/*">
                </div>

                <div style="margin-bottom:20px;">
                    <label style="display:block; font-weight:600; margin-bottom:5px; font-size:13px;">Sıralama</label>
                    <input type="number" name="sort_order" class="form-input" value="<?php echo $product['sort_order']; ?>">
                </div>

                <div style="margin-bottom:20px; display:flex; align-items:center; gap:10px;">
                    <input type="checkbox" name="is_active" id="isActive" style="width:20px; height:20px;" <?php echo $product['is_active'] == 1 ? 'checked' : ''; ?>>
                    <label for="isActive" style="font-weight:600; font-size:14px; cursor:pointer;">Yayında (Aktif)</label>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;"><i class="ph-bold ph-floppy-disk"></i> Değişiklikleri Kaydet</button>
            </div>

        </form>
    </main>

    <script>
        function addVariation() {
            const container = document.getElementById('variations-container');
            const div = document.createElement('div');
            div.className = 'variation-row';
            div.innerHTML = `
                <input type="text" name="v_name[]" placeholder="Örn: Büyük Boy" class="form-input" style="flex:2">
                <input type="number" step="0.01" name="v_price[]" placeholder="Fiyat" class="form-input" style="flex:1">
                <button type="button" class="btn-remove-var" onclick="this.parentElement.remove()"><i class="ph-bold ph-trash"></i></button>
            `;
            container.appendChild(div);
        }
    </script>
</body>
</html>