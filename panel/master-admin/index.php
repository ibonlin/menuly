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
    // Önce resimleri sil
    $stmt = $pdo->prepare("SELECT image FROM products WHERE user_id = ?");
    $stmt->execute([$del_id]);
    $images = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($images as $img) {
        if (!empty($img) && file_exists('../' . $img)) unlink('../' . $img);
    }
    // Sonra kayıtları sil
    $pdo->prepare("DELETE FROM products WHERE user_id = ?")->execute([$del_id]);
    $pdo->prepare("DELETE FROM categories WHERE user_id = ?")->execute([$del_id]);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$del_id]);
    header("Location: index.php?msg=deleted");
    exit;
}

// İstatistikler
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_active = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
$pending_tickets = $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status IN ('open', 'customer_reply')")->fetchColumn();

// Listeleme
$users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Panel | Menuly</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
        .glass-header { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); border-bottom: 1px solid #e2e8f0; }
    </style>
</head>
<body class="text-slate-800">

    <header class="glass-header fixed w-full top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center text-white shadow-lg shadow-indigo-200">
                        <i class="ph-bold ph-crown text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="font-bold text-xl text-slate-900 leading-none">Master Panel</h1>
                        <p class="text-xs text-slate-500 font-medium mt-1">Yönetim Merkezi</p>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <a href="profile.php" class="hidden md:flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-indigo-600 transition">
                        <i class="ph-bold ph-user-circle text-lg"></i> Profilim
                    </a>
                    <a href="logout.php" class="bg-red-50 text-red-600 px-4 py-2 rounded-lg text-sm font-bold hover:bg-red-100 transition flex items-center gap-2">
                        <i class="ph-bold ph-sign-out"></i> Çıkış
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-28 pb-12">
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex flex-col justify-between group hover:border-indigo-300 transition">
                    <div class="w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center text-indigo-600 mb-4 group-hover:scale-110 transition-transform">
                        <i class="ph-fill ph-storefront text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-3xl font-extrabold text-slate-900"><?php echo $total_users; ?></h3>
                        <p class="text-sm font-medium text-slate-500">Toplam Restoran</p>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex flex-col justify-between group hover:border-green-300 transition">
                    <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center text-green-600 mb-4 group-hover:scale-110 transition-transform">
                        <i class="ph-fill ph-check-circle text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-3xl font-extrabold text-slate-900"><?php echo $total_active; ?></h3>
                        <p class="text-sm font-medium text-slate-500">Aktif Üyelik</p>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex flex-col justify-between group hover:border-yellow-300 transition relative overflow-hidden">
                    <?php if($pending_tickets > 0): ?>
                        <span class="absolute top-4 right-4 w-3 h-3 bg-red-500 rounded-full animate-ping"></span>
                        <span class="absolute top-4 right-4 w-3 h-3 bg-red-500 rounded-full"></span>
                    <?php endif; ?>
                    <div class="w-12 h-12 bg-yellow-50 rounded-xl flex items-center justify-center text-yellow-600 mb-4 group-hover:scale-110 transition-transform">
                        <i class="ph-fill ph-ticket text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="text-3xl font-extrabold text-slate-900"><?php echo $pending_tickets; ?></h3>
                        <p class="text-sm font-medium text-slate-500">Bekleyen Destek</p>
                    </div>
                </div>
            </div>

            <div class="bg-slate-900 text-white p-6 rounded-2xl shadow-lg relative overflow-hidden flex flex-col justify-center">
                <div class="absolute top-0 right-0 w-32 h-32 bg-indigo-500 rounded-full blur-3xl opacity-20 -mr-10 -mt-10"></div>
                <h3 class="text-lg font-bold mb-4 flex items-center gap-2"><i class="ph-bold ph-heartbeat"></i> Sistem Sağlığı</h3>
                <div class="space-y-3 relative z-10">
                    <div class="flex justify-between items-center text-sm border-b border-white/10 pb-2">
                        <span class="text-slate-400">PHP Sürümü</span>
                        <span class="font-mono font-bold text-indigo-300"><?php echo phpversion(); ?></span>
                    </div>
                    <div class="flex justify-between items-center text-sm border-b border-white/10 pb-2">
                        <span class="text-slate-400">Sunucu</span>
                        <span class="font-mono font-bold text-green-400">Online</span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-slate-400">Disk Alanı</span>
                        <span class="font-mono font-bold text-blue-300"><?php echo round(disk_free_space("/") / 1024 / 1024 / 1024, 2) . " GB"; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6">
            <div class="flex gap-3 w-full md:w-auto">
                <a href="user_add.php" class="bg-indigo-600 text-white px-5 py-3 rounded-xl font-bold text-sm shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition flex items-center gap-2">
                    <i class="ph-bold ph-plus"></i> Restoran Ekle
                </a>
                <a href="duyurular.php" class="bg-white text-slate-700 border border-slate-200 px-5 py-3 rounded-xl font-bold text-sm hover:border-indigo-300 hover:text-indigo-600 transition flex items-center gap-2">
                    <i class="ph-bold ph-megaphone"></i> Duyurular
                </a>
                                <a href="updates.php" class="bg-white text-slate-700 border border-slate-200 px-5 py-3 rounded-xl font-bold text-sm hover:border-indigo-300 hover:text-indigo-600 transition flex items-center gap-2">
                    <i class="ph-bold ph-megaphone"></i> Güncellemeler
                </a>
                <a href="destek.php" class="bg-white text-slate-700 border border-slate-200 px-5 py-3 rounded-xl font-bold text-sm hover:border-indigo-300 hover:text-indigo-600 transition flex items-center gap-2 relative">
                    <i class="ph-bold ph-chats-circle"></i> Destek
                    <?php if($pending_tickets > 0): ?>
                        <span class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] w-5 h-5 rounded-full flex items-center justify-center"><?php echo $pending_tickets; ?></span>
                    <?php endif; ?>
                </a>
                                <a href="security_logs.php" class="bg-white text-slate-700 border border-slate-200 px-5 py-3 rounded-xl font-bold text-sm hover:border-indigo-300 hover:text-indigo-600 transition flex items-center gap-2">
                    <i class="ph-bold ph-shield"></i> Security Logs
                </a>
            </div>

            <div class="relative w-full md:w-64">
                <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Restoran ara..." class="w-full pl-10 pr-4 py-3 bg-white border border-slate-200 rounded-xl text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition">
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="userTable">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-xs uppercase text-slate-500 font-bold tracking-wider">
                            <th class="p-4 w-16 text-center">ID</th>
                            <th class="p-4">İşletme Bilgisi</th>
                            <th class="p-4">Kalan Süre</th>
                            <th class="p-4 text-center">Durum</th>
                            <th class="p-4 text-right">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <?php foreach($users as $u): ?>
                            <?php
                                $remaining = "Sınırsız";
                                $badgeClass = "bg-slate-100 text-slate-600";
                                $is_expired = false;

                                if (!empty($u['subscription_end'])) {
                                    $end_date = new DateTime($u['subscription_end']);
                                    $today = new DateTime();
                                    $today->setTime(0, 0, 0); $end_date->setTime(0, 0, 0);

                                    if ($end_date < $today) {
                                        $remaining = "SÜRESİ DOLDU";
                                        $badgeClass = "bg-red-100 text-red-700";
                                        $is_expired = true;
                                    } else {
                                        $diff = $today->diff($end_date);
                                        $days = $diff->days;
                                        $remaining = $days . " Gün Kaldı";
                                        if ($days < 7) $badgeClass = "bg-yellow-100 text-yellow-700";
                                        else $badgeClass = "bg-green-100 text-green-700";
                                    }
                                }
                                $rowClass = ($u['is_active'] == 0 || $is_expired) ? 'bg-red-50/50' : 'hover:bg-slate-50';
                            ?>
                            <tr class="transition <?php echo $rowClass; ?>">
                                <td class="p-4 text-center font-mono text-slate-400">#<?php echo $u['id']; ?></td>
                                <td class="p-4">
                                    <div class="font-bold text-slate-900 text-base"><?php echo htmlspecialchars($u['restaurant_name']); ?></div>
                                    <a href="../<?php echo $u['slug']; ?>" target="_blank" class="text-xs text-indigo-500 hover:underline flex items-center gap-1">
                                        <i class="ph-bold ph-link"></i> menuly.net/<?php echo $u['slug']; ?>
                                    </a>
                                </td>
                                <td class="p-4">
                                    <span class="px-3 py-1 rounded-full text-xs font-bold <?php echo $badgeClass; ?>">
                                        <?php echo $remaining; ?>
                                    </span>
                                </td>
                                <td class="p-4 text-center">
                                    <?php if($u['is_active'] == 1): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-green-50 text-green-600 text-xs font-bold border border-green-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Aktif
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-red-50 text-red-600 text-xs font-bold border border-red-200">
                                            <i class="ph-bold ph-lock-key"></i> Pasif
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4">
                                    <div class="flex justify-end items-center gap-2">
                                        <a href="login_as.php?id=<?php echo $u['id']; ?>" target="_blank" class="group flex items-center gap-2 bg-indigo-600 text-white px-3 py-2 rounded-lg text-xs font-bold shadow hover:bg-indigo-700 transition" title="Restoran Paneline Gir">
                                            <i class="ph-bold ph-sign-in"></i> <span class="hidden sm:inline">Yönet</span>
                                        </a>
                                        
                                        <a href="user_edit.php?id=<?php echo $u['id']; ?>" class="w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-600 hover:border-indigo-300 hover:text-indigo-600 flex items-center justify-center transition" title="Düzenle">
                                            <i class="ph-bold ph-pencil-simple"></i>
                                        </a>

                                        <a href="index.php?toggle_status=<?php echo $u['id']; ?>" class="w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-600 hover:border-yellow-300 hover:text-yellow-600 flex items-center justify-center transition" title="Dondur/Aç">
                                            <i class="ph-bold <?php echo $u['is_active'] == 1 ? 'ph-lock-open' : 'ph-lock-key'; ?>"></i>
                                        </a>

                                        <a href="index.php?delete_user=<?php echo $u['id']; ?>" onclick="return confirm('Bu restoranı ve tüm verilerini silmek istediğinize emin misiniz?')" class="w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-600 hover:border-red-300 hover:text-red-600 flex items-center justify-center transition" title="Sil">
                                            <i class="ph-bold ph-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if(count($users) == 0): ?>
                <div class="p-8 text-center text-slate-500">
                    <i class="ph-duotone ph-storefront text-4xl mb-2 opacity-50"></i>
                    <p>Henüz hiç restoran eklenmemiş.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <script>
        // Basit JS Arama Fonksiyonu
        function filterTable() {
            var input, filter, table, tr, td, i, txtValue;
            input = document.getElementById("searchInput");
            filter = input.value.toUpperCase();
            table = document.getElementById("userTable");
            tr = table.getElementsByTagName("tr");

            for (i = 0; i < tr.length; i++) {
                // 1. Sütun (Restoran Adı) içinde arama yap
                td = tr[i].getElementsByTagName("td")[1];
                if (td) {
                    txtValue = td.textContent || td.innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }
            }
        }
    </script>
</body>
</html>