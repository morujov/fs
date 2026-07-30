@extends('layouts.app')
@section('title', __('gdpr.title'))

@section('content')
    <h1 class="mb-2 text-xl font-semibold">{{ __('gdpr.title') }}</h1>
    <p class="mb-6 text-gray-600">{{ __('gdpr.intro') }}</p>

    {{-- Что храним — цифрами, без прикрас --}}
    <section class="mb-6 rounded border bg-white p-4">
        <h2 class="mb-2 font-medium">{{ __('gdpr.stored_title') }}</h2>
        <dl class="grid gap-1 text-sm sm:grid-cols-2">
            @foreach ($preview as $key => $count)
                <div>
                    <dt class="inline text-gray-500">{{ __('gdpr.stored.'.$key) }}:</dt>
                    <dd class="inline font-medium">{{ $count }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- Ст. 15 и 20 --}}
    <section class="mb-6 rounded border bg-white p-4">
        <h2 class="mb-1 font-medium">{{ __('gdpr.export_title') }}</h2>
        <p class="mb-3 text-sm text-gray-600">{{ __('gdpr.export_help') }}</p>
        <a href="{{ route('account.privacy.export') }}" class="rounded border px-3 py-2 text-sm">
            {{ __('gdpr.export_button') }}
        </a>
    </section>

    {{--
        Ст. 17.

        Честность ДО кнопки. «Мы всё удалим», а потом «ну кроме вот этого» —
        хуже, чем сразу объяснить, что останется и почему.
    --}}
    <section class="rounded border border-red-200 bg-white p-4">
        <h2 class="mb-1 font-medium text-red-900">{{ __('gdpr.delete_title') }}</h2>
        <p class="mb-2 text-sm text-gray-700">{{ __('gdpr.delete_help') }}</p>

        <ul class="mb-3 list-disc pl-5 text-sm text-gray-700">
            @foreach (['account', 'listings', 'personal'] as $item)
                <li>{{ __('gdpr.delete_what.'.$item) }}</li>
            @endforeach
        </ul>

        <p class="mb-1 text-sm font-medium text-gray-800">{{ __('gdpr.delete_kept_title') }}</p>
        <ul class="mb-3 list-disc pl-5 text-sm text-gray-600">
            @foreach (['listings', 'reports'] as $item)
                <li>{{ __('gdpr.delete_kept.'.$item) }}</li>
            @endforeach
        </ul>

        <p class="mb-3 text-sm text-gray-600">{{ __('gdpr.delete_can_return') }}</p>

        <form method="POST" action="{{ route('account.privacy.destroy') }}">
            @csrf
            @method('DELETE')

            <label class="block text-sm" for="confirm">{{ __('gdpr.confirm_label') }}</label>
            <input type="text" name="confirm" id="confirm" autocomplete="off"
                   class="mb-2 rounded border p-2 font-mono">
            @error('confirm') <p class="mb-2 text-sm text-red-700">{{ $message }}</p> @enderror

            <button type="submit" class="rounded bg-red-700 px-4 py-2 text-sm text-white">
                {{ __('gdpr.delete_button') }}
            </button>
        </form>
    </section>
@endsection
