<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/panel/assets/favicon.png">
    <title>Sayfa Bulunamadı | Menuly</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .glass-card { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.3); }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-6">
    <div class="max-w-md w-full text-center">
        <div class="mb-8 flex justify-center">
            <div class="w-24 h-24 bg-blue-100 rounded-3xl flex items-center justify-center text-blue-600 shadow-xl shadow-blue-500/10">
                <i class="ph-fill ph-detective text-5xl"></i>
            </div>
        </div>

        <h1 class="text-9xl font-extrabold text-slate-200 mb-2">404</h1>
        <h2 class="text-2xl font-bold text-slate-800 mb-4">Aradığın lezzet burada değil!</h2>
        <p class="text-slate-500 mb-10 leading-relaxed">Ulaşmaya çalıştığın sayfa taşınmış, silinmiş veya hiç var olmamış olabilir.</p>

        <div class="grid gap-4">
            <a href="/panel/admin" class="bg-blue-600 text-white font-bold py-4 rounded-2xl hover:bg-blue-700 transition-all shadow-lg shadow-blue-500/20 flex items-center justify-center gap-2">
                <i class="ph-bold ph-house"></i> Panele Dön
            </a>
            <a href="/panel/admin/destek" class="bg-white text-slate-700 font-bold py-4 rounded-2xl border border-slate-200 hover:bg-slate-50 transition-all flex items-center justify-center gap-2">
                <i class="ph-bold ph-life-buoy"></i> Destek Al
            </a>
        </div>

        <p class="mt-12 text-slate-400 text-xs font-bold uppercase tracking-widest">©  Menuly.</p>
    </div>
</body>
</html>