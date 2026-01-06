<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['master_id'])) {
    header("Location: login.php");
    exit;
}

$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;

// DOSYA YÜKLEME FONKSİYONU (Aynı mantık)
function uploadFile($file) {
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    $maxSize = 5 * 1024 * 1024; 
    // Dikkat: Master admin panel klasöründe olduğu için yol ../assets/uploads/
    $uploadDir = '../assets/uploads/';

    if ($file['size'] > $maxSize) return ['error' => 'Dosya 5MB büyük olamaz!'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) return ['error' => 'Geçersiz dosya türü!'];

    $newName = uniqid('admin_') . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
        return ['path' => 'assets/uploads/' . $newName];
    }
    return ['error' => 'Yükleme hatası.'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_reply'])) {
    $t_id = (int)$_POST['ticket_id'];
    $msg = htmlspecialchars(trim($_POST['message']));
    $attachmentPath = null;

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $upload = uploadFile($_FILES['attachment']);
        if (isset($upload['error'])) $error = $upload['error'];
        else $attachmentPath = $upload['path'];
    }

    if (!isset($error) && (!empty($msg) || $attachmentPath)) {
        $stmt = $pdo->prepare("INSERT INTO ticket_replies (ticket_id, user_id, message, attachment) VALUES (?, 0, ?, ?)");
        $stmt->execute([$t_id, $msg, $attachmentPath]);
        $pdo->prepare("UPDATE support_tickets SET status = 'answered', updated_at = NOW() WHERE id = ?")->execute([$t_id]);
        header("Location: destek.php?view=" . $t_id);
        exit;
    }
}

if (isset($_GET['close'])) {
    $pdo->prepare("UPDATE support_tickets SET status = 'closed' WHERE id = ?")->execute([(int)$_GET['close']]);
    header("Location: destek.php");
    exit;
}

if ($view_id > 0) {
    $stmt = $pdo->prepare("SELECT t.*, u.restaurant_name FROM support_tickets t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
    $stmt->execute([$view_id]);
    $ticket_detail = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM ticket_replies WHERE ticket_id = ? ORDER BY created_at ASC");
    $stmt->execute([$view_id]);
    $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->query("SELECT t.*, u.restaurant_name FROM support_tickets t JOIN users u ON t.user_id = u.id ORDER BY updated_at DESC");
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Master Destek</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; background:#f3f4f6; }</style>
</head>
<body class="p-6">
    <div class="max-w-6xl mx-auto">
        <?php if(isset($error)): ?><div class="bg-red-100 text-red-700 p-4 mb-4 rounded-xl font-bold"><?php echo $error; ?></div><?php endif; ?>

        <?php if($view_id > 0): ?>
            <div class="flex justify-between items-center mb-6">
                <div class="flex items-center gap-4">
                    <a href="destek.php" class="bg-white px-3 py-2 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50"><i class="ph-bold ph-arrow-left"></i></a>
                    <h1 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($ticket_detail['subject']); ?> <span class="text-blue-600 ml-2"><?php echo htmlspecialchars($ticket_detail['restaurant_name']); ?></span></h1>
                </div>
                <?php if($ticket_detail['status'] !== 'closed'): ?>
                    <a href="destek.php?close=<?php echo $view_id; ?>" onclick="return confirm('Kapatılsın mı?')" class="bg-red-50 text-red-600 px-4 py-2 rounded-lg font-bold text-sm hover:bg-red-100">Kapat</a>
                <?php endif; ?>
            </div>

            <div class="space-y-4 mb-8">
                <?php foreach($replies as $reply): ?>
                    <?php $is_admin = ($reply['user_id'] == 0); ?>
                    <div class="flex <?php echo $is_admin ? 'justify-end' : 'justify-start'; ?>">
                        <div class="max-w-[70%] p-4 rounded-2xl shadow-sm <?php echo $is_admin ? 'bg-slate-800 text-white rounded-tr-none' : 'bg-white border border-gray-200 rounded-tl-none'; ?>">
                            <div class="text-xs font-bold mb-1 opacity-70"><?php echo $is_admin ? 'Siz' : 'Müşteri'; ?> • <?php echo date("H:i", strtotime($reply['created_at'])); ?></div>
                            <?php echo nl2br(htmlspecialchars($reply['message'])); ?>
                            <?php if(!empty($reply['attachment'])): ?>
                                <a href="../<?php echo $reply['attachment']; ?>" target="_blank" class="flex items-center gap-2 mt-2 bg-white/20 p-2 rounded text-sm font-bold hover:bg-white/30 transition">
                                    <i class="ph-bold ph-file"></i> Ek Dosyayı Aç
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if($ticket_detail['status'] !== 'closed'): ?>
                <form method="POST" enctype="multipart/form-data" class="bg-white p-4 rounded-2xl border border-gray-200 sticky bottom-4 shadow-lg">
                    <input type="hidden" name="admin_reply" value="1">
                    <input type="hidden" name="ticket_id" value="<?php echo $view_id; ?>">
                    <textarea name="message" rows="2" class="w-full p-3 border border-gray-200 rounded-xl outline-none resize-none mb-3" placeholder="Yanıt yaz..."></textarea>
                    <div class="flex justify-between items-center">
                        <label class="cursor-pointer text-sm text-gray-500 hover:text-blue-600 flex items-center gap-2">
                            <i class="ph-bold ph-paperclip"></i> <span id="admFileName">Dosya Ekle</span>
                            <input type="file" name="attachment" class="hidden" onchange="document.getElementById('admFileName').innerText = this.files[0].name">
                        </label>
                        <button type="submit" class="bg-slate-900 text-white px-6 py-2 rounded-xl font-bold hover:bg-slate-800">Gönder</button>
                    </div>
                </form>
            <?php endif; ?>

        <?php else: ?>
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-extrabold text-slate-900">Gelen Talepler</h1>
                <a href="index.php" class="text-slate-500 hover:text-blue-600 font-bold">Panele Dön</a>
            </div>
            <div class="grid gap-3">
                <?php foreach($tickets as $ticket): ?>
                    <a href="destek.php?view=<?php echo $ticket['id']; ?>" class="bg-white p-5 rounded-xl border border-gray-200 hover:border-blue-400 transition shadow-sm flex justify-between items-center">
                        <div>
                            <h3 class="font-bold text-gray-900 text-lg"><?php echo htmlspecialchars($ticket['subject']); ?></h3>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="bg-blue-50 text-blue-700 text-xs px-2 py-0.5 rounded font-bold uppercase"><?php echo htmlspecialchars($ticket['restaurant_name']); ?></span>
                                <span class="text-xs text-gray-400"><?php echo date("d.m H:i", strtotime($ticket['updated_at'])); ?></span>
                            </div>
                        </div>
                        <?php if($ticket['status']=='customer_reply'): ?>
                            <span class="bg-red-100 text-red-600 text-xs px-3 py-1 rounded-full font-bold animate-pulse">Müşteri Yanıtladı</span>
                        <?php elseif($ticket['status']=='open'): ?>
                            <span class="bg-yellow-100 text-yellow-700 text-xs px-3 py-1 rounded-full font-bold">Yeni</span>
                        <?php else: ?>
                            <span class="bg-gray-100 text-gray-500 text-xs px-3 py-1 rounded-full font-bold">Bekliyor/Kapalı</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>