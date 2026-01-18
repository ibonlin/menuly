<?php
// panel/master-admin/login.php

// Veritabanı bağlantısı
require_once '../includes/db.php';

// Session güvenliği
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- GÜVENLİK FONKSİYONLARI (Admin panelinden uyarlandı) ---

function get_client_ip() {
    return $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
}

function checkBruteForce($pdo, $ip) {
    // Son 15 dakikada 5'ten fazla hata yapılmış mı?
    $stmt = $pdo->prepare("SELECT * FROM login_attempts WHERE ip_address = ? AND last_attempt > (NOW() - INTERVAL 15 MINUTE)");
    $stmt->execute([$ip]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($attempt && $attempt['attempt_count'] >= 5) {
        return true; // Engellendi
    }
    return false;
}

function logFailedLogin($pdo, $ip) {
    // Kayıt var mı bak, varsa artır, yoksa oluştur
    $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, attempt_count, last_attempt) VALUES (?, 1, NOW()) 
                           ON DUPLICATE KEY UPDATE attempt_count = attempt_count + 1, last_attempt = NOW()");
    $stmt->execute([$ip]);
}

function clearLoginAttempts($pdo, $ip) {
    // Başarılı girişte sayacı sıfırla
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
    $stmt->execute([$ip]);
}

// -----------------------------------------------------------

// CSRF Token Oluştur (Yoksa)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Zaten giriş yapmışsa yönlendir
if (isset($_SESSION['master_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = get_client_ip();

    // 1. BRUTE FORCE KONTROLÜ
    if (checkBruteForce($pdo, $ip)) {
        // Güvenlik: Hata mesajında süreyi belirtmek kullanıcı dostudur ancak saldırgana bilgi verir.
        // Standart bir mesaj verip işlemi durduruyoruz.
        die("Güvenlik Uyarısı: Çok fazla başarısız giriş denemesi. Lütfen 15 dakika bekleyiniz.");
    }

    // 2. CSRF KONTROLÜ
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Güvenlik Hatası: Oturum süreniz dolmuş veya geçersiz istek. Lütfen sayfayı yenileyip tekrar deneyin.");
    }

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Master Admin tablosuna bak
    $stmt = $pdo->prepare("SELECT * FROM master_admin WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // 3. ŞİFRE KONTROLÜ
    if ($admin && password_verify($password, $admin['password'])) {
        // Başarılı girişte IP engelini kaldır
        clearLoginAttempts($pdo, $ip);

        // Session güvenliği
        session_regenerate_id(true);

        $_SESSION['master_id'] = $admin['id'];
        $_SESSION['master_user'] = $admin['username'];
        
        // Yeni CSRF token oluştur (Session Fixation önlemi)
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        header("Location: index.php");
        exit;
    } else {
        // Hatalı girişi kaydet
        logFailedLogin($pdo, $ip);
        $error = "Hatalı patron bilgisi veya şifre.";
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Panel | Menuly</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .glass-effect {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .animate-blob { animation: blob 7s infinite; }
        .animation-delay-2000 { animation-delay: 2s; }
        @keyframes blob {
            0% { transform: translate(0px, 0px) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
            100% { transform: translate(0px, 0px) scale(1); }
        }
    </style>
</head>
<body class="bg-slate-950 min-h-screen flex items-center justify-center p-4 overflow-hidden relative selection:bg-purple-500 selection:text-white">
    
    <div class="absolute top-0 left-0 w-full h-full overflow-hidden z-0 pointer-events-none">
        <div class="absolute top-[10%] left-[20%] w-72 h-72 bg-purple-600 rounded-full mix-blend-screen filter blur-[100px] opacity-20 animate-blob"></div>
        <div class="absolute bottom-[20%] right-[20%] w-72 h-72 bg-blue-600 rounded-full mix-blend-screen filter blur-[100px] opacity-20 animate-blob animation-delay-2000"></div>
    </div>

    <div class="w-full max-w-md relative z-10">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-tr from-blue-600 to-purple-600 shadow-xl shadow-blue-500/20 mb-5 transform hover:scale-105 transition-transform duration-300">
                <i class="ph-bold ph-crown text-3xl text-white"></i>
            </div>
            <h1 class="text-3xl font-bold text-white tracking-tight">Master Admin</h1>
            <p class="text-slate-400 text-sm mt-2 font-medium">Yönetim paneline güvenli erişim.</p>
        </div>

        <div class="glass-effect rounded-3xl p-8 shadow-2xl relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-blue-500 to-transparent opacity-50"></div>

            <?php if($error): ?>
                <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-xl text-sm font-bold mb-6 flex items-center gap-3 animate-pulse">
                    <i class="ph-fill ph-warning-circle text-lg"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="space-y-1.5">
                    <label class="text-[11px] font-bold text-slate-400 uppercase ml-1 tracking-wider">Kullanıcı Adı</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="ph-bold ph-user text-slate-500 group-focus-within:text-blue-400 transition-colors"></i>
                        </div>
                        <input type="text" name="username" class="w-full pl-11 pr-4 py-3.5 bg-slate-900/50 border border-slate-700/50 rounded-xl text-white placeholder-slate-600 focus:outline-none focus:border-blue-500/50 focus:ring-2 focus:ring-blue-500/20 transition-all font-medium" placeholder="admin" required autofocus>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-[11px] font-bold text-slate-400 uppercase ml-1 tracking-wider">Şifre</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="ph-bold ph-lock-key text-slate-500 group-focus-within:text-purple-400 transition-colors"></i>
                        </div>
                        <input type="password" name="password" class="w-full pl-11 pr-4 py-3.5 bg-slate-900/50 border border-slate-700/50 rounded-xl text-white placeholder-slate-600 focus:outline-none focus:border-purple-500/50 focus:ring-2 focus:ring-purple-500/20 transition-all font-medium" placeholder="••••••••" required>
                    </div>
                </div>

                <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white font-bold py-4 rounded-xl hover:shadow-lg hover:shadow-purple-600/25 transform hover:-translate-y-0.5 active:translate-y-0 active:opacity-90 transition-all duration-200 mt-2">
                    Güvenli Giriş Yap
                </button>
            </form>
        </div>

        <p class="text-center mt-8 text-slate-600 text-[10px] font-mono">
            IP: <?php echo $_SERVER['REMOTE_ADDR']; ?> &bull; Secured by Menuly
        </p>
    </div>

</body>
</html>