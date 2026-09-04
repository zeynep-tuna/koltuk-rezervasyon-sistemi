<?php
require '../includes/admin-auth-kontrol.php';
require '../includes/db.php';

function tc_gecerli_mi($tc) {
    if (strlen($tc) !== 11 || !ctype_digit($tc) || $tc[0] === '0') {
        return false;
    }
    $rakamlar = str_split($tc);
    $tek_toplam = (int)$rakamlar[0] + (int)$rakamlar[2] + (int)$rakamlar[4] + (int)$rakamlar[6] + (int)$rakamlar[8];
    $cift_toplam = (int)$rakamlar[1] + (int)$rakamlar[3] + (int)$rakamlar[5] + (int)$rakamlar[7];
    $hesaplanan_10 = (($tek_toplam * 7) - $cift_toplam) % 10;
    if ($hesaplanan_10 < 0) {
        $hesaplanan_10 += 10;
    }
    if ($hesaplanan_10 !== (int)$rakamlar[9]) {
        return false;
    }
    $ilk_10_toplam = 0;
    for ($i = 0; $i < 10; $i++) {
        $ilk_10_toplam += (int)$rakamlar[$i];
    }
    $hesaplanan_11 = $ilk_10_toplam % 10;
    return $hesaplanan_11 === (int)$rakamlar[10];
}

$hata = '';
$tc_hata = '';
$ekle_modal_ac = false;
$duzenle_modal_ac = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vatandas_ekle'])) {
    $tc_no = trim($_POST['tc_no'] ?? '');
    $ad = trim($_POST['ad'] ?? '');
    $soyad = trim($_POST['soyad'] ?? '');
    $dogum_tarihi = trim($_POST['dogum_tarihi'] ?? '');
    $telefon = trim($_POST['telefon'] ?? '');

    if ($ad === '' || $soyad === '' || $tc_no === '' || $dogum_tarihi === '') {
        $hata = 'Lütfen zorunlu alanları doldurun.';
        $ekle_modal_ac = true;
    } elseif (!tc_gecerli_mi($tc_no)) {
        $tc_hata = 'Geçerli bir TC Kimlik No giriniz.';
        $ekle_modal_ac = true;
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO vatandaslar (tc_no, ad, soyad, dogum_tarihi, telefon) VALUES (:tc_no, :ad, :soyad, :dogum_tarihi, :telefon)");
            $stmt->execute([
                'tc_no' => $tc_no,
                'ad' => $ad,
                'soyad' => $soyad,
                'dogum_tarihi' => $dogum_tarihi,
                'telefon' => $telefon !== '' ? $telefon : null,
            ]);
            header('Location: vatandaslar.php');
            exit;
        } catch (PDOException $e) {
            $tc_hata = ($e->getCode() == 23000) ? 'Bu TC Kimlik No zaten kayıtlı.' : '';
            if ($tc_hata === '') {
                $hata = 'Bir hata oluştu, tekrar deneyin.';
            }
            $ekle_modal_ac = true;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vatandas_duzenle'])) {
    $id = $_POST['id'] ?? '';
    $tc_no = trim($_POST['tc_no'] ?? '');
    $ad = trim($_POST['ad'] ?? '');
    $soyad = trim($_POST['soyad'] ?? '');
    $dogum_tarihi = trim($_POST['dogum_tarihi'] ?? '');
    $telefon = trim($_POST['telefon'] ?? '');

    if ($ad === '' || $soyad === '' || $tc_no === '' || $dogum_tarihi === '') {
        $hata = 'Lütfen zorunlu alanları doldurun.';
        $duzenle_modal_ac = true;
    } elseif (!tc_gecerli_mi($tc_no)) {
        $tc_hata = 'Geçerli bir TC Kimlik No giriniz.';
        $duzenle_modal_ac = true;
    } elseif (!ctype_digit((string)$id)) {
        $hata = 'Geçersiz kayıt.';
        $duzenle_modal_ac = true;
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE vatandaslar SET tc_no = :tc_no, ad = :ad, soyad = :soyad, dogum_tarihi = :dogum_tarihi, telefon = :telefon WHERE id = :id");
            $stmt->execute([
                'tc_no' => $tc_no,
                'ad' => $ad,
                'soyad' => $soyad,
                'dogum_tarihi' => $dogum_tarihi,
                'telefon' => $telefon !== '' ? $telefon : null,
                'id' => $id,
            ]);
            header('Location: vatandaslar.php');
            exit;
        } catch (PDOException $e) {
            $tc_hata = ($e->getCode() == 23000) ? 'Bu TC Kimlik No zaten kayıtlı.' : '';
            if ($tc_hata === '') {
                $hata = 'Bir hata oluştu, tekrar deneyin.';
            }
            $duzenle_modal_ac = true;
        }
    }
}

