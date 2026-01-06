<?php
// Bu dosya XML çıktısı verecek
header("Content-Type: application/xml; charset=utf-8");

// Veritabanı bağlantısı
require_once 'panel/includes/db.php';

// Site adresin (Sonunda / olmasın)
$baseUrl = "https://menuly.net";

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

    <url>
        <loc><?php echo $baseUrl; ?>/</loc>
        <lastmod><?php echo date("Y-m-d"); ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    
    <url>
        <loc><?php echo $baseUrl; ?>/panel/admin/login.php</loc>
        <lastmod><?php echo date("Y-m-d"); ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>

    <url>
        <loc><?php echo $baseUrl; ?>/panel/demo</loc>
        <lastmod><?php echo date("Y-m-d"); ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
        <url>
        <loc><?php echo $baseUrl; ?>/kurumsal</loc>
        <lastmod><?php echo date("Y-m-d"); ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>

    <?php
    try {
        // HATA ÇÖZÜMÜ: Sadece 'slug' sütununu çekiyoruz.
        // 'updated_at' sütunu olmadığı için onu sorgudan kaldırdık.
        $stmt = $pdo->prepare("SELECT slug FROM users WHERE is_active = 1");
        $stmt->execute();
        $restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($restaurants as $res):
            // Tarih sütunu çekmediğimiz için tarihi "Bugün" olarak ayarlıyoruz
            $date = date("Y-m-d");
    ?>
    <url>
        <loc><?php echo $baseUrl; ?>/<?php echo htmlspecialchars($res['slug']); ?></loc>
        <lastmod><?php echo $date; ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.9</priority>
    </url>
    <?php 
        endforeach; 
    } catch (PDOException $e) {
        // Olası bir hatada XML yapısını bozmamak için sessiz kalalım veya loglayalım
        // error_log($e->getMessage());
    }
    ?>

</urlset>