<?php
require '../includes/admin-auth-kontrol.php';
require '../includes/db.php';
header('Content-Type: application/json');

$tc = $_GET['tc'] ?? '';
$haric_id = $_GET['haric_id'] ?? '';

if (!ctype_digit($tc) || strlen($tc) !== 11) {
    echo json_encode(['kayitli' => false]);
    exit;
}

if ($haric_id !== '' && ctype_digit((string)$haric_id)) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM vatandaslar WHERE tc_no = :tc AND deleted = 0 AND id != :haric_id");
    $stmt->execute(['tc' => $tc, 'haric_id' => $haric_id]);
} else {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM vatandaslar WHERE tc_no = :tc AND deleted = 0");
    $stmt->execute(['tc' => $tc]);
}

$sayi = $stmt->fetchColumn();
echo json_encode(['kayitli' => $sayi > 0]);