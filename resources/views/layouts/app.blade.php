{{-- Каркас. Намеренно простой: дизайн — отдельная задача, сейчас важнее,
     чтобы работали поиск и гейт на контактах. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="text-ink">
    <x-bg-pattern />

    <header class="border-b border-line bg-surface">
        <div class="mx-auto flex max-w-4xl flex-wrap items-center justify-between gap-x-4 gap-y-2 p-4">
            <a href="{{ route('home') }}" class="font-semibold">{{ config('app.name') }}</a>

            <div class="flex items-center gap-3 sm:gap-4">
                <x-locale-switcher />

                @auth
                    <div class="flex items-center gap-4 text-sm">
                        <a href="{{ route('seller.listings.index') }}" class="hover:text-accent">{{ __('listing.my_listings') }}</a>
                        <a href="{{ route('account.privacy.show') }}" class="hover:text-accent">{{ __('gdpr.title') }}</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="hover:text-accent">{{ __('auth.sign_out') }}</button>
                        </form>
                    </div>
                @else
                    <a href="{{ route('auth.google.redirect') }}"
                       class="rounded-md bg-selected px-4 py-2 text-sm text-selected-ink">
                        {{ __('auth.sign_in_with_google') }}
                    </a>
                @endauth
            </div>
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
    @stack('scripts')
</body>
</html>
