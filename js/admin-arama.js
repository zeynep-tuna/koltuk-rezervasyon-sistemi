document.addEventListener('DOMContentLoaded', function () {
    var aramaKutusu = document.querySelector('.admin-arama input[name="q"]');
    var aramaFormu = document.querySelector('.admin-arama');
    var tablo = document.querySelector('.admin-tablo tbody');

    if (!aramaKutusu || !tablo) return;

    aramaFormu.addEventListener('submit', function (e) {
        e.preventDefault();
    });

    aramaKutusu.addEventListener('input', function () {
        var aranan = aramaKutusu.value.trim().toLocaleLowerCase('tr-TR');
        var satirlar = tablo.querySelectorAll('tr');

        satirlar.forEach(function (satir) {
            var satirMetni = satir.textContent.toLocaleLowerCase('tr-TR');
            satir.style.display = satirMetni.includes(aranan) ? '' : 'none';
        });
    });
});
