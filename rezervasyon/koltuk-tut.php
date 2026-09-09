<?php
require '../includes/db.php';
header('Content-Type: application/json');

const TUTMA_SURESI_SANIYE = 300; // 5 dakika

$koltuk_id = $_POST['koltuk_id'] ?? '';
$etkinlik_id = $_POST['etkinlik_id'] ?? '';
$oturum_token = trim($_POST['oturum_token'] ?? '');
$islem = $_POST['islem'] ?? '';

if (!ctype_digit((string)$koltuk_id) || !ctype_digit((string)$etkinlik_id) || $oturum_token === '' || !in_array($islem, ['tut', 'birak'], true)) {
    echo json_encode(['basarili' => false, 'hata' => 'Geçersiz istek.']);
    exit;
}

if ($islem === 'birak') {
    // Sadece kendi tuttugun koltugu birakabilirsin
    $birak = $pdo->prepare("UPDATE koltuklar SET tutan_oturum = NULL, tutulma_bitis = NULL WHERE id = :id AND etkinlik_id = :etkinlik_id AND tutan_oturum = :oturum_token");
    $birak->execute([
        'id' => $koltuk_id,
        'etkinlik_id' => $etkinlik_id,
        'oturum_token' => $oturum_token,
    ]);
    echo json_encode(['basarili' => true]);
    exit;
}

// islem === 'tut'
$stmt = $pdo->prepare("SELECT durum, tutan_oturum, tutulma_bitis FROM koltuklar WHERE id = :id AND etkinlik_id = :etkinlik_id AND deleted = 0");
$stmt->execute(['id' => $koltuk_id, 'etkinlik_id' => $etkinlik_id]);
$koltuk = $stmt->fetch();

if (!$koltuk) {
    echo json_encode(['basarili' => false, 'hata' => 'Koltuk bulunamadı.']);
    exit;
}

if ($koltuk['durum'] !== 'bos') {
    echo json_encode(['basarili' => false, 'hata' => 'Bu koltuk artık boş değil.']);
    exit;
}

$baskasi_tutuyor_mu = $koltuk['tutan_oturum'] !== null
    && $koltuk['tutan_oturum'] !== $oturum_token
    && $koltuk['tutulma_bitis'] !== null
    && strtotime($koltuk['tutulma_bitis']) > time();

if ($baskasi_tutuyor_mu) {
    echo json_encode(['basarili' => false, 'hata' => 'Bu koltuk şu anda başka biri tarafından değerlendiriliyor.']);
    exit;
}

$yeni_bitis = date('Y-m-d H:i:s', time() + TUTMA_SURESI_SANIYE);
$tut = $pdo->prepare("UPDATE koltuklar SET tutan_oturum = :oturum_token, tutulma_bitis = :bitis WHERE id = :id AND etkinlik_id = :etkinlik_id AND durum = 'bos'");
$tut->execute([
    'oturum_token' => $oturum_token,
    'bitis' => $yeni_bitis,
    'id' => $koltuk_id,
    'etkinlik_id' => $etkinlik_id,
]);

echo json_encode(['basarili' => true, 'tutulma_bitis' => $yeni_bitis]);