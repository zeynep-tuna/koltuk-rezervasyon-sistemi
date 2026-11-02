# Koltuk Rezervasyon Sistemi

Belediye etkinlikleri (konser, tiyatro vb.) için geliştirilmiş, koltuk seçmeli bir rezervasyon sistemi. Vatandaşlar etkinlik ve koltuk seçip kimlik doğrulamasıyla ücretsiz bilet alır; yöneticiler etkinlikleri, koltukları ve rezervasyonları bir admin panelinden yönetir. Staj projesi olarak geliştirilmiştir.

## Teknolojiler

- **Backend:** PHP (PDO)
- **Veritabanı:** MySQL / MariaDB
- **Frontend:** HTML, CSS, Vanilla JavaScript
- **Kütüphaneler:** qrcodejs (QR kod üretimi), html2canvas (bilet indirme)
- **Yerel ortam:** XAMPP

## Özellikler

### Vatandaş tarafı
- Etkinlikleri kimlik girmeden görüntüleme, ardından koltuk seçimi
- TC kimlik no + doğum tarihi ile doğrulama
- Aynı etkinlikte en fazla 4 koltuk seçimi
- QR kodlu bilet oluşturma ve görsel olarak indirme
- **Rezervasyonlarım** sayfası: filtreleme, arama, iptal
- Etkinliğe 3 gün kalana kadar iptal imkânı (kural hem arayüzde hem sunucuda kontrol edilir)
- Mobil ve dar ekranlara uyumlu arayüz

### Eşzamanlı koltuk seçimi
Aynı anda birden fazla kişi rezervasyon yaparken çakışmayı önlemek için:
- Koltuk haritası 2 saniyede bir otomatik yenilenir (polling)
- Bir koltuk seçildiğinde 5 dakikalığına o kişiye özel **geçici olarak kilitlenir**, diğer kullanıcılarda dolu görünür
- Koltuk bırakılırsa, sayfa kapatılırsa veya süre dolarsa kilit otomatik kalkar
- Kayıt anında son bir müsaitlik kontrolü daha yapılır

### Admin paneli
- **Etkinlikler:** ekleme, düzenleme, gizli/aktif yapma, silme
- **Koltuklar:** koltuk haritası oluşturma, toplu seçim ve boş / dolu / protokol olarak işaretleme
- **Vatandaşlar:** kayıt ekleme, düzenleme, arama
- **Rezervasyonlar:** filtreleme (etkinlik, giriş durumu, tür), arama, sayfalama, çoklu seçimle toplu iptal, giriş durumu işaretleme
- Silme işlemleri **soft delete** (`deleted` sütunu) ile yapılır; kayıtlar veritabanından fiziksel olarak silinmez

### QR ile giriş kontrolü
- Biletteki QR kod, `giris-kontrol.php` sayfasına giden bir bağlantı taşır
- Sayfa yalnızca **admin girişi yapmış görevliler** tarafından açılabilir
- QR okutulduğunda bilet otomatik olarak "giriş yaptı" işaretlenir
- QR okunamazsa 12 haneli **referans kodu** elle girilebilir
- Daha önce kullanılmış biletler tekrar işaretlenmez, giriş saati gösterilir

## Proje Yapısı

```
koltuk-rezervasyon-sistemi/
├── rezervasyon/   # Vatandaş sayfaları ve uç noktaları (rezervasyon, rezervasyonlarım, giriş kontrol, koltuk kilitleme)
├── admin/         # Admin paneli sayfaları
├── css/           # Stil dosyaları
├── js/            # Admin paneli yardımcı script'leri
└── includes/      # Veritabanı bağlantısı, admin oturum kontrolü, ortak parçalar
```

## Kurulum

1. [XAMPP](https://www.apachefriends.org/) kurun ve Apache ile MySQL'i başlatın.
2. Projeyi `C:\xampp\htdocs\` altına klonlayın:
   ```bash
   git clone https://github.com/zeynep-tuna/koltuk-rezervasyon-sistemi.git
   ```
3. phpMyAdmin'de `koltuk_rezervasyon_sistemi` adlı bir veritabanı oluşturup tabloları içe aktarın.
4. `includes/db.php` dosyasındaki veritabanı bağlantı bilgilerini kendi ortamınıza göre düzenleyin.
5. Koltuk geçici kilitleme için `koltuklar` tablosuna şu sütunları ekleyin:
   ```sql
   ALTER TABLE koltuklar
   ADD COLUMN tutan_oturum VARCHAR(64) NULL,
   ADD COLUMN tutulma_bitis DATETIME NULL;
   ```
6. Tarayıcıdan açın:
   - Vatandaş: `http://localhost/koltuk-rezervasyon-sistemi/rezervasyon/rezervasyon.php`
   - Admin: `http://localhost/koltuk-rezervasyon-sistemi/admin/`

> **Not:** Telefondan QR okutarak test etmek için sayfaları `localhost` yerine bilgisayarın yerel ağ IP adresiyle (örn. `192.168.x.x`) açmanız gerekir. Aksi halde QR içindeki bağlantı telefondan erişilemez.

## Veritabanı

Ana tablolar: `etkinlikler`, `koltuklar`, `rezervasyonlar`, `vatandaslar`.

## Geliştirici

[zeynep-tuna](https://github.com/zeynep-tuna)
