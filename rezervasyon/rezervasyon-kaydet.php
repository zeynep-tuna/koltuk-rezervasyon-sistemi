<?php
require '../includes/db.php';
header('Content-Type: application/json');

$vatandas_id = $_POST['vatandas_id'] ?? '';
$etkinlik_id = $_POST['etkinlik_id'] ?? '';
$koltuk_id_listesi = $_POST['koltuk_id'] ?? [];

if (!is_array($koltuk_id_listesi)) {
    $koltuk_id_listesi = [];
}
$koltuk_id_listesi = array_values(array_filter($koltuk_id_listesi, function ($v) {
    return ctype_digit((string)$v);
}));

if (!ctype_digit((string)$vatandas_id) || !ctype_digit((string)$etkinlik_id) || count($koltuk_id_listesi) === 0 || count($koltuk_id_listesi) > 4) {
    echo json_encode(['basarili' => false, 'hata' => 'Geçersiz istek. Lütfen sayfayı yenileyip tekrar dene.']);
    exit;
}

// Vatandas gercekten var mi kontrol et
$vatandas_kontrol = $pdo->prepare("SELECT ad, soyad FROM vatandaslar WHERE id = :id AND deleted = 0");
$vatandas_kontrol->execute(['id' => $vatandas_id]);
$vatandas_satiri = $vatandas_kontrol->fetch();
if (!$vatandas_satiri) {
    echo json_encode(['basarili' => false, 'hata' => 'Kimlik bilgisi doğrulanamadı. Lütfen baştan dene.']);
    exit;
}

// Etkinlik gercekten aktif mi kontrol et
$etkinlik_kontrol = $pdo->prepare("SELECT ad, yer, etkinlik_tarih, etkinlik_saat FROM etkinlikler WHERE id = :id AND deleted = 0 AND aktif_mi = 1");
$etkinlik_kontrol->execute(['id' => $etkinlik_id]);
$etkinlik_satiri = $etkinlik_kontrol->fetch();
if (!$etkinlik_satiri) {
    echo json_encode(['basarili' => false, 'hata' => 'Bu etkinlik artık rezervasyona açık değil.']);
    exit;
}

// Koltuklarin hepsi hala bos mu diye son bir kontrol (baskasi ayni anda almis olabilir)
$yer_tutucular = implode(',', array_fill(0, count($koltuk_id_listesi), '?'));
$koltuk_kontrol = $pdo->prepare("SELECT id, koltuk_no, durum FROM koltuklar WHERE id IN ($yer_tutucular) AND etkinlik_id = ? AND deleted = 0");
$koltuk_kontrol->execute(array_merge($koltuk_id_listesi, [$etkinlik_id]));
$bulunan_koltuklar = $koltuk_kontrol->fetchAll();

$hepsi_bos_mu = count($bulunan_koltuklar) === count($koltuk_id_listesi);
foreach ($bulunan_koltuklar as $k) {
    if ($k['durum'] !== 'bos') {
        $hepsi_bos_mu = false;
    }
}

if (!$hepsi_bos_mu) {
    echo json_encode(['basarili' => false, 'hata' => 'Seçtiğin koltuklardan biri az önce başkası tarafından alındı. Lütfen tekrar seç.']);
    exit;
}

$sonuc_listesi = [];
foreach ($bulunan_koltuklar as $k) {
    $yeni_qr_token = bin2hex(random_bytes(16));

    $ekle = $pdo->prepare("INSERT INTO rezervasyonlar (vatandas_id, etkinlik_id, koltuk_id, qr_token) VALUES (:vatandas_id, :etkinlik_id, :koltuk_id, :qr_token)");
    $ekle->execute([
        'vatandas_id' => $vatandas_id,
        'etkinlik_id' => $etkinlik_id,
        'koltuk_id' => $k['id'],
        'qr_token' => $yeni_qr_token,
    ]);

    $koltuk_guncelle = $pdo->prepare("UPDATE koltuklar SET durum = 'dolu' WHERE id = :id");
    $koltuk_guncelle->execute(['id' => $k['id']]);

    $sonuc_listesi[] = [
        'koltuk_no' => $k['koltuk_no'],
        'qr_token' => $yeni_qr_token,
        'referans_kodu' => strtoupper(substr($yeni_qr_token, 0, 12)),
    ];
}

// Koltuk numarasina gore dogru (sayisal) sirala
usort($sonuc_listesi, function ($a, $b) {
    preg_match('/^([A-Z]+)(\d+)$/', $a['koltuk_no'], $m1);
    preg_match('/^([A-Z]+)(\d+)$/', $b['koltuk_no'], $m2);
    $harfA = $m1[1] ?? '';
    $harfB = $m2[1] ?? '';
    if ($harfA !== $harfB) {
        return $harfA <=> $harfB;
    }
    return ((int)($m1[2] ?? 0)) <=> ((int)($m2[2] ?? 0));
});

echo json_encode([
    'basarili' => true,
    'isim' => $vatandas_satiri['ad'] . ' ' . $vatandas_satiri['soyad'],
    'etkinlik_adi' => $etkinlik_satiri['ad'],
    'etkinlik_yer' => $etkinlik_satiri['yer'],
    'etkinlik_tarih' => $etkinlik_satiri['etkinlik_tarih'] ? date('d.m.Y', strtotime($etkinlik_satiri['etkinlik_tarih'])) : '',
    'etkinlik_saat' => $etkinlik_satiri['etkinlik_saat'] ? substr($etkinlik_satiri['etkinlik_saat'], 0, 5) : '',
    'rezervasyonlar' => $sonuc_listesi,
]);
