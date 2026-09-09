<?php
require '../includes/admin-auth-kontrol.php';
require '../includes/db.php';

$basari = '';
$hata = '';
$ekle_modal_ac = false;

$vatandas_hata = false;
$etkinlik_hata = false;
$koltuk_hata = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rez_ekle'])) {
    $vatandas_id = $_POST['vatandas_id'] ?? '';
    $etkinlik_id_ekle = $_POST['etkinlik_id_ekle'] ?? '';
    $koltuk_id_listesi = $_POST['koltuk_id_ekle'] ?? [];

    if (!is_array($koltuk_id_listesi)) {
        $koltuk_id_listesi = [];
    }

    $koltuk_id_listesi = array_values(array_filter($koltuk_id_listesi, function ($v) {
        return ctype_digit((string)$v);
    }));

    $vatandas_hata = !ctype_digit((string)$vatandas_id);
    $etkinlik_hata = !ctype_digit((string)$etkinlik_id_ekle);
    $koltuk_hata = count($koltuk_id_listesi) === 0 || count($koltuk_id_listesi) > 4;

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
        $yer_tutucular = implode(',', array_fill(0, count($koltuk_id_listesi), '?'));
        $kontrol = $pdo->prepare("SELECT id, durum FROM koltuklar WHERE id IN ($yer_tutucular) AND etkinlik_id = ? AND deleted = 0");
        $kontrol->execute(array_merge($koltuk_id_listesi, [$etkinlik_id_ekle]));
        $bulunan_koltuklar = $kontrol->fetchAll();

        $hepsi_bos_mu = count($bulunan_koltuklar) === count($koltuk_id_listesi);
        foreach ($bulunan_koltuklar as $k) {
            if ($k['durum'] !== 'bos') {
                $hepsi_bos_mu = false;
            }
        }

        if (!$hepsi_bos_mu) {
            $_SESSION['rez_form_hata'] = [
                'hata' => 'Seçtiğin koltuklardan biri artık boş değil, lütfen tekrar seç.',
                'vatandas_hata' => false,
                'etkinlik_hata' => false,
                'koltuk_hata' => true,
            ];
            header('Location: rezervasyonlar.php?form_hata=1');
            exit;
        } else {
            $son_qr_token = '';
            foreach ($koltuk_id_listesi as $koltuk_id) {
                $yeni_qr_token = bin2hex(random_bytes(16));
                $son_qr_token = $yeni_qr_token;

                // Bu koltuk icin daha once iptal edilmis (soft-delete) bir kayit varsa,
                // unique constraint (koltuk_id) cakismasin diye yeni INSERT yerine o kaydi canlandir.
                $eski_kayit = $pdo->prepare("SELECT id FROM rezervasyonlar WHERE koltuk_id = :koltuk_id AND deleted = 1 LIMIT 1");
                $eski_kayit->execute(['koltuk_id' => $koltuk_id]);
                $eski = $eski_kayit->fetch();

                if ($eski) {
                    $ekle = $pdo->prepare("UPDATE rezervasyonlar SET vatandas_id = :vatandas_id, etkinlik_id = :etkinlik_id, qr_token = :qr_token, protokol_adi = NULL, deleted = 0, giris_yapildi_mi = 0, giris_zamani = NULL, olusturma_tarihi = NOW() WHERE id = :id");
                    $ekle->execute([
                        'vatandas_id' => $vatandas_id,
                        'etkinlik_id' => $etkinlik_id_ekle,
                        'qr_token' => $yeni_qr_token,
                        'id' => $eski['id'],
                    ]);
                } else {
                    $ekle = $pdo->prepare("INSERT INTO rezervasyonlar (vatandas_id, etkinlik_id, koltuk_id, qr_token) VALUES (:vatandas_id, :etkinlik_id, :koltuk_id, :qr_token)");
                    $ekle->execute([
                        'vatandas_id' => $vatandas_id,
                        'etkinlik_id' => $etkinlik_id_ekle,
                        'koltuk_id' => $koltuk_id,
                        'qr_token' => $yeni_qr_token,
                    ]);
                }

                $koltuk_guncelle = $pdo->prepare("UPDATE koltuklar SET durum = 'dolu' WHERE id = :id");
                $koltuk_guncelle->execute(['id' => $koltuk_id]);
            }

            $vatandas_bilgi = $pdo->prepare("SELECT ad, soyad FROM vatandaslar WHERE id = :id");
            $vatandas_bilgi->execute(['id' => $vatandas_id]);
            $v_satiri = $vatandas_bilgi->fetch();
            $yeni_isim = $v_satiri ? $v_satiri['ad'] . ' ' . $v_satiri['soyad'] : '';
            $koltuk_sayisi = count($koltuk_id_listesi);

            $yonlendirme = [
                'rez_eklendi' => 1,
                'rez_koltuk_sayisi' => $koltuk_sayisi,
                'yeni_isim' => $yeni_isim,
            ];
            if ($koltuk_sayisi === 1) {
                $yonlendirme['yeni_qr'] = $son_qr_token;
            }

            header('Location: rezervasyonlar.php?' . http_build_query($yonlendirme));
            exit;
        }
    }
}

