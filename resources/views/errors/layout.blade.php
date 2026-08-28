{{-- Общее тело страниц ошибок. Наследует layouts.app, поэтому на них есть
     шапка, дизайн-система и подвал с подписью студии — как на всех
     остальных страницах. Стандартные шаблоны Laravel этого не давали. --}}
@extends('layouts.app')
@section('title', $title)

@section('content')
    <div class="mx-auto max-w-xl py-12 text-center">
        <p class="font-mono text-5xl text-accent">{{ $code }}</p>
        <h1 class="mt-4 text-xl font-semibold text-ink">{{ $title }}</h1>
        <p class="mt-2 text-sm text-ink-muted">{{ $message }}</p>
        <a href="{{ route('home') }}"
           class="mt-6 inline-flex min-h-11 items-center justify-center rounded-md bg-selected px-5 text-selected-ink">
            {{ __('errors.back_home') }}
        </a>
    </div>
@endsection
