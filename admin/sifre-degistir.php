<?php
require '../includes/admin-auth-kontrol.php';
require '../includes/db.php';

$hata = '';
$basari = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eski_sifre = $_POST['eski_sifre'] ?? '';
    $yeni_sifre = $_POST['yeni_sifre'] ?? '';
    $yeni_sifre_tekrar = $_POST['yeni_sifre_tekrar'] ?? '';

    if ($eski_sifre === '' || $yeni_sifre === '' || $yeni_sifre_tekrar === '') {
        $hata = 'Lütfen tüm alanları doldurun.';
    } else {
        $stmt = $pdo->prepare("SELECT sifre_hash FROM adminler WHERE id = :id AND deleted = 0");
        $stmt->execute(['id' => $_SESSION['admin_id'] ?? 0]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($eski_sifre, $admin['sifre_hash'])) {
            $hata = 'Mevcut şifren yanlış.';
        } elseif ($yeni_sifre !== $yeni_sifre_tekrar) {
            $hata = 'Girdiğin yeni şifreler birbiriyle eşleşmiyor, tekrar kontrol et.';
        } elseif (strlen($yeni_sifre) < 4) {
            $hata = 'Yeni şifre en az 4 karakter olmalı.';
        } else {
            $yeni_hash = password_hash($yeni_sifre, PASSWORD_DEFAULT);
            $guncelle = $pdo->prepare("UPDATE adminler SET sifre_hash = :hash WHERE id = :id");
            $guncelle->execute([
                'hash' => $yeni_hash,
                'id' => $_SESSION['admin_id'] ?? 0,
            ]);
            header('Location: sifre-degistir.php?basarili=1');
            exit;
        }
    }
}

if (isset($_GET['basarili'])) {
    $basari = 'Şifren başarıyla güncellendi.';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Admin Paneli - Şifre Değiştir</title>
    <link rel="stylesheet" href="../css/admin-layout.css">
    <link rel="stylesheet" href="../css/admin-sifre-degistir.css">
</head>
<body>

    <div class="admin-layout">
        <?php require '../includes/admin-sidebar.php'; ?>

        <div class="admin-main">
            <?php require '../includes/admin-topbar.php'; ?>

            <div class="admin-content">
                <h1>Şifre Değiştir</h1>

                <?php if ($basari !== ''): ?>
                    <div class="toast-bildirim toast-eklendi" id="basari-toast"><?= htmlspecialchars($basari) ?></div>
                <?php endif; ?>

                <div class="sifre-form-kutu">
                    <?php if ($hata !== ''): ?>
                        <p class="hata-mesaj-genel"><?= htmlspecialchars($hata) ?></p>
                    <?php endif; ?>

                    <form method="POST" action="sifre-degistir.php">
                        <label for="eski-sifre">Mevcut Şifre</label>
                        <div class="sifre-alani">
                            <input type="password" id="eski-sifre" name="eski_sifre" autocomplete="current-password">
                            <button type="button" class="sifre-goster-btn" data-hedef="eski-sifre" tabindex="-1" title="Şifreyi göster">
                                <svg class="goz-acik" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg class="goz-kapali" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                    <line x1="1" y1="1" x2="23" y2="23"></line>
                                </svg>
                            </button>
                        </div>

                        <label for="yeni-sifre">Yeni Şifre</label>
                        <div class="sifre-alani">
                            <input type="password" id="yeni-sifre" name="yeni_sifre" autocomplete="new-password">
                            <button type="button" class="sifre-goster-btn" data-hedef="yeni-sifre" tabindex="-1" title="Şifreyi göster">
                                <svg class="goz-acik" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg class="goz-kapali" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                    <line x1="1" y1="1" x2="23" y2="23"></line>
                                </svg>
                            </button>
                        </div>
                        <p class="hata-mesaj-inline" id="yeni-sifre-hata" style="display:none">Şifre en az 4 karakter olmalı.</p>

                        <label for="yeni-sifre-tekrar">Yeni Şifre (Tekrar)</label>
                        <div class="sifre-alani">
                            <input type="password" id="yeni-sifre-tekrar" name="yeni_sifre_tekrar" autocomplete="new-password">
                            <button type="button" class="sifre-goster-btn" data-hedef="yeni-sifre-tekrar" tabindex="-1" title="Şifreyi göster">
                                <svg class="goz-acik" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg class="goz-kapali" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                    <line x1="1" y1="1" x2="23" y2="23"></line>
                                </svg>
                            </button>
                        </div>

                        <button type="submit" class="btn-kaydet">Şifreyi Güncelle</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('.sifre-goster-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var hedefKutu = document.getElementById(btn.dataset.hedef);
                var gozAcik = btn.querySelector('.goz-acik');
                var gozKapali = btn.querySelector('.goz-kapali');
                if (hedefKutu.type === 'password') {
                    hedefKutu.type = 'text';
                    gozAcik.style.display = 'block';
                    gozKapali.style.display = 'none';
                } else {
                    hedefKutu.type = 'password';
                    gozAcik.style.display = 'none';
                    gozKapali.style.display = 'block';
                }
            });
        });

        var yeniSifreAlani = document.getElementById('yeni-sifre');
        var yeniSifreHata = document.getElementById('yeni-sifre-hata');
        yeniSifreAlani.addEventListener('input', function () {
            if (yeniSifreAlani.value !== '' && yeniSifreAlani.value.length < 4) {
                yeniSifreAlani.classList.add('input-hata');
                yeniSifreHata.style.display = 'block';
            } else {
                yeniSifreAlani.classList.remove('input-hata');
                yeniSifreHata.style.display = 'none';
            }
        });

        var basariToast = document.getElementById('basari-toast');
        if (basariToast) {
            setTimeout(function () {
                basariToast.classList.add('toast-kayboluyor');
                setTimeout(function () {
                    basariToast.remove();
                }, 400);
            }, 2500);
        }
    </script>

</body>
</html>
