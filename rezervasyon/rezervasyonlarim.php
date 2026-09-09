<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Rezervasyonlarım sadece rezervasyon sayfasındaki butondan gelindiğinde
// mevcut kimlik oturumunu kullanır. Sayfa yenilenirse geçiş izni olmadığı
// için kullanıcı tekrar giriş ekranını görür.
// (Bu kontrol, iptal AJAX isteğinde ÇALIŞMAMALI - o istek sayfa açıldıktan
// sonra geliyor ve tek kullanımlık çerez o zaman zaten tüketilmiş oluyor.)
$ajaxIptalIstegi = ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['islem'] ?? '') === 'iptal');

if (!$ajaxIptalIstegi) {
    $rezervasyonGirisIzni = ($_COOKIE['rezervasyon_giris_izni'] ?? '') === '1';
    if ($rezervasyonGirisIzni) {
        setcookie('rezervasyon_giris_izni', '', time() - 3600, '/');
    } else {
        unset($_SESSION['rezervasyon_kullanici']);
    }
}

require '../includes/db.php';

// Rezervasyon iptali: sadece oturumda doğrulanmış vatandaş kendi rezervasyonunu iptal edebilir.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['islem'] ?? '') === 'iptal') {
    header('Content-Type: application/json; charset=utf-8');

    $oturum = $_SESSION['rezervasyon_kullanici'] ?? null;
    $qr_token = trim($_POST['qr_token'] ?? '');

    if (!$oturum || empty($oturum['id']) || $qr_token === '') {
        echo json_encode(['basarili' => false, 'hata' => 'Oturum bilgileri bulunamadı.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT r.id, r.koltuk_id, r.giris_yapildi_mi, e.etkinlik_tarih
            FROM rezervasyonlar r
            JOIN etkinlikler e ON e.id = r.etkinlik_id
            WHERE r.qr_token = :qr_token
              AND r.vatandas_id = :vatandas_id
            LIMIT 1
        ");
        $stmt->execute([
            'qr_token' => $qr_token,
            'vatandas_id' => (int)$oturum['id']
        ]);
        $rezervasyon = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rezervasyon) {
            $pdo->rollBack();
            echo json_encode(['basarili' => false, 'hata' => 'Rezervasyon bulunamadı.']);
            exit;
        }

        if (!empty($rezervasyon['giris_yapildi_mi'])) {
            $pdo->rollBack();
            echo json_encode(['basarili' => false, 'hata' => 'Giriş yapılmış bir rezervasyon iptal edilemez.']);
            exit;
        }

        if (!empty($rezervasyon['etkinlik_tarih'])) {
            $bugun = new DateTime('today');
            $etkinlikGunu = new DateTime($rezervasyon['etkinlik_tarih']);
            $kalanGun = (int)$bugun->diff($etkinlikGunu)->format('%r%a');

            if ($kalanGun < 3) {
                $pdo->rollBack();
                echo json_encode(['basarili' => false, 'hata' => 'Etkinliğe 3 günden az kaldığı için rezervasyon iptal edilemez.']);
                exit;
            }
        }

        $deletedKontrol = $pdo->query("SHOW COLUMNS FROM rezervasyonlar LIKE 'deleted'")->fetch(PDO::FETCH_ASSOC);

        if ($deletedKontrol) {
            $sil = $pdo->prepare("UPDATE rezervasyonlar SET deleted = 1 WHERE id = :id");
        } else {
            $sil = $pdo->prepare("DELETE FROM rezervasyonlar WHERE id = :id");
        }
        $sil->execute(['id' => (int)$rezervasyon['id']]);

        $koltuk = $pdo->prepare("UPDATE koltuklar SET durum = 'bos' WHERE id = :koltuk_id AND deleted = 0");
        $koltuk->execute(['koltuk_id' => (int)$rezervasyon['koltuk_id']]);

        $pdo->commit();
        echo json_encode(['basarili' => true]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['basarili' => false, 'hata' => 'Rezervasyon iptal edilemedi.']);
    }
    exit;
}

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
$geriUrl = 'rezervasyon.php?rezervasyonlardan=1';
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
                <a href="<?= htmlspecialchars($geriUrl) ?>" class="rez-geri-link" onclick="rezervasyonEtkinligeDonusHazirla()">← Geri dön</a>
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
                <a href="<?= htmlspecialchars($geriUrl) ?>" class="rez-geri-link" onclick="rezervasyonEtkinligeDonusHazirla()">← Geri dön</a>
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

    <div class="rez-iptal-modal-perde" id="rez-iptal-modal-perde" style="display:none"></div>
    <div class="rez-iptal-modal" id="rez-iptal-modal" style="display:none" role="dialog" aria-modal="true" aria-labelledby="rez-iptal-baslik">
        <div class="rez-iptal-ikon">!</div>
        <h2 id="rez-iptal-baslik">Rezervasyonu İptal Et</h2>
        <p>Bu rezervasyonu iptal etmek istediğinize emin misiniz?<br>Bu işlem geri alınamaz.</p>
        <div class="rez-iptal-modal-butonlar">
            <button type="button" class="rez-iptal-modal-vazgec" onclick="iptalOnayiKapat()">Vazgeç</button>
            <button type="button" class="rez-iptal-modal-evet" id="rez-iptal-modal-evet" onclick="rezervasyonuIptalEt()">Evet, İptal Et</button>
        </div>
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

        var oturumTc = <?= json_encode($_SESSION['rezervasyon_kullanici']['tc_no'] ?? '') ?>;
        var oturumDogum = <?= json_encode($_SESSION['rezervasyon_kullanici']['dogum_tarihi'] ?? '') ?>;

        function rezervasyonEtkinligeDonusHazirla() {
            document.cookie = 'rezervasyon_geri_izni=1; path=/; SameSite=Lax';
        }

        function oturumRezervasyonlariniGetir() {
            if (!oturumTc || !oturumDogum) return;

            var sorguIcerik = document.getElementById('sorgu-icerik');
            var sonucIcerik = document.getElementById('sonuc-icerik');

            sorguIcerik.style.display = 'none';
            sonucIcerik.style.display = 'block';
            document.getElementById('sonuc-baslik').textContent = 'Rezervasyonların yükleniyor...';
            document.getElementById('sonuc-alt-baslik').textContent = '';

            fetch('vatandas-rezervasyonlari.php?tc_no=' + encodeURIComponent(oturumTc) + '&dogum_tarihi=' + encodeURIComponent(oturumDogum), { cache: 'no-store' })
                .then(function (yanit) { return yanit.json(); })
                .then(function (veri) {
                    if (veri.durum === 'bulundu') {
                        sonuclariGoster(veri);
                    } else {
                        sorguIcerik.style.display = 'block';
                        sonucIcerik.style.display = 'none';
                        gosterToast('Oturum bilgileri doğrulanamadı. Lütfen tekrar giriş yap.');
                    }
                })
                .catch(function (hata) {
                    sorguIcerik.style.display = 'block';
                    sonucIcerik.style.display = 'none';
                    gosterToast('Rezervasyonlar yüklenirken bir hata oluştu.');
                    console.error(hata);
                });
        }

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
                fetch('vatandas-rezervasyonlari.php?tc_no=' + encodeURIComponent(tc) + '&dogum_tarihi=' + encodeURIComponent(dogum), { cache: 'no-store' })
                    .then(function (yanit) { return yanit.json(); })
                    .then(function (veri) {
                        if (veri.durum === 'bulundu') {
                            oturumTc = tc;
                            oturumDogum = dogum;
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
                    '<div class="notch notch-orta"></div>' +
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
                    (!r.giris_yapildi_mi && rezervasyonIptalEdilebilirMi(r.tarih) ? '<button type="button" class="btn-iptal" onclick="iptalOnayiAc(this, \'' + r.qr_token + '\')">İptal et</button>' : '') +
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

        function rezervasyonIptalEdilebilirMi(tarihStr) {
            if (!tarihStr) return true;
            var parcalar = tarihStr.split('.');
            if (parcalar.length !== 3) return true;

            var etkinlikGunu = new Date(parcalar[2], parcalar[1] - 1, parcalar[0]);
            var bugun = new Date();
            bugun.setHours(0, 0, 0, 0);

            var farkGun = Math.round((etkinlikGunu - bugun) / (1000 * 60 * 60 * 24));
            return farkGun >= 3;
        }

        var aktifIptalReferans = '';

        function iptalOnayiAc(buton, referansKodu) {
            aktifIptalReferans = referansKodu;
            document.getElementById('rez-iptal-modal-perde').style.display = 'block';
            document.getElementById('rez-iptal-modal').style.display = 'block';
        }

        function iptalOnayiKapat() {
            aktifIptalReferans = '';
            document.getElementById('rez-iptal-modal-perde').style.display = 'none';
            document.getElementById('rez-iptal-modal').style.display = 'none';
            document.getElementById('rez-iptal-modal-evet').disabled = false;
            document.getElementById('rez-iptal-modal-evet').textContent = 'Evet, İptal Et';
        }

        function rezervasyonuIptalEt() {
            if (!aktifIptalReferans) return;

            var buton = document.getElementById('rez-iptal-modal-evet');
            buton.disabled = true;
            buton.textContent = 'İptal ediliyor...';

            var veriler = new FormData();
            veriler.append('islem', 'iptal');
            veriler.append('qr_token', aktifIptalReferans);

            fetch('rezervasyonlarim.php', { method: 'POST', body: veriler })
                .then(function (yanit) { return yanit.json(); })
                .then(function (veri) {
                    if (!veri.basarili) {
                        gosterToast(veri.hata || 'Rezervasyon iptal edilemedi.');
                        buton.disabled = false;
                        buton.textContent = 'Evet, İptal Et';
                        return;
                    }

                    iptalOnayiKapat();
                    gosterToast('Rezervasyonun iptal edildi.');
                    oturumRezervasyonlariniGetir();
                })
                .catch(function (hata) {
                    console.error(hata);
                    gosterToast('Rezervasyon iptal edilirken bir hata oluştu.');
                    buton.disabled = false;
                    buton.textContent = 'Evet, İptal Et';
                });
        }

        document.getElementById('rez-iptal-modal-perde').addEventListener('click', iptalOnayiKapat);

        function qrIndir(buton, koltukNo) {
            var kart = buton.closest('.rez-qr-karti, .ticket-container');
            if (!kart) return;

            var gizlenecekButonlar = kart.querySelectorAll('.btn-download, .btn-iptal');
            gizlenecekButonlar.forEach(function (el) { el.style.display = 'none'; });

            html2canvas(kart, { backgroundColor: '#ffffff', scale: 2 }).then(function (canvas) {
                var link = document.createElement('a');
                link.download = 'koltuk-' + koltukNo + '-bilet.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
                gizlenecekButonlar.forEach(function (el) { el.style.display = ''; });
            }).catch(function () {
                gizlenecekButonlar.forEach(function (el) { el.style.display = ''; });
                gosterToast('Bilet indirilemedi.');
            });
        }
        document.addEventListener('DOMContentLoaded', function () {
            oturumRezervasyonlariniGetir();
        });
    </script>

</body>
</html>