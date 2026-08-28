CREATE TABLE vatandaslar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tc_no VARCHAR(11) NOT NULL,
    ad VARCHAR(50) NOT NULL,
    soyad VARCHAR(50) NOT NULL,
    dogum_tarihi DATE NOT NULL,
    telefon VARCHAR(15) DEFAULT NULL,
    deleted TINYINT(1) NOT NULL DEFAULT 0,
    olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY tc_no_unique (tc_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
