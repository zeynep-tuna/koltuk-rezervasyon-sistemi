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

$vatandas_stmt = $pdo->prepare("SELECT id, ad, soyad, dogum_tarihi FROM vatandaslar WHERE tc_no = :tc_no AND deleted = 0");
$vatandas_stmt->execute(['tc_no' => $tc_no]);
$vatandas = $vatandas_stmt->fetch();

if (!$vatandas) {
    echo json_encode(['durum' => 'bulunamadi']);
    exit;
}

if ($vatandas['dogum_tarihi'] !== $dogum_tarihi) {
    echo json_encode(['durum' => 'eslesmiyor']);
    exit;
}

$rez_stmt = $pdo->prepare("
    SELECT r.qr_token, r.giris_yapildi_mi, k.koltuk_no, e.ad AS etkinlik_adi, e.yer, e.etkinlik_tarih, e.etkinlik_saat
    FROM rezervasyonlar r
    JOIN koltuklar k ON k.id = r.koltuk_id
    JOIN etkinlikler e ON e.id = r.etkinlik_id
    WHERE r.vatandas_id = :vatandas_id AND r.deleted = 0
    ORDER BY
        CASE WHEN e.etkinlik_tarih >= CURDATE() THEN 0 ELSE 1 END,
        CASE WHEN e.etkinlik_tarih >= CURDATE() THEN e.etkinlik_tarih END ASC,
        CASE WHEN e.etkinlik_tarih < CURDATE() THEN e.etkinlik_tarih END DESC,
        k.koltuk_no ASC
");
$rez_stmt->execute(['vatandas_id' => $vatandas['id']]);
$rezervasyonlar_ham = $rez_stmt->fetchAll();

$rezervasyonlar = array_map(function ($r) {
    return [
        'koltuk_no' => $r['koltuk_no'],
        'referans_kodu' => strtoupper(substr($r['qr_token'], 0, 12)),
        'qr_token' => $r['qr_token'],
        'giris_yapildi_mi' => (bool)$r['giris_yapildi_mi'],
        'etkinlik_adi' => $r['etkinlik_adi'],
        'yer' => $r['yer'],
        'tarih' => $r['etkinlik_tarih'] ? date('d.m.Y', strtotime($r['etkinlik_tarih'])) : '',
        'saat' => $r['etkinlik_saat'] ? substr($r['etkinlik_saat'], 0, 5) : '',
    ];
}, $rezervasyonlar_ham);

echo json_encode([
    'durum' => 'bulundu',
    'ad' => $vatandas['ad'],
    'soyad' => $vatandas['soyad'],
    'rezervasyonlar' => $rezervasyonlar,
]);