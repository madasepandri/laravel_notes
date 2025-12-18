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

    // Super Admin Routes
    Route::middleware(['role:super_admin'])->prefix('superadmin')->name('superadmin.')->group(function () {
        Route::resource('admins', SuperAdminController::class);
    });

    // Admin Routes
    Route::middleware(['role:admin', 'org.active'])->prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', AdminUserController::class);
        Route::resource('categories', AdminCategoryController::class);
    });

    // Notes Routes (accessible by admin and user roles)
    Route::middleware(['role:admin,user', 'org.active'])->group(function () {
        Route::resource('notes', NoteController::class);
    });
});

require __DIR__.'/auth.php';
