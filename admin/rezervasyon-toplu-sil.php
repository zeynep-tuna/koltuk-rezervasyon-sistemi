<?php
require '../includes/db.php';
header('Content-Type: application/json');

$idler = $_POST['idler'] ?? [];
if (!is_array($idler)) {
    $idler = [$idler];
}
$idler = array_values(array_filter($idler, function ($id) {
    return ctype_digit((string)$id);
}));

if (empty($idler)) {
    echo json_encode(['basarili' => false, 'hata' => 'Geçersiz istek.']);
    exit;
}

$silinen_idler = [];

foreach ($idler as $id) {
    $rez = $pdo->prepare("SELECT koltuk_id FROM rezervasyonlar WHERE id = :id AND deleted = 0");
    $rez->execute(['id' => $id]);
    $rezervasyon = $rez->fetch();

    if ($rezervasyon) {
        $koltuk_guncelle = $pdo->prepare("UPDATE koltuklar SET durum = 'bos' WHERE id = :koltuk_id");
        $koltuk_guncelle->execute(['koltuk_id' => $rezervasyon['koltuk_id']]);

        $sil = $pdo->prepare("UPDATE rezervasyonlar SET deleted = 1 WHERE id = :id");
        $sil->execute(['id' => $id]);

        $silinen_idler[] = (int)$id;
    }
}

echo json_encode(['basarili' => true, 'silinen_idler' => $silinen_idler]);