list($ekle_gun, $ekle_ay, $ekle_yil) = ($ekle_modal_ac && isset($_POST['dogum_tarihi'])) ? tarih_parcala($_POST['dogum_tarihi']) : ['', '', ''];
list($duzenle_gun, $duzenle_ay, $duzenle_yil) = ($duzenle_modal_ac && isset($_POST['dogum_tarihi'])) ? tarih_parcala($_POST['dogum_tarihi']) : ['', '', ''];

$arama = trim($_GET['q'] ?? '');
if ($arama !== '') {
    $stmt = $pdo->prepare("SELECT id, tc_no, ad, soyad, dogum_tarihi, telefon FROM vatandaslar WHERE deleted = 0 AND (tc_no LIKE :arama OR ad LIKE :arama OR soyad LIKE :arama OR telefon LIKE :arama) ORDER BY id ASC");
    $stmt->execute(['arama' => '%' . $arama . '%']);
} else {
    $stmt = $pdo->query("SELECT id, tc_no, ad, soyad, dogum_tarihi, telefon FROM vatandaslar WHERE deleted = 0 ORDER BY id ASC");
}
$vatandaslar = $stmt->fetchAll();

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

function tarih_parcala($tarih) {
    if (!$tarih) {
        return ['', '', ''];
    }
    $p = explode('-', $tarih);
    return [$p[2] ?? '', $p[1] ?? '', $p[0] ?? ''];
}

function yil_datalist_secenekleri() {
    $html = '';
    $bu_yil = (int)date('Y');
    for ($y = $bu_yil; $y >= 1920; $y--) {
        $html .= '<option value="' . $y . '">';
    }
    return $html;
}

function tarih_formatla($tarih, $aylar) {
    if (!$tarih) {
        return '<span class="tarih-yok">Belirtilmemiş</span>';
    }
    $ts = strtotime($tarih);
    return date('d', $ts) . ' ' . $aylar[(int)date('n', $ts) - 1] . ' ' . date('Y', $ts);
}

