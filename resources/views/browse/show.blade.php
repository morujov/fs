@extends('layouts.app')
@section('title', $listing->formattedMsisdn())

@section('content')
    <div class="rounded-md border border-line bg-surface p-6">
        {{-- Товар — герой страницы. Виден целиком и всегда, инвариант №1. --}}
        <h1 class="font-mono text-3xl font-medium tracking-[0.12em] text-product sm:text-4xl">{{ $listing->formattedMsisdn() }}</h1>

        {{-- Паттерн-теги — знак ценности, латунь, сразу под номером. --}}
        @if (!empty($listing->pattern_tags))
            <div class="mt-3 flex flex-wrap gap-1">
                @foreach ($listing->pattern_tags as $tag)
                    <span class="rounded-full bg-accent-weak px-2 py-0.5 text-xs font-medium text-accent-strong">{{ __('browse.tags.'.$tag) }}</span>
                @endforeach
            </div>
        @endif

        <div class="mt-3 flex items-baseline gap-3">
            @if ($listing->is_negotiable)
                <span class="text-lg text-ink-muted">{{ __('browse.negotiable') }}</span>
            @else
                <span class="text-2xl font-semibold text-ink">{{ (int) $listing->price }} €</span>
            @endif

            <span class="rounded-full bg-shop-bg px-2 py-0.5 text-sm text-shop-ink">
                {{ $listing->shop_id ? __('browse.shop') : __('browse.private') }}
            </span>
        </div>

        <dl class="mt-5 grid gap-2 text-sm sm:grid-cols-2">
            <div><dt class="inline text-ink-subtle">{{ __('listing.attributes.operator_id') }}:</dt>
                 <dd class="inline text-ink">{{ $listing->operator?->name }}</dd></div>
            <div><dt class="inline text-ink-subtle">{{ __('listing.attributes.line_type') }}:</dt>
                 <dd class="inline text-ink">{{ __('listing.line_types.'.$listing->line_type) }}</dd></div>
            <div><dt class="inline text-ink-subtle">{{ __('listing.attributes.condition') }}:</dt>
                 <dd class="inline text-ink">{{ __('listing.conditions.'.$listing->condition) }}</dd></div>
            <div><dt class="inline text-ink-subtle">{{ __('browse.permanency') }}:</dt>
                 <dd class="inline text-ink">
                    @if ($listing->has_permanency)
                        {{ __('browse.permanency_con') }}
                        @if ($listing->permanency_until) ({{ $listing->permanency_until->format('m/Y') }}) @endif
                    @else
                        {{ __('browse.permanency_libre') }}
                    @endif
                 </dd></div>
            <div><dt class="inline text-ink-subtle">{{ __('listing.attributes.province_id') }}:</dt>
                 <dd class="inline text-ink">{{ $listing->province?->localizedName() }}{{ $listing->city ? ', '.$listing->city : '' }}</dd></div>
        </dl>

        @if ($listing->description)
            <p class="mt-5 whitespace-pre-line text-ink">{{ $listing->description }}</p>
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
        <div class="mt-6 rounded-md border border-line bg-sunken p-4" id="contact-box"
             data-url="{{ route('listings.contact', $listing) }}">

            {{-- Маска — намеренно «запертое поле», а НЕ размытие: иконка-замок
                 сообщает, что значение под гейтом. Полного контакта в DOM нет. --}}
            <div id="contact-masked">
                <div class="flex items-center gap-1.5 text-sm text-ink-muted">
                    <svg class="h-4 w-4 text-ink-subtle" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 1a4 4 0 00-4 4v2H5a2 2 0 00-2 2v7a2 2 0 002 2h10a2 2 0 002-2V9a2 2 0 00-2-2h-1V5a4 4 0 00-4-4zM8 5a2 2 0 114 0v2H8V5z" clip-rule="evenodd" />
                    </svg>
                    {{ $contact['name'] }}
                </div>
                <div class="mt-1 font-mono text-xl tracking-wider text-ink-subtle">{{ $contact['phone'] }}</div>
                @if ($contact['email'])
                    <div class="text-sm text-ink-subtle">{{ $contact['email'] }}</div>
                @endif
            </div>

            <div id="contact-full" class="hidden">
                <div class="text-sm text-ink" id="c-name"></div>
                <a class="font-mono text-xl text-accent-strong hover:underline" id="c-phone" href="#"></a>
                <div class="text-sm" id="c-email"></div>
                <a class="mt-2 inline-block rounded-md bg-green-600 px-3 py-1 text-sm text-white hidden"
                   id="c-whatsapp" target="_blank" rel="noopener">WhatsApp</a>
            </div>

            <p class="mt-2 text-sm text-danger hidden" id="contact-error"></p>

            @auth
                <button type="button" id="reveal-btn"
                        class="mt-3 inline-flex min-h-11 items-center justify-center rounded-md bg-selected px-5 text-selected-ink">
                    {{ __('reveal.show_contact') }}
                </button>
            @else
                <a href="{{ route('auth.google.redirect', ['intended' => request()->path()]) }}"
                   class="mt-3 inline-flex min-h-11 items-center justify-center rounded-md bg-selected px-5 text-selected-ink">
                    {{ __('reveal.sign_in_to_see') }}
                </a>
                {{-- Честно объясняем, зачем гейт: «зарегистрируйтесь» без
                     причины выглядит как выкачивание данных. --}}
                <p class="mt-2 text-xs text-ink-subtle">{{ __('reveal.why') }}</p>
            @endauth
        </div>

        {{-- Дисклеймер обязателен: номер юридически не собственность
             абонента, продаётся перенос линии. Блюпринт, раздел 0. --}}
        <p class="mt-6 text-xs text-ink-subtle">{{ __('listing.legal_notice') }}</p>
    </div>

    {{--
        ЖАЛОБА. Открыта гостю намеренно.

        Самая важная жалоба — «это мой номер, я его не продаю». Оставляет её
        человек, который сюда не заходил, аккаунта не имеет и заводить не
        станет: он узнал о нас из чужого звонка. Потребовать вход = не
        узнать о чужом номере никогда.

        Единственное исключение из гейта, и оно верное: здесь данные отдают,
        а не забирают.
    --}}
    <details class="mt-4 rounded-md border border-line bg-surface p-4">
        <summary class="cursor-pointer text-sm text-ink-muted">{{ __('report.title') }}</summary>

        <p class="mt-2 text-sm text-ink-muted">{{ __('report.intro') }}</p>

        <form method="POST" action="{{ route('listings.report', $listing) }}" class="mt-3 space-y-2">
            @csrf

            <select name="reason" class="min-h-11 w-full rounded-md border border-line bg-surface p-2">
                @foreach (['not_mine', 'fraud', 'wrong_info', 'spam', 'sold', 'other'] as $reason)
                    <option value="{{ $reason }}">{{ __('report.reasons.'.$reason) }}</option>
                @endforeach
            </select>
            @error('reason') <p class="text-sm text-danger">{{ $message }}</p> @enderror

            <textarea name="comment" rows="3" class="w-full rounded-md border border-line bg-surface p-2"
                      placeholder="{{ __('report.comment_help') }}">{{ old('comment') }}</textarea>
            @error('comment') <p class="text-sm text-danger">{{ $message }}</p> @enderror

            <input type="email" name="reporter_email" value="{{ old('reporter_email') }}"
                   class="min-h-11 w-full rounded-md border border-line bg-surface p-2" placeholder="{{ __('report.email_help') }}">
            @error('reporter_email') <p class="text-sm text-danger">{{ $message }}</p> @enderror

            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-md border border-line px-4 text-sm text-ink-muted">{{ __('report.submit') }}</button>
        </form>
    </details>

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
