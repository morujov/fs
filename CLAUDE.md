# CLAUDE.md — контекст проекта для Claude Code

## Что это

`numeros-es` — доска объявлений о продаже мобильных номеров в Испании.
**Не маркетплейс:** сделки идут вне сайта, деньги через площадку не проходят,
площадка не является стороной сделки.

Полное ТЗ, все архитектурные решения и их обоснования — в `BLUEPRINT-numeros-es.md`.
**Прочитай его перед любой нетривиальной задачей.** Там же список из 28 пробелов
исходного ТЗ и то, как каждый закрыт.

## Состояние

Готовы S0 и S1: скелет, схема, модели, сидеры, ядро wildcard-поиска.
Три коммита. Дальше по плану S2 (Google Sign-In + подача объявления + OTP).

**Важно:** весь код написан без единого запуска — в окружении, где его писали,
не было PHP. Он не проверен ничем, кроме портирования логики поиска на JS.
Первый `composer install && php artisan migrate --seed` вполне может упасть.
Это ожидаемо. Чини и иди дальше.

## Стек и версии

| | Локально | Bluehost (прод) |
|---|---|---|
| **Laravel** | **13.x** | 13.x |
| PHP | **8.3** | 8.3.32 |
| MySQL | 8.0 | **5.7.44** |
| Composer | 2.x | `/opt/cpanel/composer/bin/composer` |
| Node | есть | **нет** — ассеты собираются локально |

**Laravel 13, не 11.** Проект начинался на 11 — это была ошибка: security-поддержка
Laravel 11 закончилась 12 марта 2026. Composer 2.10 правильно отказывался его ставить
через advisory-блок. Если увидишь `policy.advisories.block false` в глобальном конфиге
Composer — это обход той ошибки, его нужно **откатить**:

```bash
composer config --global --unset policy.advisories.block
```

Laravel 13: PHP 8.3+, багфиксы до Q3 2027, безопасность до Q1 2028.

**PHP строго 8.3.** На машине есть Homebrew-PHP 8.5 — он должен быть отлинкован.
Прод на 8.3.32, расхождение версий нам не нужно.

**MySQL: прод на 5.7.** Ничего из MySQL 8: ни оконных функций, ни `CHECK`,
ни `SKIP LOCKED`. `JSON` и generated-колонки в 5.7 есть, их используем.

**Laravel 13 — что важно помнить:**
- CSRF-мидлвара переименована: `VerifyCsrfToken` → `PreventRequestForgery`,
  плюс проверка `Sec-Fetch-Site`. Пригодится на AJAX-эндпоинте раскрытия контактов (S4).
- `config/cache.php` → `serializable_classes => false`. Мы кэшируем только скаляры
  (`Setting::get`), так что менять нечего. Не ослаблять без причины.
- Tailwind 4: `tailwind.config.js` и `postcss.config.js` больше не используются,
  конфиг через `@tailwindcss/vite`.
- `symfony/polyfill-php85` определяет глобальные `array_first()`/`array_last()`.
  Использовать `Arr::first()`/`Arr::last()`, а не глобальные функции.

## Инварианты — не ломать

1. **`listings.msisdn` не маскируется никогда.** Это товар, витрина и весь SEO.
   Маскируется только контакт продавца (`contact_phone/name/email`).
2. **Полный контакт не попадает в HTML.** Ни разу, ни под каким CSS.
   Маска считается на сервере (`Listing::maskedContact()`), полное значение
   уходит только из AJAX-эндпоинта после проверки сессии.
   Рендерить полное значение и прятать `blur`/`opacity` — вскрывается Ctrl+U.
3. **`%` и `_` от пользователя вырезаются до построения LIKE.**
   Вся санитизация в `App\Services\Search\NumberPatternQuery` — whitelist `[0-9?]`.
   Один пропущенный `%` выгружает всю базу контактов одним запросом.
4. **Гейт только на раскрытии контакта.** Поиск, фильтры, листинг, карточка,
   цена — открыты анониму и Googlebot. Загейтить просмотр = убить SEO = убить проект.
5. **Авторизация только Google OAuth.** Паролей нет: ни колонки `password`,
   ни `password_reset_tokens`, ни `email_verified_at` (Google даёт email
   верифицированным). Не добавляй их обратно.
6. **OTP-SMS остаётся при Google-входе.** Google подтверждает личность,
   но не владение продаваемым номером. Это разные проверки.
7. **Ни одной пользовательской строки в коде.** Только `__('...')` и файлы
   в `lang/`. Сейчас `es`/`en`, потом `ca`/`gl`/`eu` — должны добавляться
   файлами, без правки кода.
8. **Изменяемые значения — в таблице `settings`, не в `config/`.**
   Пороги, TTL, лимиты, фича-флаги правятся из админки. На shared-хостинге
   деплой это ручной `git pull` в рвущемся терминале — туда за сменой цифры
   никто не полезет. В `config/numeros.php` только неизменяемые константы.

