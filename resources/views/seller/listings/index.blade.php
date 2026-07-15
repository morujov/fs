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

            @if ($listing->status === 'active' && $listing->expires_at)
                <p class="mt-1 text-xs text-gray-500">
                    {{ __('listing.expires_in', ['days' => max((int) now()->diffInDays($listing->expires_at, false), 0)]) }}
                </p>
            @endif

            @if ($listing->status === 'rejected' && $listing->rejection_reason)
                <p class="mt-1 text-sm text-red-700">{{ $listing->rejection_reason }}</p>
            @endif

            {{-- Без «продано» доска зарастает проданными номерами: покупатель
                 звонит по десяти объявлениям, все неактуальны, и не
                 возвращается. Блокер №6 блюпринта. --}}
            <div class="mt-2 flex gap-2">
                @if (in_array($listing->status, ['active', 'pending'], true))
                    <form method="POST" action="{{ route('seller.listings.sold', $listing) }}">
                        @csrf
                        <button type="submit" class="rounded border px-2 py-1 text-xs">{{ __('listing.mark_sold') }}</button>
                    </form>

                    <form method="POST" action="{{ route('seller.listings.archive', $listing) }}">
                        @csrf
                        <button type="submit" class="rounded border px-2 py-1 text-xs">{{ __('listing.archive') }}</button>
                    </form>
                @endif

                @if (in_array($listing->status, ['active', 'expired'], true))
                    <a href="{{ route('seller.listings.renew', $listing) }}"
                       class="rounded border px-2 py-1 text-xs">{{ __('listing.renew') }}</a>
                @endif
            </div>
        </div>
    @empty
        <p class="text-gray-600">{{ __('listing.no_listings') }}</p>
    @endforelse

    {{ $listings->links() }}
@endsection
