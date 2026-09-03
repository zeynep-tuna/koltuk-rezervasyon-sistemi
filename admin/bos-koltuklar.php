<?php
require '../includes/admin-auth-kontrol.php';
require '../includes/db.php';
header('Content-Type: application/json');

$etkinlik_id = $_GET['etkinlik_id'] ?? '';

if (!ctype_digit((string)$etkinlik_id)) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT id, koltuk_no FROM koltuklar WHERE etkinlik_id = :etkinlik_id AND deleted = 0 AND durum = 'bos' ORDER BY koltuk_no ASC");
$stmt->execute(['etkinlik_id' => $etkinlik_id]);
$sonuclar = $stmt->fetchAll();

echo json_encode($sonuclar);
