
# Tutorial Integrasi Midtrans (Snap) — Aktivasi Organisasi Berbayar

Dokumen ini adalah tutorial implementasi fitur **aktivasi organisasi berbayar** menggunakan **Midtrans Snap** pada project ini.

## Target Fitur

- Admin organisasi bisa **mendaftar sendiri**.
- Sistem **mencari organisasi berdasarkan nama**:
  - jika sudah ada -> pakai organisasi tersebut,
  - jika belum ada -> buat organisasi baru.
- Jika organisasi **belum aktif**, sistem membuat tagihan aktivasi (default **Rp 100.000**) via Midtrans Snap.
- Setelah pembayaran **settlement/capture**, organisasi menjadi **aktif** dan fitur admin/notes terbuka.

Status pembayaran disimpan di tabel `payments`, sedangkan status aktivasi organisasi disimpan di `organizations.is_active`.

---

## 1) Prasyarat

- PHP 8.2+ (project ini berjalan di PHP 8.3)
- Laravel 12
- Database MySQL/MariaDB
- Akun Midtrans (Sandbox untuk development)

---

## 2) Migrasi Database

### 2.1. Tambah Kolom Aktivasi ke `organizations`

**File:** `database/migrations/2025_12_18_000001_add_activation_to_organizations_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->boolean('is_active')->default(false);
            $table->timestamp('activated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'activated_at']);
        });
    }
};
```

### 2.2. Buat Tabel `payments`

**File:** `database/migrations/2025_12_18_000002_create_payments_table.php`

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
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('order_id')->unique();
            $table->unsignedInteger('gross_amount');
            $table->enum('status', ['pending', 'paid', 'failed', 'expired', 'canceled'])->default('pending');
            $table->string('snap_token')->nullable();
            $table->text('snap_redirect_url')->nullable();
            $table->string('midtrans_transaction_status')->nullable();
            $table->string('midtrans_fraud_status')->nullable();
            $table->string('midtrans_status_code')->nullable();
            $table->text('midtrans_signature_key')->nullable();
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

### 2.3. Jalankan Migrasi

```bash
php artisan migrate
```

Jika ini masih development dan boleh menghapus data:

```bash
php artisan migrate:fresh
```

---
## 3) Model & Relasi

### 3.1. Model `Payment`

**File:** `app/Models/Payment.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

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

    protected function casts(): array
    {
        return [
            'gross_amount' => 'integer',
            'raw_notification' => 'array',
            'paid_at' => 'datetime',
        ];
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

### 3.2. Update Model `Organization`

**File:** `app/Models/Organization.php`

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
        'is_active',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
        ];
    }

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

---
## 4) Konfigurasi `.env`

### 4.1. Variable yang Dibutuhkan

Tambahkan/isi variable berikut di `.env` (lihat `.env.example`):

```dotenv
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_SERVER_KEY=SB-Mid-server-xxxx
MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxx

# SSL
MIDTRANS_VERIFY_SSL=true
# Opsional (Windows/Laragon), isi bila sering kena cURL error 77
MIDTRANS_CA_CERT=C:\laragon\etc\ssl\cacert.pem

# Biaya aktivasi organisasi
ORG_REG_FEE=100000
```

### 4.2. Clear Config Cache

Setelah ubah `.env`:

```bash
php artisan config:clear
```

---

## 5) File Konfigurasi Aplikasi

### 5.1. `config/midtrans.php`

**File:** `config/midtrans.php`

```php
<?php

return [
    'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
    'server_key' => env('MIDTRANS_SERVER_KEY'),
    'client_key' => env('MIDTRANS_CLIENT_KEY'),
    'verify_ssl' => (bool) env('MIDTRANS_VERIFY_SSL', true),
    'ca_cert' => env('MIDTRANS_CA_CERT') ?: (file_exists(base_path('vendor/midtrans/midtrans-php/data/cacert.pem'))
        ? base_path('vendor/midtrans/midtrans-php/data/cacert.pem')
        : null),
];
```

### 5.2. `config/billing.php`

**File:** `config/billing.php`

```php
<?php