if (isset($_GET['rez_eklendi'])) {
    $eklenen_koltuk_sayisi = (int)($_GET['rez_koltuk_sayisi'] ?? 1);
    $basari = $eklenen_koltuk_sayisi > 1 ? $eklenen_koltuk_sayisi . ' koltuk için rezervasyon eklendi.' : 'Rezervasyon eklendi.';
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

// Giris durumunu degistir (AJAX destegiyle - sayfa kaymasin diye)
if (isset($_GET['giris_toggle'])) {
    $toggle_id = $_GET['giris_toggle'];
    $ajax_istegi = isset($_GET['ajax']) && $_GET['ajax'] === '1';

    if (ctype_digit((string)$toggle_id)) {
        $mevcut = $pdo->prepare("
            SELECT r.giris_yapildi_mi, e.etkinlik_tarih, e.etkinlik_saat
            FROM rezervasyonlar r
            JOIN etkinlikler e ON e.id = r.etkinlik_id
            WHERE r.id = :id AND r.deleted = 0
        ");
        $mevcut->execute(['id' => $toggle_id]);
        $satir = $mevcut->fetch();
        $yeni_durum = null;
        $vakti_gelmedi = false;

        if ($satir) {
            $yeni_durum = $satir['giris_yapildi_mi'] ? 0 : 1;

            // Sadece "giriş yapıldı" olarak işaretlerken (0 -> 1) kontrol et.
            // Geri almak (1 -> 0) her zaman serbest.
            if ($yeni_durum === 1 && $satir['etkinlik_tarih']) {
                $etkinlik_zamani = $satir['etkinlik_tarih'] . ' ' . ($satir['etkinlik_saat'] ?? '00:00:00');
                if (strtotime($etkinlik_zamani) > time()) {
                    $vakti_gelmedi = true;
                }
            }

            if (!$vakti_gelmedi) {
                $guncelle = $pdo->prepare("UPDATE rezervasyonlar SET giris_yapildi_mi = :durum, giris_zamani = :zaman WHERE id = :id");
                $guncelle->execute([
                    'durum' => $yeni_durum,
                    'zaman' => $yeni_durum ? date('Y-m-d H:i:s') : null,
                    'id' => $toggle_id,
                ]);
            }
        }

        if ($ajax_istegi) {
            header('Content-Type: application/json');
            if ($vakti_gelmedi) {
                echo json_encode([
                    'basarili' => false,
                    'hata' => 'Etkinliğin vakti henüz gelmediği için giriş işaretlenemez.',
                ]);
            } else {
                echo json_encode([
                    'basarili' => $satir !== false,
                    'yeni_durum' => $yeni_durum,
                ]);
            }
            exit;
        }

        $geri_don = 'rezervasyonlar.php?' . http_build_query(array_filter([
            'etkinlik_id' => $_GET['etkinlik_id'] ?? null,
            'giris' => $_GET['giris'] ?? null,
            'tur' => $_GET['tur'] ?? null,
            'q' => $_GET['q'] ?? null,
            'page' => $_GET['page'] ?? null,
        ]));
        header('Location: ' . $geri_don);
        exit;
    }
}

$arama = trim($_GET['q'] ?? '');
$etkinlik_filtre = $_GET['etkinlik_id'] ?? '';
$giris_filtre = $_GET['giris'] ?? 'tumu';
$tur_filtre = $_GET['tur'] ?? 'tumu';

$etkinlikler_listesi = $pdo->query("SELECT id, ad FROM etkinlikler WHERE deleted = 0 AND aktif_mi = 1 ORDER BY ad ASC")->fetchAll();

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
               e.ad AS etkinlik_ad, e.etkinlik_tarih, e.etkinlik_saat, k.koltuk_no, k.durum AS koltuk_durumu
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
    $sql .= " AND r.protokol_adi IS NOT NULL AND k.durum != 'dolu'";
} elseif ($tur_filtre === 'dolu') {
    $sql .= " AND r.protokol_adi IS NOT NULL AND k.durum = 'dolu'";
}
if ($arama !== '') {
    $sql .= " AND (v.ad LIKE :arama OR v.soyad LIKE :arama OR v.tc_no LIKE :arama OR e.ad LIKE :arama OR r.protokol_adi LIKE :arama)";
    $parametreler['arama'] = '%' . $arama . '%';
}

$count_sql = "SELECT COUNT(*)
        FROM rezervasyonlar r
        LEFT JOIN vatandaslar v ON r.vatandas_id = v.id
        JOIN etkinlikler e ON r.etkinlik_id = e.id
        JOIN koltuklar k ON r.koltuk_id = k.id
        WHERE r.deleted = 0";

$count_parametreler = [];
if ($etkinlik_filtre !== '' && ctype_digit((string)$etkinlik_filtre)) {
    $count_sql .= " AND r.etkinlik_id = :etkinlik_id";
    $count_parametreler['etkinlik_id'] = $etkinlik_filtre;
}
if ($giris_filtre === 'yapti') {
    $count_sql .= " AND r.giris_yapildi_mi = 1";
} elseif ($giris_filtre === 'bekliyor') {
    $count_sql .= " AND r.giris_yapildi_mi = 0";
}
if ($tur_filtre === 'vatandas') {
    $count_sql .= " AND r.protokol_adi IS NULL";
} elseif ($tur_filtre === 'protokol') {
    $count_sql .= " AND r.protokol_adi IS NOT NULL AND k.durum != 'dolu'";
} elseif ($tur_filtre === 'dolu') {
    $count_sql .= " AND r.protokol_adi IS NOT NULL AND k.durum = 'dolu'";
}
if ($arama !== '') {
    $count_sql .= " AND (v.ad LIKE :arama OR v.soyad LIKE :arama OR v.tc_no LIKE :arama OR e.ad LIKE :arama OR r.protokol_adi LIKE :arama)";
    $count_parametreler['arama'] = '%' . $arama . '%';
}

$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($count_parametreler);
$toplam_kayit = (int)$count_stmt->fetchColumn();

$sayfa_basina = 20;
$sayfa = isset($_GET['page']) && ctype_digit((string)$_GET['page']) ? (int)$_GET['page'] : 1;
$sayfa = max(1, $sayfa);
$toplam_sayfa = max(1, (int)ceil($toplam_kayit / $sayfa_basina));
if ($sayfa > $toplam_sayfa) {
    $sayfa = $toplam_sayfa;
}
$offset = ($sayfa - 1) * $sayfa_basina;

$sql .= " ORDER BY r.olusturma_tarihi DESC, r.id DESC LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($parametreler as $anahtar => $deger) {
    $stmt->bindValue(':' . $anahtar, $deger);
}
$stmt->bindValue(':limit', $sayfa_basina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
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
                    $tur_etiketleri = ['tumu' => 'Tümü', 'vatandas' => 'Vatandaş', 'protokol' => 'Protokol', 'dolu' => 'Dolu'];
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
                            <div class="ozel-secici-madde <?= $tur_filtre === 'dolu' ? 'ozel-secici-madde-secili' : '' ?>" onclick="filtreDegistir('tur', 'dolu')">Dolu</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rez-ekle-toplu-satiri">
                <button type="button" class="btn-ekle" onclick="ekleFormunuAcSifirla()">+ Yeni Rezervasyon Ekle</button>
                <div class="rez-toplu-satir">
                    <span id="secili-sayisi-metni" style="display:none">0 rezervasyon seçildi</span>
                    <button type="button" class="ozel-secici-buton btn-kucuk-filtre" id="secilenleri-sil-btn" style="display:none" onclick="topluSilOnayGoster()">Seçilenleri Sil</button>
                    <button type="button" class="ozel-secici-buton btn-kucuk-filtre" id="secilenleri-kaldir-btn" style="display:none" onclick="secimiTemizle()">Seçilenleri Kaldır</button>
                    <button type="button" class="ozel-secici-buton btn-kucuk-filtre" id="tumunu-sec-btn" onclick="tumunuSecToggle()">Tümünü Seç</button>
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
                    <?php $sira = $offset + 1; foreach ($rezervasyonlar as $r): ?>
                        <tr class="rez-secilebilir-satir <?= $r['giris_yapildi_mi'] ? 'satir-giris-yapildi' : '' ?>" data-rez-id="<?= $r['id'] ?>" onclick="satirSecimToggle(event, this)">
                            <td><?= $sira++ ?></td>
                            <td>
                                <?php if ($r['protokol_adi'] !== null): ?>
                                    <?= htmlspecialchars($r['protokol_adi']) ?> <span class="protokol-etiketi <?= $r['koltuk_durumu'] === 'dolu' ? 'dolu-etiketi' : '' ?>"><?= $r['koltuk_durumu'] === 'dolu' ? 'Dolu' : 'Protokol' ?></span>
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
                                    <a href="<?= url_koru(['giris_toggle' => $r['id']]) ?>" class="giris-kutu <?= $r['giris_yapildi_mi'] ? 'giris-kutu-isaretli' : '' ?>" data-rez-id="<?= $r['id'] ?>" title="<?= $r['giris_yapildi_mi'] ? 'Giriş yaptı - kaldırmak için tıkla' : 'Giriş yapmadı - işaretlemek için tıkla' ?>" onclick="girisToggle(event, this)"></a>
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

            <?php if ($toplam_sayfa > 1): ?>
                <div class="rez-sayfalama">
                    <?php if ($sayfa > 1): ?>
                        <a href="<?= url_koru(['page' => $sayfa - 1]) ?>" class="sayfa-ok" aria-label="Önceki sayfa">←</a>
                    <?php else: ?>
                        <span class="sayfa-ok pasif" aria-hidden="true">←</span>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $toplam_sayfa; $i++): ?>
                        <?php if ($i === $sayfa): ?>
                            <span class="sayfa-numara aktif"><?= $i ?></span>
                        <?php else: ?>
                            <a href="<?= url_koru(['page' => $i]) ?>" class="sayfa-numara"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($sayfa < $toplam_sayfa): ?>
                        <a href="<?= url_koru(['page' => $sayfa + 1]) ?>" class="sayfa-ok" aria-label="Sonraki sayfa">→</a>
                    <?php else: ?>
                        <span class="sayfa-ok pasif" aria-hidden="true">→</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
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

        <label>Koltuk (en fazla 4) <span class="zorunlu-yildiz">*</span></label>
        <div class="koltuk-coklu-secim" id="koltuk-coklu-secim">
            <p class="rez-bos-mesaj">Önce vatandaş ve etkinlik seç...</p>
        </div>
        <?php if ($koltuk_hata): ?>
            <p class="hata-mesaj-inline"><?= $hata !== '' && strpos($hata, 'boş değil') !== false ? htmlspecialchars($hata) : 'En az bir koltuk seçmelisin.' ?></p>
        <?php endif; ?>
        <div id="koltuk-id-alanlari"></div>

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
    seciliKoltukIdleri = [];
    document.getElementById('koltuk-coklu-secim').innerHTML = '<p class="rez-bos-mesaj">Önce vatandaş ve etkinlik seç...</p>';
    document.getElementById('koltuk-id-alanlari').innerHTML = '';

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
    document.getElementById('koltuk-coklu-secim').innerHTML = '<p class="rez-bos-mesaj">Önce vatandaş ve etkinlik seç...</p>';
    document.getElementById('tc-arama-kutusu').focus();
}

