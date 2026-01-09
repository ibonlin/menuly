<?php
session_start();
require_once '../includes/db.php';

// --- GÜVENLİK VE YARDIMCI FONKSİYONLAR ---

// 1. Yetki Kontrolü
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$user_id = $_SESSION['user_id'];

// 2. CSRF Token Oluştur
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 3. Resim Optimizasyonu Fonksiyonu
function uploadAndResizeImage($file, $target_dir) {
    $max_width = 800; 
    $quality = 80;    

    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $file_tmp = $file['tmp_name'];
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file_tmp);
    finfo_close($finfo);

    $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime_type, $allowed_types)) {
        return ['success' => false, 'error' => 'Geçersiz dosya formatı.'];
    }

    $new_name = 'prod_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $file_ext;
    $target_file = $target_dir . $new_name;

    switch ($mime_type) {
        case 'image/jpeg': $source = imagecreatefromjpeg($file_tmp); break;
        case 'image/png':  $source = imagecreatefrompng($file_tmp); break;
        case 'image/webp': $source = imagecreatefromwebp($file_tmp); break;
        default: return ['success' => false, 'error' => 'Desteklenmeyen format.'];
    }

    $width = imagesx($source);
    $height = imagesy($source);
    
    if ($width > $max_width) {
        $new_width = $max_width;
        $new_height = floor($height * ($max_width / $width));
        $virtual_image = imagecreatetruecolor($new_width, $new_height);
        
        if ($mime_type == 'image/png' || $mime_type == 'image/webp') {
            imagealphablending($virtual_image, false);
            imagesavealpha($virtual_image, true);
        }
        
        imagecopyresampled($virtual_image, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        $final_image = $virtual_image;
    } else {
        $final_image = $source;
    }

    $result = false;
    switch ($mime_type) {
        case 'image/jpeg': $result = imagejpeg($final_image, $target_file, $quality); break;
        case 'image/png':  $result = imagepng($final_image, $target_file, 8); break;
        case 'image/webp': $result = imagewebp($final_image, $target_file, $quality); break;
    }

    imagedestroy($source);
    if (isset($virtual_image)) imagedestroy($virtual_image);

    if ($result) {
        return ['success' => true, 'path' => 'assets/uploads/' . $new_name];
    } else {
        return ['success' => false, 'error' => 'Resim işlenirken hata oluştu.'];
    }
}

$message = '';

// --- İŞLEMLER ---

// A. SİLME İŞLEMİ (DEMO KORUMALI)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Güvenlik Hatası: Token geçersiz.");
    }

    // DEMO KONTROLÜ
    if (isset($_SESSION['username']) && $_SESSION['username'] === 'demo') {
        // İşlem yapılmış gibi davran ama yapma
        header("Location: products.php?msg=deleted");
        exit;
    } else {
        // GERÇEK SİLME İŞLEMİ
        $del_id = (int)$_POST['delete_id'];
        $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ? AND user_id = ?");
        $stmt->execute([$del_id, $user_id]);
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($prod) {
            if (!empty($prod['image']) && file_exists('../' . $prod['image'])) {
                unlink('../' . $prod['image']);
            }
            $pdo->prepare("DELETE FROM product_variations WHERE product_id = ?")->execute([$del_id]);
            $del_stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND user_id = ?");
            $del_stmt->execute([$del_id, $user_id]);

            header("Location: products.php?msg=deleted");
            exit;
        }
    }
}

