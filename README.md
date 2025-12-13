# Tutorial: Mengembangkan Aplikasi Laravel dengan Role-Based Access Control (RBAC) dan Multi-Tenancy

Tutorial ini akan memandu Anda melalui langkah-langkah implementasi sistem Role-Based Access Control (RBAC) dengan tiga peran pengguna (Super Admin, Admin, User) dan fungsionalitas multi-tenancy berbasis organisasi dalam aplikasi Laravel yang sudah ada. Kami juga akan membahas bagaimana Admin dapat mengelola pengguna dan kategori catatan dalam organisasinya, serta bagaimana registrasi pengguna umum dinonaktifkan.

## Pendahuluan

Tujuan tutorial ini adalah untuk memperluas fungsionalitas aplikasi Laravel dasar (contoh: "Laravel Notes") dengan menambahkan lapisan otorisasi dan isolasi data yang kompleks. Fitur-fitur utama yang akan kita implementasikan meliputi:

*   **Peran Pengguna (User Roles):**
    *   **Super Admin:** Mengelola Admin di seluruh sistem.
    *   **Admin:** Mengelola Pengguna dan Kategori Catatan dalam organisasinya sendiri. Dapat melihat dan mengedit semua catatan yang dibuat oleh Pengguna dalam organisasinya.
    *   **User:** Membuat catatan dengan kategori yang telah ditentukan oleh Admin organisasinya.
*   **Multi-Tenancy:** Admin dan Pengguna hanya dapat melihat dan mengelola data yang terkait dengan organisasi mereka sendiri. Super Admin memiliki akses global untuk manajemen Admin.
*   **Pendaftaran Pengguna:** Registrasi pengguna umum dinonaktifkan; Pengguna hanya dapat didaftarkan oleh Admin.

**Asumsi:** Anda sudah memiliki aplikasi Laravel yang berjalan dan dasar-dasar pengembangan Laravel.

---

## 1. Perubahan Skema Database

Langkah pertama adalah memodifikasi skema database untuk mendukung peran pengguna dan multi-tenancy.

### 1.1. Membuat Tabel `organizations`

Tabel ini akan menyimpan informasi tentang setiap organisasi.

*   **Perintah Artisan:**
    ```bash
    php artisan make:migration create_organizations_table
    ```
*   **Modifikasi File Migrasi (`database/migrations/..._create_organizations_table.php`):**
    Tambahkan kolom `name`.
    ```php
    // ...
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nama Organisasi
            $table->timestamps();
        });
    }
    // ...
    ```

### 1.2. Membuat Tabel `categories`

Tabel ini akan menyimpan kategori catatan, yang akan terhubung ke organisasi tertentu.

*   **Perintah Artisan:**
    ```bash
    php artisan make:migration create_categories_table
    ```
*   **Modifikasi File Migrasi (`database/migrations/..._create_categories_table.php`):**
    Tambahkan kolom `name` dan `organization_id` sebagai *foreign key*.
    ```php
    // ...
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nama Kategori
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('cascade');
            $table->timestamps();
        });
    }
    // ...
    ```

### 1.3. Menambahkan Peran dan Organisasi ke Tabel `users`

Kita akan menambahkan kolom `role` untuk menyimpan peran pengguna dan `organization_id` untuk menghubungkan pengguna dengan organisasinya.

*   **Perintah Artisan:**
    ```bash
    php artisan make:migration add_role_and_organization_to_users_table --table=users
    ```
*   **Modifikasi File Migrasi (`database/migrations/..._add_role_and_organization_to_users_table.php`):**
    Tambahkan kolom `role` (enum) dan `organization_id` (foreign key).
    ```php
    // ...
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['super_admin', 'admin', 'user'])->default('user')->after('password');
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->onDelete('set null')->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn(['role', 'organization_id']);
        });
    }
    // ...
    ```

### 1.4. Menambahkan Kategori dan Organisasi ke Tabel `notes`

Catatan akan terhubung ke kategori dan organisasi asalnya.

*   **Perintah Artisan:**
    ```bash
    php artisan make:migration add_category_and_organization_to_notes_table --table=notes
    ```
*   **Modifikasi File Migrasi (`database/migrations/..._add_category_and_organization_to_notes_table.php`):**
    Tambahkan kolom `category_id` dan `organization_id` sebagai *foreign key*.
    ```php
    // ...
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('set null')->after('user_id');
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('cascade')->after('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropForeign(['organization_id']);
            $table->dropColumn(['category_id', 'organization_id']);
        });
    }
    // ...
    ```

### 1.5. Jalankan Migrasi

Setelah semua file migrasi selesai, jalankan perintah untuk memperbarui database Anda:

```bash
php artisan migrate
```

---

## 2. Pembaruan Model & Relasi Eloquent

Setelah skema database diperbarui, kita perlu menyesuaikan model-model Eloquent agar merefleksikan relasi baru.

### 2.1. Membuat Model `Organization`

*   **Perintah Artisan:**
    ```bash
    php artisan make:model Organization
    ```
