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