// B. EKLEME İŞLEMİ (DEMO KORUMALI)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    
    // DEMO KONTROLÜ
    if (isset($_SESSION['username']) && $_SESSION['username'] === 'demo') {
        $message = '<div class="alert success">Demo Modu: Ürün başarıyla eklendi! (Simülasyon)</div>';
    } else {
        // GERÇEK EKLEME İŞLEMİ
        $name = trim($_POST['name']);
        $desc = trim($_POST['description']);
        $price = trim($_POST['price']);
        $cat_id = (int)$_POST['category_id'];
        $badges = isset($_POST['badges']) ? implode(',', $_POST['badges']) : '';
        
        $image_path = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $upload = uploadAndResizeImage($_FILES['image'], '../assets/uploads/');
            if ($upload['success']) {
                $image_path = $upload['path'];
            } else {
                $message = '<div class="alert error">' . $upload['error'] . '</div>';
            }
        }

        if (empty($message) && !empty($name) && !empty($price)) {
            $sql = "INSERT INTO products (user_id, category_id, name, description, price, image, badges, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 0)";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$user_id, $cat_id, $name, $desc, $price, $image_path, $badges])) {
                $message = '<div class="alert success">Ürün başarıyla eklendi!</div>';
            } else {
                $message = '<div class="alert error">Veritabanı hatası.</div>';
            }
        }
    }
}

// C. SIRALAMA GÜNCELLEME (DEMO KORUMALI)
if (isset($_POST['update_orders'])) {
    // DEMO KONTROLÜ
    if (isset($_SESSION['username']) && $_SESSION['username'] === 'demo') {
        $message = '<div class="alert success">Demo Modu: Sıralama güncellendi! (Simülasyon)</div>';
    } else {
        // GERÇEK GÜNCELLEME
        if (isset($_POST['order']) && is_array($_POST['order'])) {
            $update_stmt = $pdo->prepare("UPDATE products SET sort_order = ? WHERE id = ? AND user_id = ?");
            foreach ($_POST['order'] as $prod_id => $order_val) {
                $update_stmt->execute([(int)$order_val, (int)$prod_id, $user_id]);
            }
            $message = '<div class="alert success">Sıralama güncellendi!</div>';
        }
    }
}

// --- VERİ ÇEKME ---
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.user_id = ? 
        ORDER BY c.sort_order ASC, p.sort_order ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cat_stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY sort_order ASC");
$cat_stmt->execute([$user_id]);
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