return [
    'organization_registration_fee' => (int) env('ORG_REG_FEE', 100_000),
    'currency' => env('BILLING_CURRENCY', 'IDR'),
];
```

---
## 6) Service Midtrans (Snap + Status API)

Semua komunikasi ke Midtrans dipusatkan pada service.

**File:** `app/Services/MidtransSnapService.php`

```php
<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class MidtransSnapService
{
    public function createSnapTransaction(Payment $payment, User $user): array
    {
        $serverKey = config('midtrans.server_key');
        if (!$serverKey) {
            throw new \RuntimeException('MIDTRANS_SERVER_KEY is not configured.');
        }

        $baseUrl = config('midtrans.is_production')
            ? 'https://app.midtrans.com'
            : 'https://app.sandbox.midtrans.com';

        $payload = [
            'transaction_details' => [
                'order_id' => $payment->order_id,
                'gross_amount' => $payment->gross_amount,
            ],
            'item_details' => [
                [
                    'id' => 'ORG_REG_FEE',
                    'price' => $payment->gross_amount,
                    'quantity' => 1,
                    'name' => 'Biaya pendaftaran organisasi',
                ],
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
            ],
            'callbacks' => [
                'finish' => route('billing.show', absolute: true),
            ],
        ];

        $verify = (bool) config('midtrans.verify_ssl', true);
        $caCert = config('midtrans.ca_cert');
        $verifyOption = $verify ? ($caCert ?: true) : false;

        try {
            $response = Http::withBasicAuth($serverKey, '')
                ->withOptions(['verify' => $verifyOption])
                ->acceptJson()
                ->asJson()
                ->post($baseUrl.'/snap/v1/transactions', $payload)
                ->throw();
        } catch (RequestException|ConnectionException $e) {
            $message = $e instanceof RequestException
                ? ($e->response?->json('error_messages.0') ?? $e->getMessage())
                : $e->getMessage();

            throw new \RuntimeException('Midtrans request failed: '.$message, previous: $e);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Midtrans request failed: '.$e->getMessage(), previous: $e);
        }

        return [
            'token' => (string) $response->json('token'),
            'redirect_url' => (string) $response->json('redirect_url'),
        ];
    }

    public function fetchTransactionStatus(Payment $payment): array
    {
        $serverKey = config('midtrans.server_key');
        if (!$serverKey) {
            throw new \RuntimeException('MIDTRANS_SERVER_KEY is not configured.');
        }

        $baseUrl = config('midtrans.is_production')
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';

        $verify = (bool) config('midtrans.verify_ssl', true);
        $caCert = config('midtrans.ca_cert');
        $verifyOption = $verify ? ($caCert ?: true) : false;

        try {
            $response = Http::withBasicAuth($serverKey, '')
                ->withOptions(['verify' => $verifyOption])
                ->acceptJson()
                ->get($baseUrl.'/v2/'.$payment->order_id.'/status')
                ->throw();
        } catch (RequestException|ConnectionException $e) {
            $message = $e instanceof RequestException
                ? ($e->response?->json('error_messages.0') ?? $e->getMessage())
                : $e->getMessage();

            throw new \RuntimeException('Midtrans status request failed: '.$message, previous: $e);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Midtrans status request failed: '.$e->getMessage(), previous: $e);
        }

        return $response->json();
    }
}
```

---
## 7) Registrasi Admin Organisasi (Create/Find Organization)

Registrasi dipakai untuk membuat admin organisasi.

### 7.1. Aktifkan Route Register

**File:** `routes/auth.php`

```php
<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
```

### 7.2. Controller Register

**File:** `app/Http/Controllers/Auth/RegisteredUserController.php`

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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register', [
            'registrationFee' => (int) config('billing.organization_registration_fee', 100_000),
        ]);
    }

    public function store(Request $request, MidtransSnapService $midtrans): RedirectResponse
    {
        $request->validate([
            'organization_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $organization = Organization::where('name', $request->organization_name)->first();
        if (!$organization) {
            $organization = Organization::create([
                'name' => $request->organization_name,
                'is_active' => false,
            ]);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'admin',
            'organization_id' => $organization->id,
        ]);

        event(new Registered($user));

        Auth::login($user);

        if ($organization->is_active) {
            return redirect()->route('dashboard');
        }

        $payment = Payment::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'order_id' => 'ORGREG-'.$organization->id.'-'.Str::upper(Str::random(10)),
            'gross_amount' => (int) config('billing.organization_registration_fee', 100_000),
            'status' => 'pending',
        ]);

        try {
            $snap = $midtrans->createSnapTransaction($payment, $user);
            $payment->update([
                'snap_token' => $snap['token'] ?: null,
                'snap_redirect_url' => $snap['redirect_url'] ?: null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to create Midtrans snap token', [
                'order_id' => $payment->order_id,
                'message' => $e->getMessage(),
            ]);
            session()->flash('status', 'Registrasi berhasil. Silakan konfigurasi Midtrans atau coba buat tagihan lagi.');
        }

        return redirect()->route('billing.show');
    }
}
```

