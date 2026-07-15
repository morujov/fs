<?php

namespace App\Services\Moderation\Rules;

use App\Models\Listing;
use App\Services\Moderation\ModerationRule;
use App\Services\Moderation\RuleResult;

/**
 * Контакты продавца пригодны для связи.
 *
 * Объявление, по контактам которого нельзя дозвониться, бесполезно всем:
 * покупатель тратит раскрытие впустую, мы получаем жалобу.
 *
 * Отдельно ловим случай «контактный телефон совпадает с продаваемым».
 * Звучит нелепо, но это частая честная ошибка: человек машинально вписывает
 * тот же номер дважды. А продав номер, он теряет и связь с покупателями.
 */
final class ContactsValid implements ModerationRule
{
    /**
     * Одноразовые почты. Не для борьбы с анонимностью — email продавца
     * приходит от Google и уже верифицирован. Но contact_email он вводит
     * руками, и одноразовый ящик означает, что через неделю по нему никто
     * не ответит.
     */
    private const DISPOSABLE_DOMAINS = [
        'mailinator.com', 'guerrillamail.com', 'guerrillamail.info',
        '10minutemail.com', 'tempmail.com', 'temp-mail.org',
        'throwawaymail.com', 'yopmail.com', 'trashmail.com',
        'sharklasers.com', 'getnada.com', 'maildrop.cc',
        'dispostable.com', 'fakeinbox.com', 'mytemp.email',
    ];

    public function name(): string
    {
        return 'contacts_valid';
    }

    public function check(Listing $listing): RuleResult
    {
        $digits = preg_replace('/\D/', '', (string) $listing->contact_phone) ?? '';

        if ($digits === '') {
            return RuleResult::reject('moderation.reasons.contact_phone_missing');
        }

        // Испанский номер — 9 цифр; с кодом страны 11. Короче — опечатка.
        if (strlen($digits) < 9) {
            return RuleResult::reject(
                'moderation.reasons.contact_phone_malformed',
                payload: ['digits' => strlen($digits)]
            );
        }

        $local = substr($digits, -9);

        if ($local === $listing->msisdn) {
            return RuleResult::reject('moderation.reasons.contact_equals_msisdn');
        }

        if ($listing->contact_email !== null) {
            if (! filter_var($listing->contact_email, FILTER_VALIDATE_EMAIL)) {
                return RuleResult::reject('moderation.reasons.contact_email_malformed');
            }

            $domain = strtolower(substr(strrchr($listing->contact_email, '@') ?: '', 1));

            if (in_array($domain, self::DISPOSABLE_DOMAINS, true)) {
                return RuleResult::flag(
                    'moderation.reasons.contact_email_disposable',
                    score: 2,
                    payload: ['domain' => $domain]
                );
            }
        }

        return RuleResult::pass();
    }
}
