<?php
require '../includes/admin-auth-kontrol.php';
require '../includes/db.php';

if (empty($_SESSION['admin_ana_admin'])) {
    header('Location: index.php?yetki_yok=1');
    exit;
}

$hata = '';
$basari = '';
$basari_tur = '';
$ekle_modal_ac = false;
$kullanici_adi_hata = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_ekle'])) {
    $kullanici_adi = trim($_POST['kullanici_adi'] ?? '');
    $sifre = $_POST['sifre'] ?? '';
    $sifre_tekrar = $_POST['sifre_tekrar'] ?? '';
    $kullanici_adi_hata = false;

    if ($kullanici_adi === '' || $sifre === '' || $sifre_tekrar === '') {
        $hata = 'Lütfen tüm alanları doldurun.';
        $ekle_modal_ac = true;
    } elseif ($sifre !== $sifre_tekrar) {
        $hata = 'Girdiğin şifreler birbiriyle eşleşmiyor, tekrar kontrol et.';
        $ekle_modal_ac = true;
    } elseif (strlen($sifre) < 4) {
        $hata = 'Şifre en az 4 karakter olmalı.';
        $ekle_modal_ac = true;
    } else {
        $kontrol = $pdo->prepare("SELECT id FROM adminler WHERE kullanici_adi = :kullanici_adi AND deleted = 0");
        $kontrol->execute(['kullanici_adi' => $kullanici_adi]);
        if ($kontrol->fetch()) {
            $hata = 'Bu kullanıcı adı zaten kayıtlı.';
            $ekle_modal_ac = true;
            $kullanici_adi_hata = true;
        } else {
            $hash = password_hash($sifre, PASSWORD_DEFAULT);
            $ekle = $pdo->prepare("INSERT INTO adminler (kullanici_adi, sifre_hash) VALUES (:kullanici_adi, :sifre_hash)");
            $ekle->execute([
                'kullanici_adi' => $kullanici_adi,
                'sifre_hash' => $hash,
            ]);
            header('Location: adminler.php?eklendi=1&isim=' . urlencode($kullanici_adi));
            exit;
        }
    }
}

if (isset($_GET['eklendi']) && isset($_GET['isim'])) {
    $basari = $_GET['isim'] . ' hesabı eklendi.';
    $basari_tur = 'eklendi';
}

// Admin silme
if (isset($_GET['sil'])) {
    $silinecek_id = $_GET['sil'];
    if (ctype_digit((string)$silinecek_id)) {
        $toplam = $pdo->query("SELECT COUNT(*) AS adet FROM adminler WHERE deleted = 0")->fetch();
        if ((int)$toplam['adet'] <= 1) {
            $hata = 'Son admin hesabı silinemez.';
        } elseif ((int)$silinecek_id === (int)($_SESSION['admin_id'] ?? 0)) {
            $hata = 'Şu an giriş yaptığın hesabı silemezsin.';
        } else {
            $silinecek = $pdo->prepare("SELECT kullanici_adi FROM adminler WHERE id = :id AND deleted = 0");
            $silinecek->execute(['id' => $silinecek_id]);
            $silinecek_admin = $silinecek->fetch();

            $sil = $pdo->prepare("UPDATE adminler SET deleted = 1 WHERE id = :id");
            $sil->execute(['id' => $silinecek_id]);
            header('Location: adminler.php?silindi=1&isim=' . urlencode($silinecek_admin['kullanici_adi'] ?? ''));
            exit;
        }
    }
}

if (isset($_GET['silindi']) && isset($_GET['isim'])) {
    $basari = $_GET['isim'] . ' hesabı silindi.';
    $basari_tur = 'silindi';
}

$arama = trim($_GET['q'] ?? '');
if ($arama !== '') {
    $stmt = $pdo->prepare("SELECT id, kullanici_adi, olusturma_tarihi, ana_admin FROM adminler WHERE deleted = 0 AND kullanici_adi LIKE :arama ORDER BY id ASC");
    $stmt->execute(['arama' => '%' . $arama . '%']);
} else {
    $stmt = $pdo->query("SELECT id, kullanici_adi, olusturma_tarihi, ana_admin FROM adminler WHERE deleted = 0 ORDER BY id ASC");
}
$adminler = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Admin Paneli - Adminleri Yönet</title>
    <link rel="stylesheet" href="../css/admin-layout.css">
    <link rel="stylesheet" href="../css/admin-adminler.css">