*   **Modifikasi File Model (`app/Models/Organization.php`):**
    Tambahkan properti `$fillable` dan relasi `users()` serta `categories()`.
    ```php
    <?php
    namespace App\Models;
    use Illuminate\Database\Eloquent\Factories\HasFactory;
    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Database\Eloquent\Relations\HasMany;

    class Organization extends Model
    {
        use HasFactory;
        protected $fillable = [
            'name',
        ];

        public function users(): HasMany
        {
            return $this->hasMany(User::class);
        }

        public function categories(): HasMany
        {
            return $this->hasMany(Category::class);
        }
    }
    ```

### 2.2. Membuat Model `Category`

*   **Perintah Artisan:**
    ```bash
    php artisan make:model Category
    ```
*   **Modifikasi File Model (`app/Models/Category.php`):**
    Tambahkan properti `$fillable` dan relasi `organization()` serta `notes()`.
    ```php
    <?php
    namespace App\Models;
    use Illuminate\Database\Eloquent\Factories\HasFactory;
    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Database\Eloquent\Relations\BelongsTo;
    use Illuminate\Database\Eloquent\Relations\HasMany;

    class Category extends Model
    {
        use HasFactory;
        protected $fillable = [
            'name',
            'organization_id',
        ];

        public function organization(): BelongsTo
        {
            return $this->belongsTo(Organization::class);
        }

        public function notes(): HasMany
        {
            return $this->hasMany(Note::class);
        }
    }
    ```

### 2.3. Memperbarui Model `User`

*   **Modifikasi File Model (`app/Models/User.php`):**
    Tambahkan `organization_id` dan `role` ke `$fillable` dan definisikan relasi `organization()`.
    ```php
    <?php
    namespace App\Models;
    // ...
    use Illuminate\Database\Eloquent\Relations\BelongsTo;
    use Illuminate\Database\Eloquent\Relations\HasMany;

    class User extends Authenticatable
    {
        // ...
        protected $fillable = [
            'name',
            'email',
            'password',
            'organization_id',
            'role',
        ];
        // ...
        public function organization(): BelongsTo
        {
            return $this->belongsTo(Organization::class);
        }
        // ...
    }
    ```

### 2.4. Memperbarui Model `Note`

*   **Modifikasi File Model (`app/Models/Note.php`):**
    Tambahkan `category_id` dan `organization_id` ke `$fillable` dan definisikan relasi `category()` serta `organization()`.
    ```php
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
            'category_id',
            'organization_id',
        ];

        public function user(): BelongsTo
        {
            return $this->belongsTo(User::class);
        }

        public function category(): BelongsTo
        {
            return $this->belongsTo(Category::class);
        }

        public function organization(): BelongsTo
        {
            return $this->belongsTo(Organization::class);
        }
    }
    ```

---

## 3. Mengisi Data Awal (Database Seeding)

Kita akan membuat data dummy untuk organisasi, pengguna, dan kategori agar mudah dalam pengujian.

### 3.1. Membuat Seeder untuk `Organization`, `User`, dan `Category`

*   **Perintah Artisan:**
    ```bash
    php artisan make:seeder OrganizationSeeder
    php artisan make:seeder UserSeeder
    php artisan make:seeder CategorySeeder
    ```

### 3.2. Modifikasi File Seeder

*   **`database/seeders/OrganizationSeeder.php`:**
    ```php
    <?php
    namespace Database\Seeders;
    use App\Models\Organization;
    use Illuminate\Database\Console\Seeds\WithoutModelEvents;
    use Illuminate\Database\Seeder;

    class OrganizationSeeder extends Seeder
    {
        public function run(): void
        {
            Organization::create(['name' => 'Organization A']);
            Organization::create(['name' => 'Organization B']);
        }
    }
    ```

*   **`database/seeders/UserSeeder.php`:**
    ```php
    <?php
    namespace Database\Seeders;
    use App\Models\Organization;
    use App\Models\User;
    use Illuminate\Database\Console\Seeds\WithoutModelEvents;
    use Illuminate\Database\Seeder;
    use Illuminate\Support\Facades\Hash;

    class UserSeeder extends Seeder
    {
        public function run(): void
        {
            // Create Super Admin
            User::create([
                'name' => 'Super Admin',
                'email' => 'superadmin@example.com',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
            ]);

            $organizations = Organization::all();

            foreach ($organizations as $organization) {
                // Create Admin for each organization
                User::create([
                    'name' => 'Admin ' . $organization->name,
                    'email' => 'admin_' . strtolower(str_replace(' ', '', $organization->name)) . '@example.com',
                    'password' => Hash::make('password'),
                    'role' => 'admin',
                    'organization_id' => $organization->id,
                ]);

                // Create Regular User for each organization
                User::create([
                    'name' => 'User ' . $organization->name,
                    'email' => 'user_' . strtolower(str_replace(' ', '', $organization->name)) . '@example.com',
                    'password' => Hash::make('password'),
                    'role' => 'user',
                    'organization_id' => $organization->id,
                ]);
            }
        }
    }
    ```

*   **`database/seeders/CategorySeeder.php`:**
    ```php
    <?php
    namespace Database\Seeders;
    use App\Models\Category;
    use App\Models\Organization;
    use Illuminate\Database\Console\Seeds\WithoutModelEvents;
    use Illuminate\Database\Seeder;

    class CategorySeeder extends Seeder
    {
        public function run(): void
        {
            $organizations = Organization::all();
            foreach ($organizations as $organization) {
                Category::create([
                    'name' => 'General ' . $organization->name,
                    'organization_id' => $organization->id,
                ]);
                Category::create([
                    'name' => 'Work ' . $organization->name,
                    'organization_id' => $organization->id,
                ]);
                Category::create([
                    'name' => 'Personal ' . $organization->name,
                    'organization_id' => $organization->id,
                ]);
            }
        }
    }
    ```

