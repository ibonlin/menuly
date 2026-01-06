<?php
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$user_id = $_SESSION['user_id'];
$message = '';

// --- İŞLEM: AYARLARI GÜNCELLE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- DEMO KORUMASI ---
    if (isset($_SESSION['username']) && $_SESSION['username'] === 'demo') {
        $message = '<div class="alert success" style="background:#fff7ed; color:#ea580c; border:1px solid #fed7aa;">
            <i class="ph-bold ph-warning"></i> Demo Modu: Değişiklikler simülasyon amaçlıdır.
        </div>';
        goto skip_save; 
    }

    $rest_name = trim($_POST['restaurant_name']);
    $slug = trim($_POST['slug']);
    $wifi = trim($_POST['wifi_pass']);
    $insta = trim($_POST['instagram']);
    $new_pass = trim($_POST['password']);
    $theme_col = trim($_POST['theme_color']);
    
    // QR Renkleri
    $qr_fg = trim($_POST['qr_fg_color']);
    $qr_bg = trim($_POST['qr_bg_color']);
    
    // Slug Temizliği
    $slug = strtolower(str_replace(' ', '-', $slug));
    $slug = preg_replace('/[^a-z0-9-]/', '', $slug);

    // LOGO YÜKLEME
    $stmt = $pdo->prepare("SELECT logo, cover_image FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current_data = $stmt->fetch();
    $logo_path = $current_data['logo'];
    $cover_path = $current_data['cover_image'];

    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            if (!empty($logo_path) && file_exists('../' . $logo_path)) unlink('../' . $logo_path);
            $new_name = 'logo_' . $user_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], '../assets/uploads/' . $new_name)) {
                $logo_path = 'assets/uploads/' . $new_name;
            }
        }
    }

    // KAPAK FOTOĞRAFI
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            if (!empty($cover_path) && file_exists('../' . $cover_path)) unlink('../' . $cover_path);
            $new_cover = 'cover_' . $user_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['cover_image']['tmp_name'], '../assets/uploads/' . $new_cover)) {
                $cover_path = 'assets/uploads/' . $new_cover;
            }
        }
    }

    // VERİTABANI GÜNCELLEME (QR renkleri dahil, Email/Phone hariç - onlar sadece okunur)
    $sql = "UPDATE users SET restaurant_name=?, slug=?, wifi_pass=?, instagram=?, logo=?, cover_image=?, theme_color=?, qr_fg_color=?, qr_bg_color=?";
    $params = [$rest_name, $slug, $wifi, $insta, $logo_path, $cover_path, $theme_col, $qr_fg, $qr_bg];

    if (!empty($new_pass)) {
        $sql .= ", password=?";
        $params[] = $new_pass;
    }
    
    $sql .= " WHERE id=?";
    $params[] = $user_id;

    $stmt = $pdo->prepare($sql);
    if ($stmt->execute($params)) {
        $message = '<div class="alert success">Ayarlar güncellendi!</div>';
        $_SESSION['restaurant_name'] = $rest_name;
    } else {
        $message = '<div class="alert error">Bir hata oluştu.</div>';
    }
    skip_save: 
}

// VERİLERİ ÇEK
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Varsayılanlar
$current_theme_color = !empty($user['theme_color']) ? $user['theme_color'] : '#1e3a8a';
$qr_fg = !empty($user['qr_fg_color']) ? $user['qr_fg_color'] : '#000000';
$qr_bg = !empty($user['qr_bg_color']) ? $user['qr_bg_color'] : '#ffffff';

