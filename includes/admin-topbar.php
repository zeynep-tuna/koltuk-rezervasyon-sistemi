<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<header class="admin-topbar">
    <form class="admin-arama" method="GET" action="#">
        <svg class="admin-arama-ikon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input type="text" name="q" placeholder="Ara..." autocomplete="off">
    </form>

    <details class="admin-menu">
        <summary class="admin-menu-buton"><?= htmlspecialchars($_SESSION['admin_kullanici_adi'] ?? 'Admin') ?></summary>
        <div class="admin-menu-icerik">
            <a href="sifre-degistir.php">Şifre Değiştir</a>
            <a href="cikis.php">Çıkış Yap</a>
        </div>
    </details>
</header>

<script src="../js/admin-menu.js"></script>
<script src="../js/admin-arama.js"></script>