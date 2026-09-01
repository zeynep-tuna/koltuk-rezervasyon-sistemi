ALTER TABLE koltuklar
MODIFY COLUMN durum ENUM('bos', 'dolu', 'protokol') NOT NULL DEFAULT 'bos';
