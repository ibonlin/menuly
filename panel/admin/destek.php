<?php
session_start();
require_once '../includes/db.php';

// ... (TELEGRAM AYARLARI AYNI KALSIN) ...
define('TG_BOT_TOKEN', '8589740930:AAELRmRrRIqal63joAnmHWdTIblI_oDyMc8');
define('TG_CHAT_ID', '758649120');

function sendTelegram($message) {
    if (empty(TG_BOT_TOKEN) || empty(TG_CHAT_ID)) return;
    $url = "https://api.telegram.org/bot" . TG_BOT_TOKEN . "/sendMessage";
    $data = ['chat_id' => TG_CHAT_ID, 'text' => $message, 'parse_mode' => 'HTML'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); 
    curl_exec($ch);
    curl_close($ch);
}

// Güvenlik
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$restaurant_name = isset($_SESSION['restaurant_name']) ? $_SESSION['restaurant_name'] : 'Restoran #' . $user_id;
$view_ticket_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;

// --- CSRF KORUMASI ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- GÜVENLİ DOSYA YÜKLEME FONKSİYONU (GÜNCELLENDİ) ---
function uploadFile($file) {
    $maxSize = 5 * 1024 * 1024; // 5MB
    $uploadDir = '../assets/uploads/';

    if ($file['size'] > $maxSize) return ['error' => 'Dosya boyutu 5MB\'ı geçemez!'];
    
    // 1. Uzantı Kontrolü
    $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext)) return ['error' => 'Sadece JPG, PNG ve PDF yüklenebilir!'];

    // 2. MIME Type Kontrolü (Gerçek dosya türü)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowed_mime = [
        'image/jpeg', 'image/png', 'application/pdf'
    ];

    if (!in_array($mime, $allowed_mime)) {
        return ['error' => 'Geçersiz dosya içeriği!'];
    }

    $newName = uniqid('support_') . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
        return ['path' => 'assets/uploads/' . $newName];
    }
    return ['error' => 'Dosya yüklenirken sunucu hatası oluştu.'];
}

$is_demo = (isset($_SESSION['username']) && $_SESSION['username'] === 'demo');

// --- İŞLEM 1: YENİ TALEP OLUŞTURMA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    
    // CSRF Check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Güvenlik Hatası: Token geçersiz.");
    }

    if ($is_demo) { header("Location: destek.php?error=demo"); exit; }

    $subject = htmlspecialchars(trim($_POST['subject']));
    $message = htmlspecialchars(trim($_POST['message']));
    $attachmentPath = null;

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $upload = uploadFile($_FILES['attachment']);
        if (isset($upload['error'])) $error = $upload['error'];
        else $attachmentPath = $upload['path'];
    }

    if (!isset($error) && !empty($subject) && !empty($message)) {
        $stmt = $pdo->prepare("INSERT INTO support_tickets (user_id, subject, status) VALUES (?, ?, 'open')");
        $stmt->execute([$user_id, $subject]);
        $ticket_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO ticket_replies (ticket_id, user_id, message, attachment) VALUES (?, ?, ?, ?)");
        $stmt->execute([$ticket_id, $user_id, $message, $attachmentPath]);

        // Telegram
        $tg_msg = "🚨 <b>YENİ DESTEK TALEBİ!</b>\n\n";
        $tg_msg .= "🏪 <b>Restoran:</b> $restaurant_name\n";
        $tg_msg .= "📌 <b>Konu:</b> $subject\n";
        $tg_msg .= "📝 <b>Mesaj:</b> $message";
        sendTelegram($tg_msg);

        header("Location: destek.php?success=1");
        exit;
    }
}