### 7.3. Form Register

**File:** `resources/views/auth/register.blade.php`

```blade
<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Organization Name -->
        <div>
            <x-input-label for="organization_name" :value="__('Nama Organisasi')" />
            <x-text-input id="organization_name" class="block mt-1 w-full" type="text" name="organization_name" :value="old('organization_name')" required autofocus autocomplete="organization" />
            <x-input-error :messages="$errors->get('organization_name')" class="mt-2" />
            <div class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Biaya aktivasi: Rp {{ number_format($registrationFee ?? 100000, 0, ',', '.') }}
            </div>
        </div>

        <!-- Name -->
        <div class="mt-4">
            <x-input-label for="name" :value="__('Nama Admin')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Daftar & Bayar') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
```

---
## 8) Halaman Aktivasi (Billing)

### 8.1. Routes Billing

**File:** `routes/web.php`

```php
<?php

use App\Http\Controllers\NoteController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\MidtransWebhookController;
use App\Http\Controllers\SuperAdmin\AdminController as SuperAdminController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    $user = request()->user();
    if ($user?->role === 'admin' && !$user->organization?->is_active) {
        return redirect()->route('billing.show');
    }
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::get('/billing', [BillingController::class, 'show'])->name('billing.show');
    Route::post('/billing', [BillingController::class, 'create'])->name('billing.create');
    Route::post('/billing/sync', [BillingController::class, 'sync'])->name('billing.sync');
});

Route::post('/midtrans/notification', [MidtransWebhookController::class, 'notification'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('midtrans.notification');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware(['role:super_admin'])->prefix('superadmin')->name('superadmin.')->group(function () {
        Route::resource('admins', SuperAdminController::class);
    });

    Route::middleware(['role:admin', 'org.active'])->prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', AdminUserController::class);
        Route::resource('categories', AdminCategoryController::class);
    });

    Route::middleware(['role:admin,user', 'org.active'])->group(function () {
        Route::resource('notes', NoteController::class);
    });
});

require __DIR__.'/auth.php';
```

### 8.2. Controller Billing

