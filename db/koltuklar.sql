CREATE TABLE koltuklar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    etkinlik_id INT NOT NULL,
    koltuk_no VARCHAR(10) NOT NULL,
    durum ENUM('bos', 'dolu') NOT NULL DEFAULT 'bos',
    deleted TINYINT(1) NOT NULL DEFAULT 0,
    olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (etkinlik_id) REFERENCES etkinlikler(id),
    UNIQUE KEY etkinlik_koltuk_unique (etkinlik_id, koltuk_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
