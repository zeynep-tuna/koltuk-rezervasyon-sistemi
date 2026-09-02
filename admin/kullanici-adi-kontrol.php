<?php
require '../includes/admin-auth-kontrol.php';
require '../includes/db.php';
header('Content-Type: application/json');

$kullanici_adi = trim($_GET['kullanici_adi'] ?? '');

if ($kullanici_adi === '') {
    echo json_encode(['var_mi' => false]);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM adminler WHERE kullanici_adi = :kullanici_adi AND deleted = 0");
$stmt->execute(['kullanici_adi' => $kullanici_adi]);

echo json_encode(['var_mi' => (bool)$stmt->fetch()]);