**File:** `app/Http/Controllers/BillingController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\MidtransSnapService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class BillingController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'admin', 403);

        $organization = $user->organization;
        abort_unless($organization, 403);

        $payment = Payment::query()
            ->where('organization_id', $organization->id)
            ->latest()
            ->first();

        return view('payment.billing', [
            'organization' => $organization,
            'payment' => $payment,
            'midtransClientKey' => config('midtrans.client_key'),
            'midtransIsProduction' => (bool) config('midtrans.is_production'),
        ]);
    }

    public function create(Request $request, MidtransSnapService $midtrans)
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'admin', 403);

        $organization = $user->organization;
        abort_unless($organization, 403);

        $fee = (int) config('billing.organization_registration_fee', 100_000);

        $payment = Payment::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'order_id' => 'ORGREG-'.$organization->id.'-'.Str::upper(Str::random(10)),
            'gross_amount' => $fee,
            'status' => 'pending',
        ]);

        try {
            $snap = $midtrans->createSnapTransaction($payment, $user);
            $payment->update([
                'snap_token' => $snap['token'] ?: null,
                'snap_redirect_url' => $snap['redirect_url'] ?: null,
            ]);
        } catch (\Throwable $e) {
            $request->session()->flash('billing_error', $e->getMessage());
        }

        return redirect()->route('billing.show');
    }

    public function sync(Request $request, MidtransSnapService $midtrans)
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'admin', 403);

        $payment = Payment::where('organization_id', $user->organization_id)
            ->latest()
            ->first();

        if (!$payment) {
            return redirect()->route('billing.show')->with('billing_error', 'Tidak ada tagihan untuk disinkronkan.');
        }

        try {
            $payload = $midtrans->fetchTransactionStatus($payment);
        } catch (\Throwable $e) {
            return redirect()->route('billing.show')->with('billing_error', $e->getMessage());
        }

        $transactionStatus = (string) data_get($payload, 'transaction_status');
        $fraudStatus = (string) data_get($payload, 'fraud_status');
        $statusCode = (string) data_get($payload, 'status_code');
        $signatureKey = (string) data_get($payload, 'signature_key');
        $grossAmount = (string) data_get($payload, 'gross_amount');
        $orderId = (string) data_get($payload, 'order_id');

        $expected = hash('sha512', $orderId.$statusCode.$grossAmount.config('midtrans.server_key'));
        if ($orderId !== $payment->order_id || !hash_equals($expected, $signatureKey)) {
            return redirect()->route('billing.show')->with('billing_error', 'Signature Midtrans tidak valid.');
        }

        $payment->update([
            'midtrans_transaction_status' => $transactionStatus,
            'midtrans_fraud_status' => $fraudStatus ?: null,
            'midtrans_status_code' => $statusCode ?: null,
            'midtrans_signature_key' => $signatureKey ?: null,
            'raw_notification' => $payload,
        ]);

        if (in_array($transactionStatus, ['settlement', 'capture'], true)) {
            if ($transactionStatus === 'capture' && $fraudStatus === 'challenge') {
                $payment->update(['status' => 'pending']);
                return redirect()->route('billing.show')->with('status', 'Transaksi masih challenge.');
            }

            if ($payment->status !== 'paid') {
                $payment->update([
                    'status' => 'paid',
                    'paid_at' => Carbon::now(),
                ]);

                $payment->organization?->update([
                    'is_active' => true,
                    'activated_at' => Carbon::now(),
                ]);
            }

            return redirect()->route('billing.show')->with('status', 'Pembayaran berhasil dan organisasi sudah aktif.');
        }

        if ($transactionStatus === 'pending') {
            $payment->update(['status' => 'pending']);
            return redirect()->route('billing.show')->with('status', 'Status masih pending.');
        }

        if ($transactionStatus === 'expire') {
            $payment->update(['status' => 'expired']);
            return redirect()->route('billing.show')->with('status', 'Tagihan sudah kedaluwarsa.');
        }

        if ($transactionStatus === 'cancel') {
            $payment->update(['status' => 'canceled']);
            return redirect()->route('billing.show')->with('status', 'Transaksi dibatalkan.');
        }

        if ($transactionStatus === 'deny') {
            $payment->update(['status' => 'failed']);
            return redirect()->route('billing.show')->with('status', 'Transaksi ditolak.');
        }

        return redirect()->route('billing.show')->with('status', 'Status terakhir: '.$transactionStatus);
    }
}
```

### 8.3. View Billing

