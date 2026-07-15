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
| PHP | **8.3** — обязательно | 8.3.32 |
| MySQL | 8.0 | **5.7.44** |
| Composer | 2.x | `/opt/cpanel/composer/bin/composer` |
| Node | есть | **нет** — ассеты собираются локально |

**PHP 8.5 не подходит.** Laravel 11 вышел в марте 2024 и с ним не тестировался.
На машине может стоять Homebrew-PHP 8.5 — его нужно отлинковать.

**MySQL: прод на 5.7.** Ничего из MySQL 8: ни оконных функций, ни `CHECK`,
ни `SKIP LOCKED`. `JSON` и generated-колонки в 5.7 есть, их используем.

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

## Задача сейчас: поднять окружение

```bash
# ВАЖНО: brew спрашивает подтверждение. Без NONINTERACTIVE=1 команда
# зависнет на промпте, а следующие строки скрипта уйдут ему в ответ.
NONINTERACTIVE=1 brew install php@8.3 composer mysql@8.0

brew unlink php 2>/dev/null
brew link --overwrite --force php@8.3

# Шелл — bash, не zsh. Правится ~/.bash_profile, не ~/.zshrc.
echo 'export PATH="/opt/homebrew/opt/mysql@8.0/bin:$PATH"' >> ~/.bash_profile
export PATH="/opt/homebrew/opt/mysql@8.0/bin:$PATH"

brew services start mysql@8.0
sleep 5

# Проверить перед тем, как идти дальше:
php -v          # обязан быть 8.3.x, НЕ 8.5
composer -V
mysql --version

mysql -u root -h 127.0.0.1 -e "CREATE DATABASE IF NOT EXISTS numeros_es CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

cd ~/Documents/numeros-es
composer install
[ -f .env ] || cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

Ожидаемо: 52 провинции, 14 операторов, ~500 объявлений.

```bash
php artisan tinker --execute="echo App\Models\Listing::count();"
```

### Если падает

- `Specified key was too long` → `Schema::defaultStringLength(191)` в `AppServiceProvider::boot()`
- Ошибка на generated-колонке → проверь версию MySQL, нужна ≥ 5.7.6
- `requires php ^8.2 but your php version 8.5` → `brew unlink php` не сработал
- Фабрика падает на `Operator::inRandomOrder()->value('id')` → сидеры справочников
  должны отработать раньше `DemoListingSeeder`; порядок задан в `DatabaseSeeder`

### Когда заработает

```bash
git push -u origin main    # remote уже настроен на github.com/morujov/fs
```

Затем сообщи, что прошло и что чинил — и можно начинать S2.

## Чего не делать

- Не добавляй Filament — он в S5, сейчас потянет конфликты версий.
- Не трогай `BLUEPRINT-numeros-es.md` без явной просьбы: это источник правды
  по решениям, а не рабочий файл.
- Не переходи к S2, пока `migrate --seed` не пройдёт чисто.