// --- İŞLEM 2: CEVAP YAZMA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_ticket'])) {
    
    // CSRF Check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Güvenlik Hatası: Token geçersiz.");
    }

    $t_id = (int)$_POST['ticket_id'];
    
    if ($is_demo) { header("Location: destek.php?view=$t_id&error=demo"); exit; }

    $msg = htmlspecialchars(trim($_POST['message']));
    $attachmentPath = null;

    $check = $pdo->prepare("SELECT id, subject FROM support_tickets WHERE id = ? AND user_id = ?");
    $check->execute([$t_id, $user_id]);
    $ticket_info = $check->fetch(PDO::FETCH_ASSOC);
    
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $upload = uploadFile($_FILES['attachment']);
        if (isset($upload['error'])) $error = $upload['error'];
        else $attachmentPath = $upload['path'];
    }

    if (!isset($error) && $ticket_info && (!empty($msg) || $attachmentPath)) {
        $stmt = $pdo->prepare("INSERT INTO ticket_replies (ticket_id, user_id, message, attachment) VALUES (?, ?, ?, ?)");
        $stmt->execute([$t_id, $user_id, $msg, $attachmentPath]);

        $pdo->prepare("UPDATE support_tickets SET status = 'customer_reply', updated_at = NOW() WHERE id = ?")->execute([$t_id]);
        
        // Telegram
        $tg_msg = "💬 <b>MÜŞTERİ YANITLADI!</b>\n\n";
        $tg_msg .= "🏪 <b>Restoran:</b> $restaurant_name\n";
        $tg_msg .= "📌 <b>Konu:</b> " . htmlspecialchars($ticket_info['subject']) . "\n";
        $tg_msg .= "📝 <b>Yanıt:</b> $msg";
        sendTelegram($tg_msg);

        header("Location: destek.php?view=" . $t_id);
        exit;
    }
}

