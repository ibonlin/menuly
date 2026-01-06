<?php
// Önbellek Engelleme
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once 'includes/db.php';

// SLUG AL
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

// EĞER SLUG YOKSA (Ana sayfaya girildiyse)
if (empty($slug)) {
    if (file_exists('index.html')) {
        include 'index.html';
        exit;
    } else {
        header("Location: https://menuly.net");
        exit;
    }
}

// RESTORAN ÇEK
$stmt = $pdo->prepare("SELECT * FROM users WHERE slug = ?");
$stmt->execute([$slug]);
$restaurant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$restaurant) header("Location: panel/404.php");

// --- ZİYARETÇİ SAYACI ---
$today = date("Y-m-d");
$res_id = $restaurant['id'];

// 1. Günlük Log
$check = $pdo->prepare("SELECT id FROM visit_logs WHERE user_id = ? AND visit_date = ?");
$check->execute([$res_id, $today]);

if ($check->rowCount() > 0) {
    $pdo->prepare("UPDATE visit_logs SET visit_count = visit_count + 1 WHERE user_id = ? AND visit_date = ?")->execute([$res_id, $today]);
} else {
    $pdo->prepare("INSERT INTO visit_logs (user_id, visit_date, visit_count) VALUES (?, ?, 1)")->execute([$res_id, $today]);
}

// 2. Toplam Görüntülenme
$view_upd = $pdo->prepare("UPDATE users SET views = views + 1 WHERE id = ?");
$view_upd->execute([$res_id]);

// --- TEMA RENGİ VE RESİM AYARLARI ---
$theme_color = !empty($restaurant['theme_color']) ? $restaurant['theme_color'] : '#1e3a8a';

function fixPath($path) {
    if (empty($path)) return '';
    if (!file_exists($path) && file_exists('panel/' . $path)) {
        return 'panel/' . $path;
    }
    return $path;
}

$logo_img = fixPath($restaurant['logo']);
$cover_img = fixPath($restaurant['cover_image']);

// --- OTO-KONTROL VE PASİF DURUMU ---
if (!empty($restaurant['subscription_end'])) {
    $expireDate = new DateTime($restaurant['subscription_end']);
    $today_dt = new DateTime();
    if ($today_dt > $expireDate && $restaurant['is_active'] == 1) {
        $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?")->execute([$restaurant['id']]);
        $restaurant['is_active'] = 0;
    }
}

if ($restaurant['is_active'] == 0) {
    die("<div style='display:flex;flex-direction:column;align-items:center;justify-content:center;height:100vh;font-family:\"Segoe UI\",sans-serif;text-align:center;background:#f8fafc;color:#1e293b;padding:20px;'><div style='font-size:64px;margin-bottom:20px;'>⏳</div><h1 style='margin:0 0 10px 0;font-size:24px;font-weight:800;'>Hizmet Süresi Sona Erdi</h1><p style='color:#64748b;font-size:16px;line-height:1.5;margin:0;'>Bu işletmenin dijital menü hizmet süresi dolmuştur.<br>Hizmeti yenilemek için lütfen yönetici ile iletişime geçiniz.</p><div style='margin-top:30px;font-size:12px;color:#cbd5e1;font-weight:700;'>POWERED BY MENULY.</div></div>");
}

// --- VERİLERİ ÇEKME ---

