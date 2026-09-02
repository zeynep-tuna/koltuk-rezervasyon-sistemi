<?php require '../includes/admin-auth-kontrol.php'; ?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Admin Paneli - Ana Sayfa</title>
    <link rel="stylesheet" href="../css/admin-layout.css">
    <link rel="stylesheet" href="../css/admin-dashboard.css">
</head>
<body>

    <div class="admin-layout">
        <?php require '../includes/admin-sidebar.php'; ?>

        <div class="admin-main">
            <?php require '../includes/admin-topbar.php'; ?>

            <div class="admin-content">
                <h1>Kontrol Paneli</h1>
                <p class="dashboard-alt-baslik">Hoş geldin, aşağıdaki bölümlerden birini seçerek başlayabilirsin.</p>

                <?php if (isset($_GET['yetki_yok'])): ?>
                    <p class="yetki-uyarisi">Bu sayfaya erişim yetkin yok. Admin hesaplarını sadece ana admin yönetebilir.</p>
                <?php endif; ?>

                <div class="dashboard-kartlar">
                    <a href="etkinlikler.php" class="dashboard-kart">
                        <div class="kart-ikon kart-ikon-mor">📅</div>
                        <h2>Etkinlikler</h2>
                        <p>Etkinlik ekle, düzenle, sil ve koltukları yönet.</p>
                    </a>

                    <a href="takvim.php" class="dashboard-kart">
                        <div class="kart-ikon kart-ikon-mavi">🗓️</div>
                        <h2>Takvim</h2>
                        <p>Etkinlikleri aylık takvim görünümünde incele.</p>
                    </a>

                    <a href="koltuklar.php" class="dashboard-kart">
                        <div class="kart-ikon kart-ikon-mavi">💺</div>
                        <h2>Koltuklar</h2>
                        <p>Etkinliklere göre koltuk düzenini oluştur ve durumlarını yönet.</p>
                    </a>

                    <a href="vatandaslar.php" class="dashboard-kart">
                        <div class="kart-ikon kart-ikon-yesil">👤</div>
                        <h2>Vatandaşlar</h2>
                        <p>Vatandaş kayıtlarını (TC, ad, soyad, doğum tarihi) yönet.</p>
                    </a>

                    <a href="rezervasyonlar.php" class="dashboard-kart">
                        <div class="kart-ikon kart-ikon-turuncu">🎟️</div>
                        <h2>Rezervasyonlar</h2>
                        <p>Yapılan rezervasyonları görüntüle.</p>
                    </a>

                    <?php if (!empty($_SESSION['admin_ana_admin'])): ?>
                        <a href="adminler.php" class="dashboard-kart">
                            <div class="kart-ikon kart-ikon-mor">🔑</div>
                            <h2>Adminler</h2>
                            <p>Panele giriş yapabilecek admin hesaplarını yönet.</p>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</body>
</html>