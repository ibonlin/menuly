<?php
session_start();
require_once '../includes/db.php';

// Güvenlik
if (!isset($_SESSION['master_id'])) {
    header("Location: login.php");
    exit;
}

// RESTORANLARI ÇEK (Dropdown için)
$users = $pdo->query("SELECT id, restaurant_name FROM users ORDER BY restaurant_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// DUYURU EKLEME
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_announcement'])) {
    $title = htmlspecialchars(trim($_POST['title']));
    $message = htmlspecialchars(trim($_POST['message']));
    $type = $_POST['type'];
    $target = (int)$_POST['target_user_id']; // Hedef Kitle

    if (!empty($title) && !empty($message)) {
        $stmt = $pdo->prepare("INSERT INTO announcements (title, message, type, target_user_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $message, $type, $target]);
        
        header("Location: duyurular.php?success=1"); 
        exit;
    }
}

// DUYURU SİLME
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM announcements WHERE id = ?")->execute([$id]);
    header("Location: duyurular.php");
    exit;
}

// LİSTELEME (Kime gittiğini de çekiyoruz)
$sql = "SELECT a.*, u.restaurant_name 
        FROM announcements a 
        LEFT JOIN users u ON a.target_user_id = u.id 
        ORDER BY a.created_at DESC";
$announcements = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Duyuru Yönetimi - Master Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script> tailwind.config = { corePlugins: { preflight: false } } </script> 
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background:#f3f4f6; margin:0; }
        *, ::after, ::before { box-sizing: border-box; border-width: 0; border-style: solid; border-color: #e5e7eb; }
        h1, h2, h3, h4, h5, h6 { font-weight: inherit; font-size: inherit; margin:0; }
        button, input, optgroup, select, textarea { font-family: inherit; font-size: 100%; font-weight: inherit; line-height: inherit; color: inherit; margin: 0; padding: 0; }
    </style>
</head>
<body class="p-6">
    <div class="max-w-6xl mx-auto">
        <header class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-extrabold text-slate-900">Duyuru Sistemi</h1>
                <p class="text-slate-500 text-sm mt-1">Restoranlara bildirim gönderin.</p>
            </div>
            <a href="index.php" class="bg-white text-slate-700 px-4 py-2 rounded-lg font-bold shadow-sm border border-slate-200 hover:bg-slate-50 text-sm transition">Panele Dön</a>
        </header>

        <div class="grid md:grid-cols-3 gap-8">
            <div class="md:col-span-1">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 sticky top-6">
                    <h2 class="text-lg font-bold text-slate-900 mb-4">Yeni Bildirim Gönder</h2>
                    
                    <form method="POST" action="" class="space-y-4">
                        <input type="hidden" name="add_announcement" value="1">
                        
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Hedef Kitle</label>
                            <select name="target_user_id" class="w-full px-4 py-2 border border-slate-200 rounded-xl outline-none focus:border-blue-600 bg-white transition">
                                <option value="0">📢 Tüm Restoranlar</option>
                                <?php foreach($users as $u): ?>
                                    <option value="<?php echo $u['id']; ?>">👤 <?php echo htmlspecialchars($u['restaurant_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Başlık</label>
                            <input type="text" name="title" class="w-full px-4 py-2 border border-slate-200 rounded-xl outline-none focus:border-blue-600 transition" placeholder="Örn: Bakım Çalışması" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Mesaj</label>
                            <textarea name="message" rows="3" class="w-full px-4 py-2 border border-slate-200 rounded-xl outline-none focus:border-blue-600 resize-none transition" placeholder="Bildirim içeriği..." required></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Bildirim Tipi</label>
                            <select name="type" class="w-full px-4 py-2 border border-slate-200 rounded-xl outline-none focus:border-blue-600 bg-white transition">
                                <option value="info">Bilgilendirme (Mavi)</option>
                                <option value="warning">Uyarı (Turuncu)</option>
                                <option value="danger">Kritik (Kırmızı)</option>
                                <option value="success">Müjde (Yeşil)</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="w-full bg-slate-900 text-white py-3 rounded-xl font-bold hover:bg-blue-600 transition shadow-lg shadow-slate-900/20">Gönder</button>
                    </form>
                </div>
            </div>

            <div class="md:col-span-2 space-y-4">
                <?php if(isset($_GET['success'])): ?>
                    <div class="bg-green-100 text-green-700 p-4 rounded-xl border border-green-200 font-bold text-sm flex items-center gap-2">
                        <i class="ph-fill ph-check-circle text-lg"></i> Duyuru başarıyla gönderildi.
                    </div>
                <?php endif; ?>

                <?php foreach($announcements as $ann): ?>
                    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200 flex justify-between items-start transition hover:shadow-md">
                        <div class="flex gap-4">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 
                                <?php 
                                    if($ann['type']=='info') echo 'bg-blue-100 text-blue-600';
                                    elseif($ann['type']=='warning') echo 'bg-orange-100 text-orange-600';
                                    elseif($ann['type']=='danger') echo 'bg-red-100 text-red-600';
                                    else echo 'bg-green-100 text-green-600';
                                ?>">
                                <i class="ph-fill <?php 
                                    if($ann['type']=='info') echo 'ph-info';
                                    elseif($ann['type']=='warning') echo 'ph-warning';
                                    elseif($ann['type']=='danger') echo 'ph-warning-circle';
                                    else echo 'ph-check-circle';
                                ?> text-xl"></i>
                            </div>
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <h3 class="font-bold text-slate-900 text-lg"><?php echo htmlspecialchars($ann['title']); ?></h3>
                                    <?php if($ann['target_user_id'] == 0): ?>
                                        <span class="bg-gray-100 text-gray-500 text-[10px] px-2 py-0.5 rounded font-bold uppercase">Herkese</span>
                                    <?php else: ?>
                                        <span class="bg-blue-50 text-blue-600 text-[10px] px-2 py-0.5 rounded font-bold uppercase"><?php echo htmlspecialchars($ann['restaurant_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-slate-600 text-sm mt-1 leading-relaxed"><?php echo nl2br(htmlspecialchars($ann['message'])); ?></p>
                                <span class="text-xs text-slate-400 mt-2 block font-medium"><?php echo date("d.m.Y H:i", strtotime($ann['created_at'])); ?></span>
                            </div>
                        </div>
                        <a href="duyurular.php?delete=<?php echo $ann['id']; ?>" onclick="return confirm('Silmek istiyor musunuz?')" class="text-gray-300 hover:text-red-500 p-2 transition"><i class="ph-bold ph-trash text-lg"></i></a>
                    </div>
                <?php endforeach; ?>

                <?php if(empty($announcements)): ?>
                    <div class="text-center py-16 border border-dashed border-slate-300 rounded-2xl">
                        <i class="ph-duotone ph-megaphone-simple text-4xl text-slate-300 mb-2"></i>
                        <p class="text-slate-400 font-medium">Henüz duyuru gönderilmemiş.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>