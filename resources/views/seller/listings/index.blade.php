@extends('layouts.app')
@section('title', __('listing.my_listings'))

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-semibold">{{ __('listing.my_listings') }}</h1>
        <a href="{{ route('seller.listings.create') }}" class="rounded bg-gray-900 px-4 py-2 text-sm text-white">
            {{ __('listing.create_title') }}
        </a>
    </div>

    @forelse ($listings as $listing)
        <div class="mb-2 rounded border bg-white p-4">
            <div class="flex items-center justify-between">
                {{-- Продаваемый номер виден целиком: это товар. Инвариант №1. --}}
                <span class="font-mono text-lg">{{ $listing->formattedMsisdn() }}</span>
                <span class="text-sm text-gray-600">{{ __('listing.statuses.'.$listing->status) }}</span>
            </div>

            @if ($listing->status === 'pending' && $listing->phone_verified_at === null)
                <a href="{{ route('seller.listings.otp.show', $listing) }}" class="text-sm text-blue-700">
                    {{ __('otp.title') }}
                </a>
            @endif
        </div>
    @empty
        <p class="text-gray-600">{{ __('listing.no_listings') }}</p>
    @endforelse

    {{ $listings->links() }}
@endsection
