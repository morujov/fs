@extends('layouts.app')
@section('title', __('browse.title'))

@section('content')
    <h1 class="mb-4 text-xl font-semibold">{{ __('browse.title') }}</h1>

    <form method="GET" action="{{ route('home') }}" class="mb-6 space-y-4">
        {{-- Маска номера — герой формы. Значение уже санитизировано в контроллере:
             в поле не может вернуться ничего, кроме цифр и '?'. --}}
        <div>
            <label class="mb-1 block text-sm font-medium text-ink" for="q">{{ __('browse.search') }}</label>
            <input type="text" name="q" id="q" value="{{ $pattern }}"
                   inputmode="numeric" maxlength="9" autocomplete="off" placeholder="6??12??34"
                   class="w-full rounded-md border border-line bg-surface p-3 font-mono text-lg tracking-widest">
            <p class="mt-1 text-xs text-ink-subtle">{{ __('browse.search_help') }}</p>
        </div>

        {{-- Категории «красоты» подняты сразу под маску: это и эмоция, ради
             которой приходят, и SEO-посадочные. Чистый CSS has-[:checked], без JS. --}}
        <div>
            <span class="mb-1 block text-sm font-medium text-ink">{{ __('browse.categories') }}</span>
            <div class="flex flex-wrap gap-2">
                @foreach ($tags as $tag)
                    <label class="inline-flex min-h-9 cursor-pointer items-center rounded-full border border-line bg-surface px-3 text-sm text-ink-muted transition hover:border-line-strong has-[:checked]:border-transparent has-[:checked]:bg-selected has-[:checked]:text-selected-ink">
                        <input type="checkbox" name="tag[]" value="{{ $tag }}" class="sr-only"
                            @checked(in_array($tag, (array) ($filters['tag'] ?? []), true))>
                        {{ __('browse.tags.'.$tag) }}
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Фасеты: провинция и цена. province — скаляр (одиночный выбор):
             имя-массив при одиночном select было рассинхроном. Бэк принимает и то, и другое. --}}
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="sr-only" for="province">{{ __('browse.province') }}</label>
                @php $selProvince = array_map('strval', (array) ($filters['province'] ?? [])); @endphp
                <select name="province" id="province" data-combobox
                        data-placeholder="{{ __('browse.province_search') }}"
                        data-nomatch="{{ __('browse.province_none') }}"
                        class="min-h-11 w-full rounded-md border border-line bg-surface p-2">
                    <option value="">{{ __('browse.province') }}: {{ __('browse.any') }}</option>
                    @foreach ($provinces as $province)
                        <option value="{{ $province->id }}"
                            @selected(in_array((string) $province->id, $selProvince, true))>
                            {{ $province->localizedName() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <input type="number" inputmode="numeric" name="price_min" value="{{ $filters['price_min'] ?? '' }}"
                       placeholder="{{ __('browse.price_from') }}"
                       class="min-h-11 w-full rounded-md border border-line bg-surface p-2">
                <input type="number" inputmode="numeric" name="price_max" value="{{ $filters['price_max'] ?? '' }}"
                       placeholder="{{ __('browse.price_to') }}"
                       class="min-h-11 w-full rounded-md border border-line bg-surface p-2">
            </div>
        </div>

        {{-- Состояние — сегмент из радио-пилюль (без JS, тот же GET-параметр condition). --}}
        <fieldset>
            <legend class="mb-1 text-sm font-medium text-ink">{{ __('browse.condition') }}</legend>
            <div class="flex flex-wrap gap-2">
                @php $curCond = (string) ($filters['condition'] ?? ''); @endphp
                @foreach (['' => __('browse.any'), 'new' => __('listing.conditions.new'), 'used' => __('listing.conditions.used')] as $val => $label)
                    <label class="inline-flex min-h-9 cursor-pointer items-center rounded-full border border-line bg-surface px-3 text-sm text-ink-muted transition hover:border-line-strong has-[:checked]:border-transparent has-[:checked]:bg-selected has-[:checked]:text-selected-ink">
                        <input type="radio" name="condition" value="{{ $val }}" class="sr-only"
                            @checked($curCond === (string) $val)>
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        {{-- Действия + сортировка. Sort — контрол результатов, а не фасет: держим
             его справа, отдельно от фильтров, но в той же форме (без JS, один submit). --}}
        <div class="flex flex-wrap items-center gap-3 border-t border-line pt-4">
            <button type="submit"
                    class="inline-flex min-h-11 items-center justify-center rounded-md bg-selected px-5 text-selected-ink">
                {{ __('browse.apply') }}
            </button>
            <a href="{{ route('home') }}"
               class="inline-flex min-h-11 items-center justify-center rounded-md border border-line px-5 text-ink-muted">
                {{ __('browse.reset') }}
            </a>

            <div class="ml-auto flex items-center gap-2">
                <label class="text-sm text-ink-subtle" for="sort">{{ __('browse.sort') }}</label>
                <select name="sort" id="sort" class="min-h-11 rounded-md border border-line bg-surface p-2 text-sm">
                    @foreach (['newest', 'price_asc', 'price_desc'] as $sort)
                        <option value="{{ $sort }}" @selected(($filters['sort'] ?? 'newest') === $sort)>
                            {{ __('browse.sorts.'.$sort) }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </form>

    <p class="mb-3 text-sm text-ink-muted">{{ __('browse.results', ['count' => $listings->total()]) }}</p>

    @forelse ($listings as $listing)
        @if ($loop->first)<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">@endif
            <x-listing-card :listing="$listing" />
        @if ($loop->last)</div>@endif
    @empty
        <div class="rounded-md border border-line bg-surface p-8 text-center">
            <p class="text-ink">{{ __('browse.empty') }}</p>
            <p class="mt-1 text-sm text-ink-subtle">{{ __('browse.empty_hint') }}</p>
        </div>
    @endforelse

    <div class="mt-6">{{ $listings->links() }}</div>
@endsection
