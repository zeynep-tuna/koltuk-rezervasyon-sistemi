<?php
require '../includes/admin-auth-kontrol.php';
require '../includes/db.php';

$admin_id = $_SESSION['admin_id'] ?? 0;

function yonlendirme_url($ay, $yil, $secili) {
    return 'takvim.php?' . http_build_query(['ay' => $ay, 'yil' => $yil, 'secili' => $secili]);
}

// Yeni not ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['not_ekle'])) {
    $not_metni = trim($_POST['not_metni'] ?? '');
    $not_ay = (int)($_POST['not_ay'] ?? date('n'));
    $not_yil = (int)($_POST['not_yil'] ?? date('Y'));
    $not_gun = (int)($_POST['not_gun'] ?? date('j'));
    $not_tarih = sprintf('%04d-%02d-%02d', $not_yil, $not_ay, $not_gun);

    if ($not_metni !== '') {
        $ekle = $pdo->prepare("INSERT INTO admin_notlar (admin_id, tarih, not_metni) VALUES (:admin_id, :tarih, :not_metni)");
        $ekle->execute(['admin_id' => $admin_id, 'tarih' => $not_tarih, 'not_metni' => $not_metni]);
    }

    header('Location: ' . yonlendirme_url($not_ay, $not_yil, $not_gun) . '&not_kaydedildi=1');
    exit;
}

// Not metnini guncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['not_guncelle'])) {
    $not_id = (int)($_POST['not_id'] ?? 0);
    $not_metni = trim($_POST['not_metni'] ?? '');
    $not_ay = (int)($_POST['not_ay'] ?? date('n'));
    $not_yil = (int)($_POST['not_yil'] ?? date('Y'));
    $not_gun = (int)($_POST['not_gun'] ?? date('j'));

    if ($not_metni !== '') {
        $guncelle = $pdo->prepare("UPDATE admin_notlar SET not_metni = :not_metni, tamamlandi = 0 WHERE id = :id AND admin_id = :admin_id");
        $guncelle->execute(['not_metni' => $not_metni, 'id' => $not_id, 'admin_id' => $admin_id]);
    }

    header('Location: ' . yonlendirme_url($not_ay, $not_yil, $not_gun) . '&not_guncellendi=1');
    exit;
}

// Tamamlandi isaretini degistir
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['not_tamamla'])) {
    $not_id = (int)($_POST['not_id'] ?? 0);
    $not_ay = (int)($_POST['not_ay'] ?? date('n'));
    $not_yil = (int)($_POST['not_yil'] ?? date('Y'));
    $not_gun = (int)($_POST['not_gun'] ?? date('j'));

    $guncelle = $pdo->prepare("UPDATE admin_notlar SET tamamlandi = NOT tamamlandi WHERE id = :id AND admin_id = :admin_id");
    $guncelle->execute(['id' => $not_id, 'admin_id' => $admin_id]);

    header('Location: ' . yonlendirme_url($not_ay, $not_yil, $not_gun));
    exit;
}

// Not silme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['not_sil'])) {
    $not_id = (int)($_POST['not_id'] ?? 0);
    $not_ay = (int)($_POST['not_ay'] ?? date('n'));
    $not_yil = (int)($_POST['not_yil'] ?? date('Y'));
    $not_gun = (int)($_POST['not_gun'] ?? date('j'));

    $sil = $pdo->prepare("UPDATE admin_notlar SET deleted = 1 WHERE id = :id AND admin_id = :admin_id");
    $sil->execute(['id' => $not_id, 'admin_id' => $admin_id]);

    header('Location: ' . yonlendirme_url($not_ay, $not_yil, $not_gun));
    exit;
}

$bu_ay = (int)date('n');
$bu_yil = (int)date('Y');

$ay = isset($_GET['ay']) && ctype_digit((string)$_GET['ay']) ? (int)$_GET['ay'] : $bu_ay;
$yil = isset($_GET['yil']) && ctype_digit((string)$_GET['yil']) ? (int)$_GET['yil'] : $bu_yil;

if ($ay < 1) { $ay = 12; $yil--; }
if ($ay > 12) { $ay = 1; $yil++; }

$aylar = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
$gun_isimleri = ['Pt', 'Sa', 'Ça', 'Pe', 'Cu', 'Ct', 'Pa'];

$ay_ilk_gun = "$yil-" . str_pad($ay, 2, '0', STR_PAD_LEFT) . "-01";
$ayin_gun_sayisi = (int)date('t', strtotime($ay_ilk_gun));
$ilk_gun_haftanin_kaci = (int)date('N', strtotime($ay_ilk_gun));

