<?php
require '../includes/admin-auth-kontrol.php';
require '../includes/db.php';

function rezervasyonu_token_ile_getir($pdo, $token) {
    $stmt = $pdo->prepare("
        SELECT r.id, r.giris_yapildi_mi, r.giris_zamani, r.protokol_adi,
               v.ad AS vatandas_ad, v.soyad AS vatandas_soyad,
               e.ad AS etkinlik_ad, e.etkinlik_tarih, e.etkinlik_saat, e.yer,
               k.koltuk_no
        FROM rezervasyonlar r
        LEFT JOIN vatandaslar v ON r.vatandas_id = v.id
        JOIN etkinlikler e ON r.etkinlik_id = e.id
        JOIN koltuklar k ON r.koltuk_id = k.id
        WHERE r.qr_token = :token AND r.deleted = 0
        LIMIT 1
    ");
    $stmt->execute(['token' => $token]);
    return $stmt->fetch();
}

function rezervasyonu_referans_ile_getir($pdo, $referans) {
    $stmt = $pdo->prepare("
        SELECT r.id, r.giris_yapildi_mi, r.giris_zamani, r.protokol_adi,
               v.ad AS vatandas_ad, v.soyad AS vatandas_soyad,
               e.ad AS etkinlik_ad, e.etkinlik_tarih, e.etkinlik_saat, e.yer,
               k.koltuk_no
        FROM rezervasyonlar r
        LEFT JOIN vatandaslar v ON r.vatandas_id = v.id
        JOIN etkinlikler e ON r.etkinlik_id = e.id
        JOIN koltuklar k ON r.koltuk_id = k.id
        WHERE UPPER(SUBSTRING(r.qr_token, 1, 12)) = :referans AND r.deleted = 0
        LIMIT 1
    ");
    $stmt->execute(['referans' => strtoupper(trim($referans))]);
    return $stmt->fetch();
}

$token = trim($_GET['token'] ?? '');
$referans_girisi = trim($_POST['referans_kodu'] ?? $_GET['referans_kodu'] ?? '');
$hata = '';
$rezervasyon = null;
$yeniden_isaretlendi = false;

if ($token !== '') {
    $rezervasyon = rezervasyonu_token_ile_getir($pdo, $token);
    if (!$rezervasyon) {
        $hata = 'Bu bilet geçersiz ya da iptal edilmiş.';
    }
} elseif ($referans_girisi !== '') {
    if (!preg_match('/^[A-F0-9]{12}$/i', $referans_girisi)) {
        $hata = 'Referans kodu 12 haneli olmalı.';
    } else {
        $rezervasyon = rezervasyonu_referans_ile_getir($pdo, $referans_girisi);
        if (!$rezervasyon) {
            $hata = 'Bu referans koduna ait bir bilet bulunamadı.';
        }
    }
}

// Otomatik giris isaretleme - sayfa acildigi/kod girildigi an, daha once giris yapilmamissa
if ($rezervasyon && !$rezervasyon['giris_yapildi_mi']) {
    $guncelle = $pdo->prepare("UPDATE rezervasyonlar SET giris_yapildi_mi = 1, giris_zamani = NOW() WHERE id = :id");
    $guncelle->execute(['id' => $rezervasyon['id']]);

    $rezervasyon['giris_yapildi_mi'] = 1;
    $rezervasyon['giris_zamani'] = date('Y-m-d H:i:s');
    $yeniden_isaretlendi = true;
}

$ad_soyad = '';
if ($rezervasyon) {
    $ad_soyad = $rezervasyon['protokol_adi'] !== null
        ? $rezervasyon['protokol_adi']
        : trim($rezervasyon['vatandas_ad'] . ' ' . $rezervasyon['vatandas_soyad']);
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Giriş Kontrol</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #F8FAFC;
            color: #3F3936;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .gk-header {
            width: 100%;
            background: linear-gradient(135deg, #2D5A44, #4F9E78);
            color: #fff;
            padding: 20px;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
        }
        .gk-govde {
            width: 100%;
            max-width: 420px;
            padding: 24px 18px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .gk-kart {
            width: 100%;
            background-color: #fff;
            border: 1px solid #E5DFD8;
            border-radius: 14px;
            padding: 28px 24px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
        }
        .gk-ikon {
            font-size: 40px;
            margin-bottom: 6px;
        }
        .gk-durum-basarili { color: #2D5A44; }
        .gk-durum-uyari { color: #B15C00; }
        .gk-durum-hata { color: #C62828; }
        .gk-ad {
            font-size: 20px;
            font-weight: bold;
            margin: 8px 0 18px;
            color: #1F1F1F;
        }
        .gk-detay-satiri {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #F0EBE5;
            font-size: 14px;
            text-align: left;
        }
        .gk-detay-satiri:last-of-type { border-bottom: none; }
        .gk-detay-etiket { color: #888; }
        .gk-detay-deger { font-weight: bold; color: #3F3936; text-align: right; }
        .gk-mesaj {
            margin-top: 16px;
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
        }
        .gk-mesaj-basarili { background-color: #E5F3EB; color: #2D5A44; }
        .gk-mesaj-uyari { background-color: #FFF3E0; color: #B15C00; }
        .gk-koltuk-etiketi {
            display: inline-block;
            background-color: #2D5A44;
            color: #fff;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 14px;
        }
        .gk-referans-form {
            width: 100%;
            margin-top: 18px;
        }
        .gk-referans-form label {
            display: block;
            font-size: 13px;
            color: #666;
            margin-bottom: 6px;
            text-align: left;
        }
        .gk-referans-form input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 15px;
            text-transform: uppercase;
            text-align: center;
            letter-spacing: 1px;
        }
        .gk-referans-form button {
            width: 100%;
            margin-top: 10px;
            padding: 14px;
            border: none;
            border-radius: 8px;
            background: linear-gradient(90deg, #4F9E78, #2D5A44);
            color: #fff;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }
        .gk-ayrac {
            width: 100%;
            text-align: center;
            color: #aaa;
            font-size: 12px;
            margin: 20px 0 4px;
        }
        .gk-baska-link {
            display: inline-block;
            margin-top: 16px;
            color: #2D5A44;
            font-size: 13px;
            font-weight: bold;
            text-decoration: none;
        }
    </style>
</head>
<body>

    <div class="gk-header">Giriş Kontrol</div>

    <div class="gk-govde">
        <div class="gk-kart">

            <?php if ($rezervasyon): ?>

                <div class="gk-koltuk-etiketi">Koltuk <?= htmlspecialchars($rezervasyon['koltuk_no']) ?></div>
                <p class="gk-ad"><?= htmlspecialchars($ad_soyad) ?></p>

                <div class="gk-detay-satiri">
                    <span class="gk-detay-etiket">Etkinlik</span>
                    <span class="gk-detay-deger"><?= htmlspecialchars($rezervasyon['etkinlik_ad']) ?></span>
                </div>
                <div class="gk-detay-satiri">
                    <span class="gk-detay-etiket">Yer</span>
                    <span class="gk-detay-deger"><?= htmlspecialchars($rezervasyon['yer']) ?></span>
                </div>
                <?php if ($rezervasyon['etkinlik_tarih']): ?>
                    <div class="gk-detay-satiri">
                        <span class="gk-detay-etiket">Tarih</span>
                        <span class="gk-detay-deger">
                            <?= date('d.m.Y', strtotime($rezervasyon['etkinlik_tarih'])) ?>
                            <?= $rezervasyon['etkinlik_saat'] ? ' ' . substr($rezervasyon['etkinlik_saat'], 0, 5) : '' ?>
                        </span>
                    </div>
                <?php endif; ?>

                <?php if ($yeniden_isaretlendi): ?>

                    <div class="gk-mesaj gk-mesaj-basarili">✓ Giriş başarıyla işaretlendi.</div>

                <?php else: ?>

                    <div class="gk-mesaj gk-mesaj-uyari">
                        ⚠ Bu bilet daha önce kullanıldı.<br>
                        Giriş saati: <?= date('d.m.Y H:i', strtotime($rezervasyon['giris_zamani'])) ?>
                    </div>

                <?php endif; ?>

                <a href="giris-kontrol.php" class="gk-baska-link">← Başka bir bilet oku / kod gir</a>

            <?php else: ?>

                <?php if ($hata !== ''): ?>
                    <div class="gk-ikon gk-durum-hata">✕</div>
                    <p class="gk-ad gk-durum-hata"><?= htmlspecialchars($hata) ?></p>
                <?php else: ?>
                    <div class="gk-ikon">🎫</div>
                    <p style="color:#888; font-size:14px; margin-bottom:0;">QR kod okutulmadıysa, biletin üzerindeki referans kodunu buradan girebilirsin.</p>
                <?php endif; ?>

                <form method="POST" class="gk-referans-form">
                    <label for="referans_kodu">Referans Kodu</label>
                    <input type="text" id="referans_kodu" name="referans_kodu" maxlength="12" placeholder="Örn: 8B073BAE48E1" autocomplete="off" autofocus>
                    <button type="submit">Kontrol Et</button>
                </form>

            <?php endif; ?>

        </div>
    </div>

</body>
</html>
