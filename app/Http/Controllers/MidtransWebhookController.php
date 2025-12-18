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

