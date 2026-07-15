<?php

namespace App\Services\Moderation\Rules;

use App\Models\Listing;
use App\Services\Moderation\ModerationRule;
use App\Services\Moderation\RuleResult;

/**
 * В описании нет контактов в обход гейта.
 *
 * ── Почему это важнее, чем кажется ──────────────────────────────────────
 * Весь смысл Google-гейта, лимитов раскрытия и маскировки — в том, что
 * контакты выдаются дозированно и под учёт. Телефон, вписанный в свободный
 * текст описания, обходит всё это разом: он в HTML, его видит любой бот,
 * и никакой лимит к нему не применяется. Одна такая строка сводит на нет
 * всю защиту базы.
 *
 * ── Почему flag, а не reject, и почему не вырезаем молча ────────────────
 * Ложные срабатывания реальны: «vendo 3 números», «tengo 2 líneas»,
 * год выпуска, цена в тексте. Отклонять честного продавца за цифру в
 * описании — потерять его.
 *
 * Молча вырезать найденное (что предлагал исходный план) — хуже. Тихо
 * менять то, что написал человек, значит: он не узнает, что его текст
 * правили; он не поймёт правило и повторит; а мы получим репутацию сайта,
 * который «съедает текст». Отправляем модератору — он разберёт за секунду.
 */
final class NoContactsInDescription implements ModerationRule
{
    public function name(): string
    {
        return 'contacts_in_text';
    }

    public function check(Listing $listing): RuleResult
    {
        $text = (string) $listing->description;

        if (trim($text) === '') {
            return RuleResult::pass();
        }

        $found = [];

        // Телефон: 9 цифр подряд, возможно разделённые пробелами/дефисами,
        // возможно с +34. Продаваемый номер в описании не считаем: он и так
        // виден целиком, это товар.
        $normalized = preg_replace('/[\s\-().]/', '', $text) ?? '';
        if (preg_match_all('/(?:\+?34)?([67]\d{8})/', $normalized, $m)) {
            $phones = array_diff(array_unique($m[1]), [$listing->msisdn]);
            if ($phones !== []) {
                $found['phones'] = array_values($phones);
            }
        }

        if (preg_match_all('/[\w.+-]+@[\w-]+\.[\w.-]+/u', $text, $m)) {
            $found['emails'] = array_unique($m[0]);
        }

        if (preg_match_all('#https?://\S+|www\.\S+#iu', $text, $m)) {
            $found['urls'] = array_unique($m[0]);
        }

        // @ник — телеграм/инстаграм. Отдельно от email: собака без домена.
        if (preg_match_all('/(?<![\w.])@([A-Za-z][A-Za-z0-9_]{3,})/u', $text, $m)) {
            $found['handles'] = array_unique($m[1]);
        }

        // «Whatsapp», «telegram» словами — часто идут вместе с номером,
        // но сами по себе безобидны: продавец может просто указать канал.
        // Ловим только в паре с чем-то ещё, поэтому отдельным флагом не делаем.

        if ($found === []) {
            return RuleResult::pass();
        }

        // Вес 3 = порог ручной очереди по умолчанию. Одного попадания
        // достаточно, чтобы модератор посмотрел: цена ошибки высока.
        return RuleResult::flag('moderation.reasons.contacts_in_text', score: 3, payload: $found);
    }
}
