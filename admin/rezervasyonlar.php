<?php
require '../includes/admin-auth-kontrol.php';
require '../includes/db.php';

$basari = '';
$hata = '';
$ekle_modal_ac = false;

$vatandas_hata = false;
$etkinlik_hata = false;
$koltuk_hata = false;

// Yeni rezervasyon ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rez_ekle'])) {
    $vatandas_id = $_POST['vatandas_id'] ?? '';
    $etkinlik_id_ekle = $_POST['etkinlik_id_ekle'] ?? '';
    $koltuk_id_ekle = $_POST['koltuk_id_ekle'] ?? '';

    $vatandas_hata = !ctype_digit((string)$vatandas_id);
    $etkinlik_hata = !ctype_digit((string)$etkinlik_id_ekle);
    $koltuk_hata = !ctype_digit((string)$koltuk_id_ekle);

    if ($vatandas_hata || $etkinlik_hata || $koltuk_hata) {
        $_SESSION['rez_form_hata'] = [
            'hata' => 'Lütfen kırmızı ile işaretlenen alanları doldur.',
            'vatandas_hata' => $vatandas_hata,
            'etkinlik_hata' => $etkinlik_hata,
            'koltuk_hata' => $koltuk_hata,
        ];
        header('Location: rezervasyonlar.php?form_hata=1');
        exit;
    } else {
        // Koltuk hala bos mu diye son bir kontrol (baskasi ayni anda almis olabilir)
        $kontrol = $pdo->prepare("SELECT durum FROM koltuklar WHERE id = :id AND etkinlik_id = :etkinlik_id AND deleted = 0");
        $kontrol->execute(['id' => $koltuk_id_ekle, 'etkinlik_id' => $etkinlik_id_ekle]);
        $koltuk_satiri = $kontrol->fetch();

        if (!$koltuk_satiri || $koltuk_satiri['durum'] !== 'bos') {
            $_SESSION['rez_form_hata'] = [
                'hata' => 'Seçtiğin koltuk artık boş değil, lütfen başka bir koltuk seç.',
                'vatandas_hata' => false,
                'etkinlik_hata' => false,
                'koltuk_hata' => true,
            ];
            header('Location: rezervasyonlar.php?form_hata=1');
            exit;
        } else {
            $yeni_qr_token = bin2hex(random_bytes(16));
            $ekle = $pdo->prepare("INSERT INTO rezervasyonlar (vatandas_id, etkinlik_id, koltuk_id, qr_token) VALUES (:vatandas_id, :etkinlik_id, :koltuk_id, :qr_token)");
            $ekle->execute([
                'vatandas_id' => $vatandas_id,
                'etkinlik_id' => $etkinlik_id_ekle,
                'koltuk_id' => $koltuk_id_ekle,
                'qr_token' => $yeni_qr_token,
            ]);

            $koltuk_guncelle = $pdo->prepare("UPDATE koltuklar SET durum = 'dolu' WHERE id = :id");
            $koltuk_guncelle->execute(['id' => $koltuk_id_ekle]);

            $vatandas_bilgi = $pdo->prepare("SELECT ad, soyad FROM vatandaslar WHERE id = :id");
            $vatandas_bilgi->execute(['id' => $vatandas_id]);
            $v_satiri = $vatandas_bilgi->fetch();
            $yeni_isim = $v_satiri ? $v_satiri['ad'] . ' ' . $v_satiri['soyad'] : '';

            header('Location: rezervasyonlar.php?' . http_build_query([
                'rez_eklendi' => 1,
                'yeni_qr' => $yeni_qr_token,
                'yeni_isim' => $yeni_isim,
            ]));
            exit;
        }
    }
}

if (isset($_GET['rez_eklendi'])) {
    $basari = 'Rezervasyon eklendi.';
}

if (isset($_GET['form_hata']) && isset($_SESSION['rez_form_hata'])) {
    $flash = $_SESSION['rez_form_hata'];
    unset($_SESSION['rez_form_hata']);
    $hata = $flash['hata'];
    $vatandas_hata = $flash['vatandas_hata'];
    $etkinlik_hata = $flash['etkinlik_hata'];
    $koltuk_hata = $flash['koltuk_hata'];
    $ekle_modal_ac = true;
}

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
            'tur' => $_GET['tur'] ?? null,
            'q' => $_GET['q'] ?? null,
        ]));
        header('Location: ' . $geri_don);
        exit;
    }
}

