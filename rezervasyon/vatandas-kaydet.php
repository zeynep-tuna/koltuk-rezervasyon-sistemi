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
$ad = trim($_POST['ad'] ?? '');
$soyad = trim($_POST['soyad'] ?? '');
$telefon = trim($_POST['telefon'] ?? '');
$telefon_rakam = preg_replace('/[^0-9]/', '', $telefon);

if (!preg_match('/^[0-9]{11}$/', $tc_no) || $dogum_tarihi === '' || $ad === '' || $soyad === '' || $telefon === '') {
    echo json_encode(['basarili' => false, 'hata' => 'Lütfen tüm alanları doldur.']);
    exit;
}

if (!tc_gecerli_mi($tc_no)) {
    echo json_encode(['basarili' => false, 'hata' => 'Geçersiz TC kimlik numarası.']);
    exit;
}

if (!preg_match('/^[a-zA-ZçğıöşüÇĞİÖŞÜ\s]+$/u', $ad) || !preg_match('/^[a-zA-ZçğıöşüÇĞİÖŞÜ\s]+$/u', $soyad)) {
    echo json_encode(['basarili' => false, 'hata' => 'Ad ve soyad alanına sadece harf girilebilir.']);
    exit;
}

if (strlen($telefon_rakam) !== 10) {
    echo json_encode(['basarili' => false, 'hata' => 'Lütfen geçerli bir telefon numarası gir.']);
    exit;
}

// Guvenlik icin: bu TC gercekten sistemde yok mu tekrar kontrol et
$kontrol = $pdo->prepare("SELECT id FROM vatandaslar WHERE tc_no = :tc_no AND deleted = 0");
$kontrol->execute(['tc_no' => $tc_no]);
if ($kontrol->fetch()) {
    // Guvenlik: TC'nin zaten kayitli oldugunu acikca soylemiyoruz (deneme-yanilma riski)
    echo json_encode(['basarili' => false, 'hata' => 'Kayıt tamamlanamadı. Lütfen bilgilerini kontrol edip tekrar dene.']);
    exit;
}

$ekle = $pdo->prepare("INSERT INTO vatandaslar (tc_no, ad, soyad, dogum_tarihi, telefon) VALUES (:tc_no, :ad, :soyad, :dogum_tarihi, :telefon)");
$ekle->execute([
    'tc_no' => $tc_no,
    'ad' => $ad,
    'soyad' => $soyad,
    'dogum_tarihi' => $dogum_tarihi,
    'telefon' => $telefon,
]);

echo json_encode([
    'basarili' => true,
    'id' => $pdo->lastInsertId(),
    'ad' => $ad,
    'soyad' => $soyad,
]);