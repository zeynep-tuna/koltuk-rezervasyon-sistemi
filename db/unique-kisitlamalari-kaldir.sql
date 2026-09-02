-- Soft delete (deleted=0/1) kullanan tablolarda, silinen kayitlarin
-- ayni benzersiz degeri (TC, kullanici adi vs.) tekrar kullanilabilmesi icin
-- veritabani seviyesindeki UNIQUE kisitlamalari kaldiriliyor.
-- Tekillik kontrolu artik kod tarafinda (WHERE deleted = 0 sartiyla) yapiliyor.

ALTER TABLE vatandaslar DROP INDEX tc_no_unique;
ALTER TABLE adminler DROP INDEX kullanici_adi;