### 3.3. Memperbarui `DatabaseSeeder`

Panggil semua seeder yang telah dibuat dari `DatabaseSeeder` utama.

*   **Modifikasi File Seeder (`database/seeders/DatabaseSeeder.php`):**
    ```php
    <?php
    namespace Database\Seeders;
    use Illuminate\Database\Console\Seeds\WithoutModelEvents;
    use Illuminate\Database\Seeder;

    class DatabaseSeeder extends Seeder
    {
        public function run(): void
        {
            $this->call([
                OrganizationSeeder::class,
                UserSeeder::class,
                CategorySeeder::class,
            ]);
        }
    }
    ```

### 3.4. Jalankan Seeder

```bash
php artisan db:seed
```

---

## 4. Implementasi Middleware untuk Kontrol Akses

Kita akan membuat middleware untuk memverifikasi peran pengguna yang terautentikasi sebelum mengizinkan akses ke rute tertentu.

### 4.1. Membuat `RoleMiddleware`

*   **Perintah Artisan:**
    ```bash
    php artisan make:middleware RoleMiddleware
    ```
*   **Modifikasi File Middleware (`app/Http/Middleware/RoleMiddleware.php`):**
    Tambahkan logika pemeriksaan peran di method `handle()`.
    ```php
    <?php
    namespace App\Http\Middleware;
    use Closure;
    use Illuminate\Http\Request;
    use Symfony\Component\HttpFoundation\Response;
    use Illuminate\Support\Facades\Auth;

    class RoleMiddleware
    {
        public function handle(Request $request, Closure $next, ...$roles): Response
        {
            if (!Auth::check()) {
                return redirect('login');
            }

            $user = Auth::user();

            if (!in_array($user->role, $roles)) {
                abort(403, 'Unauthorized action.');
            }

            return $next($request);
        }
    }
    ```

### 4.2. Mendaftarkan `RoleMiddleware`

Daftarkan middleware ini di `bootstrap/app.php` agar dapat digunakan sebagai alias di rute.

*   **Modifikasi File (`bootstrap/app.php`):**
    ```php
    // ...
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    // ...
    ```

---

## 5. Pembaruan Sistem Rute

Kita akan mengatur rute agar sesuai dengan peran pengguna dan mengaktifkan akses ke fungsionalitas baru.

### 5.1. Modifikasi `routes/web.php`

Tambahkan grup rute untuk Super Admin dan Admin, serta sesuaikan rute `notes`.

```php
<?php
use App\Http\Controllers\NoteController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuperAdmin\AdminController as SuperAdminController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use Illuminate\Support\Facades\Route;

// ... (rute dasar lainnya)

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Super Admin Routes (hanya untuk super_admin)
    Route::middleware(['role:super_admin'])->prefix('superadmin')->name('superadmin.')->group(function () {
        Route::resource('admins', SuperAdminController::class);
    });

    // Admin Routes (hanya untuk admin)
    Route::middleware(['role:admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', AdminUserController::class);
        Route::resource('categories', AdminCategoryController::class);
    });

    // Notes Routes (dapat diakses oleh admin dan user biasa)
    Route::middleware(['role:admin,user'])->group(function () {
        Route::resource('notes', NoteController::class);
    });
});

require __DIR__.'/auth.php';
```

### 5.2. Menonaktifkan Registrasi Pengguna Umum

Hapus atau komentari rute registrasi publik untuk memastikan pengguna hanya bisa didaftarkan oleh Admin.

*   **Modifikasi File (`routes/auth.php`):**
    ```php
    // ...
    Route::middleware('guest')->group(function () {
        // Route::get('register', [RegisteredUserController::class, 'create'])
        //     ->name('register');
        // Route::post('register', [RegisteredUserController::class, 'store']);

        // ... (rute login dan reset password lainnya)
    });
    // ...
    ```
    (Catatan: Pastikan juga untuk menghapus tautan registrasi yang mungkin ada di file blade seperti `resources/views/auth/login.blade.php` atau `resources/views/layouts/guest.blade.php`.)

---

## 6. Logika Controller untuk Setiap Peran

Kita akan mengisi logika CRUD di controller yang baru dibuat dan menyesuaikan controller yang sudah ada untuk memberlakukan kontrol akses.

### 6.1. `NoteController` (Pembaruan Kontrol Akses)

Modifikasi `NoteController` untuk sepenuhnya mencegah Super Admin mengakses catatan dan memberlakukan filter organisasi/pengguna.

