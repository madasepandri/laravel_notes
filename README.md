# Tutorial Membangun Aplikasi Laravel Multi-Tenant dengan RBAC dan Payment Gateway

## Daftar Isi
1. [Pengenalan](#pengenalan)
2. [Setup Environment](#setup-environment)
3. [Instalasi Laravel & Dependencies](#instalasi-laravel--dependencies)
4. [Database Design & Models](#database-design--models)
5. [Setup Authentication dengan Breeze](#setup-authentication-dengan-breeze)
6. [Implementasi Role-Based Access Control (RBAC)](#implementasi-role-based-access-control-rbac)
7. [Implementasi Multi-Tenancy](#implementasi-multi-tenancy)
8. [Integrasi Midtrans Payment Gateway](#integrasi-midtrans-payment-gateway)
9. [CRUD Notes dengan Multi-Tenancy](#crud-notes-dengan-multi-tenancy)
10. [Admin Panel: Manajemen User & Category](#admin-panel-manajemen-user--category)
11. [Super Admin Panel](#super-admin-panel)
12. [Testing & Deployment](#testing--deployment)
13. [Membangun REST API dengan Laravel Sanctum](#membangun-rest-api-dengan-laravel-sanctum)

---

## Pengenalan

### Apa yang Akan Kita Bangun?

Kita akan membangun **Aplikasi Manajemen Catatan (Notes)** berbasis multi-tenant dengan fitur:

- **Multi-Tenancy berbasis Organisasi**: Setiap organisasi memiliki data terpisah
- **Role-Based Access Control (RBAC)**: Super Admin, Admin, dan User
- **Payment Gateway**: Aktivasi organisasi dengan pembayaran Rp 100.000 via Midtrans
- **CRUD Notes**: Dengan kategori dan search
- **Admin Panel**: Manajemen users dan categories per organisasi
- **Super Admin Panel**: Manajemen admin organisasi

### Teknologi yang Digunakan

- **Laravel 12** - Framework PHP
- **PHP 8.2+** - Language
- **SQLite/MySQL** - Database
- **Laravel Breeze** - Authentication UI
- **Midtrans Snap** - Payment Gateway
- **Tailwind CSS** - Styling

### Konsep Multi-Tenancy

Multi-tenancy adalah arsitektur dimana satu aplikasi melayani banyak tenant (organisasi) dengan data yang terisolasi. Kita akan menggunakan pendekatan **Shared Database, Shared Schema** dengan kolom `organization_id` untuk memisahkan data.

### Konsep RBAC (Role-Based Access Control)

RBAC adalah sistem authorization dimana akses ditentukan berdasarkan role:
- **Super Admin**: Mengelola semua admin (tidak terikat organisasi)
- **Admin**: Mengelola users, categories, dan melihat semua notes dalam organisasinya
- **User**: Hanya dapat mengelola notes miliknya sendiri

---

## Setup Environment

### Requirements

Pastikan sistem Anda memiliki:

```bash
# Cek versi PHP (minimal 8.2)
php --version

# Cek Composer
composer --version

# Cek Node.js dan NPM (untuk build assets)
node --version
npm --version
```

### Install PHP Extensions

Laravel memerlukan beberapa PHP extensions:

```bash
# Ubuntu/Debian
sudo apt install php8.2-cli php8.2-mbstring php8.2-xml php8.2-curl php8.2-sqlite3

# macOS (via Homebrew)
brew install php@8.2

# Windows: Gunakan XAMPP atau Laragon
```

### Install Composer

```bash
# Download dan install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

---

## Instalasi Laravel & Dependencies

### Step 1: Buat Project Laravel Baru

```bash
# Buat project Laravel dengan nama laravel_notes
composer create-project laravel/laravel laravel_notes

# Masuk ke direktori project
cd laravel_notes
```

**Penjelasan**: Command ini akan membuat project Laravel baru dengan semua dependencies dasar.

### Step 2: Setup Database

Kita akan menggunakan SQLite untuk development (lebih mudah):

```bash
# Buat file database SQLite
touch database/database.sqlite
```

Edit file `.env`:

```env
DB_CONNECTION=sqlite
# Hapus atau comment baris berikut:
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=laravel
# DB_USERNAME=root
# DB_PASSWORD=
```

**Penjelasan**: SQLite adalah database berbasis file, cocok untuk development karena tidak perlu setup server database terpisah.

### Step 3: Install Laravel Breeze

Laravel Breeze menyediakan authentication UI yang siap pakai:

```bash
# Install Breeze via Composer
composer require laravel/breeze --dev

# Install Breeze dengan Blade stack
php artisan breeze:install blade

# Install dependencies NPM dan build assets
npm install
npm run build
```

**Penjelasan**:
- Breeze memberikan halaman login, register, forgot password, dll
- Stack Blade menggunakan template engine Laravel dengan Tailwind CSS
- `npm run build` mengcompile CSS dan JavaScript

### Step 4: Test Instalasi

```bash
# Jalankan migration
php artisan migrate

# Jalankan development server
php artisan serve
```

Buka browser dan akses `http://localhost:8000`. Anda akan melihat halaman welcome Laravel.

**Checkpoint**: Anda seharusnya bisa melihat tombol "Login" dan "Register" di kanan atas.

---

## Database Design & Models

### Step 1: Buat Migration untuk Organizations

```bash
php artisan make:migration create_organizations_table
```

Edit file `database/migrations/xxxx_create_organizations_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(false);
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
```

**Penjelasan**:
- `name`: Nama organisasi (unique)
- `is_active`: Status aktivasi (default false, akan true setelah bayar)
- `activated_at`: Timestamp ketika organisasi diaktifkan

### Step 2: Modifikasi Users Table

```bash
php artisan make:migration add_role_and_organization_to_users_table
```

Edit migration file:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('role')->default('user'); // user, admin, super_admin
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn(['organization_id', 'role']);
        });
    }
};
```

**Penjelasan**:
- `organization_id`: Foreign key ke table organizations
- `role`: Enum untuk role user (user, admin, super_admin)
- `onDelete('cascade')`: Jika organisasi dihapus, users juga dihapus

### Step 3: Buat Migration untuk Categories

```bash
php artisan make:migration create_categories_table
```

Edit migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            // Category name unique per organization
            $table->unique(['name', 'organization_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
```

**Penjelasan**:
- Category terikat ke organisasi
- Nama category harus unique per organisasi (bukan global)

### Step 4: Buat Migration untuk Notes

```bash
php artisan make:migration create_notes_table
```

Edit migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
```

**Penjelasan**:
- Note memiliki title dan content
- Terikat ke category, organization, dan user
- Cascade delete: hapus category/user/organization → notes ikut terhapus

### Step 5: Buat Migration untuk Payments

```bash
php artisan make:migration create_payments_table
```

Edit migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('order_id')->unique();
            $table->decimal('gross_amount', 15, 2);
            $table->string('status')->default('pending'); // pending, paid, failed, expired, canceled
            $table->string('snap_token')->nullable();
            $table->string('snap_redirect_url')->nullable();
            $table->string('midtrans_transaction_status')->nullable();
            $table->string('midtrans_fraud_status')->nullable();
            $table->string('midtrans_status_code')->nullable();
            $table->string('midtrans_signature_key')->nullable();
            $table->json('raw_notification')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
```

**Penjelasan**:
- `order_id`: ID unik untuk transaksi (format: ORG-{org_id}-{timestamp})
- `snap_token`: Token dari Midtrans untuk popup payment
- `status`: Status internal kita (pending, paid, failed, expired, canceled)
- `midtrans_*`: Data dari Midtrans untuk tracking dan debugging
- `raw_notification`: Simpan notifikasi JSON dari Midtrans

### Step 6: Jalankan Migration

```bash
php artisan migrate
```

**Checkpoint**: Cek database Anda. Harus ada tables: users, organizations, categories, notes, payments.

### Step 7: Buat Models

#### Organization Model

```bash
php artisan make:model Organization
```

Edit `app/Models/Organization.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'is_active',
        'activated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'activated_at' => 'datetime',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
```

**Penjelasan**:
- `$fillable`: Kolom yang boleh di-mass assign
- `$casts`: Cast tipe data (boolean untuk is_active, datetime untuk activated_at)
- Relations: Organization punya banyak users, categories, dan payments

#### Category Model

```bash
php artisan make:model Category
```

Edit `app/Models/Category.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
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

#### Note Model

```bash
php artisan make:model Note
```

Edit `app/Models/Note.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    protected $fillable = [
        'title',
        'content',
        'category_id',
        'organization_id',
        'user_id',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

#### Payment Model

```bash
php artisan make:model Payment
```

Edit `app/Models/Payment.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'organization_id',
        'user_id',
        'order_id',
        'gross_amount',
        'status',
        'snap_token',
        'snap_redirect_url',
        'midtrans_transaction_status',
        'midtrans_fraud_status',
        'midtrans_status_code',
        'midtrans_signature_key',
        'raw_notification',
        'paid_at',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'raw_notification' => 'array',
        'paid_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

#### Update User Model

Edit `app/Models/User.php`, tambahkan:

```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Tambahkan ke $fillable
protected $fillable = [
    'name',
    'email',
    'password',
    'organization_id',
    'role',
];

// Tambahkan relations
public function organization(): BelongsTo
{
    return $this->belongsTo(Organization::class);
}

public function notes(): HasMany
{
    return $this->hasMany(Note::class);
}

public function payments(): HasMany
{
    return $this->hasMany(Payment::class);
}
```

---

## Setup Authentication dengan Breeze

Breeze sudah terinstall, tapi kita perlu modifikasi registrasi untuk support organisasi.

### Step 1: Modifikasi Halaman Register

Edit `resources/views/auth/register.blade.php`, tambahkan field organization name setelah field name:

```blade
<!-- Organization Name -->
<div class="mt-4">
    <x-input-label for="organization_name" :value="__('Organization Name')" />
    <x-input-text id="organization_name" class="block mt-1 w-full" type="text"
                  name="organization_name" :value="old('organization_name')" required />
    <x-input-error :messages="$errors->get('organization_name')" class="mt-2" />
</div>
```

**Penjelasan**: User yang register akan otomatis jadi admin organisasi, jadi mereka perlu input nama organisasi.

### Step 2: Modifikasi RegisteredUserController

Edit `app/Http/Controllers/Auth/RegisteredUserController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use App\Services\MidtransSnapService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function __construct(
        private MidtransSnapService $midtransSnapService
    ) {}

    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'organization_name' => ['required', 'string', 'max:255'],
        ]);

        DB::beginTransaction();

        try {
            // Cari atau buat organisasi
            $organization = Organization::firstOrCreate(
                ['name' => $request->organization_name],
                ['is_active' => false]
            );

            // Buat user sebagai admin organisasi
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'organization_id' => $organization->id,
                'role' => 'admin',
            ]);

            event(new Registered($user));

            Auth::login($user);

            // Jika organisasi belum aktif, buat payment
            if (!$organization->is_active) {
                // Cek apakah sudah ada payment pending
                $existingPayment = Payment::where('organization_id', $organization->id)
                    ->where('status', 'pending')
                    ->first();

                if (!$existingPayment) {
                    $orderId = 'ORG-' . $organization->id . '-' . time();
                    $grossAmount = config('billing.organization_registration_fee');

                    // Buat transaksi Midtrans
                    $snapData = $this->midtransSnapService->createSnapTransaction(
                        $orderId,
                        $grossAmount,
                        [
                            'first_name' => $user->name,
                            'email' => $user->email,
                        ],
                        [
                            ['id' => 'org-activation', 'price' => $grossAmount, 'quantity' => 1, 'name' => 'Organization Activation Fee']
                        ]
                    );

                    // Simpan payment record
                    Payment::create([
                        'organization_id' => $organization->id,
                        'user_id' => $user->id,
                        'order_id' => $orderId,
                        'gross_amount' => $grossAmount,
                        'status' => 'pending',
                        'snap_token' => $snapData['token'],
                        'snap_redirect_url' => $snapData['redirect_url'],
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('billing.show');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Registration failed: ' . $e->getMessage()]);
        }
    }
}
```

**Penjelasan**:
- `firstOrCreate`: Cari organisasi by name, jika tidak ada buat baru
- User dibuat dengan role 'admin'
- Jika organisasi belum aktif, buat payment record dan snap token
- Redirect ke halaman billing untuk pembayaran

### Step 3: Buat Config Files

#### Buat config/midtrans.php

```bash
php artisan make:config midtrans
```

Edit `config/midtrans.php`:

```php
<?php

return [
    'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
    'server_key' => env('MIDTRANS_SERVER_KEY'),
    'client_key' => env('MIDTRANS_CLIENT_KEY'),
    'verify_ssl' => env('MIDTRANS_VERIFY_SSL', true),
];
```

#### Buat config/billing.php

```php
<?php

return [
    'organization_registration_fee' => env('ORG_REG_FEE', 100000),
    'currency' => env('BILLING_CURRENCY', 'IDR'),
];
```

### Step 4: Update .env

Tambahkan ke `.env`:

```env
# Midtrans Configuration
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_SERVER_KEY=your_sandbox_server_key
MIDTRANS_CLIENT_KEY=your_sandbox_client_key
MIDTRANS_VERIFY_SSL=true

# Billing Configuration
ORG_REG_FEE=100000
BILLING_CURRENCY=IDR
```

**Penjelasan**: Kita akan buat MidtransSnapService nanti di bagian Payment Gateway.

---

## Implementasi Role-Based Access Control (RBAC)

### Step 1: Buat RoleMiddleware

```bash
php artisan make:middleware RoleMiddleware
```

Edit `app/Http/Middleware/RoleMiddleware.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }

        if (!in_array($request->user()->role, $roles)) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
```

**Penjelasan**:
- Middleware ini menerima parameter roles (bisa multiple)
- Cek apakah user memiliki salah satu role yang diizinkan
- Jika tidak, return 403 Forbidden

### Step 2: Register Middleware

Edit `bootstrap/app.php`, tambahkan di dalam `withMiddleware`:

```php
use App\Http\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
```

**Penjelasan**: Alias `role` memudahkan kita menggunakan middleware di routes.

### Step 3: Contoh Penggunaan di Routes

Edit `routes/web.php`:

```php
// Super Admin routes
Route::middleware(['auth', 'role:super_admin'])->prefix('superadmin')->name('superadmin.')->group(function () {
    Route::resource('admins', App\Http\Controllers\SuperAdmin\AdminController::class);
});

// Admin routes
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('users', App\Http\Controllers\Admin\UserController::class);
    Route::resource('categories', App\Http\Controllers\Admin\CategoryController::class);
});

// Admin dan User routes
Route::middleware(['auth', 'role:admin,user'])->group(function () {
    Route::resource('notes', App\Http\Controllers\NoteController::class);
});
```

**Penjelasan**:
- `role:super_admin`: Hanya super admin
- `role:admin`: Hanya admin
- `role:admin,user`: Admin atau user

---

## Implementasi Multi-Tenancy

Multi-tenancy memastikan data organisasi terisolasi. Kita akan buat middleware untuk enforce organization activation.

### Step 1: Buat EnsureOrganizationIsActive Middleware

```bash
php artisan make:middleware EnsureOrganizationIsActive
```

Edit `app/Http/Middleware/EnsureOrganizationIsActive.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Super admin tidak perlu organisasi aktif
        if ($user->role === 'super_admin') {
            return $next($request);
        }

        // User harus punya organisasi
        if (!$user->organization_id || !$user->organization) {
            abort(403, 'No organization assigned.');
        }

        // Jika organisasi belum aktif
        if (!$user->organization->is_active) {
            // Admin diarahkan ke billing
            if ($user->role === 'admin') {
                return redirect()->route('billing.show');
            }

            // User biasa diblokir
            abort(403, 'Your organization is not active. Please contact your administrator.');
        }

        return $next($request);
    }
}
```

**Penjelasan**:
- Super admin bypass check ini
- Admin yang organisasinya belum aktif → redirect ke billing
- User biasa yang organisasinya belum aktif → 403 error
- Middleware ini protect semua fitur aplikasi

### Step 2: Register Middleware

Edit `bootstrap/app.php`, tambahkan di alias:

```php
use App\Http\Middleware\EnsureOrganizationIsActive;

$middleware->alias([
    'role' => RoleMiddleware::class,
    'org.active' => EnsureOrganizationIsActive::class,
]);
```

### Step 3: Apply Middleware di Routes

Edit `routes/web.php`:

```php
// Admin routes - butuh org aktif
Route::middleware(['auth', 'role:admin', 'org.active'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('users', App\Http\Controllers\Admin\UserController::class);
    Route::resource('categories', App\Http\Controllers\Admin\CategoryController::class);
});

// Notes - butuh org aktif
Route::middleware(['auth', 'role:admin,user', 'org.active'])->group(function () {
    Route::resource('notes', App\Http\Controllers\NoteController::class);
});
```

### Step 4: Implementasi Data Scoping di Controller

Contoh di NoteController (akan kita buat lengkap nanti):

```php
// Admin melihat semua notes dalam organisasinya
if ($user->role === 'admin') {
    $notes = Note::where('organization_id', $user->organization_id)->get();
}

// User hanya melihat notes miliknya
if ($user->role === 'user') {
    $notes = Note::where('user_id', $user->id)->get();
}
```

**Penjelasan**: Ini adalah inti dari multi-tenancy - setiap query harus di-scope berdasarkan organization_id atau user_id.

---

## Integrasi Midtrans Payment Gateway

### Step 1: Daftar di Midtrans

1. Kunjungi https://dashboard.sandbox.midtrans.com/
2. Register akun baru
3. Dapatkan Server Key dan Client Key dari Settings → Access Keys

### Step 2: Buat MidtransSnapService

```bash
php artisan make:service MidtransSnapService
```

Jika command tidak ada, buat manual folder `app/Services/` dan file `MidtransSnapService.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MidtransSnapService
{
    private string $serverKey;
    private string $clientKey;
    private bool $isProduction;
    private string $snapApiUrl;
    private string $coreApiUrl;

    public function __construct()
    {
        $this->serverKey = config('midtrans.server_key');
        $this->clientKey = config('midtrans.client_key');
        $this->isProduction = config('midtrans.is_production');

        $this->snapApiUrl = $this->isProduction
            ? 'https://app.midtrans.com/snap/v1'
            : 'https://app.sandbox.midtrans.com/snap/v1';

        $this->coreApiUrl = $this->isProduction
            ? 'https://api.midtrans.com/v2'
            : 'https://api.sandbox.midtrans.com/v2';
    }

    /**
     * Buat transaksi Snap dan dapatkan token
     */
    public function createSnapTransaction(
        string $orderId,
        float $grossAmount,
        array $customerDetails,
        array $itemDetails
    ): array {
        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $grossAmount,
            ],
            'customer_details' => $customerDetails,
            'item_details' => $itemDetails,
        ];

        $response = Http::withBasicAuth($this->serverKey, '')
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->post($this->snapApiUrl . '/transactions', $params);

        if ($response->failed()) {
            Log::error('Midtrans Snap API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to create Midtrans transaction: ' . $response->body());
        }

        $data = $response->json();

        return [
            'token' => $data['token'],
            'redirect_url' => $data['redirect_url'],
        ];
    }

    /**
     * Ambil status transaksi dari Midtrans
     */
    public function fetchTransactionStatus(string $orderId): array
    {
        $response = Http::withBasicAuth($this->serverKey, '')
            ->get($this->coreApiUrl . '/' . $orderId . '/status');

        if ($response->failed()) {
            Log::error('Midtrans Status API Error', [
                'order_id' => $orderId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to fetch transaction status');
        }

        return $response->json();
    }

    /**
     * Validasi signature dari notifikasi Midtrans
     */
    public function validateSignature(
        string $orderId,
        string $statusCode,
        string $grossAmount,
        string $signatureKey
    ): bool {
        $expectedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $this->serverKey);
        return hash_equals($expectedSignature, $signatureKey);
    }

    public function getClientKey(): string
    {
        return $this->clientKey;
    }
}
```

**Penjelasan**:
- **createSnapTransaction**: Request ke Midtrans untuk membuat transaksi dan dapat token
- **fetchTransactionStatus**: Cek status transaksi (untuk manual sync)
- **validateSignature**: Validasi notifikasi dari Midtrans menggunakan SHA512
- Menggunakan HTTP Basic Auth dengan server_key sebagai username

### Step 3: Buat BillingController

```bash
php artisan make:controller BillingController
```

Edit `app/Http/Controllers/BillingController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\MidtransSnapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BillingController extends Controller
{
    public function __construct(
        private MidtransSnapService $midtransSnapService
    ) {}

    /**
     * Tampilkan halaman billing
     */
    public function show()
    {
        $user = Auth::user();

        if ($user->role !== 'admin') {
            abort(403, 'Only admin can access billing page.');
        }

        $organization = $user->organization;

        // Ambil payment terakhir (pending atau paid)
        $payment = Payment::where('organization_id', $organization->id)
            ->whereIn('status', ['pending', 'paid'])
            ->latest()
            ->first();

        return view('payment.billing', [
            'organization' => $organization,
            'payment' => $payment,
            'clientKey' => $this->midtransSnapService->getClientKey(),
        ]);
    }

    /**
     * Buat tagihan aktivasi baru
     */
    public function create(Request $request)
    {
        $user = Auth::user();
        $organization = $user->organization;

        if ($organization->is_active) {
            return redirect()->route('billing.show')
                ->with('error', 'Organization is already active.');
        }

        DB::beginTransaction();

        try {
            // Hapus payment pending yang lama
            Payment::where('organization_id', $organization->id)
                ->where('status', 'pending')
                ->delete();

            // Buat payment baru
            $orderId = 'ORG-' . $organization->id . '-' . time();
            $grossAmount = config('billing.organization_registration_fee');

            $snapData = $this->midtransSnapService->createSnapTransaction(
                $orderId,
                $grossAmount,
                [
                    'first_name' => $user->name,
                    'email' => $user->email,
                ],
                [
                    ['id' => 'org-activation', 'price' => $grossAmount, 'quantity' => 1, 'name' => 'Organization Activation Fee']
                ]
            );

            Payment::create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'order_id' => $orderId,
                'gross_amount' => $grossAmount,
                'status' => 'pending',
                'snap_token' => $snapData['token'],
                'snap_redirect_url' => $snapData['redirect_url'],
            ]);

            DB::commit();

            return redirect()->route('billing.show')
                ->with('success', 'Payment created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to create payment: ' . $e->getMessage());
        }
    }

    /**
     * Sinkronisasi status pembayaran dari Midtrans
     */
    public function sync(Request $request)
    {
        $user = Auth::user();
        $organization = $user->organization;

        $payment = Payment::where('organization_id', $organization->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if (!$payment) {
            return back()->with('error', 'No pending payment found.');
        }

        try {
            $status = $this->midtransSnapService->fetchTransactionStatus($payment->order_id);

            // Validasi signature
            if (!$this->midtransSnapService->validateSignature(
                $status['order_id'],
                $status['status_code'],
                $status['gross_amount'],
                $status['signature_key']
            )) {
                return back()->with('error', 'Invalid signature from Midtrans.');
            }

            // Update payment
            $payment->update([
                'midtrans_transaction_status' => $status['transaction_status'],
                'midtrans_fraud_status' => $status['fraud_status'] ?? null,
                'midtrans_status_code' => $status['status_code'],
                'midtrans_signature_key' => $status['signature_key'],
            ]);

            // Aktifkan organisasi jika sudah bayar
            if (in_array($status['transaction_status'], ['capture', 'settlement'])) {
                $payment->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);

                $organization->update([
                    'is_active' => true,
                    'activated_at' => now(),
                ]);

                return redirect()->route('dashboard')
                    ->with('success', 'Payment successful! Organization is now active.');
            }

            return back()->with('info', 'Payment status: ' . $status['transaction_status']);

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to sync payment: ' . $e->getMessage());
        }
    }
}
```

**Penjelasan**:
- **show()**: Tampilkan halaman billing dengan data payment
- **create()**: Buat payment baru (jika user mau bayar lagi setelah expired)
- **sync()**: Manual sync status dari Midtrans (tombol "Perbarui Status")

### Step 4: Buat MidtransWebhookController

```bash
php artisan make:controller MidtransWebhookController
```

Edit `app/Http/Controllers/MidtransWebhookController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\MidtransSnapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MidtransWebhookController extends Controller
{
    public function __construct(
        private MidtransSnapService $midtransSnapService
    ) {}

    /**
     * Handle notifikasi dari Midtrans
     */
    public function handle(Request $request)
    {
        try {
            $notification = $request->all();

            Log::info('Midtrans Webhook Received', $notification);

            $orderId = $notification['order_id'];
            $statusCode = $notification['status_code'];
            $grossAmount = $notification['gross_amount'];
            $signatureKey = $notification['signature_key'];

            // Validasi signature
            if (!$this->midtransSnapService->validateSignature(
                $orderId,
                $statusCode,
                $grossAmount,
                $signatureKey
            )) {
                Log::warning('Invalid Midtrans signature', $notification);
                return response()->json(['message' => 'Invalid signature'], 403);
            }

            // Cari payment
            $payment = Payment::where('order_id', $orderId)->first();

            if (!$payment) {
                Log::warning('Payment not found for order: ' . $orderId);
                return response()->json(['message' => 'Payment not found'], 404);
            }

            DB::beginTransaction();

            // Update payment dengan data dari Midtrans
            $payment->update([
                'midtrans_transaction_status' => $notification['transaction_status'],
                'midtrans_fraud_status' => $notification['fraud_status'] ?? null,
                'midtrans_status_code' => $statusCode,
                'midtrans_signature_key' => $signatureKey,
                'raw_notification' => $notification,
            ]);

            $transactionStatus = $notification['transaction_status'];
            $fraudStatus = $notification['fraud_status'] ?? null;

            // Proses berdasarkan status
            if ($transactionStatus == 'capture') {
                if ($fraudStatus == 'accept') {
                    $this->activateOrganization($payment);
                }
            } elseif ($transactionStatus == 'settlement') {
                $this->activateOrganization($payment);
            } elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire'])) {
                $payment->update(['status' => 'failed']);
            } elseif ($transactionStatus == 'pending') {
                $payment->update(['status' => 'pending']);
            }

            DB::commit();

            return response()->json(['message' => 'OK']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Midtrans Webhook Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }

    /**
     * Aktifkan organisasi setelah pembayaran sukses
     */
    private function activateOrganization(Payment $payment): void
    {
        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $payment->organization->update([
            'is_active' => true,
            'activated_at' => now(),
        ]);

        Log::info('Organization activated', [
            'organization_id' => $payment->organization_id,
            'order_id' => $payment->order_id,
        ]);
    }
}
```

**Penjelasan**:
- Webhook adalah endpoint yang dipanggil Midtrans saat status pembayaran berubah
- Validasi signature penting untuk keamanan
- Status `settlement` atau `capture` dengan fraud_status `accept` → aktivasi organisasi
- Simpan `raw_notification` untuk debugging

### Step 5: Buat View Billing

Buat file `resources/views/payment/billing.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Organization Activation') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if (session('success'))
                        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                            {{ session('error') }}
                        </div>
                    @endif

                    @if (session('info'))
                        <div class="mb-4 bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded">
                            {{ session('info') }}
                        </div>
                    @endif

                    <h3 class="text-2xl font-bold mb-4">{{ $organization->name }}</h3>

                    @if ($organization->is_active)
                        <div class="bg-green-50 border border-green-200 rounded-lg p-6">
                            <h4 class="text-lg font-semibold text-green-800 mb-2">Organization Active</h4>
                            <p class="text-green-700">
                                Your organization was activated on {{ $organization->activated_at->format('d M Y H:i') }}
                            </p>
                            <a href="{{ route('dashboard') }}" class="mt-4 inline-block bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                                Go to Dashboard
                            </a>
                        </div>
                    @else
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
                            <h4 class="text-lg font-semibold text-yellow-800 mb-2">Organization Not Active</h4>
                            <p class="text-yellow-700 mb-4">
                                Please complete the payment of <strong>Rp {{ number_format(config('billing.organization_registration_fee'), 0, ',', '.') }}</strong> to activate your organization.
                            </p>
                        </div>

                        @if ($payment && $payment->status === 'pending')
                            <div class="bg-white border border-gray-200 rounded-lg p-6">
                                <h4 class="text-lg font-semibold mb-4">Payment Information</h4>
                                <dl class="grid grid-cols-1 gap-4">
                                    <div>
                                        <dt class="font-medium text-gray-700">Order ID:</dt>
                                        <dd class="text-gray-900">{{ $payment->order_id }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-medium text-gray-700">Amount:</dt>
                                        <dd class="text-gray-900">Rp {{ number_format($payment->gross_amount, 0, ',', '.') }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-medium text-gray-700">Status:</dt>
                                        <dd class="text-gray-900">{{ ucfirst($payment->status) }}</dd>
                                    </div>
                                </dl>

                                <div class="mt-6 flex gap-3">
                                    <button id="pay-button" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                        Pay Now
                                    </button>

                                    <form method="POST" action="{{ route('billing.sync') }}" class="inline">
                                        @csrf
                                        <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                            Refresh Status
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @else
                            <form method="POST" action="{{ route('billing.create') }}">
                                @csrf
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                    Create Payment
                                </button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($payment && $payment->status === 'pending')
        <script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="{{ $clientKey }}"></script>
        <script>
            document.getElementById('pay-button').addEventListener('click', function () {
                snap.pay('{{ $payment->snap_token }}', {
                    onSuccess: function(result) {
                        console.log('Payment success:', result);
                        window.location.href = '{{ route('billing.show') }}';
                    },
                    onPending: function(result) {
                        console.log('Payment pending:', result);
                        alert('Payment pending. Please complete your payment.');
                    },
                    onError: function(result) {
                        console.log('Payment error:', result);
                        alert('Payment failed. Please try again.');
                    },
                    onClose: function() {
                        console.log('Payment popup closed');
                    }
                });
            });
        </script>
    @endif
</x-app-layout>
```

**Penjelasan**:
- Menampilkan status organisasi (aktif/tidak aktif)
- Jika belum aktif, tampilkan informasi payment dan tombol "Pay Now"
- Load Midtrans Snap.js dan trigger popup saat tombol diklik
- Callback `onSuccess` redirect ke billing page untuk refresh status

### Step 6: Setup Routes

Edit `routes/web.php`, tambahkan:

```php
use App\Http\Controllers\BillingController;
use App\Http\Controllers\MidtransWebhookController;

// Billing routes (untuk admin)
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/billing', [BillingController::class, 'show'])->name('billing.show');
    Route::post('/billing', [BillingController::class, 'create'])->name('billing.create');
    Route::post('/billing/sync', [BillingController::class, 'sync'])->name('billing.sync');
});

// Midtrans webhook (tanpa CSRF karena dipanggil dari luar)
Route::post('/midtrans/notification', [MidtransWebhookController::class, 'handle'])
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
```

### Step 7: Exclude Webhook dari CSRF Protection

Edit `bootstrap/app.php`, tambahkan di `withMiddleware`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'role' => RoleMiddleware::class,
        'org.active' => EnsureOrganizationIsActive::class,
    ]);

    // Exclude webhook dari CSRF
    $middleware->validateCsrfTokens(except: [
        'midtrans/notification',
    ]);
})
```

### Step 8: Update Dashboard

Edit `resources/views/dashboard.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-2xl font-bold mb-4">Welcome, {{ Auth::user()->name }}!</h3>

                    @if (Auth::user()->role === 'super_admin')
                        <p class="mb-4">You are logged in as Super Admin.</p>
                        <a href="{{ route('superadmin.admins.index') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Manage Admins
                        </a>
                    @elseif (Auth::user()->role === 'admin')
                        <p class="mb-4">Organization: <strong>{{ Auth::user()->organization->name }}</strong></p>

                        @if (Auth::user()->organization->is_active)
                            <p class="text-green-600 mb-4">Organization Status: Active</p>
                            <div class="flex gap-3">
                                <a href="{{ route('notes.index') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                    Manage Notes
                                </a>
                                <a href="{{ route('admin.users.index') }}" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                                    Manage Users
                                </a>
                                <a href="{{ route('admin.categories.index') }}" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded">
                                    Manage Categories
                                </a>
                            </div>
                        @else
                            <p class="text-red-600 mb-4">Organization Status: Inactive</p>
                            <a href="{{ route('billing.show') }}" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                                Activate Organization
                            </a>
                        @endif
                    @else
                        <p class="mb-4">Organization: <strong>{{ Auth::user()->organization->name }}</strong></p>

                        @if (Auth::user()->organization->is_active)
                            <a href="{{ route('notes.index') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                My Notes
                            </a>
                        @else
                            <p class="text-red-600">Your organization is not active yet. Please contact your administrator.</p>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```

### Step 9: Test Payment Flow

1. Jalankan server: `php artisan serve`
2. Register sebagai admin organisasi baru
3. Anda akan diarahkan ke halaman billing
4. Klik "Pay Now"
5. Di sandbox Midtrans, gunakan test card:
   - Card Number: `4811 1111 1111 1114`
   - CVV: `123`
   - Expiry: `01/25`
6. Complete pembayaran
7. Klik "Refresh Status" atau tunggu webhook

**Catatan**: Di sandbox, Anda bisa trigger webhook manual dari Midtrans Dashboard → Transactions → View Detail → Resend Webhook.

---

## CRUD Notes dengan Multi-Tenancy

### Step 1: Buat NoteController

```bash
php artisan make:controller NoteController --resource
```

Edit `app/Http/Controllers/NoteController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NoteController extends Controller
{
    /**
     * Display a listing of notes
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $search = $request->input('search');

        $query = Note::with(['category', 'user']);

        // Admin melihat semua notes dalam organisasinya
        if ($user->role === 'admin') {
            $query->where('organization_id', $user->organization_id);
        } else {
            // User hanya melihat notes miliknya
            $query->where('user_id', $user->id);
        }

        // Search
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                  ->orWhere('content', 'like', '%' . $search . '%');
            });
        }

        $notes = $query->latest()->paginate(10);

        return view('notes.index', compact('notes', 'search'));
    }

    /**
     * Show the form for creating a new note
     */
    public function create()
    {
        $user = Auth::user();
        $categories = Category::where('organization_id', $user->organization_id)->get();

        return view('notes.create', compact('categories'));
    }

    /**
     * Store a newly created note
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category_id' => 'required|exists:categories,id',
        ]);

        // Validasi category milik organisasi yang sama
        $category = Category::findOrFail($request->category_id);
        if ($category->organization_id !== $user->organization_id) {
            abort(403, 'Category not found in your organization.');
        }

        Note::create([
            'title' => $request->title,
            'content' => $request->content,
            'category_id' => $request->category_id,
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
        ]);

        return redirect()->route('notes.index')
            ->with('success', 'Note created successfully.');
    }

    /**
     * Display the specified note
     */
    public function show(Note $note)
    {
        $user = Auth::user();

        // Cek akses
        if ($user->role === 'admin') {
            if ($note->organization_id !== $user->organization_id) {
                abort(403);
            }
        } else {
            if ($note->user_id !== $user->id) {
                abort(403);
            }
        }

        return view('notes.show', compact('note'));
    }

    /**
     * Show the form for editing the specified note
     */
    public function edit(Note $note)
    {
        $user = Auth::user();

        // Cek akses
        if ($user->role === 'admin') {
            if ($note->organization_id !== $user->organization_id) {
                abort(403);
            }
        } else {
            if ($note->user_id !== $user->id) {
                abort(403);
            }
        }

        $categories = Category::where('organization_id', $user->organization_id)->get();

        return view('notes.edit', compact('note', 'categories'));
    }

    /**
     * Update the specified note
     */
    public function update(Request $request, Note $note)
    {
        $user = Auth::user();

        // Cek akses
        if ($user->role === 'admin') {
            if ($note->organization_id !== $user->organization_id) {
                abort(403);
            }
        } else {
            if ($note->user_id !== $user->id) {
                abort(403);
            }
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category_id' => 'required|exists:categories,id',
        ]);

        // Validasi category
        $category = Category::findOrFail($request->category_id);
        if ($category->organization_id !== $user->organization_id) {
            abort(403, 'Category not found in your organization.');
        }

        $note->update([
            'title' => $request->title,
            'content' => $request->content,
            'category_id' => $request->category_id,
        ]);

        return redirect()->route('notes.index')
            ->with('success', 'Note updated successfully.');
    }

    /**
     * Remove the specified note
     */
    public function destroy(Note $note)
    {
        $user = Auth::user();

        // Cek akses
        if ($user->role === 'admin') {
            if ($note->organization_id !== $user->organization_id) {
                abort(403);
            }
        } else {
            if ($note->user_id !== $user->id) {
                abort(403);
            }
        }

        $note->delete();

        return redirect()->route('notes.index')
            ->with('success', 'Note deleted successfully.');
    }
}
```

**Penjelasan**:
- **Data Scoping**: Admin lihat semua notes dalam org, User hanya lihat miliknya
- **Authorization**: Setiap method cek apakah user berhak akses note
- **Validation**: Pastikan category yang dipilih ada dalam organisasi yang sama

### Step 2: Buat Views untuk Notes

#### Index View

Buat `resources/views/notes/index.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Notes') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-2xl font-bold">My Notes</h3>
                        <a href="{{ route('notes.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Create Note
                        </a>
                    </div>

                    <!-- Search Form -->
                    <form method="GET" action="{{ route('notes.index') }}" class="mb-6">
                        <div class="flex gap-2">
                            <input type="text" name="search" value="{{ $search }}"
                                   placeholder="Search notes..."
                                   class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                            <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                Search
                            </button>
                            @if ($search)
                                <a href="{{ route('notes.index') }}" class="bg-gray-400 hover:bg-gray-500 text-white font-bold py-2 px-4 rounded">
                                    Clear
                                </a>
                            @endif
                        </div>
                    </form>

                    <!-- Notes Table -->
                    @if ($notes->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Author</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach ($notes as $note)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <a href="{{ route('notes.show', $note) }}" class="text-blue-600 hover:underline">
                                                    {{ $note->title }}
                                                </a>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">{{ $note->category->name }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">{{ $note->user->name }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">{{ $note->created_at->format('d M Y') }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <a href="{{ route('notes.edit', $note) }}" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</a>
                                                <form method="POST" action="{{ route('notes.destroy', $note) }}" class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-900"
                                                            onclick="return confirm('Are you sure?')">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            {{ $notes->links() }}
                        </div>
                    @else
                        <p class="text-gray-500">No notes found.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```

#### Create View

Buat `resources/views/notes/create.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Note') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('notes.store') }}">
                        @csrf

                        <!-- Title -->
                        <div class="mb-4">
                            <label for="title" class="block font-medium text-sm text-gray-700">Title</label>
                            <input id="title" type="text" name="title" value="{{ old('title') }}" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                            @error('title')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Category -->
                        <div class="mb-4">
                            <label for="category_id" class="block font-medium text-sm text-gray-700">Category</label>
                            <select id="category_id" name="category_id" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <option value="">Select Category</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('category_id')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Content -->
                        <div class="mb-4">
                            <label for="content" class="block font-medium text-sm text-gray-700">Content</label>
                            <textarea id="content" name="content" rows="10" required
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">{{ old('content') }}</textarea>
                            @error('content')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center gap-4">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                Create Note
                            </button>
                            <a href="{{ route('notes.index') }}" class="text-gray-600 hover:text-gray-900">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```

#### Edit & Show Views

Buat `resources/views/notes/edit.blade.php` (mirip create, tapi dengan `@method('PUT')` dan `route('notes.update', $note)`).

Buat `resources/views/notes/show.blade.php` untuk menampilkan detail note.

### Step 3: Setup Routes

Di `routes/web.php`, pastikan sudah ada:

```php
Route::middleware(['auth', 'role:admin,user', 'org.active'])->group(function () {
    Route::resource('notes', NoteController::class);
});
```

---

## Admin Panel: Manajemen User & Category

### Step 1: Buat Admin\UserController

```bash
php artisan make:controller Admin/UserController --resource
```

Edit `app/Http/Controllers/Admin/UserController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class UserController extends Controller
{
    /**
     * Display a listing of users in the organization
     */
    public function index()
    {
        $organizationId = Auth::user()->organization_id;

        $users = User::where('organization_id', $organizationId)
            ->where('role', 'user')
            ->latest()
            ->paginate(10);

        return view('admin.users.index', compact('users'));
    }

    /**
     * Show the form for creating a new user
     */
    public function create()
    {
        return view('admin.users.create');
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'organization_id' => Auth::user()->organization_id,
            'role' => 'user',
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    /**
     * Show the form for editing the specified user
     */
    public function edit(User $user)
    {
        // Validasi user dalam organisasi yang sama
        if ($user->organization_id !== Auth::user()->organization_id) {
            abort(403);
        }

        return view('admin.users.edit', compact('user'));
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, User $user)
    {
        // Validasi user dalam organisasi yang sama
        if ($user->organization_id !== Auth::user()->organization_id) {
            abort(403);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
        ]);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified user
     */
    public function destroy(User $user)
    {
        // Validasi user dalam organisasi yang sama
        if ($user->organization_id !== Auth::user()->organization_id) {
            abort(403);
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }
}
```

**Penjelasan**:
- Admin hanya bisa CRUD users dengan role 'user' dalam organisasinya
- Validasi ketat: pastikan user yang diedit/dihapus ada dalam organisasi yang sama

### Step 2: Buat Admin\CategoryController

```bash
php artisan make:controller Admin/CategoryController --resource
```

Edit `app/Http/Controllers/Admin/CategoryController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories
     */
    public function index()
    {
        $organizationId = Auth::user()->organization_id;

        $categories = Category::where('organization_id', $organizationId)
            ->withCount('notes')
            ->latest()
            ->paginate(10);

        return view('admin.categories.index', compact('categories'));
    }

    /**
     * Show the form for creating a new category
     */
    public function create()
    {
        return view('admin.categories.create');
    }

    /**
     * Store a newly created category
     */
    public function store(Request $request)
    {
        $organizationId = Auth::user()->organization_id;

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')->where('organization_id', $organizationId),
            ],
        ]);

        Category::create([
            'name' => $request->name,
            'organization_id' => $organizationId,
        ]);

        return redirect()->route('admin.categories.index')
            ->with('success', 'Category created successfully.');
    }

    /**
     * Show the form for editing the specified category
     */
    public function edit(Category $category)
    {
        if ($category->organization_id !== Auth::user()->organization_id) {
            abort(403);
        }

        return view('admin.categories.edit', compact('category'));
    }

    /**
     * Update the specified category
     */
    public function update(Request $request, Category $category)
    {
        if ($category->organization_id !== Auth::user()->organization_id) {
            abort(403);
        }

        $organizationId = Auth::user()->organization_id;

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')
                    ->where('organization_id', $organizationId)
                    ->ignore($category->id),
            ],
        ]);

        $category->update([
            'name' => $request->name,
        ]);

        return redirect()->route('admin.categories.index')
            ->with('success', 'Category updated successfully.');
    }

    /**
     * Remove the specified category
     */
    public function destroy(Category $category)
    {
        if ($category->organization_id !== Auth::user()->organization_id) {
            abort(403);
        }

        $category->delete();

        return redirect()->route('admin.categories.index')
            ->with('success', 'Category deleted successfully.');
    }
}
```

**Penjelasan**:
- Validasi unique category name per organization menggunakan `Rule::unique()`
- `withCount('notes')` untuk menampilkan jumlah notes per category

### Step 3: Buat Views untuk Admin Panel

Views untuk admin panel mirip dengan notes views. Buat folder:
- `resources/views/admin/users/` dengan index.blade.php, create.blade.php, edit.blade.php
- `resources/views/admin/categories/` dengan index.blade.php, create.blade.php, edit.blade.php

### Step 4: Setup Routes

Di `routes/web.php`:

```php
Route::middleware(['auth', 'role:admin', 'org.active'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('users', App\Http\Controllers\Admin\UserController::class);
    Route::resource('categories', App\Http\Controllers\Admin\CategoryController::class);
});
```

---

## Super Admin Panel

### Step 1: Buat SuperAdmin\AdminController

```bash
php artisan make:controller SuperAdmin/AdminController --resource
```

Edit `app/Http/Controllers/SuperAdmin/AdminController.php`:

```php
<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class AdminController extends Controller
{
    /**
     * Display a listing of admins
     */
    public function index()
    {
        $admins = User::with('organization')
            ->where('role', 'admin')
            ->latest()
            ->paginate(10);

        return view('superadmin.admins.index', compact('admins'));
    }

    /**
     * Show the form for creating a new admin
     */
    public function create()
    {
        $organizations = Organization::all();
        return view('superadmin.admins.create', compact('organizations'));
    }

    /**
     * Store a newly created admin
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'organization_name' => ['required_without:organization_id', 'string', 'max:255'],
        ]);

        // Jika pilih organisasi existing
        if ($request->filled('organization_id')) {
            $organizationId = $request->organization_id;
        } else {
            // Buat organisasi baru
            $organization = Organization::create([
                'name' => $request->organization_name,
                'is_active' => false,
            ]);
            $organizationId = $organization->id;
        }

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'organization_id' => $organizationId,
            'role' => 'admin',
        ]);

        return redirect()->route('superadmin.admins.index')
            ->with('success', 'Admin created successfully.');
    }

    /**
     * Show the form for editing the specified admin
     */
    public function edit(User $admin)
    {
        $organizations = Organization::all();
        return view('superadmin.admins.edit', compact('admin', 'organizations'));
    }

    /**
     * Update the specified admin
     */
    public function update(Request $request, User $admin)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,' . $admin->id],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'organization_id' => ['required', 'exists:organizations,id'],
        ]);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'organization_id' => $request->organization_id,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $admin->update($data);

        return redirect()->route('superadmin.admins.index')
            ->with('success', 'Admin updated successfully.');
    }

    /**
     * Remove the specified admin
     */
    public function destroy(User $admin)
    {
        $admin->delete();

        return redirect()->route('superadmin.admins.index')
            ->with('success', 'Admin deleted successfully.');
    }
}
```

**Penjelasan**:
- Super admin bisa buat admin untuk organisasi existing atau buat organisasi baru
- Super admin bisa update/delete semua admin

### Step 2: Buat Views untuk Super Admin

Buat folder `resources/views/superadmin/admins/` dengan CRUD views.

### Step 3: Setup Routes

Di `routes/web.php`:

```php
Route::middleware(['auth', 'role:super_admin'])->prefix('superadmin')->name('superadmin.')->group(function () {
    Route::resource('admins', App\Http\Controllers\SuperAdmin\AdminController::class);
});
```

### Step 4: Buat Super Admin via Seeder

Buat seeder untuk super admin pertama:

```bash
php artisan make:seeder SuperAdminSeeder
```

Edit `database/seeders/SuperAdminSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'organization_id' => null,
        ]);
    }
}
```

Jalankan seeder:

```bash
php artisan db:seed --class=SuperAdminSeeder
```

---

## Testing & Deployment

### Step 1: Testing Manual

1. **Test Super Admin**:
   - Login sebagai superadmin@example.com
   - Buat admin baru untuk organisasi baru
   - Logout

2. **Test Admin**:
   - Login sebagai admin yang baru dibuat
   - Anda akan diarahkan ke halaman billing
   - Complete pembayaran (gunakan test card Midtrans)
   - Setelah bayar, buat users dan categories
   - Buat notes

3. **Test User**:
   - Login sebagai user yang dibuat oleh admin
   - Buat notes
   - Pastikan user tidak bisa lihat notes user lain
   - User tidak bisa akses admin panel

### Step 2: Testing Webhook

Test webhook Midtrans:

1. Buat ngrok tunnel (untuk local testing):
```bash
ngrok http 8000
```

2. Copy URL ngrok (contoh: https://abc123.ngrok.io)

3. Set di Midtrans Dashboard:
   - Settings → Configuration
   - Payment Notification URL: `https://abc123.ngrok.io/midtrans/notification`

4. Lakukan pembayaran test

5. Cek log di `storage/logs/laravel.log` untuk debug

### Step 3: Automated Testing (Optional)

Buat feature tests untuk flow utama:

```bash
php artisan make:test OrganizationActivationTest
php artisan make:test MultiTenancyTest
php artisan make:test RBACTest
```

### Step 4: Deployment ke Production

#### Persiapan Server

```bash
# Update server
sudo apt update && sudo apt upgrade -y

# Install PHP, Nginx, Composer
sudo apt install php8.2-fpm php8.2-cli php8.2-mbstring php8.2-xml php8.2-curl php8.2-mysql nginx -y

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

#### Deploy Aplikasi

```bash
# Clone repository
git clone https://github.com/your-repo/laravel_notes.git
cd laravel_notes

# Install dependencies
composer install --no-dev --optimize-autoloader
npm install && npm run build

# Setup environment
cp .env.example .env
php artisan key:generate

# Setup database (gunakan MySQL untuk production)
php artisan migrate --force

# Create super admin
php artisan db:seed --class=SuperAdminSeeder

# Set permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

#### Configure .env untuk Production

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_notes
DB_USERNAME=root
DB_PASSWORD=your_secure_password

# Midtrans Production
MIDTRANS_IS_PRODUCTION=true
MIDTRANS_SERVER_KEY=your_production_server_key
MIDTRANS_CLIENT_KEY=your_production_client_key
```

#### Setup Nginx

Edit `/etc/nginx/sites-available/laravel_notes`:

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/laravel_notes/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable site dan restart Nginx:

```bash
sudo ln -s /etc/nginx/sites-available/laravel_notes /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

#### Setup SSL dengan Let's Encrypt

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d yourdomain.com
```

#### Setup Queue Worker (Optional)

Jika menggunakan queue untuk background jobs:

```bash
# Install supervisor
sudo apt install supervisor

# Create config
sudo nano /etc/supervisor/conf.d/laravel-worker.conf
```

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/laravel_notes/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/laravel_notes/storage/logs/worker.log
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

#### Setup Scheduler

Tambahkan ke crontab:

```bash
sudo crontab -e -u www-data
```

Tambahkan:

```
* * * * * cd /var/www/laravel_notes && php artisan schedule:run >> /dev/null 2>&1
```

---

## Membangun REST API dengan Laravel Sanctum

Setelah aplikasi web selesai, sekarang kita akan membangun REST API untuk aplikasi mobile atau integrasi dengan sistem lain. Kita akan menggunakan **Laravel Sanctum** untuk token-based authentication.

### Apa itu Laravel Sanctum?

Laravel Sanctum adalah package official Laravel untuk API authentication yang simple dan lightweight. Sanctum menyediakan:
- **Token-based authentication** untuk SPA dan mobile apps
- **Cookie-based authentication** untuk SPA same-domain
- API token management yang mudah

### Step 1: Install Laravel Sanctum

```bash
# Install Sanctum
composer require laravel/sanctum
```

**Penjelasan**: Sanctum sudah included di Laravel 11+, tapi untuk versi sebelumnya perlu install manual.

### Step 2: Publish Sanctum Config & Migration

```bash
# Publish config (optional)
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# Jalankan migration untuk tabel personal_access_tokens
php artisan migrate
```

**Penjelasan**: Migration membuat tabel `personal_access_tokens` untuk menyimpan API tokens.

### Step 3: Tambahkan HasApiTokens Trait ke User Model

Edit `app/Models/User.php`, tambahkan trait `HasApiTokens`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;  // Import trait

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;  // Tambahkan HasApiTokens

    // ... rest of the code
}
```

**Penjelasan**: Trait `HasApiTokens` memberikan method untuk create, delete, dan manage API tokens.

### Step 4: Configure API Routes

Laravel sudah menyediakan `routes/api.php` untuk API routes. Edit `routes/api.php`:

```php
<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NoteController;
use Illuminate\Support\Facades\Route;

// API Versioning - v1
Route::prefix('v1')->group(function () {

    // Public endpoints (no auth required)
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // Protected endpoints (auth required)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', function (Request $request) {
            return $request->user();
        });

        // Notes CRUD
        Route::apiResource('notes', NoteController::class);
    });
});
```

**Penjelasan**:
- **API Versioning**: Menggunakan prefix `/v1` untuk versioning API
- **Public Routes**: Register dan login tidak butuh authentication
- **Protected Routes**: Menggunakan middleware `auth:sanctum`
- **apiResource**: Generate routes untuk CRUD (index, store, show, update, destroy) tanpa create & edit

### Step 5: Buat API Auth Controller

```bash
# Buat folder Api dan AuthController
mkdir -p app/Http/Controllers/Api
php artisan make:controller Api/AuthController
```

Edit `app/Http/Controllers/Api/AuthController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register user baru
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    /**
     * Login user
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * Logout user (hapus current token)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }
}
```

**Penjelasan**:
- **register()**: Buat user baru, generate token, return token
- **login()**: Validasi credentials, generate token, return token
- **logout()**: Hapus current access token dari database
- **createToken('mobile-app')**: Buat token dengan nama "mobile-app"
- **plainTextToken**: Get token dalam format plain text (hanya available saat create)

### Step 6: Buat API Resource untuk Note

API Resource adalah cara elegant untuk transform model ke JSON response.

```bash
php artisan make:resource NoteResource
```

Edit `app/Http/Resources/NoteResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'category_id' => $this->category_id,
            'organization_id' => $this->organization_id,
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                ];
            }),
            'organization' => $this->whenLoaded('organization', function () {
                return [
                    'id' => $this->organization->id,
                    'name' => $this->organization->name,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

**Penjelasan**:
- **toArray()**: Define struktur JSON response
- **whenLoaded()**: Hanya include relasi jika sudah di-eager load
- Memberikan kontrol penuh atas data yang di-expose ke API

### Step 7: Buat Note Policy untuk Authorization

Policy adalah cara Laravel untuk organize authorization logic.

```bash
php artisan make:policy NotePolicy --model=Note
```

Edit `app/Policies/NotePolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Note;
use App\Models\User;

class NotePolicy
{
    /**
     * Determine if the user can view the note.
     */
    public function view(User $user, Note $note): bool
    {
        return $note->user_id === $user->id;
    }

    /**
     * Determine if the user can create notes.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can update the note.
     */
    public function update(User $user, Note $note): bool
    {
        return $note->user_id === $user->id;
    }

    /**
     * Determine if the user can delete the note.
     */
    public function delete(User $user, Note $note): bool
    {
        return $note->user_id === $user->id;
    }
}
```

**Penjelasan**:
- Policy methods return `true` jika user authorized, `false` jika tidak
- User hanya bisa view/update/delete notes miliknya sendiri
- Semua user bisa create notes

### Step 8: Register Policy

Edit `app/Providers/AppServiceProvider.php`:

```php
<?php

namespace App\Providers;

use App\Models\Note;
use App\Policies\NotePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Note::class, NotePolicy::class);
    }
}
```

**Penjelasan**: Register policy agar Laravel tahu policy mana yang digunakan untuk model Note.

### Step 9: Buat API Note Controller

```bash
php artisan make:controller Api/NoteController --api
```

Edit `app/Http/Controllers/Api/NoteController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NoteResource;
use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NoteController extends Controller
{
    /**
     * Display a listing of notes
     */
    public function index(Request $request)
    {
        $notes = Note::where('user_id', $request->user()->id)
            ->with(['category', 'organization'])
            ->latest()
            ->paginate(15);

        return NoteResource::collection($notes);
    }

    /**
     * Store a newly created note
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'organization_id' => 'nullable|exists:organizations,id',
        ]);

        $note = Note::create([
            'title' => $request->title,
            'content' => $request->content,
            'category_id' => $request->category_id,
            'organization_id' => $request->organization_id,
            'user_id' => $request->user()->id,
        ]);

        return new NoteResource($note);
    }

    /**
     * Display the specified note
     */
    public function show(Request $request, Note $note)
    {
        Gate::authorize('view', $note);

        return new NoteResource($note->load(['category', 'organization']));
    }

    /**
     * Update the specified note
     */
    public function update(Request $request, Note $note)
    {
        Gate::authorize('update', $note);

        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'content' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'organization_id' => 'nullable|exists:organizations,id',
        ]);

        $note->update($request->only([
            'title',
            'content',
            'category_id',
            'organization_id',
        ]));

        return new NoteResource($note);
    }

    /**
     * Remove the specified note
     */
    public function destroy(Request $request, Note $note)
    {
        Gate::authorize('delete', $note);

        $note->delete();

        return response()->json([
            'message' => 'Note deleted successfully',
        ]);
    }
}
```

**Penjelasan**:
- **index()**: List notes milik user dengan pagination (15 items/page)
- **store()**: Buat note baru untuk user yang login
- **show()**: Tampilkan detail note (dengan authorization check)
- **update()**: Update note (dengan authorization check)
- **destroy()**: Hapus note (dengan authorization check)
- **Gate::authorize()**: Check permission menggunakan Policy
- **NoteResource**: Transform response ke format JSON yang konsisten
- **with()**: Eager loading untuk relasi category dan organization

### Step 10: Testing API dengan Postman/Thunder Client

#### 1. Register User

```http
POST http://localhost:8000/api/v1/register
Content-Type: application/json