</head>
<body>

    <div class="admin-layout">
        <?php require '../includes/admin-sidebar.php'; ?>

        <div class="admin-main">
            <?php require '../includes/admin-topbar.php'; ?>

            <div class="admin-content">
                <h1>Adminleri Yönet</h1>

                <?php if ($hata !== '' && !$ekle_modal_ac): ?>
                    <p class="basari-mesaj hata-mesaj-genel"><?= htmlspecialchars($hata) ?></p>
                <?php endif; ?>

                <?php if ($basari !== ''): ?>
                    <div class="toast-bildirim toast-<?= $basari_tur ?>" id="basari-toast"><?= htmlspecialchars($basari) ?></div>
                <?php endif; ?>

                <button type="button" class="btn-ekle" onclick="adminEkleAc()">+ Yeni Admin Ekle</button>

                <table class="admin-tablo">
                    <thead>
                        <tr>
                            <th>Sıra No</th>
                            <th>Kullanıcı Adı</th>
                            <th>Eklenme Tarihi</th>
                            <th>Saat</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($adminler) === 0): ?>
                            <tr>
                                <td colspan="5" class="bos-satir">Henüz admin hesabı yok.</td>
                            </tr>
                        <?php else: ?>
                            <?php $sira = 1; foreach ($adminler as $admin): ?>
                                <tr>
                                    <td><?= $sira++ ?></td>
                                    <td><?= htmlspecialchars($admin['kullanici_adi']) ?><?= ((int)$admin['id'] === (int)($_SESSION['admin_id'] ?? 0)) ? ' <span class="sen-etiketi">(sen)</span>' : '' ?></td>
                                    <td><?= date('d.m.Y', strtotime($admin['olusturma_tarihi'])) ?></td>
                                    <td><?= date('H:i', strtotime($admin['olusturma_tarihi'])) ?></td>
                                    <td class="islemler">
                                        <?php if (count($adminler) > 1 && (int)$admin['id'] !== (int)($_SESSION['admin_id'] ?? 0)): ?>
                                            <button type="button" class="btn-sil" onclick="silOnayGoster(<?= (int)$admin['id'] ?>, '<?= htmlspecialchars(addslashes($admin['kullanici_adi'])) ?>')">Sil</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <dialog id="ekle-modal" class="modal">
        <form method="POST" action="adminler.php">
            <h2>Yeni Admin Ekle</h2>

            <?php if ($hata !== '' && $ekle_modal_ac && !$kullanici_adi_hata): ?>
                <p class="hata-mesaj-genel"><?= htmlspecialchars($hata) ?></p>
            <?php endif; ?>

            <label for="ekle-kullanici-adi">Kullanıcı Adı <span class="zorunlu-yildiz">*</span></label>
            <input type="text" id="ekle-kullanici-adi" name="kullanici_adi" autocomplete="off" class="<?= $kullanici_adi_hata ? 'input-hata' : '' ?>" value="<?= $ekle_modal_ac ? htmlspecialchars($_POST['kullanici_adi'] ?? '') : '' ?>">
            <p class="hata-mesaj-inline" id="ekle-kullanici-adi-hata" style="<?= $kullanici_adi_hata ? 'display:block' : 'display:none' ?>"><?= $kullanici_adi_hata ? 'Bu kullanıcı adı zaten kayıtlı.' : 'Bu alan zorunludur.' ?></p>

            <label for="ekle-sifre">Şifre <span class="zorunlu-yildiz">*</span></label>
            <input type="password" id="ekle-sifre" name="sifre" autocomplete="new-password">
            <p class="hata-mesaj-inline" id="ekle-sifre-hata" style="display:none">Bu alan zorunludur.</p>

            <label for="ekle-sifre-tekrar">Şifre (Tekrar) <span class="zorunlu-yildiz">*</span></label>
            <input type="password" id="ekle-sifre-tekrar" name="sifre_tekrar" autocomplete="new-password">
            <p class="hata-mesaj-inline" id="ekle-sifre-tekrar-hata" style="display:none">Bu alan zorunludur.</p>

            <input type="hidden" name="admin_ekle" value="1">

            <div class="modal-butonlar">
                <button type="button" class="btn-vazgec" onclick="document.getElementById('ekle-modal').close()">Vazgeç</button>
                <button type="submit" class="btn-kaydet">Ekle</button>
            </div>
        </form>
    </dialog>

    <script>
        var basariToast = document.getElementById('basari-toast');
        if (basariToast) {
            setTimeout(function () {
                basariToast.classList.add('toast-kayboluyor');
                setTimeout(function () {
                    basariToast.remove();
                }, 400);
            }, 2500);
        }

        function adminEkleAc() {
            document.getElementById('ekle-kullanici-adi').value = '';
            document.getElementById('ekle-sifre').value = '';
            document.getElementById('ekle-sifre-tekrar').value = '';
            ['ekle-kullanici-adi', 'ekle-sifre', 'ekle-sifre-tekrar'].forEach(function (id) {
                document.getElementById(id).classList.remove('input-hata');
                document.getElementById(id + '-hata').style.display = 'none';
            });
            document.getElementById('ekle-modal').showModal();
        }

        <?php if ($ekle_modal_ac): ?>
        document.getElementById('ekle-modal').showModal();
        <?php endif; ?>

        var kullaniciAdiZamanlayici = null;
        document.getElementById('ekle-kullanici-adi').addEventListener('input', function () {
            var alan = this;
            var deger = alan.value.trim();
            clearTimeout(kullaniciAdiZamanlayici);

            if (deger === '') {
                alan.classList.remove('input-hata');
                document.getElementById('ekle-kullanici-adi-hata').style.display = 'none';
                return;
            }

            kullaniciAdiZamanlayici = setTimeout(function () {
                fetch('kullanici-adi-kontrol.php?kullanici_adi=' + encodeURIComponent(deger))
                    .then(function (yanit) { return yanit.json(); })
                    .then(function (veri) {
                        var hataYazisi = document.getElementById('ekle-kullanici-adi-hata');
                        if (veri.var_mi) {
                            alan.classList.add('input-hata');
                            hataYazisi.textContent = 'Bu kullanıcı adı zaten kayıtlı.';
                            hataYazisi.style.display = 'block';
                        } else {
                            alan.classList.remove('input-hata');
                            hataYazisi.style.display = 'none';
                        }
                    });
            }, 400);
        });

        function sifreCanliKontrol() {
            var sifreAlani = document.getElementById('ekle-sifre');
            var sifreHata = document.getElementById('ekle-sifre-hata');

            if (sifreAlani.value !== '' && sifreAlani.value.length < 4) {
                sifreAlani.classList.add('input-hata');
                sifreHata.textContent = 'Şifre en az 4 karakter olmalı.';
                sifreHata.style.display = 'block';
            } else {
                sifreAlani.classList.remove('input-hata');
                sifreHata.style.display = 'none';
            }
        }

        document.getElementById('ekle-sifre').addEventListener('input', sifreCanliKontrol);

        var eklemeFormu = document.querySelector('#ekle-modal form');
        var zorunluAlanlar = ['ekle-kullanici-adi', 'ekle-sifre', 'ekle-sifre-tekrar'];

        eklemeFormu.addEventListener('submit', function (e) {
            var eksikVar = false;
            zorunluAlanlar.forEach(function (id) {
                var alan = document.getElementById(id);
                if (alan.value.trim() === '') {
                    alan.classList.add('input-hata');
                    document.getElementById(id + '-hata').style.display = 'block';
                    eksikVar = true;
                }
            });
            if (eksikVar) {
                e.preventDefault();
            }
        });

        for (var i = 0; i < zorunluAlanlar.length; i++) {
            (function (index) {
                document.getElementById(zorunluAlanlar[index]).addEventListener('keydown', function (e) {
                    if (e.key !== 'Enter') return;
                    if (index < zorunluAlanlar.length - 1) {
                        e.preventDefault();
                        document.getElementById(zorunluAlanlar[index + 1]).focus();
                    }
                });
            })(i);
        }
    </script>

    <dialog id="sil-onay-modal" class="modal modal-kucuk modal-ortali">
        <div class="uyari-ikon">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                <line x1="12" y1="9" x2="12" y2="13"></line>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
        </div>
        <h2>Admin Hesabını Sil</h2>
        <p class="onay-mesaji" id="sil-onay-mesaji"></p>
        <div class="modal-butonlar">
            <button type="button" class="btn-vazgec" onclick="document.getElementById('sil-onay-modal').close()">Vazgeç</button>
            <a id="sil-onay-link" href="#" class="btn-sil-onayla">Evet, Sil</a>
        </div>
    </dialog>

    <script>
        function silOnayGoster(id, kullaniciAdi) {
            document.getElementById('sil-onay-mesaji').textContent = '"' + kullaniciAdi + '" adlı admin hesabını silmek istediğine emin misin?';
            document.getElementById('sil-onay-link').href = 'adminler.php?sil=' + id;
            document.getElementById('sil-onay-modal').showModal();
        }
    </script>

</body>
</html>