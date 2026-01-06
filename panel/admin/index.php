<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$user_id = $_SESSION['user_id'];
$restaurant_name = $_SESSION['restaurant_name'];

// --- VERİLERİ ÇEK ---
$stmt = $pdo->prepare("SELECT views, slug, subscription_end, last_read_announcement_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

$current_views = $user_data['views'];
$my_slug = $user_data['slug'];
$sub_end = $user_data['subscription_end'];
$last_read_at = $user_data['last_read_announcement_at'];

$total_categories = $pdo->query("SELECT COUNT(*) FROM categories WHERE user_id = $user_id")->fetchColumn();
$total_products = $pdo->query("SELECT COUNT(*) FROM products WHERE user_id = $user_id")->fetchColumn();

// --- BİLDİRİMLERİ ÇEK ---
$notif_stmt = $pdo->prepare("SELECT * FROM announcements WHERE (target_user_id = 0 OR target_user_id = ?) ORDER BY created_at DESC LIMIT 15");
$notif_stmt->execute([$user_id]);
$notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);

// Okunmamışları Say
$unread_count = 0;
foreach($notifications as $n) {
    if ($last_read_at == NULL || $n['created_at'] > $last_read_at) {
        $unread_count++;
    }
}

// Abonelik Durumu
$days_left = "Sınırsız";
$alert_color = "success"; 
if ($sub_end) {
    $end_date = new DateTime($sub_end);
    $today_dt = new DateTime();
    $today_dt->setTime(0,0,0); $end_date->setTime(0,0,0);
    if ($end_date < $today_dt) {
        $days_left = "SÜRENİZ DOLDU!";
        $alert_color = "danger";
    } else {
        $days_left = $today_dt->diff($end_date)->days . " Gün Kaldı";
        if ($today_dt->diff($end_date)->days < 7) $alert_color = "warning";
    }
}

