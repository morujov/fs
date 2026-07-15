<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    /**
     * Изоляция глобального состояния между тестами.
     *
     * Всё это утечки в рамках одного процесса PHPUnit — в проде их нет, там
     * каждый HTTP-запрос поднимает приложение заново. Но тесты идут в одном
     * процессе, и состояние протекает из теста в тест, делая исход зависимым
     * от порядка (тот же класс бага, что «правило и данные врозь»).
     *
     * - Пагинатор: рендер таблиц Filament/Livewire подменяет глобальный
     *   дефолтный вид пагинатора и current-page resolver на свои (wire:click
     *   вместо ?page=N). После админских тестов ссылки витрины переставали
     *   содержать `page=2`, хотя сам пагинатор считал страницы верно.
     * - Кэш настроек: Setting::get кэширует rememberForever, а RefreshDatabase
     *   откатывает БД, но не кэш — значение из одного теста жило в следующем.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Paginator::defaultView('pagination::tailwind');
        Paginator::defaultSimpleView('pagination::simple-tailwind');
        Paginator::currentPageResolver(fn (string $pageName = 'page') => (int) request()->input($pageName, 1));

        Cache::flush();
    }
}
