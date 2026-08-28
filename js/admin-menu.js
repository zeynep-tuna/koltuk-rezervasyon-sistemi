document.addEventListener('click', function (e) {
    var menu = document.querySelector('.admin-menu');
    if (!menu) return;

    if (menu.hasAttribute('open') && !menu.contains(e.target)) {
        menu.removeAttribute('open');
    }
});
