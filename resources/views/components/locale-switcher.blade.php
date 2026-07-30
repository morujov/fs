{{-- Переключатель языка. Локали и эндонимы — из config numeros.locales,
     активная выделена и не кликабельна. Прячется, если язык всего один. --}}
@php
    $active  = config('numeros.locales.active', []);
    $names   = config('numeros.locales.names', []);
    $current = app()->getLocale();
@endphp

@if (count($active) > 1)
    <nav aria-label="{{ __('common.language') }}" class="flex items-center gap-2 text-sm">
        @foreach ($active as $loc)
            @unless ($loop->first)
                <span class="text-gray-300" aria-hidden="true">·</span>
            @endunless

            @if ($loc === $current)
                <span class="font-semibold" aria-current="true">{{ $names[$loc] ?? strtoupper($loc) }}</span>
            @else
                <a href="{{ route('locale.switch', $loc) }}" rel="nofollow"
                   class="text-gray-500 hover:text-gray-900">{{ $names[$loc] ?? strtoupper($loc) }}</a>
            @endif
        @endforeach
    </nav>
@endif
