# Laravel Notes v1.1: Panduan Fitur Baru

Tutorial ini menjelaskan perubahan dan penambahan fitur pada versi 1.1 dari aplikasi "Laravel Notes", termasuk migrasi database ke MySQL, serta implementasi fungsionalitas pencarian dan paginasi.

## Langkah 1: Mengubah Konfigurasi Database ke MySQL

Pada versi ini, kita beralih dari SQLite ke MySQL untuk skalabilitas dan performa yang lebih baik di lingkungan produksi.

### 1.1. Membuat Database di MySQL

Sebelum mengonfigurasi Laravel, Anda perlu membuat database baru di server MySQL Anda. Anda bisa menggunakan alat bantu seperti phpMyAdmin, Sequel Pro, atau langsung dari command line MySQL.

```sql
CREATE DATABASE laravel_notes;
```

Pastikan server MySQL Anda berjalan, biasanya di `127.0.0.1` (localhost) dengan port `3306`.

### 1.2. Memperbarui File `.env`

Selanjutnya, kita perlu memberitahu Laravel untuk menggunakan database baru ini. Buka file `.env` di direktori utama proyek dan perbarui variabel database.

**Sebelum:**
```env
DB_CONNECTION=sqlite
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=laravel
# DB_USERNAME=root
# DB_PASSWORD=
```

**Sesudah:**
```env
# DB_CONNECTION=sqlite
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_notes
DB_USERNAME=root
DB_PASSWORD=
```
*Catatan: Sesuaikan `DB_USERNAME` dan `DB_PASSWORD` jika Anda menggunakan kredensial yang berbeda untuk MySQL.*

### 1.3. Menjalankan Migrasi Database

Setelah konfigurasi selesai, jalankan migrasi untuk membuat semua tabel aplikasi (seperti `users`, `notes`, dll.) di dalam database `laravel_notes` Anda.

```bash
php artisan migrate
```
Jika Anda sudah memiliki tabel dari database SQLite sebelumnya dan ingin memulai dari awal, Anda bisa menggunakan perintah `migrate:fresh`.

## Langkah 2: Implementasi Pencarian dan Paginasi di Controller

Untuk memungkinkan pengguna mencari catatan dan tidak memuat semua catatan sekaligus, kita akan memperbarui logika di `NoteController`.

### 2.1. Memperbarui Metode `index` di `NoteController.php`

Buka file `app/Http/Controllers/NoteController.php` dan perbarui metode `index` untuk menangani input pencarian dan memaginasi hasil.

```php
// app/Http/Controllers/NoteController.php

// ... (impor kelas lain)
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function index(Request $request)
    {
        $query = auth()->user()->notes();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $notes = $query->latest()->paginate(10);

        return view('notes.index', compact('notes', 'search'));
    }

    // ... (metode-metode lain)
}
```
*   **Logika Pencarian:** Jika ada input `search` dari *request*, kita menambahkan klausa `where` untuk mencari kata kunci di kolom `title` atau `content`.
*   **Paginasi:** Metode `get()` diubah menjadi `paginate(10)` untuk membatasi hasil menjadi 10 catatan per halaman.

## Langkah 3: Menambahkan Formulir Pencarian dan Paginasi ke Tampilan

Terakhir, kita perlu memperbarui tampilan `notes/index.blade.php` untuk menampilkan formulir pencarian dan tautan navigasi halaman.

### 3.1. Memperbarui `resources/views/notes/index.blade.php`

```html
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Notes') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="flex justify-between mb-4">
                        <form method="GET" action="{{ route('notes.index') }}" class="flex-grow mr-4">
                            <x-text-input type="text" name="search" placeholder="Search notes..." class="w-full" :value="request()->input('search')" />
                        </form>
                        <a href="{{ route('notes.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            {{ __('Create Note') }}
                        </a>
                    </div>

                    <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                        <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="px-6 py-3">
                                        Title
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        Content
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        <span class="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($notes as $note)
                                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                            {{ $note->title }}
                                        </th>
                                        <td class="px-6 py-4">
                                            {{ Str::limit($note->content, 50) }}
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <a href="{{ route('notes.show', $note) }}" class="font-medium text-blue-600 dark:text-blue-500 hover:underline">View</a>
                                            <a href="{{ route('notes.edit', $note) }}" class="font-medium text-blue-600 dark:text-blue-500 hover:underline ml-4">Edit</a>
                                            <form action="{{ route('notes.destroy', $note) }}" method="POST" class="inline-block ml-4">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="font-medium text-red-600 dark:text-red-500 hover:underline">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                        <td colspan="3" class="px-6 py-4 text-center">
                                            No notes found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $notes->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```
