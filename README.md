# Números ES

Доска объявлений о продаже мобильных номеров в Испании.
Не маркетплейс: сделки идут вне сайта, деньги через площадку не проходят.

Полное ТЗ и архитектурные решения — в `BLUEPRINT-numeros-es.md`.

**Стек:** Laravel 11 · PHP 8.3 · MySQL · Blade + Alpine + Tailwind
**Авторизация:** только Google Sign-In. Паролей в системе нет.

---

## Запуск на Mac — по шагам

### 1. PHP 8.3, Composer, MySQL

**Версия PHP важна.** На Bluehost стоит 8.3.32. Локально должно быть 8.3, а не
8.4/8.5: Laravel 11 вышел в марте 2024 и с PHP 8.5 никогда не тестировался.
Если в системе уже есть Homebrew-PHP другой версии, его нужно отлинковать —
иначе `php` продолжит указывать на него.

```bash
brew install php@8.3 composer mysql@8.0

# Убрать с дороги другую версию PHP, если она была слинкована
brew unlink php 2>/dev/null
brew link --overwrite --force php@8.3

# mysql@8.0 — keg-only, сам в PATH не попадёт
echo 'export PATH="/opt/homebrew/opt/mysql@8.0/bin:$PATH"' >> ~/.bash_profile
source ~/.bash_profile

brew services start mysql@8.0
```

Проверка — обе команды обязаны ответить:

```bash
php -v          # → PHP 8.3.x
composer -V     # → Composer 2.x
mysql --version # → 8.0.x
```

> Если `php -v` показывает не 8.3 — открыт другой шелл или PATH не перечитан.
> У вас **bash**, а не zsh, поэтому правится `~/.bash_profile`, не `~/.zshrc`.

### 2. База

```bash
mysql -u root -h 127.0.0.1 -e "CREATE DATABASE numeros_es CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

У Homebrew-MySQL пароль root пустой — это совпадает с `.env.example`.

### 3. Зависимости и конфиг

```bash
cd ~/Documents/numeros-es
composer install
cp .env.example .env     # если .env ещё нет
php artisan key:generate
```

`.env` уже настроен на MySQL:

```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=numeros_es
DB_USERNAME=root
DB_PASSWORD=
```

### 4. Схема и демо-данные

```bash
php artisan migrate --seed
```

Должно появиться: 52 провинции, 14 операторов, ~500 объявлений,
блок-лист служебных диапазонов, настройки.

```bash
php artisan tinker --execute="echo App\Models\Listing::count();"
```

### 5. Запуск

```bash
php artisan serve
```

Открыть `http://127.0.0.1:8000`.

> Витрины ещё нет — она в S4. Сейчас проверяется только то,
> что схема разворачивается и данные сеются.

---

## Git

```bash
cd ~/Documents/numeros-es
git push -u origin main
```

Remote `origin` уже настроен на `github.com/morujov/fs`.

---

## Если что-то упало

Пришлите вывод целиком — код писался без возможности его запустить
(в окружении Claude нет PHP), поэтому ошибки на первом прогоне ожидаемы
и чинятся быстро.

**`composer: command not found`** / **`mysql: command not found`** — шаг 1 не выполнен
или PATH не перечитан. `source ~/.bash_profile` или новое окно терминала.

**`vendor/autoload.php: Failed to open stream`** — не выполнен `composer install`.

**`Specified key was too long`** — MySQL с utf8mb4.
Лечится строкой `Schema::defaultStringLength(191);` в `boot()`
у `app/Providers/AppServiceProvider.php`.

**`SQLSTATE[HY000] [2002] Connection refused`** — MySQL не запущен:
`brew services start mysql@8.0`.

**`could not find driver`** — нет `pdo_mysql`. Проверить: `php -m | grep pdo_mysql`.

**`requires php ^8.2 but your php version 8.5.x`** — не переключился PHP.
`brew unlink php && brew link --overwrite --force php@8.3`.

---

## Деплой на Bluehost

Хост проверен 14.07.2026: PHP 8.3.32, Composer, Git 2.48, inodes без лимита.
Node на хосте нет — ассеты собираются локально и коммитятся готовыми.

```bash
# на хосте, через cPanel → Terminal
cd ~
git clone <приватный-репозиторий> numeros-es
cd numeros-es

# Терминал cPanel рвёт сессию каждые несколько минут — длинную команду
# запускаем в фоне, иначе composer умрёт на полпути.
nohup composer install --no-dev -o > /tmp/composer.log 2>&1 &
tail -f /tmp/composer.log

php artisan migrate --force
```

Дальше — содержимое `public/` в `~/public_html/test`, пути в `index.php`
на `../../numeros-es/`, и обязательная проверка:

```bash
curl -I https://<домен>/test/.env    # ← обязан вернуть 403 или 404
```

Если `.env` отдался — стоп, деплой переделывается. Полный чек-лист
в блюпринте, раздел 8.

---

## Структура

```
app/
├── Models/                 14 моделей
├── Services/Search/
│   ├── NumberPatternQuery  ← ядро wildcard-поиска, вся санитизация здесь
│   └── PatternTagger       ← repetido / capicúa / escalera
config/numeros.php          ← константы; всё изменяемое — в таблице settings
database/
├── migrations/             14 таблиц
├── factories/              User, Listing (+ состояния под красивые номера)
└── seeders/                провинции, операторы, блок-лист, настройки, демо
```

## Что нельзя ломать

1. **Продаваемый номер (`listings.msisdn`) не маскируется никогда** — это товар,
   витрина и весь SEO. Маскируется только контакт продавца.
2. **Полный контакт не попадает в HTML** — ни разу, ни под каким CSS.
   Только AJAX-ответ после проверки сессии.
3. **`%` и `_` от пользователя вырезаются до построения LIKE.**
   Иначе один символ `%` выгружает всю базу контактов одним запросом.
4. **Гейт стоит только на раскрытии контакта.** Поиск, фильтры и карточки
   открыты анониму и Googlebot.
5. **Ничего из MySQL 8** — на проде 5.7.44.
