<?php
require '../includes/admin-auth-kontrol.php';
require '../includes/db.php';

$etkinlik_id = $_GET['etkinlik_id'] ?? '';

if (!ctype_digit((string)$etkinlik_id)) {
    header('Location: koltuklar.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, ad, yer, koltuk_sayisi, koridor_sonrasi, satir_basi_koltuk FROM etkinlikler WHERE id = :id AND deleted = 0");
$stmt->execute(['id' => $etkinlik_id]);
$etkinlik = $stmt->fetch();

if (!$etkinlik) {
    header('Location: koltuklar.php');
    exit;
}

// Koltuk olusturma islemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['koltuk_olustur'])) {
    $satir_basi_koltuk = (int)($_POST['satir_basi_koltuk'] ?? 10);
    if ($satir_basi_koltuk < 1) {
        $satir_basi_koltuk = 10;
    }
    $toplam = (int)$etkinlik['koltuk_sayisi'];
    $harfler = range('A', 'Z');
    $satir_index = 0;
    $koltuk_no_index = 1;

    $insert = $pdo->prepare("INSERT INTO koltuklar (etkinlik_id, koltuk_no, durum) VALUES (:etkinlik_id, :koltuk_no, 'bos')");

    for ($i = 1; $i <= $toplam; $i++) {
        $harf = $harfler[$satir_index] ?? 'Z';
        $koltuk_no = $harf . $koltuk_no_index;

        $insert->execute([
            'etkinlik_id' => $etkinlik_id,
            'koltuk_no' => $koltuk_no,
        ]);

        $koltuk_no_index++;
        if ($koltuk_no_index > $satir_basi_koltuk) {
            $koltuk_no_index = 1;
            $satir_index++;
        }
    }

    $satir_basi_guncelle = $pdo->prepare("UPDATE etkinlikler SET satir_basi_koltuk = :satir_basi WHERE id = :id");
    $satir_basi_guncelle->execute(['satir_basi' => $satir_basi_koltuk, 'id' => $etkinlik_id]);

    header('Location: koltuk-yonet.php?etkinlik_id=' . $etkinlik_id);
    exit;
}

// Secilen koltuklarin durumunu topluca degistir
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toplu_durum'])) {
    $yeni_durum = $_POST['toplu_durum'];
    $secili_idler = $_POST['secili_koltuklar'] ?? [];
    $gecerli_durumlar = ['bos', 'dolu', 'protokol'];

    if (in_array($yeni_durum, $gecerli_durumlar) && count($secili_idler) > 0) {
        $temiz_idler = array_filter($secili_idler, function ($v) {
            return ctype_digit((string)$v);
        });
        if (count($temiz_idler) > 0) {
            $yer_tutucular = implode(',', array_fill(0, count($temiz_idler), '?'));
            $params = array_merge([$yeni_durum], array_values($temiz_idler), [$etkinlik_id]);
            $sorgu = $pdo->prepare("UPDATE koltuklar SET durum = ? WHERE id IN ($yer_tutucular) AND etkinlik_id = ?");
            $sorgu->execute($params);
        }
    }

    header('Location: koltuk-yonet.php?etkinlik_id=' . $etkinlik_id);
    exit;
}

$stmt = $pdo->prepare("SELECT id, koltuk_no, durum FROM koltuklar WHERE etkinlik_id = :etkinlik_id AND deleted = 0");
$stmt->execute(['etkinlik_id' => $etkinlik_id]);
$koltuklar_ham = $stmt->fetchAll();

// Koltuklari satira gore grupla, her satir icinde numaraya gore dogru sirala
$satirlar = [];
foreach ($koltuklar_ham as $k) {
    preg_match('/^([A-Z]+)(\d+)$/', $k['koltuk_no'], $m);
    $harf = $m[1] ?? '?';
    $numara = isset($m[2]) ? (int)$m[2] : 0;
    $k['numara'] = $numara;
    $satirlar[$harf][] = $k;
}
ksort($satirlar);
foreach ($satirlar as $harf => &$satir_koltuklari) {
    usort($satir_koltuklari, function ($a, $b) {
        return $a['numara'] <=> $b['numara'];
    });
}
unset($satir_koltuklari);

