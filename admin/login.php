<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require '../includes/db.php';

// Zaten giriş yapmışsa direkt panele gönder
if (!empty($_SESSION['admin_giris_yapti'])) {
    header('Location: index.php');
    exit;
}

$hata = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kullanici_adi = trim($_POST['kullanici_adi'] ?? '');
    $sifre = $_POST['sifre'] ?? '';

    if ($kullanici_adi === '' || $sifre === '') {
        $hata = 'Kullanıcı adı ve şifre gerekli.';
    } else {
        $stmt = $pdo->prepare("SELECT id, kullanici_adi, sifre_hash, ana_admin FROM adminler WHERE kullanici_adi = :kullanici_adi AND deleted = 0");
        $stmt->execute(['kullanici_adi' => $kullanici_adi]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($sifre, $admin['sifre_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_giris_yapti'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_kullanici_adi'] = $admin['kullanici_adi'];
            $_SESSION['admin_ana_admin'] = (bool)$admin['ana_admin'];
            header('Location: index.php');
            exit;
        } else {
            $hata = 'Kullanıcı adı veya şifre hatalı.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Admin Girişi</title>
    <link rel="stylesheet" href="../css/login.css">
</head>
<body>

    <div class="giris-kutu">
        <h1>Admin Girişi</h1>
        <p class="giris-alt-baslik">Koltuk Rezervasyon Sistemi</p>

        <?php if ($hata !== ''): ?>
            <p class="giris-hata"><?= htmlspecialchars($hata) ?></p>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <label for="kullanici_adi">Kullanıcı Adı</label>
            <input type="text" id="kullanici_adi" name="kullanici_adi" autocomplete="username" autofocus>

            <label for="sifre">Şifre</label>
            <div class="sifre-alani">
                <input type="password" id="sifre" name="sifre" autocomplete="current-password">
                <button type="button" id="sifre-goster-btn" class="sifre-goster-btn" tabindex="-1" title="Şifreyi göster">
                    <svg id="goz-acik" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <svg id="goz-kapali" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                </button>
            </div>

            <button type="submit">Giriş Yap</button>
        </form>
    </div>

    <script>
        document.getElementById('sifre-goster-btn').addEventListener('click', function () {
            var sifreKutusu = document.getElementById('sifre');
            var gozAcik = document.getElementById('goz-acik');
            var gozKapali = document.getElementById('goz-kapali');
            if (sifreKutusu.type === 'password') {
                sifreKutusu.type = 'text';
                gozAcik.style.display = 'block';
                gozKapali.style.display = 'none';
            } else {
                sifreKutusu.type = 'password';
                gozAcik.style.display = 'none';
                gozKapali.style.display = 'block';
            }
        });

        document.getElementById('kullanici_adi').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('sifre').focus();
            }
        });
    </script>

</body>
</html>