## Ключевые места

```
app/Services/Search/NumberPatternQuery.php  ← ядро поиска, вся санитизация
app/Services/Search/PatternTagger.php       ← repetido/capicua/escalera
app/Models/Listing.php                      ← маскировка контактов
config/numeros.php                          ← константы
database/migrations/..._create_listings_table.php
```

### Антидубль — почему так

Требование: один номер = одно активное объявление, но история неактивных нужна.

`UNIQUE(msisdn, status)` не годится — запретил бы и два `expired` на один номер.
Поэтому в `listings` есть generated-колонка:

```sql
active_msisdn CHAR(9) GENERATED ALWAYS AS (IF(status='active', msisdn, NULL)) STORED
UNIQUE INDEX uniq_active_msisdn (active_msisdn)
```

NULL-ы в UNIQUE не конфликтуют → «один активный номер один раз» при любом
количестве неактивных. Гарантия на уровне БД, без гонок. Не заменяй это
проверкой в коде.

### Wildcard-поиск

Пользовательский синтаксис: цифры и `?` (одна любая цифра).
`'6??12??34'` → `LIKE '6__12__34'`.

Производительность: префикс известен → индекс работает. Полностью открытая
маска → full scan, ~30–60 мс на 200k строк. Приемлемо. Пути отхода
(generated-колонки `d1..d9`, кэш в памяти) — в блюпринте, раздел 4.
Преждевременно не оптимизировать.

---

## Задача сейчас: апгрейд на Laravel 13

Ветка `upgrade-laravel-13` уже содержит файловую часть — её подготовили без
возможности запустить composer. Осталось выполнить и проверить.

**Что уже сделано в ветке:**
- `composer.json`: `laravel/framework ^13.8`, `laravel/tinker ^3.0`,
  `phpunit/phpunit ^12.5.12`, `php ^8.3`; убран `laravel/sail`
- Файлы скелета (`bootstrap/`, `config/*`, `phpunit.xml`, `package.json`,
  `vite.config.js`, `resources/`) заменены на версии из `laravel/laravel:13.x`
- Удалены `tailwind.config.js`, `postcss.config.js` (Tailwind 4 их не использует)
- Удалён `.github/` — это мейнтенерский CI самого laravel/laravel, не наш
- `config/services.php`: добавлен блок `google` для Socialite
- Проверено: `laravel/socialite ^5.x` объявляет `illuminate/contracts: ^13.0` — совместим

**Наши файлы намеренно НЕ трогали** — их нельзя перезаписывать скелетом:
`app/Models/User.php`, `database/factories/UserFactory.php`,
`database/seeders/DatabaseSeeder.php`,
`database/migrations/0001_01_01_000000_create_users_table.php`,
`.env.example`, `README.md`, `config/numeros.php`.

**Выполнить:**

```bash
# 1. Откатить обход advisory-блока — он был неправильным решением.
#    Composer говорил правду: Laravel 11 без security-поддержки.
composer config --global --unset policy.advisories.block

cd ~/Documents/numeros-es
git checkout upgrade-laravel-13

# 2. Обновить зависимости. Advisory-блок теперь ДОЛЖЕН молчать: Laravel 13
#    поддерживается. Если он снова ругается — не обходи, разберись и сообщи.
rm -rf vendor composer.lock
composer install

# 3. Проверить
php artisan migrate:fresh --seed
php artisan test
npm install && npm run build
```

Ожидаемо: 52 провинции, 14 операторов, ~487 объявлений, 0 дублей активных msisdn.

**Затем:** запушить ветку и открыть PR в `main` (после того как PR #1 вмёржен).

### Если падает

- `Specified key was too long` → `Schema::defaultStringLength(191)` в `AppServiceProvider::boot()`
- Ошибка на generated-колонке → нужна MySQL ≥ 5.7.6
- `requires php ^8.3 but your php version 8.5` → `brew unlink php && brew link --overwrite --force php@8.3`
- Socialite не резолвится → сообщи, не понижай Laravel обратно
- Vite/Tailwind 4 ругается на конфиг → остатки Tailwind 3, проверь что
  `tailwind.config.js` и `postcss.config.js` удалены

## Чего не делать

- **Не обходи advisory-блок Composer.** Если он сработал — это сигнал, а не помеха.
  Сообщи, что именно он говорит.
- **Не понижай Laravel обратно на 11 или 12.** 11 без поддержки с марта 2026,
  у 12 багфиксы кончаются 13 августа 2026.
- Не добавляй Filament — он в S5, сейчас потянет конфликты версий.
- Не трогай `BLUEPRINT-numeros-es.md` без явной просьбы: это источник правды
  по решениям, а не рабочий файл.
- Не переходи к S2, пока `migrate:fresh --seed` и `php artisan test` не пройдут чисто.