```php
<?php
namespace App\Http\Controllers;
use App\Models\Note;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NoteController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if ($user->role === 'super_admin') {
            abort(403, 'Super admin cannot access notes.');
        }

        $query = Note::query();
        if ($user->role === 'admin') {
            $query->where('organization_id', $user->organization_id);
        } else { // Regular user
            $query->where('user_id', $user->id);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }
        $notes = $query->latest()->paginate(10);
        
        $categories = collect();
        if ($user->organization_id) {
            $categories = $user->organization->categories()->pluck('name', 'id');
        }
        return view('notes.index', compact('notes', 'search', 'categories'));
    }

    public function create()
    {
        $user = Auth::user();
        if ($user->role === 'super_admin') {
            abort(403, 'Super admin cannot access note creation.');
        }
        $categories = collect();
        if ($user->organization_id) {
            $categories = $user->organization->categories()->pluck('name', 'id');
        }
        return view('notes.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if ($user->role !== 'user') {
            abort(403, 'Only regular users can create notes.');
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category_id' => 'required|exists:categories,id',
        ]);
        $user->notes()->create([
            'title' => $request->title,
            'content' => $request->content,
            'category_id' => $request->category_id,
            'organization_id' => $user->organization_id,
        ]);
        return redirect()->route('notes.index');
    }

    public function show(Note $note)
    {
        $user = Auth::user();
        if ($user->role === 'super_admin') {
            abort(403, 'Super admin cannot view notes.');
        } elseif ($user->role === 'admin') {
            if ($note->organization_id !== $user->organization_id) {
                abort(403);
            }
        } else { // Regular user
            if ($note->user_id !== $user->id) {
                abort(403);
            }
        }
        return view('notes.show', compact('note'));
    }

    public function edit(Note $note)
    {
        $user = Auth::user();
        if ($user->role === 'super_admin') {
            abort(403, 'Super admin cannot edit notes.');
        } elseif ($user->role === 'admin') {
            if ($note->organization_id !== $user->organization_id) {
                abort(403);
            }
        } else { // Regular user
            if ($note->user_id !== $user->id) {
                abort(403);
            }
        }
        $categories = collect();
        if ($user->organization_id) {
            $categories = $user->organization->categories()->pluck('name', 'id');
        }
        return view('notes.edit', compact('note', 'categories'));
    }

    public function update(Request $request, Note $note)
    {
        $user = Auth::user();
        if ($user->role === 'super_admin') {
            abort(403, 'Super admin cannot update notes.');
        } elseif ($user->role === 'admin') {
            if ($note->organization_id !== $user->organization_id) {
                abort(403);
            }
        } else { // Regular user
            if ($note->user_id !== $user->id) {
                abort(403);
            }
        }
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category_id' => 'required|exists:categories,id',
        ]);
        $note->update($request->only('title', 'content', 'category_id'));
        return redirect()->route('notes.index');
    }

    public function destroy(Note $note)
    {
        $user = Auth::user();
        if ($user->role === 'super_admin') {
            abort(403, 'Super admin cannot delete notes.');
        } elseif ($user->role === 'admin') {
            if ($note->organization_id !== $user->organization_id) {
                abort(403);
            }
        } else { // Regular user
            if ($note->user_id !== $user->id) {
                abort(403);
            }
        }
        $note->delete();
        return redirect()->route('notes.index');
    }
}
```

### 6.2. `SuperAdmin\AdminController` (Manajemen Admin & Otomasi Organisasi)

Controller ini akan menangani CRUD untuk admin, dengan fitur otomatis membuat organisasi baru jika belum ada.

*   **Perintah Artisan:**
    ```bash
    php artisan make:controller SuperAdmin/AdminController --resource
    ```
*   **Modifikasi File Controller (`app/Http/Controllers/SuperAdmin/AdminController.php`):**
    ```php
    <?php
    namespace App\Http\Controllers\SuperAdmin;
    use App\Http\Controllers\Controller;
    use App\Models\User;
    use App\Models\Organization;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Hash;

    class AdminController extends Controller
    {
        public function index()
        {
            $admins = User::where('role', 'admin')->paginate(10);
            return view('superadmin.admins.index', compact('admins'));
        }

        public function create()
        {
            // Tidak perlu lagi mengambil organisasi karena akan input text
            return view('superadmin.admins.create');
        }

        public function store(Request $request)
        {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'organization_name' => 'required|string|max:255', // Validasi untuk nama organisasi
            ]);

            // Cari atau buat organisasi
            $organization = Organization::firstOrCreate([
                'name' => $request->organization_name,
            ]);

            User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'admin',
                'organization_id' => $organization->id, // Kaitkan dengan ID organisasi yang ditemukan/dibuat
            ]);

            return redirect()->route('superadmin.admins.index')->with('success', 'Admin created successfully.');
        }

        public function show(string $id)
        {
            abort(404); // Tidak digunakan untuk resource ini
        }

        public function edit(User $admin)
        {
            $organizations = Organization::all(); // Tetap butuh untuk dropdown di edit
            return view('superadmin.admins.edit', compact('admin', 'organizations'));
        }

        public function update(Request $request, User $admin)
        {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email,' . $admin->id,
                'password' => 'nullable|string|min:8|confirmed',
                'organization_id' => 'required|exists:organizations,id',
            ]);

            $admin->update([
                'name' => $request->name,
                'email' => $request->email,
                'organization_id' => $request->organization_id,
                'password' => $request->password ? Hash::make($request->password) : $admin->password,
            ]);

            return redirect()->route('superadmin.admins.index')->with('success', 'Admin updated successfully.');
        }

        public function destroy(User $admin)
        {
            $admin->delete();
            return redirect()->route('superadmin.admins.index')->with('success', 'Admin deleted successfully.');
        }
    }
    ```

