<?php
require '../includes/db.php';

// Sadece Aktif etkinlikleri listeye koy (Gizli olanlar halka gorunmez)
$etkinlikler = $pdo->query("SELECT id, ad, yer, etkinlik_tarih, etkinlik_saat FROM etkinlikler WHERE deleted = 0 AND aktif_mi = 1 ORDER BY etkinlik_tarih ASC")->fetchAll();

foreach ($etkinlikler as &$e) {
    $sayim = $pdo->prepare("SELECT COUNT(*) AS adet FROM koltuklar WHERE etkinlik_id = :id AND deleted = 0 AND durum = 'bos'");
    $sayim->execute(['id' => $e['id']]);
    $e['kalan_koltuk'] = (int)$sayim->fetch()['adet'];
}
unset($e);

function ozel_secici_render($id, $placeholder, $secenekler) {
    $html = '<div class="ozel-secici-sarmalayici ozel-secici-kompakt">';
    $html .= '<button type="button" class="ozel-secici-buton" id="' . $id . '-buton" onclick="ozelSeciciAcKapa(\'' . $id . '-liste\')">' . htmlspecialchars($placeholder) . '</button>';
    $html .= '<input type="hidden" id="' . $id . '">';
    $html .= '<div class="ozel-secici-liste" id="' . $id . '-liste">';
    foreach ($secenekler as $deger => $etiket) {
        $html .= '<div class="ozel-secici-madde" data-deger="' . htmlspecialchars($deger) . '" data-id="' . $id . '" onclick="ozelSeciciSec(this)">' . htmlspecialchars($etiket) . '</div>';
    }
    $html .= '</div></div>';
    return $html;
}

$gun_secenekleri = [];
for ($g = 1; $g <= 31; $g++) {
    $gun_secenekleri[str_pad($g, 2, '0', STR_PAD_LEFT)] = (string)$g;
}

$ay_secenekleri = [];
$aylar_liste = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
foreach ($aylar_liste as $ay_index => $ay_adi) {
    $ay_secenekleri[str_pad($ay_index + 1, 2, '0', STR_PAD_LEFT)] = $ay_adi;
}

