<?php
require '../includes/admin-auth-kontrol.php';
require '../includes/db.php';

$basari = '';
$hata = '';

// Rezervasyon iptal etme
if (isset($_GET['iptal'])) {
    $iptal_id = $_GET['iptal'];
    if (ctype_digit((string)$iptal_id)) {
        $rez = $pdo->prepare("SELECT koltuk_id FROM rezervasyonlar WHERE id = :id AND deleted = 0");
        $rez->execute(['id' => $iptal_id]);
        $rezervasyon = $rez->fetch();

        if ($rezervasyon) {
            $koltuk_guncelle = $pdo->prepare("UPDATE koltuklar SET durum = 'bos' WHERE id = :koltuk_id");
            $koltuk_guncelle->execute(['koltuk_id' => $rezervasyon['koltuk_id']]);

            $sil = $pdo->prepare("UPDATE rezervasyonlar SET deleted = 1 WHERE id = :id");
            $sil->execute(['id' => $iptal_id]);

            header('Location: rezervasyonlar.php?iptal_edildi=1');
            exit;
        }
    }
}

if (isset($_GET['iptal_edildi'])) {
    $basari = 'Rezervasyon iptal edildi ve koltuk boşa çıkarıldı.';
}

// Giris durumunu degistir (toggle)
if (isset($_GET['giris_toggle'])) {
    $toggle_id = $_GET['giris_toggle'];
    if (ctype_digit((string)$toggle_id)) {
        $mevcut = $pdo->prepare("SELECT giris_yapildi_mi FROM rezervasyonlar WHERE id = :id AND deleted = 0");
        $mevcut->execute(['id' => $toggle_id]);
        $satir = $mevcut->fetch();
        if ($satir) {
            $yeni_durum = $satir['giris_yapildi_mi'] ? 0 : 1;
            $guncelle = $pdo->prepare("UPDATE rezervasyonlar SET giris_yapildi_mi = :durum, giris_zamani = :zaman WHERE id = :id");
            $guncelle->execute([
                'durum' => $yeni_durum,
                'zaman' => $yeni_durum ? date('Y-m-d H:i:s') : null,
                'id' => $toggle_id,
            ]);
        }
        $geri_don = 'rezervasyonlar.php?' . http_build_query(array_filter([
            'etkinlik_id' => $_GET['etkinlik_id'] ?? null,
            'giris' => $_GET['giris'] ?? null,
            'q' => $_GET['q'] ?? null,
        ]));
        header('Location: ' . $geri_don);
        exit;
    }
}

$arama = trim($_GET['q'] ?? '');
$etkinlik_filtre = $_GET['etkinlik_id'] ?? '';
$giris_filtre = $_GET['giris'] ?? 'tumu';

$etkinlikler_listesi = $pdo->query("SELECT id, ad FROM etkinlikler WHERE deleted = 0 ORDER BY ad ASC")->fetchAll();

$secili_etkinlik_adi = '';
if ($etkinlik_filtre !== '') {
    foreach ($etkinlikler_listesi as $e) {
        if ((string)$e['id'] === (string)$etkinlik_filtre) {
            $secili_etkinlik_adi = $e['ad'];
            break;
        }
    }
}

$sql = "SELECT r.id, r.olusturma_tarihi, r.protokol_adi, r.qr_token, r.giris_yapildi_mi, r.giris_zamani,
               v.tc_no, v.ad AS vatandas_ad, v.soyad AS vatandas_soyad, v.telefon,
               e.ad AS etkinlik_ad, e.etkinlik_tarih, e.etkinlik_saat, k.koltuk_no
        FROM rezervasyonlar r
        LEFT JOIN vatandaslar v ON r.vatandas_id = v.id
        JOIN etkinlikler e ON r.etkinlik_id = e.id
        JOIN koltuklar k ON r.koltuk_id = k.id
        WHERE r.deleted = 0";

$parametreler = [];
if ($etkinlik_filtre !== '' && ctype_digit((string)$etkinlik_filtre)) {
    $sql .= " AND r.etkinlik_id = :etkinlik_id";
    $parametreler['etkinlik_id'] = $etkinlik_filtre;
}
if ($giris_filtre === 'yapti') {
    $sql .= " AND r.giris_yapildi_mi = 1";
} elseif ($giris_filtre === 'bekliyor') {
    $sql .= " AND r.giris_yapildi_mi = 0";
}
if ($arama !== '') {
    $sql .= " AND (v.ad LIKE :arama OR v.soyad LIKE :arama OR v.tc_no LIKE :arama OR e.ad LIKE :arama OR r.protokol_adi LIKE :arama)";
    $parametreler['arama'] = '%' . $arama . '%';
}
$sql .= " ORDER BY r.olusturma_tarihi DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($parametreler);
$rezervasyonlar = $stmt->fetchAll();