// --- GRAFİK VERİLERİ ---
$chart_labels = []; $chart_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $stmt = $pdo->prepare("SELECT visit_count FROM visit_logs WHERE user_id = ? AND visit_date = ?");
    $stmt->execute([$user_id, $date]);
    $chart_labels[] = date('d.m', strtotime("-$i days"));
    $chart_data[] = $stmt->fetchColumn() ?: 0;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetim Paneli | Menuly</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container { background: white; border-radius: 16px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-top: 24px; border: 1px solid #e5e7eb; }
        .chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .chart-badge { background: #eff6ff; color: #2563eb; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 20px; }
        
        .header-actions { display: flex; align-items: center; gap: 12px; }
        
        /* Bildirim Kapsayıcısı */
        .notif-wrapper { position: relative; }

        /* Zil Butonu */
        .notif-btn { 
            position: relative; width: 44px; height: 44px; border-radius: 12px; background: white; border: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: center; font-size: 22px; color: #6b7280; cursor: pointer; transition: all 0.2s;
        }
        .notif-btn:hover { background: #f9fafb; color: #2563eb; border-color: #bfdbfe; }
        .notif-btn.active { background: #eff6ff; color: #2563eb; border-color: #2563eb; }
        
        @keyframes bellShake { 0% { transform: rotate(0); } 15% { transform: rotate(5deg); } 30% { transform: rotate(-5deg); } 45% { transform: rotate(4deg); } 60% { transform: rotate(-4deg); } 75% { transform: rotate(2deg); } 85% { transform: rotate(-2deg); } 100% { transform: rotate(0); } }
        .notif-btn.has-unread i { animation: bellShake 2s infinite; color: #2563eb; }

        .notif-badge { position: absolute; top: -4px; right: -4px; background: #ef4444; color: white; font-size: 10px; font-weight: 700; min-width: 18px; height: 18px; border-radius: 9px; display: flex; align-items: center; justify-content: center; border: 2px solid #fff; box-shadow: 0 2px 4px rgba(239, 68, 68, 0.2); padding: 0 4px; }

        /* --- DROPDOWN (TAM MOBİL UYUMLU) --- */
        .notif-dropdown { 
            position: absolute; 
            top: 60px; 
            right: 0; 
            width: 360px; 
            background: white; 
            border-radius: 16px; 
            box-shadow: 0 20px 40px -5px rgba(0,0,0,0.15), 0 10px 20px -5px rgba(0,0,0,0.05); 
            border: 1px solid #f3f4f6; 
            z-index: 9999; 
            opacity: 0; 
            visibility: hidden; 
            transform: translateY(10px); 
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1); 
        }
        .notif-dropdown.show { opacity: 1; visibility: visible; transform: translateY(0); }
        
        /* MOBİL İÇİN KRİTİK AYARLAR */
        @media (max-width: 768px) {
            /* Kapsayıcıların pozisyonunu serbest bırak */
            .header-actions, .notif-wrapper { position: static !important; }
            
            /* Dropdown'u ekrana göre hizala */
            .notif-dropdown {
                width: auto !important; /* Genişlik serbest */
                left: 15px !important;  /* Soldan 15px */
                right: 15px !important; /* Sağdan 15px */
                top: 85px !important;   /* Header altına */
                transform: none !important;
            }
        }

        .notif-header { padding: 14px 16px; border-bottom: 1px solid #f3f4f6; display: flex; justify-content: space-between; align-items: center; background: #ffffff; border-radius: 16px 16px 0 0; }
        .notif-title { font-weight: 700; font-size: 14px; color: #111827; }
        
        .mark-read-link { 
            font-size: 11px; color: #2563eb; font-weight: 700; cursor: pointer; text-decoration: none; 
            background: #eff6ff; padding: 6px 12px; border-radius: 8px; white-space: nowrap;
        }
        .mark-read-link:hover { background: #dbeafe; }

        .notif-list { max-height: 350px; overflow-y: auto; }
        .notif-item { padding: 14px 16px; border-bottom: 1px solid #f9fafb; transition: 0.2s; display: flex; gap: 12px; align-items: flex-start; }
        .notif-item:hover { background: #f8fafc; }
        .notif-item.unread { background: #eff6ff; }
        .n-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 16px; }
        .n-content h4 { margin: 0 0 2px 0; font-size: 13px; font-weight: 700; color: #1f2937; }
        .n-content p { margin: 0; font-size: 12px; color: #6b7280; line-height: 1.4; }
        .n-time { font-size: 10px; color: #9ca3af; margin-top: 4px; display: block; }

        .nt-info { color: #2563eb; background: #dbeafe; }
        .nt-warning { color: #ea580c; background: #ffedd5; }
        .nt-danger { color: #dc2626; background: #fee2e2; }
        .nt-success { color: #16a34a; background: #dcfce7; }
        
        .btn-view-menu { height: 44px; padding: 0 20px; border-radius: 12px; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 8px; background: white; border: 1px solid #e5e7eb; color: #374151; text-decoration: none; transition: 0.2s; }
        .btn-view-menu:hover { border-color: #dbeafe; color: #2563eb; background: #f9fafb; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <a href="index.php" class="logo"><span><i class="ph-bold ph-qr-code"></i></span> Menuly.</a>
        <ul class="nav-links">
            <li class="nav-item"><a href="index.php" class="active"><i class="ph-bold ph-squares-four"></i> Özet</a></li>
            <li class="nav-item"><a href="categories.php"><i class="ph-bold ph-list-dashes"></i> Kategoriler</a></li>
            <li class="nav-item"><a href="products.php"><i class="ph-bold ph-hamburger"></i> Ürünler</a></li>
            <li class="nav-item"><a href="destek.php"><i class="ph-bold ph-chats-circle"></i> Destek</a></li>
            <li class="nav-item"><a href="settings.php"><i class="ph-bold ph-gear"></i> Ayarlar</a></li>
            <li class="nav-item"><a href="logout.php" class="logout-btn"><i class="ph-bold ph-sign-out"></i> Çıkış Yap</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <button class="mobile-toggle" onclick="toggleSidebar()"><i class="ph-bold ph-list"></i> Menü</button>
        <div class="overlay" onclick="toggleSidebar()"></div>
        
        <div class="header" style="display:flex; justify-content:space-between; align-items:center;">
            <div class="welcome">
                <h1>Hoş geldin, <?php echo htmlspecialchars($restaurant_name); ?> 👋</h1>
                <p>Menü yönetim panelindesin.</p>
            </div>
            
            <div class="header-actions">
                <div class="notif-wrapper">
                    <div class="notif-btn <?php echo ($unread_count > 0) ? 'has-unread' : ''; ?>" onclick="toggleNotif()" id="bellBtn">
                        <i class="ph-bold ph-bell"></i>
                        <?php if($unread_count > 0): ?>
                            <div class="notif-badge" id="badgeCount"><?php echo $unread_count; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="notif-dropdown" id="notifDropdown">
                        <div class="notif-header">
                            <span class="notif-title">Bildirimler</span>
                            <?php if($unread_count > 0): ?>
                                <span class="mark-read-link" onclick="markAllRead()">Tümünü okundu işaretle</span>
                            <?php endif; ?>
                        </div>
                        <div class="notif-list">
                            <?php if(count($notifications) > 0): ?>
                                <?php foreach($notifications as $notif): ?>
                                    <?php 
                                        $is_unread = ($last_read_at == NULL || $notif['created_at'] > $last_read_at);
                                        $unreadClass = $is_unread ? 'unread' : '';
                                        $icon = "ph-info"; $color = "nt-info";
                                        if($notif['type']=='warning') { $icon="ph-warning"; $color="nt-warning"; }
                                        if($notif['type']=='danger') { $icon="ph-warning-circle"; $color="nt-danger"; }
                                        if($notif['type']=='success') { $icon="ph-check-circle"; $color="nt-success"; }
                                    ?>
                                    <div class="notif-item <?php echo $unreadClass; ?>">
                                        <div class="n-icon <?php echo $color; ?>"><i class="ph-fill <?php echo $icon; ?>"></i></div>
                                        <div class="n-content">
                                            <h4><?php echo htmlspecialchars($notif['title']); ?></h4>
                                            <p><?php echo nl2br(htmlspecialchars($notif['message'])); ?></p>
                                            <span class="n-time"><?php echo date("d.m H:i", strtotime($notif['created_at'])); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="padding:30px; text-align:center; color:#9ca3af; font-size:13px;">
                                    <i class="ph-duotone ph-bell-slash" style="font-size:24px; margin-bottom:5px; display:block;"></i>
                                    <span style="font-size:12px;">Henüz yeni bildirim yok.</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <a href="../<?php echo $my_slug; ?>" target="_blank" class="btn-view-menu">
                    <i class="ph-bold ph-eye"></i> <span class="desktop-only">Menüyü Gör</span>
                </a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="ph-fill ph-list-dashes"></i></div>
                <div class="stat-info"><h3><?php echo $total_categories; ?></h3><p>Toplam Kategori</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fff7ed; color:#ea580c;"><i class="ph-fill ph-hamburger"></i></div>
                <div class="stat-info"><h3><?php echo $total_products; ?></h3><p>Toplam Ürün</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#f0fdf4; color:#16a34a;"><i class="ph-fill ph-eye"></i></div>
                <div class="stat-info"><h3><?php echo number_format($current_views); ?></h3><p>Görüntülenme</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#f3f4f6; color:#4b5563;"><i class="ph-fill ph-clock-countdown"></i></div>
                <div class="stat-info">
                    <h3 style="font-size:18px; color:<?php echo ($alert_color == 'danger') ? '#ef4444' : (($alert_color == 'warning') ? '#ea580c' : '#16a34a'); ?>;">
                        <?php echo $days_left; ?>
                    </h3>
                    <p>Abonelik</p>
                </div>
            </div>
        </div>

        <div class="chart-container">
            <div class="chart-header"><h3>📈 Haftalık Analiz</h3><span class="chart-badge">Son 7 Gün</span></div>
            <div style="height: 300px;"><canvas id="viewsChart"></canvas></div>
        </div>
    </main>

    <script>
        function toggleNotif() {
            document.getElementById('notifDropdown').classList.toggle('show');
            document.getElementById('bellBtn').classList.toggle('active');
        }
        
        // Dışarı tıklayınca kapat
        document.addEventListener('click', function(e) {
            const wrapper = document.querySelector('.notif-wrapper');
            if (!wrapper.contains(e.target)) {
                document.getElementById('notifDropdown').classList.remove('show');
                document.getElementById('bellBtn').classList.remove('active');
            }
        });

        // OKUNDU İŞARETLE (Esnek Metod)
        function markAllRead() {
            // Hem GET hem POST deneyebiliriz ama sunucu ayarı ne olursa olsun çalışsın diye 
            // basit bir POST isteği atıyoruz.
            fetch('mark_read.php', { method: 'POST' })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    const badge = document.getElementById('badgeCount');
                    if(badge) badge.remove();
                    
                    document.getElementById('bellBtn').classList.remove('has-unread');
                    
                    const link = document.querySelector('.mark-read-link');
                    if(link) link.style.display = 'none';
                    
                    document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
                } else {
                    console.error('Sunucu Hatası:', data.error);
                    alert("Hata: " + data.error);
                }
            })
            .catch(err => {
                console.error('Bağlantı Hatası:', err);
                // Hata olsa bile görsel olarak temizleyelim (kullanıcı deneyimi için)
                // İstersen burayı silebilirsin.
            });
        }

        function toggleSidebar() { document.querySelector('.sidebar').classList.toggle('active'); document.querySelector('.overlay').classList.toggle('active'); }

        const ctx = document.getElementById('viewsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Ziyaretçi',
                    data: <?php echo json_encode($chart_data); ?>,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });
    </script>
    <style>@media(min-width:768px){.desktop-only{display:inline-block !important;}}</style>
</body>
</html>