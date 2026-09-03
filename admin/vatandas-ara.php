<?php
require '../includes/admin-auth-kontrol.php';
require '../includes/db.php';
header('Content-Type: application/json');

$arama = trim($_GET['tc'] ?? '');

if (strlen($arama) < 3) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT id, tc_no, ad, soyad FROM vatandaslar WHERE deleted = 0 AND tc_no LIKE :arama ORDER BY tc_no ASC LIMIT 8");
$stmt->execute(['arama' => $arama . '%']);
$sonuclar = $stmt->fetchAll();

echo json_encode($sonuclar);
