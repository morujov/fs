<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Database\Seeders\NumberingRangeSeeder;
use Database\Seeders\OperatorSeeder;
use Database\Seeders\ProvinceSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * «Один номер — одно активное объявление» держится генерируемой колонкой:
 *
 *   active_msisdn CHAR(9) GENERATED ALWAYS AS (IF(status='active', msisdn, NULL)) STORED
 *   UNIQUE INDEX uniq_active_msisdn (active_msisdn)
 *
 * Гарантия на уровне БД, а не проверкой в коде: проверка в коде проигрывает
 * гонке между двумя одновременными подачами. Если этот инвариант отвалится,
 * один номер смогут выставить пятеро — и это прямое мошенничество.
 *
 * Тест требует MySQL: IF() и STORED-колонки — его синтаксис, на SQLite
 * миграция не пройдёт. См. комментарий в phpunit.xml.
 */
class ListingUniquenessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NumberingRangeSeeder::class);
        $this->seed(ProvinceSeeder::class);
        $this->seed(OperatorSeeder::class);
    }

    private function make(string $msisdn, string $status = 'active'): Listing
    {
        return Listing::factory()->create([
            'user_id' => User::factory(),
            'msisdn'  => $msisdn,
            'status'  => $status,
            'slug'    => $msisdn.'-'.uniqid(),
        ]);
    }

    #[Test]
    public function a_second_active_listing_for_the_same_number_is_rejected_by_the_database(): void
    {
        $this->make('612345678');

        $this->expectException(QueryException::class);
        $this->make('612345678');
    }

    #[Test]
    public function the_same_number_may_repeat_among_inactive_listings(): void
    {
        // История нужна: номер продали, потом владелец сменился и выставил
        // снова. UNIQUE(msisdn, status) запретил бы и это — поэтому
        // генерируемая колонка, а не составной уникальный индекс.
        $this->make('612345678', 'expired');
        $this->make('612345678', 'sold');
        $this->make('612345678', 'archived');
        $this->make('612345678', 'pending');

        $this->assertSame(4, Listing::where('msisdn', '612345678')->count());
    }

    #[Test]
    public function one_active_listing_may_coexist_with_many_inactive_ones(): void
    {
        $this->make('612345678', 'expired');
        $this->make('612345678', 'sold');
        $this->make('612345678', 'active');

        $this->assertSame(1, Listing::active()->where('msisdn', '612345678')->count());
        $this->assertSame(3, Listing::where('msisdn', '612345678')->count());
    }

    #[Test]
    public function a_number_can_be_reactivated_after_the_previous_listing_is_closed(): void
    {
        $first = $this->make('612345678');

        // Пока первое активно — второе не пройдёт.
        try {
            $this->make('612345678');
            $this->fail('БД пропустила второе активное объявление');
        } catch (QueryException) {
            // ожидаемо
        }

        // Закрыли первое — номер освободился.
        $first->update(['status' => 'sold']);

        $second = $this->make('612345678');

        $this->assertSame('active', $second->fresh()->status);
        $this->assertSame(1, Listing::active()->where('msisdn', '612345678')->count());
    }

    #[Test]
    public function deactivating_a_listing_frees_the_number_immediately(): void
    {
        $l = $this->make('612345678');

        $this->assertSame(
            '612345678',
            $l->fresh()->active_msisdn,
            'у активного объявления генерируемая колонка обязана равняться номеру'
        );

        $l->update(['status' => 'sold']);

        $this->assertNull(
            $l->fresh()->active_msisdn,
            'у неактивного объявления колонка обязана стать NULL — иначе номер останется занят навсегда'
        );
    }

    #[Test]
    public function the_generated_column_is_maintained_by_the_database_not_by_the_code(): void
    {
        // Пишем напрямую, минуя модель: колонка обязана посчитаться сама.
        // Если её начнёт заполнять код — появится путь в обход гарантии.
        $l = $this->make('612345678');

        DB::table('listings')->where('id', $l->id)->update(['status' => 'expired']);

        $this->assertNull(
            DB::table('listings')->where('id', $l->id)->value('active_msisdn'),
            'колонка не пересчиталась при обновлении в обход Eloquent'
        );
    }

    #[Test]
    public function different_numbers_do_not_collide(): void
    {
        $this->make('612345678');
        $this->make('612345679');
        $this->make('712345678');

        $this->assertSame(3, Listing::active()->count());
    }
}
