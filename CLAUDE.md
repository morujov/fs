# CLAUDE.md — контекст проекта для Claude Code

## Что это

`numeros-es` — доска объявлений о продаже мобильных номеров в Испании.
**Не маркетплейс:** сделки идут вне сайта, деньги через площадку не проходят,
площадка не является стороной сделки.

Полное ТЗ, все архитектурные решения и их обоснования — в `BLUEPRINT-numeros-es.md`.
**Прочитай его перед любой нетривиальной задачей.** Там же список из 28 пробелов
исходного ТЗ и то, как каждый закрыт.

## Состояние

S0, S1 готовы и проверены (Laravel 13.20.0, 106 тестов зелёные, ~487 демо).
S2 написан, но **ещё не запускался** — ветка `s2-auth-listing-otp`.
Дальше S3 (конвейер модерации).

**Важно про происхождение кода.** Он пишется в окружении, где нет PHP, и
проверяется уже здесь. Поэтому свежие правки могут падать на первом прогоне —
это ожидаемо, чини и иди дальше. Найденные так баги стоит понимать как класс,
а не как случайность: сидер просил 15 «репетидо», которых существует ровно 2;
валидатор пропускал 70X, которые сам же блок-лист запрещал; тесты гонялись на
SQLite, где миграция физически не проходит. Общее у них одно — правило и данные
жили в разных местах и разошлись.

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
   Вся санитизация в `App\Services\Search\NumberPatternQuery` — whitelist `[0-9?]`,
   **в одном месте**. Не копируй её в скоупы и FormRequest'ы «для надёжности»:
   два экземпляра защиты означают, что однажды поправят один и забудут второй.
   Покрыто `tests/Unit/NumberPatternQueryTest.php` — если правишь этот класс,
   тест обязан остаться зелёным.

4. **Гейт только на раскрытии контакта.** Поиск, фильтры, листинг, карточка,
   цена — открыты анониму и Googlebot. Загейтить просмотр = убить SEO = убить проект.
5. **Авторизация только Google OAuth.** Паролей нет: ни колонки `password`,
   ни `password_reset_tokens`, ни `email_verified_at` (Google даёт email
   верифицированным). Не добавляй их обратно.
   Scopes строго `openid email profile` — «базовый вход». Он не требует
   аудита Google и публичной политики конфиденциальности на этапе Testing.
   Любой лишний scope ломает и то, и другое.
6. **OTP-SMS остаётся при Google-входе.** Google подтверждает личность,
   но не владение продаваемым номером. Это разные проверки.
7. **Ни одной пользовательской строки в коде.** Только `__('...')` и файлы
   в `lang/`. Сейчас `es`/`en`, потом `ca`/`gl`/`eu` — должны добавляться
   файлами, без правки кода.
8. **Изменяемые значения — в таблице `settings`, не в `config/`.**
   Пороги, TTL, лимиты, фича-флаги правятся из админки. На shared-хостинге
   деплой это ручной `git pull` в рвущемся терминале — туда за сменой цифры
   никто не полезет. В `config/numeros.php` только неизменяемые константы.
9. **План нумерации — в БД, а не в коде.**
   Какие префиксы мобильные и продаются — в таблице `numbering_ranges`,
   читается через `App\Services\Search\NumberingPlan`. Матчинг по самому
   длинному префиксу: `70` перебивает `7`. Новый диапазон = одна строка,
   без деплоя. **Не возвращай в код регулярку вида `^[67]\d{8}$`** — CNMC
   двигает диапазоны без нас (6XX открыли, когда кончился 9XX; 71–74 в 2010;
   75–79 ждут очереди), а деплой здесь — ручной `git pull` в рвущемся терминале.

   Разделение обязательно: `NumberPatternQuery` — синтаксис (чистый, без БД,
   мгновенные юнит-тесты защиты от `%`). `NumberingPlan` — политика (БД + кэш,
   fail-closed). Смешать их значит утащить БД в тесты санитизации.

10. **На хостинге живёт чужой боевой сайт — cac.az.**
    Тот же Unix-юзер `clsthmmy`. cac.az принимает платежи (Epoint, PashaBank),
    его docroot — `public_html`. Аккаунт **уже упирается в лимит процессов**
    (`fork: Resource temporarily unavailable` при обычном входе в терминал).

    - **`public_html` не трогать ни одним файлом.** Это docroot cac.az.
    - **Никаких поддоменов cac.az**: `test.cac.az` занят редиректом,
      HSTS `includeSubDomains` действует год. Только addon-домен.
    - **Composer и npm на хосте не запускать.** Собираем локально, на хост
      едет тарбол. Это не удобство — это чтобы не уронить платёжный сайт
      пиком процессов.
    - Отдельная база и отдельный MySQL-юзер. Наш `.env` не должен иметь
      доступа к базе cac.az.

    Подробности — блюпринт, раздел 8.

## Ключевые места

```
app/Services/Search/NumberPatternQuery.php  ← синтаксис: санитизация, LIKE. Чистый, без БД
app/Services/Search/NumberingPlan.php       ← политика: план нумерации из БД + кэш
app/Services/Search/PatternTagger.php       ← repetido/capicua/escalera
app/Models/Listing.php                      ← маскировка контактов
config/numeros.php                          ← только неизменяемые константы
database/migrations/..._create_numbering_ranges_table.php
database/migrations/..._create_listings_table.php
```

