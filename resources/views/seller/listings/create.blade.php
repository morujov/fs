@extends('layouts.app')
@section('title', __('listing.create_title'))

@section('content')
    <h1 class="mb-3 text-xl font-semibold">{{ __('listing.create_title') }}</h1>

    {{-- Дисклеймер обязателен: номер юридически не собственность абонента,
         продаётся перенос линии. Блюпринт, раздел 0. --}}
    <p class="mb-4 rounded bg-amber-50 p-3 text-sm text-amber-900">{{ __('listing.legal_notice') }}</p>

    <form method="POST" action="{{ route('seller.listings.store') }}" class="space-y-4">
        @csrf

        {{-- Продаваемый номер --}}
        <div>
            <label class="block text-sm font-medium">{{ __('listing.attributes.msisdn') }}</label>
            <input type="text" name="msisdn" value="{{ old('msisdn') }}" inputmode="numeric"
                   class="w-full rounded border p-2 font-mono">
            <p class="text-xs text-gray-500">{{ __('listing.help.msisdn') }}</p>
            @error('msisdn') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        {{-- Цена --}}
        <div>
            <label class="block text-sm font-medium">{{ __('listing.attributes.price') }} (€)</label>
            <input type="number" name="price" value="{{ old('price') }}" min="{{ $priceMin }}" max="{{ $priceMax }}"
                   class="w-full rounded border p-2">
            <label class="mt-1 flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_negotiable" value="1" @checked(old('is_negotiable'))>
                {{ __('listing.help.negotiable') }}
            </label>
            @error('price') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        {{-- Оператор и тип линии: без них объявление бесполезно покупателю --}}
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium">{{ __('listing.attributes.operator_id') }}</label>
                <select name="operator_id" class="w-full rounded border p-2">
                    @foreach ($operators as $operator)
                        <option value="{{ $operator->id }}" @selected(old('operator_id') == $operator->id)>
                            {{ $operator->name }}@if ($operator->is_mvno) ({{ $operator->host_network }})@endif
                        </option>
                    @endforeach
                </select>
                @error('operator_id') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">{{ __('listing.attributes.line_type') }}</label>
                <select name="line_type" class="w-full rounded border p-2">
                    @foreach (['prepago', 'contrato'] as $type)
                        <option value="{{ $type }}" @selected(old('line_type') === $type)>
                            {{ __('listing.line_types.'.$type) }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Permanencia --}}
        <div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="has_permanency" value="1" @checked(old('has_permanency'))>
                {{ __('listing.help.permanency') }}
            </label>
            <input type="date" name="permanency_until" value="{{ old('permanency_until') }}"
                   class="mt-1 rounded border p-2">
            @error('permanency_until') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        {{-- Состояние: подсказка обязательна, иначе заполнят как попало --}}
        <div>
            <label class="block text-sm font-medium">{{ __('listing.attributes.condition') }}</label>
            @foreach (['new', 'used'] as $condition)
                <label class="mr-4 text-sm">
                    <input type="radio" name="condition" value="{{ $condition }}" @checked(old('condition', 'used') === $condition)>
                    {{ __('listing.conditions.'.$condition) }}
                    <span class="text-xs text-gray-500">— {{ __('listing.help.condition_'.$condition) }}</span>
                </label>
            @endforeach
        </div>

        {{-- География --}}
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium">{{ __('listing.attributes.province_id') }}</label>
                <select name="province_id" class="w-full rounded border p-2">
                    @foreach ($provinces as $province)
                        <option value="{{ $province->id }}" @selected(old('province_id') == $province->id)>
                            {{ $province->localizedName() }}
                        </option>
                    @endforeach
                </select>
                @error('province_id') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">{{ __('listing.attributes.city') }}</label>
                <input type="text" name="city" value="{{ old('city') }}" class="w-full rounded border p-2">
            </div>
        </div>

        {{-- Комментарии --}}
        <div>
            <label class="block text-sm font-medium">{{ __('listing.attributes.description') }}</label>
            <textarea name="description" rows="3" class="w-full rounded border p-2">{{ old('description') }}</textarea>
            @error('description') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        {{-- Контакты продавца --}}
        <fieldset class="rounded border p-3">
            <legend class="px-1 text-sm font-medium">{{ __('listing.attributes.contact_name') }}</legend>

            <input type="text" name="contact_name" value="{{ old('contact_name') }}"
                   placeholder="{{ __('listing.attributes.contact_name') }}" class="mb-2 w-full rounded border p-2">
            @error('contact_name') <p class="text-sm text-red-700">{{ $message }}</p> @enderror

            <input type="text" name="contact_phone" value="{{ old('contact_phone') }}"
                   placeholder="{{ __('listing.attributes.contact_phone') }}" class="w-full rounded border p-2">
            <p class="mb-2 text-xs text-gray-500">{{ __('listing.help.contact_phone') }}</p>
            @error('contact_phone') <p class="text-sm text-red-700">{{ $message }}</p> @enderror

            <input type="email" name="contact_email" value="{{ old('contact_email') }}"
                   placeholder="{{ __('listing.attributes.contact_email') }}" class="mb-2 w-full rounded border p-2">
            @error('contact_email') <p class="text-sm text-red-700">{{ $message }}</p> @enderror

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="contact_whatsapp" value="1" @checked(old('contact_whatsapp', true))>
                {{ __('listing.attributes.contact_whatsapp') }}
            </label>
        </fieldset>

        {{-- Тип продавца (п.10.8 ТЗ) --}}
        <div>
            <label class="block text-sm font-medium">{{ __('listing.attributes.seller_type') }}</label>
            @foreach (['private', 'shop'] as $type)
                <label class="mr-4 text-sm">
                    <input type="radio" name="seller_type" value="{{ $type }}"
                           @checked(old('seller_type', auth()->user()->seller_type ?? 'private') === $type)>
                    {{ __('listing.seller_types.'.$type) }}
                </label>
            @endforeach
            @error('seller_type') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="rounded bg-gray-900 px-4 py-2 text-white">{{ __('listing.submit') }}</button>
    </form>
@endsection