$onceki_ay = $ay - 1;
$onceki_yil = $yil;
if ($onceki_ay < 1) { $onceki_ay = 12; $onceki_yil--; }
$onceki_ay_gun_sayisi = (int)date('t', strtotime("$onceki_yil-" . str_pad($onceki_ay, 2, '0', STR_PAD_LEFT) . "-01"));

$sonraki_ay = $ay + 1;
$sonraki_yil = $yil;
if ($sonraki_ay > 12) { $sonraki_ay = 1; $sonraki_yil++; }

$ay_baslangic = "$yil-" . str_pad($ay, 2, '0', STR_PAD_LEFT) . "-01";
$ay_bitis = "$yil-" . str_pad($ay, 2, '0', STR_PAD_LEFT) . "-" . str_pad($ayin_gun_sayisi, 2, '0', STR_PAD_LEFT);

$stmt = $pdo->prepare("SELECT id, ad, etkinlik_tarih, etkinlik_saat, aktif_mi FROM etkinlikler WHERE deleted = 0 AND etkinlik_tarih BETWEEN :baslangic AND :bitis ORDER BY etkinlik_saat ASC");
$stmt->execute(['baslangic' => $ay_baslangic, 'bitis' => $ay_bitis]);
$etkinlikler_ham = $stmt->fetchAll();

$gune_gore_etkinlikler = [];
foreach ($etkinlikler_ham as $e) {
    $gun_no = (int)date('j', strtotime($e['etkinlik_tarih']));
    $gune_gore_etkinlikler[$gun_no][] = $e;
}

$not_stmt = $pdo->prepare("SELECT id, tarih, not_metni, tamamlandi FROM admin_notlar WHERE admin_id = :admin_id AND deleted = 0 AND tarih BETWEEN :baslangic AND :bitis ORDER BY id ASC");
$not_stmt->execute(['admin_id' => $admin_id, 'baslangic' => $ay_baslangic, 'bitis' => $ay_bitis]);
$gune_gore_notlar = [];
foreach ($not_stmt->fetchAll() as $n) {
    $gun_no = (int)date('j', strtotime($n['tarih']));
    $gune_gore_notlar[$gun_no][] = $n;
}

$bugun_gun = ((int)date('n') === $ay && (int)date('Y') === $yil) ? (int)date('j') : 0;

$secili_gun = isset($_GET['secili']) && ctype_digit((string)$_GET['secili']) ? (int)$_GET['secili'] : $bugun_gun;
if ($secili_gun < 1 || $secili_gun > $ayin_gun_sayisi) {
    $secili_gun = 0;
}
$secili_gun_haftanin_kaci = $secili_gun > 0 ? (int)date('N', strtotime("$yil-" . str_pad($ay, 2, '0', STR_PAD_LEFT) . "-" . str_pad($secili_gun, 2, '0', STR_PAD_LEFT))) : 0;
$gun_isimleri_uzun = ['Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi', 'Pazar'];
$secili_gun_notlari = $secili_gun > 0 ? ($gune_gore_notlar[$secili_gun] ?? []) : [];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Admin Paneli - Takvim</title>
    <link rel="stylesheet" href="../css/admin-layout.css">
    <link rel="stylesheet" href="../css/admin-takvim.css">
