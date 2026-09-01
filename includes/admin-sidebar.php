<?php
$mevcut_sayfa = basename($_SERVER['PHP_SELF']);

function aktif_class($sayfa_adlari, $mevcut_sayfa) {
    $liste = is_array($sayfa_adlari) ? $sayfa_adlari : [$sayfa_adlari];
    return in_array($mevcut_sayfa, $liste) ? ' aktif' : '';
}
?>
<aside class="admin-sidebar">
    <div class="sidebar-logo">Koltuk Rezervasyon</div>
    <nav class="sidebar-nav">
        <a href="index.php" class="nav-item<?= aktif_class('index.php', $mevcut_sayfa) ?>">Ana Sayfa</a>
        <a href="etkinlikler.php" class="nav-item<?= aktif_class('etkinlikler.php', $mevcut_sayfa) ?>">Etkinlikler</a>
        <a href="koltuklar.php" class="nav-item<?= aktif_class(['koltuklar.php', 'koltuk-yonet.php'], $mevcut_sayfa) ?>">Koltuklar</a>
        <a href="vatandaslar.php" class="nav-item<?= aktif_class('vatandaslar.php', $mevcut_sayfa) ?>">Vatandaşlar</a>
        <a href="rezervasyonlar.php" class="nav-item<?= aktif_class('rezervasyonlar.php', $mevcut_sayfa) ?>">Rezervasyonlar</a>
    </nav>
</aside>