**File:** `resources/views/payment/billing.blade.php`

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Aktivasi Organisasi') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100 space-y-4">
                    @if (session('status'))
                        <x-auth-session-status :status="session('status')" />
                    @endif
                    @if (session('billing_error'))
                        <div class="rounded-md bg-red-50 dark:bg-red-900/30 p-4 text-sm text-red-800 dark:text-red-200">
                            {{ session('billing_error') }}
                        </div>
                    @endif

                    <div>
                        <div class="text-lg font-semibold">{{ $organization->name }}</div>
                        @if ($organization->is_active)
                            <div class="mt-1 text-sm text-green-600 dark:text-green-400">Status: Aktif</div>
                        @else
                            <div class="mt-1 text-sm text-amber-600 dark:text-amber-400">Status: Belum aktif (perlu pembayaran)</div>
                        @endif
                    </div>

                    @if ($organization->is_active)
                        <div class="flex items-center gap-3">
                            <a href="{{ route('dashboard') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                                Masuk ke Beranda
                            </a>
                        </div>
                    @else
                        <div class="rounded-md border border-gray-200 dark:border-gray-700 p-4">
                            <div class="font-semibold">Biaya aktivasi: Rp {{ number_format(config('billing.organization_registration_fee', 100_000), 0, ',', '.') }}</div>
                            <div class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Setelah pembayaran berhasil, fitur admin akan terbuka.
                            </div>
                        </div>

                        @if (!$payment)
                            <form method="POST" action="{{ route('billing.create') }}">
                                @csrf
                                <x-primary-button>Buat Tagihan</x-primary-button>
                            </form>
                        @else
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                Tagihan terakhir: <span class="font-mono">{{ $payment->order_id }}</span> ({{ strtoupper($payment->status) }})
                            </div>

                            @if ($payment->snap_token && $midtransClientKey)
                                <div class="flex flex-wrap items-center gap-3">
                                    <button id="pay-button" type="button" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                                        Bayar Sekarang
                                    </button>

                                    @if ($payment->snap_redirect_url)
                                        <a class="text-sm underline text-gray-700 dark:text-gray-300" href="{{ $payment->snap_redirect_url }}" target="_blank" rel="noreferrer">
                                            Buka halaman pembayaran
                                        </a>
                                    @endif

                                    <form method="POST" action="{{ route('billing.sync') }}">
                                        @csrf
                                        <x-secondary-button type="submit">Perbarui Status</x-secondary-button>
                                    </form>
                                </div>

                                <script
                                    src="{{ $midtransIsProduction ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js' }}"
                                    data-client-key="{{ $midtransClientKey }}"
                                ></script>
                                <script>
                                    document.getElementById('pay-button')?.addEventListener('click', function () {
                                        if (!window.snap) return;
                                        window.snap.pay(@json($payment->snap_token), {
                                            onSuccess: function () { window.location.reload(); },
                                            onPending: function () { window.location.reload(); },
                                            onError: function () { window.location.reload(); },
                                            onClose: function () { /* noop */ },
                                        });
                                    });
                                </script>
                            @else
                                <div class="rounded-md bg-amber-50 dark:bg-amber-900/30 p-4 text-sm text-amber-800 dark:text-amber-200">
                                    Midtrans belum siap (pastikan `MIDTRANS_CLIENT_KEY` dan `MIDTRANS_SERVER_KEY` terisi).
                                </div>
                            @endif
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```

---
## 9) Webhook Midtrans (Payment Notification)

Webhook dipakai untuk update status secara otomatis saat Midtrans mengirim notifikasi.

**Controller:** `app/Http/Controllers/MidtransWebhookController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class MidtransWebhookController extends Controller
{
    public function notification(Request $request)
    {
        $payload = $request->json()->all();

        $orderId = (string) data_get($payload, 'order_id');
        if ($orderId === '') {
            return response()->json(['message' => 'Missing order_id'], 422);
        }

        $payment = Payment::where('order_id', $orderId)->first();
        if (!$payment) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $serverKey = (string) config('midtrans.server_key');
        $statusCode = (string) data_get($payload, 'status_code');
        $grossAmount = (string) data_get($payload, 'gross_amount');
        $signatureKey = (string) data_get($payload, 'signature_key');

        $expected = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);
        if (!hash_equals($expected, $signatureKey)) {
            Log::warning('Midtrans signature mismatch', ['order_id' => $orderId]);
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $transactionStatus = (string) data_get($payload, 'transaction_status');
        $fraudStatus = (string) data_get($payload, 'fraud_status');

        $payment->update([
            'midtrans_transaction_status' => $transactionStatus,
            'midtrans_fraud_status' => $fraudStatus ?: null,
            'midtrans_status_code' => $statusCode ?: null,
            'midtrans_signature_key' => $signatureKey ?: null,
            'raw_notification' => $payload,
        ]);

        if (in_array($transactionStatus, ['settlement', 'capture'], true)) {
            if ($transactionStatus === 'capture' && $fraudStatus === 'challenge') {
                $payment->update(['status' => 'pending']);
                return response()->json(['message' => 'OK']);
            }

            if ($payment->status !== 'paid') {
                $payment->update([
                    'status' => 'paid',
                    'paid_at' => Carbon::now(),
                ]);

                $payment->organization?->update([
                    'is_active' => true,
                    'activated_at' => Carbon::now(),
                ]);
            }

            return response()->json(['message' => 'OK']);
        }

        if ($transactionStatus === 'pending') {
            $payment->update(['status' => 'pending']);
            return response()->json(['message' => 'OK']);
        }

        if ($transactionStatus === 'expire') {
            $payment->update(['status' => 'expired']);
            return response()->json(['message' => 'OK']);
        }

        if ($transactionStatus === 'cancel') {
            $payment->update(['status' => 'canceled']);
            return response()->json(['message' => 'OK']);
        }

        if ($transactionStatus === 'deny') {
            $payment->update(['status' => 'failed']);
            return response()->json(['message' => 'OK']);
        }

        return response()->json(['message' => 'OK']);
    }
}
```

> Untuk production: arahkan Midtrans **Payment Notification URL** ke `https://domain-kamu.com/midtrans/notification`.

