@extends('layouts.app')

@section('content')
    <h1 class="mb-2 text-2xl font-semibold">{{ config('app.name') }}</h1>
    <p class="mb-4 text-gray-600">{{ __('auth.why_sign_in') }}</p>

    {{-- Витрина — в S4. Здесь пока только вход и подача. --}}
    @auth
        <a href="{{ route('seller.listings.create') }}" class="rounded bg-gray-900 px-4 py-2 text-white">
            {{ __('listing.create_title') }}
        </a>
    @endauth
@endsection
