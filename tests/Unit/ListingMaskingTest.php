<?php

namespace Tests\Unit;

use App\Models\Listing;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Маскировка контактов — вторая половина защиты базы продавцов
 * (первая — санитизация LIKE, см. NumberPatternQueryTest).
 *
 * Инвариант: то, что уходит анониму, не должно содержать полного телефона
 * ни в каком виде. Рендерить полное значение и прятать CSS-ом нельзя —
 * вскрывается через Ctrl+U за пять секунд.
 *
 * Модель тут НЕ сохраняется в БД: маскировка — чистая функция от полей.
 * Наследуемся от Tests\TestCase (приложение поднимается, БД не трогается):
 * Eloquent без контейнера капризничает на кастах, а ловить это в тесте
 * защиты контактов — последнее, что нужно.
 */
class ListingMaskingTest extends TestCase
{
    private function listing(array $attrs = []): Listing
    {
        $l = new Listing;
        $l->forceFill(array_merge([
            'msisdn'           => '612345678',
            'contact_name'     => 'Juan Martínez García',
            'contact_phone'    => '+34655443322',
            'contact_email'    => 'juan.martinez@gmail.com',
            'contact_whatsapp' => true,
        ], $attrs));

        return $l;
    }

    // -----------------------------------------------------------------
    // Телефон
    // -----------------------------------------------------------------

    #[Test]
    public function masked_phone_reveals_exactly_one_digit(): void
    {
        $this->assertSame('6** ** ** **', $this->listing()->maskedPhone());
    }

    #[Test]
    public function masked_phone_handles_all_input_formats(): void
    {
        foreach (['+34655443322', '0034655443322', '655443322', '655 44 33 22', '655-44-33-22'] as $input) {
            $this->assertSame(
                '6** ** ** **',
                $this->listing(['contact_phone' => $input])->maskedPhone(),
                "Формат {$input} замаскирован неправильно"
            );
        }
    }

    #[Test]
    public function masked_phone_leaks_no_digit_other_than_the_first(): void
    {
        // Ключевое свойство: из маски нельзя восстановить ни одной цифры,
        // кроме первой — а она и так предсказуема (6 или 7).
        $masked = $this->listing(['contact_phone' => '+34655443322'])->maskedPhone();

        foreach (['5', '4', '3', '2'] as $digit) {
            $this->assertStringNotContainsString(
                $digit,
                $masked,
                "Цифра {$digit} утекла в маску: {$masked}"
            );
        }
    }

    #[Test]
    public function masked_phone_survives_an_empty_phone(): void
    {
        $this->assertSame('6** ** ** **', $this->listing(['contact_phone' => ''])->maskedPhone());
        $this->assertSame('6** ** ** **', $this->listing(['contact_phone' => null])->maskedPhone());
    }

    // -----------------------------------------------------------------
    // Имя и email
    // -----------------------------------------------------------------

    #[Test]
    public function masked_name_shows_first_name_and_surname_initial(): void
    {
        $this->assertSame('Juan M.', $this->listing()->maskedName());
        $this->assertSame('Juan', $this->listing(['contact_name' => 'Juan'])->maskedName());
        $this->assertSame('José M.', $this->listing(['contact_name' => 'José maría lópez'])->maskedName());
    }

    #[Test]
    public function masked_name_survives_junk_input(): void
    {
        $this->assertSame('—', $this->listing(['contact_name' => ''])->maskedName());
        $this->assertSame('—', $this->listing(['contact_name' => '   '])->maskedName());
    }

    #[Test]
    public function masked_email_keeps_only_the_first_letter(): void
    {
        $this->assertSame('j••••@gmail.com', $this->listing()->maskedEmail());
        $this->assertNull($this->listing(['contact_email' => null])->maskedEmail());
    }

    // -----------------------------------------------------------------
    // Главный инвариант
    // -----------------------------------------------------------------

    #[Test]
    public function masked_contact_contains_no_full_value_anywhere(): void
    {
        $listing = $this->listing();
        $masked = $listing->maskedContact();
        $flat = json_encode($masked, JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString('655443322', $flat, 'полный телефон в замаскированном наборе');
        $this->assertStringNotContainsString('juan.martinez@gmail.com', $flat, 'полный email');
        $this->assertStringNotContainsString('Martínez', $flat, 'полная фамилия');
        $this->assertStringNotContainsString('wa.me', $flat, 'ссылка на WhatsApp даёт номер целиком');

        $this->assertFalse($masked['revealed']);
        $this->assertNull($masked['whatsapp'], 'до входа WhatsApp-ссылки не существует');
    }

    #[Test]
    public function full_contact_returns_everything_but_only_when_asked(): void
    {
        // fullContact вызывается ТОЛЬКО после проверки сессии и лимитов
        // в ContactRevealController. Здесь проверяем сам контракт.
        $full = $this->listing()->fullContact();

        $this->assertSame('+34655443322', $full['phone']);
        $this->assertSame('Juan Martínez García', $full['name']);
        $this->assertSame('juan.martinez@gmail.com', $full['email']);
        $this->assertSame('https://wa.me/34655443322', $full['whatsapp']);
        $this->assertTrue($full['revealed']);
    }

    #[Test]
    public function whatsapp_link_is_absent_when_the_seller_did_not_offer_it(): void
    {
        $full = $this->listing(['contact_whatsapp' => false])->fullContact();

        $this->assertNull($full['whatsapp']);
    }

    #[Test]
    public function contacts_are_hidden_from_serialization(): void
    {
        // Вторая линия обороны: если кто-то отдаст модель через ->toJson()
        // или в Blade целиком, контакты не должны утечь.
        $json = $this->listing()->toJson();

        $this->assertStringNotContainsString('655443322', $json);
        $this->assertStringNotContainsString('juan.martinez@gmail.com', $json);
        $this->assertStringNotContainsString('Martínez', $json);
    }

    // -----------------------------------------------------------------
    // Товар не маскируется
    // -----------------------------------------------------------------

    #[Test]
    public function the_number_for_sale_is_never_masked(): void
    {
        // Инвариант №1 проекта. Продаваемый номер — это товар, витрина и
        // весь SEO. Если он вдруг начнёт маскироваться, продукта не станет.
        $listing = $this->listing(['msisdn' => '612345678']);

        $this->assertSame('612 34 56 78', $listing->formattedMsisdn());
        $this->assertStringNotContainsString('*', $listing->formattedMsisdn());
        $this->assertStringContainsString('612345678', $listing->toJson());
    }

    #[Test]
    public function the_number_for_sale_uses_spanish_grouping(): void
    {
        // Испанская запись мобильного — 3-2-2-2, а не 3-3-3.
        $this->assertSame('612 34 56 78', $this->listing(['msisdn' => '612345678'])->formattedMsisdn());
        $this->assertSame('777 77 77 77', $this->listing(['msisdn' => '777777777'])->formattedMsisdn());
    }
}
