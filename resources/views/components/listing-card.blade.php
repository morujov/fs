@props(['listing'])

<a href="{{ route('listings.show', $listing) }}"
   class="block rounded border bg-white p-4 transition hover:border-gray-400">
    {{-- Продаваемый номер виден целиком. Инвариант №1: это товар,
         витрина и весь SEO. Никогда не маскируется. --}}
    <div class="font-mono text-xl tracking-wide">{{ $listing->formattedMsisdn() }}</div>

    <div class="mt-2 flex items-baseline gap-2">
        @if ($listing->is_negotiable)
            <span class="text-sm text-gray-600">{{ __('browse.negotiable') }}</span>
        @else
            <span class="text-lg font-semibold">{{ (int) $listing->price }} €</span>
        @endif

        @if ($listing->shop_id)
            <span class="rounded bg-blue-50 px-2 py-0.5 text-xs text-blue-800">{{ __('browse.shop') }}</span>
        @endif
    </div>

    <div class="mt-2 flex flex-wrap gap-1">
        @foreach ($listing->pattern_tags ?? [] as $tag)
            <span class="rounded bg-amber-50 px-2 py-0.5 text-xs text-amber-900">
                {{ __('browse.tags.'.$tag) }}
            </span>
        @endforeach
    </div>

    <div class="mt-2 text-sm text-gray-500">
        {{ $listing->operator?->name }} ·
        {{ $listing->province?->localizedName() }} ·
        {{ __('listing.conditions.'.$listing->condition) }}
    </div>
</a>
