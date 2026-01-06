<?php
require_once '../includes/db.php';

// Güvenlik
if (!isset($_SESSION['master_id'])) { header("Location: login.php"); exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$msg_type = ''; // success veya error

// Veriyi Çek
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) die("Kullanıcı bulunamadı.");

// GÜNCELLEME İŞLEMİ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rest_name = trim($_POST['restaurant_name']);
    $slug = trim($_POST['slug']);
    $new_pass = trim($_POST['password']);
    $sub_date = !empty($_POST['subscription_end']) ? $_POST['subscription_end'] : NULL;
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);

    // Link temizliği
    $slug = strtolower(str_replace(' ', '-', $slug));
    $slug = preg_replace('/[^a-z0-9-]/', '', $slug);

    $sql = "UPDATE users SET restaurant_name=?, slug=?, subscription_end=?, email=?, phone=?";
    $params = [$rest_name, $slug, $sub_date, $email, $phone];

    if (!empty($new_pass)) {
        // Şifre hashleme (Güvenlik için)
        $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
        $sql .= ", password=?";
        $params[] = $hashed_pass;
    }

    $sql .= " WHERE id=?";
    $params[] = $id;

    $upd = $pdo->prepare($sql);
    if ($upd->execute($params)) {
        $message = "Bilgiler başarıyla güncellendi!";
        $msg_type = "success";
    } else {
        $message = "Bir hata oluştu.";
        $msg_type = "error";
    }
    
    // Güncel veriyi tekrar çek
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restoran Düzenle | Master Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }</style>
</head>
<body class="text-slate-800">

    <div class="bg-white border-b border-slate-200 sticky top-0 z-50">
        <div class="max-w-5xl mx-auto px-4 h-16 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="index.php" class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-600 hover:bg-slate-200 transition"><i class="ph-bold ph-arrow-left"></i></a>
                <h1 class="font-bold text-lg text-slate-900">Restoran Düzenle</h1>
            </div>
            <div class="text-xs font-mono text-slate-400">ID: #<?php echo $user['id']; ?></div>
        </div>
    </div>

    <div class="max-w-5xl mx-auto px-4 py-8">
        
        <?php if($message): ?>
            <div class="<?php echo $msg_type == 'success' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200'; ?> border px-4 py-3 rounded-xl mb-6 font-bold flex items-center gap-2">
                <i class="ph-bold <?php echo $msg_type == 'success' ? 'ph-check-circle' : 'ph-warning-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                    <h3 class="font-bold text-slate-900 mb-4 flex items-center gap-2"><i class="ph-duotone ph-storefront text-indigo-600 text-xl"></i> İşletme Bilgileri</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Restoran Adı</label>
                            <input type="text" name="restaurant_name" value="<?php echo htmlspecialchars($user['restaurant_name']); ?>" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 outline-none transition font-medium" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Link (Slug)</label>
                            <div class="flex items-center">
                                <span class="bg-slate-100 border border-r-0 border-slate-200 text-slate-500 p-3 rounded-l-xl text-sm">/</span>
                                <input type="text" name="slug" value="<?php echo htmlspecialchars($user['slug']); ?>" class="w-full p-3 bg-white border border-slate-200 rounded-r-xl focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 outline-none transition font-medium" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                    <h3 class="font-bold text-slate-900 mb-4 flex items-center gap-2"><i class="ph-duotone ph-user-circle text-indigo-600 text-xl"></i> İletişim Bilgileri</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">E-Posta</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 outline-none transition" placeholder="Örn: info@restoran.com">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Telefon</label>
                            <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 outline-none transition" placeholder="0555 123 45 67">
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                    <h3 class="font-bold text-slate-900 mb-4 flex items-center gap-2"><i class="ph-duotone ph-gear text-indigo-600 text-xl"></i> Hesap Ayarları</h3>
                    
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Abonelik Bitiş Tarihi</label>
                        <input type="date" name="subscription_end" value="<?php echo $user['subscription_end']; ?>" class="w-full p-3 bg-indigo-50 border border-indigo-200 text-indigo-700 font-bold rounded-xl focus:ring-2 focus:ring-indigo-100 outline-none">
                    </div>

                    <div class="mb-6">
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Şifre Değiştir</label>
                        <input type="text" name="password" placeholder="Sadece değiştirmek için yazın..." class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 outline-none transition text-sm">
                    </div>

                    <button type="submit" class="w-full bg-indigo-600 text-white font-bold py-3.5 rounded-xl hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition flex items-center justify-center gap-2">
                        <i class="ph-bold ph-floppy-disk"></i> Değişiklikleri Kaydet
                    </button>
                </div>

                <div class="bg-slate-100 p-4 rounded-xl text-xs text-slate-500 text-center">
                    Son güncelleme: <?php echo date("d.m.Y H:i"); ?>
                </div>
            </div>

        </form>
    </div>

</body>
</html>