</head>
<body>

    <div class="admin-layout">
        <?php require '../includes/admin-sidebar.php'; ?>

        <div class="admin-main">
            <?php require '../includes/admin-topbar.php'; ?>

            <div class="admin-content">
                <h1>Takvim</h1>

                <?php if (isset($_GET['not_kaydedildi']) || isset($_GET['not_guncellendi'])): ?>
                    <div class="toast-bildirim" id="not-toast">Not kaydedildi.</div>
                <?php endif; ?>

                <div class="takvim-sayfa-duzeni">
                <div class="takvim-kart">
                    <div class="takvim-baslik-satiri">
                        <div class="ay-yil-secici">
                            <div class="ozel-secici-sarmalayici">
                                <button type="button" class="ozel-secici-buton" onclick="ozelListeAcKapa('ay-liste')"><?= $aylar[$ay - 1] ?></button>
                                <div class="ozel-secici-liste" id="ay-liste">
                                    <?php foreach ($aylar as $index => $ay_adi): ?>
                                        <div class="ozel-secici-madde <?= ($index + 1) === $ay ? 'ozel-secici-madde-secili' : '' ?>" onclick="ayYilSec(<?= $index + 1 ?>, <?= $yil ?>)"><?= $ay_adi ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="ozel-secici-sarmalayici">
                                <button type="button" class="ozel-secici-buton" onclick="ozelListeAcKapa('yil-liste')"><?= $yil ?></button>
                                <div class="ozel-secici-liste" id="yil-liste">
                                    <?php for ($y = 2025; $y <= $bu_yil + 5; $y++): ?>
                                        <div class="ozel-secici-madde <?= $y === $yil ? 'ozel-secici-madde-secili' : '' ?>" onclick="ayYilSec(<?= $ay ?>, <?= $y ?>)"><?= $y ?></div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                        <div class="takvim-gezinme">
                            <a href="takvim.php?ay=<?= $onceki_ay ?>&yil=<?= $onceki_yil ?>" class="takvim-ok">‹</a>
                            <a href="takvim.php?ay=<?= $sonraki_ay ?>&yil=<?= $sonraki_yil ?>" class="takvim-ok">›</a>
                        </div>
                    </div>

                    <div class="takvim-hafta-satiri">
                        <?php foreach ($gun_isimleri as $gun_ismi): ?>
                            <div class="takvim-hafta-gunu"><?= $gun_ismi ?></div>
                        <?php endforeach; ?>
                    </div>

                    <div class="takvim-izgara">
                        <?php
                        for ($i = $ilk_gun_haftanin_kaci - 1; $i > 0; $i--):
                            $gosterilecek = $onceki_ay_gun_sayisi - $i + 1;
                        ?>
                            <div class="takvim-gun takvim-gun-soluk">
                                <span class="takvim-gun-no"><?= $gosterilecek ?></span>
                            </div>
                        <?php endfor; ?>

                        <?php for ($gun = 1; $gun <= $ayin_gun_sayisi; $gun++): ?>
                            <a href="takvim.php?ay=<?= $ay ?>&yil=<?= $yil ?>&secili=<?= $gun ?>" class="takvim-gun <?= (isset($gune_gore_etkinlikler[$gun]) || isset($gune_gore_notlar[$gun])) ? 'takvim-gun-etkinlikli' : '' ?>">
                                <span class="takvim-gun-no <?= $gun === $bugun_gun ? 'takvim-gun-bugun' : '' ?> <?= $gun === $secili_gun && $gun !== $bugun_gun ? 'takvim-gun-secili' : '' ?>"><?= $gun ?></span>
                                <?php if (isset($gune_gore_etkinlikler[$gun]) || isset($gune_gore_notlar[$gun])): ?>
                                    <span class="takvim-nokta-grubu">
                                        <?php if (isset($gune_gore_etkinlikler[$gun])): ?>
                                            <?php foreach (array_slice($gune_gore_etkinlikler[$gun], 0, 3) as $e): ?>
                                                <span class="takvim-nokta <?= $e['aktif_mi'] ? 'takvim-nokta-aktif' : 'takvim-nokta-gizli' ?>"></span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        <?php if (isset($gune_gore_notlar[$gun])): ?>
                                            <span class="takvim-nokta takvim-nokta-not"></span>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>

                                <?php if (isset($gune_gore_etkinlikler[$gun]) || isset($gune_gore_notlar[$gun])): ?>
                                    <div class="takvim-hover-onizleme">
                                        <div class="onizleme-tarih"><?= $gun ?> <?= $aylar[$ay - 1] ?></div>
                                        <?php if (isset($gune_gore_etkinlikler[$gun])): ?>
                                            <?php foreach ($gune_gore_etkinlikler[$gun] as $e): ?>
                                                <div class="onizleme-satir">
                                                    <span class="onizleme-nokta <?= $e['aktif_mi'] ? 'takvim-nokta-aktif' : 'takvim-nokta-gizli' ?>"></span>
                                                    <?= $e['etkinlik_saat'] ? substr($e['etkinlik_saat'], 0, 5) . ' — ' : '' ?><?= htmlspecialchars($e['ad']) ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        <?php if (isset($gune_gore_notlar[$gun])): ?>
                                            <?php foreach ($gune_gore_notlar[$gun] as $n): ?>
                                                <div class="onizleme-satir <?= $n['tamamlandi'] ? 'onizleme-tamamlandi' : '' ?>">
                                                    <span class="onizleme-nokta takvim-nokta-not"></span>
                                                    <?= htmlspecialchars(mb_strimwidth($n['not_metni'], 0, 40, '…')) ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </a>
                        <?php endfor; ?>

                        <?php
                        $toplam_hucre = ($ilk_gun_haftanin_kaci - 1) + $ayin_gun_sayisi;
                        $kalan = (7 - ($toplam_hucre % 7)) % 7;
                        for ($gun = 1; $gun <= $kalan; $gun++):
                        ?>
                            <div class="takvim-gun takvim-gun-soluk">
                                <span class="takvim-gun-no"><?= $gun ?></span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="yan-sutun">
                <div class="not-kart">
                    <?php if ($secili_gun > 0): ?>
                        <div class="not-kart-tarih"><?= $secili_gun ?> <?= $aylar[$ay - 1] ?> <?= $gun_isimleri_uzun[$secili_gun_haftanin_kaci - 1] ?></div>

                        <?php if (isset($gune_gore_etkinlikler[$secili_gun])): ?>
                            <?php foreach ($gune_gore_etkinlikler[$secili_gun] as $e): ?>
                                <a href="etkinlikler.php" class="alt-panel-satir">
                                    <span class="onizleme-nokta <?= $e['aktif_mi'] ? 'takvim-nokta-aktif' : 'takvim-nokta-gizli' ?>"></span>
                                    <?= $e['etkinlik_saat'] ? substr($e['etkinlik_saat'], 0, 5) . ' — ' : '' ?><?= htmlspecialchars($e['ad']) ?>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="alt-panel-bos">Bu günde etkinlik yok.</p>
                        <?php endif; ?>

                        <?php if (count($secili_gun_notlari) > 0): ?>
                            <div class="notun-baslik">Notların</div>
                            <div class="not-listesi">
                                <?php foreach ($secili_gun_notlari as $n): ?>
                                    <div class="not-satiri">
                                        <form method="POST" action="takvim.php" class="not-mini-form">
                                            <input type="hidden" name="not_id" value="<?= $n['id'] ?>">
                                            <input type="hidden" name="not_ay" value="<?= $ay ?>">
                                            <input type="hidden" name="not_yil" value="<?= $yil ?>">
                                            <input type="hidden" name="not_gun" value="<?= $secili_gun ?>">
                                            <button type="submit" name="not_tamamla" value="1" class="not-tamamla-kutu <?= $n['tamamlandi'] ? 'not-tamamla-kutu-isaretli' : '' ?>" title="Tamamlandı olarak işaretle"></button>
                                        </form>

                                        <span class="not-metni-goster <?= $n['tamamlandi'] ? 'not-tamamlandi' : '' ?>" onclick="notDuzenleAc(<?= $n['id'] ?>)"><?= nl2br(htmlspecialchars($n['not_metni'])) ?></span>

                                        <span class="not-eylem-simgeleri">
                                            <button type="button" class="not-eylem-btn" onclick="notDuzenleAc(<?= $n['id'] ?>)" title="Düzenle">✎</button>
                                            <form method="POST" action="takvim.php" class="not-mini-form">
                                                <input type="hidden" name="not_id" value="<?= $n['id'] ?>">
                                                <input type="hidden" name="not_ay" value="<?= $ay ?>">
                                                <input type="hidden" name="not_yil" value="<?= $yil ?>">
                                                <input type="hidden" name="not_gun" value="<?= $secili_gun ?>">
                                                <button type="submit" name="not_sil" value="1" class="not-eylem-btn" title="Sil">✕</button>
                                            </form>
                                        </span>

                                        <form method="POST" action="takvim.php" class="not-duzenle-formu" id="not-duzenle-<?= $n['id'] ?>">
                                            <textarea name="not_metni" rows="3"><?= htmlspecialchars($n['not_metni']) ?></textarea>
                                            <input type="hidden" name="not_id" value="<?= $n['id'] ?>">
                                            <input type="hidden" name="not_ay" value="<?= $ay ?>">
                                            <input type="hidden" name="not_yil" value="<?= $yil ?>">
                                            <input type="hidden" name="not_gun" value="<?= $secili_gun ?>">
                                            <input type="hidden" name="not_guncelle" value="1">
                                            <div class="not-duzenle-butonlari">
                                                <button type="button" class="btn-not-vazgec" onclick="notDuzenleKapat(<?= $n['id'] ?>)">Vazgeç</button>
                                                <button type="submit" class="btn-not-kaydet-kucuk">Kaydet</button>
                                            </div>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php
                        $ay_gunleri_dolu = array_unique(array_merge(array_keys($gune_gore_etkinlikler), array_keys($gune_gore_notlar)));
                        sort($ay_gunleri_dolu);
                        ?>
                        <?php if (count($ay_gunleri_dolu) === 0): ?>
                            <p class="alt-panel-bos">Bu ayda hiç etkinlik veya not yok.</p>
                        <?php else: ?>
                            <div class="not-kart-tarih">Bu Ayın Tamamı</div>
                            <?php foreach ($ay_gunleri_dolu as $gun_no): ?>
                                <?php $gun_haftanin_kaci = (int)date('N', strtotime("$yil-" . str_pad($ay, 2, '0', STR_PAD_LEFT) . "-" . str_pad($gun_no, 2, '0', STR_PAD_LEFT))); ?>
                                <a href="takvim.php?ay=<?= $ay ?>&yil=<?= $yil ?>&secili=<?= $gun_no ?>" class="ay-liste-gun-baslik"><?= $gun_no ?> <?= $aylar[$ay - 1] ?> <?= $gun_isimleri_uzun[$gun_haftanin_kaci - 1] ?></a>
                                <?php if (isset($gune_gore_etkinlikler[$gun_no])): ?>
                                    <?php foreach ($gune_gore_etkinlikler[$gun_no] as $e): ?>
                                        <div class="alt-panel-satir">
                                            <span class="onizleme-nokta <?= $e['aktif_mi'] ? 'takvim-nokta-aktif' : 'takvim-nokta-gizli' ?>"></span>
                                            <?= $e['etkinlik_saat'] ? substr($e['etkinlik_saat'], 0, 5) . ' — ' : '' ?><?= htmlspecialchars($e['ad']) ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <?php if (isset($gune_gore_notlar[$gun_no])): ?>
                                    <?php foreach ($gune_gore_notlar[$gun_no] as $n): ?>
                                        <div class="alt-panel-satir <?= $n['tamamlandi'] ? 'onizleme-tamamlandi' : '' ?>">
                                            <span class="onizleme-nokta takvim-nokta-not"></span>
                                            <?= nl2br(htmlspecialchars($n['not_metni'])) ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="not-yaz-kart">
                    <div class="not-yaz-baslik">Not Ekle</div>
                    <form method="POST" action="takvim.php" class="not-formu">
                        <?php if ($secili_gun === 0): ?>
                            <label for="not-gun-secici" class="not-gun-etiket">Gün</label>
                            <select id="not-gun-secici" name="not_gun" class="not-gun-secici">
                                <?php for ($g = 1; $g <= $ayin_gun_sayisi; $g++): ?>
                                    <option value="<?= $g ?>" <?= $g === (int)date('j') && $ay === $bu_ay && $yil === $bu_yil ? 'selected' : '' ?>><?= $g ?> <?= $aylar[$ay - 1] ?></option>
                                <?php endfor; ?>
                            </select>
                        <?php else: ?>
                            <input type="hidden" name="not_gun" value="<?= $secili_gun ?>">
                        <?php endif; ?>
                        <textarea id="not-metni" name="not_metni" rows="3" placeholder="Bir not yaz..."></textarea>
                        <input type="hidden" name="not_ay" value="<?= $ay ?>">
                        <input type="hidden" name="not_yil" value="<?= $yil ?>">
                        <input type="hidden" name="not_ekle" value="1">
                        <button type="submit" class="btn-not-kaydet">Ekle</button>
                    </form>
                </div>
                </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function ozelListeAcKapa(id) {
            document.querySelectorAll('.ozel-secici-liste').forEach(function (el) {
                if (el.id !== id) el.style.display = 'none';
            });
            var liste = document.getElementById(id);
            liste.style.display = (liste.style.display === 'block') ? 'none' : 'block';
        }

        function ayYilSec(ay, yil) {
            window.location.href = 'takvim.php?ay=' + ay + '&yil=' + yil;
        }

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.ozel-secici-sarmalayici')) {
                document.querySelectorAll('.ozel-secici-liste').forEach(function (el) {
                    el.style.display = 'none';
                });
            }
        });

        function notDuzenleAc(id) {
            document.getElementById('not-duzenle-' + id).classList.add('not-duzenle-formu-acik');
        }
        function notDuzenleKapat(id) {
            document.getElementById('not-duzenle-' + id).classList.remove('not-duzenle-formu-acik');
        }

        var notToast = document.getElementById('not-toast');
        if (notToast) {
            setTimeout(function () {
                notToast.classList.add('toast-kayboluyor');
                setTimeout(function () { notToast.remove(); }, 400);
            }, 2200);
        }
    </script>

</body>
</html>