{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

**Response:**
```json
{
    "message": "User registered successfully",
    "user": {
        "name": "John Doe",
        "email": "john@example.com",
        "updated_at": "2026-01-12T10:30:00.000000Z",
        "created_at": "2026-01-12T10:30:00.000000Z",
        "id": 1
    },
    "token": "1|aBcDeFgHiJkLmNoPqRsTuVwXyZ1234567890"
}
```

**Simpan token** untuk digunakan di request berikutnya!

#### 2. Login

```http
POST http://localhost:8000/api/v1/login
Content-Type: application/json

{
    "email": "john@example.com",
    "password": "password123"
}
```

**Response:**
```json
{
    "message": "Login successful",
    "user": {...},
    "token": "2|aBcDeFgHiJkLmNoPqRsTuVwXyZ1234567890"
}
```

#### 3. Get Current User

```http
GET http://localhost:8000/api/v1/user
Authorization: Bearer {token}
```

**Response:**
```json
{
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "organization_id": null,
    "role": null,
    "email_verified_at": null,
    "created_at": "2026-01-12T10:30:00.000000Z",
    "updated_at": "2026-01-12T10:30:00.000000Z"
}
```

#### 4. Create Note

```http
POST http://localhost:8000/api/v1/notes
Authorization: Bearer {token}
Content-Type: application/json

{
    "title": "My First Note",
    "content": "This is the content of my note",
    "category_id": 1
}
```

**Response:**
```json
{
    "data": {
        "id": 1,
        "title": "My First Note",
        "content": "This is the content of my note",
        "category_id": 1,
        "organization_id": null,
        "category": null,
        "organization": null,
        "created_at": "2026-01-12T10:35:00.000000Z",
        "updated_at": "2026-01-12T10:35:00.000000Z"
    }
}
```

#### 5. List Notes

```http
GET http://localhost:8000/api/v1/notes
Authorization: Bearer {token}
```

**Response:**
```json
{
    "data": [
        {
            "id": 1,
            "title": "My First Note",
            "content": "This is the content",
            ...
        }
    ],
    "links": {
        "first": "http://localhost:8000/api/v1/notes?page=1",
        "last": "http://localhost:8000/api/v1/notes?page=1",
        "prev": null,
        "next": null
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 1,
        "per_page": 15,
        "to": 1,
        "total": 1
    }
}
```

#### 6. Show Note

```http
GET http://localhost:8000/api/v1/notes/1
Authorization: Bearer {token}
```

#### 7. Update Note

```http
PUT http://localhost:8000/api/v1/notes/1
Authorization: Bearer {token}
Content-Type: application/json

{
    "title": "Updated Title",
    "content": "Updated content"
}
```

#### 8. Delete Note

```http
DELETE http://localhost:8000/api/v1/notes/1
Authorization: Bearer {token}
```

**Response:**
```json
{
    "message": "Note deleted successfully"
}
```

#### 9. Logout

```http
POST http://localhost:8000/api/v1/logout
Authorization: Bearer {token}
```

**Response:**
```json
{
    "message": "Logged out successfully"
}
```

### Step 11: Error Handling untuk API

Laravel secara otomatis mengembalikan error dalam format JSON untuk API requests. Berikut contoh error responses:

#### Validation Error (422)

```json
{
    "message": "The title field is required. (and 1 more error)",
    "errors": {
        "title": ["The title field is required."],
        "content": ["The content field must be a string."]
    }
}
```

#### Unauthorized (401)

```json
{
    "message": "Unauthenticated."
}
```

#### Forbidden (403)

```json
{
    "message": "This action is unauthorized."
}
```

#### Not Found (404)

```json
{
    "message": "No query results for model [App\\Models\\Note] 999"
}
```

### Step 12: Custom Error Handler (Optional)

Untuk error response yang lebih user-friendly, edit `bootstrap/app.php`:

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // ... middleware config
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                    return response()->json([
                        'message' => 'Resource not found',
                    ], 404);
                }

                if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                    return response()->json([
                        'message' => 'Endpoint not found',
                    ], 404);
                }

                if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    return response()->json([
                        'message' => 'Unauthenticated',
                    ], 401);
                }

                if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                    return response()->json([
                        'message' => 'Unauthorized',
                    ], 403);
                }
            }

            return null; // Let default handler handle it
        });
    })->create();