// ... (VERİ ÇEKME KISIMLARI AYNI KALSIN) ...
if ($view_ticket_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE id = ? AND user_id = ?");
    $stmt->execute([$view_ticket_id, $user_id]);
    $ticket_detail = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket_detail) { header("Location: destek.php"); exit; } 

    $stmt = $pdo->prepare("SELECT * FROM ticket_replies WHERE ticket_id = ? ORDER BY created_at ASC");
    $stmt->execute([$view_ticket_id]);
    $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE user_id = ? ORDER BY updated_at DESC");
    $stmt->execute([$user_id]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Destek Merkezi - Menuly</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { corePlugins: { preflight: false } }</script>
    <style>
        .tw-reset *, .tw-reset ::before, .tw-reset ::after { box-sizing: border-box; border-width: 0; border-style: solid; border-color: #e5e7eb; }
        .tw-reset { font-family: 'Plus Jakarta Sans', sans-serif; color: #1f2937; line-height: 1.5; }
        .chat-bubble { position: relative; max-width: 90%; padding: 16px; border-radius: 16px; font-size: 14px; line-height: 1.6; word-wrap: break-word; }
        @media (min-width: 768px) { .chat-bubble { max-width: 80%; } }
        .chat-me { background: #2563eb; color: white; margin-left: auto; border-top-right-radius: 4px; }
        .chat-support { background: #ffffff; border: 1px solid #e5e7eb; color: #374151; margin-right: auto; border-top-left-radius: 4px; }
        .chat-time { font-size: 11px; margin-top: 4px; opacity: 0.7; font-weight: 600; display: block; text-align: right; }
        .file-attachment { display:flex; align-items:center; gap:8px; margin-top:8px; background:rgba(255,255,255,0.2); padding:8px; border-radius:8px; text-decoration:none; color:inherit; font-weight:600; transition:0.2s; word-break: break-all; }
        .file-attachment:hover { background:rgba(255,255,255,0.3); }
        .chat-support .file-attachment { background:#f3f4f6; color:#2563eb; }
        .chat-support .file-attachment:hover { background:#e5e7eb; }
        @keyframes fadeIn { from { opacity:0; transform: scale(0.95); } to { opacity:1; transform: scale(1); } }
        .animate-bounce-in { animation: fadeIn 0.2s ease-out forwards; }
    </style>
</head>
<body>
    <aside class="sidebar">
        <a href="index.php" class="logo"><span><i class="ph-bold ph-qr-code"></i></span> Menuly.</a>
        <ul class="nav-links">
            <li class="nav-item"><a href="index.php"><i class="ph-bold ph-squares-four"></i> Özet</a></li>
            <li class="nav-item"><a href="categories.php"><i class="ph-bold ph-list-dashes"></i> Kategoriler</a></li>
            <li class="nav-item"><a href="products.php"><i class="ph-bold ph-hamburger"></i> Ürünler</a></li>
            <li class="nav-item"><a href="destek.php" class="active"><i class="ph-bold ph-chats-circle"></i> Destek</a></li>
            <li class="nav-item"><a href="settings.php"><i class="ph-bold ph-gear"></i> Ayarlar</a></li>
            <li class="nav-item"><a href="logout.php" class="logout-btn"><i class="ph-bold ph-sign-out"></i> Çıkış Yap</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <button class="mobile-toggle" onclick="toggleSidebar()"><i class="ph-bold ph-list"></i></button>
        <div class="overlay" onclick="toggleSidebar()"></div>

        <div class="tw-reset">
            <?php if(isset($_GET['error']) && $_GET['error'] == 'demo'): ?>
                <div class="bg-orange-100 text-orange-800 p-4 rounded-xl mb-6 border border-orange-200 font-bold text-sm flex items-center gap-2 animate-pulse">
                    <i class="ph-fill ph-warning-circle text-lg"></i> Demo modunda destek talebi oluşturamaz veya yanıtlayamazsınız.
                </div>
            <?php endif; ?>
            <?php if(isset($_GET['success'])): ?>
                <div class="bg-green-100 text-green-700 p-4 rounded-xl mb-6 border border-green-200 font-bold text-sm flex items-center gap-2">
                    <i class="ph-fill ph-check-circle text-lg"></i> Talebiniz başarıyla oluşturuldu.
                </div>
            <?php endif; ?>
            <?php if($view_ticket_id > 0): ?>
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-4">
                        <a href="destek.php" class="w-10 h-10 bg-white border border-gray-200 rounded-xl flex items-center justify-center text-gray-600 hover:bg-gray-50 transition"><i class="ph-bold ph-arrow-left text-lg"></i></a>
                        <div>
                            <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3">
                                <h1 class="text-lg sm:text-xl font-bold text-gray-900"><?php echo htmlspecialchars($ticket_detail['subject']); ?></h1>
                                <span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded font-bold w-fit">#<?php echo $ticket_detail['id']; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-6 mb-8 p-4">
                    <?php foreach($replies as $reply): ?>
                        <?php $is_me = ($reply['user_id'] == $user_id); ?>
                        <div class="flex <?php echo $is_me ? 'justify-end' : 'justify-start'; ?>">
                            <div class="chat-bubble shadow-sm <?php echo $is_me ? 'chat-me' : 'chat-support'; ?>">
                                <?php if(!$is_me): ?>
                                    <div class="text-xs font-bold text-blue-600 mb-1 flex items-center gap-1"><i class="ph-fill ph-headset"></i> Destek Ekibi</div>
                                <?php endif; ?>
                                
                                <?php echo nl2br(htmlspecialchars($reply['message'])); ?>

                                <?php if(!empty($reply['attachment'])): ?>
                                    <a href="../<?php echo $reply['attachment']; ?>" target="_blank" class="file-attachment">
                                        <i class="ph-bold ph-file-text text-lg"></i>
                                        <span>Eki Görüntüle</span>
                                    </a>
                                <?php endif; ?>
                                
                                <span class="chat-time"><?php echo date("H:i", strtotime($reply['created_at'])); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if($ticket_detail['status'] !== 'closed'): ?>
                    <div class="bg-white p-4 rounded-2xl border border-gray-200 shadow-lg sticky bottom-6">
                        <form method="POST" enctype="multipart/form-data" class="flex flex-col gap-3">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="reply_ticket" value="1">
                            <input type="hidden" name="ticket_id" value="<?php echo $view_ticket_id; ?>">
                            
                            <textarea name="message" rows="2" class="w-full bg-gray-50 px-4 py-3 rounded-xl outline-none text-sm resize-none focus:bg-white focus:border-blue-500 border border-transparent transition" placeholder="Yanıtınızı buraya yazın..."></textarea>
                            
                            <div class="flex flex-col sm:flex-row justify-between items-center gap-3">
                                <label class="cursor-pointer flex items-center gap-2 text-sm text-gray-500 hover:text-blue-600 transition w-full sm:w-auto p-2 sm:p-0 border sm:border-none rounded-lg justify-center sm:justify-start bg-gray-50 sm:bg-transparent">
                                    <i class="ph-bold ph-paperclip text-lg"></i>
                                    <span id="chatFileName">Dosya Ekle</span>
                                    <input type="file" name="attachment" class="hidden" onchange="document.getElementById('chatFileName').innerText = this.files[0].name">
                                </label>
                                <button type="submit" class="bg-blue-600 text-white w-full sm:w-auto px-6 py-3 rounded-xl font-bold text-sm hover:bg-blue-700 transition shadow-md">Gönder</button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="bg-gray-100 p-4 rounded-xl text-center text-gray-500 font-bold text-sm border border-gray-200">
                        <i class="ph-fill ph-lock-key mr-2"></i> Bu destek talebi kapatılmıştır.
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Destek Taleplerim</h1>
                        <p class="text-gray-500 text-sm">Sorunlarınızı buradan iletebilirsiniz.</p>
                    </div>
                    <button onclick="document.getElementById('newTicketModal').classList.remove('hidden')" class="bg-slate-900 hover:bg-blue-600 text-white px-5 py-3 rounded-xl font-bold text-sm transition flex items-center gap-2 shadow-lg w-full sm:w-auto justify-center">
                        <i class="ph-bold ph-plus"></i> Yeni Talep Aç
                    </button>
                </div>

                <div class="grid gap-4">
                    <?php foreach($tickets as $ticket): ?>
                        <a href="destek.php?view=<?php echo $ticket['id']; ?>" style="text-decoration:none;" class="block bg-white border border-gray-200 hover:border-blue-400 p-5 rounded-2xl transition shadow-sm ">
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 sm:gap-0">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-full flex items-center justify-center text-xl shrink-0 <?php echo ($ticket['status'] == 'answered') ? 'bg-green-100 text-green-600' : 'bg-blue-50 text-blue-600'; ?>">
                                        <i class="ph-fill <?php echo ($ticket['status'] == 'answered') ? 'ph-chat-circle-dots' : 'ph-chat-circle-text'; ?>"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-gray-900 text-lg"><?php echo htmlspecialchars($ticket['subject']); ?></h3>
                                        <span class="text-xs text-gray-400 font-medium">#<?php echo $ticket['id']; ?> • <?php echo date("d.m.Y H:i", strtotime($ticket['updated_at'])); ?></span>
                                    </div>
                                </div>
                                
                                <div>
                                    <?php if($ticket['status'] == 'answered'): ?>
                                        <span class="bg-green-100 text-green-700 text-xs px-3 py-1 rounded-full font-bold inline-block">Yanıtlandı</span>
                                    <?php elseif($ticket['status'] == 'open'): ?>
                                        <span class="bg-gray-100 text-gray-600 text-xs px-3 py-1 rounded-full font-bold inline-block">Bekliyor</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
                
                <div id="newTicketModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div class="bg-white w-[95%] max-w-lg rounded-2xl shadow-2xl p-6 relative animate-bounce-in">
                        <button onclick="document.getElementById('newTicketModal').classList.add('hidden')" class="absolute top-4 right-4 text-gray-400 hover:text-red-500"><i class="ph-bold ph-x text-xl"></i></button>
                        <h2 class="text-lg font-bold text-gray-900 mb-4">Yeni Destek Talebi</h2>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="create_ticket" value="1">
                            <div class="mb-4">
                                <label class="block text-sm font-bold text-gray-700 mb-1">Konu</label>
                                <input type="text" name="subject" required class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-blue-500 text-sm">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-bold text-gray-700 mb-1">Mesaj</label>
                                <textarea name="message" rows="4" required class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-blue-500 resize-none text-sm"></textarea>
                            </div>
                            <div class="mb-6">
                                <label class="flex items-center gap-2 text-sm text-gray-500 cursor-pointer hover:text-blue-600 transition w-fit">
                                    <i class="ph-bold ph-paperclip text-lg"></i>
                                    <span id="modalFileName">Dosya Ekle (Opsiyonel)</span>
                                    <input type="file" name="attachment" class="hidden" onchange="document.getElementById('modalFileName').innerText = this.files[0].name">
                                </label>
                            </div>
                            <button type="submit" class="bg-blue-600 text-white w-full py-3 rounded-xl font-bold hover:bg-blue-700 transition">Gönder</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <script>function toggleSidebar(){document.querySelector('.sidebar').classList.toggle('active');document.querySelector('.overlay').classList.toggle('active');}</script>
</body>
</html>