### 6.3. `Admin\UserController` (Manajemen Pengguna oleh Admin)

Controller ini memungkinkan Admin mengelola pengguna di dalam organisasinya.

*   **Perintah Artisan:**
    ```bash
    php artisan make:controller Admin/UserController --resource
    ```
*   **Modifikasi File Controller (`app/Http/Controllers/Admin/UserController.php`):**
    ```php
    <?php
    namespace App\Http\Controllers\Admin;
    use App\Http\Controllers\Controller;
    use App\Models\User;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Hash;

    class UserController extends Controller
    {
        public function index()
        {
            $organizationId = Auth::user()->organization_id;
            $users = User::where('organization_id', $organizationId)
                         ->where('role', 'user') // Hanya tampilkan user biasa
                         ->paginate(10);
            return view('admin.users.index', compact('users'));
        }

        public function create()
        {
            return view('admin.users.create');
        }

        public function store(Request $request)
        {
            $organizationId = Auth::user()->organization_id;

            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
            ]);

            User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'user', // Selalu 'user'
                'organization_id' => $organizationId,
            ]);

            return redirect()->route('admin.users.index')->with('success', 'User created successfully.');
        }

        public function show(string $id)
        {
            abort(404); // Tidak digunakan
        }

        public function edit(User $user)
        {
            // Pastikan user adalah milik organisasi admin dan berperan 'user'
            if ($user->organization_id !== Auth::user()->organization_id || $user->role !== 'user') {
                abort(403, 'Unauthorized action.');
            }
            return view('admin.users.edit', compact('user'));
        }

        public function update(Request $request, User $user)
        {
            // Pastikan user adalah milik organisasi admin dan berperan 'user'
            if ($user->organization_id !== Auth::user()->organization_id || $user->role !== 'user') {
                abort(403, 'Unauthorized action.');
            }

            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
                'password' => 'nullable|string|min:8|confirmed',
            ]);

            $user->update([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password ? Hash::make($request->password) : $user->password,
            ]);

            return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
        }

        public function destroy(User $user)
        {
            // Pastikan user adalah milik organisasi admin dan berperan 'user'
            if ($user->organization_id !== Auth::user()->organization_id || $user->role !== 'user') {
                abort(403, 'Unauthorized action.');
            }

            $user->delete();
            return redirect()->route('admin.users.index')->with('success', 'User deleted successfully.');
        }
    }
    ```

### 6.4. `Admin\CategoryController` (Manajemen Kategori oleh Admin)

Controller ini memungkinkan Admin mengelola kategori catatan di dalam organisasinya.

*   **Perintah Artisan:**
    ```bash
    php artisan make:controller Admin/CategoryController --resource
    ```
*   **Modifikasi File Controller (`app/Http/Controllers/Admin/CategoryController.php`):**
    ```php
    <?php
    namespace App\Http\Controllers\Admin;
    use App\Http\Controllers\Controller;
    use App\Models\Category;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Auth;

    class CategoryController extends Controller
    {
        public function index()
        {
            $organizationId = Auth::user()->organization_id;
            $categories = Category::where('organization_id', $organizationId)->paginate(10);
            return view('admin.categories.index', compact('categories'));
        }

        public function create()
        {
            return view('admin.categories.create');
        }

        public function store(Request $request)
        {
            $organizationId = Auth::user()->organization_id;

            $request->validate([
                'name' => 'required|string|max:255|unique:categories,name,NULL,id,organization_id,' . $organizationId,
            ]);

            Category::create([
                'name' => $request->name,
                'organization_id' => $organizationId,
            ]);

            return redirect()->route('admin.categories.index')->with('success', 'Category created successfully.');
        }

        public function show(string $id)
        {
            abort(404); // Tidak digunakan
        }

        public function edit(Category $category)
        {
            // Pastikan kategori adalah milik organisasi admin
            if ($category->organization_id !== Auth::user()->organization_id) {
                abort(403, 'Unauthorized action.');
            }
            return view('admin.categories.edit', compact('category'));
        }

        public function update(Request $request, Category $category)
        {
            // Pastikan kategori adalah milik organisasi admin
            if ($category->organization_id !== Auth::user()->organization_id) {
                abort(403, 'Unauthorized action.');
            }

            $organizationId = Auth::user()->organization_id;
            $request->validate([
                'name' => 'required|string|max:255|unique:categories,name,' . $category->id . ',id,organization_id,' . $organizationId,
            ]);

            $category->update([
                'name' => $request->name,
            ]);

            return redirect()->route('admin.categories.index')->with('success', 'Category updated successfully.');
        }

        public function destroy(Category $category)
        {
            // Pastikan kategori adalah milik organisasi admin
            if ($category->organization_id !== Auth::user()->organization_id) {
                abort(403, 'Unauthorized action.');
            }

            $category->delete();
            return redirect()->route('admin.categories.index')->with('success', 'Category deleted successfully.');
        }
    }
    ```

---

## 7. Pembaruan Tampilan (UI Updates)

Kita akan memperbarui tampilan yang ada dan membuat tampilan baru agar fungsionalitas RBAC dan multi-tenancy dapat digunakan melalui antarmuka pengguna.

### 7.1. Navigasi (`resources/views/layouts/navigation.blade.php`)

