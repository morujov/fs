@extends('layouts.app')
@section('title', $listing->formattedMsisdn())

@section('content')
    <div class="rounded border bg-white p-6">
        {{-- Товар. Виден целиком и всегда — инвариант №1. --}}
        <h1 class="font-mono text-3xl tracking-wide">{{ $listing->formattedMsisdn() }}</h1>

        <div class="mt-3 flex items-baseline gap-3">
            @if ($listing->is_negotiable)
                <span class="text-lg text-gray-600">{{ __('browse.negotiable') }}</span>
            @else
                <span class="text-2xl font-semibold">{{ (int) $listing->price }} €</span>
            @endif

            <span class="rounded bg-gray-100 px-2 py-0.5 text-sm">
                {{ $listing->shop_id ? __('browse.shop') : __('browse.private') }}
            </span>
        </div>

        <div class="mt-3 flex flex-wrap gap-1">
            @foreach ($listing->pattern_tags ?? [] as $tag)
                <span class="rounded bg-amber-50 px-2 py-0.5 text-xs text-amber-900">{{ __('browse.tags.'.$tag) }}</span>
            @endforeach
        </div>

        <dl class="mt-5 grid gap-2 text-sm sm:grid-cols-2">
            <div><dt class="inline text-gray-500">{{ __('listing.attributes.operator_id') }}:</dt>
                 <dd class="inline">{{ $listing->operator?->name }}</dd></div>
            <div><dt class="inline text-gray-500">{{ __('listing.attributes.line_type') }}:</dt>
                 <dd class="inline">{{ __('listing.line_types.'.$listing->line_type) }}</dd></div>
            <div><dt class="inline text-gray-500">{{ __('listing.attributes.condition') }}:</dt>
                 <dd class="inline">{{ __('listing.conditions.'.$listing->condition) }}</dd></div>
            <div><dt class="inline text-gray-500">{{ __('browse.permanency') }}:</dt>
                 <dd class="inline">
                    @if ($listing->has_permanency)
                        {{ __('browse.permanency_con') }}
                        @if ($listing->permanency_until) ({{ $listing->permanency_until->format('m/Y') }}) @endif
                    @else
                        {{ __('browse.permanency_libre') }}
                    @endif
                 </dd></div>
            <div><dt class="inline text-gray-500">{{ __('listing.attributes.province_id') }}:</dt>
                 <dd class="inline">{{ $listing->province?->localizedName() }}{{ $listing->city ? ', '.$listing->city : '' }}</dd></div>
        </dl>

        @if ($listing->description)
            <p class="mt-5 whitespace-pre-line text-gray-800">{{ $listing->description }}</p>
        @endif

        {{--
            КОНТАКТЫ.

            В разметку уходит ТОЛЬКО маска из $contact — она посчитана на
            сервере в Listing::maskedContact(). Полного значения здесь нет
            и быть не может: его отдаёт ContactRevealController после
            проверки сессии и лимитов.

            Инвариант №2. Отрендерить полное значение и спрятать его
            CSS-ом (blur/opacity/::before) — то, что делает половина досок
            объявлений, и это вскрывается через Ctrl+U за пять секунд.
        --}}
        <div class="mt-6 rounded border bg-gray-50 p-4" id="contact-box"
             data-url="{{ route('listings.contact', $listing) }}">

            <div id="contact-masked">
                <div class="text-sm text-gray-500">{{ $contact['name'] }}</div>
                <div class="font-mono text-xl text-gray-400">{{ $contact['phone'] }}</div>
                @if ($contact['email'])
                    <div class="text-sm text-gray-400">{{ $contact['email'] }}</div>
                @endif
            </div>

            <div id="contact-full" class="hidden">
                <div class="text-sm text-gray-700" id="c-name"></div>
                <a class="font-mono text-xl text-gray-900" id="c-phone" href="#"></a>
                <div class="text-sm" id="c-email"></div>
                <a class="mt-2 inline-block rounded bg-green-600 px-3 py-1 text-sm text-white hidden"
                   id="c-whatsapp" target="_blank" rel="noopener">WhatsApp</a>
            </div>

            <p class="mt-2 text-sm text-red-700 hidden" id="contact-error"></p>

            @auth
                <button type="button" id="reveal-btn"
                        class="mt-3 rounded bg-gray-900 px-4 py-2 text-white">
                    {{ __('reveal.show_contact') }}
                </button>
            @else
                <a href="{{ route('auth.google.redirect', ['intended' => request()->path()]) }}"
                   class="mt-3 inline-block rounded bg-gray-900 px-4 py-2 text-white">
                    {{ __('reveal.sign_in_to_see') }}
                </a>
                {{-- Честно объясняем, зачем гейт: «зарегистрируйтесь» без
                     причины выглядит как выкачивание данных. --}}
                <p class="mt-2 text-xs text-gray-500">{{ __('reveal.why') }}</p>
            @endauth
        </div>

        {{-- Дисклеймер обязателен: номер юридически не собственность
             абонента, продаётся перенос линии. Блюпринт, раздел 0. --}}
        <p class="mt-6 text-xs text-gray-500">{{ __('listing.legal_notice') }}</p>
    </div>

    @auth
        @push('scripts')
        <script>
        document.getElementById('reveal-btn')?.addEventListener('click', async (e) => {
            const btn = e.currentTarget;
            const box = document.getElementById('contact-box');
            const err = document.getElementById('contact-error');

            btn.disabled = true;
            btn.textContent = @json(__('reveal.loading'));
            err.classList.add('hidden');

            try {
                const res = await fetch(box.dataset.url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                });

                const data = await res.json();

                if (!res.ok) {
                    err.textContent = data.message || @json(__('reveal.errors.generic'));
                    err.classList.remove('hidden');
                    btn.disabled = false;
                    btn.textContent = @json(__('reveal.show_contact'));
                    return;
                }

                document.getElementById('c-name').textContent = data.name;

                const phone = document.getElementById('c-phone');
                phone.textContent = data.phone;
                phone.href = 'tel:' + data.phone.replace(/\s/g, '');

                const email = document.getElementById('c-email');
                if (data.email) {
                    email.innerHTML = '';
                    const a = document.createElement('a');
                    a.href = 'mailto:' + data.email;
                    a.textContent = data.email;
                    email.appendChild(a);
                }

                if (data.whatsapp) {
                    const wa = document.getElementById('c-whatsapp');
                    wa.href = data.whatsapp;
                    wa.classList.remove('hidden');
                }

                document.getElementById('contact-masked').classList.add('hidden');
                document.getElementById('contact-full').classList.remove('hidden');
                btn.remove();
            } catch {
                err.textContent = @json(__('reveal.errors.generic'));
                err.classList.remove('hidden');
                btn.disabled = false;
                btn.textContent = @json(__('reveal.show_contact'));
            }
        });
        </script>
        @endpush
    @endauth
@endsection
