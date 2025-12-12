# Tutorial Cepat: Membangun Aplikasi Catatan dengan Laravel 12

Tutorial ini akan memandu Anda melalui proses pembuatan aplikasi web sederhana untuk mengelola catatan pribadi menggunakan Laravel 12. Aplikasi ini akan memiliki fitur autentikasi pengguna (registrasi dan login) serta operasi CRUD (Create, Read, Update, Delete) untuk catatan.

## Langkah 1: Inisialisasi Proyek Laravel

Pertama, kita akan membuat proyek Laravel baru menggunakan Composer. Perintah ini akan mengunduh kerangka kerja Laravel beserta semua dependensi yang diperlukan ke dalam direktori baru bernama `laravel-notes`.

```bash
composer create-project laravel/laravel laravel-notes "12.*"
```

Setelah proyek dibuat, Laravel secara otomatis mengonfigurasi beberapa hal penting, termasuk membuat file `.env` dari `.env.example` dan menghasilkan kunci aplikasi (`APP_KEY`).

## Langkah 2: Konfigurasi Database

Untuk kemudahan, kita akan menggunakan SQLite sebagai database. Konfigurasi default Laravel 12 sudah diatur untuk menggunakan SQLite, jadi kita tidak perlu mengubah file `.env`. Proses instalasi juga sudah membuatkan file `database/database.sqlite` untuk kita.

Jika Anda membuka file `.env`, Anda akan melihat baris berikut yang mengonfirmasi penggunaan SQLite:

```env
DB_CONNECTION=sqlite
```

## Langkah 3: Menyiapkan Autentikasi dengan Laravel Breeze

Laravel Breeze adalah *starter kit* yang menyediakan implementasi sederhana untuk semua sistem autentikasi Laravel, termasuk login, registrasi, reset password, dan verifikasi email.

1.  **Instal Breeze:**
    Buka terminal di direktori proyek (`laravel-notes`) dan jalankan perintah berikut untuk menginstal Breeze.

    ```bash
    composer require laravel/breeze --dev
    ```

2.  **Scaffolding Blade:**
    Setelah Breeze terinstal, kita akan menggunakannya untuk membuat *scaffolding* (kerangka) autentikasi dengan Blade sebagai *templating engine*.

    ```bash
    php artisan breeze:install blade --dark --pest
    ```
    *   `blade`: Menggunakan Blade dengan AlpineJS.
    *   `--dark`: Menambahkan dukungan mode gelap (opsional).
    *   `--pest`: Menggunakan Pest sebagai kerangka kerja pengujian (opsional).

3.  **Instal Dependensi Frontend dan Migrasi:**
    Breeze akan menambahkan *view*, *controller*, dan rute autentikasi ke proyek Anda. Kita perlu menginstal dependensi NPM dan menjalankan migrasi untuk membuat tabel `users`.

    ```bash
    npm install
    npm run build
    php artisan migrate
    ```

Sekarang, sistem registrasi dan login Anda sudah berfungsi penuh.

## Langkah 4: Membuat Model dan Migrasi untuk Catatan

Setiap catatan akan memiliki judul, konten, dan akan terhubung dengan seorang pengguna.

1.  **Buat Migrasi:**
    Jalankan perintah Artisan berikut untuk membuat file migrasi baru untuk tabel `notes`.

    ```bash
    php artisan make:migration create_notes_table
    ```

2.  **Definisikan Skema Tabel:**
    Buka file migrasi yang baru dibuat di `database/migrations/`. Di dalam method `up()`, tambahkan kolom untuk `user_id`, `title`, dan `content`.

    ```php
    // database/migrations/xxxx_xx_xx_xxxxxx_create_notes_table.php
    Schema::create('notes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->string('title');
        $table->text('content');
        $table->timestamps();
    });
    ```
    *   `foreignId('user_id')->constrained()->onDelete('cascade')`: Membuat kolom `user_id` sebagai *foreign key* yang terhubung ke tabel `users`. Jika seorang pengguna dihapus, semua catatannya juga akan terhapus.

