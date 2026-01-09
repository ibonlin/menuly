<?php
session_start();
require_once '../includes/db.php';

// --- GÜVENLİK FONKSİYONLARI ---

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

// ------------------------------

// CSRF Token Oluştur
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = get_client_ip();

    // 1. BRUTE FORCE KONTROLÜ
    if (checkBruteForce($pdo, $ip)) {
        die("Çok fazla hatalı deneme yaptınız. Lütfen 15 dakika bekleyin.");
    }

    // 2. CSRF KONTROLÜ
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Güvenlik Hatası: Sayfa süresi dolmuş. Lütfen sayfayı yenileyip tekrar deneyin.");
    }

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 3. ŞİFRE KONTROLÜ
    if ($user && password_verify($password, $user['password'])) {
        // --- SESSION FIXATION ÖNLEMİ (KRİTİK) ---
        session_regenerate_id(true); 
        // ----------------------------------------
        
        // --- BU SATIRI EKLE ---
        logAdminAccess($pdo, $user['id']); 
        // ----------------------

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['restaurant_name'] = $user['restaurant_name'];
        
        // Başarılı girişte IP engelini kaldır
        clearLoginAttempts($pdo, $ip);

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        header("Location: index.php");
        exit;
    } else {
        // Hatalı girişi kaydet
        logFailedLogin($pdo, $ip);
        $error = "Hatalı kullanıcı adı veya şifre.";
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap | Menuly</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" href="/panel/assets/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .step-hidden { display: none; }
        .fade-in { animation: fadeIn 0.4s ease-out forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-sm">
        <div class="bg-white rounded-[2.5rem] shadow-2xl shadow-slate-200/60 p-10 relative overflow-hidden">
            <div class="flex items-center gap-2.5 mb-10 justify-center">
                <div class="w-9 h-9 bg-blue-600 rounded-xl flex items-center justify-center text-white shadow-lg shadow-blue-500/30">
                    <i class="ph-bold ph-qr-code text-xl"></i>
                </div>
                <span class="text-2xl font-extrabold text-slate-800 tracking-tight">Menuly.</span>
            </div>

            <?php if($error): ?>
                <div class="bg-red-50 text-red-600 p-4 rounded-2xl text-[11px] font-bold mb-6 border border-red-100 text-center uppercase tracking-wider">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div id="step1" class="fade-in space-y-6">
                    <div class="text-center">
                        <h2 class="text-2xl font-extrabold text-slate-900">Hoş Geldiniz</h2>
                        <p class="text-slate-500 text-sm mt-1">Devam etmek için kullanıcı adınızı girin</p>
                    </div>
                    <input type="text" name="username" id="username" class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl outline-none focus:border-blue-600 transition-all font-medium" placeholder="Kullanıcı Adı" required autofocus>
                    <button type="button" onclick="goToStep2()" class="w-full bg-slate-900 text-white font-bold py-4 rounded-2xl hover:bg-blue-600 transition-all flex items-center justify-center gap-2 group">
                        Sonraki <i class="ph-bold ph-arrow-right group-hover:translate-x-1 transition-transform"></i>
                    </button>
                </div>

                <div id="step2" class="step-hidden fade-in space-y-6 text-center">
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-blue-50 border border-blue-100 rounded-full mb-2">
                        <i class="ph-bold ph-user-circle text-blue-600"></i>
                        <span id="userDisp" class="text-xs font-bold text-blue-700 tracking-tighter"></span>
                        <button type="button" onclick="goBack()" class="ml-1 text-[10px] text-blue-400 hover:text-blue-600 uppercase font-bold underline">Değiştir</button>
                    </div>
                    <h2 class="text-2xl font-extrabold text-slate-900">Şifre Girin</h2>
                    <input type="password" name="password" id="password" class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl outline-none focus:border-blue-600 transition-all font-medium text-center" placeholder="••••••" required>
                    <button type="submit" class="w-full bg-blue-600 text-white font-extrabold py-4 rounded-2xl hover:bg-blue-700 shadow-xl shadow-blue-500/20 transition-all">Giriş Yap</button>
                </div>
            </form>
        </div>
        <p class="text-center mt-8 text-slate-400 text-[10px] font-bold uppercase tracking-widest">Powered by Menuly.<br>
                Menuly, bir <a href="https://adda.net.tr">Adda Teknoloji markasıdır.</a></p>

    </div>

    <script>
        function goToStep2() {
            const user = document.getElementById('username').value;
            if(user.length > 1) {
                document.getElementById('userDisp').innerText = user;
                document.getElementById('step1').classList.add('step-hidden');
                document.getElementById('step2').classList.remove('step-hidden');
                document.getElementById('password').focus();
            }
        }
        function goBack() {
            document.getElementById('step2').classList.add('step-hidden');
            document.getElementById('step1').classList.remove('step-hidden');
        }
        document.getElementById('username').addEventListener("keypress", function(e) {
            if (e.key === "Enter") { e.preventDefault(); goToStep2(); }
        });
    </script>
</body>
</html>