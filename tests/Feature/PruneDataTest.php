<?php

namespace Tests\Feature;

use App\Models\ContactReveal;
use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\NumberingRangeSeeder;
use Database\Seeders\OperatorSeeder;
use Database\Seeders\ProvinceSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ограничение хранения — GDPR ст. 5 (`data:prune`).
 *
 * Отдельно от GdprTest, который весь про удаление аккаунта (AccountEraser).
 * Ретеншн-команду `PruneOldData` не гонял ни один тест — из-за этого
 * `reports.reporter_ip` спокойно оставался NOT NULL: обезличивает его только
 * прунер, а прунера в тестах не было. Здесь гоняем настоящий (не dry) прогон.
 */
class PruneDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NumberingRangeSeeder::class);
        $this->seed(ProvinceSeeder::class);
        $this->seed(OperatorSeeder::class);
        $this->seed(SettingSeeder::class);
    }

    private function listing(): Listing
    {
        return Listing::factory()->create([
            'user_id' => User::factory(),
            'status'  => 'active',
            'slug'    => 'l-'.uniqid(),
        ]);
    }

    /** created_at в прошлом, мимо кастов и updated_at. */
    private function ageReveal(ContactReveal $r, string $ago): void
    {
        DB::table('contact_reveals')->where('id', $r->id)
            ->update(['created_at' => now()->sub($ago)]);
    }

    #[Test]
    public function it_anonymizes_the_ip_of_an_old_reveal_but_keeps_the_row(): void
    {
        // retention.reveal_ip_days = 90
        $user = User::factory()->create();
        $listing = $this->listing();

        $old = ContactReveal::create([
            'user_id' => $user->id, 'listing_id' => $listing->id,
            'ip' => '203.0.113.7', 'user_agent' => 'Mozilla/5.0',
        ]);
        $this->ageReveal($old, '120 days');

        $fresh = ContactReveal::create([
            'user_id' => $user->id, 'listing_id' => $this->listing()->id,
            'ip' => '203.0.113.9', 'user_agent' => 'Mozilla/5.0',
        ]);

        $this->artisan('data:prune')->assertSuccessful();

        $old = $old->fresh();
        $this->assertNotNull($old, 'строку раскрытия удалили — потеряли счётчик «повтор бесплатно»');
        $this->assertNull($old->ip, 'старый IP не обезличен');
        $this->assertNull($old->user_agent);
        $this->assertSame($user->id, $old->user_id, 'связь с человеком порвана — а она нужна лимитеру');
        $this->assertSame($listing->id, $old->listing_id);

        $this->assertSame('203.0.113.9', $fresh->fresh()->ip, 'свежий IP не должен трогаться раньше срока');
    }

    #[Test]
    public function it_anonymizes_the_reporter_ip_of_an_old_closed_report(): void
    {
        // Это и есть путь, который никто не гонял: reports.reporter_ip был
        // NOT NULL, и настоящий прогон падал бы на «Column cannot be null».
        // retention.report_ip_days = 180, отсчёт от resolved_at.
        $listing = $this->listing();

        $reportId = DB::table('reports')->insertGetId([
            'listing_id'     => $listing->id,
            'reporter_ip'    => '198.51.100.4',
            'reporter_email' => 'denunciante@example.com',
            'reason'         => 'fraud',
            'status'         => 'resolved',
            'resolved_at'    => now()->subDays(200),
            'created_at'     => now()->subDays(210),
            'updated_at'     => now()->subDays(200),
        ]);

        $this->artisan('data:prune')->assertSuccessful();

        $row = DB::table('reports')->find($reportId);
        $this->assertNotNull($row, 'жалобу удалили — а ст. 17(3)(e) велит хранить как возможное доказательство');
        $this->assertNull($row->reporter_ip, 'IP заявителя не обезличен');
        $this->assertNull($row->reporter_email);
    }

    #[Test]
    public function an_open_report_keeps_its_reporter_ip_even_when_old(): void
    {
        // Пока жалоба открыта, IP — часть разбора: срок не идёт.
        $listing = $this->listing();

        $reportId = DB::table('reports')->insertGetId([
            'listing_id'  => $listing->id,
            'reporter_ip' => '198.51.100.9',
            'reason'      => 'fraud',
            'status'      => 'open',
            'resolved_at' => null,
            'created_at'  => now()->subDays(400),
            'updated_at'  => now()->subDays(400),
        ]);

        $this->artisan('data:prune')->assertSuccessful();

        $this->assertSame('198.51.100.9', DB::table('reports')->find($reportId)->reporter_ip);
    }

    #[Test]
    public function a_dry_run_changes_nothing(): void
    {
        $listing = $this->listing();
        $old = ContactReveal::create([
            'user_id' => User::factory()->create()->id, 'listing_id' => $listing->id,
            'ip' => '203.0.113.7',
        ]);
        $this->ageReveal($old, '400 days');

        $this->artisan('data:prune --dry-run')->assertSuccessful();

        $this->assertSame('203.0.113.7', $old->fresh()->ip, 'dry-run изменил данные');
    }
}
