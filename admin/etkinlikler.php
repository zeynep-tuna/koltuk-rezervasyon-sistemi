<?php
require '../includes/db.php';

$hata = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['etkinlik_ekle'])) {
    $ad = trim($_POST['ad'] ?? '');
    $yer = trim($_POST['yer'] ?? '');
    $etkinlik_tarih = trim($_POST['etkinlik_tarih'] ?? '');
    $etkinlik_saat = trim($_POST['etkinlik_saat'] ?? '');
    $koltuk_sayisi = trim($_POST['koltuk_sayisi'] ?? '');

    if ($ad === '' || $yer === '' || $etkinlik_tarih === '' || $etkinlik_saat === '' || $koltuk_sayisi === '') {
        $hata = 'Lütfen tüm alanları doldurun.';
    } elseif (!ctype_digit($koltuk_sayisi) || (int)$koltuk_sayisi <= 0) {
        $hata = 'Koltuk sayısı pozitif bir tam sayı olmalıdır.';
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
        $hata = 'Lütfen tüm alanları doldurun.';
    } elseif (!ctype_digit($koltuk_sayisi) || (int)$koltuk_sayisi <= 0) {
        $hata = 'Koltuk sayısı pozitif bir tam sayı olmalıdır.';
    } elseif (!ctype_digit((string)$id)) {
        $hata = 'Geçersiz etkinlik.';
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

$stmt = $pdo->query("SELECT id, ad, yer, etkinlik_tarih, etkinlik_saat, koltuk_sayisi FROM etkinlikler WHERE deleted = 0 ORDER BY id ASC");
$etkinlikler = $stmt->fetchAll();

$aylar = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];

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

        <?php if ($hata !== '' && !isset($_POST['etkinlik_duzenle'])): ?>
            <p class="hata-mesaj"><?= htmlspecialchars($hata) ?></p>
        <?php endif; ?>

        <form method="POST" action="etkinlikler.php" autocomplete="off">
            <input type="hidden" name="etkinlik_ekle" value="1">

            <label for="ekle-ad">Etkinlik Adı</label>
            <input type="text" id="ekle-ad" name="ad" autocomplete="off">

            <label for="ekle-yer">Yer</label>
            <input type="text" id="ekle-yer" name="yer" autocomplete="off">

            <label for="ekle-tarih">Etkinlik Tarihi</label>
            <input type="date" id="ekle-tarih" name="etkinlik_tarih" autocomplete="off">

            <label for="ekle-saat">Etkinlik Saati</label>
            <input type="time" id="ekle-saat" name="etkinlik_saat" autocomplete="off">

            <label for="ekle-koltuk">Koltuk Sayısı</label>
            <input type="number" id="ekle-koltuk" name="koltuk_sayisi" min="1" autocomplete="off">

            <div class="modal-butonlar">
                <button type="button" class="btn-vazgec" onclick="document.getElementById('ekle-modal').close()">Vazgeç</button>
                <button type="submit" class="btn-kaydet">Kaydet</button>
            </div>
        </form>
    </dialog>

    <!-- Düzenleme modalı -->
    <dialog id="duzenle-modal" class="admin-modal">
        <h2>Etkinlik Düzenle</h2>

        <?php if ($hata !== '' && isset($_POST['etkinlik_duzenle'])): ?>
            <p class="hata-mesaj"><?= htmlspecialchars($hata) ?></p>
        <?php endif; ?>

        <form method="POST" action="etkinlikler.php" autocomplete="off">
            <input type="hidden" name="etkinlik_duzenle" value="1">
            <input type="hidden" name="id" id="duzenle-id">

            <label for="duzenle-ad">Etkinlik Adı</label>
            <input type="text" id="duzenle-ad" name="ad" autocomplete="off">

            <label for="duzenle-yer">Yer</label>
            <input type="text" id="duzenle-yer" name="yer" autocomplete="off">

            <label for="duzenle-tarih">Etkinlik Tarihi</label>
            <input type="date" id="duzenle-tarih" name="etkinlik_tarih" autocomplete="off">

            <label for="duzenle-saat">Etkinlik Saati</label>
            <input type="time" id="duzenle-saat" name="etkinlik_saat" autocomplete="off">

            <label for="duzenle-koltuk">Koltuk Sayısı</label>
            <input type="number" id="duzenle-koltuk" name="koltuk_sayisi" min="1" autocomplete="off">

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
        function silOnayAc(id) {
            document.getElementById('sil-id').value = id;
            document.getElementById('sil-onay-modal').showModal();
        }

        function silOnayla() {
            var id = document.getElementById('sil-id').value;
            window.location.href = 'etkinlik-sil.php?id=' + id;
        }

        function duzenleAc(id, ad, yer, tarih, saat, koltukSayisi) {
            document.getElementById('duzenle-id').value = id;
            document.getElementById('duzenle-ad').value = ad;
            document.getElementById('duzenle-yer').value = yer;
            document.getElementById('duzenle-tarih').value = tarih;
            document.getElementById('duzenle-saat').value = saat;
            document.getElementById('duzenle-koltuk').value = koltukSayisi;
            document.getElementById('duzenle-modal').showModal();
        }

        document.querySelectorAll('dialog.admin-modal').forEach(function (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    modal.close();
                }
            });
        });
    </script>

</body>
</html>