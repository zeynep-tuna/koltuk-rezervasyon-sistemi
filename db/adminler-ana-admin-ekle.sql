ALTER TABLE adminler
ADD COLUMN ana_admin TINYINT(1) NOT NULL DEFAULT 0;

UPDATE adminler SET ana_admin = 1 WHERE kullanici_adi = 'admin';