---

## 10) Mengunci Fitur Sebelum Lunas

### 10.1. Middleware `EnsureOrganizationIsActive`

**File:** `app/Http/Middleware/EnsureOrganizationIsActive.php`

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

        if ($user?->role === 'admin' && !$user->organization?->is_active) {
            return redirect()->route('billing.show');
        }

        return $next($request);
    }
}
```

### 10.2. Daftarkan Alias Middleware

**File:** `bootstrap/app.php`

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'org.active' => \App\Http\Middleware\EnsureOrganizationIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
```

---

## 11) Cara Cek Status Pembayaran

### 11.1. Database

- `payments.status`:
  - `pending|paid|failed|expired|canceled`
- `payments.paid_at`
- `organizations.is_active` dan `organizations.activated_at`

### 11.2. UI

Buka `/billing`:
- Jika status belum berubah setelah bayar (local), klik **Perbarui Status**.

---

## 12) Troubleshooting

### 12.1. cURL error 77 (SSL certificate)

Jika request ke Midtrans gagal dengan `cURL error 77 ... cacert.pem`:

Solusi disarankan (global PHP di Laragon):
1. Cek `php.ini`: `php --ini`
2. Set:
   - `curl.cainfo="C:\laragon\etc\ssl\cacert.pem"`
   - `openssl.cafile="C:\laragon\etc\ssl\cacert.pem"`
3. Restart Laragon.

Alternatif per project:
- Set `.env`:
  - `MIDTRANS_CA_CERT=C:\laragon\etc\ssl\cacert.pem`
- Lalu `php artisan config:clear`

### 12.2. Halaman billing tidak berubah setelah bayar

Biasanya webhook tidak bisa menjangkau local. Gunakan tombol **Perbarui Status** (route `POST /billing/sync`).

---

## Referensi File Utama

- Konfigurasi: `config/midtrans.php`, `config/billing.php`
- Migrasi: `database/migrations/2025_12_18_000001_add_activation_to_organizations_table.php`, `database/migrations/2025_12_18_000002_create_payments_table.php`
- Payment model: `app/Models/Payment.php`
- Register flow: `routes/auth.php`, `app/Http/Controllers/Auth/RegisteredUserController.php`, `resources/views/auth/register.blade.php`
- Billing: `routes/web.php`, `app/Http/Controllers/BillingController.php`, `resources/views/payment/billing.blade.php`
- Webhook: `app/Http/Controllers/MidtransWebhookController.php`
- Middleware lock: `app/Http/Middleware/EnsureOrganizationIsActive.php`, `bootstrap/app.php`
