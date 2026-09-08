<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Kimlik bilgileri yalnızca Rezervasyonlarım sayfasından gelen tek seferlik geçişte
// korunur. Sayfa yenilendiğinde bu izin bulunmadığı için oturum temizlenir.
$rezervasyonlardanGeldi = ($_GET['rezervasyonlardan'] ?? '') === '1';
$geriGecisIzni = ($_COOKIE['rezervasyon_geri_izni'] ?? '') === '1';

if (!$rezervasyonlardanGeldi || !$geriGecisIzni) {
    unset($_SESSION['rezervasyon_kullanici']);
} else {
    setcookie('rezervasyon_geri_izni', '', time() - 3600, '/');
}

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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</head>
<body>

    <div class="rez-toast" id="rez-toast"></div>

    <nav class="top-navbar">
        <div class="brand">
            <h1>Belediye Etkinlik Rezervasyonu</h1>
            <span>Ücretsiz etkinlik biletlerinizi buradan alabilirsiniz</span>
        </div>
        <div class="stepper">
            <div class="step step-aktif" id="stepper-1" onclick="stepperTiklandi(1)"><span class="step-num" id="stepper-nokta-1">1</span> Etkinlik</div>
            <div class="step" id="stepper-2" onclick="stepperTiklandi(2)"><span class="step-num" id="stepper-nokta-2">2</span> Koltuk</div>
            <div class="step" id="stepper-3" onclick="stepperTiklandi(3)"><span class="step-num" id="stepper-nokta-3">3</span> Kişi Bilgileri</div>
            <div class="step" id="stepper-4" onclick="stepperTiklandi(4)"><span class="step-num" id="stepper-nokta-4">4</span> Onay</div>
        </div>
    </nav>

    <div class="main-workspace">

        <!-- SIDEBAR - Adim 1 -->
        <aside class="sidebar-info" id="sidebar-1">
            <div>
                <span class="info-tag">ETKİNLİK PORTALI</span>
                <h2 class="info-title">Kültür ve Sanatla Buluşuyoruz</h2>
                <p class="info-desc">Belediyemizin tüm tiyatro, konser ve kültür etkinlikleri vatandaşlarımıza ücretsiz olarak sunulmaktadır.</p>
                <div class="policy-box">
                    <div class="policy-title">KULLANIM KURALLARI</div>
                    <ul class="policy-list">
                        <li>TC kimlik doğrulaması ile güvenli giriş yapılır.</li>
                        <li>Aynı etkinlikte en fazla 4 koltuk seçebilirsiniz.</li>
                        <li>Her koltuk için kendine özel bir QR kod oluşturulur.</li>
                        <li>Rezervasyonunu, etkinlikten en az 3 gün öncesine kadar iptal edebilirsin.</li>
                    </ul>
                </div>
            </div>
            <a href="rezervasyonlarim.php?kaynak=etkinlik" class="rez-sidebar-rezervasyonlarim" onclick="rezervasyonlarimaGecisHazirla()">🎟️ Rezervasyonlarım</a>
        </aside>

        <!-- SIDEBAR - Adim 2: Koltuk -->
        <aside class="sidebar-summary" id="sidebar-2" style="display:none">
            <div>
                <span class="info-tag" id="sidebar-etkinlik-tag">ETKİNLİK</span>
                <h2 class="event-heading" id="sidebar-etkinlik-adi"></h2>
                <p class="event-venue" id="sidebar-etkinlik-yer"></p>

                <div class="rez-koltuk-lejant rez-koltuk-lejant-sidebar rez-koltuk-lejant-sidebar-ust">
                    <span><span class="rez-lejant-nokta rez-lejant-bos"></span> Müsait</span>
                    <span><span class="rez-lejant-nokta rez-lejant-secili"></span> Seçili</span>
                    <span><span class="rez-lejant-nokta rez-lejant-dolu"></span> Dolu</span>
                </div>
                <p class="control-bar-not">En fazla 4 koltuk seçebilirsin.</p>

                <div class="summary-card">
                    <div class="summary-card-title">Seçilen Koltuklar (Maks. 4)</div>
                    <div class="rez-secili-koltuklar" id="secili-koltuklar-ozet" style="display:none">
                        <span class="rez-secili-liste" id="secili-koltuklar-liste"></span>
                    </div>
                    <p class="rez-bos-mesaj" id="secili-koltuklar-bos-mesaj">Henüz koltuk seçmediniz.</p>
                </div>
            </div>

            <div>
                <button type="button" class="btn-devam" id="adim3-devam-btn" disabled onclick="koltukKontrolEdipDevamEt()">Devam Et</button>
                <a href="rezervasyonlarim.php?kaynak=akis" class="rez-sidebar-rezervasyonlarim" onclick="rezervasyonlarimaGecisHazirla()">🎟️ Rezervasyonlarım</a>
            </div>
        </aside>

        <!-- SIDEBAR - Adim 3: Kimlik -->
        <aside class="sidebar-summary" id="sidebar-3" style="display:none">
            <div>
                <span class="info-tag" id="sidebar-etkinlik-tag-2">ETKİNLİK</span>
                <h2 class="event-heading" id="sidebar-etkinlik-adi-2"></h2>
                <p class="event-venue" id="sidebar-etkinlik-yer-2"></p>
                <div class="policy-box">
                    <div class="policy-title">KİMLİK DOĞRULAMA</div>
                    <p class="info-desc" style="margin-bottom:0">Rezervasyonu tamamlamak için TC kimlik numaranı ve doğum tarihini doğrulaman gerekiyor.</p>
                </div>
            </div>
            <a href="rezervasyonlarim.php?kaynak=akis" class="rez-sidebar-rezervasyonlarim" onclick="rezervasyonlarimaGecisHazirla()">🎟️ Rezervasyonlarım</a>
        </aside>

        <!-- SIDEBAR - Adim 4 -->
        <aside class="sidebar-summary" id="sidebar-4" style="display:none">
            <div>
                <span class="info-tag">TAMAMLANDI</span>
                <h2 class="event-heading">Rezervasyonun Hazır</h2>
                <p class="info-desc">QR kodlarını kaydet veya ekran görüntüsü al. Girişte bu kodlar okutulacak.</p>
            </div>
            <a href="rezervasyonlarim.php?kaynak=akis" class="rez-sidebar-rezervasyonlarim" onclick="rezervasyonlarimaGecisHazirla()">🎟️ Rezervasyonlarım</a>
        </aside>

        <main class="content-area">

            <!-- ADIM 1: Etkinlik Secimi -->
            <div class="content-box" id="adim-1-icerik">
                <h2 class="form-header-title">Etkinlik Seç</h2>
                <p class="form-header-sub">Katılmak istediğin etkinliği seç.</p>

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
            </div>

            <!-- ADIM 2: Koltuk Secimi -->
            <div class="adim3-govde" id="adim-2-icerik" style="display:none">
                <button type="button" class="rez-geri-link" onclick="adimGit(1)">← Geri dön</button>
                <div class="auditorium-container">
                    <div class="rez-sahne">SAHNE</div>
                    <div class="rez-koltuk-harita" id="koltuk-harita">
                        <p class="rez-bos-mesaj">Yükleniyor...</p>
                    </div>
                </div>
            </div>

            <!-- ADIM 3: Kimlik Dogrulama -->
            <div class="content-box" id="adim-3-icerik" style="display:none">
                <button type="button" class="rez-geri-link" onclick="adimGit(2)">← Geri dön</button>
                <h2 class="form-header-title">Kimlik Doğrulama</h2>
                <p class="form-header-sub">Rezervasyonu tamamlamak için TC kimlik numaranı ve doğum tarihini gir.</p>

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

                <button type="button" class="btn-devam" id="adim1-devam-btn" style="display:none" onclick="rezervasyonuKaydet()">Devam Et</button>
            </div>

            <!-- ADIM 4: Onay -->
            <div class="content-box content-box-genis" id="adim-4-icerik" style="display:none">
                <div class="rez-onay-basarili" id="onay-basarili-alani" style="display:none">
                    <div class="rez-onay-buyuk-ikon">✓</div>
                    <h2 class="form-header-title" style="text-align:center">Rezervasyonun Oluşturuldu</h2>
                    <p class="form-header-sub" style="text-align:center" id="onay-etkinlik-ozeti"></p>

                    <div class="rez-qr-listesi" id="onay-qr-listesi"></div>

                    <p class="rez-onay-not">Bu ekranı kaydet veya ekran görüntüsü al — girişte QR kodların okutulacak. Birden fazla koltuk seçtiysen, her kodu ayrı kişiyle paylaşabilirsin.</p>
                </div>
                <div id="onay-yukleniyor-alani">
                    <p class="rez-bos-mesaj">Rezervasyonun oluşturuluyor...</p>
                </div>
            </div>

        </main>
    </div>

    <script>
        var mevcutAdim = 1;
        var enYuksekUlasilanAdim = 1;

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
        var dogrulananIsim = '';
        var dogrulananVatandasId = null;
        var dogrulananTc = '';
        var dogrulananDogum = '';

        var oturumKullanici = <?= json_encode($_SESSION['rezervasyon_kullanici'] ?? null, JSON_UNESCAPED_UNICODE) ?>;
        var rezervasyonlardanGeldi = <?= json_encode($rezervasyonlardanGeldi && $geriGecisIzni) ?>;

        function rezervasyonlarimaGecisHazirla() {
            document.cookie = 'rezervasyon_giris_izni=1; path=/; SameSite=Lax';
        }

        function rezervasyonKullaniciBilgileriniDoldur() {
            if (!rezervasyonlardanGeldi || !oturumKullanici || !oturumKullanici.tc_no || !oturumKullanici.dogum_tarihi) {
                return;
            }

            dogrulananVatandasId = oturumKullanici.id || null;
            dogrulananIsim = (oturumKullanici.ad || '') + ' ' + (oturumKullanici.soyad || '');
            dogrulananTc = oturumKullanici.tc_no;
            dogrulananDogum = oturumKullanici.dogum_tarihi;

            var tcInput = document.getElementById('tc_no');
            var dogumInput = document.getElementById('dogum_tarihi');
            if (tcInput) tcInput.value = dogrulananTc;
            if (dogumInput) dogumInput.value = dogrulananDogum;

            var parcalar = dogrulananDogum.split('-');
            if (parcalar.length === 3) {
                document.getElementById('dogum-gun').value = parcalar[2];
                document.getElementById('dogum-ay').value = parcalar[1];
                document.getElementById('dogum-yil').value = parcalar[0];
                document.getElementById('dogum-gun-buton').textContent = String(parseInt(parcalar[2], 10));
                document.getElementById('dogum-ay-buton').textContent = ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'][parseInt(parcalar[1], 10) - 1];
                document.getElementById('dogum-yil-buton').textContent = parcalar[0];
            }

            document.getElementById('ad-soyad-deger').textContent = dogrulananIsim.trim();
            document.getElementById('ad-soyad-goster').style.display = 'flex';
            document.getElementById('adim1-devam-btn').style.display = 'block';
        }

        function adimGit(hedefAdim, gecmiseEkle) {
            if (hedefAdim > enYuksekUlasilanAdim) {
                enYuksekUlasilanAdim = hedefAdim;
            }

            if (gecmiseEkle !== false) {
                history.pushState({ adim: hedefAdim }, '', '#adim-' + hedefAdim);
            }

            for (var i = 1; i <= 4; i++) {
                document.getElementById('adim-' + i + '-icerik').style.display = (i === hedefAdim) ? (i === 2 ? 'flex' : 'block') : 'none';
                document.getElementById('sidebar-' + i).style.display = (i === hedefAdim) ? 'flex' : 'none';

                var stepEl = document.getElementById('stepper-' + i);
                var noktaEl = document.getElementById('stepper-nokta-' + i);
                stepEl.classList.remove('step-aktif', 'step-tamamlandi');
                stepEl.classList.toggle('step-tiklanabilir', i <= enYuksekUlasilanAdim && i !== hedefAdim);

                if (i < hedefAdim) {
                    stepEl.classList.add('step-tamamlandi');
                    noktaEl.innerHTML = '✓';
                } else if (i === hedefAdim) {
                    stepEl.classList.add('step-aktif');
                    noktaEl.textContent = i;
                } else {
                    noktaEl.textContent = i;
                }
            }
            mevcutAdim = hedefAdim;
        }

        function stepperTiklandi(hedefAdim) {
            if (hedefAdim <= enYuksekUlasilanAdim) {
                adimGit(hedefAdim);
            }
        }

        window.addEventListener('popstate', function (e) {
            var adim = (e.state && e.state.adim) ? e.state.adim : 1;
            if (adim <= enYuksekUlasilanAdim) {
                adimGit(adim, false);
            }
        });

        history.replaceState({ adim: 1 }, '', '#adim-1');

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

            if (acikMiydi) {
                liste.style.display = 'none';
                return;
            }

            var buton = document.getElementById(listeId.replace('-liste', '-buton'));
            if (buton) {
                if (liste.parentNode !== document.body) {
                    document.body.appendChild(liste);
                    liste.classList.add('ozel-secici-liste-tasindi');
                }
                var dikdortgen = buton.getBoundingClientRect();
                liste.style.top = (dikdortgen.bottom + 4) + 'px';
                liste.style.left = dikdortgen.left + 'px';
                liste.style.width = dikdortgen.width + 'px';
            }

            liste.style.display = 'block';
            liste.scrollTop = 0;
        }

        window.addEventListener('scroll', function (e) {
            if (e.target && e.target.classList && e.target.classList.contains('ozel-secici-liste')) {
                return;
            }
            document.querySelectorAll('.ozel-secici-liste').forEach(function (el) {
                el.style.display = 'none';
            });
        }, true);

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
                            dogrulananIsim = veri.ad + ' ' + veri.soyad;
                            dogrulananVatandasId = veri.id;
                            dogrulananTc = tc;
                            dogrulananDogum = dogum;
                            document.getElementById('ad-soyad-deger').textContent = dogrulananIsim;
                            document.getElementById('ad-soyad-goster').style.display = 'flex';
                            document.getElementById('adim1-devam-btn').style.display = 'block';
                        } else if (veri.durum === 'gecersiz_tc') {
                            gosterDurumMesaji('Girdiğin TC kimlik numarası geçersiz. Lütfen kontrol et.');
                        } else if (veri.durum === 'eslesmiyor') {
                            gosterDurumMesaji('Bilgilerde bir yanlışlık var. Lütfen TC kimlik numaranı ve doğum tarihini kontrol et.');
                            document.getElementById('tc_no').value = '';
                            document.getElementById('dogum-gun').value = '';
                            document.getElementById('dogum-ay').value = '';
                            document.getElementById('dogum-yil').value = '';
                            document.getElementById('dogum-gun-buton').textContent = 'Gün';
                            document.getElementById('dogum-ay-buton').textContent = 'Ay';
                            document.getElementById('dogum-yil-buton').textContent = 'Yıl';
                            document.getElementById('dogum_tarihi').value = '';
                        } else if (veri.durum === 'bulunamadi') {
                            gosterDurumMesaji('Bu bilgilerle bir kayıt bulunamadı. Bilgilerini kontrol edebilir ya da aşağıdan yeni kayıt oluşturabilirsin.');
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
            document.getElementById('yeni-kayit-alani').style.display = 'none';
            document.getElementById('dogum-gun-buton').classList.remove('input-hata');
            document.getElementById('dogum-ay-buton').classList.remove('input-hata');
            document.getElementById('dogum-yil-buton').classList.remove('input-hata');
            if (tcAlgoritmaGecerliMi(document.getElementById('tc_no').value)) {
                document.getElementById('tc_no').classList.remove('input-hata');
            }
        }

        function gosterDurumMesaji(mesaj) {
            gosterToast(mesaj);
            document.getElementById('tc_no').classList.add('input-hata');
            document.getElementById('dogum-gun-buton').classList.add('input-hata');
            document.getElementById('dogum-ay-buton').classList.add('input-hata');
            document.getElementById('dogum-yil-buton').classList.add('input-hata');
        }

        var toastZamanlayici = null;

        function gosterToast(mesaj) {
            var toast = document.getElementById('rez-toast');
            clearTimeout(toastZamanlayici);
            toast.textContent = mesaj;
            toast.classList.add('rez-toast-goster');
            toastZamanlayici = setTimeout(function () {
                toast.classList.remove('rez-toast-goster');
            }, 4000);
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
                    dogrulananIsim = veri.ad + ' ' + veri.soyad;
                    dogrulananVatandasId = veri.id;
                    dogrulananTc = tc;
                    dogrulananDogum = dogum;
                    document.getElementById('ad-soyad-deger').textContent = dogrulananIsim;
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
            var etkinlikDetayMetni = secilenEtkinlik.yer + (secilenEtkinlik.tarih ? ' · ' + secilenEtkinlik.tarih + ' ' + secilenEtkinlik.saat : '');
            document.getElementById('sidebar-etkinlik-adi-2').textContent = secilenEtkinlik.ad;
            document.getElementById('sidebar-etkinlik-yer-2').textContent = etkinlikDetayMetni;
            document.getElementById('sidebar-etkinlik-adi').textContent = secilenEtkinlik.ad;
            document.getElementById('sidebar-etkinlik-yer').textContent = etkinlikDetayMetni;
            seciliKoltuklar = [];
            adimGit(2);
            koltukHaritasiniYukle();
        }

        var seciliKoltuklar = [];
        var MAKS_KOLTUK = 4;

        function koltukHaritasiniYukle() {
            var harita = document.getElementById('koltuk-harita');
            harita.innerHTML = '<p class="rez-bos-mesaj">Yükleniyor...</p>';
            document.getElementById('adim3-devam-btn').disabled = true;
            seciliKoltuklarOzetiGuncelle();

            fetch('koltuk-liste.php?etkinlik_id=' + secilenEtkinlik.id)
                .then(function (yanit) { return yanit.json(); })
                .then(function (veri) {
                    if (veri.hata) {
                        harita.innerHTML = '<p class="rez-bos-mesaj">Koltuklar yüklenemedi.</p>';
                        return;
                    }
                    koltukHaritasiniCiz(veri);
                })
                .catch(function (hata) {
                    harita.innerHTML = '<p class="rez-bos-mesaj">Bir hata oluştu, konsolu kontrol et.</p>';
                    console.error('Koltuk haritası yüklenemedi:', hata);
                });
        }

        function koltukHaritasiniCiz(veri) {
            var satirlar = {};
            veri.koltuklar.forEach(function (k) {
                var harf = k.koltuk_no.charAt(0);
                if (!satirlar[harf]) satirlar[harf] = [];
                satirlar[harf].push(k);
            });

            var satirBasi = veri.satir_basi_koltuk ? parseInt(veri.satir_basi_koltuk, 10) : null;
            var koridorPozisyonlari = [];
            if (veri.koridor_sonrasi !== null && veri.koridor_sonrasi !== '') {
                koridorPozisyonlari = veri.koridor_sonrasi.split(',').map(Number).filter(function (v) { return v >= 0; });
            } else if (satirBasi && satirBasi > 1) {
                koridorPozisyonlari = [Math.floor(satirBasi / 2)];
            } else {
                koridorPozisyonlari = [5];
            }

            var html = '';
            var enUzunSatir = 0;
            var satirHarfleri = Object.keys(satirlar).sort();
            var toplamSatirSayisi = satirHarfleri.length;
            var ortaSatirIndex = Math.floor((toplamSatirSayisi - 1) / 2);
            var mevcutYukseklik = toplamSatirSayisi * 46;
            var koridorYaziBoyutu = Math.max(7, Math.min(16, Math.floor(mevcutYukseklik / (7 * 1.3))));
            var koridorDikeyKaydirma = (toplamSatirSayisi % 2 === 0) ? 23 : 0;

            satirHarfleri.forEach(function (harf, satirIndex) {
                var satirUzunlugu = satirlar[harf].length;
                var koridorSayisiBuSatirda = koridorPozisyonlari.filter(function (p) { return p >= 0 && p <= satirUzunlugu; }).length;
                enUzunSatir = Math.max(enUzunSatir, satirUzunlugu + koridorSayisiBuSatirda);

                var buSatirOrtaMi = (satirIndex === ortaSatirIndex);
                var koridorEtiketHtml = buSatirOrtaMi
                    ? '<span style="font-size:' + koridorYaziBoyutu + 'px; margin-top:' + koridorDikeyKaydirma + 'px;">KORİDOR</span>'
                    : '';
                var koridorSinifi = 'rez-koridor-bosluk' + (buSatirOrtaMi ? ' rez-koridor-bosluk-etiketli' : '');

                html += '<div class="rez-koltuk-satiri">';
                html += '<span class="rez-satir-etiket">' + harf + '</span>';
                html += '<div class="rez-satir-koltuklari">';

                if (koridorPozisyonlari.indexOf(0) !== -1) {
                    html += '<div class="' + koridorSinifi + '">' + koridorEtiketHtml + '</div>';
                }

                satirlar[harf].forEach(function (k, index) {
                    if (index > 0 && koridorPozisyonlari.indexOf(index) !== -1) {
                        html += '<div class="' + koridorSinifi + '">' + koridorEtiketHtml + '</div>';
                    }
                    var numara = k.koltuk_no.substring(harf.length);
                    var durumSinif = 'rez-koltuk-bos';
                    var tiklanamaz = '';
                    if (k.durum !== 'bos') {
                        durumSinif = 'rez-koltuk-dolu';
                        tiklanamaz = 'disabled';
                    }
                    html += '<button type="button" class="rez-koltuk-kutu ' + durumSinif + '" data-id="' + k.id + '" data-no="' + k.koltuk_no + '" ' + tiklanamaz + ' onclick="koltukSec(this)"><span>' + numara + '</span></button>';
                });

                if (koridorPozisyonlari.indexOf(satirUzunlugu) !== -1) {
                    html += '<div class="' + koridorSinifi + '">' + koridorEtiketHtml + '</div>';
                }

                html += '</div>';
                html += '<span class="rez-satir-etiket">' + harf + '</span>';
                html += '</div>';
            });

            document.getElementById('koltuk-harita').innerHTML = html;
            koltukBoyutunuSigdir(enUzunSatir);
        }

        function koltukBoyutunuSigdir(sutunSayisi) {
            var harita = document.getElementById('koltuk-harita');
            if (sutunSayisi <= 0) return;

            var mevcutGenislik = harita.parentElement.clientWidth - 60;
            var varsayilanKutu = 34;
            var varsayilanBosluk = 10;
            var minimumKutu = 26;
            var etiketGenisligi = 44;

            var gerekliGenislik = sutunSayisi * (varsayilanKutu + varsayilanBosluk) + etiketGenisligi;

            if (gerekliGenislik > mevcutGenislik) {
                var oran = (mevcutGenislik - etiketGenisligi) / (sutunSayisi * (varsayilanKutu + varsayilanBosluk));
                oran = Math.max(oran, minimumKutu / varsayilanKutu);
                var yeniKutu = Math.max(minimumKutu, Math.floor(varsayilanKutu * oran));
                var yeniBosluk = Math.max(6, Math.floor(varsayilanBosluk * oran));
                var yeniYaziBoyutu = Math.max(10, Math.floor(11 * oran));

                harita.style.setProperty('--koltuk-boyutu', yeniKutu + 'px');
                harita.style.setProperty('--koltuk-boslugu', yeniBosluk + 'px');
                harita.style.setProperty('--koltuk-yazi-boyutu', yeniYaziBoyutu + 'px');

                var hedefGenislik = sutunSayisi * (yeniKutu + yeniBosluk) + etiketGenisligi;
                if (hedefGenislik > mevcutGenislik) {
                    harita.style.overflowX = 'auto';
                    harita.style.width = '100%';
                } else {
                    harita.style.overflowX = '';
                    harita.style.width = '';
                }
            } else {
                harita.style.removeProperty('--koltuk-boyutu');
                harita.style.removeProperty('--koltuk-boslugu');
                harita.style.removeProperty('--koltuk-yazi-boyutu');
                harita.style.overflowX = '';
                harita.style.width = '';
            }
        }

        function koltukSec(buton) {
            var id = buton.dataset.id;
            var no = buton.dataset.no;
            var index = seciliKoltuklar.findIndex(function (k) { return k.id === id; });

            if (index !== -1) {
                seciliKoltuklar.splice(index, 1);
                buton.classList.remove('rez-koltuk-secili');
            } else {
                if (seciliKoltuklar.length >= MAKS_KOLTUK) {
                    gosterToast('En fazla ' + MAKS_KOLTUK + ' koltuk seçebilirsin.');
                    return;
                }
                seciliKoltuklar.push({ id: id, no: no });
                buton.classList.add('rez-koltuk-secili');
            }
            seciliKoltuklarOzetiGuncelle();
        }

        function koltukKontrolEdipDevamEt() {
            if (seciliKoltuklar.length === 0) return;

            var devamBtn = document.getElementById('adim3-devam-btn');
            devamBtn.disabled = true;

            fetch('koltuk-liste.php?etkinlik_id=' + secilenEtkinlik.id)
                .then(function (yanit) { return yanit.json(); })
                .then(function (veri) {
                    devamBtn.disabled = false;

                    if (veri.hata) {
                        gosterToast('Koltuklar kontrol edilemedi, tekrar dene.');
                        return;
                    }

                    var durumlar = {};
                    veri.koltuklar.forEach(function (k) {
                        durumlar[k.id] = k.durum;
                    });

                    var hepsiBosMu = seciliKoltuklar.every(function (k) {
                        return durumlar[k.id] === 'bos';
                    });

                    if (!hepsiBosMu) {
                        gosterToast('Seçtiğin koltuklardan biri az önce başkası tarafından alındı. Lütfen tekrar seç.');
                        seciliKoltuklar = [];
                        koltukHaritasiniYukle();
                        return;
                    }

                    adimGit(3);
                })
                .catch(function () {
                    devamBtn.disabled = false;
                    gosterToast('Bir hata oluştu, tekrar dene.');
                });
        }

        function seciliKoltuklarOzetiGuncelle() {
            var ozet = document.getElementById('secili-koltuklar-ozet');
            var liste = document.getElementById('secili-koltuklar-liste');
            var bosMesaj = document.getElementById('secili-koltuklar-bos-mesaj');
            var devamBtn = document.getElementById('adim3-devam-btn');

            if (seciliKoltuklar.length === 0) {
                ozet.style.display = 'none';
                bosMesaj.style.display = 'block';
                devamBtn.disabled = true;
                return;
            }

            liste.innerHTML = seciliKoltuklar.map(function (k) {
                return '<span class="rez-koltuk-etiketi">' + k.no +
                    '<button type="button" class="rez-koltuk-etiketi-sil" onclick="sidebardanKoltukKaldir(\'' + k.id + '\')" aria-label="Koltuğu kaldır">✕</button>' +
                    '</span>';
            }).join('');
            ozet.style.display = 'block';
            bosMesaj.style.display = 'none';
            devamBtn.disabled = false;
        }

        function sidebardanKoltukKaldir(id) {
            var index = seciliKoltuklar.findIndex(function (k) { return k.id === id; });
            if (index === -1) return;

            seciliKoltuklar.splice(index, 1);

            var buton = document.querySelector('.rez-koltuk-kutu[data-id="' + id + '"]');
            if (buton) {
                buton.classList.remove('rez-koltuk-secili');
            }

            seciliKoltuklarOzetiGuncelle();
        }

        function rezervasyonuKaydet() {
            if (!dogrulananVatandasId || !secilenEtkinlik || seciliKoltuklar.length === 0) {
                gosterToast('Bir şeyler eksik, lütfen baştan dene.');
                return;
            }

            var devamBtn = document.getElementById('adim1-devam-btn');
            devamBtn.disabled = true;
            devamBtn.textContent = 'Kaydediliyor...';

            document.getElementById('onay-basarili-alani').style.display = 'none';
            document.getElementById('onay-yukleniyor-alani').style.display = 'block';

            var veriler = new FormData();
            veriler.append('vatandas_id', dogrulananVatandasId);
            veriler.append('etkinlik_id', secilenEtkinlik.id);
            seciliKoltuklar.forEach(function (k) {
                veriler.append('koltuk_id[]', k.id);
            });

            fetch('rezervasyon-kaydet.php', { method: 'POST', body: veriler })
                .then(function (yanit) { return yanit.json(); })
                .then(function (veri) {
                    devamBtn.disabled = false;
                    devamBtn.textContent = 'Devam Et';
                    document.getElementById('onay-yukleniyor-alani').style.display = 'block';

                    if (!veri.basarili) {
                        gosterToast(veri.hata);
                        seciliKoltuklar = [];
                        adimGit(2);
                        koltukHaritasiniYukle();
                        return;
                    }

                    onayEkraniniDoldur(veri);
                    seciliKoltuklar = [];
                    koltukHaritasiniYukle();
                    adimGit(4);
                })
                .catch(function (hata) {
                    devamBtn.disabled = false;
                    devamBtn.textContent = 'Devam Et';
                    gosterToast('Bir hata oluştu, tekrar dene.');
                    console.error(hata);
                });
        }

        function onayEkraniniDoldur(veri) {
            document.getElementById('onay-yukleniyor-alani').style.display = 'none';
            document.getElementById('onay-basarili-alani').style.display = 'block';

            document.getElementById('onay-etkinlik-ozeti').textContent =
                veri.etkinlik_adi + ' — ' + veri.etkinlik_yer +
                (veri.etkinlik_tarih ? ' · ' + veri.etkinlik_tarih + ' ' + veri.etkinlik_saat : '');

            sonKaydedilenRezervasyonlar = veri.rezervasyonlar;
            sonEtkinlikBilgisi = {
                adi: veri.etkinlik_adi,
                yer: veri.etkinlik_yer,
                tarih: veri.etkinlik_tarih,
                saat: veri.etkinlik_saat
            };
            sonKisiAdi = veri.isim || dogrulananIsim;
            qrKartlariniCiz(veri.rezervasyonlar);
        }

        var sonEtkinlikBilgisi = {};
        var sonKisiAdi = '';

        var sonKaydedilenRezervasyonlar = [];

        function qrIndir(buton, koltukNo) {
            var kart = buton.closest('.ticket-container');
            if (!kart) return;

            buton.style.display = 'none';
            var iptalAlani = kart.querySelector('.rez-iptal-alani');
            if (iptalAlani) iptalAlani.style.display = 'none';

            html2canvas(kart, { backgroundColor: '#ffffff', scale: 2 }).then(function (canvas) {
                var link = document.createElement('a');
                link.download = 'koltuk-' + koltukNo + '-bilet.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
                buton.style.display = '';
                if (iptalAlani) iptalAlani.style.display = '';
            });
        }

        function qrKartlariniCiz(rezervasyonlar) {
            var kutu = document.getElementById('onay-qr-listesi');
            kutu.innerHTML = '';
            kutu.className = 'rez-qr-listesi rez-qr-listesi-genis';

            rezervasyonlar.forEach(function (r, index) {
                var kart = document.createElement('div');

                kart.className = 'ticket-container';
                kart.innerHTML =
                    '<div class="ticket-left">' +
                    '<div class="ticket-image-wrapper">' +
                    '<img src="https://images.unsplash.com/photo-1514306191717-452ec28c7814?w=500&q=80" alt="Etkinlik Görseli" class="ticket-image">' +
                    '</div>' +
                    '<div class="ticket-info">' +
                    '<h2 class="event-title">' + sonEtkinlikBilgisi.adi + '</h2>' +
                    '<div class="info-group">' +
                    '<span class="info-label">Etkinlik Detayları</span>' +
                    '<div class="info-row"><span class="row-key">Salon:</span><span class="row-val">' + sonEtkinlikBilgisi.yer + '</span></div>' +
                    '<div class="info-row"><span class="row-key">Tarih:</span><span class="row-val">' + sonEtkinlikBilgisi.tarih + ' ' + sonEtkinlikBilgisi.saat + '</span></div>' +
                    '<div class="info-row"><span class="row-key">Koltuk:</span><span class="row-val highlight">' + r.koltuk_no + '</span></div>' +
                    '</div>' +
                    '<div class="ticket-owner">' +
                    '<label class="owner-label">Bilet Sahibi</label>' +
                    '<div class="owner-box">' + sonKisiAdi + '</div>' +
                    '</div>' +
                    '</div>' +
                    '</div>' +
                    '<div class="ticket-divider">' +
                    '<div class="notch notch-top"></div>' +
                    '<div class="notch notch-orta"></div>' +
                    '<div class="dashed-line"></div>' +
                    '<div class="notch notch-bottom"></div>' +
                    '</div>' +
                    '<div class="ticket-right">' +
                    '<div class="seat-badge">Koltuk ' + r.koltuk_no + '</div>' +
                    '<div class="qr-wrapper" id="onay-qr-' + index + '"></div>' +
                    '<div class="ticket-ref-area">' +
                    '<span class="ref-title">REFERANS KODU</span>' +
                    '<span class="ref-code">' + r.referans_kodu + '</span>' +
                    '<button type="button" class="btn-download" onclick="qrIndir(this, \'' + r.koltuk_no + '\')">İndir ⬇</button>' +
                    '</div>' +
                    '</div>';

                kutu.appendChild(kart);

                new QRCode(document.getElementById('onay-qr-' + index), {
                    text: r.qr_token,
                    width: 130,
                    height: 130,
                    colorDark: '#1F1F1F',
                    colorLight: '#ffffff'
                });
            });
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

        // Sadece Rezervasyonlarım -> Geri dön akışında ve yalnızca etkinlik
        // seçiminden önce gelindiyse bilgileri bir kez doldur.
        if (rezervasyonlardanGeldi) {
            rezervasyonKullaniciBilgileriniDoldur();
            // Tek seferlik geçişi URL'den kaldır; yenilemede tekrar otomatik giriş olmasın.
            history.replaceState({ adim: 1 }, '', 'rezervasyon.php#adim-1');
        }
    </script>

</body>
</html>