function telefon_formatla($tel) {
    if (!$tel) {
        return '<span class="tarih-yok">-</span>';
    }
    $rakamlar = preg_replace('/[^0-9]/', '', $tel);
    if (strlen($rakamlar) === 10) {
        return '(' . substr($rakamlar, 0, 3) . ') ' . substr($rakamlar, 3, 3) . ' ' . substr($rakamlar, 6, 2) . ' ' . substr($rakamlar, 8, 2);
    }
    return htmlspecialchars($tel);
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Admin Paneli - Vatandaşlar</title>
    <link rel="stylesheet" href="../css/admin-layout.css">
    <link rel="stylesheet" href="../css/admin-vatandaslar.css">
    <link rel="stylesheet" href="../css/admin-vatandas-form.css">
</head>
<body>

    <div class="admin-layout">
        <?php require '../includes/admin-sidebar.php'; ?>

        <div class="admin-main">
            <?php require '../includes/admin-topbar.php'; ?>

            <div class="admin-content">
                <h1>Vatandaşlar</h1>

                <button type="button" class="btn-ekle" onclick="document.getElementById('ekle-modal').showModal()">+ Yeni Vatandaş Ekle</button>

                <table class="admin-tablo">
                    <thead>
                        <tr>
                            <th>Sıra No</th>
                            <th>TC Kimlik No</th>
                            <th>Ad</th>
                            <th>Soyad</th>
                            <th>Doğum Tarihi</th>
                            <th>Telefon</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($vatandaslar) === 0): ?>
                            <tr>
                                <td colspan="7" class="bos-satir">Henüz eklenmiş vatandaş yok.</td>
                            </tr>
                        <?php else: ?>
                            <?php $sira = 1; foreach ($vatandaslar as $v): ?>
                                <tr>
                                    <td><?= $sira++ ?></td>
                                    <td><?= htmlspecialchars($v['tc_no']) ?></td>
                                    <td><?= htmlspecialchars($v['ad']) ?></td>
                                    <td><?= htmlspecialchars($v['soyad']) ?></td>
                                    <td><?= tarih_formatla($v['dogum_tarihi'], $aylar) ?></td>
                                    <td><?= telefon_formatla($v['telefon']) ?></td>
                                    <td class="islemler">
                                        <button type="button" class="btn-duzenle" onclick="duzenleAc(<?= (int)$v['id'] ?>, '<?= htmlspecialchars(addslashes($v['tc_no'])) ?>', '<?= htmlspecialchars(addslashes($v['ad'])) ?>', '<?= htmlspecialchars(addslashes($v['soyad'])) ?>', '<?= $v['dogum_tarihi'] ?>', '<?= htmlspecialchars(addslashes($v['telefon'] ?? '')) ?>')">Düzenle</button>
                                        <button type="button" class="btn-sil" onclick="silOnayAc(<?= (int)$v['id'] ?>)">Sil</button>
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
        <h2>Yeni Vatandaş Ekle</h2>
        <p class="zorunlu-not"><span class="zorunlu-yildiz">*</span> ile işaretli alanlar zorunludur.</p>

        <p class="hata-mesaj" id="ekle-genel-hata" style="<?= ($hata !== '' && $ekle_modal_ac) ? '' : 'display:none' ?>"><?= htmlspecialchars($hata !== '' ? $hata : 'Lütfen zorunlu alanları doldurun.') ?></p>

        <form method="POST" action="vatandaslar.php" autocomplete="off" id="ekle-form">
            <input type="hidden" name="vatandas_ekle" value="1">

            <label for="ekle-tc">TC Kimlik No <span class="zorunlu-yildiz">*</span></label>
            <input type="text" id="ekle-tc" name="tc_no" maxlength="11" inputmode="numeric" pattern="[0-9]*" autocomplete="off" value="<?= $ekle_modal_ac ? htmlspecialchars($_POST['tc_no'] ?? '') : '' ?>">
            <p class="hata-mesaj-inline" id="ekle-tc-hata" style="<?= ($tc_hata !== '' && $ekle_modal_ac) ? '' : 'display:none' ?>"><?= htmlspecialchars($tc_hata) ?></p>

            <label for="ekle-ad">Ad <span class="zorunlu-yildiz">*</span></label>
            <input type="text" id="ekle-ad" name="ad" autocomplete="off" oninput="this.value=this.value.replace(/[^A-Za-zÇĞİÖŞÜçğıöşü\s\'-]/g,'')" value="<?= $ekle_modal_ac ? htmlspecialchars($_POST['ad'] ?? '') : '' ?>">
            <p class="hata-mesaj-inline" id="ekle-ad-hata" style="display:none">Bu alan zorunludur.</p>

            <label for="ekle-soyad">Soyad <span class="zorunlu-yildiz">*</span></label>
            <input type="text" id="ekle-soyad" name="soyad" autocomplete="off" oninput="this.value=this.value.replace(/[^A-Za-zÇĞİÖŞÜçğıöşü\s\'-]/g,'')" value="<?= $ekle_modal_ac ? htmlspecialchars($_POST['soyad'] ?? '') : '' ?>">
            <p class="hata-mesaj-inline" id="ekle-soyad-hata" style="display:none">Bu alan zorunludur.</p>

            <label>Doğum Tarihi <span class="zorunlu-yildiz">*</span></label>
            <div class="tarih-secim-grubu">
                <select id="ekle-dogum-gun"><?= gun_secenekleri($ekle_gun) ?></select>
                <div class="yil-kutu-sarmalayici">
                    <input type="text" id="ekle-dogum-ay-goster" placeholder="Ay" autocomplete="off" readonly value="<?= ($ekle_ay && isset($aylar[(int)$ekle_ay - 1])) ? $aylar[(int)$ekle_ay - 1] : '' ?>">
                    <input type="hidden" id="ekle-dogum-ay" value="<?= htmlspecialchars($ekle_ay) ?>">
                    <div class="yil-oneri-listesi" id="ekle-dogum-ay-oneri"></div>
                </div>
                <div class="yil-kutu-sarmalayici">
                    <input type="text" id="ekle-dogum-yil" placeholder="Yıl" autocomplete="off" value="<?= htmlspecialchars($ekle_yil) ?>">
                    <div class="yil-oneri-listesi" id="ekle-yil-oneri"></div>
                </div>
                <button type="button" class="btn-tarih-temizle" onclick="tarihTemizle('ekle')" title="Temizle">✕</button>
            </div>
            <input type="hidden" id="ekle-dogum" name="dogum_tarihi" value="<?= ($ekle_modal_ac && isset($_POST['dogum_tarihi'])) ? htmlspecialchars($_POST['dogum_tarihi']) : '' ?>">
            <p class="hata-mesaj-inline" id="ekle-dogum-hata" style="display:none">Bu alan zorunludur.</p>

            <label for="ekle-telefon">Telefon</label>
            <input type="text" id="ekle-telefon" name="telefon" inputmode="numeric" autocomplete="off" placeholder="(555) 555 55 55" oninput="telefonMaskele(this)" onkeydown="telefonBackspaceYakala(event)" value="<?= $ekle_modal_ac ? htmlspecialchars($_POST['telefon'] ?? '') : '' ?>">

            <div class="modal-butonlar">
                <button type="button" class="btn-vazgec" onclick="document.getElementById('ekle-modal').close()">Vazgeç</button>
                <button type="submit" class="btn-kaydet">Kaydet</button>
            </div>
        </form>
    </dialog>

    <!-- Düzenleme modalı -->
    <dialog id="duzenle-modal" class="admin-modal">
        <h2>Vatandaş Düzenle</h2>
        <p class="zorunlu-not"><span class="zorunlu-yildiz">*</span> ile işaretli alanlar zorunludur.</p>

        <p class="hata-mesaj" id="duzenle-genel-hata" style="<?= ($hata !== '' && $duzenle_modal_ac) ? '' : 'display:none' ?>"><?= htmlspecialchars($hata !== '' ? $hata : 'Lütfen zorunlu alanları doldurun.') ?></p>

        <form method="POST" action="vatandaslar.php" autocomplete="off" id="duzenle-form">
            <input type="hidden" name="vatandas_duzenle" value="1">
            <input type="hidden" name="id" id="duzenle-id" value="<?= $duzenle_modal_ac ? htmlspecialchars($_POST['id'] ?? '') : '' ?>">

            <label for="duzenle-tc">TC Kimlik No <span class="zorunlu-yildiz">*</span></label>
            <input type="text" id="duzenle-tc" name="tc_no" maxlength="11" inputmode="numeric" pattern="[0-9]*" autocomplete="off" value="<?= $duzenle_modal_ac ? htmlspecialchars($_POST['tc_no'] ?? '') : '' ?>">
            <p class="hata-mesaj-inline" id="duzenle-tc-hata" style="<?= ($tc_hata !== '' && $duzenle_modal_ac) ? '' : 'display:none' ?>"><?= htmlspecialchars($tc_hata) ?></p>

            <label for="duzenle-ad">Ad <span class="zorunlu-yildiz">*</span></label>
            <input type="text" id="duzenle-ad" name="ad" autocomplete="off" oninput="this.value=this.value.replace(/[^A-Za-zÇĞİÖŞÜçğıöşü\s\'-]/g,'')" value="<?= $duzenle_modal_ac ? htmlspecialchars($_POST['ad'] ?? '') : '' ?>">
            <p class="hata-mesaj-inline" id="duzenle-ad-hata" style="display:none">Bu alan zorunludur.</p>

            <label for="duzenle-soyad">Soyad <span class="zorunlu-yildiz">*</span></label>
            <input type="text" id="duzenle-soyad" name="soyad" autocomplete="off" oninput="this.value=this.value.replace(/[^A-Za-zÇĞİÖŞÜçğıöşü\s\'-]/g,'')" value="<?= $duzenle_modal_ac ? htmlspecialchars($_POST['soyad'] ?? '') : '' ?>">
            <p class="hata-mesaj-inline" id="duzenle-soyad-hata" style="display:none">Bu alan zorunludur.</p>

            <label>Doğum Tarihi <span class="zorunlu-yildiz">*</span></label>
            <div class="tarih-secim-grubu">
                <select id="duzenle-dogum-gun"><?= gun_secenekleri($duzenle_gun) ?></select>
                <div class="yil-kutu-sarmalayici">
                    <input type="text" id="duzenle-dogum-ay-goster" placeholder="Ay" autocomplete="off" readonly value="<?= ($duzenle_ay && isset($aylar[(int)$duzenle_ay - 1])) ? $aylar[(int)$duzenle_ay - 1] : '' ?>">
                    <input type="hidden" id="duzenle-dogum-ay" value="<?= htmlspecialchars($duzenle_ay) ?>">
                    <div class="yil-oneri-listesi" id="duzenle-dogum-ay-oneri"></div>
                </div>
                <div class="yil-kutu-sarmalayici">
                    <input type="text" id="duzenle-dogum-yil" placeholder="Yıl" autocomplete="off" value="<?= htmlspecialchars($duzenle_yil) ?>">
                    <div class="yil-oneri-listesi" id="duzenle-yil-oneri"></div>
                </div>
                <button type="button" class="btn-tarih-temizle" onclick="tarihTemizle('duzenle')" title="Temizle">✕</button>
            </div>
            <input type="hidden" id="duzenle-dogum" name="dogum_tarihi" value="<?= ($duzenle_modal_ac && isset($_POST['dogum_tarihi'])) ? htmlspecialchars($_POST['dogum_tarihi']) : '' ?>">
            <p class="hata-mesaj-inline" id="duzenle-dogum-hata" style="display:none">Bu alan zorunludur.</p>

            <label for="duzenle-telefon">Telefon</label>
            <input type="text" id="duzenle-telefon" name="telefon" inputmode="numeric" autocomplete="off" placeholder="(555) 555 55 55" oninput="telefonMaskele(this)" onkeydown="telefonBackspaceYakala(event)" value="<?= $duzenle_modal_ac ? htmlspecialchars($_POST['telefon'] ?? '') : '' ?>">

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
        <h2>Vatandaşı Sil</h2>
        <p class="onay-mesaji">Bu vatandaşı silmek istediğinize emin misiniz? Bu işlem geri alınamaz.</p>
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
            window.location.href = 'vatandas-sil.php?id=' + id;
        }

        function duzenleAc(id, tc, ad, soyad, dogum, telefon) {
            document.getElementById('duzenle-id').value = id;
            document.getElementById('duzenle-tc').value = tc;
            document.getElementById('duzenle-ad').value = ad;
            document.getElementById('duzenle-soyad').value = soyad;
            document.getElementById('duzenle-telefon').value = telefon;
            telefonMaskele(document.getElementById('duzenle-telefon'));
            document.getElementById('duzenle-dogum').value = dogum;
            if (dogum) {
                var parcalar = dogum.split('-');
                document.getElementById('duzenle-dogum-yil').value = parcalar[0];
                document.getElementById('duzenle-dogum-ay').value = parcalar[1];
                document.getElementById('duzenle-dogum-ay-goster').value = parcalar[1] ? aylarListesi[parseInt(parcalar[1], 10) - 1] : '';
                document.getElementById('duzenle-dogum-gun').value = parcalar[2];
            } else {
                document.getElementById('duzenle-dogum-yil').value = '';
                document.getElementById('duzenle-dogum-ay').value = '';
                document.getElementById('duzenle-dogum-ay-goster').value = '';
                document.getElementById('duzenle-dogum-gun').value = '';
            }
            ['duzenle-dogum-gun'].forEach(function (id) {
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
            modal.querySelectorAll('input[type="text"]').forEach(function (el) {
                el.value = '';
            });
            modal.querySelectorAll('input.input-hata, select.input-hata').forEach(function (el) {
                el.classList.remove('input-hata');
            });
            modal.querySelectorAll('select').forEach(function (sel) {
                sel.value = '';
                sel.dispatchEvent(new Event('change'));
            });
            modal.querySelectorAll('input[type="hidden"][id$="-dogum"], input[type="hidden"][id$="-ay"]').forEach(function (el) {
                el.value = '';
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



        function tarihBirlestir(prefix) {
            var gun = document.getElementById(prefix + '-dogum-gun').value;
            var ay = document.getElementById(prefix + '-dogum-ay').value;
            var yil = document.getElementById(prefix + '-dogum-yil').value;
            var gizli = document.getElementById(prefix + '-dogum');
            var hata = document.getElementById(prefix + '-dogum-hata');
            if (gun && ay && yil && yil.length === 4) {
                gizli.value = yil + '-' + ay + '-' + gun;
                if (hata) hata.style.display = 'none';
                document.getElementById(prefix + '-dogum-gun').classList.remove('input-hata');
                document.getElementById(prefix + '-dogum-ay-goster').classList.remove('input-hata');
                document.getElementById(prefix + '-dogum-yil').classList.remove('input-hata');
            } else {
                gizli.value = '';
            }
        }

        function tarihTemizle(prefix) {
            document.getElementById(prefix + '-dogum-gun').value = '';
            document.getElementById(prefix + '-dogum-ay').value = '';
            document.getElementById(prefix + '-dogum-ay-goster').value = '';
            document.getElementById(prefix + '-dogum-yil').value = '';
            document.getElementById(prefix + '-dogum').value = '';
        }

        ['ekle', 'duzenle'].forEach(function (prefix) {
            document.getElementById(prefix + '-dogum-gun').addEventListener('change', function () {
                tarihBirlestir(prefix);
            });
        });

        var aylarListesi = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];

        function aySecimBagla(prefix) {
            var goster = document.getElementById(prefix + '-dogum-ay-goster');
            var kodGizli = document.getElementById(prefix + '-dogum-ay');
            var liste = document.getElementById(prefix + '-dogum-ay-oneri');

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

        aySecimBagla('ekle');
        aySecimBagla('duzenle');


        function telefonMaskeleRakamdan(input, d) {
            d = d.slice(0, 10);
            var sonuc = '';
            if (d.length > 0) {
                sonuc = '(' + d.substring(0, 3);
                if (d.length >= 3) sonuc += ')';
            }
            if (d.length > 3) {
                sonuc += ' ' + d.substring(3, 6);
            }
            if (d.length > 6) {
                sonuc += ' ' + d.substring(6, 8);
            }
            if (d.length > 8) {
                sonuc += ' ' + d.substring(8, 10);
            }
            input.value = sonuc;
            input.setSelectionRange(sonuc.length, sonuc.length);
        }

        function telefonMaskele(input) {
            var d = input.value.replace(/[^0-9]/g, '').slice(0, 10);
            telefonMaskeleRakamdan(input, d);
        }

        function telefonBackspaceYakala(e) {
            if (e.key === 'Backspace') {
                e.preventDefault();
                var input = e.target;
                var d = input.value.replace(/[^0-9]/g, '');
                d = d.slice(0, -1);
                telefonMaskeleRakamdan(input, d);
            }
        }


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

        enterSirayaBagla('ekle-form', ['ekle-tc', 'ekle-ad', 'ekle-soyad', 'ekle-dogum-gun', 'ekle-dogum-ay-goster', 'ekle-dogum-yil', 'ekle-telefon']);
        enterSirayaBagla('duzenle-form', ['duzenle-tc', 'duzenle-ad', 'duzenle-soyad', 'duzenle-dogum-gun', 'duzenle-dogum-ay-goster', 'duzenle-dogum-yil', 'duzenle-telefon']);


        function yilOneriBagla(prefix) {
            var input = document.getElementById(prefix + '-dogum-yil');
            var liste = document.getElementById(prefix + '-yil-oneri');
            var buYil = new Date().getFullYear();
            var tumYillar = [];
            for (var y = buYil; y >= 1920; y--) {
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

        ['ekle-dogum-gun', 'duzenle-dogum-gun'].forEach(bosSecimGriBoya);

        yilOneriBagla('ekle');
        yilOneriBagla('duzenle');


        function tcGecerliMi(tc) {
            if (tc.length !== 11 || !/^[0-9]{11}$/.test(tc) || tc[0] === '0') {
                return false;
            }
            var r = tc.split('').map(Number);
            var tekToplam = r[0] + r[2] + r[4] + r[6] + r[8];
            var ciftToplam = r[1] + r[3] + r[5] + r[7];
            var hesap10 = ((tekToplam * 7) - ciftToplam) % 10;
            if (hesap10 < 0) hesap10 += 10;
            if (hesap10 !== r[9]) return false;
            var ilk10Toplam = 0;
            for (var i = 0; i < 10; i++) ilk10Toplam += r[i];
            var hesap11 = ilk10Toplam % 10;
            return hesap11 === r[10];
        }

        function tcCanliDogrula(tcInputId, hataId, haricIdInputId) {
            var input = document.getElementById(tcInputId);
            var hata = document.getElementById(hataId);
            input.addEventListener('input', function () {
                input.value = input.value.replace(/[^0-9]/g, '');
                var deger = input.value.trim();
                if (deger.length === 11) {
                    if (!tcGecerliMi(deger)) {
                        hata.textContent = 'Geçerli bir TC Kimlik No giriniz.';
                        hata.style.display = 'block';
                        input.classList.add('input-hata');
                        return;
                    }
                    hata.style.display = 'none';
                    input.classList.remove('input-hata');

                    var haricId = haricIdInputId ? document.getElementById(haricIdInputId).value : '';
                    var url = 'tc-kontrol.php?tc=' + deger + (haricId ? '&haric_id=' + haricId : '');
                    fetch(url)
                        .then(function (r) { return r.json(); })
                        .then(function (sonuc) {
                            if (input.value.trim() !== deger) return;
                            if (sonuc.kayitli) {
                                hata.textContent = 'Bu TC Kimlik No zaten kayıtlı.';
                                hata.style.display = 'block';
                                input.classList.add('input-hata');
                            } else {
                                hata.style.display = 'none';
                                input.classList.remove('input-hata');
                            }
                        })
                        .catch(function () {});
                } else {
                    hata.style.display = 'none';
                    input.classList.remove('input-hata');
                }
            });

            input.addEventListener('blur', function () {
                var deger = input.value.trim();
                if (deger.length > 0 && deger.length !== 11) {
                    hata.textContent = 'TC Kimlik No 11 haneli olmalıdır.';
                    hata.style.display = 'block';
                    input.classList.add('input-hata');
                }
            });
        }

        function zorunluAlanlariBagla(formId, alanIdleri) {
            var form = document.getElementById(formId);
            alanIdleri.forEach(function (alanId) {
                var input = document.getElementById(alanId);
                var hata = document.getElementById(alanId + '-hata');
                var tcAlaniMi = alanId.indexOf('-tc') !== -1;
                if (!tcAlaniMi) {
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
                        if (input.type === 'hidden' && alanId.indexOf('-dogum') !== -1) {
                            document.getElementById(alanId + '-gun').classList.add('input-hata');
                            document.getElementById(alanId + '-ay-goster').classList.add('input-hata');
                            document.getElementById(alanId + '-yil').classList.add('input-hata');
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

        tcCanliDogrula('ekle-tc', 'ekle-tc-hata', null);
        tcCanliDogrula('duzenle-tc', 'duzenle-tc-hata', 'duzenle-id');
        zorunluAlanlariBagla('ekle-form', ['ekle-tc', 'ekle-ad', 'ekle-soyad', 'ekle-dogum']);
        zorunluAlanlariBagla('duzenle-form', ['duzenle-tc', 'duzenle-ad', 'duzenle-soyad', 'duzenle-dogum']);

        if (ekleModalAcMi) {
            document.getElementById('ekle-modal').showModal();
        }
        if (duzenleModalAcMi) {
            document.getElementById('duzenle-modal').showModal();
        }
    </script>

</body>
</html>