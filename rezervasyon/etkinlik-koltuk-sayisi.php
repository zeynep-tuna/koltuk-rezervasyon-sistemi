<?php
require '../includes/db.php';
header('Content-Type: application/json');

$etkinlik_id = $_GET['etkinlik_id'] ?? '';

if (!ctype_digit((string)$etkinlik_id)) {
    echo json_encode(['hata' => true]);
    exit;
}

$etkinlik_stmt = $pdo->prepare("SELECT ad, yer, etkinlik_tarih, etkinlik_saat FROM etkinlikler WHERE id = :id AND deleted = 0 AND aktif_mi = 1");
$etkinlik_stmt->execute(['id' => $etkinlik_id]);
$etkinlik = $etkinlik_stmt->fetch();

if (!$etkinlik) {
    echo json_encode(['hata' => true]);
    exit;
}

$sayim = $pdo->prepare("SELECT COUNT(*) AS adet FROM koltuklar WHERE etkinlik_id = :id AND deleted = 0 AND durum = 'bos'");
$sayim->execute(['id' => $etkinlik_id]);
$kalan = (int)$sayim->fetch()['adet'];

echo json_encode([
    'hata' => false,
    'ad' => $etkinlik['ad'],
    'yer' => $etkinlik['yer'],
    'tarih' => $etkinlik['etkinlik_tarih'] ? date('d.m.Y', strtotime($etkinlik['etkinlik_tarih'])) : '',
    'saat' => $etkinlik['etkinlik_saat'] ? substr($etkinlik['etkinlik_saat'], 0, 5) : '',
    'kalan' => $kalan,
]);