3.  **Jalankan Migrasi:**
    Jalankan migrasi untuk membuat tabel `notes` di database.

    ```bash
    php artisan migrate
    ```

## Langkah 5: Membuat Model dan Relasi

1.  **Buat Model Note:**

    ```bash
    php artisan make:model Note
    ```

2.  **Definisikan Relasi:**
    Buka model `app/Models/Note.php` dan atur properti `$fillable` serta definisikan relasi `belongsTo` ke model `User`.

    ```php
    // app/Models/Note.php
    <?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Factories\HasFactory;
    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Database\Eloquent\Relations\BelongsTo;

    class Note extends Model
    {
        use HasFactory;

        protected $fillable = [
            'title',
            'content',
        ];

        public function user(): BelongsTo
        {
            return $this->belongsTo(User::class);
        }
    }
    ```

    Selanjutnya, buka model `app/Models/User.php` dan definisikan relasi `hasMany` ke model `Note`.

    ```php
    // app/Models/User.php
    // ... (impor kelas lain)
    use Illuminate\Database\Eloquent\Relations\HasMany;

    class User extends Authenticatable
    {
        // ... (properti lain)

        public function notes(): HasMany
        {
            return $this->hasMany(Note::class);
        }
    }
    ```

## Langkah 6: Membuat Controller dan Rute

1.  **Buat Resource Controller:**
    Kita akan membuat `NoteController` yang akan menangani semua permintaan HTTP untuk CRUD catatan.

    ```bash
    php artisan make:controller NoteController --resource --model=Note
    ```
    Opsi `--resource` dan `--model` akan membuat *controller* dengan metode-metode yang sudah diisi *type-hinting* untuk model `Note`.

2.  **Definisikan Rute:**
    Buka `routes/web.php` dan tambahkan rute *resource* untuk `NoteController` di dalam grup *middleware* `auth`.

    ```php
    // routes/web.php
    use App\Http\Controllers\NoteController;
    // ...

    Route::middleware('auth')->group(function () {
        // ... (rute profil)

        Route::resource('notes', NoteController::class);
    });
    ```

## Langkah 7: Implementasi Logika Controller

Sekarang kita isi logika untuk setiap metode di `app/Http/Controllers/NoteController.php`.

```php
<?php

namespace App\Http\Controllers;

use App\Models\Note;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function index()
    {
        $notes = auth()->user()->notes()->latest()->get();
        return view('notes.index', compact('notes'));
    }

    public function create()
    {
        return view('notes.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        auth()->user()->notes()->create($request->only('title', 'content'));

        return redirect()->route('notes.index');
    }

    public function show(Note $note)
    {
        if ($note->user_id !== auth()->id()) {
            abort(403);
        }
        return view('notes.show', compact('note'));
    }

    public function edit(Note $note)
    {
        if ($note->user_id !== auth()->id()) {
            abort(403);
        }
        return view('notes.edit', compact('note'));
    }

    public function update(Request $request, Note $note)
    {
        if ($note->user_id !== auth()->id()) {
            abort(403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $note->update($request->only('title', 'content'));

        return redirect()->route('notes.index');
    }

    public function destroy(Note $note)
    {
        if ($note->user_id !== auth()->id()) {
            abort(403);
        }

        $note->delete();

        return redirect()->route('notes.index');
    }
}
```
*Penting*: Pengecekan `$note->user_id !== auth()->id()` adalah langkah keamanan sederhana untuk memastikan pengguna hanya bisa mengakses dan memodifikasi catatannya sendiri.

## Langkah 8: Membuat Tampilan (Views) untuk Catatan

Kita akan membuat direktori `resources/views/notes` dan file-file Blade di dalamnya.

