-- Eski admin_notlar tablosunu (tek not/gun mantigi) kaldirip,
-- coklu not + tamamlandi isareti destekleyen yeni yapiyi kuruyoruz.
DROP TABLE IF EXISTS admin_notlar;

CREATE TABLE admin_notlar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    tarih DATE NOT NULL,
    not_metni TEXT NOT NULL,
    tamamlandi TINYINT(1) NOT NULL DEFAULT 0,
    olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
