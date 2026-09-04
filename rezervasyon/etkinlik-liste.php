<?php
require '../includes/db.php';
header('Content-Type: application/json');

$etkinlikler = $pdo->query("SELECT id, ad, yer, etkinlik_tarih, etkinlik_saat FROM etkinlikler WHERE deleted = 0 AND aktif_mi = 1 ORDER BY etkinlik_tarih ASC")->fetchAll();

foreach ($etkinlikler as &$e) {
    $sayim = $pdo->prepare("SELECT COUNT(*) AS adet FROM koltuklar WHERE etkinlik_id = :id AND deleted = 0 AND durum = 'bos'");
    $sayim->execute(['id' => $e['id']]);
    $e['kalan_koltuk'] = (int)$sayim->fetch()['adet'];
    $e['tarih_gosterim'] = $e['etkinlik_tarih'] ? date('d.m.Y', strtotime($e['etkinlik_tarih'])) : '';
    $e['saat_gosterim'] = $e['etkinlik_saat'] ? substr($e['etkinlik_saat'], 0, 5) : '';
}
unset($e);

echo json_encode($etkinlikler);
