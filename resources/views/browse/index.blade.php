@extends('layouts.app')
@section('title', __('browse.title'))

@section('content')
    <h1 class="mb-4 text-xl font-semibold">{{ __('browse.title') }}</h1>

    <form method="GET" action="{{ route('home') }}" class="mb-6 space-y-3">
        {{-- Маска номера. Значение уже санитизировано в контроллере:
             в поле не может вернуться ничего, кроме цифр и '?'. --}}
        <div>
            <label class="block text-sm font-medium" for="q">{{ __('browse.search') }}</label>
            <input type="text" name="q" id="q" value="{{ $pattern }}"
                   inputmode="numeric" maxlength="9" autocomplete="off"
                   placeholder="6??12??34"
                   class="w-full rounded border p-2 font-mono text-lg tracking-widest">
            <p class="text-xs text-gray-500">{{ __('browse.search_help') }}</p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <select name="province[]" class="rounded border p-2">
                <option value="">{{ __('browse.province') }}: {{ __('browse.any') }}</option>
                @foreach ($provinces as $province)
                    <option value="{{ $province->id }}"
                        @selected(in_array((string) $province->id, (array) ($filters['province'] ?? []), true))>
                        {{ $province->localizedName() }}
                    </option>
                @endforeach
            </select>

            <select name="operator[]" class="rounded border p-2">
                <option value="">{{ __('browse.operator') }}: {{ __('browse.any') }}</option>
                @foreach ($operators as $operator)
                    <option value="{{ $operator->id }}"
                        @selected(in_array((string) $operator->id, (array) ($filters['operator'] ?? []), true))>
                        {{ $operator->name }}
                    </option>
                @endforeach
            </select>

            <input type="number" name="price_min" value="{{ $filters['price_min'] ?? '' }}"
                   placeholder="{{ __('browse.price_from') }}" class="rounded border p-2">
            <input type="number" name="price_max" value="{{ $filters['price_max'] ?? '' }}"
                   placeholder="{{ __('browse.price_to') }}" class="rounded border p-2">

            <select name="condition" class="rounded border p-2">
                <option value="">{{ __('browse.condition') }}: {{ __('browse.any') }}</option>
                @foreach (['new', 'used'] as $condition)
                    <option value="{{ $condition }}" @selected(($filters['condition'] ?? null) === $condition)>
                        {{ __('listing.conditions.'.$condition) }}
                    </option>
                @endforeach
            </select>

            <select name="line_type" class="rounded border p-2">
                <option value="">{{ __('browse.line_type') }}: {{ __('browse.any') }}</option>
                @foreach (['prepago', 'contrato'] as $type)
                    <option value="{{ $type }}" @selected(($filters['line_type'] ?? null) === $type)>
                        {{ __('listing.line_types.'.$type) }}
                    </option>
                @endforeach
            </select>

            <select name="permanency" class="rounded border p-2">
                <option value="">{{ __('browse.permanency') }}: {{ __('browse.any') }}</option>
                <option value="libre" @selected(($filters['permanency'] ?? null) === 'libre')>{{ __('browse.permanency_libre') }}</option>
                <option value="con" @selected(($filters['permanency'] ?? null) === 'con')>{{ __('browse.permanency_con') }}</option>
            </select>

            <select name="sort" class="rounded border p-2">
                @foreach (['newest', 'price_asc', 'price_desc'] as $sort)
                    <option value="{{ $sort }}" @selected(($filters['sort'] ?? 'newest') === $sort)>
                        {{ __('browse.sorts.'.$sort) }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Категории «красоты» — они же SEO-посадочные --}}
        <div class="flex flex-wrap gap-2">
            @foreach ($tags as $tag)
                <label class="cursor-pointer rounded border px-2 py-1 text-sm has-[:checked]:bg-gray-900 has-[:checked]:text-white">
                    <input type="checkbox" name="tag[]" value="{{ $tag }}" class="sr-only"
                        @checked(in_array($tag, (array) ($filters['tag'] ?? []), true))>
                    {{ __('browse.tags.'.$tag) }}
                </label>
            @endforeach
        </div>

        <div class="flex gap-2">
            <button type="submit" class="rounded bg-gray-900 px-4 py-2 text-white">{{ __('browse.apply') }}</button>
            <a href="{{ route('home') }}" class="rounded border px-4 py-2">{{ __('browse.reset') }}</a>
        </div>
    </form>

    <p class="mb-3 text-sm text-gray-600">{{ __('browse.results', ['count' => $listings->total()]) }}</p>

    @forelse ($listings as $listing)
        @if ($loop->first)<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">@endif
            <x-listing-card :listing="$listing" />
        @if ($loop->last)</div>@endif
    @empty
        <div class="rounded border bg-white p-8 text-center">
            <p class="text-gray-700">{{ __('browse.empty') }}</p>
            <p class="mt-1 text-sm text-gray-500">{{ __('browse.empty_hint') }}</p>
        </div>
    @endforelse

    <div class="mt-6">{{ $listings->links() }}</div>
@endsection
