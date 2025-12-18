<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        <!-- Styles -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased bg-gray-100 dark:bg-gray-900">
        <div class="relative min-h-screen flex flex-col items-center justify-center">
            @if (Route::has('login'))
                <div class="absolute top-0 right-0 p-6">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="font-semibold text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white focus:outline focus:outline-2 focus:rounded-sm focus:outline-red-500">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="font-semibold text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white focus:outline focus:outline-2 focus:rounded-sm focus:outline-red-500">Log in</a>

                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="ml-4 font-semibold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 focus:outline focus:outline-2 focus:rounded-sm focus:outline-red-500">Daftar Organisasi</a>
                        @endif
                    @endauth
                </div>
            @endif

            <div class="max-w-7xl mx-auto p-6 lg:p-8">
                <div class="flex justify-center">
                    <h1 class="text-4xl font-bold text-gray-800 dark:text-white">Laravel Notes</h1>
                </div>

                <div class="mt-8">
                    <p class="text-center text-gray-600 dark:text-gray-400">
                        Welcome to Laravel Notes. Your simple and powerful note-taking application.
                    </p>
                </div>

                <div class="mt-10 grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <div class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            Kelola catatan tim dalam 1 organisasi
                        </div>
                        <div class="mt-2 text-gray-600 dark:text-gray-400">
                            Buat organisasi, tambah pengguna, dan atur kategori catatan agar rapi.
                        </div>
                        <ul class="mt-4 space-y-2 text-sm text-gray-700 dark:text-gray-300">
                            <li>• Admin bisa kelola pengguna & kategori</li>
                            <li>• Pengguna bisa membuat catatan pribadi</li>
                            <li>• Admin bisa melihat catatan organisasi</li>
                        </ul>
                    </div>

                    <div class="bg-indigo-600 text-white rounded-xl shadow-sm p-6">
                        <div class="text-sm uppercase tracking-wider opacity-90">Mulai Sekarang</div>
                        <div class="mt-2 text-2xl font-bold">
                            Rp {{ number_format(config('billing.organization_registration_fee', 100000), 0, ',', '.') }}
                        </div>
                        <div class="mt-1 text-sm opacity-90">Biaya aktivasi organisasi (sekali bayar)</div>
                        <div class="mt-5">
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="inline-flex w-full justify-center items-center px-4 py-2 bg-white text-indigo-700 rounded-md font-semibold hover:bg-indigo-50">
                                    Daftar & Aktivasi
                                </a>
                            @endif
                            <div class="mt-3 text-xs opacity-80">
                                Pembayaran via Midtrans (Snap).
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
