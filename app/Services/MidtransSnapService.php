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