var seciliKoltukIdleri = [];
var MAKS_KOLTUK_ADMIN = 4;

function etkinlikDegisti() {
    var etkinlikId = document.getElementById('etkinlik-secimi').value;
    var kutu = document.getElementById('koltuk-coklu-secim');
    seciliKoltukIdleri = [];
    koltukIdAlanlariniGuncelle();

    if (etkinlikId === '') {
        kutu.innerHTML = '<p class="rez-bos-mesaj">Önce etkinlik seç...</p>';
        return;
    }

    kutu.innerHTML = '<p class="rez-bos-mesaj">Yükleniyor...</p>';

    fetch('bos-koltuklar.php?etkinlik_id=' + etkinlikId)
        .then(function (yanit) { return yanit.json(); })
        .then(function (koltuklar) {
            if (koltuklar.length === 0) {
                kutu.innerHTML = '<p class="rez-bos-mesaj">Boş koltuk yok.</p>';
                return;
            }
            var html = '';
            koltuklar.forEach(function (k) {
                html += '<button type="button" class="koltuk-cip" data-id="' + k.id + '" data-no="' + k.koltuk_no + '" onclick="koltukToggle(this)">' + k.koltuk_no + '</button>';
            });
            kutu.innerHTML = html;
        });
}

function koltukToggle(buton) {
    var id = buton.dataset.id;
    var index = seciliKoltukIdleri.indexOf(id);

    if (index !== -1) {
        seciliKoltukIdleri.splice(index, 1);
        buton.classList.remove('koltuk-cip-secili');
    } else {
        if (seciliKoltukIdleri.length >= MAKS_KOLTUK_ADMIN) {
            alert('En fazla ' + MAKS_KOLTUK_ADMIN + ' koltuk seçebilirsin.');
            return;
        }
        seciliKoltukIdleri.push(id);
        buton.classList.add('koltuk-cip-secili');
    }
    koltukIdAlanlariniGuncelle();
}