function url_koru($ekstra = []) {
    $mevcut = [
        'etkinlik_id' => $_GET['etkinlik_id'] ?? null,
        'giris' => $_GET['giris'] ?? null,
        'q' => $_GET['q'] ?? null,
    ];
    return 'rezervasyonlar.php?' . http_build_query(array_filter(array_merge($mevcut, $ekstra)));
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Admin Paneli - Rezervasyonlar</title>
    <link rel="stylesheet" href="../css/admin-layout.css">
    <link rel="stylesheet" href="../css/admin-rezervasyonlar.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body>

    <div class="admin-layout">
        <?php require '../includes/admin-sidebar.php'; ?>

        <div class="admin-main">
            <?php require '../includes/admin-topbar.php'; ?>

            <div class="admin-content">
                <div class="rez-baslik-satiri">
                    <h1>Rezervasyonlar</h1>
                    <div class="rez-filtre-grubu">
                        <div class="ozel-secici-sarmalayici">
                            <button type="button" class="ozel-secici-buton" onclick="ozelListeAcKapa()"><?= $etkinlik_filtre !== '' ? htmlspecialchars($secili_etkinlik_adi) : 'Tüm Etkinlikler' ?></button>
                            <div class="ozel-secici-liste" id="etkinlik-liste">
                                <div class="ozel-secici-madde <?= $etkinlik_filtre === '' ? 'ozel-secici-madde-secili' : '' ?>" onclick="etkinlikSec('')">Tüm Etkinlikler</div>
                                <?php foreach ($etkinlikler_listesi as $e): ?>
                                    <div class="ozel-secici-madde <?= (string)$etkinlik_filtre === (string)$e['id'] ? 'ozel-secici-madde-secili' : '' ?>" onclick="etkinlikSec(<?= $e['id'] ?>)"><?= htmlspecialchars($e['ad']) ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="giris-filtre-satiri">
                            <a href="<?= url_koru(['giris' => null]) ?>" class="giris-filtre-sekme <?= $giris_filtre === 'tumu' ? 'giris-filtre-aktif' : '' ?>">Tümü</a>
                            <a href="<?= url_koru(['giris' => 'yapti']) ?>" class="giris-filtre-sekme <?= $giris_filtre === 'yapti' ? 'giris-filtre-aktif' : '' ?>">Giriş Yaptı</a>
                            <a href="<?= url_koru(['giris' => 'bekliyor']) ?>" class="giris-filtre-sekme <?= $giris_filtre === 'bekliyor' ? 'giris-filtre-aktif' : '' ?>">Bekliyor</a>
                        </div>
                    </div>
                </div>

                <?php if ($basari !== ''): ?>
                    <div class="toast-bildirim" id="basari-toast"><?= htmlspecialchars($basari) ?></div>
                <?php endif; ?>

                <table class="admin-tablo">
                    <thead>
                        <tr>
                            <th>Sıra No</th>
                            <th>Vatandaş</th>
                            <th>TC</th>
                            <th>Etkinlik</th>
                            <th>Koltuk</th>
                            <th>Rezervasyon Tarihi</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($rezervasyonlar) === 0): ?>
                            <tr>
                                <td colspan="7" class="bos-satir">
                                    <?= $arama !== '' ? 'Bu aramaya uyan rezervasyon yok.' : 'Henüz hiç rezervasyon yapılmadı.' ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $sira = 1; foreach ($rezervasyonlar as $r): ?>
                                <tr class="<?= $r['giris_yapildi_mi'] ? 'satir-giris-yapildi' : '' ?>">
                                    <td><?= $sira++ ?></td>
                                    <td>
                                        <?php if ($r['protokol_adi'] !== null): ?>
                                            <?= htmlspecialchars($r['protokol_adi']) ?> <span class="protokol-etiketi">Protokol</span>
                                        <?php else: ?>
                                            <?= htmlspecialchars($r['vatandas_ad'] . ' ' . $r['vatandas_soyad']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $r['protokol_adi'] !== null ? '<span class="tc-yok">—</span>' : htmlspecialchars($r['tc_no']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($r['etkinlik_ad']) ?>
                                        <?php if ($r['etkinlik_tarih']): ?>
                                            <span class="rezervasyon-etkinlik-tarih"><?= date('d.m.Y', strtotime($r['etkinlik_tarih'])) ?><?= $r['etkinlik_saat'] ? ' ' . substr($r['etkinlik_saat'], 0, 5) : '' ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="koltuk-etiketi"><?= htmlspecialchars($r['koltuk_no']) ?></span></td>
                                    <td><?= date('d.m.Y H:i', strtotime($r['olusturma_tarihi'])) ?></td>
                                    <td class="islemler">
                                        <a href="<?= url_koru(['giris_toggle' => $r['id']]) ?>" class="giris-kutu <?= $r['giris_yapildi_mi'] ? 'giris-kutu-isaretli' : '' ?>" title="<?= $r['giris_yapildi_mi'] ? 'Giriş yaptı - kaldırmak için tıkla' : 'Giriş yapmadı - işaretlemek için tıkla' ?>"></a>
                                        <button type="button" class="btn-qr" onclick="qrGoster('<?= htmlspecialchars(addslashes($r['qr_token'])) ?>', '<?= htmlspecialchars(addslashes($r['protokol_adi'] !== null ? $r['protokol_adi'] : $r['vatandas_ad'] . ' ' . $r['vatandas_soyad'])) ?>')" title="QR Kodu Göster">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="3" y="3" width="7" height="7"></rect>
                                                <rect x="14" y="3" width="7" height="7"></rect>
                                                <rect x="3" y="14" width="7" height="7"></rect>
                                                <line x1="14" y1="14" x2="14" y2="21"></line>
                                                <line x1="21" y1="14" x2="21" y2="21"></line>
                                                <line x1="17.5" y1="14" x2="17.5" y2="17.5"></line>
                                                <line x1="14" y1="17.5" x2="21" y2="17.5"></line>
                                            </svg>
                                        </button>
                                        <button type="button" class="btn-sil" onclick="iptalOnayGoster(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['protokol_adi'] !== null ? $r['protokol_adi'] : $r['vatandas_ad'] . ' ' . $r['vatandas_soyad'])) ?>', '<?= htmlspecialchars(addslashes($r['etkinlik_ad'])) ?>')">İptal Et</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <dialog id="iptal-onay-modal" class="modal modal-kucuk modal-ortali">
        <div class="uyari-ikon">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                <line x1="12" y1="9" x2="12" y2="13"></line>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
        </div>
        <h2>Rezervasyonu İptal Et</h2>
        <p class="onay-mesaji" id="iptal-onay-mesaji"></p>
        <div class="modal-butonlar">
            <button type="button" class="btn-vazgec" onclick="document.getElementById('iptal-onay-modal').close()">Vazgeç</button>
            <a id="iptal-onay-link" href="#" class="btn-sil-onayla">Evet, İptal Et</a>
        </div>
    </dialog>

    <dialog id="qr-modal" class="modal modal-kucuk modal-ortali">
        <h2 id="qr-modal-baslik"></h2>
        <div id="qr-kod-alani" class="qr-kod-alani"></div>
        <p class="qr-referans-etiket">Referans Kodu</p>
        <p class="qr-referans-kodu" id="qr-referans-kodu"></p>
        <div class="modal-butonlar">
            <button type="button" class="btn-vazgec" onclick="document.getElementById('qr-modal').close()">Kapat</button>
        </div>
    </dialog>

    <script>
        function ozelListeAcKapa() {
            var liste = document.getElementById('etkinlik-liste');
            liste.style.display = (liste.style.display === 'block') ? 'none' : 'block';
        }

        function etkinlikSec(id) {
            var url = 'rezervasyonlar.php';
            if (id !== '') {
                url += '?etkinlik_id=' + id;
            }
            window.location.href = url;
        }

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.ozel-secici-sarmalayici')) {
                var liste = document.getElementById('etkinlik-liste');
                if (liste) liste.style.display = 'none';
            }
        });

        function iptalOnayGoster(id, vatandasAdi, etkinlikAdi) {
            document.getElementById('iptal-onay-mesaji').textContent = '"' + vatandasAdi + '" adlı kişinin "' + etkinlikAdi + '" etkinliğindeki rezervasyonunu iptal etmek istediğine emin misin? Koltuk otomatik olarak boşa çıkacak.';
            document.getElementById('iptal-onay-link').href = 'rezervasyonlar.php?iptal=' + id;
            document.getElementById('iptal-onay-modal').showModal();
        }

        function qrGoster(qrToken, kimIcin) {
            document.getElementById('qr-modal-baslik').textContent = kimIcin;
            document.getElementById('qr-referans-kodu').textContent = qrToken.substring(0, 12).toUpperCase();
            var qrAlani = document.getElementById('qr-kod-alani');
            qrAlani.innerHTML = '';
            new QRCode(qrAlani, {
                text: qrToken,
                width: 180,
                height: 180,
                colorDark: '#1F1F1F',
                colorLight: '#ffffff'
            });
            document.getElementById('qr-modal').showModal();
        }

        var kayitliKonum = sessionStorage.getItem('rez-scroll');
        if (kayitliKonum !== null) {
            window.scrollTo(0, parseInt(kayitliKonum, 10));
            sessionStorage.removeItem('rez-scroll');
        }

        document.querySelectorAll('.giris-kutu').forEach(function (kutu) {
            kutu.addEventListener('click', function () {
                sessionStorage.setItem('rez-scroll', window.scrollY);
            });
        });

        var basariToast = document.getElementById('basari-toast');
        if (basariToast) {
            setTimeout(function () {
                basariToast.classList.add('toast-kayboluyor');
                setTimeout(function () { basariToast.remove(); }, 400);
            }, 2500);
        }
    </script>

</body>
</html>