```

**Penjelasan**: Custom error handler untuk API routes memberikan response JSON yang konsisten.

### Step 13: Rate Limiting untuk API

Protect API dari abuse dengan rate limiting. Edit `bootstrap/app.php`:

```php
use Illuminate\Http\Request;

->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'role' => RoleMiddleware::class,
        'org.active' => EnsureOrganizationIsActive::class,
    ]);

    // API Rate Limiting
    $middleware->throttleApi();

    // Custom rate limit untuk API tertentu
    $middleware->throttle([
        'api' => \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by(fn (Request $request) => $request->user()?->id ?: $request->ip()),
    ]);
})
```

**Penjelasan**:
- Default: 60 requests per minute per user/IP
- Bisa dikustomisasi per route group
- Response 429 Too Many Requests jika limit exceeded

### Step 14: API Documentation dengan Scribe (Optional)

Install Scribe untuk auto-generate API documentation:

```bash
composer require --dev knuckleswtf/scribe
```

Generate dokumentasi:

```bash
# Publish config
php artisan vendor:publish --tag=scribe-config

# Generate documentation
php artisan scribe:generate
```

Edit `config/scribe.php` untuk customize:

```php
'title' => 'Laravel Notes API Documentation',
'description' => 'API documentation for Laravel Notes application',
'base_url' => env('APP_URL', 'http://localhost:8000'),
'routes' => [
    [
        'match' => [
            'prefixes' => ['api/*'],
        ],
    ],
],
```

Akses dokumentasi di `http://localhost:8000/docs`.

