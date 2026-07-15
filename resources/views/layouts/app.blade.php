{{-- Черновой каркас. Нормальная вёрстка — в S4 вместе с витриной. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-900">
    <header class="border-b bg-white">
        <div class="mx-auto flex max-w-4xl items-center justify-between p-4">
            <a href="{{ route('home') }}" class="font-semibold">{{ config('app.name') }}</a>

            @auth
                <div class="flex items-center gap-4 text-sm">
                    <a href="{{ route('seller.listings.index') }}">{{ __('listing.my_listings') }}</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit">{{ __('auth.sign_out') }}</button>
                    </form>
                </div>
            @else
                <a href="{{ route('auth.google.redirect') }}" class="rounded bg-gray-900 px-4 py-2 text-sm text-white">
                    {{ __('auth.sign_in_with_google') }}
                </a>
            @endauth
        </div>
    </header>

    <main class="mx-auto max-w-4xl p-4">
        @if (session('status'))
            <div class="mb-4 rounded bg-green-100 p-3 text-sm text-green-900">{{ session('status') }}</div>
        @endif

        @if (session('error'))
            <div class="mb-4 rounded bg-red-100 p-3 text-sm text-red-900">{{ session('error') }}</div>
        @endif

        @yield('content')
    </main>
</body>
</html>
