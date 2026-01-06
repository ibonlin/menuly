<?php
require_once '../includes/db.php';

// Güvenlik
if (!isset($_SESSION['master_id'])) {
    header("Location: login.php");
    exit;
}

// --- İŞLEM 1: HESAP DONDUR / AÇ (Toggle) ---
if (isset($_GET['toggle_status'])) {
    $uid = (int)$_GET['toggle_status'];
    $stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $current = $stmt->fetchColumn();
    $new_status = ($current == 1) ? 0 : 1;
    $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$new_status, $uid]);
    header("Location: index.php");
    exit;
}

// --- İŞLEM 2: SİLME ---
if (isset($_GET['delete_user'])) {
    $del_id = (int)$_GET['delete_user'];
    $stmt = $pdo->prepare("SELECT image FROM products WHERE user_id = ?");
    $stmt->execute([$del_id]);
    $images = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($images as $img) {
        if (!empty($img) && file_exists('../' . $img)) unlink('../' . $img);
    }
    $pdo->prepare("DELETE FROM products WHERE user_id = ?")->execute([$del_id]);
    $pdo->prepare("DELETE FROM categories WHERE user_id = ?")->execute([$del_id]);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$del_id]);
    header("Location: index.php?msg=deleted");
    exit;
}

// İstatistikler
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_active = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
// Bekleyen Destek Sayısı (YENİ)
$pending_tickets = $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status IN ('open', 'customer_reply')")->fetchColumn();
// Listeleme
$users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Master Admin - Menuly</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        body { margin: 0; background: #f3f4f6; font-family: 'Plus Jakarta Sans', sans-serif; color: #1f2937; }
        .topbar { background: #111827; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-weight: 800; font-size: 20px; color: #2563eb; text-decoration: none;}
        .logout { color: #f87171; text-decoration: none; font-size: 14px; font-weight: 600; }
        .container { max-width: 1100px; margin: 30px auto; padding: 0 20px; }
        
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .card { background: white; padding: 25px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 20px; }
        .icon { width: 60px; height: 60px; background: #eff6ff; color: #2563eb; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 30px; }
        .num { font-size: 32px; font-weight: 800; margin: 0; line-height: 1; }
        .label { color: #6b7280; font-size: 14px; margin: 5px 0 0 0; font-weight: 500; }
        
        .table-box { background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f9fafb; text-align: left; padding: 15px; font-size: 13px; color: #6b7280; text-transform: uppercase; font-weight: 600; }
        td { padding: 15px; border-top: 1px solid #e5e7eb; font-size: 14px; vertical-align: middle; }
        tr:hover td { background: #f9fafb; }
        
        .badge { padding: 4px 10px; border-radius: 99px; font-size: 12px; font-weight: 700; display: inline-block; }
        .badge.active { background: #dcfce7; color: #166534; }
        .badge.passive { background: #fee2e2; color: #991b1b; }

        .btn-icon { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; text-decoration: none; transition: 0.2s; }
        .btn-edit { background: #eff6ff; color: #2563eb; }
        .btn-edit:hover { background: #2563eb; color: white; }
        .btn-ban { background: #fff7ed; color: #ea580c; }
        .btn-ban:hover { background: #ea580c; color: white; }
        .btn-delete { background: #fef2f2; color: #ef4444; }
        .btn-delete:hover { background: #ef4444; color: white; }
        
        /* Buton Grubu */
        .action-bar { margin-bottom: 20px; display: flex; justify-content: flex-end; gap: 15px; }
        .btn-primary { background:#2563eb; color:white; text-decoration:none; padding:12px 20px; border-radius:10px; font-weight:700; display:inline-flex; align-items:center; gap:8px; box-shadow:0 4px 10px rgba(37, 99, 235, 0.3); transition:0.2s; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-support { background:#4f46e5; color:white; text-decoration:none; padding:12px 20px; border-radius:10px; font-weight:700; display:inline-flex; align-items:center; gap:8px; box-shadow:0 4px 10px rgba(79, 70, 229, 0.3); transition:0.2s; }
        .btn-support:hover { background: #4338ca; }
    </style>
</head>
<body>

    <div class="topbar">
        <a href="index.php" class="logo">MASTER PANEL</a>
        <div style="display:flex; gap:20px; align-items:center;">
            <a href="profile.php" style="color:white; text-decoration:none; font-size:14px;"><i class="ph-bold ph-user"></i> Profilim</a>
            <a href="logout.php" class="logout">Çıkış</a>
        </div>
    </div>
<div class="card" style="background:white; padding:20px; border-radius:12px; border:1px solid #e2e8f0; margin-top:20px;">
    <h3 style="margin-top:0;">🖥️ Sistem Sağlık Durumu</h3>
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
        <div style="background:#f8fafc; padding:10px; border-radius:8px;">
            <span style="font-size:12px; color:#64748b;">PHP Sürümü</span>
            <div style="font-weight:bold;"><?php echo phpversion(); ?></div>
        </div>
        <div style="background:#f8fafc; padding:10px; border-radius:8px;">
            <span style="font-size:12px; color:#64748b;">Sunucu Yazılımı</span>
            <div style="font-weight:bold;"><?php echo $_SERVER['SERVER_SOFTWARE']; ?></div>
        </div>
        <div style="background:#f8fafc; padding:10px; border-radius:8px;">
            <span style="font-size:12px; color:#64748b;">Toplam Kullanıcı</span>
            <div style="font-weight:bold; color:#2563eb;"><?php echo $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(); ?></div>
        </div>
        <div style="background:#f8fafc; padding:10px; border-radius:8px;">
            <span style="font-size:12px; color:#64748b;">Disk Alanı (Tahmini)</span>
            <div style="font-weight:bold;"><?php echo round(disk_free_space("/") / 1024 / 1024 / 1024, 2) . " GB Boş"; ?></div>
        </div>
    </div>
</div>
    <div class="container">
        
        <div class="stats">
            <div class="card">
                <div class="icon"><i class="ph-fill ph-storefront"></i></div>
                <div><h3 class="num"><?php echo $total_users; ?></h3><p class="label">Toplam Restoran</p></div>
            </div>
            <div class="card">
                <div class="icon" style="background:#ecfdf5; color:#059669;"><i class="ph-fill ph-check-circle"></i></div>
                <div><h3 class="num"><?php echo $total_active; ?></h3><p class="label">Aktif Üyelik</p></div>
            </div>
            <div class="card">
                <div class="icon" style="background:#fefce8; color:#ca8a04;"><i class="ph-fill ph-chats-circle"></i></div>
                <div><h3 class="num"><?php echo $pending_tickets; ?></h3><p class="label">Bekleyen Destek</p></div>
            </div>
        </div>
        <div class="action-bar">
              <a href="duyurular.php" class="btn-support" style="background:#f59e0b; box-shadow:0 4px 10px rgba(245, 158, 11, 0.3);">
                <i class="ph-bold ph-megaphone"></i> Duyurular
            </a>
            <a href="destek.php" class="btn-support">
                <i class="ph-bold ph-chats-circle"></i> Destek Merkezi
                <?php if($pending_tickets > 0): ?>
                    <span style="background:white; color:#4f46e5; font-size:11px; padding:2px 6px; border-radius:10px;"><?php echo $pending_tickets; ?></span>
                <?php endif; ?>
            </a>
            
            <a href="user_add.php" class="btn-primary">
                <i class="ph-bold ph-plus"></i> Yeni Restoran Ekle
            </a>
        </div>

        <div class="table-box">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Restoran</th>
                        <th>Kalan Süre</th> <th>Durum</th>
                        <th style="text-align:right">Yönetim</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $u): ?>
                        
                    <?php
                        $remaining_days = "Sınırsız";
                        $days_color = "#6b7280";
                        $is_expired = false;

                        if (!empty($u['subscription_end'])) {
                            $end_date = new DateTime($u['subscription_end']);
                            $today = new DateTime();
                            $today->setTime(0, 0, 0);
                            $end_date->setTime(0, 0, 0);

                            if ($end_date < $today) {
                                $remaining_days = "SÜRESİ BİTTİ";
                                $days_color = "#ef4444";
                                $is_expired = true;
                            } else {
                                $diff = $today->diff($end_date);
                                $days = $diff->days;
                                $remaining_days = $days . " Gün";
                                if ($days < 7) $days_color = "#ea580c";
                                else $days_color = "#16a34a";
                            }
                        }
                    ?>

                    <tr style="<?php echo ($u['is_active'] == 0 || $is_expired) ? 'background:#fef2f2;' : ''; ?>">
                        <td>#<?php echo $u['id']; ?></td>
                        <td>
                            <div style="font-weight:700"><?php echo htmlspecialchars($u['restaurant_name']); ?></div>
                            <div style="font-size:12px; color:#6b7280;">/<?php echo $u['slug']; ?></div>
                        </td>
                        
                        <td>
                            <span style="font-weight:700; color:<?php echo $days_color; ?>; background:white; padding:4px 8px; border:1px solid #eee; border-radius:6px;">
                                <?php echo $remaining_days; ?>
                            </span>
                        </td>

                        <td>
                            <?php if($u['is_active'] == 1): ?>
                                <span class="badge active">Aktif</span>
                            <?php else: ?>
                                <span class="badge passive">Donduruldu</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right; display:flex; justify-content:flex-end; gap:8px;">
                            <a href="login_as.php?id=<?php echo $u['id']; ?>" target="_blank" class="btn-action" title="Restoran Olarak Gir">
    <i class="ph-bold ph-sign-in"></i> Yönet
</a>
                            <a href="../<?php echo $u['slug']; ?>" target="_blank" class="btn-icon btn-edit" title="Menüyü Gör"><i class="ph-bold ph-eye"></i></a>
                            <a href="user_edit.php?id=<?php echo $u['id']; ?>" class="btn-icon btn-edit" title="Süre Uzat / Düzenle"><i class="ph-bold ph-pencil-simple"></i></a>
                            <a href="index.php?toggle_status=<?php echo $u['id']; ?>" class="btn-icon btn-ban" title="Hesabı Dondur/Aç"><i class="ph-bold <?php echo $u['is_active'] == 1 ? 'ph-lock-key' : 'ph-lock-key-open'; ?>"></i></a>
                            <a href="index.php?delete_user=<?php echo $u['id']; ?>" class="btn-icon btn-delete" onclick="return confirm('Silmek istediğine emin misin?')" title="Sil"><i class="ph-bold ph-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>