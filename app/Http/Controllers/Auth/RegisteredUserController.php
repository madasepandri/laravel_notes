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
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register', [
            'registrationFee' => (int) config('billing.organization_registration_fee', 100_000),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
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