// QR API Linki
$qr_fg_clean = str_replace('#', '', $qr_fg);
$qr_bg_clean = str_replace('#', '', $qr_bg);
$base_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
$script_path = dirname(dirname($_SERVER['SCRIPT_NAME']));
if($script_path == '/') $script_path = '';
$menu_url = $base_url . $script_path . '/' . $user['slug'];
$qr_api = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($menu_url) . "&color=$qr_fg_clean&bgcolor=$qr_bg_clean&margin=10";
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Ayarlar - Menuly</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .settings-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
        .logo-preview { width: 80px; height: 80px; border-radius: 12px; object-fit: contain; background: #f8fafc; border: 1px solid #e2e8f0; margin-bottom: 10px; }
        
        /* Renk Seçiciler */
        .color-input-group { display: flex; gap: 10px; flex-wrap: wrap; }
        .color-input-wrapper { display: flex; align-items: center; border: 1px solid #e2e8f0; padding: 8px 12px; border-radius: 8px; background: white; cursor: pointer; flex: 1; }
        input[type="color"] { border: none; width: 32px; height: 32px; cursor: pointer; background: none; padding: 0; margin-right: 10px; }
        .input-label { font-size: 12px; color: #64748b; font-weight: 600; display: block; margin-bottom: 5px; }
        
        .btn-reset-color { background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b; padding: 0 15px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 5px; transition: 0.2s; height: 50px; }
        .btn-reset-color:hover { background: #e2e8f0; color: #1e293b; }

        /* Kilitli Alan Stili (Gri ve ikonlu) */
        .input-locked { background-color: #f1f5f9; color: #64748b; cursor: not-allowed; border-color: #e2e8f0; padding-right: 35px; }
        .locked-wrapper { position: relative; }
        .locked-icon { position: absolute; right: 12px; top: 38px; color: #94a3b8; pointer-events: none; font-size: 18px; }

        @media (max-width: 768px) { .settings-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

    <aside class="sidebar">
        <a href="index.php" class="logo"><span><i class="ph-bold ph-qr-code"></i></span> Menuly.</a>
        <ul class="nav-links">
            <li class="nav-item"><a href="index.php"><i class="ph-bold ph-squares-four"></i> Özet</a></li>
            <li class="nav-item"><a href="categories.php"><i class="ph-bold ph-list-dashes"></i> Kategoriler</a></li>
            <li class="nav-item"><a href="products.php"><i class="ph-bold ph-hamburger"></i> Ürünler</a></li>
            <li class="nav-item"><a href="destek.php"><i class="ph-bold ph-chats-circle"></i> Destek</a></li>
            <li class="nav-item"><a href="settings.php" class="active"><i class="ph-bold ph-gear"></i> Ayarlar</a></li>
            <li class="nav-item"><a href="logout.php" class="logout-btn"><i class="ph-bold ph-sign-out"></i> Çıkış Yap</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <button class="mobile-toggle" onclick="toggleSidebar()"><i class="ph-bold ph-list"></i> Menü</button>
        <div class="overlay" onclick="toggleSidebar()"></div>
        <script>function toggleSidebar(){document.querySelector('.sidebar').classList.toggle('active');document.querySelector('.overlay').classList.toggle('active');}</script>
        
        <div class="header">
            <div class="welcome"><h1>Restoran Ayarları</h1><p>Marka ve iletişim bilgilerinizi buradan yönetin.</p></div>
        </div>

        <?php echo $message; ?>

        <div class="settings-grid">
            <div class="form-card" style="display:block">
                <form method="POST" enctype="multipart/form-data">
                    
                    <div style="display:flex; gap:20px; margin-bottom:20px; align-items:flex-start;">
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:5px; font-size:13px;">Logo</label>
                            <?php if(!empty($user['logo'])): ?>
                                <img src="../<?php echo $user['logo']; ?>" class="logo-preview">
                            <?php else: ?>
                                <div class="logo-preview" style="display:flex;align-items:center;justify-content:center;color:#cbd5e1"><i class="ph-fill ph-image" style="font-size:30px"></i></div>
                            <?php endif; ?>
                        </div>
                        <div style="flex:1">
                            <label style="display:block; font-weight:600; margin-bottom:5px; font-size:13px;">Logo Yükle</label>
                            <input type="file" name="logo" accept="image/*" class="form-input">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px;">
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:5px; font-size:13px;">Restoran Adı</label>
                            <input type="text" name="restaurant_name" class="form-input" value="<?php echo htmlspecialchars($user['restaurant_name']); ?>" required>
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:5px; font-size:13px;">Link (Slug)</label>
                            <input type="text" name="slug" class="form-input" value="<?php echo htmlspecialchars($user['slug']); ?>" required>
                        </div>
                    </div>

                    <div style="margin-bottom:20px; padding:20px; background:#f8fafc; border-radius:12px; border:1px solid #e2e8f0;">
                        <label style="display:block; font-weight:700; margin-bottom:15px; font-size:14px; color:#1e293b; display:flex; align-items:center; gap:5px;">
                            <i class="ph-fill ph-user-circle"></i> Üyelik Bilgileri 
                            <span style="font-size:11px; font-weight:400; color:#64748b; margin-left:auto; display:none; sm:display:inline;">(Değiştirmek için yöneticiyle iletişime geçin)</span>
                        </label>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="locked-wrapper">
                                <label style="display:block; font-weight:600; margin-bottom:5px; font-size:13px;">E-Posta Adresi</label>
                                <input type="text" value="<?php echo htmlspecialchars($user['email'] ?? '-'); ?>" class="form-input input-locked" readonly>
                                <i class="ph-bold ph-lock-key locked-icon"></i>
                            </div>
                            <div class="locked-wrapper">
                                <label style="display:block; font-weight:600; margin-bottom:5px; font-size:13px;">Telefon Numarası</label>
                                <input type="text" value="<?php echo htmlspecialchars($user['phone'] ?? '-'); ?>" class="form-input input-locked" readonly>
                                <i class="ph-bold ph-lock-key locked-icon"></i>
                            </div>
                        </div>
                    </div>

                    <div style="margin-bottom:20px;">
                        <label style="display:block; font-weight:600; margin-bottom:5px; font-size:13px;">Tema Rengi</label>
                        <div style="display:flex; gap:10px;">
                            <div class="color-input-wrapper">
                                <input type="color" id="themeColor" name="theme_color" value="<?php echo htmlspecialchars($current_theme_color); ?>">
                                <span style="font-size:13px; color:#64748b; font-weight:500; margin-left: 5px;">Markanızın ana rengi</span>
                            </div>
                            <button type="button" onclick="resetTheme()" class="btn-reset-color"><i class="ph-bold ph-arrow-counter-clockwise"></i> Sıfırla</button>
                        </div>
                    </div>

                    <div style="margin-bottom:20px; padding:20px; background:#f8fafc; border-radius:12px; border:1px solid #e2e8f0;">
                        <label style="display:block; font-weight:700; margin-bottom:10px; font-size:14px; color:#1e293b;">
                            <i class="ph-fill ph-qr-code"></i> QR Kod Özelleştirme
                        </label>
                        <div class="color-input-group">
                            <div style="flex:1; min-width: 120px;">
                                <span class="input-label">QR Rengi</span>
                                <div class="color-input-wrapper">
                                    <input type="color" id="qrFg" name="qr_fg_color" value="<?php echo htmlspecialchars($qr_fg); ?>">
                                </div>
                            </div>
                            <div style="flex:1; min-width: 120px;">
                                <span class="input-label">Arka Plan</span>
                                <div class="color-input-wrapper">
                                    <input type="color" id="qrBg" name="qr_bg_color" value="<?php echo htmlspecialchars($qr_bg); ?>">
                                </div>
                            </div>
                            <div style="display: flex; align-items: flex-end;">
                                <button type="button" onclick="resetQR()" class="btn-reset-color" style="height: 50px;">
                                    <i class="ph-bold ph-arrow-counter-clockwise"></i> Sıfırla
                                </button>
                            </div>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px;">
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:5px; font-size:13px;"><i class="ph-bold ph-wifi-high"></i> Wifi Şifresi</label>
                            <input type="text" name="wifi_pass" class="form-input" value="<?php echo htmlspecialchars($user['wifi_pass']); ?>">
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:5px; font-size:13px;"><i class="ph-bold ph-instagram-logo"></i> Instagram</label>
                            <input type="text" name="instagram" class="form-input" placeholder="Örn: nusret" value="<?php echo htmlspecialchars($user['instagram']); ?>">
                        </div>
                    </div>

                    <div style="margin-bottom:20px;">
                        <label style="display:block; font-weight:600; margin-bottom:5px; font-size:13px;">Kapak Fotoğrafı</label>
                        <input type="file" name="cover_image" accept="image/*" class="form-input">
                        <p style="font-size:11px; color:#64748b; margin-top:5px;">Önerilen: 1200x600 px</p>
                        <?php if(!empty($user['cover_image'])): ?>
                            <p style="font-size:11px; color:#16a34a; margin-top:5px;"><i class="ph-bold ph-check"></i> Yüklü.</p>
                        <?php endif; ?>
                    </div>

                    <div style="margin-bottom:20px;">
                        <label style="display:block; font-weight:600; margin-bottom:5px; font-size:13px;">Yeni Şifre</label>
                        <input type="password" name="password" class="form-input" placeholder="Değiştirmek için yazın...">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%"><i class="ph-bold ph-floppy-disk"></i> Kaydet</button>
                </form>
            </div>

            <div class="stat-card qr-card" style="display:flex; flex-direction:column;">
                <h3 style="margin:0 0 10px 0; font-size:18px;">QR Menü</h3>
                <img src="<?php echo $qr_api; ?>" style="width:180px; border-radius:12px; margin-bottom:15px; border:1px solid #eee; align-self:center;">
                <a href="<?php echo $menu_url; ?>" target="_blank" style="font-size:13px; color:#2563eb; text-decoration:none; margin-bottom:15px; word-break:break-all; text-align:center;">
                    <?php echo $menu_url; ?> <i class="ph-bold ph-arrow-square-out"></i>
                </a>
                <a href="<?php echo $qr_api; ?>" download="qr.png" target="_blank" class="btn btn-outline" style="width:100%; justify-content:center;">İndir</a>
            </div>
        </div>
    </main>

    <script>
        function resetTheme() { document.getElementById('themeColor').value = '#1e3a8a'; }
        function resetQR() { document.getElementById('qrFg').value = '#000000'; document.getElementById('qrBg').value = '#ffffff'; }
    </script>
</body>
</html>