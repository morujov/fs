<?php

namespace Tests\Feature;

use App\Filament\Widgets\TopRevealAccounts;
use App\Models\ContactReveal;
use App\Models\Listing;
use App\Models\User;
use Database\Seeders\NumberingRangeSeeder;
use Database\Seeders\OperatorSeeder;
use Database\Seeders\ProvinceSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Виджет топ-раскрытий — ради него и строилась админка. Проверяем, что
 * агрегат за 24 часа считается и сортирует скрейпера наверх.
 */
class AdminWidgetTest extends TestCase
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

    private function reveal(User $user, Listing $listing, string $ip, string $when = 'now'): void
    {
        ContactReveal::create([
            'user_id'    => $user->id,
            'listing_id' => $listing->id,
            'ip'         => $ip,
            'user_agent' => 'test',
        ]);
        // created_at выставляем отдельно: фабрика/create ставит now().
        ContactReveal::where('user_id', $user->id)
            ->where('listing_id', $listing->id)
            ->update(['created_at' => $when === 'now' ? now() : now()->sub($when)]);
    }

    #[Test]
    public function it_ranks_the_scraper_on_top_and_ignores_older_than_24h(): void
    {
        $scraper = User::factory()->create(['email' => 'scraper@example.com']);
        $casual  = User::factory()->create(['email' => 'casual@example.com']);
        $stale   = User::factory()->create(['email' => 'stale@example.com']);

        $listings = Listing::factory()->count(6)->create(['status' => 'active']);

        // Скрейпер: 5 раскрытий, 2 IP, за сутки.
        foreach ($listings->take(5) as $i => $l) {
            $this->reveal($scraper, $l, $i < 3 ? '10.0.0.1' : '10.0.0.2');
        }
        // Обычный покупатель: 1 раскрытие.
        $this->reveal($casual, $listings[0], '10.0.0.9');
        // Старое (2 дня назад) — не должно попасть в окно 24ч.
        $this->reveal($stale, $listings[1], '10.0.0.8', '2 days');

        Livewire::actingAs(User::factory()->create(['role' => 'superadmin']))
            ->test(TopRevealAccounts::class)
            ->assertCanSeeTableRecords([$scraper->id, $casual->id])
            ->assertCanNotSeeTableRecords([$stale->id])
            ->assertSee('scraper@example.com')
            ->assertSeeInOrder(['scraper@example.com', 'casual@example.com']);
    }

    #[Test]
    public function the_dashboard_loads_for_a_panel_user(): void
    {
        // Виджеты Filament догружаются Livewire-запросом, поэтому заголовок
        // в исходном HTML дашборда может отсутствовать — проверяем, что сама
        // страница открывается модератору без 500, а сам виджет и его данные
        // проверены в it_ranks_the_scraper_on_top выше через Livewire::test.
        $this->actingAs(User::factory()->create(['role' => 'moderator']))
            ->get('/admin')
            ->assertOk();
    }

    #[Test]
    public function the_widget_heading_comes_from_a_lang_file(): void
    {
        Livewire::actingAs(User::factory()->create(['role' => 'superadmin']))
            ->test(TopRevealAccounts::class)
            ->assertSee(__('admin.top_reveals.heading'));
    }
}
