@extends('layouts.app')
@section('title', __('otp.title'))

@section('content')
    <h1 class="mb-2 text-xl font-semibold">{{ __('otp.title') }}</h1>

    <p class="mb-2 text-gray-700">{{ __('otp.intro', ['number' => $masked]) }}</p>
    <p class="mb-4 text-sm text-gray-500">{{ __('otp.why') }}</p>

    <form method="POST" action="{{ route('seller.listings.otp.verify', $listing) }}" class="mb-3">
        @csrf
        <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
               maxlength="6" class="rounded border p-2 font-mono text-lg tracking-widest"
               aria-label="{{ __('otp.code') }}">

        <button type="submit" class="rounded bg-gray-900 px-4 py-2 text-white">{{ __('otp.verify') }}</button>

        @error('code')
            <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
        @enderror
    </form>

    <form method="POST" action="{{ route('seller.listings.otp.resend', $listing) }}">
        @csrf
        <button type="submit" class="text-sm text-gray-600 underline">{{ __('otp.resend') }}</button>
    </form>
@endsection