function koltukIdAlanlariniGuncelle() {
    var alan = document.getElementById('koltuk-id-alanlari');
    var html = '';
    seciliKoltukIdleri.forEach(function (id) {
        html += '<input type="hidden" name="koltuk_id_ekle[]" value="' + id + '">';
    });
    alan.innerHTML = html;
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
        <button type="button" id="iptal-onay-buton" class="btn-sil-onayla" onclick="iptalOnayla()">Evet, İptal Et</button>
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
    mevcutUrl.searchParams.delete('page');
    window.location.href = mevcutUrl.toString();
}

document.addEventListener('click', function (e) {
    if (!e.target.closest('.ozel-secici-sarmalayici')) {
        document.querySelectorAll('.filtre-paneli').forEach(function (el) {
            el.style.display = 'none';
        });
    }
});

function gosterToast(mesaj) {
    var eski = document.getElementById('basari-toast');
    if (eski) eski.remove();

    var toast = document.createElement('div');
    toast.className = 'toast-bildirim';
    toast.id = 'basari-toast';
    toast.textContent = mesaj;

    var toplu = document.querySelector('.rez-toplu-satir');
    toplu.parentNode.insertBefore(toast, toplu.nextSibling);

    setTimeout(function () {
        toast.classList.add('toast-kayboluyor');
        setTimeout(function () { toast.remove(); }, 400);
    }, 2500);
}

