# Números ES — рунбук

Практическое руководство по проекту для человека: где что лежит, как зайти,
как выкатить правку, что делать, когда сломалось.

Смежные документы:
`CLAUDE.md` — инварианты и правила (**не менять без явной необходимости**) ·
`BLUEPRINT-numeros-es.md` — ТЗ и обоснования решений ·
`AUDIT-2026-08-27.md` — состояние после переноса и список открытых пунктов ·
`STATUS-2026-07-31.md` — снимок проекта.

---

## 1. Что это

Доска объявлений о продаже мобильных номеров в Испании. **Не маркетплейс:**
сделки идут вне сайта, деньги через площадку не проходят.

Продавец подаёт номер, подтверждает владение по SMS-коду, объявление проходит
модерацию и попадает на витрину. Покупатель ищет номер по маске (`6??12??34`),
фильтрует и **открывает контакт продавца только после входа** — до этого
контакт замаскирован. Сам продаваемый номер виден всем: это товар и SEO.

Языки интерфейса: испанский (по умолчанию) и английский.

---

## 2. Где что живёт

### Рабочая копия (Mac)
```
~/Documents/numeros-es
```
Git: `github.com/morujov/fs`. Рабочая ветка — **`main`**: PR #13 влит 27.08,
открытых PR нет. Новую работу вести в отдельной ветке и вливать через PR.

### Тестовый сервер (клиента)
| | |
|---|---|
| Адрес сайта | **http://test.numerofacil.es** |
| Пароль стенда | `cliente` / `numerofacil2026` (Basic Auth) |
| Сервер | `152.53.226.65`, netcup, Debian 13 |
| Каталог проекта | `/var/www/numeros-es` |
| Вход | `ssh -i ~/.ssh/numerofacil_dev root@152.53.226.65` |

### Домен
`numerofacil.es` в GoDaddy клиента. Поддомен стенда — запись
`A · test · 152.53.226.65`. Основной домен (`@`, `www`) не тронут.

---

## 3. Ежедневные операции

Все команды — на сервере, из каталога проекта:
```bash
ssh -i ~/.ssh/numerofacil_dev root@152.53.226.65
cd /var/www/numeros-es
```

### Проверить, что всё живо
```bash
systemctl is-active nginx php8.3-fpm mysql cron
curl -s -o /dev/null -w "%{http_code}\n" -u cliente:numerofacil2026 http://test.numerofacil.es/
```
Ожидается четыре `active` и `200`.

### Посмотреть ошибки приложения
```bash
tail -50 /var/www/numeros-es/storage/logs/laravel.log
```

### Посмотреть ошибки веб-сервера
```bash
tail -30 /var/log/nginx/error.log
```

### Проверить планировщик (сроки объявлений, GDPR-очистка)
```bash
sudo -u www-data HOME=/tmp php artisan schedule:list
tail -20 /var/log/numeros-schedule.log
```

### Перезапустить сервисы
```bash
systemctl restart php8.3-fpm    # после правок PHP-кода вне кэша
systemctl reload nginx          # после правок конфига nginx
systemctl restart mysql         # редко; сайт на это время ляжет
```

---

## 4. Выкатить обновление кода

Node на сервере нет — **ассеты собираются на Mac**, на сервер едет архив.

**На Mac:**
```bash
cd ~/Documents/numeros-es
npm run build

tar -czf /tmp/numeros-code.tar.gz \
  --exclude=.git --exclude=node_modules --exclude=vendor --exclude=.env \
  --exclude='storage/logs/*.log' --exclude='storage/framework/cache/*' \
  --exclude='storage/framework/sessions/*' --exclude='storage/framework/views/*' \
  --exclude='bootstrap/cache/*.php' .

scp -i ~/.ssh/numerofacil_dev /tmp/numeros-code.tar.gz root@152.53.226.65:/tmp/
```