### Step 15: Testing API dengan PHPUnit

Buat feature test untuk API:

```bash
php artisan make:test Api/AuthTest
php artisan make:test Api/NoteTest
```

Edit `tests/Feature/Api/AuthTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email'],
                'token',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user',
                'token',
            ]);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully']);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }
}
```

Edit `tests/Feature/Api/NoteTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteTest extends TestCase
{
    use RefreshDatabase;

    private function authenticatedUser(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        return [$user, $token];
    }

    public function test_user_can_list_their_notes(): void
    {
        [$user, $token] = $this->authenticatedUser();

        Note::factory()->count(3)->create(['user_id' => $user->id]);
        Note::factory()->count(2)->create(); // Notes dari user lain

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/notes');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_create_note(): void
    {
        [$user, $token] = $this->authenticatedUser();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/notes', [
                'title' => 'Test Note',
                'content' => 'Test Content',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'title', 'content'],
            ]);

        $this->assertDatabaseHas('notes', [
            'title' => 'Test Note',
            'user_id' => $user->id,
        ]);
    }

    public function test_user_cannot_view_others_note(): void
    {
        [$user, $token] = $this->authenticatedUser();
        $otherNote = Note::factory()->create(); // Note dari user lain

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/notes/' . $otherNote->id);

        $response->assertStatus(403);
    }

    public function test_user_can_update_their_note(): void
    {
        [$user, $token] = $this->authenticatedUser();
        $note = Note::factory()->create(['user_id' => $user->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/notes/' . $note->id, [
                'title' => 'Updated Title',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => ['title' => 'Updated Title'],
            ]);
    }

    public function test_user_can_delete_their_note(): void
    {
        [$user, $token] = $this->authenticatedUser();
        $note = Note::factory()->create(['user_id' => $user->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/v1/notes/' . $note->id);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('notes', ['id' => $note->id]);
    }
}
```

