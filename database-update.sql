-- Update struktur database untuk informasi tambahan
-- Jalankan script ini di phpMyAdmin atau MySQL client

USE taniasli;

-- Tambah kolom no_hp ke tabel users (jika belum ada)
-- Cek dulu apakah kolom sudah ada sebelum menambah
ALTER TABLE users 
ADD COLUMN no_hp VARCHAR(20) AFTER email;

-- Tambah kolom no_hp ke tabel toko (jika belum ada)
ALTER TABLE toko 
ADD COLUMN no_hp VARCHAR(20) AFTER deskripsi;

-- Tambah kolom alamat ke tabel toko (jika belum ada)
ALTER TABLE toko 
ADD COLUMN alamat TEXT AFTER no_hp;

-- Tampilkan struktur tabel untuk verifikasi
DESCRIBE users;
DESCRIBE toko;
