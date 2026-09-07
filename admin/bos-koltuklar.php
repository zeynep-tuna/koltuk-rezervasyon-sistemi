<?php
require '../includes/admin-auth-kontrol.php';
require '../includes/db.php';
header('Content-Type: application/json');

$etkinlik_id = $_GET['etkinlik_id'] ?? '';

if (!ctype_digit((string)$etkinlik_id)) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT id, koltuk_no FROM koltuklar WHERE etkinlik_id = :etkinlik_id AND deleted = 0 AND durum = 'bos'");
$stmt->execute(['etkinlik_id' => $etkinlik_id]);
$sonuclar = $stmt->fetchAll();

usort($sonuclar, function ($a, $b) {
    preg_match('/^([A-Z]+)(\d+)$/', $a['koltuk_no'], $m1);
    preg_match('/^([A-Z]+)(\d+)$/', $b['koltuk_no'], $m2);
    $harfA = $m1[1] ?? '';
    $harfB = $m2[1] ?? '';
    if ($harfA !== $harfB) {
        return $harfA <=> $harfB;
    }
    $numA = isset($m1[2]) ? (int)$m1[2] : 0;
    $numB = isset($m2[2]) ? (int)$m2[2] : 0;
    return $numA <=> $numB;
});

echo json_encode($sonuclar);