ALTER TABLE etkinlikler
DROP COLUMN etkinlik_tarihi,
ADD COLUMN etkinlik_tarih DATE NULL DEFAULT NULL AFTER yer,
ADD COLUMN etkinlik_saat TIME NULL DEFAULT NULL AFTER etkinlik_tarih;
