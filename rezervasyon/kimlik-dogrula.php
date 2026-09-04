<?php
require '../includes/db.php';
header('Content-Type: application/json');

function tc_gecerli_mi($tc) {
    if (!preg_match('/^[0-9]{11}$/', $tc)) return false;
    if ($tc[0] === '0') return false;

    $d = array_map('intval', str_split($tc));
    $tekSayilarToplami = $d[0] + $d[2] + $d[4] + $d[6] + $d[8];
    $ciftSayilarToplami = $d[1] + $d[3] + $d[5] + $d[7];

    $onuncuHane = (($tekSayilarToplami * 7) - $ciftSayilarToplami) % 10;
    if ($onuncuHane < 0) $onuncuHane += 10;

    $onbirinciHane = ($tekSayilarToplami + $ciftSayilarToplami + $onuncuHane) % 10;

    return $onuncuHane === $d[9] && $onbirinciHane === $d[10];
}

$tc_no = trim($_GET['tc_no'] ?? '');
$dogum_tarihi = trim($_GET['dogum_tarihi'] ?? '');

if (!preg_match('/^[0-9]{11}$/', $tc_no) || $dogum_tarihi === '') {
    echo json_encode(['durum' => 'gecersiz']);
    exit;
}

if (!tc_gecerli_mi($tc_no)) {
    echo json_encode(['durum' => 'gecersiz_tc']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, ad, soyad, dogum_tarihi FROM vatandaslar WHERE tc_no = :tc_no AND deleted = 0");
$stmt->execute(['tc_no' => $tc_no]);
$vatandas = $stmt->fetch();

if (!$vatandas || $vatandas['dogum_tarihi'] !== $dogum_tarihi) {
    // Guvenlik: "TC var ama tarih yanlis" ile "TC hic yok" ayni mesaji donuyor,
    // boylece biri deneme-yanilmayla bir TC'nin sistemde olup olmadigini anlayamaz.
    echo json_encode(['durum' => 'bulunamadi']);
    exit;
}

echo json_encode([
    'durum' => 'bulundu',
    'id' => $vatandas['id'],
    'ad' => $vatandas['ad'],
    'soyad' => $vatandas['soyad'],
]);