## Тесты

```bash
# Нужна отдельная база — Feature-тесты идут на MySQL, не на SQLite
mysql -u root -h 127.0.0.1 -e "CREATE DATABASE IF NOT EXISTS numeros_es_testing
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

php artisan test
```

**Почему MySQL, а не SQLite.** Схема `listings` держится на генерируемой
колонке `active_msisdn ... GENERATED ALWAYS AS (IF(...))` — это MySQL-синтаксис,
`IF()` в SQLite нет, миграция там не пройдёт. Раньше в `phpunit.xml` стоял
SQLite и это не всплывало: единственный feature-тест был «главная отдаёт 200»,
БД он не трогал, и миграции под SQLite ни разу не запускались.

Что покрыто:

| Файл | Что защищает |
|---|---|
| `Unit/NumberPatternQueryTest` | **Санитизация LIKE.** Один пропущенный `%` = выгрузка всей базы контактов |
| `Unit/PatternTaggerTest` | Теги = навигация и SEO-посадочные |
| `Unit/ListingMaskingTest` | Полный контакт не утекает в HTML; `msisdn` не маскируется |
| `Feature/NumberingPlanTest` | Новый диапазон работает строкой в БД, без правки кода |
| `Feature/ListingUniquenessTest` | «Один номер — одно активное объявление» на уровне БД |

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

## Задача сейчас: прогнать S2

Ветка `s2-auth-listing-otp`, поверх `upgrade-laravel-13` (PR #2).
Код написан без запуска — падения на первом прогоне ожидаемы.

**Что в S2:**
- Google Sign-In: `GoogleAuthController`, роуты, поиск по `google_id` (не по email)
- SMS-слой: `SmsSenderInterface` + `LogSmsSender` (коды в лог) + заготовка LabsMobile.
  `SmsManager` не даст использовать драйвер `log` в проде.
- `OtpService`: выпуск/проверка, хэш кода, TTL, лимиты попыток и отправок из settings
- Подача: `StoreListingRequest` + правило `SellableMsisdn` (через `NumberingPlan`),
  `ListingController`, `OtpController`. Объявление доходит только до `pending`.
- `lang/es`, `lang/en` — инвариант №7, ни одной строки в коде
- Черновые Blade-вьюхи. Вёрстка — в S4 вместе с витриной, сейчас не тратить на неё время.
- 3 тестовых файла: `GoogleAuthTest`, `OtpServiceTest`, `ListingSubmissionTest`

**Выполнить:**

```bash
cd ~/Documents/numeros-es
git checkout s2-auth-listing-otp
composer install          # добавился laravel/socialite
php artisan migrate:fresh --seed
php artisan test
```

Тесты Socialite мокают, поэтому **ключи Google для прогона не нужны**.
Для ручной проверки в браузере — нужны, см. ниже.

### Если падает

- `Target [SmsSenderInterface] is not instantiable` → биндинг в `AppServiceProvider::register`
- `Socialite driver [google] not found` → нет `laravel/socialite` в `composer.json`,
  либо не заполнен блок `google` в `config/services.php`
- Мок Socialite не подхватывается → проверь, что мокается фасад `Socialite::shouldReceive('driver')`
- `route [home] not defined` → `routes/web.php` перезаписан
- Тесты OTP падают на `travel()` → нужен трейт `InteractsWithTime` (в Laravel 13 он в TestCase)

### Ручная проверка (нужны ключи Google)

Google Cloud Console → Credentials → OAuth client ID → Web application.
Redirect URI: `http://127.0.0.1:8000/auth/google/callback`. Consent screen:
External, статус Testing, себя в Test users. Scopes не трогать.

```bash
# .env
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=http://127.0.0.1:8000/auth/google/callback
APP_URL=http://127.0.0.1:8000

php artisan serve
```

Сценарий: войти через Google → подать объявление → код OTP найти в
`storage/logs/laravel.log` (SMS_DRIVER=log, реальных отправок нет) → ввести →
объявление в `pending` с заполненным `phone_verified_at`.

**Затем:** закоммитить правки, запушить, открыть PR в `main` (после PR #2).

## Чего не делать

- **Не обходи advisory-блок Composer.** Если он сработал — это сигнал, а не помеха.
  Сообщи, что именно он говорит.
- **Не понижай Laravel обратно на 11 или 12.** 11 без поддержки с марта 2026,
  у 12 багфиксы кончаются 13 августа 2026.
- Не добавляй Filament — он в S5, сейчас потянет конфликты версий.
- **Не доводи вьюхи S2 до ума.** Они черновые намеренно: вёрстка в S4,
  где появится витрина и дизайн-система. Полировать их сейчас — выкинуть дважды.
- **Не публикуй объявление напрямую в `active`.** Оно доходит до `pending`;
  публикует конвейер модерации в S3, и только после OTP.
- **Не включай боевой SMS-драйвер** без явной просьбы: ~0.05 €/SMS,
  цикл отладки съест бюджет незаметно.
- Не трогай `BLUEPRINT-numeros-es.md` без явной просьбы: это источник правды
  по решениям, а не рабочий файл.
- Не переходи к S2, пока `migrate:fresh --seed` и `php artisan test` не пройдут чисто.
