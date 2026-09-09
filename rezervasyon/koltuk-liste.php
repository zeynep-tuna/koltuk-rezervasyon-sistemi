<?php
require '../includes/db.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

$etkinlik_id = $_GET['etkinlik_id'] ?? '';
$oturum_token = trim($_GET['oturum_token'] ?? '');

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

$koltuklar_stmt = $pdo->prepare("SELECT id, koltuk_no, durum, tutan_oturum, tutulma_bitis FROM koltuklar WHERE etkinlik_id = :id AND deleted = 0");
$koltuklar_stmt->execute(['id' => $etkinlik_id]);
$koltuklar_ham = $koltuklar_stmt->fetchAll();

$simdi = time();
foreach ($koltuklar_ham as &$k) {
    $baskasi_tutuyor_mu = $k['durum'] === 'bos'
        && $k['tutan_oturum'] !== null
        && $k['tutan_oturum'] !== $oturum_token
        && $k['tutulma_bitis'] !== null
        && strtotime($k['tutulma_bitis']) > $simdi;

    $k['musait_mi'] = ($k['durum'] === 'bos' && !$baskasi_tutuyor_mu);
    $k['kendim_mi_tutuyorum'] = ($k['durum'] === 'bos' && $k['tutan_oturum'] === $oturum_token && $oturum_token !== '' && $k['tutulma_bitis'] !== null && strtotime($k['tutulma_bitis']) > $simdi);

    unset($k['tutan_oturum']);
    unset($k['tutulma_bitis']);
}
unset($k);

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