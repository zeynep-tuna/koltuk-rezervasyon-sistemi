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

$tc_no = trim($_POST['tc_no'] ?? '');
$dogum_tarihi = trim($_POST['dogum_tarihi'] ?? '');
$qr_token = trim($_POST['qr_token'] ?? '');

if (!preg_match('/^[0-9]{11}$/', $tc_no) || !tc_gecerli_mi($tc_no) || $dogum_tarihi === '' || $qr_token === '') {
    echo json_encode(['basarili' => false, 'hata' => 'Geçersiz istek.']);
    exit;
}

// Kimligi dogrula
$vatandas_stmt = $pdo->prepare("SELECT id FROM vatandaslar WHERE tc_no = :tc_no AND dogum_tarihi = :dogum_tarihi AND deleted = 0");
$vatandas_stmt->execute(['tc_no' => $tc_no, 'dogum_tarihi' => $dogum_tarihi]);
$vatandas = $vatandas_stmt->fetch();

if (!$vatandas) {
    echo json_encode(['basarili' => false, 'hata' => 'Kimlik doğrulanamadı.']);
    exit;
}

// Rezervasyon gercekten bu kisiye mi ait, kontrol et
$rez_stmt = $pdo->prepare("SELECT id, koltuk_id FROM rezervasyonlar WHERE qr_token = :qr_token AND vatandas_id = :vatandas_id AND deleted = 0");
$rez_stmt->execute(['qr_token' => $qr_token, 'vatandas_id' => $vatandas['id']]);
$rezervasyon = $rez_stmt->fetch();

if (!$rezervasyon) {
    echo json_encode(['basarili' => false, 'hata' => 'Rezervasyon bulunamadı.']);
    exit;
}

$iptal = $pdo->prepare("UPDATE rezervasyonlar SET deleted = 1 WHERE id = :id");
$iptal->execute(['id' => $rezervasyon['id']]);

$koltuk_bosalt = $pdo->prepare("UPDATE koltuklar SET durum = 'bos' WHERE id = :id");
$koltuk_bosalt->execute(['id' => $rezervasyon['koltuk_id']]);

echo json_encode(['basarili' => true]);