var REZ_SIRA_BASLANGIC = <?= $offset ?>;

function siraNumaralariniGuncelle() {
    var satirlar = document.querySelectorAll('.rez-secilebilir-satir');
    satirlar.forEach(function (satir, index) {
        var ilkHucre = satir.querySelector('td');
        if (ilkHucre) {
            ilkHucre.textContent = REZ_SIRA_BASLANGIC + index + 1;
        }
    });
}

var aktifIptalIdler = [];

var REZ_SECIM_ANAHTARI = 'rez-secili-idler';

function secimListesiOku() {
    try {
        var veri = sessionStorage.getItem(REZ_SECIM_ANAHTARI);
        return veri ? JSON.parse(veri) : [];
    } catch (e) {
        return [];
    }
}

function secimListesiYaz(liste) {
    sessionStorage.setItem(REZ_SECIM_ANAHTARI, JSON.stringify(liste));
}

function secimEkle(id) {
    var liste = secimListesiOku();
    if (liste.indexOf(id) === -1) {
        liste.push(id);
        secimListesiYaz(liste);
    }
}

function secimCikar(id) {
    var liste = secimListesiOku();
    var index = liste.indexOf(id);
    if (index !== -1) {
        liste.splice(index, 1);
        secimListesiYaz(liste);
    }
}

