<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Пороги и флаги. Всё, что здесь, правится из админки без деплоя —
 * на shared-хостинге деплой это ручной git pull в рвущемся терминале,
 * и менять цифру порога таким способом никто не станет.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        // [key, value, type, group, description]
        $rows = [
            // --- Лимиты раскрытия контактов (блюпринт 4A) ---
            ['reveal.per_minute_user',   '5',     'int',  'limits', 'Раскрытий в минуту на аккаунт'],
            ['reveal.per_day_user',      '20',    'int',  'limits', 'Раскрытий в сутки на аккаунт → 429 + Turnstile'],
            ['reveal.per_day_ip',        '40',    'int',  'limits', 'Раскрытий в сутки на IP'],
            ['reveal.accounts_per_ip',   '3',     'int',  'limits', 'Аккаунтов с одного IP за сутки → флаг'],
            ['reveal.autoblock_per_day', '50',    'int',  'limits', 'Раскрытий в сутки → автоблок аккаунта'],
            ['reveal.bot_interval_ms',   '2000',  'int',  'limits', 'Интервал < N мс трижды подряд → сигнатура бота'],

            // --- Подача объявлений ---
            ['listing.per_day_user',     '3',     'int',  'limits', 'Объявлений в сутки с аккаунта → флаг'],
            ['listing.per_day_ip',       '5',     'int',  'limits', 'Объявлений в сутки с IP → флаг'],
            ['listing.ttl_days',         '60',    'int',  'listing', 'Срок жизни объявления, дней'],
            ['listing.expiry_notice_days', '7',   'int',  'listing', 'За сколько дней предупредить об истечении'],
            ['listing.price_min',        '1',     'int',  'listing', 'Минимальная цена, €'],
            ['listing.price_max',        '50000', 'int',  'listing', 'Максимальная цена, €'],
            ['listing.per_page',         '20',    'int',  'listing', 'Объявлений на страницу витрины'],

            // --- Модерация ---
            ['moderation.manual_threshold', '3',  'int',  'moderation', 'Score ≥ N → ручная очередь'],
            ['moderation.otp_ttl_minutes',  '10', 'int',  'moderation', 'Срок жизни OTP-кода, минут'],
            ['moderation.otp_max_sends',    '3',  'int',  'moderation', 'Макс. SMS на одно объявление (защита от накрутки счёта)'],
            ['moderation.otp_max_attempts', '5',  'int',  'moderation', 'Макс. попыток ввода кода'],


            // --- Сроки хранения (GDPR, ст. 5 — ограничение хранения) ---
            //
            // Персональные данные нельзя держать «на всякий случай». Каждый
            // срок ниже обоснован конкретной целью; когда цель отпадает,
            // данные обязаны уйти. Это не перестраховка — это то, за что
            // AEPD штрафует.
            ['retention.reveal_ip_days',      '90',  'int', 'retention', 'Через сколько дней обезличить IP и user-agent в логе раскрытий. Сама строка остаётся: она нужна, чтобы повторное раскрытие не тратило лимит'],
            ['retention.report_ip_days',      '180', 'int', 'retention', 'Через сколько дней после закрытия жалобы обезличить IP заявителя'],
            ['retention.moderation_log_days', '365', 'int', 'retention', 'Сколько хранить логи модерации: в payload попадают куски описаний'],
            ['retention.otp_days',            '30',  'int', 'retention', 'Сколько хранить использованные и протухшие OTP-коды'],
            ['retention.audit_ip_days',       '365', 'int', 'retention', 'Через сколько дней обезличить IP в аудите действий админов'],
            ['retention.sold_listing_days',   '365', 'int', 'retention', 'Через сколько дней после продажи обезличить контакты в объявлении'],

            // --- Фича-флаги ---
            ['features.payments_enabled', 'false', 'bool', 'features', 'Платёжный гейт. Выключен: монетизации нет'],
            ['features.shops_enabled',    'true',  'bool', 'features', 'Регистрация магазинов'],
            ['features.alerts_enabled',   'true',  'bool', 'features', 'Email-алерты по сохранённым поискам'],
            ['features.otp_enabled',      'true',  'bool', 'features', 'OTP-SMS. Выключать только в dev'],
        ];

        foreach ($rows as [$key, $value, $type, $group, $desc]) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'type' => $type, 'group' => $group, 'description' => $desc]
            );
        }
    }
}
