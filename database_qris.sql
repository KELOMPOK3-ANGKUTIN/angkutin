-- Jalankan sekali pada database angkutin_db melalui phpMyAdmin > SQL.
-- Menambahkan kolom bukti pembayaran tanpa menghapus data pesanan lama.
ALTER TABLE pesanan
    ADD COLUMN bukti_pembayaran VARCHAR(255) NULL AFTER foto_bukti,
    ADD COLUMN status_pembayaran ENUM('menunggu','diunggah') NOT NULL DEFAULT 'menunggu' AFTER bukti_pembayaran;
