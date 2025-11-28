# 📘 Panduan Instalasi & Penggunaan Repository

Selamat datang di repository tutorial ini! Proyek ini disusun secara bertahap untuk memudahkan proses pembelajaran Anda.

> **Penting:** Setiap "tahap" atau langkah dalam tutorial ini disimpan dalam **branch (cabang) Git** yang berbeda. Hal ini memungkinkan Anda untuk melihat kode pada titik waktu tertentu tanpa bingung dengan perubahan di masa depan.

---

## 📋 Prasyarat

Sebelum memulai, pastikan Anda telah menginstal Git di komputer Anda.

- Cek instalasi dengan perintah:  
git --version

- Jika belum terpasang, unduh Git di [git-scm.com](https://git-scm.com)

---

## 🚀 Cara Mengunduh (Clone)

Anda memiliki dua opsi utama untuk mengunduh proyek ini, tergantung kebutuhan Anda:

### Opsi 1: Mengunduh Seluruh Proyek (Direkomendasikan)
Gunakan jika ingin mengikuti tutorial dari awal hingga akhir dan berpindah antar tahap dengan bebas.

1. Buka terminal atau Git Bash.
2. Jalankan perintah berikut:  
git clone https://github.com/madasepandri/laravel_notes.git
3. Masuk ke direktori proyek:  
cd repository-anda
4. Secara default Anda akan berada di branch `main` (atau `master`). Untuk melihat semua tahap yang tersedia:  
git branch -a

### Opsi 2: Mengunduh Hanya Tahap Tertentu (Hemat Penyimpanan)
Gunakan jika hanya tertarik pada satu tahap spesifik.

Format perintah:  
git clone --branch <nama-branch> --single-branch https://github.com/madasepandri/laravel_notes.git

Contoh untuk mengunduh hanya cabang quickstart:  
git clone --branch quickstart --single-branch https://github.com/madasepandri/laravel_notes.git

---

## 🔀 Cara Berpindah Antar Tahap (Branch)

Jika menggunakan Opsi 1, Anda dapat berpindah antar tahap kapan saja:

- Lihat daftar tahap:  
git branch -r
- Pindah ke tahap yang diinginkan, misal quickstart:  
git checkout quickstart
- Kembali ke kode awal (versi utama):  
git checkout main

> ⚠️ **Catatan Penting:** Pastikan tidak ada perubahan file yang belum disimpan (uncommitted changes) sebelum berpindah branch. Simpan perubahan (commit) atau simpan sementara (stash) agar tidak terjadi konflik.

---

## 🛠️ Instalasi Dependensi

Setelah berada di branch yang diinginkan, install dependensi proyek dengan:

composer install


---

## 🤝 Berkontribusi

Jika Anda menemukan bug atau kesalahan di salah satu tahap tutorial, silakan buat **Issue** atau ajukan **Pull Request** ke branch yang relevan.

---

Selamat belajar dan semoga bermanfaat!
