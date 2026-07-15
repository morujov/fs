<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\NumberingRangeSeeder;
use Database\Seeders\OperatorSeeder;
use Database\Seeders\ProvinceSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Жалобы — блокер №8 блюпринта.
 *
 * Обязательный элемент доски объявлений и наша юридическая защита: при
 * претензии мы обязаны показать, что механизм реагирования существует.
 *
 * Ключевое здесь — что жалоба доступна БЕЗ входа. Самая важная звучит
 * «это мой номер, я его не продаю», и оставляет её человек, у которого
 * аккаунта нет и не будет: он узнал о нас из чужого звонка.
 */
class ReportTest extends TestCase
{
    use RefreshDatabase;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NumberingRangeSeeder::class);
        $this->seed(ProvinceSeeder::class);
        $this->seed(OperatorSeeder::class);
        $this->seed(SettingSeeder::class);

        $this->listing = Listing::factory()->create([
            'user_id' => User::factory(),
            'msisdn'  => '612345678',
            'status'  => 'active',
            'slug'    => '612345678-abc',
        ]);
    }

    #[Test]
    public function a_guest_can_report_a_listing(): void
    {
        // Если этот тест покраснеет, о чужих номерах мы узнавать перестанем.
        $this->post(route('listings.report', $this->listing), [
            'reason'  => 'not_mine',
            'comment' => 'Este es mi número, yo no lo vendo.',
        ])->assertRedirect();

        $this->assertDatabaseHas('reports', [
            'listing_id' => $this->listing->id,
            'reason'     => 'not_mine',
            'status'     => 'open',
            'user_id'    => null,
        ]);
    }

    #[Test]
    public function the_report_form_is_visible_to_guests_on_the_card(): void
    {
        $this->get(route('listings.show', $this->listing))
            ->assertOk()
            ->assertSee(__('report.title'));
    }

    #[Test]
    public function a_signed_in_user_report_is_linked_to_the_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('listings.report', $this->listing), [
            'reason' => 'fraud',
        ]);

        $this->assertDatabaseHas('reports', [
            'listing_id' => $this->listing->id,
            'user_id'    => $user->id,
        ]);
    }

    #[Test]
    public function the_reporter_ip_is_recorded(): void
    {
        // Нужен для антифлуда и для разбора: одна жалоба от десяти разных
        // людей и десять от одного — это разные истории.
        $this->post(route('listings.report', $this->listing), ['reason' => 'spam'], [
            'REMOTE_ADDR' => '203.0.113.9',
        ]);

        $this->assertDatabaseHas('reports', ['reporter_ip' => '203.0.113.9']);
    }

    #[Test]
    public function a_second_report_from_the_same_ip_does_not_error(): void
    {
        // UNIQUE(listing_id, reporter_ip) — антифлуд. Но человек мог просто
        // не заметить, что первая ушла. Показывать ему SQL-ошибку за попытку
        // нам помочь — плохая благодарность.
        $this->post(route('listings.report', $this->listing), ['reason' => 'spam'], ['REMOTE_ADDR' => '203.0.113.9']);

        $this->post(route('listings.report', $this->listing), ['reason' => 'fraud'], ['REMOTE_ADDR' => '203.0.113.9'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(1, Report::where('listing_id', $this->listing->id)->count());
    }

    #[Test]
    public function different_people_can_report_the_same_listing(): void
    {
        $this->post(route('listings.report', $this->listing), ['reason' => 'spam'], ['REMOTE_ADDR' => '203.0.113.9']);
        $this->post(route('listings.report', $this->listing), ['reason' => 'fraud'], ['REMOTE_ADDR' => '198.51.100.4']);

        $this->assertSame(2, Report::where('listing_id', $this->listing->id)->count());
    }

    #[Test]
    public function the_reason_must_be_one_of_the_known_ones(): void
    {
        $this->post(route('listings.report', $this->listing), ['reason' => 'porque-si'])
            ->assertSessionHasErrors('reason');

        $this->assertSame(0, Report::count());
    }

    #[Test]
    public function the_reason_is_required(): void
    {
        $this->post(route('listings.report', $this->listing), [])
            ->assertSessionHasErrors('reason');
    }

    #[Test]
    public function an_optional_email_is_stored(): void
    {
        $this->post(route('listings.report', $this->listing), [
            'reason'         => 'not_mine',
            'reporter_email' => 'victima@example.com',
        ]);

        $this->assertDatabaseHas('reports', ['reporter_email' => 'victima@example.com']);
    }

    #[Test]
    public function a_malformed_email_is_rejected(): void
    {
        $this->post(route('listings.report', $this->listing), [
            'reason'         => 'not_mine',
            'reporter_email' => 'no-es-un-email',
        ])->assertSessionHasErrors('reporter_email');
    }

    #[Test]
    public function an_unpublished_listing_cannot_be_reported(): void
    {
        // Его никто не видит — жаловаться не на что.
        $pending = Listing::factory()->create([
            'user_id' => User::factory(),
            'msisdn'  => '698765432',
            'status'  => 'pending',
            'slug'    => 'pending-'.uniqid(),
        ]);

        $this->post(route('listings.report', $pending), ['reason' => 'spam'])
            ->assertNotFound();
    }

    #[Test]
    public function reports_land_in_the_open_queue_for_a_moderator(): void
    {
        $this->post(route('listings.report', $this->listing), ['reason' => 'not_mine']);

        $this->assertSame(1, Report::open()->count());
        $this->assertSame(1, Report::open()->urgent()->count(), 'not_mine не попала в срочные');
    }
}