$arama = trim($_GET['q'] ?? '');
$etkinlik_filtre = $_GET['etkinlik_id'] ?? '';
$giris_filtre = $_GET['giris'] ?? 'tumu';
$tur_filtre = $_GET['tur'] ?? 'tumu';

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
if ($tur_filtre === 'vatandas') {
    $sql .= " AND r.protokol_adi IS NULL";
} elseif ($tur_filtre === 'protokol') {
    $sql .= " AND r.protokol_adi IS NOT NULL";
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
        'tur' => $_GET['tur'] ?? null,
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
                    <div>
                        <h1>Rezervasyonlar</h1>
                        <button type="button" class="btn-ekle" onclick="ekleFormunuAcSifirla()">+ Yeni Rezervasyon Ekle</button>
                    </div>
                    <div class="rez-filtre-grubu">
                        <div class="ozel-secici-sarmalayici">
                            <button type="button" class="ozel-secici-buton btn-kucuk-filtre" onclick="filtreAcKapa('etkinlik-panel')"><?= $etkinlik_filtre !== '' ? htmlspecialchars($secili_etkinlik_adi) : 'Tüm Etkinlikler' ?></button>
                            <div class="ozel-secici-liste filtre-paneli" id="etkinlik-panel">
                                <div class="ozel-secici-madde <?= $etkinlik_filtre === '' ? 'ozel-secici-madde-secili' : '' ?>" onclick="filtreDegistir('etkinlik_id', '')">Tüm Etkinlikler</div>
                                <?php foreach ($etkinlikler_listesi as $e): ?>
                                    <div class="ozel-secici-madde <?= (string)$etkinlik_filtre === (string)$e['id'] ? 'ozel-secici-madde-secili' : '' ?>" onclick="filtreDegistir('etkinlik_id', <?= $e['id'] ?>)"><?= htmlspecialchars($e['ad']) ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <?php
                        $giris_etiketleri = ['tumu' => 'Tümü', 'yapti' => 'Giriş Yaptı', 'bekliyor' => 'Bekliyor'];
                        $tur_etiketleri = ['tumu' => 'Tümü', 'vatandas' => 'Vatandaş', 'protokol' => 'Protokol'];
                        ?>

                        <div class="ozel-secici-sarmalayici">
                            <button type="button" class="ozel-secici-buton btn-kucuk-filtre" onclick="filtreAcKapa('giris-panel')">Giriş: <?= $giris_etiketleri[$giris_filtre] ?></button>
                            <div class="ozel-secici-liste filtre-paneli" id="giris-panel">
                                <div class="ozel-secici-madde <?= $giris_filtre === 'tumu' ? 'ozel-secici-madde-secili' : '' ?>" onclick="filtreDegistir('giris', '')">Tümü</div>
                                <div class="ozel-secici-madde <?= $giris_filtre === 'yapti' ? 'ozel-secici-madde-secili' : '' ?>" onclick="filtreDegistir('giris', 'yapti')">Giriş Yaptı</div>
                                <div class="ozel-secici-madde <?= $giris_filtre === 'bekliyor' ? 'ozel-secici-madde-secili' : '' ?>" onclick="filtreDegistir('giris', 'bekliyor')">Bekliyor</div>
                            </div>
                        </div>

                        <div class="ozel-secici-sarmalayici">
                            <button type="button" class="ozel-secici-buton btn-kucuk-filtre" onclick="filtreAcKapa('tur-panel')">Tür: <?= $tur_etiketleri[$tur_filtre] ?></button>
                            <div class="ozel-secici-liste filtre-paneli" id="tur-panel">
                                <div class="ozel-secici-madde <?= $tur_filtre === 'tumu' ? 'ozel-secici-madde-secili' : '' ?>" onclick="filtreDegistir('tur', '')">Tümü</div>
                                <div class="ozel-secici-madde <?= $tur_filtre === 'vatandas' ? 'ozel-secici-madde-secili' : '' ?>" onclick="filtreDegistir('tur', 'vatandas')">Vatandaş</div>
                                <div class="ozel-secici-madde <?= $tur_filtre === 'protokol' ? 'ozel-secici-madde-secili' : '' ?>" onclick="filtreDegistir('tur', 'protokol')">Protokol</div>
                            </div>
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
                            <th>Ad / Grup</th>
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
                                            <?= htmlspecialchars($r['vatandas_ad'] . ' ' . $r['vatandas_soyad']) ?> <span class="vatandas-etiketi">Vatandaş</span>
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
                                    <td>
                                        <div class="islemler">
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
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <dialog id="ekle-rez-modal" class="modal modal-kucuk">
        <h2>Yeni Rezervasyon Ekle</h2>

        <?php if ($hata !== '' && $ekle_modal_ac): ?>
            <p class="hata-mesaj-genel"><?= htmlspecialchars($hata) ?></p>
        <?php endif; ?>

        <form method="POST" action="rezervasyonlar.php" id="ekle-rez-form">
            <label for="tc-arama-kutusu">Vatandaş (TC ile ara) <span class="zorunlu-yildiz">*</span></label>
            <input type="text" id="tc-arama-kutusu" class="<?= $vatandas_hata ? 'input-hata' : '' ?>" placeholder="TC kimlik numarası yaz..." maxlength="11" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
            <div class="tc-sonuc-listesi" id="tc-sonuc-listesi"></div>
            <div class="secili-vatandas" id="secili-vatandas-goster" style="display:none">
                <span id="secili-vatandas-metni"></span>
                <span class="secili-vatandas-kaldir" onclick="vatandasKaldir()">✕</span>
            </div>
            <input type="hidden" name="vatandas_id" id="vatandas-id-gizli">
            <?php if ($vatandas_hata): ?>
                <p class="hata-mesaj-inline">Bir vatandaş seçmelisin.</p>
            <?php endif; ?>

            <label for="etkinlik-secimi">Etkinlik <span class="zorunlu-yildiz">*</span></label>
            <select id="etkinlik-secimi" name="etkinlik_id_ekle" class="<?= $etkinlik_hata ? 'input-hata' : '' ?>" onchange="etkinlikDegisti()" <?= ($ekle_modal_ac && !$vatandas_hata) ? '' : 'disabled' ?>>
                <option value="">Etkinlik seç...</option>
                <?php foreach ($etkinlikler_listesi as $e): ?>
                    <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['ad']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($etkinlik_hata): ?>
                <p class="hata-mesaj-inline">Bir etkinlik seçmelisin.</p>
            <?php endif; ?>

            <label for="koltuk-secimi">Koltuk <span class="zorunlu-yildiz">*</span></label>
            <select id="koltuk-secimi" name="koltuk_id_ekle" class="<?= $koltuk_hata ? 'input-hata' : '' ?>" disabled>
                <option value="">Önce vatandaş ve etkinlik seç...</option>
            </select>
            <?php if ($koltuk_hata): ?>
                <p class="hata-mesaj-inline"><?= $hata !== '' && strpos($hata, 'boş değil') !== false ? htmlspecialchars($hata) : 'Bir koltuk seçmelisin.' ?></p>
            <?php endif; ?>

            <input type="hidden" name="rez_ekle" value="1">

            <div class="modal-butonlar">
                <button type="button" class="btn-vazgec" onclick="ekleFormunuKapatSifirla()">Vazgeç</button>
                <button type="submit" class="btn-kaydet">Kaydet</button>
            </div>
        </form>
    </dialog>

    <script>
        function ekleFormunuSifirla() {
            document.getElementById('tc-arama-kutusu').value = '';
            document.getElementById('tc-sonuc-listesi').innerHTML = '';
            vatandasKaldir();
            document.getElementById('ekle-rez-form').reset();

            document.querySelectorAll('#ekle-rez-modal .hata-mesaj-genel, #ekle-rez-modal .hata-mesaj-inline').forEach(function (el) {
                el.remove();
            });
            document.querySelectorAll('#ekle-rez-modal .input-hata').forEach(function (el) {
                el.classList.remove('input-hata');
            });
        }

        function ekleFormunuAcSifirla() {
            ekleFormunuSifirla();
            document.getElementById('ekle-rez-modal').showModal();
        }

        function ekleFormunuKapatSifirla() {
            document.getElementById('ekle-rez-modal').close();
            ekleFormunuSifirla();
        }

        var tcZamanlayici = null;
        var tcVurgulananIndex = -1;

        function vatandasSec(v) {
            document.getElementById('vatandas-id-gizli').value = v.id;
            document.getElementById('secili-vatandas-metni').textContent = v.ad + ' ' + v.soyad + ' (' + v.tc_no + ')';
            document.getElementById('secili-vatandas-goster').style.display = 'flex';
            document.getElementById('tc-sonuc-listesi').innerHTML = '';
            document.getElementById('tc-arama-kutusu').value = '';
            document.getElementById('tc-arama-kutusu').disabled = true;
            document.getElementById('etkinlik-secimi').disabled = false;
            tcVurgulananIndex = -1;
            document.getElementById('etkinlik-secimi').focus();
        }

        function tcVurguGuncelle() {
            var satirlar = document.querySelectorAll('.tc-sonuc-satiri');
            satirlar.forEach(function (s, i) {
                s.classList.toggle('tc-sonuc-vurgulu', i === tcVurgulananIndex);
            });
            if (tcVurgulananIndex >= 0 && satirlar[tcVurgulananIndex]) {
                satirlar[tcVurgulananIndex].scrollIntoView({ block: 'nearest' });
            }
        }

        document.getElementById('tc-arama-kutusu').addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '');
            var deger = this.value.trim();
            clearTimeout(tcZamanlayici);
            document.getElementById('vatandas-id-gizli').value = '';
            document.getElementById('secili-vatandas-goster').style.display = 'none';
            tcVurgulananIndex = -1;

            if (deger.length < 3) {
                document.getElementById('tc-sonuc-listesi').innerHTML = '';
                return;
            }

            tcZamanlayici = setTimeout(function () {
                fetch('vatandas-ara.php?tc=' + encodeURIComponent(deger))
                    .then(function (yanit) { return yanit.json(); })
                    .then(function (sonuclar) {
                        var kutu = document.getElementById('tc-sonuc-listesi');
                        kutu.innerHTML = '';
                        tcVurgulananIndex = -1;
                        sonuclar.forEach(function (v) {
                            var satir = document.createElement('div');
                            satir.className = 'tc-sonuc-satiri';
                            satir.textContent = v.tc_no + ' — ' + v.ad + ' ' + v.soyad;
                            satir.onclick = function () {
                                vatandasSec(v);
                            };
                            satir.dataset.vatandas = JSON.stringify(v);
                            kutu.appendChild(satir);
                        });
                    });
            }, 350);
        });

        document.getElementById('tc-arama-kutusu').addEventListener('keydown', function (e) {
            var satirlar = document.querySelectorAll('.tc-sonuc-satiri');
            if (satirlar.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                tcVurgulananIndex = Math.min(tcVurgulananIndex + 1, satirlar.length - 1);
                tcVurguGuncelle();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                tcVurgulananIndex = Math.max(tcVurgulananIndex - 1, 0);
                tcVurguGuncelle();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                var secilecekIndex = tcVurgulananIndex >= 0 ? tcVurgulananIndex : 0;
                var secilecekSatir = satirlar[secilecekIndex];
                if (secilecekSatir) {
                    vatandasSec(JSON.parse(secilecekSatir.dataset.vatandas));
                }
            }
        });

        function vatandasKaldir() {
            document.getElementById('vatandas-id-gizli').value = '';
            document.getElementById('secili-vatandas-goster').style.display = 'none';
            document.getElementById('tc-arama-kutusu').disabled = false;
            document.getElementById('etkinlik-secimi').disabled = true;
            document.getElementById('etkinlik-secimi').value = '';
            document.getElementById('koltuk-secimi').innerHTML = '<option value="">Önce vatandaş ve etkinlik seç...</option>';
            document.getElementById('koltuk-secimi').disabled = true;
            document.getElementById('tc-arama-kutusu').focus();
        }

        function etkinlikDegisti() {
            var etkinlikId = document.getElementById('etkinlik-secimi').value;
            var koltukKutu = document.getElementById('koltuk-secimi');

            if (etkinlikId === '') {
                koltukKutu.innerHTML = '<option value="">Önce etkinlik seç...</option>';
                koltukKutu.disabled = true;
                return;
            }

            koltukKutu.innerHTML = '<option value="">Yükleniyor...</option>';
            koltukKutu.disabled = true;

            fetch('bos-koltuklar.php?etkinlik_id=' + etkinlikId)
                .then(function (yanit) { return yanit.json(); })
                .then(function (koltuklar) {
                    if (koltuklar.length === 0) {
                        koltukKutu.innerHTML = '<option value="">Boş koltuk yok</option>';
                        koltukKutu.disabled = true;
                        return;
                    }
                    koltukKutu.innerHTML = '<option value="">Koltuk seç...</option>';
                    koltuklar.forEach(function (k) {
                        var secenek = document.createElement('option');
                        secenek.value = k.id;
                        secenek.textContent = k.koltuk_no;
                        koltukKutu.appendChild(secenek);
                    });
                    koltukKutu.disabled = false;
                    koltukKutu.focus();
                });
        }

        <?php if ($ekle_modal_ac): ?>
        document.getElementById('ekle-rez-modal').showModal();
        <?php endif; ?>
    </script>

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
        <div class="qr-sekme-satiri">
            <button type="button" class="qr-sekme qr-sekme-aktif" id="qr-sekme-btn" onclick="qrSekmeGoster('kod')">QR Kod</button>
            <button type="button" class="qr-sekme" id="referans-sekme-btn" onclick="qrSekmeGoster('referans')">Referans Kodu</button>
        </div>
        <div id="qr-kod-alani" class="qr-kod-alani"></div>
        <div class="qr-referans-goster" id="qr-referans-goster" style="display:none">
            <p class="qr-referans-etiket">Referans Kodu</p>
            <p class="qr-referans-kodu" id="qr-referans-kodu"></p>
        </div>
        <div class="modal-butonlar">
            <button type="button" class="btn-vazgec" onclick="document.getElementById('qr-modal').close()">Kapat</button>
        </div>
    </dialog>

    <script>
        function filtreAcKapa(id) {
            document.querySelectorAll('.filtre-paneli').forEach(function (el) {
                if (el.id !== id) el.style.display = 'none';
            });
            var liste = document.getElementById(id);
            liste.style.display = (liste.style.display === 'block') ? 'none' : 'block';
        }

        function filtreDegistir(anahtar, deger) {
            var mevcutUrl = new URL(window.location.href);
            if (deger === '') {
                mevcutUrl.searchParams.delete(anahtar);
            } else {
                mevcutUrl.searchParams.set(anahtar, deger);
            }
            window.location.href = mevcutUrl.toString();
        }

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.ozel-secici-sarmalayici')) {
                document.querySelectorAll('.filtre-paneli').forEach(function (el) {
                    el.style.display = 'none';
                });
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
            qrSekmeGoster('kod');
            document.getElementById('qr-modal').showModal();
        }

        function qrSekmeGoster(hangisi) {
            var kodAlani = document.getElementById('qr-kod-alani');
            var referansAlani = document.getElementById('qr-referans-goster');
            var kodBtn = document.getElementById('qr-sekme-btn');
            var referansBtn = document.getElementById('referans-sekme-btn');

            if (hangisi === 'kod') {
                kodAlani.style.display = 'flex';
                referansAlani.style.display = 'none';
                kodBtn.classList.add('qr-sekme-aktif');
                referansBtn.classList.remove('qr-sekme-aktif');
            } else {
                kodAlani.style.display = 'none';
                referansAlani.style.display = 'block';
                kodBtn.classList.remove('qr-sekme-aktif');
                referansBtn.classList.add('qr-sekme-aktif');
            }
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

<?php if (isset($_GET['yeni_qr'])): ?>
        qrGoster('<?= htmlspecialchars(addslashes($_GET['yeni_qr'])) ?>', '<?= htmlspecialchars(addslashes($_GET['yeni_isim'] ?? '')) ?>');
        <?php endif; ?>

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