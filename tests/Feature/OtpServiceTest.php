<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\OtpCode;
use App\Models\Setting;
use App\Models\User;
use App\Services\Otp\OtpException;
use App\Services\Otp\OtpService;
use App\Services\Sms\SmsSenderInterface;
use Database\Seeders\NumberingRangeSeeder;
use Database\Seeders\OperatorSeeder;
use Database\Seeders\ProvinceSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * OTP — единственное, что мешает выставить чужой номер.
 *
 * Google подтверждает, кто ты. Он ничего не говорит о том, твой ли номер.
 * Если эти тесты покраснеют, площадка станет инструментом для публикации
 * чужих телефонов — с прямым ущербом третьим лицам.
 */
class OtpServiceTest extends TestCase
{
    use RefreshDatabase;

    private OtpService $otp;

    /** @var list<array{msisdn:string,text:string}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NumberingRangeSeeder::class);
        $this->seed(ProvinceSeeder::class);
        $this->seed(OperatorSeeder::class);
        $this->seed(SettingSeeder::class);

        // Перехватываем отправку: реальных SMS в тестах быть не должно —
        // это и деньги, и зависимость от чужого API.
        $this->sent = [];
        $spy = new class($this->sent) implements SmsSenderInterface
        {
            public function __construct(private array &$sent) {}

            public function send(string $msisdn, string $text): void
            {
                $this->sent[] = ['msisdn' => $msisdn, 'text' => $text];
            }
        };

        $this->otp = new OtpService($spy);
    }

    private function listing(array $attrs = []): Listing
    {
        return Listing::factory()->create(array_merge([
            'user_id'           => User::factory(),
            'msisdn'            => '612345678',
            'status'            => 'pending',
            'phone_verified_at' => null,
            'slug'              => '612345678-'.uniqid(),
        ], $attrs));
    }

    /** Достаём код из перехваченного текста SMS. */
    private function lastCode(): string
    {
        preg_match('/\b(\d{6})\b/', end($this->sent)['text'], $m);

        return $m[1];
    }

    // -----------------------------------------------------------------
    // Выпуск
    // -----------------------------------------------------------------

    #[Test]
    public function issues_a_six_digit_code_and_sends_it_to_the_number_for_sale(): void
    {
        $listing = $this->listing();

        $this->otp->issue($listing);

        $this->assertCount(1, $this->sent);
        $this->assertSame('612345678', $this->sent[0]['msisdn'], 'код ушёл не на продаваемый номер');
        $this->assertMatchesRegularExpression('/\b\d{6}\b/', $this->sent[0]['text']);
    }

    #[Test]
    public function stores_the_code_hashed_not_in_plain_text(): void
    {
        // Утечка таблицы не должна давать возможности подтвердить чужие
        // объявления — иначе один дамп БД обнуляет всю защиту.
        $listing = $this->listing();
        $this->otp->issue($listing);

        $code = $this->lastCode();
        $otp = OtpCode::where('listing_id', $listing->id)->latest('id')->first();

        $this->assertNotSame($code, $otp->code_hash, 'код лежит в открытом виде');
        $this->assertTrue(Hash::check($code, $otp->code_hash), 'хэш не совпадает с кодом');
    }

    #[Test]
    public function reissuing_invalidates_the_previous_code(): void
    {
        // Иначе «отправить ещё раз» не заменяет код, а расширяет окно атаки:
        // валидными становятся оба.
        $listing = $this->listing();

        $this->otp->issue($listing);
        $first = $this->lastCode();

        $this->otp->issue($listing);

        $this->expectException(OtpException::class);
        $this->otp->verify($listing, $first);
    }

    #[Test]
    public function enforces_the_send_limit(): void
    {
        // Кнопка «отправить ещё раз» без счётчика — это способ выставить
        // нам счёт за SMS.
        Setting::updateOrCreate(['key' => 'moderation.otp_max_sends'], ['value' => '2', 'type' => 'int']);
        cache()->flush();

        $listing = $this->listing();

        $this->otp->issue($listing);
        $this->otp->issue($listing);

        $this->expectException(OtpException::class);
        $this->otp->issue($listing);
    }

    // -----------------------------------------------------------------
    // Проверка
    // -----------------------------------------------------------------

    #[Test]
    public function verifies_a_correct_code_and_marks_the_number_confirmed(): void
    {
        $listing = $this->listing();
        $this->otp->issue($listing);

        $this->assertTrue($this->otp->verify($listing, $this->lastCode()));
        $this->assertNotNull($listing->fresh()->phone_verified_at);
    }

    #[Test]
    public function rejects_a_wrong_code(): void
    {
        $listing = $this->listing();
        $this->otp->issue($listing);

        $wrong = $this->lastCode() === '000000' ? '111111' : '000000';

        $this->expectException(OtpException::class);
        $this->otp->verify($listing, $wrong);
    }

    #[Test]
    public function a_wrong_code_leaves_the_number_unconfirmed(): void
    {
        $listing = $this->listing();
        $this->otp->issue($listing);

        try {
            $this->otp->verify($listing, '000000');
        } catch (OtpException) {
        }

        $this->assertNull($listing->fresh()->phone_verified_at);
    }

    #[Test]
    public function counts_the_attempt_even_when_the_code_is_wrong(): void
    {
        // Инкремент обязан случиться ДО сравнения: иначе прерванный запрос
        // обнулит счётчик, и лимит попыток перестанет ограничивать перебор.
        $listing = $this->listing();
        $this->otp->issue($listing);

        try {
            $this->otp->verify($listing, '000000');
        } catch (OtpException) {
        }

        $this->assertSame(1, OtpCode::where('listing_id', $listing->id)->latest('id')->first()->attempts);
    }

    #[Test]
    public function enforces_the_attempt_limit(): void
    {
        // Шестизначный код перебирается за миллион запросов. Без лимита
        // это минуты работы скрипта.
        Setting::updateOrCreate(['key' => 'moderation.otp_max_attempts'], ['value' => '3', 'type' => 'int']);
        cache()->flush();

        $listing = $this->listing();
        $this->otp->issue($listing);

        for ($i = 0; $i < 3; $i++) {
            try {
                $this->otp->verify($listing, '000000');
            } catch (OtpException) {
            }
        }

        // Четвёртая попытка отбивается лимитом, а не сравнением —
        // даже если код вдруг верный.
        $this->expectException(OtpException::class);
        $this->otp->verify($listing, $this->lastCode());
    }

    #[Test]
    public function rejects_an_expired_code(): void
    {
        $listing = $this->listing();
        $this->otp->issue($listing);
        $code = $this->lastCode();

        $this->travel(11)->minutes();

        $this->expectException(OtpException::class);
        $this->otp->verify($listing, $code);
    }

    #[Test]
    public function a_consumed_code_cannot_be_reused(): void
    {
        $listing = $this->listing();
        $this->otp->issue($listing);
        $code = $this->lastCode();

        $this->otp->verify($listing, $code);

        $this->expectException(OtpException::class);
        $this->otp->verify($listing, $code);
    }

    #[Test]
    public function throws_when_there_is_no_pending_code(): void
    {
        $this->expectException(OtpException::class);
        $this->otp->verify($this->listing(), '123456');
    }

    #[Test]
    public function the_sms_carries_the_ttl_so_the_seller_knows_the_deadline(): void
    {
        $listing = $this->listing();
        $this->otp->issue($listing);

        $this->assertStringContainsString('10', $this->sent[0]['text'], 'в SMS нет срока жизни кода');
    }
}
