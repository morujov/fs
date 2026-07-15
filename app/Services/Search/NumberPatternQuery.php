<?php

namespace App\Services\Search;

use InvalidArgumentException;

/**
 * Ядро wildcard-поиска. Единственное место, где пользовательская маска
 * превращается в SQL-паттерн.
 *
 * Пользовательский синтаксис:
 *   цифры  — фиксированная позиция
 *   '?'    — любая одна цифра
 *   пустые позиции справа добиваются до 9
 *
 * Пример: '6??12??34' → LIKE '6__12__34'
 *
 * ── БЕЗОПАСНОСТЬ ───────────────────────────────────────────────────────
 * '%' и '_' от пользователя ОБЯЗАНЫ быть вырезаны до построения LIKE.
 * Если пользователь введёт '%', он получит выгрузку всей базы контактов
 * одним запросом. Это дыра, а не косметика. Санитизация здесь —
 * whitelist (оставляем только [0-9?]), а не blacklist: чёрный список
 * рано или поздно забывает про очередной спецсимвол.
 *
 * ── ПРОИЗВОДИТЕЛЬНОСТЬ ─────────────────────────────────────────────────
 * LIKE '6__12__34' использует индекс по msisdn: префикс известен.
 * LIKE '___12____' — не использует, будет full scan. На CHAR(9) и объёме
 * до ~200k строк это 30–60 мс, что приемлемо. Пути отхода, когда перестанет
 * хватать, описаны в блюпринте (раздел 4, «План B / План C»).
 * Преждевременно не оптимизируем, но держим логику в одном классе,
 * чтобы замена движка не трогала контроллеры.
 */
final class NumberPatternQuery
{
    public const LENGTH = 9;

    /** Испанский мобильный начинается на 6 или 7. */
    public const VALID_FIRST_DIGITS = ['6', '7'];

    /**
     * Санитизация пользовательского ввода.
     * Возвращает строку из [0-9?] длиной не больше 9, либо '' если ввод пуст.
     */
    public static function sanitize(?string $input): string
    {
        $clean = preg_replace('/[^0-9?]/', '', (string) $input);

        return substr($clean ?? '', 0, self::LENGTH);
    }

    /**
     * Маска → SQL LIKE-паттерн.
     * Возвращает null, если искать не по чему (пустой ввод).
     */
    public static function toLike(?string $input): ?string
    {
        $clean = self::sanitize($input);

        if ($clean === '') {
            return null;
        }

        // Добиваем справа: '612' означает «начинается на 612», а не
        // «ровно 612». Пользователь, набравший три цифры, ждёт именно этого.
        $clean = str_pad($clean, self::LENGTH, '?');

        return strtr($clean, ['?' => '_']);
    }

    /**
     * Маска → regex. Нужен для сохранённых поисков: при публикации нового
     * объявления мы матчим его против сотен масок в памяти, и гонять
     * ради этого SQL по каждой маске было бы расточительно.
     */
    public static function toRegex(?string $input): ?string
    {
        $clean = self::sanitize($input);

        if ($clean === '') {
            return null;
        }

        $clean = str_pad($clean, self::LENGTH, '?');

        return '/^'.strtr($clean, ['?' => '\d']).'$/';
    }

    /** Валиден ли номер как испанский мобильный. */
    public static function isValidMsisdn(string $msisdn): bool
    {
        return (bool) preg_match('/^[67]\d{8}$/', $msisdn);
    }

    /**
     * Нормализация введённого номера к хранимому виду.
     * Принимает '+34 612 34 56 78', '0034612345678', '612-34-56-78'.
     * Бросает исключение, если номер не испанский мобильный.
     */
    public static function normalize(string $input): string
    {
        $digits = preg_replace('/\D/', '', $input) ?? '';

        // Отрезаем международный префикс в любой из двух записей.
        if (str_starts_with($digits, '0034')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '34') && strlen($digits) === 11) {
            $digits = substr($digits, 2);
        }

        if (! self::isValidMsisdn($digits)) {
            throw new InvalidArgumentException("Not a valid Spanish mobile number: {$input}");
        }

        return $digits;
    }
}