Ganti teks berbahasa Inggris dengan Bahasa Indonesia dan sesuaikan visibilitas tautan berdasarkan peran pengguna.

```html
<nav x-data="{ open: false }" class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800 dark:text-gray-200" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Beranda') }}
                    </x-nav-link>
                    @unless(Auth::user()->role === 'super_admin')
                        <x-nav-link :href="route('notes.index')" :active="request()->routeIs('notes.index')">
                            {{ __('Catatan') }}
                        </x-nav-link>
                    @endunless
                    @if(Auth::user()->role === 'super_admin')
                        <x-nav-link :href="route('superadmin.admins.index')" :active="request()->routeIs('superadmin.admins.index')">
                            {{ __('Kelola Admin') }}
                        </x-nav-link>
                    @endif
                    @if(Auth::user()->role === 'admin')
                        <x-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.index')">
                            {{ __('Kelola Pengguna') }}
                        </x-nav-link>
                        <x-nav-link :href="route('admin.categories.index')" :active="request()->routeIs('admin.categories.index')">
                            {{ __('Kelola Kategori') }}
                        </x-nav-link>
                    @endif
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profil') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Keluar') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-900 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-900 focus:text-gray-500 dark:focus:text-gray-400 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Beranda') }}
            </x-responsive-nav-link>
            @unless(Auth::user()->role === 'super_admin')
                <x-responsive-nav-link :href="route('notes.index')" :active="request()->routeIs('notes.index')">
                    {{ __('Catatan') }}
                </x-responsive-nav-link>
            @endunless
            @if(Auth::user()->role === 'super_admin')
                <x-responsive-nav-link :href="route('superadmin.admins.index')" :active="request()->routeIs('superadmin.admins.index')">
                    {{ __('Kelola Admin') }}
                </x-responsive-nav-link>
            @endif
            @if(Auth::user()->role === 'admin')
                <x-responsive-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.index')">
                    {{ __('Kelola Pengguna') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.categories.index')" :active="request()->routeIs('admin.categories.index')">
                    {{ __('Kelola Kategori') }}
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-600">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800 dark:text-gray-200">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profil') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Keluar') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
```

### 7.2. Tampilan Super Admin (`resources/views/superadmin/admins/*`)

#### `resources/views/superadmin/admins/index.blade.php`

Menampilkan daftar admin dengan tautan untuk mengedit dan menghapus.

```html
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Kelola Admin') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="flex justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Daftar Admin</h3>
                        <a href="{{ route('superadmin.admins.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            {{ __('Buat Admin') }}
                        </a>
                    </div>

                    <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                        <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="px-6 py-3">
                                        Nama
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        Email
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        Organisasi
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        <span class="sr-only">Aksi</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($admins as $admin)
                                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                            {{ $admin->name }}
                                        </td>
                                        <td class="px-6 py-4">
                                            {{ $admin->email }}
                                        </td>
                                        <td class="px-6 py-4">
                                            {{ $admin->organization->name ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <a href="{{ route('superadmin.admins.edit', $admin) }}" class="font-medium text-blue-600 dark:text-blue-500 hover:underline">Edit</a>
                                            <form action="{{ route('superadmin.admins.destroy', $admin) }}" method="POST" class="inline-block ml-4">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="font-medium text-red-600 dark:text-red-500 hover:underline">Hapus</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                        <td colspan="4" class="px-6 py-4 text-center">
                                            Admin tidak ditemukan.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $admins->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```

#### `resources/views/superadmin/admins/create.blade.php`

Formulir untuk membuat admin baru, dengan input teks untuk nama organisasi.

```html
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Buat Admin Baru') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Formulir Buat Admin</h3>

                    <form method="POST" action="{{ route('superadmin.admins.store') }}" class="mt-6 space-y-6">
                        @csrf

                        <!-- Name -->
                        <div>
                            <x-input-label for="name" :value="__('Nama')" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required autofocus autocomplete="name" />
                            <x-input-error class="mt-2" :messages="$errors->get('name')" />
                        </div>

                        <!-- Email Address -->
                        <div class="mt-4">
                            <x-input-label for="email" :value="__('Email')" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required autocomplete="username" />
                            <x-input-error class="mt-2" :messages="$errors->get('email')" />
                        </div>

                        <!-- Password -->
                        <div class="mt-4">
                            <x-input-label for="password" :value="__('Kata Sandi')" />
                            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                            <x-input-error class="mt-2" :messages="$errors->get('password')" />
                        </div>

                        <!-- Confirm Password -->
                        <div class="mt-4">
                            <x-input-label for="password_confirmation" :value="__('Konfirmasi Kata Sandi')" />
                            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                            <x-input-error class="mt-2" :messages="$errors->get('password_confirmation')" />
                        </div>

                        <!-- Organization Name -->
                        <div class="mt-4">
                            <x-input-label for="organization_name" :value="__('Nama Organisasi')" />
                            <x-text-input id="organization_name" name="organization_name" type="text" class="mt-1 block w-full" :value="old('organization_name')" required />
                            <x-input-error class="mt-2" :messages="$errors->get('organization_name')" />
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Buat') }}</x-primary-button>
                            <a href="{{ route('superadmin.admins.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150">
                                {{ __('Batal') }}
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```

#### `resources/views/superadmin/admins/edit.blade.php`

Formulir untuk mengedit admin, dengan dropdown organisasi yang sudah ada.