$yil_secenekleri = [];
for ($y = (int)date('Y'); $y >= (int)date('Y') - 90; $y--) {
    $yil_secenekleri[$y] = (string)$y;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rezervasyon</title>
    <link rel="stylesheet" href="../css/rezervasyon.css">
</head>
<body>

    <div class="rez-ust-serit">
        <div class="rez-ust-serit-isik"></div>
        <div class="rez-ust-icerik">
            <div class="rez-ust-logo">🎭</div>
            <div>
                <div class="rez-ust-baslik">Belediye Etkinlik Rezervasyonu</div>
                <div class="rez-ust-alt-baslik">Ücretsiz etkinlik biletlerinizi buradan alabilirsiniz</div>
            </div>
        </div>
    </div>

    <div class="rez-sayfa">
        <div class="rez-kart">

            <div class="rez-ilerleme">
                <div class="rez-adim-grubu">
                    <div class="rez-adim-nokta rez-adim-aktif" id="nokta-1">1</div>
                    <span class="rez-adim-etiket rez-adim-etiket-aktif" id="etiket-1">Kişi Bilgileri</span>
                </div>
                <div class="rez-adim-cizgi" id="cizgi-1"></div>
                <div class="rez-adim-grubu">
                    <div class="rez-adim-nokta" id="nokta-2">2</div>
                    <span class="rez-adim-etiket" id="etiket-2">Etkinlik</span>
                </div>
                <div class="rez-adim-cizgi" id="cizgi-2"></div>
                <div class="rez-adim-grubu">
                    <div class="rez-adim-nokta" id="nokta-3">3</div>
                    <span class="rez-adim-etiket" id="etiket-3">Koltuk</span>
                </div>
                <div class="rez-adim-cizgi" id="cizgi-3"></div>
                <div class="rez-adim-grubu">
                    <div class="rez-adim-nokta" id="nokta-4">4</div>
                    <span class="rez-adim-etiket" id="etiket-4">Onay</span>
                </div>
            </div>

            <div class="rez-icerik-alani">

                <!-- ADIM 1: Kisi Bilgileri -->
                <div class="rez-adim-icerik" id="adim-1-icerik">
                    <h2>Kimlik Doğrulama</h2>
                    <p class="rez-aciklama">Rezervasyon yapmak için TC kimlik numaranı ve doğum tarihini gir.</p>

                    <label for="tc_no">TC Kimlik Numarası <span class="zorunlu-yildiz">*</span></label>
                    <input type="text" id="tc_no" maxlength="11" inputmode="numeric" placeholder="11 haneli TC kimlik numaran" autocomplete="off">
                    <p class="hata-mesaj-inline" id="tc-format-hata" style="display:none">Geçersiz TC kimlik numarası.</p>

                    <label>Doğum Tarihi <span class="zorunlu-yildiz">*</span></label>
                    <div class="dogum-tarihi-grubu">
                        <?= ozel_secici_render('dogum-gun', 'Gün', $gun_secenekleri) ?>
                        <?= ozel_secici_render('dogum-ay', 'Ay', $ay_secenekleri) ?>
                        <?= ozel_secici_render('dogum-yil', 'Yıl', $yil_secenekleri) ?>
                    </div>
                    <input type="hidden" id="dogum_tarihi">

                    <p class="rez-durum-mesaji" id="kimlik-durum-mesaji" style="display:none"></p>

                    <div class="rez-ad-soyad-goster" id="ad-soyad-goster" style="display:none">
                        <span class="rez-onay-ikon">✓</span>
                        <div>
                            <div class="rez-ad-soyad-etiket">Kimlik doğrulandı</div>
                            <div class="rez-ad-soyad-deger" id="ad-soyad-deger"></div>
                        </div>
                    </div>

                    <div class="rez-yeni-kayit" id="yeni-kayit-alani" style="display:none">
                        <p class="rez-kayit-aciklama">Yeni kayıt için bilgilerini gir.</p>

                        <label for="yeni_tc">TC Kimlik Numarası <span class="zorunlu-yildiz">*</span></label>
                        <input type="text" id="yeni_tc" maxlength="11" inputmode="numeric" placeholder="11 haneli TC kimlik numaran" autocomplete="off">
                        <p class="hata-mesaj-inline" id="yeni-tc-format-hata" style="display:none">Geçersiz TC kimlik numarası.</p>

                        <label>Doğum Tarihi <span class="zorunlu-yildiz">*</span></label>
                        <div class="dogum-tarihi-grubu">
                            <?= ozel_secici_render('yeni-dogum-gun', 'Gün', $gun_secenekleri) ?>
                            <?= ozel_secici_render('yeni-dogum-ay', 'Ay', $ay_secenekleri) ?>
                            <?= ozel_secici_render('yeni-dogum-yil', 'Yıl', $yil_secenekleri) ?>
                        </div>

                        <label for="yeni_ad">Ad <span class="zorunlu-yildiz">*</span></label>
                        <input type="text" id="yeni_ad" autocomplete="off">

                        <label for="yeni_soyad">Soyad <span class="zorunlu-yildiz">*</span></label>
                        <input type="text" id="yeni_soyad" autocomplete="off">

                        <label for="yeni_telefon">Telefon <span class="zorunlu-yildiz">*</span></label>
                        <input type="text" id="yeni_telefon" inputmode="numeric" placeholder="(555) 555 55 55" autocomplete="off" oninput="telefonMaskele(this)" onkeydown="telefonBackspaceYakala(event)">

                        <p class="hata-mesaj-genel" id="kayit-hata-mesaji" style="display:none"></p>

                        <button type="button" class="btn-devam" onclick="kaydiTamamla()">Kaydımı Tamamla</button>
                    </div>

                    <button type="button" class="btn-devam" id="adim1-devam-btn" style="display:none" onclick="adimGit(2)">Devam Et</button>
                </div>

                <!-- ADIM 2: Etkinlik Secimi -->
                <div class="rez-adim-icerik" id="adim-2-icerik" style="display:none">
                    <h2>Etkinlik Seç</h2>
                    <p class="rez-aciklama">Katılmak istediğin etkinliği seç.</p>

                    <div class="rez-etkinlik-listesi" id="etkinlik-listesi">
                        <?php if (count($etkinlikler) === 0): ?>
                            <p class="rez-bos-mesaj">Şu anda rezervasyona açık bir etkinlik yok.</p>
                        <?php endif; ?>
                        <?php foreach ($etkinlikler as $e): ?>
                            <div class="rez-etkinlik-karti <?= $e['kalan_koltuk'] === 0 ? 'rez-etkinlik-karti-dolu' : '' ?>" data-etkinlik-id="<?= $e['id'] ?>" data-etkinlik-ad="<?= htmlspecialchars($e['ad']) ?>" data-etkinlik-yer="<?= htmlspecialchars($e['yer']) ?>" data-etkinlik-tarih="<?= $e['etkinlik_tarih'] ? date('d.m.Y', strtotime($e['etkinlik_tarih'])) : '' ?>" data-etkinlik-saat="<?= $e['etkinlik_saat'] ? substr($e['etkinlik_saat'], 0, 5) : '' ?>" data-kalan-koltuk="<?= $e['kalan_koltuk'] ?>" onclick="etkinlikSec(this)">
                                <div class="rez-etkinlik-poster">🎭</div>
                                <div class="rez-etkinlik-bilgi-metin">
                                    <div class="rez-etkinlik-adi"><?= htmlspecialchars($e['ad']) ?></div>
                                    <div class="rez-etkinlik-detay">
                                        <?= htmlspecialchars($e['yer']) ?>
                                        <?php if ($e['etkinlik_tarih']): ?>
                                            · <?= date('d.m.Y', strtotime($e['etkinlik_tarih'])) ?> <?= $e['etkinlik_saat'] ? substr($e['etkinlik_saat'], 0, 5) : '' ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="rez-koltuk-rozeti <?= $e['kalan_koltuk'] === 0 ? 'rez-koltuk-rozeti-dolu' : '' ?>">
                                        <?= $e['kalan_koltuk'] > 0 ? $e['kalan_koltuk'] . ' koltuk kaldı' : 'Koltuk kalmadı' ?>
                                    </div>
                                </div>
                                <button type="button" class="btn-sec" <?= $e['kalan_koltuk'] === 0 ? 'disabled' : '' ?>>Seç</button>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="button" class="rez-geri-link" onclick="adimGit(1)">← Geri dön</button>
                </div>

                <!-- ADIM 3: Koltuk Secimi (yer tutucu) -->
                <div class="rez-adim-icerik" id="adim-3-icerik" style="display:none">
                    <h2>Koltuk Seç</h2>
                    <div class="rez-yakinda-kutu">
                        <p>Koltuk seçim ekranı yakında burada olacak. 🎬</p>
                    </div>
                    <button type="button" class="rez-geri-link" onclick="adimGit(2)">← Geri dön</button>
                </div>

                <!-- ADIM 4: Onay (yer tutucu) -->
                <div class="rez-adim-icerik" id="adim-4-icerik" style="display:none">
                    <h2>Onay</h2>
                    <div class="rez-yakinda-kutu">
                        <p>Rezervasyon onay ve QR kod ekranı yakında burada olacak. 🎟️</p>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        var mevcutAdim = 1;

        function telefonMaskeleRakamdan(input, d) {
            d = d.slice(0, 10);
            var sonuc = '';
            if (d.length > 0) {
                sonuc = '(' + d.substring(0, 3);
                if (d.length >= 3) sonuc += ')';
            }
            if (d.length > 3) sonuc += ' ' + d.substring(3, 6);
            if (d.length > 6) sonuc += ' ' + d.substring(6, 8);
            if (d.length > 8) sonuc += ' ' + d.substring(8, 10);
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

        document.getElementById('yeni_ad').addEventListener('input', function () {
            this.value = this.value.replace(/[^a-zA-ZçğıöşüÇĞİÖŞÜ\s]/g, '');
        });
        document.getElementById('yeni_soyad').addEventListener('input', function () {
            this.value = this.value.replace(/[^a-zA-ZçğıöşüÇĞİÖŞÜ\s]/g, '');
        });
        var secilenEtkinlik = null;
        var tcTimer = null;

        function adimGit(hedefAdim) {
            for (var i = 1; i <= 4; i++) {
                document.getElementById('adim-' + i + '-icerik').style.display = (i === hedefAdim) ? 'block' : 'none';
                var nokta = document.getElementById('nokta-' + i);
                var etiket = document.getElementById('etiket-' + i);
                nokta.classList.remove('rez-adim-aktif', 'rez-adim-tamamlandi');
                etiket.classList.remove('rez-adim-etiket-aktif');

                if (i < hedefAdim) {
                    nokta.classList.add('rez-adim-tamamlandi');
                    nokta.innerHTML = '✓';
                } else if (i === hedefAdim) {
                    nokta.classList.add('rez-adim-aktif');
                    etiket.classList.add('rez-adim-etiket-aktif');
                    nokta.textContent = i;
                } else {
                    nokta.textContent = i;
                }

                if (i < 4) {
                    var cizgi = document.getElementById('cizgi-' + i);
                    cizgi.classList.toggle('rez-cizgi-tamamlandi', i < hedefAdim);
                }
            }
            mevcutAdim = hedefAdim;
        }

        function tcAlgoritmaGecerliMi(tc) {
            if (!/^[0-9]{11}$/.test(tc)) return false;
            if (tc[0] === '0') return false;
            var d = tc.split('').map(Number);
            var tekToplam = d[0] + d[2] + d[4] + d[6] + d[8];
            var ciftToplam = d[1] + d[3] + d[5] + d[7];
            var onuncu = ((tekToplam * 7) - ciftToplam) % 10;
            if (onuncu < 0) onuncu += 10;
            var onbirinci = (tekToplam + ciftToplam + onuncu) % 10;
            return onuncu === d[9] && onbirinci === d[10];
        }

        document.getElementById('tc_no').addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '');
            var hataKutu = document.getElementById('tc-format-hata');
            if (this.value.length === 11 && !tcAlgoritmaGecerliMi(this.value)) {
                hataKutu.style.display = 'block';
                this.classList.add('input-hata');
            } else {
                hataKutu.style.display = 'none';
                this.classList.remove('input-hata');
            }
            kimlikKontrolTetikle();
        });
        function dogumTarihiGuncelle() {
            var gun = document.getElementById('dogum-gun').value;
            var ay = document.getElementById('dogum-ay').value;
            var yil = document.getElementById('dogum-yil').value;
            var gizliAlan = document.getElementById('dogum_tarihi');

            if (gun && ay && yil) {
                gizliAlan.value = yil + '-' + ay + '-' + gun;
            } else {
                gizliAlan.value = '';
            }
            kimlikKontrolTetikle();
        }

        function ozelSeciciAcKapa(listeId) {
            document.querySelectorAll('.ozel-secici-liste').forEach(function (el) {
                if (el.id !== listeId) el.style.display = 'none';
            });
            var liste = document.getElementById(listeId);
            var acikMiydi = liste.style.display === 'block';
            liste.style.display = acikMiydi ? 'none' : 'block';
            if (!acikMiydi) {
                liste.scrollTop = 0;
            }
        }

        function ozelSeciciSec(madde) {
            var id = madde.dataset.id;
            var deger = madde.dataset.deger;
            document.getElementById(id).value = deger;
            document.getElementById(id + '-buton').textContent = madde.textContent;
            document.getElementById(id + '-buton').classList.remove('input-hata');
            document.getElementById(id + '-liste').style.display = 'none';

            if (id.indexOf('yeni-dogum-') === 0) {
                return;
            }
            if (id.indexOf('dogum-') === 0) {
                dogumTarihiGuncelle();
            }
        }

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.ozel-secici-sarmalayici')) {
                document.querySelectorAll('.ozel-secici-liste').forEach(function (el) {
                    el.style.display = 'none';
                });
            }
        });

        function kimlikKontrolTetikle() {
            clearTimeout(tcTimer);
            var tc = document.getElementById('tc_no').value;
            var dogum = document.getElementById('dogum_tarihi').value;

            resetKimlikGorunumu();

            if (tc.length !== 11 || dogum.length !== 10 || !tcAlgoritmaGecerliMi(tc)) {
                return;
            }

            tcTimer = setTimeout(function () {
                fetch('kimlik-dogrula.php?tc_no=' + encodeURIComponent(tc) + '&dogum_tarihi=' + encodeURIComponent(dogum))
                    .then(function (yanit) { return yanit.json(); })
                    .then(function (veri) {
                        if (veri.durum === 'bulundu') {
                            document.getElementById('ad-soyad-deger').textContent = veri.ad + ' ' + veri.soyad;
                            document.getElementById('ad-soyad-goster').style.display = 'flex';
                            document.getElementById('adim1-devam-btn').style.display = 'block';
                        } else if (veri.durum === 'gecersiz_tc') {
                            gosterDurumMesaji('Girdiğin TC kimlik numarası geçersiz. Lütfen kontrol et.');
                        } else if (veri.durum === 'bulunamadi') {
                            gosterDurumMesaji('TC kimlik numarası veya doğum tarihi hatalı. Bilgilerini kontrol et.');
                            document.getElementById('yeni-kayit-alani').style.display = 'block';
                            document.getElementById('tc_no').value = '';
                            document.getElementById('dogum-gun').value = '';
                            document.getElementById('dogum-ay').value = '';
                            document.getElementById('dogum-yil').value = '';
                            document.getElementById('dogum-gun-buton').textContent = 'Gün';
                            document.getElementById('dogum-ay-buton').textContent = 'Ay';
                            document.getElementById('dogum-yil-buton').textContent = 'Yıl';
                            document.getElementById('dogum_tarihi').value = '';
                        }
                    });
            }, 400);
        }

        function resetKimlikGorunumu() {
            document.getElementById('ad-soyad-goster').style.display = 'none';
            document.getElementById('adim1-devam-btn').style.display = 'none';
            document.getElementById('kimlik-durum-mesaji').style.display = 'none';
            document.getElementById('dogum-gun-buton').classList.remove('input-hata');
            document.getElementById('dogum-ay-buton').classList.remove('input-hata');
            document.getElementById('dogum-yil-buton').classList.remove('input-hata');
            if (tcAlgoritmaGecerliMi(document.getElementById('tc_no').value)) {
                document.getElementById('tc_no').classList.remove('input-hata');
            }
        }

        function gosterDurumMesaji(mesaj) {
            var kutu = document.getElementById('kimlik-durum-mesaji');
            kutu.textContent = mesaj;
            kutu.style.display = 'block';
            document.getElementById('tc_no').classList.add('input-hata');
            document.getElementById('dogum-gun-buton').classList.add('input-hata');
            document.getElementById('dogum-ay-buton').classList.add('input-hata');
            document.getElementById('dogum-yil-buton').classList.add('input-hata');
        }

        document.getElementById('yeni_tc').addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '');
            var hataKutu = document.getElementById('yeni-tc-format-hata');
            if (this.value.length === 11 && !tcAlgoritmaGecerliMi(this.value)) {
                hataKutu.style.display = 'block';
                this.classList.add('input-hata');
            } else {
                hataKutu.style.display = 'none';
                this.classList.remove('input-hata');
            }
        });

        function kaydiTamamla() {
            var tc = document.getElementById('yeni_tc').value;
            var gun = document.getElementById('yeni-dogum-gun').value;
            var ay = document.getElementById('yeni-dogum-ay').value;
            var yil = document.getElementById('yeni-dogum-yil').value;
            var ad = document.getElementById('yeni_ad').value.trim();
            var soyad = document.getElementById('yeni_soyad').value.trim();
            var telefon = document.getElementById('yeni_telefon').value.trim();
            var hataKutu = document.getElementById('kayit-hata-mesaji');

            if (tc.length !== 11 || !tcAlgoritmaGecerliMi(tc)) {
                hataKutu.textContent = 'Lütfen geçerli bir TC kimlik numarası gir.';
                hataKutu.style.display = 'block';
                return;
            }

            if (!gun || !ay || !yil) {
                hataKutu.textContent = 'Lütfen doğum tarihini eksiksiz gir.';
                hataKutu.style.display = 'block';
                return;
            }

            if (ad === '' || soyad === '' || telefon === '') {
                hataKutu.textContent = 'Lütfen tüm alanları doldur.';
                hataKutu.style.display = 'block';
                return;
            }

            if (telefon.replace(/[^0-9]/g, '').length !== 10) {
                hataKutu.textContent = 'Lütfen geçerli bir telefon numarası gir.';
                hataKutu.style.display = 'block';
                return;
            }

            var dogum = yil + '-' + ay + '-' + gun;

            var veriler = new FormData();
            veriler.append('tc_no', tc);
            veriler.append('dogum_tarihi', dogum);
            veriler.append('ad', ad);
            veriler.append('soyad', soyad);
            veriler.append('telefon', telefon);

            fetch('vatandas-kaydet.php', { method: 'POST', body: veriler })
                .then(function (yanit) { return yanit.json(); })
                .then(function (veri) {
                    if (!veri.basarili) {
                        hataKutu.textContent = veri.hata;
                        hataKutu.style.display = 'block';
                        return;
                    }
                    hataKutu.style.display = 'none';
                    document.getElementById('ad-soyad-deger').textContent = veri.ad + ' ' + veri.soyad;
                    document.getElementById('yeni-kayit-alani').style.display = 'none';
                    document.getElementById('ad-soyad-goster').style.display = 'flex';
                    document.getElementById('adim1-devam-btn').style.display = 'block';
                });
        }

        function etkinlikSec(kart) {
            if (kart.dataset.kalanKoltuk === '0') {
                return;
            }
            secilenEtkinlik = {
                id: kart.dataset.etkinlikId,
                ad: kart.dataset.etkinlikAd,
                yer: kart.dataset.etkinlikYer,
                tarih: kart.dataset.etkinlikTarih,
                saat: kart.dataset.etkinlikSaat
            };
            adimGit(3);
        }

        function etkinlikListesiniTazele() {
            fetch('etkinlik-liste.php')
                .then(function (yanit) { return yanit.json(); })
                .then(function (etkinlikler) {
                    var kutu = document.getElementById('etkinlik-listesi');
                    if (!kutu) return;

                    if (etkinlikler.length === 0) {
                        kutu.innerHTML = '<p class="rez-bos-mesaj">Şu anda rezervasyona açık bir etkinlik yok.</p>';
                        return;
                    }

                    var html = '';
                    etkinlikler.forEach(function (e) {
                        var doluMu = e.kalan_koltuk === 0;
                        html += '<div class="rez-etkinlik-karti' + (doluMu ? ' rez-etkinlik-karti-dolu' : '') + '" ' +
                            'data-etkinlik-id="' + e.id + '" data-etkinlik-ad="' + e.ad + '" data-etkinlik-yer="' + e.yer + '" ' +
                            'data-etkinlik-tarih="' + e.tarih_gosterim + '" data-etkinlik-saat="' + e.saat_gosterim + '" ' +
                            'data-kalan-koltuk="' + e.kalan_koltuk + '" onclick="etkinlikSec(this)">' +
                            '<div class="rez-etkinlik-poster">🎭</div>' +
                            '<div class="rez-etkinlik-bilgi-metin">' +
                            '<div class="rez-etkinlik-adi">' + e.ad + '</div>' +
                            '<div class="rez-etkinlik-detay">' + e.yer + (e.tarih_gosterim ? ' · ' + e.tarih_gosterim + ' ' + e.saat_gosterim : '') + '</div>' +
                            '<div class="rez-koltuk-rozeti' + (doluMu ? ' rez-koltuk-rozeti-dolu' : '') + '">' + (doluMu ? 'Koltuk kalmadı' : e.kalan_koltuk + ' koltuk kaldı') + '</div>' +
                            '</div></div>';
                    });
                    kutu.innerHTML = html;
                });
        }

        setInterval(etkinlikListesiniTazele, 15000);
    </script>

</body>
</html>