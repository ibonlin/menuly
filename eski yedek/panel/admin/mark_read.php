<?php
// Oturumu başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';

// JSON formatında yanıt ver
header('Content-Type: application/json');

// Güvenlik: Oturum yoksa durdur
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Oturum bulunamadı.']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Okuma tarihini güncelle
    // Not: Request metoduna bakmaksızın işlemi yapıyoruz (Sunucu yönlendirme hatasını aşmak için)
    $stmt = $pdo->prepare("UPDATE users SET last_read_announcement_at = NOW() WHERE id = ?");
    $result = $stmt->execute([$user_id]);
    
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Veritabanı güncellenemedi.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>