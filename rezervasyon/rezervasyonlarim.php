<?php
require '../includes/db.php';

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
    <title>Rezervasyonlarım</title>
    <link rel="stylesheet" href="../css/rezervasyon.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</head>
<body>

    <div class="rez-toast" id="rez-toast"></div>

    <nav class="top-navbar">
        <div class="brand">
            <h1>Belediye Etkinlik Rezervasyonu</h1>
            <span>Rezervasyonlarım</span>
        </div>
    </nav>

    <div class="main-workspace">
        <main class="content-area" style="justify-content:flex-start">

            <div class="content-box" id="sorgu-icerik">
                <h2 class="form-header-title">Rezervasyonlarım</h2>
                <p class="form-header-sub">Geçmiş ve mevcut rezervasyonlarını görmek için TC kimlik numaranı ve doğum tarihini gir.</p>

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
            </div>

            <div class="content-box" id="sonuc-icerik" style="display:none; max-width: 1160px;">
                <a href="rezervasyon.php" class="rez-geri-link">← Yeni Rezervasyon</a>
                <div class="rez-baslik-satiri">
                    <div>
                        <h2 class="form-header-title" id="sonuc-baslik"></h2>
                        <p class="form-header-sub" id="sonuc-alt-baslik"></p>
                    </div>
                    <div class="rez-filtre-arama-grubu">
                        <div class="rez-arama-sarmalayici" id="sonuc-arama-sarmalayici" style="display:none">
                            <span class="rez-arama-ikon"></span>
                            <input type="text" id="sonuc-arama" placeholder="Etkinlik, tarih, koltuk ara..." oninput="sonucAramaUygula()" autocomplete="off">
                        </div>
                        <div class="ozel-secici-sarmalayici ozel-secici-kompakt ozel-secici-filtre ozel-secici-filtre-buyuk" id="filtre-sarmalayici" style="display:none">
                            <button type="button" class="ozel-secici-buton btn-kucuk-filtre" id="filtre-buton" onclick="ozelSeciciAcKapa('filtre-liste')">Tüm etkinlikler</button>
                            <div class="ozel-secici-liste" id="filtre-liste"></div>
                        </div>
                    </div>
                </div>
                <div class="rez-qr-listesi" id="rezervasyon-listesi"></div>
            </div>

        </main>
    </div>

    <script>
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
            dogumTarihiGuncelle();
        }

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.ozel-secici-sarmalayici')) {
                document.querySelectorAll('.ozel-secici-liste').forEach(function (el) {
                    el.style.display = 'none';
                });
            }
        });

        var tcTimer = null;

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
            sorgulamayiTetikle();
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
            sorgulamayiTetikle();
        });

        function sorgulamayiTetikle() {
            clearTimeout(tcTimer);
            var tc = document.getElementById('tc_no').value;
            var dogum = document.getElementById('dogum_tarihi').value;

            document.getElementById('sonuc-icerik').style.display = 'none';

            if (tc.length !== 11 || dogum.length !== 10 || !tcAlgoritmaGecerliMi(tc)) {
                return;
            }

            tcTimer = setTimeout(function () {
                fetch('vatandas-rezervasyonlari.php?tc_no=' + encodeURIComponent(tc) + '&dogum_tarihi=' + encodeURIComponent(dogum))
                    .then(function (yanit) { return yanit.json(); })
                    .then(function (veri) {
                        if (veri.durum === 'bulundu') {
                            sonuclariGoster(veri);
                        } else if (veri.durum === 'gecersiz_tc') {
                            gosterToast('Girdiğin TC kimlik numarası geçersiz. Lütfen kontrol et.');
                        } else {
                            gosterToast('Bu bilgilerle bir kayıt bulunamadı. Bilgilerini kontrol et.');
                        }
                    })
                    .catch(function (hata) {
                        gosterToast('Bir hata oluştu, tekrar dene.');
                        console.error(hata);
                    });
            }, 400);
        }

        var tumRezervasyonlar = [];
        var aktifFiltreEtkinlik = '';

        function sonuclariGoster(veri) {
            document.getElementById('sonuc-baslik').textContent = 'Merhaba, ' + veri.ad + ' ' + veri.soyad;
            document.getElementById('sorgu-icerik').style.display = 'none';
            tumRezervasyonlar = veri.rezervasyonlar;
            aktifFiltreEtkinlik = '';

            var filtreSarmalayici = document.getElementById('filtre-sarmalayici');
            var filtreListe = document.getElementById('filtre-liste');
            var aramaKutu = document.getElementById('sonuc-arama');
            var aramaSarmalayici = document.getElementById('sonuc-arama-sarmalayici');
            aramaKutu.value = '';

            if (veri.rezervasyonlar.length === 0) {
                document.getElementById('sonuc-alt-baslik').textContent = 'Henüz bir rezervasyonun yok.';
                filtreSarmalayici.style.display = 'none';
                aramaSarmalayici.style.display = 'none';
            } else {
                var etkinlikAdlari = [];
                veri.rezervasyonlar.forEach(function (r) {
                    if (etkinlikAdlari.indexOf(r.etkinlik_adi) === -1) {
                        etkinlikAdlari.push(r.etkinlik_adi);
                    }
                });

                var filtreHtml = '<div class="ozel-secici-madde" data-deger="" data-id="filtre" onclick="filtreSec(this)">Tüm etkinlikler</div>';
                etkinlikAdlari.forEach(function (ad) {
                    filtreHtml += '<div class="ozel-secici-madde" data-deger="' + ad + '" data-id="filtre" onclick="filtreSec(this)">' + ad + '</div>';
                });
                filtreListe.innerHTML = filtreHtml;
                filtreSarmalayici.style.display = etkinlikAdlari.length > 1 ? 'block' : 'none';
                document.getElementById('filtre-buton').textContent = 'Tüm etkinlikler';
                aramaSarmalayici.style.display = 'block';

                qrKartlariniOlustur(veri.rezervasyonlar);
            }

            document.getElementById('sonuc-icerik').style.display = 'block';
        }

        function filtreSec(madde) {
            document.getElementById('filtre-buton').textContent = madde.textContent;
            document.getElementById('filtre-liste').style.display = 'none';
            aktifFiltreEtkinlik = madde.dataset.deger;
            sonucListesiniUygula();
        }

        function sonucAramaUygula() {
            sonucListesiniUygula();
        }

        function sonucListesiniUygula() {
            var terim = document.getElementById('sonuc-arama').value.toLocaleLowerCase('tr');

            var sonuc = tumRezervasyonlar.filter(function (r) {
                if (aktifFiltreEtkinlik !== '' && r.etkinlik_adi !== aktifFiltreEtkinlik) return false;
                if (terim === '') return true;
                var metin = (r.etkinlik_adi + ' ' + r.yer + ' ' + r.tarih + ' ' + r.saat + ' ' + r.koltuk_no + ' ' + r.referans_kodu).toLocaleLowerCase('tr');
                return metin.indexOf(terim) !== -1;
            });

            qrKartlariniOlustur(sonuc);
        }

        function qrKartlariniOlustur(rezervasyonlar) {
            var kutu = document.getElementById('rezervasyon-listesi');
            kutu.innerHTML = '';
            document.getElementById('sonuc-alt-baslik').textContent = rezervasyonlar.length + ' rezervasyon bulundu.';

            if (rezervasyonlar.length === 0) {
                kutu.innerHTML = '<p class="rez-bos-mesaj">Eşleşen bir rezervasyon bulunamadı.</p>';
                return;
            }

            rezervasyonlar.forEach(function (r, index) {
                var kart = document.createElement('div');
                kart.className = 'ticket-container ticket-container-cekmece';
                kart.innerHTML =
                    (r.giris_yapildi_mi ? '<span class="rez-giris-rozeti rez-giris-rozeti-cekmece">Giriş yapıldı</span>' : '') +
                    '<div class="ticket-left">' +
                    '<div class="ticket-image-wrapper">' +
                    '<img src="https://images.unsplash.com/photo-1514306191717-452ec28c7814?w=300&q=80" alt="Etkinlik Görseli" class="ticket-image">' +
                    '</div>' +
                    '<div class="ticket-info">' +
                    '<h2 class="event-title">' + r.etkinlik_adi + '</h2>' +
                    '<div class="info-group">' +
                    '<span class="info-label">Etkinlik Detayları</span>' +
                    '<div class="info-row"><span class="row-key">Salon:</span><span class="row-val">' + r.yer + '</span></div>' +
                    '<div class="info-row"><span class="row-key">Tarih:</span><span class="row-val">' + r.tarih + ' ' + r.saat + '</span></div>' +
                    '<div class="info-row"><span class="row-key">Koltuk:</span><span class="row-val highlight">' + r.koltuk_no + '</span></div>' +
                    '</div>' +
                    '</div>' +
                    '</div>' +
                    '<div class="ticket-divider">' +
                    '<div class="notch notch-top"></div>' +
                    '<div class="dashed-line"></div>' +
                    '<div class="notch notch-bottom"></div>' +
                    '</div>' +
                    '<div class="ticket-right">' +
                    '<div class="seat-badge">Koltuk ' + r.koltuk_no + '</div>' +
                    '<div class="qr-wrapper" id="rez-qr-' + index + '"></div>' +
                    '<div class="ticket-ref-area">' +
                    '<span class="ref-title">REFERANS KODU</span>' +
                    '<span class="ref-code">' + r.referans_kodu + '</span>' +
                    '<button type="button" class="btn-download" onclick="qrIndir(this, \'' + r.koltuk_no + '\')">İndir ⬇</button>' +
                    '</div>' +
                    '</div>';
                kutu.appendChild(kart);

                new QRCode(document.getElementById('rez-qr-' + index), {
                    text: r.qr_token,
                    width: 100,
                    height: 100,
                    colorDark: '#1F1F1F',
                    colorLight: '#ffffff'
                });
            });

            document.getElementById('sonuc-icerik').style.display = 'block';
        }

        function qrIndir(buton, koltukNo) {
            var kart = buton.closest('.rez-qr-karti, .ticket-container');
            if (!kart) return;

            buton.style.display = 'none';

            html2canvas(kart, { backgroundColor: '#ffffff', scale: 2 }).then(function (canvas) {
                var link = document.createElement('a');
                link.download = 'koltuk-' + koltukNo + '-bilet.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
                buton.style.display = '';
            });
        }
    </script>

</body>
</html>