Jalankan tests:

```bash
# Jalankan semua tests
php artisan test

# Jalankan specific test file
php artisan test --filter=AuthTest

# Jalankan dengan coverage
php artisan test --coverage
```

### Step 16: API Best Practices

#### 1. Versioning

Selalu gunakan versioning untuk API:
- ✅ `/api/v1/notes`
- ❌ `/api/notes`

Ini memudahkan untuk membuat breaking changes di versi baru tanpa merusak client yang existing.

#### 2. Consistent Response Format

Gunakan API Resources untuk konsistensi:
```json
{
    "data": {},           // Single resource
    "data": [],           // Collection
    "meta": {},           // Metadata (pagination, etc)
    "links": {}           // Pagination links
}
```

#### 3. HTTP Status Codes

Gunakan status code yang sesuai:
- `200 OK` - Success (GET, PUT, PATCH)
- `201 Created` - Resource created (POST)
- `204 No Content` - Success but no content (DELETE)
- `400 Bad Request` - Invalid request
- `401 Unauthorized` - Not authenticated
- `403 Forbidden` - Not authorized
- `404 Not Found` - Resource not found
- `422 Unprocessable Entity` - Validation error
- `429 Too Many Requests` - Rate limit exceeded
- `500 Internal Server Error` - Server error

