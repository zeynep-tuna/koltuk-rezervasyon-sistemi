ALTER TABLE rezervasyonlar
MODIFY COLUMN vatandas_id INT NULL,
ADD COLUMN protokol_adi VARCHAR(150) NULL DEFAULT NULL AFTER vatandas_id;
