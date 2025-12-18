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
