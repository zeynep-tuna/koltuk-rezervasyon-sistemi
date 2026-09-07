<?php
require '../includes/db.php';
header('Content-Type: application/json');

$etkinlik_id = $_GET['etkinlik_id'] ?? '';

if (!ctype_digit((string)$etkinlik_id)) {
    echo json_encode(['hata' => true]);
    exit;
}

$etkinlik_stmt = $pdo->prepare("SELECT satir_basi_koltuk, koridor_sonrasi FROM etkinlikler WHERE id = :id AND deleted = 0 AND aktif_mi = 1");
$etkinlik_stmt->execute(['id' => $etkinlik_id]);
$etkinlik = $etkinlik_stmt->fetch();

if (!$etkinlik) {
    echo json_encode(['hata' => true]);
    exit;
}

$koltuklar_stmt = $pdo->prepare("SELECT id, koltuk_no, durum FROM koltuklar WHERE etkinlik_id = :id AND deleted = 0");
$koltuklar_stmt->execute(['id' => $etkinlik_id]);
$koltuklar_ham = $koltuklar_stmt->fetchAll();

// Koltuklari harf+numaraya gore doğru (sayisal) sirala - metin sirasi degil
usort($koltuklar_ham, function ($a, $b) {
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

echo json_encode([
    'hata' => false,
    'satir_basi_koltuk' => $etkinlik['satir_basi_koltuk'],
    'koridor_sonrasi' => $etkinlik['koridor_sonrasi'],
    'koltuklar' => $koltuklar_ham,
]);