$koltuklar_var_mi = count($koltuklar_ham) > 0;

$sayim = ['bos' => 0, 'dolu' => 0, 'protokol' => 0];
foreach ($koltuklar_ham as $k) {
    if (isset($sayim[$k['durum']])) {
        $sayim[$k['durum']]++;
    }
}
$toplam_koltuk = count($koltuklar_ham);
if (!empty($etkinlik['koridor_sonrasi']) || $etkinlik['koridor_sonrasi'] === '0') {
    $koridor_pozisyonlari = array_filter(array_map('intval', explode(',', $etkinlik['koridor_sonrasi'])), function ($v) {
        return $v >= 0;
    });
} elseif ($etkinlik['satir_basi_koltuk'] !== null && (int)$etkinlik['satir_basi_koltuk'] > 1) {
    $koridor_pozisyonlari = [(int)floor((int)$etkinlik['satir_basi_koltuk'] / 2)];
} else {
    $koridor_pozisyonlari = [5];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Admin Paneli - Koltukları Yönet</title>
    <link rel="stylesheet" href="../css/admin-layout.css">
    <link rel="stylesheet" href="../css/admin-koltuklar.css">
</head>
<body>

    <div class="admin-layout">
        <?php require '../includes/admin-sidebar.php'; ?>

        <div class="admin-main">
            <?php require '../includes/admin-topbar.php'; ?>

            <div class="admin-content">
                <a href="koltuklar.php" class="geri-link">← Etkinlik Listesine Dön</a>
                <div class="baslik-satiri">
                    <h1><?= htmlspecialchars($etkinlik['ad']) ?></h1>
                    <?php if ($koltuklar_var_mi): ?>
                        <div class="doluluk-kapsul">
                            <span class="doluluk-rozet doluluk-rozet-toplam"><?= $toplam_koltuk ?> Toplam</span>
                            <span class="doluluk-rozet doluluk-rozet-bos"><?= $sayim['bos'] ?> Boş</span>
                            <span class="doluluk-rozet doluluk-rozet-dolu"><?= $sayim['dolu'] ?> Dolu</span>
                            <span class="doluluk-rozet doluluk-rozet-protokol"><?= $sayim['protokol'] ?> Protokol</span>
                        </div>
                    <?php endif; ?>
                </div>
                <p class="etkinlik-alt-bilgi"><?= htmlspecialchars($etkinlik['yer']) ?></p>

                <?php if ($etkinlik['koltuk_sayisi'] === null): ?>
                    <div class="koltuk-olustur-kutu">
                        <p>Bu etkinliğin koltuk sayısı henüz belirtilmemiş. Koltuk oluşturabilmek için önce Etkinlikler sayfasından bu etkinliği düzenleyip koltuk sayısını girmelisin.</p>
                        <a href="etkinlikler.php" class="btn-kaydet" style="display:inline-block; text-decoration:none;">Etkinlikler Sayfasına Git</a>
                    </div>
                <?php elseif (!$koltuklar_var_mi): ?>
                    <div class="koltuk-olustur-kutu">
                        <p>Bu etkinlik için henüz koltuk oluşturulmadı.</p>
                        <form method="POST" action="koltuk-yonet.php?etkinlik_id=<?= $etkinlik_id ?>" class="koltuk-olustur-form">
                            <input type="hidden" name="koltuk_olustur" value="1">
                            <label for="satir-basi-koltuk">Bir sırada kaç koltuk olsun?</label>
                            <input type="number" id="satir-basi-koltuk" name="satir_basi_koltuk" min="1" value="10">
                            <button type="submit" class="btn-kaydet">Koltukları Oluştur</button>
                        </form>
                    </div>
                <?php else: ?>
                    <form method="POST" action="koltuk-yonet.php?etkinlik_id=<?= $etkinlik_id ?>" id="toplu-form">

                        <div class="ust-kartlar-satiri">
                            <div class="lejant-kart">
                                <span class="lejant-madde"><span class="lejant-renk lejant-bos"></span> Boş</span>
                                <span class="lejant-madde"><span class="lejant-renk lejant-dolu"></span> Dolu</span>
                                <span class="lejant-madde"><span class="lejant-renk lejant-protokol"></span> Protokol</span>
                            </div>

                            <div class="arac-kart">
                                <button type="button" class="btn-arac" onclick="tumunuSec()">Tümünü Seç</button>
                                <button type="button" class="btn-arac" onclick="secimiTemizle()">Temizle</button>
                                <span class="arac-ayrac"></span>
                                <input type="text" id="aralik-baslangic" class="aralik-input" placeholder="A3" autocomplete="off">
                                <span class="aralik-ok">→</span>
                                <input type="text" id="aralik-bitis" class="aralik-input" placeholder="B9" autocomplete="off">
                                <span class="arac-sag-yasla"></span>
                                <button type="submit" name="toplu_durum" value="bos" class="btn-arac btn-arac-bos">Boş Yap</button>
                                <button type="submit" name="toplu_durum" value="dolu" class="btn-arac btn-arac-dolu">Dolu Yap</button>
                                <button type="submit" name="toplu_durum" value="protokol" class="btn-arac btn-arac-protokol">Protokol Yap</button>
                            </div>
                        </div>

                        <div class="sinema-kart">
                        <div class="sinema-sahne">SAHNE / EKRAN</div>

                        <div class="sinema-govde">
                            <?php
                            $genel_sira = 0;
                            $satir_sayaci = 0;
                            $toplam_satir_sayisi = count($satirlar);
                            $orta_satir_index = (int)floor(($toplam_satir_sayisi - 1) / 2);
                            $mevcut_yukseklik = $toplam_satir_sayisi * 46;
                            $koridor_yazi_boyutu = max(7, min(16, (int)floor($mevcut_yukseklik / (7 * 1.3))));
                            $koridor_dikey_kaydirma = ($toplam_satir_sayisi % 2 === 0) ? 23 : 0;
                            foreach ($satirlar as $harf => $satir_koltuklari): ?>
                                <?php $bu_satir_ortada_mi = ($satir_sayaci === $orta_satir_index); ?>
                                <div class="sinema-satir">
                                    <span class="satir-etiket-duz"><?= htmlspecialchars($harf) ?></span>
                                    <div class="satir-koltuklar" data-satir="satir-<?= $harf ?>">
                                        <?php $satir_uzunlugu = count($satir_koltuklari); ?>
                                        <?php if (in_array(0, $koridor_pozisyonlari)): ?>
                                            <div class="koridor-bosluk <?= $bu_satir_ortada_mi ? 'koridor-bosluk-etiketli' : '' ?>"><?php if ($bu_satir_ortada_mi): ?><span style="font-size: <?= $koridor_yazi_boyutu ?>px; margin-top: <?= $koridor_dikey_kaydirma ?>px;">KORİDOR</span><?php endif; ?></div>
                                        <?php endif; ?>
                                        <?php foreach ($satir_koltuklari as $index => $koltuk): ?>
                                            <?php if ($index > 0 && in_array($index, $koridor_pozisyonlari)): ?>
                                                <div class="koridor-bosluk <?= $bu_satir_ortada_mi ? 'koridor-bosluk-etiketli' : '' ?>"><?php if ($bu_satir_ortada_mi): ?><span style="font-size: <?= $koridor_yazi_boyutu ?>px; margin-top: <?= $koridor_dikey_kaydirma ?>px;">KORİDOR</span><?php endif; ?></div>
                                            <?php endif; ?>
                                            <label class="koltuk-kutu koltuk-<?= $koltuk['durum'] ?>" title="<?= htmlspecialchars($koltuk['koltuk_no']) ?>">
                                                <input type="checkbox" name="secili_koltuklar[]" value="<?= $koltuk['id'] ?>" class="koltuk-checkbox satir-<?= $harf ?>" data-sira="<?= $genel_sira++ ?>" data-harf="<?= $harf ?>" data-kod="<?= htmlspecialchars($koltuk['koltuk_no']) ?>">
                                                <span><?= $koltuk['numara'] ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                        <?php if (in_array($satir_uzunlugu, $koridor_pozisyonlari)): ?>
                                            <div class="koridor-bosluk <?= $bu_satir_ortada_mi ? 'koridor-bosluk-etiketli' : '' ?>"><?php if ($bu_satir_ortada_mi): ?><span style="font-size: <?= $koridor_yazi_boyutu ?>px; margin-top: <?= $koridor_dikey_kaydirma ?>px;">KORİDOR</span><?php endif; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="satir-etiket-duz"><?= htmlspecialchars($harf) ?></span>
                                </div>
                                <?php $satir_sayaci++; ?>
                            <?php endforeach; ?>
                        </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function tumunuSec() {
            document.querySelectorAll('.koltuk-checkbox').forEach(function (kutu) {
                kutu.checked = true;
            });
        }

        function secimiTemizle() {
            document.querySelectorAll('.koltuk-checkbox').forEach(function (kutu) {
                kutu.checked = false;
            });
            document.getElementById('aralik-baslangic').value = '';
            document.getElementById('aralik-bitis').value = '';
        }

        function araligiSec() {
            var baslangicKod = document.getElementById('aralik-baslangic').value.trim().toUpperCase();
            var bitisKod = document.getElementById('aralik-bitis').value.trim().toUpperCase();

            if (baslangicKod === '' || bitisKod === '') {
                return;
            }

            var baslangicKutu = document.querySelector('.koltuk-checkbox[data-kod="' + baslangicKod + '"]');
            var bitisKutu = document.querySelector('.koltuk-checkbox[data-kod="' + bitisKod + '"]');

            if (!baslangicKutu || !bitisKutu) {
                return;
            }

            var baslaSira = parseInt(baslangicKutu.dataset.sira, 10);
            var bitisSira = parseInt(bitisKutu.dataset.sira, 10);
            var alt = Math.min(baslaSira, bitisSira);
            var ust = Math.max(baslaSira, bitisSira);

            document.querySelectorAll('.koltuk-checkbox').forEach(function (kutu) {
                var sira = parseInt(kutu.dataset.sira, 10);
                kutu.checked = (sira >= alt && sira <= ust);
            });
        }

        ['aralik-baslangic', 'aralik-bitis'].forEach(function (id) {
            document.getElementById(id).addEventListener('input', araligiSec);
        });

        window.addEventListener('pageshow', function () {
            var b1 = document.getElementById('aralik-baslangic');
            var b2 = document.getElementById('aralik-bitis');
            if (b1) b1.value = '';
            if (b2) b2.value = '';

            var kayitliKonum = sessionStorage.getItem('koltuk-scroll-' + <?= (int)$etkinlik_id ?>);
            if (kayitliKonum !== null) {
                window.scrollTo(0, parseInt(kayitliKonum, 10));
                sessionStorage.removeItem('koltuk-scroll-' + <?= (int)$etkinlik_id ?>);
            }
        });

        var topluForm = document.getElementById('toplu-form');
        if (topluForm) {
            topluForm.addEventListener('submit', function () {
                sessionStorage.setItem('koltuk-scroll-' + <?= (int)$etkinlik_id ?>, window.scrollY);
            });
        }

        document.getElementById('aralik-baslangic').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('aralik-bitis').focus();
            }
        });

        document.getElementById('aralik-bitis').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });
    </script>

</body>
</html>