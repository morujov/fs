@extends('layouts.app')
@section('title', __('listing.create_title'))

@section('content')
    <h1 class="mb-3 text-xl font-semibold">{{ __('listing.create_title') }}</h1>

    {{-- Дисклеймер обязателен: номер юридически не собственность абонента,
         продаётся перенос линии. Блюпринт, раздел 0. --}}
    <p class="mb-5 rounded-md bg-accent-weak p-3 text-sm text-accent-strong">{{ __('listing.legal_notice') }}</p>

    <form method="POST" action="{{ route('seller.listings.store') }}" class="space-y-5">
        @csrf

        {{-- Группа: номер и цена — то, что решает покупатель в первую очередь. --}}
        <fieldset class="space-y-4 rounded-md border border-line bg-surface p-4">
            <legend class="px-1 text-sm font-semibold text-ink">{{ __('listing.groups.number_price') }}</legend>

            <div>
                <label class="mb-1 block text-sm font-medium text-ink" for="msisdn">{{ __('listing.attributes.msisdn') }}</label>
                <input type="text" name="msisdn" id="msisdn" value="{{ old('msisdn') }}" inputmode="numeric"
                       class="min-h-11 w-full rounded-md border border-line bg-surface p-2 font-mono">
                <p class="mt-1 text-xs text-ink-subtle">{{ __('listing.help.msisdn') }}</p>
                {{-- OTP объясняем ДО сабмита: код на этот номер — ключевой момент
                     доверия, и человек не должен узнать о нём внезапно. --}}
                <p class="mt-1.5 flex items-start gap-1.5 rounded bg-sunken px-2 py-1.5 text-xs text-ink-muted">
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-accent" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.84 8.84 0 01-2.62-.393L3 18l1.393-3.38A6.9 6.9 0 012 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
                    </svg>
                    {{ __('listing.help.msisdn_otp') }}
                </p>
                @error('msisdn') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-ink" for="price">{{ __('listing.attributes.price') }} (€)</label>
                <input type="number" name="price" id="price" value="{{ old('price') }}" min="{{ $priceMin }}" max="{{ $priceMax }}"
                       class="min-h-11 w-full rounded-md border border-line bg-surface p-2">
                <label class="mt-2 flex items-center gap-2 text-sm text-ink-muted">
                    <input type="checkbox" name="is_negotiable" value="1" @checked(old('is_negotiable'))>
                    {{ __('listing.help.negotiable') }}
                </label>
                @error('price') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>
        </fieldset>

        {{-- Группа: линия — оператор, тип, состояние, permanencia. --}}
        <fieldset class="space-y-4 rounded-md border border-line bg-surface p-4">
            <legend class="px-1 text-sm font-semibold text-ink">{{ __('listing.groups.line') }}</legend>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink" for="operator_id">{{ __('listing.attributes.operator_id') }}</label>
                    <select name="operator_id" id="operator_id" class="min-h-11 w-full rounded-md border border-line bg-surface p-2">
                        @foreach ($operators as $operator)
                            <option value="{{ $operator->id }}" @selected(old('operator_id') == $operator->id)>
                                {{ $operator->name }}@if ($operator->is_mvno) ({{ $operator->host_network }})@endif
                            </option>
                        @endforeach
                    </select>
                    @error('operator_id') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-ink" for="line_type">{{ __('listing.attributes.line_type') }}</label>
                    <select name="line_type" id="line_type" class="min-h-11 w-full rounded-md border border-line bg-surface p-2">
                        @foreach (['prepago', 'contrato'] as $type)
                            <option value="{{ $type }}" @selected(old('line_type') === $type)>
                                {{ __('listing.line_types.'.$type) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Состояние — сегмент-пилюли (как на витрине). Подсказка одной строкой:
                 «новый/использованный» для номера двусмысленно (пробел №12). --}}
            <fieldset>
                <legend class="mb-1 text-sm font-medium text-ink">{{ __('listing.attributes.condition') }}</legend>
                @php $curCond = old('condition', 'used'); @endphp
                <div class="flex flex-wrap gap-2">
                    @foreach (['new', 'used'] as $condition)
                        <label class="inline-flex min-h-9 cursor-pointer items-center rounded-full border border-line bg-surface px-3 text-sm text-ink-muted transition hover:border-line-strong has-[:checked]:border-transparent has-[:checked]:bg-selected has-[:checked]:text-selected-ink">
                            <input type="radio" name="condition" value="{{ $condition }}" class="sr-only" @checked($curCond === $condition)>
                            {{ __('listing.conditions.'.$condition) }}
                        </label>
                    @endforeach
                </div>
                <p class="mt-1 text-xs text-ink-subtle">{{ __('listing.help.condition_new') }} · {{ __('listing.help.condition_used') }}</p>
            </fieldset>

            <div>
                <label class="flex items-center gap-2 text-sm text-ink-muted">
                    <input type="checkbox" name="has_permanency" value="1" @checked(old('has_permanency'))>
                    {{ __('listing.help.permanency') }}
                </label>
                <input type="date" name="permanency_until" value="{{ old('permanency_until') }}"
                       class="mt-2 min-h-11 rounded-md border border-line bg-surface p-2">
                @error('permanency_until') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>
        </fieldset>

        {{-- Группа: где. --}}
        <fieldset class="rounded-md border border-line bg-surface p-4">
            <legend class="px-1 text-sm font-semibold text-ink">{{ __('listing.groups.location') }}</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink" for="province_id">{{ __('listing.attributes.province_id') }}</label>
                    <select name="province_id" id="province_id" data-combobox
                            data-placeholder="{{ __('browse.province_search') }}"
                            data-nomatch="{{ __('browse.province_none') }}"
                            class="min-h-11 w-full rounded-md border border-line bg-surface p-2">
                        @foreach ($provinces as $province)
                            <option value="{{ $province->id }}" @selected(old('province_id') == $province->id)>
                                {{ $province->localizedName() }}
                            </option>
                        @endforeach
                    </select>
                    @error('province_id') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-ink" for="city">{{ __('listing.attributes.city') }}</label>
                    <input type="text" name="city" id="city" value="{{ old('city') }}"
                           class="min-h-11 w-full rounded-md border border-line bg-surface p-2">
                </div>
            </div>
        </fieldset>

        {{-- Комментарии. --}}
        <div>
            <label class="mb-1 block text-sm font-medium text-ink" for="description">{{ __('listing.attributes.description') }}</label>
            <textarea name="description" id="description" rows="3"
                      class="w-full rounded-md border border-line bg-surface p-2">{{ old('description') }}</textarea>
            @error('description') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
        </div>

        {{-- Группа: контакты. Имя и email префиллим из Google-профиля (можно
             изменить) — меньше полей руками. Телефон продавца — не тот номер,
             что продаётся, и покупателю показывается замаскированным. --}}
        <fieldset class="space-y-2 rounded-md border border-line bg-surface p-4">
            <legend class="px-1 text-sm font-semibold text-ink">{{ __('listing.groups.contact') }}</legend>

            <div>
                <label class="mb-1 block text-sm font-medium text-ink" for="contact_name">{{ __('listing.attributes.contact_name') }}</label>
                <input type="text" name="contact_name" id="contact_name" value="{{ old('contact_name', auth()->user()->name) }}"
                       class="min-h-11 w-full rounded-md border border-line bg-surface p-2">
                @error('contact_name') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-ink" for="contact_phone">{{ __('listing.attributes.contact_phone') }}</label>
                <input type="text" name="contact_phone" id="contact_phone" value="{{ old('contact_phone') }}"
                       class="min-h-11 w-full rounded-md border border-line bg-surface p-2">
                <p class="mt-1 text-xs text-ink-subtle">{{ __('listing.help.contact_phone') }}</p>
                @error('contact_phone') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-ink" for="contact_email">{{ __('listing.attributes.contact_email') }}</label>
                <input type="email" name="contact_email" id="contact_email" value="{{ old('contact_email', auth()->user()->email) }}"
                       class="min-h-11 w-full rounded-md border border-line bg-surface p-2">
                @error('contact_email') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-ink-muted">
                <input type="checkbox" name="contact_whatsapp" value="1" @checked(old('contact_whatsapp', true))>
                {{ __('listing.attributes.contact_whatsapp') }}
            </label>
        </fieldset>

        {{-- Тип продавца (п.10.8 ТЗ). Префилл из профиля, сегмент-пилюли. --}}
        <fieldset class="rounded-md border border-line bg-surface p-4">
            <legend class="px-1 text-sm font-semibold text-ink">{{ __('listing.groups.seller') }}</legend>
            <div class="flex flex-wrap gap-2">
                @php $curSeller = old('seller_type', auth()->user()->seller_type ?? 'private'); @endphp
                @foreach (['private', 'shop'] as $type)
                    <label class="inline-flex min-h-9 cursor-pointer items-center rounded-full border border-line bg-surface px-3 text-sm text-ink-muted transition hover:border-line-strong has-[:checked]:border-transparent has-[:checked]:bg-selected has-[:checked]:text-selected-ink">
                        <input type="radio" name="seller_type" value="{{ $type }}" class="sr-only" @checked($curSeller === $type)>
                        {{ __('listing.seller_types.'.$type) }}
                    </label>
                @endforeach
            </div>
            @error('seller_type') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
        </fieldset>

        <button type="submit"
                class="inline-flex min-h-11 items-center justify-center rounded-md bg-selected px-5 text-selected-ink">
            {{ __('listing.submit') }}
        </button>
    </form>
@endsection
