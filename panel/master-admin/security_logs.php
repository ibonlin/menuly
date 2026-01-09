<?php
session_start();
require_once '../includes/db.php';

// Güvenlik Kontrolü
if (!isset($_SESSION['master_id'])) {
    header("Location: login.php");
    exit;
}

// Logları Temizle (Opsiyonel: Sadece 30 günden eskiler silinir)
if (isset($_GET['action']) && $_GET['action'] == 'cleanup') {
    $pdo->query("DELETE FROM access_logs WHERE login_time < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $msg = '<div class="alert success">30 günden eski loglar temizlendi.</div>';
}

// Logları Çek (Kullanıcı adlarıyla birleştirerek)
// Not: En son giren en üstte gözükür
$sql = "SELECT l.*, u.username, u.restaurant_name 
        FROM access_logs l 
        LEFT JOIN users u ON l.user_id = u.id 
        ORDER BY l.login_time DESC LIMIT 100";
$logs = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Güvenlik Logları - Master Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .log-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .log-table th, .log-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .log-table th { background: #f8fafc; font-weight: 700; color: #475569; }
        .log-table tr:hover { background: #f8fafc; }
        
        .device-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 8px; border-radius: 6px; font-weight: 600; font-size: 11px; }
        .dev-mobile { background: #fff7ed; color: #ea580c; }
        .dev-pc { background: #eff6ff; color: #2563eb; }
        .dev-tablet { background: #f0fdf4; color: #16a34a; }

        .ip-link { color: #64748b; text-decoration: none; border-bottom: 1px dashed #cbd5e1; transition: 0.2s; }
        .ip-link:hover { color: #0f172a; border-bottom-color: #0f172a; }
    </style>
</head>
<body>
    <div style="max-width: 1000px; margin: 40px auto; padding: 20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">
            <div>
                <h1 style="font-size: 24px; margin-bottom: 5px;">Güvenlik ve Erişim Logları</h1>
                <p style="color: #64748b; font-size: 14px;">Panele giriş yapan son 100 işlem.</p>
            </div>
            <div style="display:flex; gap:10px;">
                <a href="?action=cleanup" class="btn" style="background: #fff; color: #64748b; border: 1px solid #cbd5e1;" onclick="return confirm('Eski kayıtları silmek istediğine emin misin?')">
                    <i class="ph-bold ph-trash"></i> Geçmişi Temizle
                </a>
                <a href="index.php" class="btn btn-primary">Geri Dön</a>
            </div>
        </div>

        <?php if(isset($msg)) echo $msg; ?>

        <table class="log-table">
            <thead>
                <tr>
                    <th>Zaman</th>
                    <th>Restoran / Kullanıcı</th>
                    <th>IP Adresi</th>
                    <th>Cihaz</th>
                    <th>Tarayıcı Bilgisi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td style="color: #64748b; font-weight: 500;">
                            <?php echo date('d.m.Y H:i:s', strtotime($log['login_time'])); ?>
                        </td>
                        <td>
                            <div style="font-weight: 700; color: #0f172a;">
                                <?php echo htmlspecialchars($log['restaurant_name'] ?? 'Bilinmiyor'); ?>
                            </div>
                            <div style="font-size: 12px; color: #64748b;">
                                @<?php echo htmlspecialchars($log['username'] ?? 'Silinmiş'); ?>
                            </div>
                        </td>
                        <td>
                            <a href="https://whatismyipaddress.com/ip/<?php echo $log['ip_address']; ?>" target="_blank" class="ip-link" title="Konumu Gör">
                                <?php echo $log['ip_address']; ?> <i class="ph-bold ph-arrow-square-out" style="font-size:10px;"></i>
                            </a>
                        </td>
                        <td>
                            <?php 
                                $cls = 'dev-pc';
                                $icon = 'ph-desktop';
                                if($log['device_info'] == 'Mobil Telefon') { $cls = 'dev-mobile'; $icon = 'ph-device-mobile'; }
                                elseif($log['device_info'] == 'Tablet') { $cls = 'dev-tablet'; $icon = 'ph-device-tablet'; }
                            ?>
                            <span class="device-badge <?php echo $cls; ?>">
                                <i class="ph-bold <?php echo $icon; ?>"></i> <?php echo $log['device_info']; ?>
                            </span>
                        </td>
                        <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #94a3b8; font-size: 11px;" title="<?php echo htmlspecialchars($log['user_agent']); ?>">
                            <?php echo htmlspecialchars($log['user_agent']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>