**На сервере:**
```bash
cd /var/www/numeros-es
cp .env /root/.env.backup                     # .env в архиве нет, но подстрахуемся
tar -xzf /tmp/numeros-code.tar.gz -C /var/www/numeros-es

composer install --optimize-autoloader --no-interaction   # только если менялся composer.json
php artisan migrate --force                                # только если есть новые миграции

chown -R www-data:www-data /var/www/numeros-es
find /var/www/numeros-es -type d -exec chmod 755 {} +
find /var/www/numeros-es -type f -exec chmod 644 {} +
chmod 600 .env && chmod -R 775 storage bootstrap/cache

sudo -u www-data HOME=/tmp php artisan config:cache
sudo -u www-data HOME=/tmp php artisan route:cache
sudo -u www-data HOME=/tmp php artisan view:cache
```

> **Права — не формальность.** Архив с Mac приносит права `700/600`, под ними
> `www-data` не читает файлы и сайт отдаёт 403. Блок `chown`/`chmod` обязателен
> после каждой распаковки.

Проверить после выката:
```bash
curl -s -o /dev/null -w "%{http_code}\n" -u cliente:numerofacil2026 http://test.numerofacil.es/
```

---

## 5. База данных

Под root на сервере пароль подставляется автоматически (`/root/.my.cnf`),
поэтому достаточно `mysql`.

### Посмотреть, что в базе
```bash
mysql -e "SELECT
 (SELECT COUNT(*) FROM numeros_es.listings) AS listings,
 (SELECT COUNT(*) FROM numeros_es.listings WHERE status='active') AS active,
 (SELECT COUNT(*) FROM numeros_es.users) AS users;"
```

### Сделать резервную копию
```bash
mysqldump --single-transaction --routines numeros_es \
  | gzip > /root/numeros_es-$(date +%F-%H%M).sql.gz
ls -lh /root/numeros_es-*.sql.gz
```

### Настроить ночные бэкапы (рекомендуется, сейчас их нет)
```bash
mkdir -p /root/backups
cat > /etc/cron.d/numeros-backup <<'CRON'
15 3 * * * root mysqldump --single-transaction --routines numeros_es | gzip > /root/backups/numeros_es-$(date +\%F).sql.gz && find /root/backups -name '*.sql.gz' -mtime +14 -delete
CRON
```

### Восстановить из копии
```bash
gunzip -c /root/backups/numeros_es-2026-08-27.sql.gz | mysql numeros_es
```

### Пересоздать демо-данные
```bash
cd /var/www/numeros-es
php artisan migrate:fresh --force
php artisan db:seed --force                                     # справочники
APP_ENV=local php artisan db:seed --class="Database\Seeders\DemoListingSeeder" --force
```
> Демо-объявления в `production` сеются только с приставкой `APP_ENV=local` —
> обычный `db:seed` их намеренно пропускает.

---

## 6. Тесты

**Порядок обязателен.** При закэшированном конфиге тесты не видят настройки
из `phpunit.xml`; предохранитель в `tests/TestCase.php` их остановит, но лучше
делать сразу правильно:

```bash
cd /var/www/numeros-es
php artisan config:clear
sudo -u www-data HOME=/tmp php artisan test
php artisan config:cache          # вернуть кэш, иначе сайт станет медленнее
```

Ожидается **276 passed**. Тесты идут на отдельной базе `numeros_es_testing`
(она уже создана). На Mac — то же самое, без `sudo -u www-data`.

Если увидите «Тесты остановлены: подключение ведёт в базу «numeros_es»» — это
и есть предохранитель: не выполнен `config:clear`.

---

## 7. Админка (панель модератора)

Адрес: `http://test.numerofacil.es/admin`

Вход **только через Google** — паролей в системе нет by design. Чтобы админка
заработала, нужны два шага:

1. Прописать в `.env` ключи Google OAuth (scopes строго `openid email profile`):
   ```
   GOOGLE_CLIENT_ID=...
   GOOGLE_CLIENT_SECRET=...
   GOOGLE_REDIRECT_URI=http://test.numerofacil.es/auth/google/callback
   ```
   затем `php artisan config:cache`.
2. Войти на сайт своим Google-аккаунтом, после чего выдать себе роль:
   ```bash
   mysql -e "UPDATE numeros_es.users SET role='superadmin' WHERE email='ваш@gmail.com';"
   ```