1.  **Tampilan Daftar Catatan (`index.blade.php`):**

    ```html
    <!-- resources/views/notes/index.blade.php -->
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
                        <div class="flex justify-end mb-4">
                            <a href="{{ route('notes.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                {{ __('Create Note') }}
                            </a>
                        </div>

                        <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                            <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th scope="col" class="px-6 py-3">Title</th>
                                        <th scope="col" class="px-6 py-3">Content</th>
                                        <th scope="col" class="px-6 py-3"><span class="sr-only">Actions</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($notes as $note)
                                        <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                            <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">{{ $note->title }}</th>
                                            <td class="px-6 py-4">{{ Str::limit($note->content, 50) }}</td>
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
                                            <td colspan="3" class="px-6 py-4 text-center">No notes found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-app-layout>
    ```

2.  **Tampilan Membuat Catatan (`create.blade.php`):**

    ```html
    <!-- resources/views/notes/create.blade.php -->
    <x-app-layout>
        <x-slot name="header">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Create Note') }}
            </h2>
        </x-slot>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <form method="POST" action="{{ route('notes.store') }}">
                            @csrf
                            <div>
                                <x-input-label for="title" :value="__('Title')" />
                                <x-text-input id="title" class="block mt-1 w-full" type="text" name="title" :value="old('title')" required autofocus />
                                <x-input-error :messages="$errors->get('title')" class="mt-2" />
                            </div>
                            <div class="mt-4">
                                <x-input-label for="content" :value="__('Content')" />
                                <textarea id="content" name="content" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">{{ old('content') }}</textarea>
                                <x-input-error :messages="$errors->get('content')" class="mt-2" />
                            </div>
                            <div class="flex items-center justify-end mt-4">
                                <x-primary-button>{{ __('Save') }}</x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </x-app-layout>
    ```

3.  **Tampilan Mengedit Catatan (`edit.blade.php`):**

    ```html
    <!-- resources/views/notes/edit.blade.php -->
    <x-app-layout>
        <x-slot name="header">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Edit Note') }}
            </h2>
        </x-slot>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <form method="POST" action="{{ route('notes.update', $note) }}">
                            @csrf
                            @method('PATCH')
                            <div>
                                <x-input-label for="title" :value="__('Title')" />
                                <x-text-input id="title" class="block mt-1 w-full" type="text" name="title" :value="old('title', $note->title)" required autofocus />
                                <x-input-error :messages="$errors->get('title')" class="mt-2" />
                            </div>
                            <div class="mt-4">
                                <x-input-label for="content" :value="__('Content')" />
                                <textarea id="content" name="content" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">{{ old('content', $note->content) }}</textarea>
                                <x-input-error :messages="$errors->get('content')" class="mt-2" />
                            </div>
                            <div class="flex items-center justify-end mt-4">
                                <x-primary-button>{{ __('Update') }}</x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </x-app-layout>
    ```

## Langkah 9: Memperbarui Navigasi

Terakhir, kita akan menambahkan tautan ke halaman "Beranda" (Dashboard) dan "Catatan" (Notes) di menu navigasi utama.

Buka file `resources/views/layouts/navigation.blade.php`. Temukan bagian "Navigation Links" dan tambahkan tautan untuk `notes.index`.

```php
<!-- resources/views/layouts/navigation.blade.php -->

<!-- Primary Navigation Menu -->
<!-- ... -->
    <!-- Navigation Links -->
    <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
            {{ __('Beranda') }}
        </x-nav-link>
        <x-nav-link :href="route('notes.index')" :active="request()->routeIs('notes.index')">
            {{ __('Catatan') }}
        </x-nav-link>
    </div>
<!-- ... -->

<!-- Responsive Navigation Menu -->
<div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
    <div class="pt-2 pb-3 space-y-1">
        <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
            {{ __('Beranda') }}
        </x-responsive-nav-link>
        <x-responsive-nav-link :href="route('notes.index')" :active="request()->routeIs('notes.index')">
            {{ __('Catatan') }}
        </x-responsive-nav-link>
    </div>
    <!-- ... -->
</div>
```

