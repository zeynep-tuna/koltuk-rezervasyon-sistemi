CREATE TABLE rezervasyonlar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vatandas_id INT NOT NULL,
    etkinlik_id INT NOT NULL,
    koltuk_id INT NOT NULL,
    qr_token VARCHAR(64) NOT NULL,
    deleted TINYINT(1) NOT NULL DEFAULT 0,
    olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vatandas_id) REFERENCES vatandaslar(id),
    FOREIGN KEY (etkinlik_id) REFERENCES etkinlikler(id),
    FOREIGN KEY (koltuk_id) REFERENCES koltuklar(id),
    UNIQUE KEY koltuk_rezervasyon_unique (koltuk_id),
    UNIQUE KEY qr_token_unique (qr_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
