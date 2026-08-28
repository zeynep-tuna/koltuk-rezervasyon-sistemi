<?php
require '../includes/db.php';

$stmt = $pdo->query("SELECT id, ad, yer, koltuk_sayisi FROM etkinlikler WHERE deleted = 0 ORDER BY id DESC");
$etkinlikler = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Admin Paneli - Koltuklar</title>
    <link rel="stylesheet" href="../css/admin-layout.css">
    <link rel="stylesheet" href="../css/admin-etkinlikler.css">
</head>
<body>

    <div class="admin-layout">
        <?php require '../includes/admin-sidebar.php'; ?>

        <div class="admin-main">
            <?php require '../includes/admin-topbar.php'; ?>

            <div class="admin-content">
                <h1>Koltuklar</h1>
                <p class="dashboard-alt-baslik">Koltuk düzenini yönetmek istediğin etkinliği seç.</p>

                <table class="admin-tablo">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Etkinlik Adı</th>
                            <th>Yer</th>
                            <th>Koltuk Sayısı</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($etkinlikler) === 0): ?>
                            <tr>
                                <td colspan="5" class="bos-satir">Henüz eklenmiş etkinlik yok. Önce bir etkinlik eklemelisin.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($etkinlikler as $etkinlik): ?>
                                <tr>
                                    <td><?= htmlspecialchars($etkinlik['id']) ?></td>
                                    <td><?= htmlspecialchars($etkinlik['ad']) ?></td>
                                    <td><?= htmlspecialchars($etkinlik['yer']) ?></td>
                                    <td><?= htmlspecialchars($etkinlik['koltuk_sayisi']) ?></td>
                                    <td class="islemler">
                                        <a href="koltuk-yonet.php?etkinlik_id=<?= $etkinlik['id'] ?>">Koltukları Yönet</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
</html>
