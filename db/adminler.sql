CREATE TABLE adminler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kullanici_adi VARCHAR(50) NOT NULL UNIQUE,
    sifre_hash VARCHAR(255) NOT NULL,
    olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

INSERT INTO adminler (kullanici_adi, sifre_hash) VALUES
('admin', '$2b$12$ePE/Zd19BCosSJSDMC//p.SDRjhJ/Q1GNbnDPTtqwXtqVgWz/xbie');
