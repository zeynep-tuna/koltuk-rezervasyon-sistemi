<?php
require '../includes/db.php';

$hata = '';
$ekle_modal_ac = false;
$duzenle_modal_ac = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['etkinlik_ekle'])) {
    $ad = trim($_POST['ad'] ?? '');
    $yer = trim($_POST['yer'] ?? '');
    $etkinlik_tarih = trim($_POST['etkinlik_tarih'] ?? '');
    $etkinlik_saat = trim($_POST['etkinlik_saat'] ?? '');
    $koltuk_sayisi = trim($_POST['koltuk_sayisi'] ?? '');

    if ($ad === '' || $yer === '' || $etkinlik_tarih === '' || $etkinlik_saat === '' || $koltuk_sayisi === '') {
        $hata = 'Lütfen zorunlu alanları doldurun.';
        $ekle_modal_ac = true;
    } elseif (!ctype_digit($koltuk_sayisi) || (int)$koltuk_sayisi <= 0) {
        $hata = 'Koltuk sayısı pozitif bir tam sayı olmalıdır.';
        $ekle_modal_ac = true;
    } else {
        $stmt = $pdo->prepare("INSERT INTO etkinlikler (ad, yer, etkinlik_tarih, etkinlik_saat, koltuk_sayisi) VALUES (:ad, :yer, :etkinlik_tarih, :etkinlik_saat, :koltuk_sayisi)");
        $stmt->execute([
            'ad' => $ad,
            'yer' => $yer,
            'etkinlik_tarih' => $etkinlik_tarih,
            'etkinlik_saat' => $etkinlik_saat,
            'koltuk_sayisi' => (int)$koltuk_sayisi,
        ]);
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

    if ($ad === '' || $yer === '' || $etkinlik_tarih === '' || $etkinlik_saat === '' || $koltuk_sayisi === '') {
        $hata = 'Lütfen zorunlu alanları doldurun.';
        $duzenle_modal_ac = true;
    } elseif (!ctype_digit($koltuk_sayisi) || (int)$koltuk_sayisi <= 0) {
        $hata = 'Koltuk sayısı pozitif bir tam sayı olmalıdır.';
        $duzenle_modal_ac = true;
    } elseif (!ctype_digit((string)$id)) {
        $hata = 'Geçersiz etkinlik.';
        $duzenle_modal_ac = true;
    } else {
        $stmt = $pdo->prepare("UPDATE etkinlikler SET ad = :ad, yer = :yer, etkinlik_tarih = :etkinlik_tarih, etkinlik_saat = :etkinlik_saat, koltuk_sayisi = :koltuk_sayisi WHERE id = :id");
        $stmt->execute([
            'ad' => $ad,
            'yer' => $yer,
            'etkinlik_tarih' => $etkinlik_tarih,
            'etkinlik_saat' => $etkinlik_saat,
            'koltuk_sayisi' => (int)$koltuk_sayisi,
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

$stmt = $pdo->query("SELECT id, ad, yer, etkinlik_tarih, etkinlik_saat, koltuk_sayisi FROM etkinlikler WHERE deleted = 0 ORDER BY id ASC");
$etkinlikler = $stmt->fetchAll();

function tarih_formatla($tarih, $aylar) {
    if (!$tarih) {
        return '<span class="tarih-yok">Belirtilmemiş</span>';
    }
    $ts = strtotime($tarih);
    return date('d', $ts) . ' ' . $aylar[(int)date('n', $ts) - 1] . ' ' . date('Y', $ts);
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

                <button type="button" class="btn-ekle" onclick="document.getElementById('ekle-modal').showModal()">+ Yeni Etkinlik Ekle</button>

                <table class="admin-tablo">
                    <thead>
                        <tr>
                            <th>Sıra No</th>
                            <th>Etkinlik Adı</th>
                            <th>Yer</th>
                            <th>Tarih</th>
                            <th>Saat</th>
                            <th>Koltuk Sayısı</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($etkinlikler) === 0): ?>
                            <tr>
                                <td colspan="7" class="bos-satir">Henüz eklenmiş etkinlik yok.</td>
                            </tr>
                        <?php else: ?>
                            <?php $sira = 1; foreach ($etkinlikler as $etkinlik): ?>
                                <tr>
                                    <td><?= $sira++ ?></td>
                                    <td><?= htmlspecialchars($etkinlik['ad']) ?></td>
                                    <td><?= htmlspecialchars($etkinlik['yer']) ?></td>
                                    <td><?= tarih_formatla($etkinlik['etkinlik_tarih'], $aylar) ?></td>
                                    <td><?= saat_formatla($etkinlik['etkinlik_saat']) ?></td>
                                    <td><?= htmlspecialchars($etkinlik['koltuk_sayisi']) ?></td>
                                    <td class="islemler">
                                        <button type="button" class="btn-duzenle" onclick="duzenleAc(<?= (int)$etkinlik['id'] ?>, '<?= htmlspecialchars(addslashes($etkinlik['ad'])) ?>', '<?= htmlspecialchars(addslashes($etkinlik['yer'])) ?>', '<?= $etkinlik['etkinlik_tarih'] ?? '' ?>', '<?= $etkinlik['etkinlik_saat'] ? substr($etkinlik['etkinlik_saat'], 0, 5) : '' ?>', <?= (int)$etkinlik['koltuk_sayisi'] ?>)">Düzenle</button>
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

            <label>Etkinlik Tarihi <span class="zorunlu-yildiz">*</span></label>
            <div class="tarih-secim-grubu">
                <select id="ekle-tarih-gun"><?= gun_secenekleri($ekle_gun) ?></select>
                <select id="ekle-tarih-ay"><?= ay_secenekleri($aylar, $ekle_ay) ?></select>
                <div class="yil-kutu-sarmalayici">
                    <input type="text" id="ekle-tarih-yil" placeholder="Yıl" autocomplete="off" value="<?= htmlspecialchars($ekle_yil) ?>">
                    <div class="yil-oneri-listesi" id="ekle-tarih-yil-oneri"></div>
                </div>
                <button type="button" class="btn-tarih-temizle" onclick="tarihTemizle('ekle-tarih')" title="Temizle">✕</button>
            </div>
            <input type="hidden" id="ekle-tarih" name="etkinlik_tarih" value="<?= ($ekle_modal_ac && isset($_POST['etkinlik_tarih'])) ? htmlspecialchars($_POST['etkinlik_tarih']) : '' ?>">
            <p class="hata-mesaj-inline" id="ekle-tarih-hata" style="display:none">Bu alan zorunludur.</p>

            <label>Etkinlik Saati <span class="zorunlu-yildiz">*</span></label>
            <div class="tarih-secim-grubu">
                <select id="ekle-saat-saat"><?= saat_secenekleri($ekle_saat_h) ?></select>
                <select id="ekle-saat-dakika"><?= dakika_secenekleri($ekle_dakika) ?></select>
                <button type="button" class="btn-tarih-temizle" onclick="saatTemizle('ekle-saat')" title="Temizle">✕</button>
            </div>
            <input type="hidden" id="ekle-saat" name="etkinlik_saat" value="<?= ($ekle_modal_ac && isset($_POST['etkinlik_saat'])) ? htmlspecialchars($_POST['etkinlik_saat']) : '' ?>">
            <p class="hata-mesaj-inline" id="ekle-saat-hata" style="display:none">Bu alan zorunludur.</p>

            <label for="ekle-koltuk">Koltuk Sayısı <span class="zorunlu-yildiz">*</span></label>
            <input type="number" id="ekle-koltuk" name="koltuk_sayisi" min="1" autocomplete="off" value="<?= $ekle_modal_ac ? htmlspecialchars($_POST['koltuk_sayisi'] ?? '') : '' ?>">
            <p class="hata-mesaj-inline" id="ekle-koltuk-hata" style="display:none">Bu alan zorunludur.</p>

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

            <label>Etkinlik Tarihi <span class="zorunlu-yildiz">*</span></label>
            <div class="tarih-secim-grubu">
                <select id="duzenle-tarih-gun"><?= gun_secenekleri($duzenle_gun) ?></select>
                <select id="duzenle-tarih-ay"><?= ay_secenekleri($aylar, $duzenle_ay) ?></select>
                <div class="yil-kutu-sarmalayici">
                    <input type="text" id="duzenle-tarih-yil" placeholder="Yıl" autocomplete="off" value="<?= htmlspecialchars($duzenle_yil) ?>">
                    <div class="yil-oneri-listesi" id="duzenle-tarih-yil-oneri"></div>
                </div>
                <button type="button" class="btn-tarih-temizle" onclick="tarihTemizle('duzenle-tarih')" title="Temizle">✕</button>
            </div>
            <input type="hidden" id="duzenle-tarih" name="etkinlik_tarih" value="<?= ($duzenle_modal_ac && isset($_POST['etkinlik_tarih'])) ? htmlspecialchars($_POST['etkinlik_tarih']) : '' ?>">
            <p class="hata-mesaj-inline" id="duzenle-tarih-hata" style="display:none">Bu alan zorunludur.</p>

            <label>Etkinlik Saati <span class="zorunlu-yildiz">*</span></label>
            <div class="tarih-secim-grubu">
                <select id="duzenle-saat-saat"><?= saat_secenekleri($duzenle_saat_h) ?></select>
                <select id="duzenle-saat-dakika"><?= dakika_secenekleri($duzenle_dakika) ?></select>
                <button type="button" class="btn-tarih-temizle" onclick="saatTemizle('duzenle-saat')" title="Temizle">✕</button>
            </div>
            <input type="hidden" id="duzenle-saat" name="etkinlik_saat" value="<?= ($duzenle_modal_ac && isset($_POST['etkinlik_saat'])) ? htmlspecialchars($_POST['etkinlik_saat']) : '' ?>">
            <p class="hata-mesaj-inline" id="duzenle-saat-hata" style="display:none">Bu alan zorunludur.</p>

            <label for="duzenle-koltuk">Koltuk Sayısı <span class="zorunlu-yildiz">*</span></label>
            <input type="number" id="duzenle-koltuk" name="koltuk_sayisi" min="1" autocomplete="off" value="<?= $duzenle_modal_ac ? htmlspecialchars($_POST['koltuk_sayisi'] ?? '') : '' ?>">
            <p class="hata-mesaj-inline" id="duzenle-koltuk-hata" style="display:none">Bu alan zorunludur.</p>

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
                document.getElementById(prefix + '-ay').classList.remove('input-hata');
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
            var gizliId = prefix.substring(0, prefix.lastIndexOf('-saat'));
            var gizli = document.getElementById(gizliId);
            var hata = document.getElementById(gizliId + '-hata');
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
            var gizliId = prefix.substring(0, prefix.lastIndexOf('-saat'));
            var gizli = document.getElementById(gizliId);
            if (gizli) gizli.value = '';
        }

        ['ekle-tarih', 'duzenle-tarih'].forEach(function (prefix) {
            ['gun', 'ay'].forEach(function (parca) {
                document.getElementById(prefix + '-' + parca).addEventListener('change', function () {
                    tarihBirlestir(prefix);
                });
            });
        });

        ['ekle-saat', 'duzenle-saat'].forEach(function (prefix) {
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
            for (var y = buYil; y >= 1920; y--) {
                tumYillar.push(String(y));
            }

            function listeyiCiz(filtre) {
                var eslesenler = tumYillar.filter(function (yil) {
                    return filtre === '' || yil.indexOf(filtre) === 0;
                }).slice(0, 8);
                liste.innerHTML = '';
                if (eslesenler.length === 0) {
                    liste.style.display = 'none';
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
                listeyiCiz(input.value);
            });

            input.addEventListener('blur', function () {
                setTimeout(function () {
                    liste.style.display = 'none';
                }, 150);
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

        yilOneriBagla('ekle-tarih');
        yilOneriBagla('duzenle-tarih');
        aySayiGirisiBagla('ekle-tarih-ay');
        aySayiGirisiBagla('duzenle-tarih-ay');

        function duzenleAc(id, ad, yer, tarih, saat, koltukSayisi) {
            document.getElementById('duzenle-id').value = id;
            document.getElementById('duzenle-ad').value = ad;
            document.getElementById('duzenle-yer').value = yer;
            document.getElementById('duzenle-koltuk').value = koltukSayisi;

            document.getElementById('duzenle-tarih').value = tarih;
            if (tarih) {
                var tp = tarih.split('-');
                document.getElementById('duzenle-tarih-yil').value = tp[0];
                document.getElementById('duzenle-tarih-ay').value = tp[1];
                document.getElementById('duzenle-tarih-gun').value = tp[2];
            } else {
                document.getElementById('duzenle-tarih-yil').value = '';
                document.getElementById('duzenle-tarih-ay').value = '';
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
            modal.querySelectorAll('input[type="text"], input[type="number"]').forEach(function (el) {
                el.value = '';
            });
            modal.querySelectorAll('select').forEach(function (sel) {
                sel.value = '';
            });
            modal.querySelectorAll('input[type="hidden"][id$="-tarih"], input[type="hidden"][id$="-saat"]').forEach(function (el) {
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

        zorunluAlanlariBagla('ekle-form', ['ekle-ad', 'ekle-yer', 'ekle-tarih', 'ekle-saat', 'ekle-koltuk']);
        zorunluAlanlariBagla('duzenle-form', ['duzenle-ad', 'duzenle-yer', 'duzenle-tarih', 'duzenle-saat', 'duzenle-koltuk']);

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

        enterSirayaBagla('ekle-form', ['ekle-ad', 'ekle-yer', 'ekle-tarih-gun', 'ekle-tarih-ay', 'ekle-tarih-yil', 'ekle-saat-saat', 'ekle-saat-dakika', 'ekle-koltuk']);
        enterSirayaBagla('duzenle-form', ['duzenle-ad', 'duzenle-yer', 'duzenle-tarih-gun', 'duzenle-tarih-ay', 'duzenle-tarih-yil', 'duzenle-saat-saat', 'duzenle-saat-dakika', 'duzenle-koltuk']);

        if (ekleModalAcMi) {
            document.getElementById('ekle-modal').showModal();
        }
        if (duzenleModalAcMi) {
            document.getElementById('duzenle-modal').showModal();
        }
    </script>

</body>
</html>