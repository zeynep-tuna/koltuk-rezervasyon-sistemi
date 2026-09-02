<?php
require '../includes/admin-auth-kontrol.php';
require '../includes/db.php';

// Zamanlanmış yayın tarihi gelmiş ama hala Gizli olan etkinlikleri otomatik Aktif yap
$pdo->exec("UPDATE etkinlikler SET aktif_mi = 1 WHERE aktif_mi = 0 AND yayin_tarihi IS NOT NULL AND yayin_tarihi <= NOW() AND deleted = 0");

$hata = '';
$ekle_modal_ac = false;
$duzenle_modal_ac = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['etkinlik_ekle'])) {
    $ad = trim($_POST['ad'] ?? '');
    $yer = trim($_POST['yer'] ?? '');
    $etkinlik_tarih = trim($_POST['etkinlik_tarih'] ?? '');
    $etkinlik_saat = trim($_POST['etkinlik_saat'] ?? '');
    $koltuk_sayisi = trim($_POST['koltuk_sayisi'] ?? '');
    $aciklama = trim($_POST['aciklama'] ?? '');
    $aktif_mi = ($_POST['aktif_mi'] ?? '1') === '1' ? 1 : 0;
    $yayin_tarih_giris = trim($_POST['yayin_tarih'] ?? '');
    $yayin_saat_giris = trim($_POST['yayin_saat'] ?? '');
    $tarih_saat_zorunlu = ($aktif_mi === 1) || ($yayin_tarih_giris !== '' && $yayin_saat_giris !== '');

    $satir_basi_giris = trim($_POST['satir_basi_koltuk'] ?? '');

    if ($ad === '' || $yer === '') {
        $hata = 'Lütfen zorunlu alanları doldurun.';
        $ekle_modal_ac = true;
    } elseif ($tarih_saat_zorunlu && ($etkinlik_tarih === '' || $etkinlik_saat === '' || $koltuk_sayisi === '' || $satir_basi_giris === '')) {
        $hata = 'Etkinliği yayına almak (ya da zamanlanmış yayın kullanmak) için tarih, saat, koltuk sayısı ve sıra bilgisini girmelisiniz.';
        $ekle_modal_ac = true;
    } elseif ($koltuk_sayisi !== '' && (!ctype_digit($koltuk_sayisi) || (int)$koltuk_sayisi <= 0)) {
        $hata = 'Koltuk sayısı pozitif bir tam sayı olmalıdır.';
        $ekle_modal_ac = true;
    } elseif ($satir_basi_giris !== '' && (!ctype_digit($satir_basi_giris) || (int)$satir_basi_giris <= 0)) {
        $hata = 'Bir sıradaki koltuk sayısı pozitif bir tam sayı olmalıdır.';
        $ekle_modal_ac = true;
    } else {
        $yayin_tarihi = ($yayin_tarih_giris !== '' && $yayin_saat_giris !== '') ? $yayin_tarih_giris . ' ' . $yayin_saat_giris . ':00' : null;
        $koridor_giris = preg_replace('/[^0-9,]/', '', trim($_POST['koridor_sonrasi'] ?? ''));
        $koridor_giris = trim($koridor_giris, ',');
        $stmt = $pdo->prepare("INSERT INTO etkinlikler (ad, yer, etkinlik_tarih, etkinlik_saat, koltuk_sayisi, aciklama, aktif_mi, yayin_tarihi, satir_basi_koltuk, koridor_sonrasi) VALUES (:ad, :yer, :etkinlik_tarih, :etkinlik_saat, :koltuk_sayisi, :aciklama, :aktif_mi, :yayin_tarihi, :satir_basi_koltuk, :koridor_sonrasi)");
        $stmt->execute([
            'ad' => $ad,
            'yer' => $yer,
            'etkinlik_tarih' => $etkinlik_tarih !== '' ? $etkinlik_tarih : null,
            'etkinlik_saat' => $etkinlik_saat !== '' ? $etkinlik_saat : null,
            'koltuk_sayisi' => $koltuk_sayisi !== '' ? (int)$koltuk_sayisi : null,
            'aciklama' => $aciklama !== '' ? $aciklama : null,
            'aktif_mi' => $aktif_mi,
            'yayin_tarihi' => $yayin_tarihi,
            'satir_basi_koltuk' => $satir_basi_giris !== '' ? (int)$satir_basi_giris : null,
            'koridor_sonrasi' => $koridor_giris !== '' ? $koridor_giris : null,
        ]);

        $yeni_etkinlik_id = $pdo->lastInsertId();

        $satir_basi_koltuk = trim($_POST['satir_basi_koltuk'] ?? '');
        if ($satir_basi_koltuk !== '' && ctype_digit($satir_basi_koltuk) && (int)$satir_basi_koltuk > 0) {
            $satir_basi = (int)$satir_basi_koltuk;
            $harfler = range('A', 'Z');
            $satir_index = 0;
            $koltuk_no_index = 1;
            $koltuk_insert = $pdo->prepare("INSERT INTO koltuklar (etkinlik_id, koltuk_no, durum) VALUES (:etkinlik_id, :koltuk_no, 'bos')");
            for ($i = 1; $i <= (int)$koltuk_sayisi; $i++) {
                $harf = $harfler[$satir_index] ?? 'Z';
                $koltuk_no = $harf . $koltuk_no_index;
                $koltuk_insert->execute([
                    'etkinlik_id' => $yeni_etkinlik_id,
                    'koltuk_no' => $koltuk_no,
                ]);
                $koltuk_no_index++;
                if ($koltuk_no_index > $satir_basi) {
                    $koltuk_no_index = 1;
                    $satir_index++;
                }
            }
        }

        header('Location: etkinlikler.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['etkinlik_duzenle'])) {
    $id = $_POST['id'] ?? '';
    $ad = trim($_POST['ad'] ?? '');
    $yer = trim($_POST['yer'] ?? '');
    $etkinlik_tarih = trim($_POST['etkinlik_tarih'] ?? '');
    $etkinlik_saat = trim($_POST['etkinlik_saat'] ?? '');
    $koltuk_sayisi = trim($_POST['koltuk_sayisi'] ?? '');
    $aciklama = trim($_POST['aciklama'] ?? '');
    $aktif_mi = ($_POST['aktif_mi'] ?? '1') === '1' ? 1 : 0;
    $yayin_tarih_giris = trim($_POST['yayin_tarih'] ?? '');
    $yayin_saat_giris = trim($_POST['yayin_saat'] ?? '');
    $tarih_saat_zorunlu = ($aktif_mi === 1) || ($yayin_tarih_giris !== '' && $yayin_saat_giris !== '');

    if ($ad === '' || $yer === '') {
        $hata = 'Lütfen zorunlu alanları doldurun.';
        $duzenle_modal_ac = true;
    } elseif ($tarih_saat_zorunlu && ($etkinlik_tarih === '' || $etkinlik_saat === '' || $koltuk_sayisi === '')) {
        $hata = 'Etkinliği yayına almak (ya da zamanlanmış yayın kullanmak) için tarih, saat ve koltuk sayısını girmelisiniz.';
        $duzenle_modal_ac = true;
    } elseif ($koltuk_sayisi !== '' && (!ctype_digit($koltuk_sayisi) || (int)$koltuk_sayisi <= 0)) {
        $hata = 'Koltuk sayısı pozitif bir tam sayı olmalıdır.';
        $duzenle_modal_ac = true;
    } elseif (!ctype_digit((string)$id)) {
        $hata = 'Geçersiz etkinlik.';
        $duzenle_modal_ac = true;
    } else {
        $yayin_tarihi = ($yayin_tarih_giris !== '' && $yayin_saat_giris !== '') ? $yayin_tarih_giris . ' ' . $yayin_saat_giris . ':00' : null;
        $koridor_giris = preg_replace('/[^0-9,]/', '', trim($_POST['koridor_sonrasi'] ?? ''));
        $koridor_giris = trim($koridor_giris, ',');

        $stmt = $pdo->prepare("UPDATE etkinlikler SET ad = :ad, yer = :yer, etkinlik_tarih = :etkinlik_tarih, etkinlik_saat = :etkinlik_saat, koltuk_sayisi = :koltuk_sayisi, aciklama = :aciklama, aktif_mi = :aktif_mi, yayin_tarihi = :yayin_tarihi, koridor_sonrasi = :koridor_sonrasi WHERE id = :id");
        $stmt->execute([
            'ad' => $ad,
            'yer' => $yer,
            'etkinlik_tarih' => $etkinlik_tarih !== '' ? $etkinlik_tarih : null,
            'etkinlik_saat' => $etkinlik_saat !== '' ? $etkinlik_saat : null,
            'koltuk_sayisi' => $koltuk_sayisi !== '' ? (int)$koltuk_sayisi : null,
            'aciklama' => $aciklama !== '' ? $aciklama : null,
            'aktif_mi' => $aktif_mi,
            'yayin_tarihi' => $yayin_tarihi,
            'koridor_sonrasi' => $koridor_giris !== '' ? $koridor_giris : null,
            'id' => $id,
        ]);
        header('Location: etkinlikler.php');
        exit;
    }
}

$aylar = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];

function gun_secenekleri($secili = '') {
    $html = '<option value="">Gün</option>';
    for ($i = 1; $i <= 31; $i++) {
        $v = str_pad($i, 2, '0', STR_PAD_LEFT);
        $sel = ($v === $secili) ? ' selected' : '';
        $html .= '<option value="' . $v . '"' . $sel . '>' . $i . '</option>';
    }
    return $html;
}

function ay_secenekleri($aylar, $secili = '') {
    $html = '<option value="">Ay</option>';
    foreach ($aylar as $index => $ad) {
        $v = str_pad($index + 1, 2, '0', STR_PAD_LEFT);
        $sel = ($v === $secili) ? ' selected' : '';
        $html .= '<option value="' . $v . '"' . $sel . '>' . $ad . '</option>';
    }
    return $html;
}

function saat_secenekleri($secili = '') {
    $html = '<option value="">Saat</option>';
    for ($i = 0; $i <= 23; $i++) {
        $v = str_pad($i, 2, '0', STR_PAD_LEFT);
        $sel = ($v === $secili) ? ' selected' : '';
        $html .= '<option value="' . $v . '"' . $sel . '>' . $v . '</option>';
    }
    return $html;
}

function dakika_secenekleri($secili = '') {
    $html = '<option value="">Dakika</option>';
    for ($i = 0; $i <= 59; $i++) {
        $v = str_pad($i, 2, '0', STR_PAD_LEFT);
        $sel = ($v === $secili) ? ' selected' : '';
        $html .= '<option value="' . $v . '"' . $sel . '>' . $v . '</option>';
    }
    return $html;
}

function tarih_parcala($tarih) {
    if (!$tarih) {
        return ['', '', ''];
    }
    $p = explode('-', $tarih);
    return [$p[2] ?? '', $p[1] ?? '', $p[0] ?? ''];
}

function saat_parcala($saat) {
    if (!$saat) {
        return ['', ''];
    }
    $p = explode(':', $saat);
    return [$p[0] ?? '', $p[1] ?? ''];
}

list($ekle_gun, $ekle_ay, $ekle_yil) = ($ekle_modal_ac && isset($_POST['etkinlik_tarih'])) ? tarih_parcala($_POST['etkinlik_tarih']) : ['', '', ''];
list($ekle_saat_h, $ekle_dakika) = ($ekle_modal_ac && isset($_POST['etkinlik_saat'])) ? saat_parcala($_POST['etkinlik_saat']) : ['', ''];
list($duzenle_gun, $duzenle_ay, $duzenle_yil) = ($duzenle_modal_ac && isset($_POST['etkinlik_tarih'])) ? tarih_parcala($_POST['etkinlik_tarih']) : ['', '', ''];
list($duzenle_saat_h, $duzenle_dakika) = ($duzenle_modal_ac && isset($_POST['etkinlik_saat'])) ? saat_parcala($_POST['etkinlik_saat']) : ['', ''];

list($ekle_yayin_gun, $ekle_yayin_ay, $ekle_yayin_yil) = ($ekle_modal_ac && isset($_POST['yayin_tarih'])) ? tarih_parcala($_POST['yayin_tarih']) : ['', '', ''];
list($ekle_yayin_saat_h, $ekle_yayin_dakika) = ($ekle_modal_ac && isset($_POST['yayin_saat'])) ? saat_parcala($_POST['yayin_saat']) : ['', ''];
list($duzenle_yayin_gun, $duzenle_yayin_ay, $duzenle_yayin_yil) = ($duzenle_modal_ac && isset($_POST['yayin_tarih'])) ? tarih_parcala($_POST['yayin_tarih']) : ['', '', ''];
list($duzenle_yayin_saat_h, $duzenle_yayin_dakika) = ($duzenle_modal_ac && isset($_POST['yayin_saat'])) ? saat_parcala($_POST['yayin_saat']) : ['', ''];

$durum_filtre = $_GET['durum'] ?? 'tumu';
$sql = "SELECT id, ad, yer, etkinlik_tarih, etkinlik_saat, koltuk_sayisi, aciklama, aktif_mi, yayin_tarihi, koridor_sonrasi FROM etkinlikler WHERE deleted = 0";
if ($durum_filtre === 'aktif') {
    $sql .= " AND aktif_mi = 1";
} elseif ($durum_filtre === 'gizli') {
    $sql .= " AND aktif_mi = 0";
}
$sql .= " ORDER BY id ASC";
$stmt = $pdo->query($sql);
$etkinlikler = $stmt->fetchAll();

function tarih_formatla($tarih, $aylar) {
    if (!$tarih) {
        return '<span class="tarih-yok">Belirtilmemiş</span>';
    }
    $ts = strtotime($tarih);
    return date('d', $ts) . ' ' . $aylar[(int)date('n', $ts) - 1] . ' ' . date('Y', $ts);
}

function js_guvenli($str) {
    $str = str_replace(["\r\n", "\r", "\n"], '\\n', $str);
    return addslashes($str);
}

function yayin_tarihi_formatla($tarih, $aylar) {
    if (!$tarih) {
        return '<span class="tarih-yok">-</span>';
    }
    $ts = strtotime($tarih);
    return date('d', $ts) . ' ' . $aylar[(int)date('n', $ts) - 1] . ' ' . date('Y', $ts) . ', ' . date('H:i', $ts);
}

function saat_formatla($saat) {
    if (!$saat) {
        return '<span class="tarih-yok">-</span>';
    }
    return substr($saat, 0, 5);
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Admin Paneli - Etkinlikler</title>
    <link rel="stylesheet" href="../css/admin-layout.css">
    <link rel="stylesheet" href="../css/admin-etkinlikler.css">
    <link rel="stylesheet" href="../css/admin-etkinlik-form.css">
</head>
<body>

    <div class="admin-layout">
        <?php require '../includes/admin-sidebar.php'; ?>

        <div class="admin-main">
            <?php require '../includes/admin-topbar.php'; ?>

            <div class="admin-content">
                <h1>Etkinlikler</h1>

                <div class="ust-eylem-satiri">
                    <button type="button" class="btn-ekle" onclick="document.getElementById('ekle-modal').showModal()">+ Yeni Etkinlik Ekle</button>

                    <div class="durum-segment">
                        <a href="etkinlikler.php?durum=tumu" class="durum-segment-madde <?= $durum_filtre === 'tumu' ? 'segment-secili' : '' ?>">Tümü</a>
                        <a href="etkinlikler.php?durum=aktif" class="durum-segment-madde <?= $durum_filtre === 'aktif' ? 'segment-secili' : '' ?>">Aktif</a>
                        <a href="etkinlikler.php?durum=gizli" class="durum-segment-madde <?= $durum_filtre === 'gizli' ? 'segment-secili' : '' ?>">Gizli</a>
                    </div>
                </div>

                <table class="admin-tablo">
                    <thead>
                        <tr>
                            <th>Sıra No</th>
                            <th>Etkinlik Adı</th>
                            <th>Yer</th>
                            <th>Tarih</th>
                            <th>Saat</th>
                            <th>Koltuk Sayısı</th>
                            <th>Durum</th>
                            <th>Yayın Tarihi</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($etkinlikler) === 0): ?>
                            <tr>
                                <td colspan="9" class="bos-satir"><?= $durum_filtre === 'tumu' ? 'Henüz eklenmiş etkinlik yok.' : 'Bu filtreye uyan etkinlik yok.' ?></td>
                            </tr>
                        <?php else: ?>
                            <?php $sira = 1; foreach ($etkinlikler as $etkinlik): ?>
                                <tr>
                                    <td><?= $sira++ ?></td>
                                    <td><?= htmlspecialchars($etkinlik['ad']) ?></td>
                                    <td><?= htmlspecialchars($etkinlik['yer']) ?></td>
                                    <td><?= tarih_formatla($etkinlik['etkinlik_tarih'], $aylar) ?></td>
                                    <td><?= saat_formatla($etkinlik['etkinlik_saat']) ?></td>
                                    <td><?= $etkinlik['koltuk_sayisi'] !== null ? htmlspecialchars($etkinlik['koltuk_sayisi']) : '<span class="tarih-yok">-</span>' ?></td>
                                    <td><?= $etkinlik['aktif_mi'] ? '<span class="durum-rozet durum-aktif">Aktif</span>' : '<span class="durum-rozet durum-gizli">Gizli</span>' ?></td>
                                    <td><?= yayin_tarihi_formatla($etkinlik['yayin_tarihi'], $aylar) ?></td>
                                    <td class="islemler">
                                        <button type="button" class="btn-duzenle" onclick="duzenleAc(<?= (int)$etkinlik['id'] ?>, '<?= htmlspecialchars(addslashes($etkinlik['ad'])) ?>', '<?= htmlspecialchars(addslashes($etkinlik['yer'])) ?>', '<?= $etkinlik['etkinlik_tarih'] ?? '' ?>', '<?= $etkinlik['etkinlik_saat'] ? substr($etkinlik['etkinlik_saat'], 0, 5) : '' ?>', '<?= $etkinlik['koltuk_sayisi'] ?? '' ?>', '<?= htmlspecialchars(js_guvenli($etkinlik['aciklama'] ?? '')) ?>', <?= (int)$etkinlik['aktif_mi'] ?>, '<?= $etkinlik['yayin_tarihi'] ?? '' ?>', '<?= $etkinlik['koridor_sonrasi'] ?? '' ?>')">Düzenle</button>
                                        <button type="button" class="btn-sil" onclick="silOnayAc(<?= (int)$etkinlik['id'] ?>)">Sil</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Ekleme modalı -->
    <dialog id="ekle-modal" class="admin-modal">
        <h2>Yeni Etkinlik Ekle</h2>
        <p class="zorunlu-not"><span class="zorunlu-yildiz">*</span> ile işaretli alanlar zorunludur.</p>

        <p class="hata-mesaj" id="ekle-genel-hata" style="<?= ($hata !== '' && $ekle_modal_ac) ? '' : 'display:none' ?>"><?= htmlspecialchars($hata) ?></p>

        <form method="POST" action="etkinlikler.php" autocomplete="off" id="ekle-form">
            <input type="hidden" name="etkinlik_ekle" value="1">

            <label for="ekle-ad">Etkinlik Adı <span class="zorunlu-yildiz">*</span></label>
            <input type="text" id="ekle-ad" name="ad" autocomplete="off" value="<?= $ekle_modal_ac ? htmlspecialchars($_POST['ad'] ?? '') : '' ?>">
            <p class="hata-mesaj-inline" id="ekle-ad-hata" style="display:none">Bu alan zorunludur.</p>

            <label for="ekle-yer">Yer <span class="zorunlu-yildiz">*</span></label>
            <input type="text" id="ekle-yer" name="yer" autocomplete="off" value="<?= $ekle_modal_ac ? htmlspecialchars($_POST['yer'] ?? '') : '' ?>">
            <p class="hata-mesaj-inline" id="ekle-yer-hata" style="display:none">Bu alan zorunludur.</p>

            <label>Etkinlik Tarihi <span class="zorunlu-yildiz" id="ekle-tarih-yildiz">*</span></label>
            <div class="tarih-secim-grubu">
                <select id="ekle-tarih-gun"><?= gun_secenekleri($ekle_gun) ?></select>
                <div class="yil-kutu-sarmalayici">
                    <input type="text" id="ekle-tarih-ay-goster" placeholder="Ay" autocomplete="off" readonly value="<?= ($ekle_ay && isset($aylar[(int)$ekle_ay - 1])) ? $aylar[(int)$ekle_ay - 1] : '' ?>">
                    <input type="hidden" id="ekle-tarih-ay" value="<?= htmlspecialchars($ekle_ay) ?>">
                    <div class="yil-oneri-listesi" id="ekle-tarih-ay-oneri"></div>
                </div>
                <div class="yil-kutu-sarmalayici">
                    <input type="text" id="ekle-tarih-yil" placeholder="Yıl" autocomplete="off" value="<?= htmlspecialchars($ekle_yil) ?>">
                    <div class="yil-oneri-listesi" id="ekle-tarih-yil-oneri"></div>
                </div>
                <button type="button" class="btn-tarih-temizle" onclick="tarihTemizle('ekle-tarih')" title="Temizle">✕</button>
            </div>
            <input type="hidden" id="ekle-tarih" name="etkinlik_tarih" value="<?= ($ekle_modal_ac && isset($_POST['etkinlik_tarih'])) ? htmlspecialchars($_POST['etkinlik_tarih']) : '' ?>">
            <p class="hata-mesaj-inline" id="ekle-tarih-hata" style="display:none">Bu alan zorunludur.</p>

            <label>Etkinlik Saati <span class="zorunlu-yildiz" id="ekle-saat-yildiz">*</span></label>
            <div class="tarih-secim-grubu">
                <select id="ekle-saat-saat"><?= saat_secenekleri($ekle_saat_h) ?></select>
                <select id="ekle-saat-dakika"><?= dakika_secenekleri($ekle_dakika) ?></select>
                <button type="button" class="btn-tarih-temizle" onclick="saatTemizle('ekle-saat')" title="Temizle">✕</button>
            </div>
            <input type="hidden" id="ekle-saat" name="etkinlik_saat" value="<?= ($ekle_modal_ac && isset($_POST['etkinlik_saat'])) ? htmlspecialchars($_POST['etkinlik_saat']) : '' ?>">
            <p class="hata-mesaj-inline" id="ekle-saat-hata" style="display:none">Bu alan zorunludur.</p>

            <div class="ikili-alan-satiri">
                <div class="ikili-alan">
                    <label for="ekle-koltuk">Koltuk Sayısı <span class="zorunlu-yildiz" id="ekle-koltuk-yildiz">*</span></label>
                    <input type="number" id="ekle-koltuk" name="koltuk_sayisi" min="1" autocomplete="off" value="<?= $ekle_modal_ac ? htmlspecialchars($_POST['koltuk_sayisi'] ?? '') : '' ?>">
                    <p class="hata-mesaj-inline" id="ekle-koltuk-hata" style="display:none">Bu alan zorunludur.</p>
                </div>
                <div class="ikili-alan">
                    <label for="ekle-satir-basi">Sırada Kaç Koltuk? <span class="zorunlu-yildiz" id="ekle-satir-basi-yildiz">*</span></label>
                    <input type="number" id="ekle-satir-basi" name="satir_basi_koltuk" min="1" autocomplete="off" value="<?= $ekle_modal_ac ? htmlspecialchars($_POST['satir_basi_koltuk'] ?? '') : '' ?>">
                    <p class="hata-mesaj-inline" id="ekle-satir-basi-hata" style="display:none">Bu alan zorunludur.</p>
                </div>
            </div>
            <p class="yardim-notu">Kaydettiğin anda koltuklar bu bilgilere göre otomatik oluşturulur.</p>

            <label for="ekle-koridor">Koridor Kaçıncı Koltuktan Sonra Olsun?</label>
            <input type="text" id="ekle-koridor" name="koridor_sonrasi" placeholder="Örn: 3,7" autocomplete="off" value="<?= $ekle_modal_ac ? htmlspecialchars($_POST['koridor_sonrasi'] ?? '') : '' ?>">
            <p class="yardim-notu">Boş bırakırsan koridor otomatik ortaya konur. Birden fazla koridor için virgülle ayır (örn: 3,7).</p>

            <label for="ekle-aciklama">Açıklama</label>
            <textarea id="ekle-aciklama" name="aciklama" rows="3"><?= $ekle_modal_ac ? htmlspecialchars($_POST['aciklama'] ?? '') : '' ?></textarea>

            <label for="ekle-aktif">Durum</label>
            <select id="ekle-aktif" name="aktif_mi">
                <option value="1" <?= (!$ekle_modal_ac || ($_POST['aktif_mi'] ?? '1') === '1') ? 'selected' : '' ?>>Aktif</option>
                <option value="0" <?= ($ekle_modal_ac && ($_POST['aktif_mi'] ?? '1') === '0') ? 'selected' : '' ?>>Gizli</option>
            </select>

            <div id="ekle-yayin-blok">
                <label>Otomatik Yayın Tarihi</label>
                <div class="tarih-secim-grubu">
                    <select id="ekle-yayin-tarih-gun"><?= gun_secenekleri($ekle_yayin_gun) ?></select>
                    <div class="yil-kutu-sarmalayici">
                        <input type="text" id="ekle-yayin-tarih-ay-goster" placeholder="Ay" autocomplete="off" readonly value="<?= ($ekle_yayin_ay && isset($aylar[(int)$ekle_yayin_ay - 1])) ? $aylar[(int)$ekle_yayin_ay - 1] : '' ?>">
                        <input type="hidden" id="ekle-yayin-tarih-ay" value="<?= htmlspecialchars($ekle_yayin_ay) ?>">
                        <div class="yil-oneri-listesi" id="ekle-yayin-tarih-ay-oneri"></div>
                    </div>
                    <div class="yil-kutu-sarmalayici">
                        <input type="text" id="ekle-yayin-tarih-yil" placeholder="Yıl" autocomplete="off" value="<?= htmlspecialchars($ekle_yayin_yil) ?>">
                        <div class="yil-oneri-listesi" id="ekle-yayin-tarih-yil-oneri"></div>
                    </div>
                    <button type="button" class="btn-tarih-temizle" onclick="tarihTemizle('ekle-yayin-tarih')" title="Temizle">✕</button>
                </div>
                <input type="hidden" id="ekle-yayin-tarih" name="yayin_tarih" value="<?= ($ekle_modal_ac && isset($_POST['yayin_tarih'])) ? htmlspecialchars($_POST['yayin_tarih']) : '' ?>">

                <label>Otomatik Yayın Saati</label>
                <div class="tarih-secim-grubu">
                    <select id="ekle-yayin-saat-saat"><?= saat_secenekleri($ekle_yayin_saat_h) ?></select>
                    <select id="ekle-yayin-saat-dakika"><?= dakika_secenekleri($ekle_yayin_dakika) ?></select>
                    <button type="button" class="btn-tarih-temizle" onclick="saatTemizle('ekle-yayin-saat')" title="Temizle">✕</button>
                </div>
                <input type="hidden" id="ekle-yayin-saat" name="yayin_saat" value="<?= ($ekle_modal_ac && isset($_POST['yayin_saat'])) ? htmlspecialchars($_POST['yayin_saat']) : '' ?>">
                <p class="yardim-notu">Bu tarih-saat geldiğinde etkinlik, sen elle bir şey yapmadan otomatik Aktif olur.</p>
            </div>

            <div class="modal-butonlar">
                <button type="button" class="btn-vazgec" onclick="document.getElementById('ekle-modal').close()">Vazgeç</button>
                <button type="submit" class="btn-kaydet">Kaydet</button>
            </div>
        </form>
    </dialog>

    <!-- Düzenleme modalı -->
    <dialog id="duzenle-modal" class="admin-modal">
        <h2>Etkinlik Düzenle</h2>
        <p class="zorunlu-not"><span class="zorunlu-yildiz">*</span> ile işaretli alanlar zorunludur.</p>

        <p class="hata-mesaj" id="duzenle-genel-hata" style="<?= ($hata !== '' && $duzenle_modal_ac) ? '' : 'display:none' ?>"><?= htmlspecialchars($hata) ?></p>

        <form method="POST" action="etkinlikler.php" autocomplete="off" id="duzenle-form">
            <input type="hidden" name="etkinlik_duzenle" value="1">
            <input type="hidden" name="id" id="duzenle-id" value="<?= $duzenle_modal_ac ? htmlspecialchars($_POST['id'] ?? '') : '' ?>">

            <label for="duzenle-ad">Etkinlik Adı <span class="zorunlu-yildiz">*</span></label>
            <input type="text" id="duzenle-ad" name="ad" autocomplete="off" value="<?= $duzenle_modal_ac ? htmlspecialchars($_POST['ad'] ?? '') : '' ?>">
            <p class="hata-mesaj-inline" id="duzenle-ad-hata" style="display:none">Bu alan zorunludur.</p>

            <label for="duzenle-yer">Yer <span class="zorunlu-yildiz">*</span></label>
            <input type="text" id="duzenle-yer" name="yer" autocomplete="off" value="<?= $duzenle_modal_ac ? htmlspecialchars($_POST['yer'] ?? '') : '' ?>">
            <p class="hata-mesaj-inline" id="duzenle-yer-hata" style="display:none">Bu alan zorunludur.</p>

            <label>Etkinlik Tarihi <span class="zorunlu-yildiz" id="duzenle-tarih-yildiz">*</span></label>
            <div class="tarih-secim-grubu">
                <select id="duzenle-tarih-gun"><?= gun_secenekleri($duzenle_gun) ?></select>
                <div class="yil-kutu-sarmalayici">
                    <input type="text" id="duzenle-tarih-ay-goster" placeholder="Ay" autocomplete="off" readonly value="<?= ($duzenle_ay && isset($aylar[(int)$duzenle_ay - 1])) ? $aylar[(int)$duzenle_ay - 1] : '' ?>">
                    <input type="hidden" id="duzenle-tarih-ay" value="<?= htmlspecialchars($duzenle_ay) ?>">
                    <div class="yil-oneri-listesi" id="duzenle-tarih-ay-oneri"></div>
                </div>
                <div class="yil-kutu-sarmalayici">
                    <input type="text" id="duzenle-tarih-yil" placeholder="Yıl" autocomplete="off" value="<?= htmlspecialchars($duzenle_yil) ?>">
                    <div class="yil-oneri-listesi" id="duzenle-tarih-yil-oneri"></div>
                </div>
                <button type="button" class="btn-tarih-temizle" onclick="tarihTemizle('duzenle-tarih')" title="Temizle">✕</button>
            </div>
            <input type="hidden" id="duzenle-tarih" name="etkinlik_tarih" value="<?= ($duzenle_modal_ac && isset($_POST['etkinlik_tarih'])) ? htmlspecialchars($_POST['etkinlik_tarih']) : '' ?>">
            <p class="hata-mesaj-inline" id="duzenle-tarih-hata" style="display:none">Bu alan zorunludur.</p>

            <label>Etkinlik Saati <span class="zorunlu-yildiz" id="duzenle-saat-yildiz">*</span></label>
            <div class="tarih-secim-grubu">
                <select id="duzenle-saat-saat"><?= saat_secenekleri($duzenle_saat_h) ?></select>
                <select id="duzenle-saat-dakika"><?= dakika_secenekleri($duzenle_dakika) ?></select>
                <button type="button" class="btn-tarih-temizle" onclick="saatTemizle('duzenle-saat')" title="Temizle">✕</button>
            </div>
            <input type="hidden" id="duzenle-saat" name="etkinlik_saat" value="<?= ($duzenle_modal_ac && isset($_POST['etkinlik_saat'])) ? htmlspecialchars($_POST['etkinlik_saat']) : '' ?>">
            <p class="hata-mesaj-inline" id="duzenle-saat-hata" style="display:none">Bu alan zorunludur.</p>

            <label for="duzenle-koltuk">Koltuk Sayısı <span class="zorunlu-yildiz" id="duzenle-koltuk-yildiz">*</span></label>
            <input type="number" id="duzenle-koltuk" name="koltuk_sayisi" min="1" autocomplete="off" value="<?= $duzenle_modal_ac ? htmlspecialchars($_POST['koltuk_sayisi'] ?? '') : '' ?>">
            <p class="hata-mesaj-inline" id="duzenle-koltuk-hata" style="display:none">Bu alan zorunludur.</p>

            <label for="duzenle-koridor">Koridor Kaçıncı Koltuktan Sonra Olsun?</label>
            <input type="text" id="duzenle-koridor" name="koridor_sonrasi" placeholder="Örn: 3,7" autocomplete="off" value="<?= $duzenle_modal_ac ? htmlspecialchars($_POST['koridor_sonrasi'] ?? '') : '' ?>">
            <p class="yardim-notu">Boş bırakırsan koridor otomatik ortaya konur. Birden fazla koridor için virgülle ayır (örn: 3,7).</p>

            <label for="duzenle-aciklama">Açıklama</label>
            <textarea id="duzenle-aciklama" name="aciklama" rows="3"><?= $duzenle_modal_ac ? htmlspecialchars($_POST['aciklama'] ?? '') : '' ?></textarea>

            <label for="duzenle-aktif">Durum</label>
            <select id="duzenle-aktif" name="aktif_mi">
                <option value="1" <?= (!$duzenle_modal_ac || ($_POST['aktif_mi'] ?? '1') === '1') ? 'selected' : '' ?>>Aktif</option>
                <option value="0" <?= ($duzenle_modal_ac && ($_POST['aktif_mi'] ?? '1') === '0') ? 'selected' : '' ?>>Gizli</option>
            </select>

            <div id="duzenle-yayin-blok">
                <label>Otomatik Yayın Tarihi</label>
                <div class="tarih-secim-grubu">
                    <select id="duzenle-yayin-tarih-gun"><?= gun_secenekleri($duzenle_yayin_gun) ?></select>
                    <div class="yil-kutu-sarmalayici">
                        <input type="text" id="duzenle-yayin-tarih-ay-goster" placeholder="Ay" autocomplete="off" readonly value="<?= ($duzenle_yayin_ay && isset($aylar[(int)$duzenle_yayin_ay - 1])) ? $aylar[(int)$duzenle_yayin_ay - 1] : '' ?>">
                        <input type="hidden" id="duzenle-yayin-tarih-ay" value="<?= htmlspecialchars($duzenle_yayin_ay) ?>">
                        <div class="yil-oneri-listesi" id="duzenle-yayin-tarih-ay-oneri"></div>
                    </div>
                    <div class="yil-kutu-sarmalayici">
                        <input type="text" id="duzenle-yayin-tarih-yil" placeholder="Yıl" autocomplete="off" value="<?= htmlspecialchars($duzenle_yayin_yil) ?>">
                        <div class="yil-oneri-listesi" id="duzenle-yayin-tarih-yil-oneri"></div>
                    </div>
                    <button type="button" class="btn-tarih-temizle" onclick="tarihTemizle('duzenle-yayin-tarih')" title="Temizle">✕</button>
                </div>
                <input type="hidden" id="duzenle-yayin-tarih" name="yayin_tarih" value="<?= ($duzenle_modal_ac && isset($_POST['yayin_tarih'])) ? htmlspecialchars($_POST['yayin_tarih']) : '' ?>">

                <label>Otomatik Yayın Saati</label>
                <div class="tarih-secim-grubu">
                    <select id="duzenle-yayin-saat-saat"><?= saat_secenekleri($duzenle_yayin_saat_h) ?></select>
                    <select id="duzenle-yayin-saat-dakika"><?= dakika_secenekleri($duzenle_yayin_dakika) ?></select>
                    <button type="button" class="btn-tarih-temizle" onclick="saatTemizle('duzenle-yayin-saat')" title="Temizle">✕</button>
                </div>
                <input type="hidden" id="duzenle-yayin-saat" name="yayin_saat" value="<?= ($duzenle_modal_ac && isset($_POST['yayin_saat'])) ? htmlspecialchars($_POST['yayin_saat']) : '' ?>">
                <p class="yardim-notu">Bu tarih-saat geldiğinde etkinlik, sen elle bir şey yapmadan otomatik Aktif olur.</p>
            </div>

            <div class="modal-butonlar">
                <button type="button" class="btn-vazgec" onclick="document.getElementById('duzenle-modal').close()">Vazgeç</button>
                <button type="submit" class="btn-kaydet">Güncelle</button>
            </div>
        </form>
    </dialog>

    <!-- Silme onay modalı -->
    <dialog id="sil-onay-modal" class="admin-modal admin-modal-kucuk admin-modal-ortali">
        <div class="uyari-ikon">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                <line x1="12" y1="9" x2="12" y2="13"></line>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
        </div>
        <h2>Etkinliği Sil</h2>
        <p class="onay-mesaji">Bu etkinliği silmek istediğinize emin misiniz? Bu işlem geri alınamaz.</p>
        <input type="hidden" id="sil-id">
        <div class="modal-butonlar">
            <button type="button" class="btn-vazgec" onclick="document.getElementById('sil-onay-modal').close()">Vazgeç</button>
            <button type="button" class="btn-sil-onayla" onclick="silOnayla()">Evet, Sil</button>
        </div>
    </dialog>

    <script>
        var ekleModalAcMi = <?= $ekle_modal_ac ? 'true' : 'false' ?>;
        var duzenleModalAcMi = <?= $duzenle_modal_ac ? 'true' : 'false' ?>;

        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                document.querySelectorAll('dialog[open]').forEach(function (d) {
                    d.close();
                });
            }
        });

        function silOnayAc(id) {
            document.getElementById('sil-id').value = id;
            document.getElementById('sil-onay-modal').showModal();
        }

        function silOnayla() {
            var id = document.getElementById('sil-id').value;
            window.location.href = 'etkinlik-sil.php?id=' + id;
        }

        function tarihBirlestir(prefix) {
            var gun = document.getElementById(prefix + '-gun').value;
            var ay = document.getElementById(prefix + '-ay').value;
            var yil = document.getElementById(prefix + '-yil').value;
            var gizli = document.getElementById(prefix.replace('-tarih', '') + '-tarih') || document.getElementById(prefix);
            var hata = document.getElementById(prefix + '-hata') || document.getElementById(prefix.replace('-tarih', '') + '-tarih-hata');
            if (gun && ay && yil && yil.length === 4) {
                gizli.value = yil + '-' + ay + '-' + gun;
                if (hata) hata.style.display = 'none';
                document.getElementById(prefix + '-gun').classList.remove('input-hata');
                document.getElementById(prefix + '-ay-goster').classList.remove('input-hata');
                document.getElementById(prefix + '-yil').classList.remove('input-hata');
            } else {
                gizli.value = '';
            }
        }

        function tarihTemizle(prefix) {
            document.getElementById(prefix + '-gun').value = '';
            document.getElementById(prefix + '-ay').value = '';
            document.getElementById(prefix + '-yil').value = '';
            var gizliId = prefix.substring(0, prefix.lastIndexOf('-tarih')) + '-tarih';
            var gizli = document.getElementById(gizliId);
            if (gizli) gizli.value = '';
        }

        function saatBirlestir(prefix) {
            var saat = document.getElementById(prefix + '-saat').value;
            var dakika = document.getElementById(prefix + '-dakika').value;
            var gizli = document.getElementById(prefix);
            var hata = document.getElementById(prefix + '-hata');
            if (saat && dakika) {
                gizli.value = saat + ':' + dakika;
                if (hata) hata.style.display = 'none';
                document.getElementById(prefix + '-saat').classList.remove('input-hata');
                document.getElementById(prefix + '-dakika').classList.remove('input-hata');
            } else {
                gizli.value = '';
            }
        }

        function saatTemizle(prefix) {
            document.getElementById(prefix + '-saat').value = '';
            document.getElementById(prefix + '-dakika').value = '';
            var gizli = document.getElementById(prefix);
            if (gizli) gizli.value = '';
        }

        ['ekle-tarih', 'duzenle-tarih', 'ekle-yayin-tarih', 'duzenle-yayin-tarih'].forEach(function (prefix) {
            document.getElementById(prefix + '-gun').addEventListener('change', function () {
                tarihBirlestir(prefix);
            });
        });

        ['ekle-saat', 'duzenle-saat', 'ekle-yayin-saat', 'duzenle-yayin-saat'].forEach(function (prefix) {
            ['saat', 'dakika'].forEach(function (parca) {
                document.getElementById(prefix + '-' + parca).addEventListener('change', function () {
                    saatBirlestir(prefix);
                });
            });
        });

        function yilOneriBagla(prefix) {
            var input = document.getElementById(prefix + '-yil');
            var liste = document.getElementById(prefix + '-yil-oneri');
            var buYil = new Date().getFullYear();
            var tumYillar = [];
            for (var y = buYil + 5; y >= buYil - 1; y--) {
                tumYillar.push(String(y));
            }

            function listeyiCiz(filtre) {
                var eslesenler = tumYillar.filter(function (yil) {
                    return filtre === '' || yil.indexOf(filtre) === 0;
                });
                liste.innerHTML = '';

                var bosMadde = document.createElement('div');
                bosMadde.className = 'yil-oneri-madde';
                bosMadde.textContent = 'Yıl';
                bosMadde.style.color = '#999';
                bosMadde.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    input.value = '';
                    liste.style.display = 'none';
                    tarihBirlestir(prefix);
                });
                liste.appendChild(bosMadde);

                if (eslesenler.length === 0) {
                    liste.style.display = 'block';
                    return;
                }
                eslesenler.forEach(function (yil) {
                    var madde = document.createElement('div');
                    madde.className = 'yil-oneri-madde';
                    madde.textContent = yil;
                    madde.addEventListener('mousedown', function (e) {
                        e.preventDefault();
                        input.value = yil;
                        liste.style.display = 'none';
                        tarihBirlestir(prefix);
                    });
                    liste.appendChild(madde);
                });
                liste.style.display = 'block';
            }

            input.addEventListener('input', function () {
                input.value = input.value.replace(/[^0-9]/g, '').slice(0, 4);
                tarihBirlestir(prefix);
                listeyiCiz(input.value);
            });

            input.addEventListener('focus', function () {
                listeyiCiz('');
            });

            liste.addEventListener('mousedown', function (e) {
                e.preventDefault();
            });

            document.addEventListener('click', function (e) {
                if (e.target !== input && !liste.contains(e.target)) {
                    liste.style.display = 'none';
                }
            });
        }

        var aylarListesi = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];

        function aySecimBagla(prefix) {
            var goster = document.getElementById(prefix + '-ay-goster');
            var kodGizli = document.getElementById(prefix + '-ay');
            var liste = document.getElementById(prefix + '-ay-oneri');

            function listeyiCiz(filtre) {
                liste.innerHTML = '';

                var bosMadde = document.createElement('div');
                bosMadde.className = 'yil-oneri-madde';
                bosMadde.textContent = 'Ay';
                bosMadde.style.color = '#999';
                bosMadde.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    kodGizli.value = '';
                    goster.value = '';
                    liste.style.display = 'none';
                    tarihBirlestir(prefix);
                });
                liste.appendChild(bosMadde);

                aylarListesi.forEach(function (ad, index) {
                    var kod = String(index + 1).padStart(2, '0');
                    if (filtre !== '' && kod.indexOf(filtre) !== 0) return;
                    var madde = document.createElement('div');
                    madde.className = 'yil-oneri-madde';
                    madde.textContent = ad;
                    madde.addEventListener('mousedown', function (e) {
                        e.preventDefault();
                        kodGizli.value = kod;
                        goster.value = ad;
                        liste.style.display = 'none';
                        tarihBirlestir(prefix);
                    });
                    liste.appendChild(madde);
                });
                liste.style.display = 'block';
            }

            goster.removeAttribute('readonly');
            goster.addEventListener('input', function () {
                var rakam = goster.value.replace(/[^0-9]/g, '');
                listeyiCiz(rakam);
            });

            goster.addEventListener('focus', function () {
                listeyiCiz('');
            });

            liste.addEventListener('mousedown', function (e) {
                e.preventDefault();
            });

            document.addEventListener('click', function (e) {
                if (e.target !== goster && !liste.contains(e.target)) {
                    liste.style.display = 'none';
                }
            });
        }

        function aySayiGirisiBagla(selectId) {
            var select = document.getElementById(selectId);
            var arabellek = '';
            var zamanlayici = null;
            select.addEventListener('keydown', function (e) {
                if (e.key >= '0' && e.key <= '9') {
                    e.preventDefault();
                    arabellek += e.key;
                    clearTimeout(zamanlayici);
                    zamanlayici = setTimeout(function () { arabellek = ''; }, 700);
                    var sayi = parseInt(arabellek, 10);
                    if (sayi >= 1 && sayi <= 12) {
                        select.value = String(sayi).padStart(2, '0');
                        select.dispatchEvent(new Event('change'));
                    } else {
                        arabellek = e.key;
                        sayi = parseInt(arabellek, 10);
                        if (sayi >= 1 && sayi <= 12) {
                            select.value = String(sayi).padStart(2, '0');
                            select.dispatchEvent(new Event('change'));
                        }
                    }
                }
            });
        }

        function bosSecimGriBoya(selectId) {
            var sel = document.getElementById(selectId);
            function guncelle() {
                sel.style.color = sel.value === '' ? '#999' : '#222';
            }
            sel.addEventListener('change', guncelle);
            guncelle();
        }

        ['ekle-tarih-gun', 'duzenle-tarih-gun', 'ekle-saat-saat', 'ekle-saat-dakika', 'duzenle-saat-saat', 'duzenle-saat-dakika', 'ekle-yayin-tarih-gun', 'duzenle-yayin-tarih-gun', 'ekle-yayin-saat-saat', 'ekle-yayin-saat-dakika', 'duzenle-yayin-saat-saat', 'duzenle-yayin-saat-dakika'].forEach(bosSecimGriBoya);

        yilOneriBagla('ekle-tarih');
        yilOneriBagla('duzenle-tarih');
        aySecimBagla('ekle-tarih');
        aySecimBagla('duzenle-tarih');
        yilOneriBagla('ekle-yayin-tarih');
        yilOneriBagla('duzenle-yayin-tarih');
        aySecimBagla('ekle-yayin-tarih');
        aySecimBagla('duzenle-yayin-tarih');

        function duzenleAc(id, ad, yer, tarih, saat, koltukSayisi, aciklama, aktifMi, yayinTarihi, koridorSonrasi) {
            document.getElementById('duzenle-koridor').value = koridorSonrasi || '';
            document.getElementById('duzenle-id').value = id;
            document.getElementById('duzenle-ad').value = ad;
            document.getElementById('duzenle-yer').value = yer;
            document.getElementById('duzenle-koltuk').value = koltukSayisi;
            document.getElementById('duzenle-aciklama').value = aciklama.replace(/\\n/g, '\n');
            document.getElementById('duzenle-aktif').value = aktifMi === 1 ? '1' : '0';
            document.getElementById('duzenle-aktif').dispatchEvent(new Event('change'));

            if (yayinTarihi) {
                var yp = yayinTarihi.split(' ');
                var ytp = yp[0].split('-');
                document.getElementById('duzenle-yayin-tarih-yil').value = ytp[0];
                document.getElementById('duzenle-yayin-tarih-ay').value = ytp[1];
                document.getElementById('duzenle-yayin-tarih-ay-goster').value = ytp[1] ? aylarListesi[parseInt(ytp[1], 10) - 1] : '';
                document.getElementById('duzenle-yayin-tarih-gun').value = ytp[2];
                if (yp[1]) {
                    var ysp = yp[1].split(':');
                    document.getElementById('duzenle-yayin-saat-saat').value = ysp[0];
                    document.getElementById('duzenle-yayin-saat-dakika').value = ysp[1];
                }
            } else {
                document.getElementById('duzenle-yayin-tarih-yil').value = '';
                document.getElementById('duzenle-yayin-tarih-ay').value = '';
                document.getElementById('duzenle-yayin-tarih-ay-goster').value = '';
                document.getElementById('duzenle-yayin-tarih-gun').value = '';
                document.getElementById('duzenle-yayin-saat-saat').value = '';
                document.getElementById('duzenle-yayin-saat-dakika').value = '';
            }
            tarihBirlestir('duzenle-yayin-tarih');
            saatBirlestir('duzenle-yayin-saat');
            ['duzenle-yayin-tarih-gun', 'duzenle-yayin-saat-saat', 'duzenle-yayin-saat-dakika'].forEach(function (id) {
                document.getElementById(id).dispatchEvent(new Event('change'));
            });

            document.getElementById('duzenle-tarih').value = tarih;
            if (tarih) {
                var tp = tarih.split('-');
                document.getElementById('duzenle-tarih-yil').value = tp[0];
                document.getElementById('duzenle-tarih-ay').value = tp[1];
                document.getElementById('duzenle-tarih-ay-goster').value = tp[1] ? aylarListesi[parseInt(tp[1], 10) - 1] : '';
                document.getElementById('duzenle-tarih-gun').value = tp[2];
            } else {
                document.getElementById('duzenle-tarih-yil').value = '';
                document.getElementById('duzenle-tarih-ay').value = '';
                document.getElementById('duzenle-tarih-ay-goster').value = '';
                document.getElementById('duzenle-tarih-gun').value = '';
            }

            document.getElementById('duzenle-saat').value = saat;
            if (saat) {
                var sp = saat.split(':');
                document.getElementById('duzenle-saat-saat').value = sp[0];
                document.getElementById('duzenle-saat-dakika').value = sp[1];
            } else {
                document.getElementById('duzenle-saat-saat').value = '';
                document.getElementById('duzenle-saat-dakika').value = '';
            }

            tarihBirlestir('duzenle-tarih');
            saatBirlestir('duzenle-saat');

            ['duzenle-tarih-gun', 'duzenle-saat-saat', 'duzenle-saat-dakika'].forEach(function (id) {
                document.getElementById(id).dispatchEvent(new Event('change'));
            });

            document.getElementById('duzenle-modal').showModal();
        }

        document.getElementById('sil-onay-modal').addEventListener('click', function (e) {
            if (e.target === this) {
                this.close();
            }
        });

        function modalTemizle(modal) {
            var form = modal.querySelector('form');
            if (form) form.reset();
            modal.querySelectorAll('input[type="text"], input[type="number"], input[type="date"], input[type="time"]').forEach(function (el) {
                el.value = '';
            });
            modal.querySelectorAll('select').forEach(function (sel) {
                sel.value = '';
                sel.dispatchEvent(new Event('change'));
            });
            modal.querySelectorAll('input[type="hidden"][id$="-tarih"], input[type="hidden"][id$="-saat"], input[type="hidden"][id$="-ay"]').forEach(function (el) {
                el.value = '';
            });
            modal.querySelectorAll('input.input-hata, select.input-hata').forEach(function (el) {
                el.classList.remove('input-hata');
            });
            modal.querySelectorAll('.hata-mesaj-inline').forEach(function (p) {
                p.style.display = 'none';
            });
            var hataKutu = modal.querySelector('.hata-mesaj');
            if (hataKutu) hataKutu.style.display = 'none';
        }

        document.getElementById('ekle-modal').addEventListener('close', function () {
            modalTemizle(this);
        });

        document.getElementById('duzenle-modal').addEventListener('close', function () {
            modalTemizle(this);
        });

        function zorunluAlanlariBagla(formId, alanIdleri) {
            var form = document.getElementById(formId);
            alanIdleri.forEach(function (alanId) {
                var input = document.getElementById(alanId);
                var hata = document.getElementById(alanId + '-hata');
                var ozelGrup = input.type === 'hidden' && (alanId.indexOf('-tarih') !== -1 || alanId.indexOf('-saat') !== -1);
                if (!ozelGrup) {
                    input.addEventListener('input', function () {
                        if (input.value.trim() !== '') {
                            input.classList.remove('input-hata');
                            if (hata) hata.style.display = 'none';
                        }
                    });
                }
            });
            form.addEventListener('submit', function (e) {
                var eksikVar = false;
                alanIdleri.forEach(function (alanId) {
                    var input = document.getElementById(alanId);
                    var hata = document.getElementById(alanId + '-hata');
                    if (input.value.trim() === '') {
                        if (input.type === 'hidden' && alanId.indexOf('-tarih') !== -1) {
                            document.getElementById(alanId + '-gun').classList.add('input-hata');
                            document.getElementById(alanId + '-ay').classList.add('input-hata');
                            document.getElementById(alanId + '-yil').classList.add('input-hata');
                        } else if (input.type === 'hidden' && alanId.indexOf('-saat') !== -1) {
                            document.getElementById(alanId + '-saat').classList.add('input-hata');
                            document.getElementById(alanId + '-dakika').classList.add('input-hata');
                        } else {
                            input.classList.add('input-hata');
                        }
                        if (hata) {
                            hata.textContent = 'Bu alan zorunludur.';
                            hata.style.display = 'block';
                        }
                        eksikVar = true;
                    }
                });
                if (eksikVar) {
                    e.preventDefault();
                }
            });
        }

        zorunluAlanlariBagla('ekle-form', ['ekle-ad', 'ekle-yer']);
        zorunluAlanlariBagla('duzenle-form', ['duzenle-ad', 'duzenle-yer']);

        function aktifTarihSaatKontrolBagla(formId, prefix) {
            var form = document.getElementById(formId);
            var aktifSecim = document.getElementById(prefix + '-aktif');

            function zorunluMu() {
                var yayinTarihGizli = document.getElementById(prefix + '-yayin-tarih');
                var yayinDoldurulmus = yayinTarihGizli && yayinTarihGizli.value.trim() !== '';
                return aktifSecim.value === '1' || yayinDoldurulmus;
            }

            function yildizlariGuncelle() {
                var goster = zorunluMu();
                [prefix + '-tarih-yildiz', prefix + '-saat-yildiz', prefix + '-koltuk-yildiz', prefix + '-satir-basi-yildiz'].forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el) el.style.display = goster ? 'inline' : 'none';
                });
            }

            aktifSecim.addEventListener('change', function () {
                yildizlariGuncelle();
                if (!zorunluMu()) {
                    [prefix + '-tarih-gun', prefix + '-tarih-ay-goster', prefix + '-tarih-yil', prefix + '-saat-saat', prefix + '-saat-dakika', prefix + '-koltuk', prefix + '-satir-basi'].forEach(function (id) {
                        var el = document.getElementById(id);
                        if (el) el.classList.remove('input-hata');
                    });
                    [prefix + '-tarih-hata', prefix + '-saat-hata', prefix + '-koltuk-hata', prefix + '-satir-basi-hata'].forEach(function (id) {
                        var el = document.getElementById(id);
                        if (el) el.style.display = 'none';
                    });
                }
            });

            yildizlariGuncelle();

            form.addEventListener('submit', function (e) {
                if (!zorunluMu()) return;

                var eksikVar = false;

                var tarihGizli = document.getElementById(prefix + '-tarih');
                if (tarihGizli.value.trim() === '') {
                    document.getElementById(prefix + '-tarih-gun').classList.add('input-hata');
                    document.getElementById(prefix + '-tarih-ay-goster').classList.add('input-hata');
                    document.getElementById(prefix + '-tarih-yil').classList.add('input-hata');
                    var tarihHata = document.getElementById(prefix + '-tarih-hata');
                    if (tarihHata) {
                        tarihHata.textContent = 'Bu durumda tarih gerekli.';
                        tarihHata.style.display = 'block';
                    }
                    eksikVar = true;
                }

                var saatGizli = document.getElementById(prefix + '-saat');
                if (saatGizli.value.trim() === '') {
                    document.getElementById(prefix + '-saat-saat').classList.add('input-hata');
                    document.getElementById(prefix + '-saat-dakika').classList.add('input-hata');
                    var saatHata = document.getElementById(prefix + '-saat-hata');
                    if (saatHata) {
                        saatHata.textContent = 'Bu durumda saat gerekli.';
                        saatHata.style.display = 'block';
                    }
                    eksikVar = true;
                }

                var koltukAlan = document.getElementById(prefix + '-koltuk');
                if (koltukAlan && koltukAlan.value.trim() === '') {
                    koltukAlan.classList.add('input-hata');
                    var koltukHata = document.getElementById(prefix + '-koltuk-hata');
                    if (koltukHata) {
                        koltukHata.textContent = 'Bu durumda koltuk sayısı gerekli.';
                        koltukHata.style.display = 'block';
                    }
                    eksikVar = true;
                }

                var satirBasiAlan = document.getElementById(prefix + '-satir-basi');
                if (satirBasiAlan && satirBasiAlan.value.trim() === '') {
                    satirBasiAlan.classList.add('input-hata');
                    var satirBasiHata = document.getElementById(prefix + '-satir-basi-hata');
                    if (satirBasiHata) {
                        satirBasiHata.textContent = 'Bu durumda bu alan gerekli.';
                        satirBasiHata.style.display = 'block';
                    }
                    eksikVar = true;
                }

                if (eksikVar) {
                    e.preventDefault();
                }
            });
        }

        function yayinBlokBagla(prefix) {
            var secim = document.getElementById(prefix + '-aktif');
            var blok = document.getElementById(prefix + '-yayin-blok');
            function guncelle() {
                blok.style.display = secim.value === '1' ? 'none' : 'block';
            }
            secim.addEventListener('change', guncelle);
            guncelle();
        }

        yayinBlokBagla('ekle');
        yayinBlokBagla('duzenle');

        aktifTarihSaatKontrolBagla('ekle-form', 'ekle');
        aktifTarihSaatKontrolBagla('duzenle-form', 'duzenle');

        function enterSirayaBagla(formId, alanSirasi) {
            var form = document.getElementById(formId);
            alanSirasi.forEach(function (alanId, index) {
                var alan = document.getElementById(alanId);
                alan.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (index < alanSirasi.length - 1) {
                            document.getElementById(alanSirasi[index + 1]).focus();
                        } else {
                            form.requestSubmit();
                        }
                    }
                });
            });
        }

        enterSirayaBagla('ekle-form', ['ekle-ad', 'ekle-yer', 'ekle-tarih-gun', 'ekle-tarih-ay-goster', 'ekle-tarih-yil', 'ekle-saat-saat', 'ekle-saat-dakika', 'ekle-koltuk', 'ekle-satir-basi']);
        enterSirayaBagla('duzenle-form', ['duzenle-ad', 'duzenle-yer', 'duzenle-tarih-gun', 'duzenle-tarih-ay-goster', 'duzenle-tarih-yil', 'duzenle-saat-saat', 'duzenle-saat-dakika', 'duzenle-koltuk']);

        if (ekleModalAcMi) {
            document.getElementById('ekle-modal').showModal();
        }
        if (duzenleModalAcMi) {
            document.getElementById('duzenle-modal').showModal();
        }
    </script>

</body>
</html>