// 1. Kategoriler
$cat_stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY sort_order ASC, id DESC");
$cat_stmt->execute([$restaurant['id']]);
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Ürünler ve Varyasyonları
$prod_stmt = $pdo->prepare("
    SELECT p.*, v.id as v_id, v.name as v_name, v.price as v_price 
    FROM products p 
    LEFT JOIN product_variations v ON p.id = v.product_id 
    WHERE p.user_id = ? AND p.is_active = 1 
    ORDER BY p.sort_order ASC, p.id DESC, v.sort_order ASC
");
$prod_stmt->execute([$restaurant['id']]);
$raw_products = $prod_stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Veriyi Grupla
$products_by_category = [];
foreach ($raw_products as $row) {
    $pid = $row['id'];
    $cid = $row['category_id'];
    
    if (!isset($products_by_category[$cid][$pid])) {
        $products_by_category[$cid][$pid] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'price' => $row['price'],
            'image' => $row['image'],
            'badges' => $row['badges'],
            'category_id' => $row['category_id'],
            'variations' => []
        ];
    }
    
    if (!empty($row['v_id'])) {
        $products_by_category[$cid][$pid]['variations'][] = [
            'name' => $row['v_name'],
            'price' => $row['v_price']
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <title><?php echo htmlspecialchars($restaurant['restaurant_name']); ?> - Menü</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        :root { --primary-color: <?php echo $theme_color; ?>; }
        
        .restaurant-header {
            <?php if (!empty($cover_img)): ?>
                background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('<?php echo $cover_img; ?>') no-repeat center center / cover !important;
            <?php else: ?>
                background: var(--primary-color) !important;
            <?php endif; ?>
        }
        
        .cat-link.active {
            background-color: var(--primary-color) !important;
            color: #ffffff !important;
            border-color: var(--primary-color) !important;
        }
        
        .category-title::before { background-color: var(--primary-color) !important; }
        .category-title { color: var(--primary-color) !important; }

        #modalPrice { color: var(--primary-color) !important; background: rgba(0,0,0,0.05) !important; }
        .modal-body button { background-color: var(--primary-color) !important; color: #ffffff !important; }
        .menuly-footer a { color: var(--primary-color) !important; }
        
        .info-bar { display: flex; justify-content: center; gap: 10px; margin-top: 15px; flex-wrap: wrap; }
        .info-badge { background: rgba(255,255,255,0.25); padding: 8px 14px; border-radius: 99px; font-size: 13px; color: white; display: flex; align-items: center; gap: 6px; text-decoration: none; backdrop-filter: blur(4px); font-weight: 600; border: 1px solid rgba(255,255,255,0.2); }
        .logo-img { width: 80px; height: 80px; object-fit: contain; background: white; border-radius: 50%; padding: 4px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); margin-bottom: 10px; }

        /* VARYASYONLAR */
        .variations { margin-top: 10px; border-top: 1px dashed #e2e8f0; padding-top: 8px; width: 100%; }
        .var-item { display: flex; justify-content: space-between; font-size: 12px; color: #475569; margin-bottom: 4px; padding: 2px 0; }
        .var-name { font-weight: 600; }
        .var-price { font-weight: 700; color: var(--primary-color); }

        /* ROZET STİLLERİ */
        .badge { font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; margin-right: 3px; display: inline-block; }
        .b-new { background: #dbeafe; color: #2563eb; }
        .b-spicy { background: #fee2e2; color: #dc2626; }
        .b-vegan { background: #dcfce7; color: #16a34a; }
        .b-chef { background: #ffedd5; color: #ea580c; }
        .b-gluten_free { background: #f3f4f6; color: #4b5563; }
    </style>
</head>
<body>

    <header class="restaurant-header">
        <div class="gtranslate_wrapper"></div>
        <script>
        window.gtranslateSettings = {
            "default_language": "tr",
            "languages": ["tr", "en", "de", "ar", "ru"],
            "wrapper_selector": ".gtranslate_wrapper",
            "flag_size": 24,
            "alt_flags": { "en": "usa", "pt": "brazil" }
        }
        </script>
        <script src="https://cdn.gtranslate.net/widgets/latest/flags.js" defer></script>

        <?php if(!empty($logo_img)): ?>
            <img src="<?php echo $logo_img; ?>" class="logo-img">
        <?php else: ?>
            <div style="width:64px; height:64px; background:rgba(255,255,255,0.2); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 10px;">
                <i class="ph-fill ph-storefront" style="font-size:32px; color:white;"></i>
            </div>
        <?php endif; ?>

        <?php if ($slug === 'demo'): ?>
        <div onclick="window.open('https://menuly.net/panel/admin/demo_login.php', '_blank')" style="position: absolute; top: 20px; left: 20px; z-index: 99999; background: rgba(0, 0, 0, 0.75); color: white; padding: 5px 18px; border-radius: 50px; font-size: 13px; font-weight: 700; backdrop-filter: blur(10px); box-shadow: 0 10px 25px rgba(0,0,0,0.3); display: flex; align-items: center; gap: 8px; border: 1px solid rgba(255,255,255,0.15); transition: transform 0.2s; cursor: pointer;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
            <i class="ph-bold ph-user-gear" style="font-size: 18px; color: #fbbf24;"></i> Admin Paneli <br> (Demo)
        </div>
        <?php endif; ?>

        <h1 class="restaurant-name"><?php echo htmlspecialchars($restaurant['restaurant_name']); ?></h1>
        
        <?php if(!empty($restaurant['wifi_pass']) || !empty($restaurant['instagram'])): ?>
            <div class="info-bar">
                <?php if(!empty($restaurant['wifi_pass'])): ?>
                    <div class="info-badge" onclick="openWifiModal('<?php echo htmlspecialchars($restaurant['wifi_pass']); ?>')">
                        <i class="ph-bold ph-wifi-high"></i> WIFI
                    </div>
                <?php endif; ?>
                
                <?php if(!empty($restaurant['instagram'])): ?>
                    <a href="https://instagram.com/<?php echo $restaurant['instagram']; ?>" target="_blank" class="info-badge">
                        <i class="ph-bold ph-instagram-logo"></i> Instagram
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </header>

    <div class="category-nav-wrapper">
        <nav class="category-nav">
            <?php foreach ($categories as $index => $cat): ?>
                <?php if (isset($products_by_category[$cat['id']])): ?>
                    <a href="#cat-<?php echo $cat['id']; ?>" class="cat-link <?php echo $index === 0 ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </div>

    <div class="container">
        <div class="search-wrapper">
            <div class="search-box">
                <i class="ph-bold ph-magnifying-glass"></i>
                <input type="text" id="searchInput" onkeyup="searchProduct()" placeholder="Menüde lezzet ara...">
            </div>
        </div>

        <div id="skeleton-loader">
            <?php for($i=0; $i<5; $i++): ?>
            <div class="sk-card">
                <div class="sk-img"></div>
                <div class="sk-info"><div class="sk-line title"></div><div class="sk-line desc"></div><div class="sk-line desc-2"></div><div class="sk-line price"></div></div>
            </div>
            <?php endfor; ?>
        </div>

        <div id="real-content">
            <?php foreach ($categories as $cat): ?>
                <?php if (isset($products_by_category[$cat['id']])): ?>
                    <section id="cat-<?php echo $cat['id']; ?>" class="category-section">
                        <h2 class="category-title"><?php echo htmlspecialchars($cat['name']); ?></h2>
                        <div class="product-list">
                            <?php foreach ($products_by_category[$cat['id']] as $prod): ?>
                                <?php 
                                    $badge_config = [
                                        'new' => ['class'=>'b-new', 'text'=>'YENİ', 'icon'=>'🌟'],
                                        'spicy' => ['class'=>'b-spicy', 'text'=>'ACI', 'icon'=>'🌶️'],
                                        'vegan' => ['class'=>'b-vegan', 'text'=>'VEGAN', 'icon'=>'🌱'],
                                        'chef' => ['class'=>'b-chef', 'text'=>'ŞEFİN SEÇİMİ', 'icon'=>'👨‍🍳'],
                                        'gluten_free' => ['class'=>'b-gluten_free', 'text'=>'GLUTENSİZ', 'icon'=>'🌾']
                                    ];
                                    
                                    $b_code = isset($prod['badges']) ? $prod['badges'] : '';
                                    $prod_img = fixPath($prod['image']);
                                    
                                    // JS için düzeltilmiş ürün objesi oluştur 
                                    $js_prod = $prod;
                                    $js_prod['image'] = $prod_img; // Sabitlenmiş yolu JS'e gönder //var-price kısmının sağına + eklenebilir
                                ?>
                                <div class="product-card" onclick='openProductModal(<?php echo json_encode($js_prod); ?>)'>
                                    <?php if (!empty($prod_img)): ?>
                                        <img src="<?php echo htmlspecialchars($prod_img); ?>" class="product-img">
                                    <?php else: ?>
                                        <div class="product-img" style="display:flex;align-items:center;justify-content:center;color:#cbd5e1;"><i class="ph-fill ph-fork-knife" style="font-size:32px;"></i></div>
                                    <?php endif; ?>
                                    <div class="product-info">
                                        <div class="product-name">
                                            <?php echo htmlspecialchars($prod['name']); ?>
                                            
                                            <?php 
                                                if(!empty($b_code)) {
                                                    $badges = explode(',', $b_code);
                                                    foreach($badges as $b) {
                                                        if(isset($badge_config[$b])) {
                                                            echo '<span class="badge '.$badge_config[$b]['class'].'">'.$badge_config[$b]['icon'].' '.$badge_config[$b]['text'].'</span> ';
                                                        }
                                                    }
                                                }
                                            ?>
                                        </div>
                                        <div class="product-desc"><?php echo htmlspecialchars($prod['description']); ?></div>
                                        <div class="product-price">₺<?php echo number_format($prod['price'], 2); ?></div>

                                        <?php if(!empty($prod['variations'])): ?>
                                            <div class="variations" onclick="event.stopPropagation();">
                                                <?php foreach($prod['variations'] as $v): ?>
                                                    <div class="var-item">
                                                        <span class="var-name"><?php echo htmlspecialchars($v['name']); ?></span>
                                                        <span class="var-price"><?php echo number_format($v['price'], 2); ?>₺</span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if (count($categories) == 0): ?>
                <div style="text-align:center; padding:40px; color:#94a3b8;"><i class="ph-duotone ph-warning-circle" style="font-size:48px;"></i><p>Henüz menü eklenmemiş.</p></div>
            <?php endif; ?>

            <div id="noResults" style="display:none; text-align:center; padding:50px 20px; color:#94a3b8;">
                <i class="ph-duotone ph-magnifying-glass-question" style="font-size:64px; margin-bottom:15px; opacity:0.6;"></i>
                <h4 style="margin:0 0 5px 0; color:#64748b; font-size:18px;">Sonuç Bulunamadı</h4>
                <p style="margin:0; font-size:14px; opacity:0.8;">Aradığınız kriterlere uygun bir ürün yok.</p>
            </div>
        </div>
    </div>

    <div class="menuly-footer">Powered by <span><a href="https://menuly.net" rel="nofollow">Menuly.</a></span></div>

    <script>
        const links = document.querySelectorAll('.cat-link');
        const sections = document.querySelectorAll('.category-section');
        links.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href').substring(1);
                const targetSection = document.getElementById(targetId);
                const offsetPosition = targetSection.getBoundingClientRect().top + window.scrollY - 160;
                window.scrollTo({ top: offsetPosition, behavior: "smooth" });
            });
        });
        window.addEventListener('scroll', () => {
            let current = '';
            const scrollY = window.scrollY + 170; 
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.clientHeight;
                if (scrollY >= sectionTop && scrollY < sectionTop + sectionHeight) { current = section.getAttribute('id'); }
            });
            links.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === '#' + current) {
                    link.classList.add('active');
                    link.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                }
            });
        });

        function openWifiModal(password) {
            const modal = document.getElementById('wifiModal');
            const passText = document.getElementById('wifiPassText');
            passText.innerText = password;
            passText.setAttribute('data-pass', password);
            modal.classList.add('active');
        }
        function closeWifiModal() { document.getElementById('wifiModal').classList.remove('active'); }
        function copyWifiPass() {
            const passText = document.getElementById('wifiPassText');
            const password = passText.getAttribute('data-pass');
            navigator.clipboard.writeText(password).then(() => {
                const originalText = passText.innerText;
                passText.innerText = "Kopyalandı! ✅";
                passText.style.color = "#16a34a";
                setTimeout(() => { passText.innerText = originalText; passText.style.color = "#1e293b"; }, 1500);
            });
        }

        function openProductModal(product) {
            const modal = document.getElementById('productModal');
            document.getElementById('modalTitle').innerText = product.name;
            document.getElementById('modalDesc').innerText = product.description;
            let price = parseFloat(product.price).toLocaleString('tr-TR', {minimumFractionDigits: 2});
            document.getElementById('modalPrice').innerText = '₺' + price;
            
            const imgEl = document.getElementById('modalImg');
            
            // Düzeltilmiş Yolu Kullan
            let imgPath = product.image;

            if (imgPath && imgPath !== "") { 
                imgEl.src = imgPath; 
                imgEl.onerror = function() { this.style.display = 'none'; };
                imgEl.style.display = 'block'; 
            } else { 
                imgEl.style.display = 'none'; 
            }
            modal.classList.add('active');
        }
        function closeProductModal() { document.getElementById('productModal').classList.remove('active'); }

        function searchProduct() {
            let input = document.getElementById('searchInput').value.toLowerCase();
            let cards = document.querySelectorAll('.product-card');
            let sections = document.querySelectorAll('.category-section');
            let noResults = document.getElementById('noResults');
            let totalVisibleItems = 0;
            cards.forEach(card => {
                let name = card.querySelector('.product-name').innerText.toLowerCase();
                if (name.includes(input)) { card.style.display = "flex"; totalVisibleItems++; } else { card.style.display = "none"; }
            });
            sections.forEach(sec => {
                let visibleInSec = sec.querySelectorAll('.product-card[style="display: flex;"]').length;
                if (visibleInSec === 0 && input !== "") { sec.style.display = "none"; } else { sec.style.display = "block"; }
            });
            if (totalVisibleItems === 0 && input !== "") { noResults.style.display = "block"; } else { noResults.style.display = "none"; }
        }

        window.addEventListener('load', function() {
            setTimeout(function() {
                document.getElementById('skeleton-loader').style.display = 'none';
                document.getElementById('real-content').style.display = 'block';
            }, 800);
        });
    </script>

    <div id="wifiModal" class="modal">
        <div class="modal-backdrop" style="position:absolute; inset:0;" onclick="closeWifiModal()"></div>
        <div class="modal-content">
            <button class="modal-close" onclick="closeWifiModal()"><i class="ph-bold ph-x"></i></button>
            <div class="modal-body">
                <div class="wifi-icon-box"><i class="ph-fill ph-wifi-high"></i></div>
                <div class="wifi-title">Wifi Ağına Bağlan</div>
                <p class="wifi-desc">Aşağıdaki şifreye dokunarak kopyala.</p>
                <div class="wifi-pass-box" onclick="copyWifiPass()">
                    <div id="wifiPassText" class="wifi-pass-text">...</div>
                    <div class="wifi-copy-hint"><i class="ph-bold ph-copy"></i> Kopyalamak için tıkla</div>
                </div>
            </div>
        </div>
    </div>

    <div id="productModal" class="modal">
        <div class="modal-backdrop" onclick="closeProductModal()" style="position:absolute; inset:0;"></div>
        <div class="modal-content" style="text-align:left; overflow:hidden;">
            <button class="modal-close" onclick="closeProductModal()" style="z-index:10;"><i class="ph-bold ph-x"></i></button>
            <img id="modalImg" src="" style="width:100%; height:200px; object-fit:cover; display:block;">
            <div class="modal-body" style="padding:20px;">
                <div style="display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:10px;">
                    <h3 id="modalTitle" style="margin:0; font-size:20px; color:#1e293b; font-weight:700;"></h3>
                    <span id="modalPrice" style="background:#eff6ff; color:#1e3a8a; font-weight:800; padding:4px 10px; border-radius:8px; font-size:16px;"></span>
                </div>
                <p id="modalDesc" style="color:#64748b; font-size:14px; line-height:1.6; margin-bottom:20px;"></p>
                <button onclick="closeProductModal()" style="width:100%; background:#1e3a8a; color:white; border:none; padding:12px; border-radius:12px; font-weight:600; font-size:15px; cursor:pointer;">Tamam</button>
            </div>
        </div>
    </div>
</body>
</html>