function iptalOnayGoster(id, vatandasAdi, etkinlikAdi) {
    aktifIptalIdler = [id];
    document.getElementById('iptal-onay-mesaji').textContent = '"' + vatandasAdi + '" adlı kişinin "' + etkinlikAdi + '" etkinliğindeki rezervasyonunu iptal etmek istediğine emin misin? Koltuk otomatik olarak boşa çıkacak.';
    document.getElementById('iptal-onay-modal').showModal();
}

function topluSilOnayGoster() {
    aktifIptalIdler = secimListesiOku();
    if (aktifIptalIdler.length === 0) return;

    document.getElementById('iptal-onay-mesaji').textContent = 'Seçtiğin ' + aktifIptalIdler.length + ' rezervasyonu iptal etmek istediğine emin misin? İlgili koltuklar otomatik olarak boşa çıkacak.';
    document.getElementById('iptal-onay-modal').showModal();
}

function satirSecimToggle(event, satir) {
    if (event.target.closest('button, a')) return;
    var secildi = satir.classList.toggle('rez-satir-secili');
    var id = satir.dataset.rezId;
    if (secildi) {
        secimEkle(id);
    } else {
        secimCikar(id);
    }
    secimDegisti();
}

function tumunuSecToggle() {
    var satirlar = document.querySelectorAll('.rez-secilebilir-satir');
    var secililer = document.querySelectorAll('.rez-secilebilir-satir.rez-satir-secili');
    var sayfaHepsiSeciliMi = satirlar.length > 0 && secililer.length === satirlar.length;

    satirlar.forEach(function (satir) {
        satir.classList.toggle('rez-satir-secili', !sayfaHepsiSeciliMi);
        var id = satir.dataset.rezId;
        if (!sayfaHepsiSeciliMi) {
            secimEkle(id);
        } else {
            secimCikar(id);
        }
    });
    secimDegisti();
}

function secimDegisti() {
    var satirlar = document.querySelectorAll('.rez-secilebilir-satir');
    var secililer = document.querySelectorAll('.rez-secilebilir-satir.rez-satir-secili');
    var toplamSecili = secimListesiOku().length;

    var sayacMetni = document.getElementById('secili-sayisi-metni');
    var silBtn = document.getElementById('secilenleri-sil-btn');
    var kaldirBtn = document.getElementById('secilenleri-kaldir-btn');
    var tumunuSecBtn = document.getElementById('tumunu-sec-btn');

    var sayfaHepsiSeciliMi = satirlar.length > 0 && secililer.length === satirlar.length;

    if (toplamSecili > 0) {
        sayacMetni.textContent = toplamSecili + ' rezervasyon seçildi';
        sayacMetni.style.display = 'inline';
        silBtn.style.display = 'inline-block';
        kaldirBtn.style.display = 'inline-block';
    } else {
        sayacMetni.style.display = 'none';
        silBtn.style.display = 'none';
        kaldirBtn.style.display = 'none';
    }

    tumunuSecBtn.textContent = sayfaHepsiSeciliMi ? 'Tümünü Kaldır' : 'Tümünü Seç';
}