$badge_definitions = [
    'new' => ['label' => 'YENİ', 'class' => 'badge-blue', 'icon' => 'ph-star'],
    'spicy' => ['label' => 'ACI', 'class' => 'badge-red', 'icon' => 'ph-fire'],
    'vegan' => ['label' => 'VEGAN', 'class' => 'badge-green', 'icon' => 'ph-plant'],
    'chef' => ['label' => 'ŞEF', 'class' => 'badge-orange', 'icon' => 'ph-chef-hat'],
    'gluten_free' => ['label' => 'GLUTENSİZ', 'class' => 'badge-teal', 'icon' => 'ph-grain-slash']
];
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ürünler - Menuly</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../assets/css/admin.css?v=3">
    <style>
        .badge-pill { font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; margin-right: 4px; border: 1px solid transparent; text-transform: uppercase; white-space: nowrap; }
        .badge-blue { background: #eff6ff; color: #2563eb; border-color: #dbeafe; }
        .badge-red { background: #fef2f2; color: #dc2626; border-color: #fee2e2; }
        .badge-green { background: #f0fdf4; color: #16a34a; border-color: #dcfce7; }
        .badge-orange { background: #fff7ed; color: #ea580c; border-color: #ffedd5; }
        .badge-teal { background: #f0fdfa; color: #0d9488; border-color: #ccfbf1; }

        .prod-img-thumb { width: 48px; height: 48px; border-radius: 8px; object-fit: cover; background: #f1f5f9; border: 1px solid #e2e8f0; }
        .add-form-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; align-items: start; }
        
        .badge-options { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 5px; }
        .badge-check { display: none; }
        .badge-label { padding: 8px 14px; border-radius: 20px; border: 1px solid #e2e8f0; font-size: 12px; font-weight: 600; color: #64748b; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 5px; background: #fff; }
        .badge-check:checked + .badge-label { background: #eff6ff; color: #2563eb; border-color: #2563eb; }
        .badge-label:hover { background: #f8fafc; border-color: #cbd5e1; }

        @media (max-width: 900px) { .add-form-grid { grid-template-columns: 1fr; gap: 20px; } }
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
        <div class="header">
            <div class="welcome"><h1>Ürün Yönetimi</h1><p>Menüdeki ürünleri ekle, listele ve düzenle.</p></div>
        </div>

        <?php echo $message; ?>
        <?php if(isset($_GET['msg']) && $_GET['msg']=='deleted'): ?>
            <div class="alert success">Ürün başarıyla silindi.</div>
        <?php endif; ?>

        <div class="form-card" style="margin-bottom: 30px; padding: 25px;">
            <h3 style="margin-bottom: 20px; font-size: 16px; font-weight: 700; color:#1e293b; border-bottom:1px solid #f1f5f9; padding-bottom:10px;">Hızlı Ürün Ekle</h3>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                
                <div class="add-form-grid">
                    <div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div>
                                <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px; color:#475569;">Ürün Adı</label>
                                <input type="text" name="name" class="form-input" placeholder="Örn: İskender" required>
                            </div>
                            <div>
                                <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px; color:#475569;">Fiyat (TL)</label>
                                <input type="number" step="0.01" name="price" class="form-input" placeholder="0.00" required>
                            </div>
                        </div>

                        <div style="margin-bottom: 20px;">
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px; color:#475569;">Kategori</label>
                            <select name="category_id" class="form-input" required>
                                <option value="">Seçiniz...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat['id']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div style="margin-bottom: 10px;">
                             <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px; color:#475569;">Açıklama / İçindekiler</label>
                             <textarea name="description" class="form-input" style="height: 100px; min-height:100px; resize:vertical; line-height:1.5;" placeholder="İçindekileri ve ürün detaylarını buraya yazın..."></textarea>
                        </div>
                    </div>

                    <div>
                        <div style="margin-bottom: 25px;">
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px; color:#475569;">Ürün Görseli</label>
                            <input type="file" name="image" class="form-input" accept="image/*" style="font-size:12px; padding:10px;">
                            <small style="color:#94a3b8; font-size:11px; margin-top:5px; display:block;">Otomatik sıkıştırılır (Max: 800px)</small>
                        </div>
                        
                        <div style="margin-bottom: 25px;">
                            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px; color:#475569;">Etiketler (Rozetler)</label>
                            <div class="badge-options">
                                <label><input type="checkbox" name="badges[]" value="new" class="badge-check"><span class="badge-label"><i class="ph-bold ph-star"></i> Yeni</span></label>
                                <label><input type="checkbox" name="badges[]" value="spicy" class="badge-check"><span class="badge-label"><i class="ph-bold ph-fire"></i> Acı</span></label>
                                <label><input type="checkbox" name="badges[]" value="vegan" class="badge-check"><span class="badge-label"><i class="ph-bold ph-plant"></i> Vegan</span></label>
                                <label><input type="checkbox" name="badges[]" value="chef" class="badge-check"><span class="badge-label"><i class="ph-bold ph-chef-hat"></i> Şef</span></label>
                                <label><input type="checkbox" name="badges[]" value="gluten_free" class="badge-check"><span class="badge-label"><i class="ph-bold ph-grain-slash"></i> Glutensiz</span></label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width:100%; padding: 14px;">
                            <i class="ph-bold ph-plus"></i> Ürünü Kaydet
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <form method="POST">
            <input type="hidden" name="update_orders" value="1">
            
            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th width="80" style="text-align:center;">Sıra</th>
                            <th width="70">Görsel</th>
                            <th>Ürün Detayları</th>
                            <th>Kategori</th>
                            <th>Fiyat</th>
                            <th width="100" style="text-align:right">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($products) > 0): ?>
                            <?php foreach ($products as $prod): ?>
                                <tr>
                                    <td style="text-align:center; padding: 5px;">
                                        <input type="number" name="order[<?php echo $prod['id']; ?>]" value="<?php echo htmlspecialchars($prod['sort_order']); ?>" class="form-input" style="padding:4px; text-align:center; height:36px; width:100%;">
                                    </td>
                                    <td>
                                        <?php if(!empty($prod['image'])): ?>
                                            <img src="../<?php echo htmlspecialchars($prod['image']); ?>" class="prod-img-thumb">
                                        <?php else: ?>
                                            <div class="prod-img-thumb" style="display:flex;align-items:center;justify-content:center;color:#ccc"><i class="ph-fill ph-image"></i></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight:600; font-size:14px; color:#1e293b; margin-bottom:6px; display:flex; align-items:center; flex-wrap:wrap; gap:5px;">
                                            <?php echo htmlspecialchars($prod['name']); ?>
                                            
                                            <?php if(!empty($prod['badges'])): 
                                                $badges_arr = explode(',', $prod['badges']);
                                                foreach($badges_arr as $badge_key):
                                                    if(isset($badge_definitions[$badge_key])):
                                                        $def = $badge_definitions[$badge_key];
                                            ?>
                                                <span class="badge-pill <?php echo $def['class']; ?>">
                                                    <i class="<?php echo $def['icon']; ?>"></i> <?php echo $def['label']; ?>
                                                </span>
                                            <?php 
                                                    endif;
                                                endforeach;
                                            endif; ?>
                                        </div>
                                        
                                        <div style="font-size:13px; color:#64748b; line-height:1.4;">
                                            <?php echo mb_strimwidth(htmlspecialchars($prod['description']), 0, 80, "..."); ?>
                                        </div>
                                        
                                        <?php if($prod['is_active'] == 0): ?>
                                            <div style="margin-top:4px;"><span style="font-size:10px; color:#ef4444; font-weight:700; background:#fef2f2; padding:2px 6px; border-radius:4px;">● PASİF</span></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="section-pill"><?php echo htmlspecialchars($prod['category_name']); ?></span></td>
                                    <td style="font-weight:700; color:#1e293b;">₺<?php echo number_format($prod['price'], 2); ?></td>
                                    <td style="text-align:right;">
                                        <a href="product_edit.php?id=<?php echo $prod['id']; ?>" class="action-btn" style="color:#2563eb; background:#eff6ff; display:inline-flex; width:34px; height:34px; align-items:center; justify-content:center; border-radius:8px; margin-right:4px;">
                                            <i class="ph-bold ph-pencil-simple"></i>
                                        </a>
                                        
                                        <button type="button" class="action-btn" onclick="confirmDelete(<?php echo $prod['id']; ?>)" style="color:#ef4444; background:#fef2f2; border:none; width:34px; height:34px; border-radius:8px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center;">
                                            <i class="ph-bold ph-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding:40px; color:#94a3b8;">
                                    <i class="ph-duotone ph-hamburger" style="font-size:32px; margin-bottom:10px; display:block;"></i>
                                    Henüz hiç ürün eklenmemiş.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (count($products) > 0): ?>
                <div style="margin-top: 20px; text-align: right;">
                    <button type="submit" class="btn btn-primary" style="background:#1e3a8a; padding: 12px 20px;">
                        <i class="ph-bold ph-arrows-down-up"></i> Sıralamayı Kaydet
                    </button>
                </div>
            <?php endif; ?>
        </form>
    </main>

    <form id="deleteForm" method="POST" action="products.php" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="delete_id" id="delete_id_input" value="">
    </form>

    <script>
        function confirmDelete(id) {
            if (confirm('Bu ürünü ve varsa varyasyonlarını silmek istediğinize emin misiniz?')) {
                document.getElementById('delete_id_input').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
        if (window.history.replaceState) {
            const url = new URL(window.location);
            if (url.searchParams.has('msg')) {
                url.searchParams.delete('msg');
                window.history.replaceState(null, '', url.toString());
            }
        }
    </script>
</body>
</html>