```html
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Edit Admin') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg="px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Formulir Edit Admin</h3>

                    <form method="POST" action="{{ route('superadmin.admins.update', $admin) }}" class="mt-6 space-y-6">
                        @csrf
                        @method('PATCH')

                        <!-- Name -->
                        <div>
                            <x-input-label for="name" :value="__('Nama')" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $admin->name)" required autofocus autocomplete="name" />
                            <x-input-error class="mt-2" :messages="$errors->get('name')" />
                        </div>

                        <!-- Email Address -->
                        <div class="mt-4">
                            <x-input-label for="email" :value="__('Email')" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $admin->email)" required autocomplete="username" />
                            <x-input-error class="mt-2" :messages="$errors->get('email')" />
                        </div>

                        <!-- Password -->
                        <div class="mt-4">
                            <x-input-label for="password" :value="__('Kata Sandi')" />
                            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                            <x-input-error class="mt-2" :messages="$errors->get('password')" />
                        </div>

                        <!-- Confirm Password -->
                        <div class="mt-4">
                            <x-input-label for="password_confirmation" :value="__('Konfirmasi Kata Sandi')" />
                            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                            <x-input-error class="mt-2" :messages="$errors->get('password_confirmation')" />
                        </div>

                        <!-- Organization -->
                        <div class="mt-4">
                            <x-input-label for="organization_id" :value="__('Organisasi')" />
                            <select id="organization_id" name="organization_id" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                <option value="">Pilih Organisasi</option>
                                @foreach($organizations as $organization)
                                    <option value="{{ $organization->id }}" @selected(old('organization_id', $admin->organization_id) == $organization->id)>{{ $organization->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('organization_id')" />
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Perbarui') }}</x-primary-button>
                            <a href="{{ route('superadmin.admins.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150">
                                {{ __('Batal') }}
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```

### 7.3. Tampilan Admin Pengguna (`resources/views/admin/users/*`)

#### `resources/views/admin/users/index.blade.php`

Menampilkan daftar pengguna di organisasi admin yang sedang login.

```html
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Kelola Pengguna') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="flex justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Daftar Pengguna di Organisasi</h3>
                        <a href="{{ route('admin.users.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            {{ __('Buat Pengguna') }}
                        </a>
                    </div>

                    <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                        <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="px-6 py-3">
                                        Nama
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        Email
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        <span class="sr-only">Aksi</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($users as $user)
                                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                            {{ $user->name }}
                                        </td>
                                        <td class="px-6 py-4">
                                            {{ $user->email }}
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <a href="{{ route('admin.users.edit', $user) }}" class="font-medium text-blue-600 dark:text-blue-500 hover:underline">Edit</a>
                                            <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="inline-block ml-4">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="font-medium text-red-600 dark:text-red-500 hover:underline">Hapus</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                        <td colspan="3" class="px-6 py-4 text-center">
                                            Tidak ada pengguna ditemukan untuk organisasi ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $users->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```

#### `resources/views/admin/users/create.blade.php`

Formulir untuk membuat pengguna baru.

```html
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Buat Pengguna Baru') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Formulir Buat Pengguna</h3>

                    <form method="POST" action="{{ route('admin.users.store') }}" class="mt-6 space-y-6">
                        @csrf

                        <!-- Name -->
                        <div>
                            <x-input-label for="name" :value="__('Nama')" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required autofocus autocomplete="name" />
                            <x-input-error class="mt-2" :messages="$errors->get('name')" />
                        </div>

                        <!-- Email Address -->
                        <div class="mt-4">
                            <x-input-label for="email" :value="__('Email')" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required autocomplete="username" />
                            <x-input-error class="mt-2" :messages="$errors->get('email')" />
                        </div>

                        <!-- Password -->
                        <div class="mt-4">
                            <x-input-label for="password" :value="__('Kata Sandi')" />
                            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                            <x-input-error class="mt-2" :messages="$errors->get('password')" />
                        </div>

                        <!-- Confirm Password -->
                        <div class="mt-4">
                            <x-input-label for="password_confirmation" :value="__('Konfirmasi Kata Sandi')" />
                            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                            <x-input-error class="mt-2" :messages="$errors->get('password_confirmation')" />
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Buat') }}</x-primary-button>
                            <a href="{{ route('admin.users.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150">
                                {{ __('Batal') }}
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```

#### `resources/views/admin/users/edit.blade.php`

Formulir untuk mengedit pengguna.

```html
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Edit Pengguna') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg="px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Formulir Edit Pengguna</h3>

                    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="mt-6 space-y-6">
                        @csrf
                        @method('PATCH')

                        <!-- Name -->
                        <div>
                            <x-input-label for="name" :value="__('Nama')" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
                            <x-input-error class="mt-2" :messages="$errors->get('name')" />
                        </div>

                        <!-- Email Address -->
                        <div class="mt-4">
                            <x-input-label for="email" :value="__('Email')" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
                            <x-input-error class="mt-2" :messages="$errors->get('email')" />
                        </div>

                        <!-- Password -->
                        <div class="mt-4">
                            <x-input-label for="password" :value="__('Kata Sandi')" />
                            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                            <x-input-error class="mt-2" :messages="$errors->get('password')" />
                        </div>

                        <!-- Confirm Password -->
                        <div class="mt-4">
                            <x-input-label for="password_confirmation" :value="__('Konfirmasi Kata Sandi')" />
                            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                            <x-input-error class="mt-2" :messages="$errors->get('password_confirmation')" />
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Perbarui') }}</x-primary-button>
                            <a href="{{ route('admin.users.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150">
                                {{ __('Batal') }}
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```