#### 4. Pagination

Selalu paginate collections:
```php
$notes = Note::paginate(15); // Default 15 items
return NoteResource::collection($notes);
```

#### 5. Eager Loading

Prevent N+1 queries dengan eager loading:
```php
$notes = Note::with(['category', 'organization'])->get();
```

#### 6. Filtering & Sorting

Tambahkan query parameters untuk filtering:
```php
public function index(Request $request)
{
    $query = Note::where('user_id', $request->user()->id);

    if ($request->has('category_id')) {
        $query->where('category_id', $request->category_id);
    }

    if ($request->has('search')) {
        $query->where('title', 'like', '%' . $request->search . '%');
    }

    if ($request->has('sort')) {
        $query->orderBy($request->sort, $request->get('direction', 'asc'));
    }

    return NoteResource::collection($query->paginate(15));
}
```

#### 7. Security

- ✅ Selalu validate input
- ✅ Gunakan authorization (Policy/Gate)
- ✅ Enable rate limiting
- ✅ Hash passwords
- ✅ Use HTTPS di production
- ✅ Validate signature untuk webhooks
- ❌ Jangan expose sensitive data di response
- ❌ Jangan trust user input

### Step 17: Consume API dari Mobile App

Contoh menggunakan API dari Flutter:

```dart
import 'package:http/http.dart' as http;
import 'dart:convert';

class ApiService {
  static const String baseUrl = 'http://localhost:8000/api/v1';
  static String? token;

  // Register
  static Future<Map<String, dynamic>> register(
    String name,
    String email,
    String password,
  ) async {
    final response = await http.post(
      Uri.parse('$baseUrl/register'),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({
        'name': name,
        'email': email,
        'password': password,
        'password_confirmation': password,
      }),
    );

    if (response.statusCode == 201) {
      final data = jsonDecode(response.body);
      token = data['token'];
      return data;
    } else {
      throw Exception('Failed to register');
    }
  }

  // Login
  static Future<Map<String, dynamic>> login(
    String email,
    String password,
  ) async {
    final response = await http.post(
      Uri.parse('$baseUrl/login'),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({
        'email': email,
        'password': password,
      }),
    );

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      token = data['token'];
      return data;
    } else {
      throw Exception('Failed to login');
    }
  }

  // Get Notes
  static Future<List<dynamic>> getNotes() async {
    final response = await http.get(
      Uri.parse('$baseUrl/notes'),
      headers: {
        'Authorization': 'Bearer $token',
        'Accept': 'application/json',
      },
    );

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      return data['data'];
    } else {
      throw Exception('Failed to load notes');
    }
  }

  // Create Note
  static Future<Map<String, dynamic>> createNote(
    String title,
    String content,
  ) async {
    final response = await http.post(
      Uri.parse('$baseUrl/notes'),
      headers: {
        'Authorization': 'Bearer $token',
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: jsonEncode({
        'title': title,
        'content': content,
      }),
    );

    if (response.statusCode == 201) {
      return jsonDecode(response.body)['data'];
    } else {
      throw Exception('Failed to create note');
    }
  }
}
```

