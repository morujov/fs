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

        $this->guardAgainstNonTestingDatabase();

        Paginator::defaultView('pagination::tailwind');
        Paginator::defaultSimpleView('pagination::simple-tailwind');
        Paginator::currentPageResolver(fn (string $pageName = 'page') => (int) request()->input($pageName, 1));

        Cache::flush();
    }

    /**
     * Предохранитель: тесты идут только по базе, чьё имя кончается на `_testing`.
     *
     * `RefreshDatabase` делает `migrate:fresh` — то есть дропает все таблицы.
     * Если конфиг закэширован (`php artisan config:cache`, а на сервере он
     * закэширован всегда), Laravel читает `bootstrap/cache/config.php` и
     * НЕ смотрит на `<env>` из phpunit.xml: `DB_DATABASE=numeros_es_testing`
     * молча игнорируется, и прогон уходит в боевую базу. Один `php artisan test`
     * на сервере — и данные клиента снесены.
     *
     * Поэтому проверяем не переменную окружения, а фактическое имя базы,
     * с которым подключилось приложение. Лечится `php artisan config:clear`.
     */
    private function guardAgainstNonTestingDatabase(): void
    {
        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if (! str_ends_with($database, '_testing')) {
            throw new \RuntimeException(
                "Тесты остановлены: подключение ведёт в базу «{$database}», а не в *_testing. "
                .'Почти наверняка закэширован конфиг — выполни `php artisan config:clear`, '
                .'прогони тесты и верни кэш через `php artisan config:cache`.'
            );
        }
    }
}