### 7.4. Tampilan Admin Kategori (`resources/views/admin/categories/*`)

#### `resources/views/admin/categories/index.blade.php`

Menampilkan daftar kategori di organisasi admin yang sedang login.

```html
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Kelola Kategori') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="flex justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Daftar Kategori</h3>
                        <a href="{{ route('admin.categories.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            {{ __('Buat Kategori') }}
                        </a>
                    </div>

                    <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                        <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="px-6 py-3">
                                        Nama
                                    </th>
                                    <th scope="col" class="px-6 py-3">
                                        <span class="sr-only">Aksi</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($categories as $category)
                                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                            {{ $category->name }}
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <a href="{{ route('admin.categories.edit', $category) }}" class="font-medium text-blue-600 dark:text-blue-500 hover:underline">Edit</a>
                                            <form action="{{ route('admin.categories.destroy', $category) }}" method="POST" class="inline-block ml-4">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="font-medium text-red-600 dark:text-red-500 hover:underline">Hapus</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                        <td colspan="2" class="px-6 py-4 text-center">
                                            Tidak ada kategori ditemukan untuk organisasi ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $categories->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```

#### `resources/views/admin/categories/create.blade.php`

Formulir untuk membuat kategori baru.

```html
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Buat Kategori Baru') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Formulir Buat Kategori</h3>

                    <form method="POST" action="{{ route('admin.categories.store') }}" class="mt-6 space-y-6">
                        @csrf

                        <!-- Name -->
                        <div>
                            <x-input-label for="name" :value="__('Nama')" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required autofocus />
                            <x-input-error class="mt-2" :messages="$errors->get('name')" />
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Buat') }}</x-primary-button>
                            <a href="{{ route('admin.categories.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150">
                                {{ __('Batal') }}
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```

#### `resources/views/admin/categories/edit.blade.php`

Formulir untuk mengedit kategori.

```html
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Edit Kategori') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Formulir Edit Kategori</h3>

                    <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="mt-6 space-y-6">
                        @csrf
                        @method('PATCH')

                        <!-- Name -->
                        <div>
                            <x-input-label for="name" :value="__('Nama')" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $category->name)" required autofocus />
                            <x-input-error class="mt-2" :messages="$errors->get('name')" />
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Perbarui') }}</x-primary-button>
                            <a href="{{ route('admin.categories.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150">
                                {{ __('Batal') }}
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```

### 7.5. Tampilan Catatan (`resources/views/notes/*`)

#### `resources/views/notes/create.blade.php`

Menambahkan dropdown pemilihan kategori saat membuat catatan.

```html
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Buat Catatan') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form method="POST" action="{{ route('notes.store') }}">
                        @csrf

                        <!-- Title -->
                        <div>
                            <x-input-label for="title" :value="__('Judul')" />
                            <x-text-input id="title" class="block mt-1 w-full" type="text" name="title" :value="old('title')" required autofocus />
                            <x-input-error :messages="$errors->get('title')" class="mt-2" />
                        </div>

                        <!-- Content -->
                        <div class="mt-4">
                            <x-input-label for="content" :value="__('Konten')" />
                            <textarea id="content" name="content" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">{{ old('content') }}</textarea>
                            <x-input-error :messages="$errors->get('content')" class="mt-2" />
                        </div>

                        <!-- Category -->
                        <div class="mt-4">
                            <x-input-label for="category_id" :value="__('Kategori')" />
                            <select id="category_id" name="category_id" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                @foreach($categories as $id => $name)
                                    <option value="{{ $id }}" @selected(old('category_id') == $id)>{{ $name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end mt-4">
                            <x-primary-button>
                                {{ __('Simpan') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```

#### `resources/views/notes/edit.blade.php`

Menambahkan dropdown pemilihan kategori saat mengedit catatan.

```html
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Edit Catatan') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form method="POST" action="{{ route('notes.update', $note) }}">
                        @csrf
                        @method('PATCH')

                        <!-- Title -->
                        <div>
                            <x-input-label for="title" :value="__('Judul')" />
                            <x-text-input id="title" class="block mt-1 w-full" type="text" name="title" :value="old('title', $note->title)" required autofocus />
                            <x-input-error :messages="$errors->get('title')" class="mt-2" />
                        </div>

                        <!-- Content -->
                        <div class="mt-4">
                            <x-input-label for="content" :value="__('Konten')" />
                            <textarea id="content" name="content" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">{{ old('content', $note->content) }}</textarea>
                            <x-input-error :messages="$errors->get('content')" class="mt-2" />
                        </div>

                        <!-- Category -->
                        <div class="mt-4">
                            <x-input-label for="category_id" :value="__('Kategori')" />
                            <select id="category_id" name="category_id" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                @foreach($categories as $id => $name)
                                    <option value="{{ $id }}" @selected(old('category_id', $note->category_id) == $id)>{{ $name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end mt-4">
                            <x-primary-button>
                                {{ __('Perbarui') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```