### Ringkasan API Endpoints

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/v1/register` | ❌ | Register user baru |
| POST | `/api/v1/login` | ❌ | Login user |
| POST | `/api/v1/logout` | ✅ | Logout user |
| GET | `/api/v1/user` | ✅ | Get current user |
| GET | `/api/v1/notes` | ✅ | List notes (paginated) |
| POST | `/api/v1/notes` | ✅ | Create note |
| GET | `/api/v1/notes/{id}` | ✅ | Show note detail |
| PUT/PATCH | `/api/v1/notes/{id}` | ✅ | Update note |
| DELETE | `/api/v1/notes/{id}` | ✅ | Delete note |

### Kesimpulan API

Sekarang aplikasi Laravel Notes sudah memiliki REST API yang lengkap dengan:

✅ **Token Authentication** dengan Laravel Sanctum
✅ **API Resources** untuk transform response
✅ **Authorization** dengan Policy
✅ **Validation** untuk semua input
✅ **Pagination** untuk list endpoints
✅ **Error Handling** dengan response JSON konsisten
✅ **Rate Limiting** untuk protect dari abuse
✅ **API Versioning** untuk maintainability
✅ **Testing** dengan PHPUnit

API ini siap digunakan untuk:
- Mobile apps (Flutter, React Native, Swift, Kotlin)
- Frontend SPA (Vue.js, React, Angular)
- Integrasi dengan sistem lain
- Third-party integrations

---

## Kesimpulan

Selamat! Anda telah berhasil membangun aplikasi Laravel lengkap dengan:

✅ **Multi-Tenancy**: Data terisolasi per organisasi
✅ **RBAC**: Super Admin, Admin, dan User dengan permission berbeda
✅ **Payment Gateway**: Integrasi Midtrans untuk aktivasi organisasi
✅ **CRUD Operations**: Notes, Users, Categories dengan authorization
✅ **REST API**: API lengkap dengan Laravel Sanctum untuk mobile apps
✅ **Security**: Data scoping, middleware, validation, signature verification

### Fitur-fitur Utama:

1. **Super Admin** dapat mengelola semua admin organisasi
2. **Admin** dapat aktivasi organisasi dengan pembayaran, mengelola users, categories, dan melihat semua notes
3. **User** dapat membuat dan mengelola notes miliknya sendiri
4. **Multi-tenant** memastikan data organisasi terisolasi
5. **Payment** terintegrasi dengan Midtrans Snap
6. **REST API** dengan token authentication untuk mobile apps dan integrasi sistem lain

### Best Practices yang Diterapkan:

- ✅ Migration yang terstruktur dengan foreign key dan cascade delete
- ✅ Model relationships yang jelas
- ✅ Middleware untuk authorization (web & API)
- ✅ Data scoping untuk multi-tenancy
- ✅ Service layer untuk business logic (MidtransSnapService)
- ✅ API Resources untuk consistent response format
- ✅ Policy untuk authorization logic
- ✅ Validation yang ketat (web & API)
- ✅ Error handling dan logging
- ✅ Rate limiting untuk API protection
- ✅ API versioning untuk maintainability
- ✅ Security: signature validation, CSRF protection, hash comparison, token authentication

### Teknologi yang Digunakan:

**Backend:**
- Laravel 12
- PHP 8.2+
- SQLite/MySQL
- Laravel Breeze (Web Auth)
- Laravel Sanctum (API Auth)
- Midtrans Snap

**Frontend:**
- Blade Templates
- Tailwind CSS
- Alpine.js (via Breeze)

**Testing:**
- PHPUnit
- Feature Tests
- API Tests

### Architecture Patterns:

- **MVC Pattern**: Model-View-Controller
- **Repository Pattern**: (dapat diterapkan untuk data access layer)
- **Service Pattern**: Business logic di service classes (MidtransSnapService)
- **Policy Pattern**: Authorization logic
- **Resource Pattern**: API response transformation
- **Middleware Pattern**: Request filtering dan authorization

### API Endpoints Summary:

| Category | Endpoints | Count |
|----------|-----------|-------|
| Authentication | register, login, logout, user | 4 |
| Notes | index, store, show, update, destroy | 5 |
| **Total** | | **9** |

### Next Steps (Opsional):

1. **Email Notifications**: Kirim email saat organisasi aktif dengan Laravel Notifications
2. **Advanced Permissions**: Granular permissions dengan Spatie Permission package
3. **File Upload**: Upload attachment untuk notes dengan Laravel Storage
4. **Activity Log**: Track semua aktivitas user dengan Spatie Activity Log
5. **Better UI**: Gunakan Vue.js atau React dengan Inertia.js untuk SPA
6. **Real-time Features**: WebSocket dengan Laravel Reverb untuk real-time notifications
7. **Advanced API Features**:
   - GraphQL dengan Lighthouse
   - Webhook subscriptions
   - API key management
8. **Performance Optimization**:
   - Redis caching
   - Query optimization
   - Eager loading
   - Database indexing
9. **Monitoring & Analytics**:
   - Laravel Telescope untuk debugging
   - Laravel Horizon untuk queue monitoring
   - Sentry untuk error tracking
10. **DevOps**:
    - Docker containerization
    - CI/CD dengan GitHub Actions
    - Automated testing pipeline

### Struktur Aplikasi Final:

```
laravel_notes/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── AuthController.php
│   │   │   │   └── NoteController.php
│   │   │   ├── Auth/
│   │   │   ├── Admin/
│   │   │   ├── SuperAdmin/
│   │   │   ├── BillingController.php
│   │   │   ├── MidtransWebhookController.php
│   │   │   └── NoteController.php
│   │   ├── Middleware/
│   │   │   ├── RoleMiddleware.php
│   │   │   └── EnsureOrganizationIsActive.php
│   │   └── Resources/
│   │       └── NoteResource.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Organization.php
│   │   ├── Category.php
│   │   ├── Note.php
│   │   └── Payment.php
│   ├── Policies/
│   │   └── NotePolicy.php
│   └── Services/
│       └── MidtransSnapService.php
├── database/
│   └── migrations/
├── routes/
│   ├── web.php
│   ├── api.php
│   └── auth.php
├── resources/
│   └── views/
├── tests/
│   └── Feature/
│       └── Api/
└── config/
    ├── midtrans.php
    └── billing.php
```

### Resources untuk Belajar Lebih Lanjut:

**Official Documentation:**
- Laravel Documentation: https://laravel.com/docs
- Laravel Sanctum: https://laravel.com/docs/sanctum
- Midtrans API: https://docs.midtrans.com

**Tutorials & Courses:**
- Laracasts: https://laracasts.com
- Laravel Daily: https://laraveldaily.com
- Laravel News: https://laravel-news.com

**Community:**
- Laravel Reddit: https://reddit.com/r/laravel
- Laravel Discord: https://discord.gg/laravel
- Larachat: https://larachat.co

---

Selamat! Anda telah menyelesaikan tutorial lengkap membangun aplikasi enterprise-grade dengan Laravel. Aplikasi ini siap dikembangkan lebih lanjut sesuai kebutuhan bisnis Anda.

**Selamat belajar dan happy coding! 🚀**