Роли: `moderator` (очередь модерации и жалобы) и `superadmin` (плюс настройки).
Сейчас **роль не проставлена ни у кого** — войти в админку некому.

---

## 8. Настройки продукта — в базе, не в коде

Пороги, сроки, лимиты и фича-флаги лежат в таблице `settings` и правятся из
админки, **без выката кода**. Сроки хранения персональных данных — группа
`retention`.

Посмотреть текущие значения:
```bash
mysql -e "SELECT \`key\`, value FROM numeros_es.settings ORDER BY \`key\`;"
```

Точно так же план нумерации (какие префиксы мобильные и продаются) живёт в
таблице `numbering_ranges`: новый диапазон — это одна строка в базе, а не
правка кода и выкат.

---

## 9. Что нельзя ломать

Полный список — в `CLAUDE.md`. Самое важное человеческими словами:

1. **Продаваемый номер показываем всегда.** Он — товар и весь SEO.
   Маскируется только контакт продавца.
2. **Полный контакт не должен попадать в HTML** — ни разу, ни под каким CSS.
   Спрятать `blur`-ом нельзя: вскрывается через «Просмотр кода страницы».
3. **Поиск, фильтры и карточки открыты всем**, включая Googlebot. Гейт стоит
   ровно в одном месте — на кнопке раскрытия контакта. Закрыть просмотр =
   убить SEO = убить проект.
4. **Вход только через Google.** Паролей не добавлять.
5. **Никакой аналитики с куками** (GA, GTM, пиксели). Сейчас cookie-баннер не
   нужен, потому что кроме сессии кук нет. Добавите счётчик — баннер станет
   обязательным по закону. Только cookieless: Plausible или Matomo без кук.
6. **Тексты — только в `lang/es` и `lang/en`.** Строк в коде быть не должно.

---

## 10. Когда сломалось

| Симптом | Причина | Лечение |
|---|---|---|
| Весь сайт отдаёт **403** | после распаковки архива права `700/600`, `www-data` не читает | блок `chown`/`chmod` из §4 |
| **404 File not found** на всех страницах | php-fpm не получает путь к скрипту (дубль `SCRIPT_FILENAME` в конфиге nginx) | в блоке `location ~ \.php$` оставить только `include snippets/fastcgi-php.conf;` |
| «Please provide a valid cache path» | нет каталогов `storage/framework/*` (архив их не переносит — они пустые) | `mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache` и повторить `chmod -R 775 storage` |
| Правка кода не видна на сайте | закэширован конфиг/маршруты/шаблоны | `php artisan config:clear route:clear view:clear`, затем снова `:cache` |
| Тесты остановились с сообщением про базу | не сделан `config:clear` перед прогоном | §6 |
| Пусто на витрине | не засеяны демо-данные | §5, «Пересоздать демо-данные» |
| Объявления не истекают, GDPR-очистка не идёт | не работает cron | `systemctl status cron`, наличие `/etc/cron.d/numeros-es` |
| MySQL не стартует после правок конфига | сломан `/etc/mysql/my.cnf` | смотреть `journalctl -u mysql -n 30`; свои настройки держать в `/etc/mysql/conf.d/zz-numeros.cnf`, штатный `my.cnf` не трогать |

---

## 11. Перед боевым запуском

Стенд намеренно упрощён. До открытия для реальных пользователей обязательно:

1. **HTTPS** — сертификат, редирект 80→443. Сейчас пароль стенда ходит открытым
   текстом.
2. **Сменить пароли** root и панели netcup (передавались в переписке открыто),
   отключить вход по паролю по SSH — доступ по ключу уже настроен.
3. **Закрыть сервер**: фаервол, fail2ban, автообновления безопасности.
4. **Бэкапы БД** (§5) — сейчас их нет.
5. **Ключи Google OAuth, SMTP и SMS** — без них не работают вход, подача
   объявления и подтверждение номера.
6. **Юридические тексты** (условия, политика конфиденциальности) — без них
   запуск в ЕС незаконен. Это блокер, а не пожелание.
7. **SEO-посадочные** `/provincia/{slug}`, sitemap, hreflang.
8. Снять с сайта Basic Auth и `noindex` — иначе боевой сайт не проиндексируется.
