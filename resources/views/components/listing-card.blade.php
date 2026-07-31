@props(['listing'])

<a href="{{ route('listings.show', $listing) }}"
   class="block rounded-md border border-line bg-surface p-4 shadow-sm transition hover:border-line-strong hover:shadow-md">
    {{-- Продаваемый номер — герой карточки. Виден целиком: инвариант №1, это
         товар, витрина и весь SEO. Никогда не маскируется. --}}
    <div class="font-mono text-2xl font-medium tracking-[0.12em] text-product">{{ $listing->formattedMsisdn() }}</div>

    {{-- Паттерн-теги — знак ценности («почему номер красивый»): латунь, сразу под номером. --}}
    @if (!empty($listing->pattern_tags))
        <div class="mt-2 flex flex-wrap gap-1">
            @foreach ($listing->pattern_tags as $tag)
                <span class="rounded-full bg-accent-weak px-2 py-0.5 text-xs font-medium text-accent-strong">
                    {{ __('browse.tags.'.$tag) }}
                </span>
            @endforeach
        </div>
    @endif

    <div class="mt-3 flex items-baseline gap-2">
        @if ($listing->is_negotiable)
            <span class="text-sm text-ink-muted">{{ __('browse.negotiable') }}</span>
        @else
            <span class="text-lg font-semibold text-ink">{{ (int) $listing->price }} €</span>
        @endif

        @if ($listing->shop_id)
            <span class="rounded-full bg-shop-bg px-2 py-0.5 text-xs text-shop-ink">{{ __('browse.shop') }}</span>
        @endif
    </div>

    <div class="mt-2 text-sm text-ink-subtle">
        {{ $listing->province?->localizedName() }} ·
        {{ __('listing.conditions.'.$listing->condition) }}
    </div>
</a>
