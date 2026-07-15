# Números ES

Доска объявлений о продаже мобильных номеров в Испании.
Не маркетплейс: сделки идут вне сайта, деньги через площадку не проходят.

Полное ТЗ и архитектурные решения — в `BLUEPRINT-numeros-es.md`.

**Стек:** Laravel 11 · PHP 8.3 · MySQL · Blade + Alpine + Tailwind
**Авторизация:** только Google Sign-In. Паролей в системе нет.

---

## Запуск на Mac — по шагам

### 1. Laravel Herd

Скачать [herd.laravel.com](https://herd.laravel.com), установить, запустить.
Даёт PHP 8.3, Composer, Node и nginx одним пакетом — ставить их отдельно не нужно.

В Herd нажать **Add path** и указать `~/Documents`.
После этого проект автоматически откроется на `http://numeros-es.test`.

### 2. DBngin — MySQL

Скачать [dbngin.com](https://dbngin.com), установить.
Create → MySQL 8 → порт `3306` → Start.

Создать базу (Herd ставит `mysql` в PATH):

```bash
mysql -u root -h 127.0.0.1 -e "CREATE DATABASE numeros_es CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 3. Зависимости и конфиг

```bash
cd ~/Documents/numeros-es
composer install
cp .env.example .env
php artisan key:generate
```

В `.env` проверить, что совпадает с DBngin:

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

### 5. Фронт

```bash
npm install && npm run dev
```

Открыть `http://numeros-es.test`.

> На шаге 5 витрины ещё нет — она в S4. Сейчас проверяется только то,
> что схема разворачивается и данные сеются.

---

## Если что-то упало

Пришлите вывод целиком — код писался без возможности его запустить
(в окружении Claude нет PHP), поэтому ошибки на первом прогоне ожидаемы
и чинятся быстро.

**`Specified key was too long`** — MySQL 5.x с utf8mb4.
Лечится строкой `Schema::defaultStringLength(191);` в `boot()`
у `app/Providers/AppServiceProvider.php`.

**`SQLSTATE[HY000] [2002]`** — MySQL в DBngin не запущен.

**`could not find driver`** — в Herd не включено расширение `pdo_mysql`.

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
