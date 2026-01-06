<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['master_id'])) {
    header("Location: login.php");
    exit;
}

$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;

// DOSYA YÜKLEME FONKSİYONU
function uploadFile($file) {
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    $maxSize = 5 * 1024 * 1024; 
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Destek Merkezi | Master Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background:#f1f5f9; }
        /* Scrollbar Gizleme */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="h-screen flex flex-col overflow-hidden">

    <header class="bg-white border-b border-slate-200 h-16 flex-none z-10">
        <div class="max-w-7xl mx-auto px-4 h-full flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="index.php" class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-600 hover:bg-slate-200 transition"><i class="ph-bold ph-arrow-left"></i></a>
                <h1 class="font-bold text-lg text-slate-900">Destek Merkezi</h1>
            </div>
            <?php if($view_id > 0 && $ticket_detail['status'] !== 'closed'): ?>
                <a href="destek.php?close=<?php echo $view_id; ?>" onclick="return confirm('Talep kapatılsın mı?')" class="bg-red-50 text-red-600 px-4 py-2 rounded-lg text-xs font-bold hover:bg-red-100 transition">Talebi Kapat</a>
            <?php endif; ?>
        </div>
    </header>

    <div class="flex-1 overflow-hidden relative max-w-7xl mx-auto w-full flex">
        
        <?php if($view_id == 0): ?>
        <div class="w-full h-full overflow-y-auto p-4 space-y-3">
            <div class="flex justify-between items-center mb-4 px-2">
                <h2 class="font-bold text-slate-700">Gelen Talepler</h2>
                <span class="bg-indigo-100 text-indigo-700 text-xs px-2 py-1 rounded-full font-bold"><?php echo count($tickets); ?></span>
            </div>
            
            <?php foreach($tickets as $ticket): ?>
                <a href="destek.php?view=<?php echo $ticket['id']; ?>" class="block bg-white p-4 rounded-xl border border-slate-200 shadow-sm hover:border-indigo-400 hover:shadow-md transition group">
                    <div class="flex justify-between items-start mb-2">
                        <div class="font-bold text-slate-900 group-hover:text-indigo-600 transition"><?php echo htmlspecialchars($ticket['subject']); ?></div>
                        <?php if($ticket['status']=='customer_reply'): ?>
                            <span class="bg-red-100 text-red-600 text-[10px] px-2 py-1 rounded-full font-bold uppercase animate-pulse">Yanıtlandı</span>
                        <?php elseif($ticket['status']=='open'): ?>
                            <span class="bg-yellow-100 text-yellow-700 text-[10px] px-2 py-1 rounded-full font-bold uppercase">Yeni</span>
                        <?php else: ?>
                            <span class="bg-slate-100 text-slate-500 text-[10px] px-2 py-1 rounded-full font-bold uppercase">Kapalı</span>
                        <?php endif; ?>
                    </div>
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-indigo-600 font-bold bg-indigo-50 px-2 py-1 rounded-md"><?php echo htmlspecialchars($ticket['restaurant_name']); ?></span>
                        <span class="text-slate-400"><?php echo date("d.m H:i", strtotime($ticket['updated_at'])); ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        
        <div class="w-full flex flex-col h-full bg-white md:border-x md:border-slate-200">
            
            <div class="p-4 border-b border-slate-100 bg-slate-50/50 flex-none">
                <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
                    <i class="ph-fill ph-storefront"></i> <?php echo htmlspecialchars($ticket_detail['restaurant_name']); ?>
                </div>
                <h2 class="font-bold text-slate-900 text-lg leading-tight"><?php echo htmlspecialchars($ticket_detail['subject']); ?></h2>
            </div>

            <div class="flex-1 overflow-y-auto p-4 space-y-6 bg-[#f8fafc]" id="chatArea">
                <?php foreach($replies as $reply): ?>
                    <?php $is_admin = ($reply['user_id'] == 0); ?>
                    <div class="flex <?php echo $is_admin ? 'justify-end' : 'justify-start'; ?>">
                        <div class="max-w-[85%] md:max-w-[70%]">
                            <div class="flex items-center gap-2 mb-1 <?php echo $is_admin ? 'justify-end' : ''; ?>">
                                <span class="text-[10px] font-bold text-slate-400 uppercase"><?php echo $is_admin ? 'Siz' : 'Müşteri'; ?></span>
                                <span class="text-[10px] text-slate-300"><?php echo date("H:i", strtotime($reply['created_at'])); ?></span>
                            </div>
                            <div class="p-4 rounded-2xl shadow-sm text-sm leading-relaxed <?php echo $is_admin ? 'bg-slate-800 text-white rounded-tr-none' : 'bg-white border border-slate-200 text-slate-700 rounded-tl-none'; ?>">
                                <?php echo nl2br(htmlspecialchars($reply['message'])); ?>
                                
                                <?php if(!empty($reply['attachment'])): ?>
                                    <a href="../<?php echo $reply['attachment']; ?>" target="_blank" class="mt-3 flex items-center gap-3 p-3 rounded-xl <?php echo $is_admin ? 'bg-white/10 hover:bg-white/20' : 'bg-slate-50 border border-slate-100 hover:bg-slate-100'; ?> transition">
                                        <div class="w-8 h-8 rounded-lg bg-indigo-500 text-white flex items-center justify-center flex-shrink-0">
                                            <i class="ph-bold ph-file-text"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="font-bold truncate">Ek Dosya</div>
                                            <div class="text-[10px] opacity-70">Görüntülemek için tıkla</div>
                                        </div>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if($ticket_detail['status'] !== 'closed'): ?>
                <div class="p-4 border-t border-slate-200 bg-white flex-none">
                    <form method="POST" enctype="multipart/form-data" class="relative">
                        <input type="hidden" name="admin_reply" value="1">
                        <input type="hidden" name="ticket_id" value="<?php echo $view_id; ?>">
                        
                        <div class="relative">
                            <textarea name="message" rows="1" id="msgInput" class="w-full pl-4 pr-12 py-3 bg-slate-50 border border-slate-200 rounded-2xl outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-200 transition resize-none text-sm" placeholder="Yanıtınızı yazın..."></textarea>
                            
                            <label class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer text-slate-400 hover:text-indigo-600 transition p-1">
                                <i class="ph-bold ph-paperclip text-xl"></i>
                                <input type="file" name="attachment" class="hidden" onchange="document.getElementById('msgInput').placeholder = 'Dosya eklendi: ' + this.files[0].name">
                            </label>
                        </div>

                        <div class="mt-2 flex justify-end">
                            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-xl font-bold text-sm hover:bg-indigo-700 transition shadow-lg shadow-indigo-200 flex items-center gap-2">
                                <i class="ph-bold ph-paper-plane-right"></i> Gönder
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="p-4 bg-slate-50 text-center text-slate-500 text-sm border-t border-slate-200">
                    <i class="ph-bold ph-lock-key"></i> Bu talep kapatılmıştır.
                </div>
            <?php endif; ?>

        </div>
        
        <script>
            var chatArea = document.getElementById('chatArea');
            if(chatArea) chatArea.scrollTop = chatArea.scrollHeight;
        </script>
        
        <?php endif; ?>
    </div>

</body>
</html>