function secimiTemizle() {
    document.querySelectorAll('.rez-secilebilir-satir.rez-satir-secili').forEach(function (satir) {
        satir.classList.remove('rez-satir-secili');
    });
    secimListesiYaz([]);
    secimDegisti();
}

function iptalOnayla() {
    if (aktifIptalIdler.length === 0) return;

    var buton = document.getElementById('iptal-onay-buton');
    buton.disabled = true;
    buton.textContent = 'İptal ediliyor...';

    var gonderilenIdler = aktifIptalIdler.slice();

    var veriler = new FormData();
    gonderilenIdler.forEach(function (id) {
        veriler.append('idler[]', id);
    });

    fetch('rezervasyon-toplu-sil.php', { method: 'POST', body: veriler })
        .then(function (yanit) { return yanit.json(); })
        .then(function (veri) {
            buton.disabled = false;
            buton.textContent = 'Evet, İptal Et';
            document.getElementById('iptal-onay-modal').close();

            if (!veri.basarili) {
                gosterToast(veri.hata || 'İptal edilemedi, tekrar dene.');
                return;
            }

            var silinenler = (veri.silinen_idler && veri.silinen_idler.length > 0) ? veri.silinen_idler : gonderilenIdler;
            silinenler.forEach(function (id) {
                var satir = document.querySelector('.rez-secilebilir-satir[data-rez-id="' + id + '"]');
                if (satir) satir.remove();
                secimCikar(String(id));
            });
            siraNumaralariniGuncelle();

            gosterToast(silinenler.length > 1 ? silinenler.length + ' rezervasyon iptal edildi.' : 'Rezervasyon iptal edildi.');

            aktifIptalIdler = [];
            secimDegisti();
        })
        .catch(function (hata) {
            buton.disabled = false;
            buton.textContent = 'Evet, İptal Et';
            document.getElementById('iptal-onay-modal').close();
            console.error('İptal işlemi hatası:', hata);
            gosterToast('Bir hata oluştu: ' + hata.message);
        });
}

function girisToggle(event, baglanti) {
    event.preventDefault();

    var href = baglanti.getAttribute('href');
    baglanti.style.pointerEvents = 'none';

    fetch(href + (href.indexOf('?') !== -1 ? '&' : '?') + 'ajax=1')
        .then(function (yanit) { return yanit.json(); })
        .then(function (veri) {
            baglanti.style.pointerEvents = '';

            if (!veri.basarili) {
                gosterToast(veri.hata || 'İşlem yapılamadı, tekrar dene.');
                return;
            }

            var yeniDurum = veri.yeni_durum === 1;
            var satir = baglanti.closest('tr');

            baglanti.classList.toggle('giris-kutu-isaretli', yeniDurum);
            baglanti.title = yeniDurum ? 'Giriş yaptı - kaldırmak için tıkla' : 'Giriş yapmadı - işaretlemek için tıkla';

            if (satir) {
                satir.classList.toggle('satir-giris-yapildi', yeniDurum);
            }
        })
        .catch(function () {
            baglanti.style.pointerEvents = '';
            gosterToast('Bir hata oluştu, tekrar dene.');
        });
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

<?php if (isset($_GET['yeni_qr'])): ?>
qrGoster('<?= htmlspecialchars(addslashes($_GET['yeni_qr'])) ?>', '<?= htmlspecialchars(addslashes($_GET['yeni_isim'] ?? '')) ?>');
if (window.history.replaceState) {
    var temizUrl = new URL(window.location.href);
    temizUrl.searchParams.delete('yeni_qr');
    temizUrl.searchParams.delete('yeni_isim');
    temizUrl.searchParams.delete('rez_eklendi');
    temizUrl.searchParams.delete('rez_koltuk_sayisi');
    window.history.replaceState(null, '', temizUrl.toString());
}
<?php endif; ?>

(function () {
    var kayitliSecim = secimListesiOku();
    if (kayitliSecim.length > 0) {
        document.querySelectorAll('.rez-secilebilir-satir').forEach(function (satir) {
            if (kayitliSecim.indexOf(satir.dataset.rezId) !== -1) {
                satir.classList.add('rez-satir-secili');
            }
        });
    }
    secimDegisti();
})();

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