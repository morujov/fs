<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Operator;
use App\Models\Province;
use App\Models\User;
use Database\Seeders\NumberingRangeSeeder;
use Database\Seeders\OperatorSeeder;
use Database\Seeders\ProvinceSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Витрина.
 *
 * Инвариант №4: поиск, фильтры, листинг и карточка открыты анониму и
 * Googlebot. Загейтить просмотр = убить SEO = убить проект, потому что
 * органика — единственный канал трафика.
 *
 * Второе, что тут проверяется, — что витрина не показывает лишнего:
 * ни неопубликованных объявлений, ни базы целиком в ответ на '%'.
 */
class BrowseTest extends TestCase
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

    private function listing(array $attrs = []): Listing
    {
        return Listing::factory()->create(array_merge([
            'user_id' => User::factory(),
            'status'  => 'active',
            'slug'    => 'l-'.uniqid(),
        ], $attrs));
    }

    // -----------------------------------------------------------------
    // Открытость — инвариант №4
    // -----------------------------------------------------------------

    #[Test]
    public function a_guest_can_browse(): void
    {
        $this->listing(['msisdn' => '612345678']);

        $this->get(route('home'))->assertOk()->assertSee('612 34 56 78');
    }

    #[Test]
    public function a_guest_can_search_and_filter(): void
    {
        $this->listing(['msisdn' => '612345678']);

        $this->get(route('home', ['q' => '612', 'sort' => 'price_asc']))->assertOk();
    }

    #[Test]
    public function a_guest_can_open_a_listing_card(): void
    {
        $listing = $this->listing(['msisdn' => '612345678']);

        $this->get(route('listings.show', $listing))->assertOk();
    }

    #[Test]
    public function a_guest_sees_the_price(): void
    {
        $this->listing(['msisdn' => '612345678', 'price' => 250, 'is_negotiable' => false]);

        $this->get(route('home'))->assertOk()->assertSee('250');
    }

    // -----------------------------------------------------------------
    // Что витрина НЕ показывает
    // -----------------------------------------------------------------

    #[Test]
    public function only_active_listings_are_listed(): void
    {
        $this->listing(['msisdn' => '612345678']);

        foreach (['pending', 'rejected', 'sold', 'expired', 'archived', 'draft'] as $status) {
            $this->listing(['msisdn' => $this->uniqueMsisdn(), 'status' => $status]);
        }

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(__('browse.results', ['count' => 1]));
    }

    #[Test]
    public function an_unpublished_listing_card_returns_404(): void
    {
        // 404, а не 403: сообщать, что объявление есть, но скрыто, —
        // лишняя утечка.
        $pending = $this->listing(['msisdn' => '698765432', 'status' => 'pending']);

        $this->get(route('listings.show', $pending))->assertNotFound();
    }

    // -----------------------------------------------------------------
    // Wildcard-поиск через HTTP
    // -----------------------------------------------------------------

    #[Test]
    public function the_wildcard_mask_filters_results(): void
    {
        $this->listing(['msisdn' => '612345678']);
        $this->listing(['msisdn' => '698765432']);

        $this->get(route('home', ['q' => '612??5678']))
            ->assertOk()
            ->assertSee('612 34 56 78')
            ->assertDontSee('698 76 54 32');
    }

    #[Test]
    public function a_percent_sign_in_the_search_does_not_dump_the_database(): void
    {
        // Сквозная проверка защиты, покрытой юнит-тестами на уровне
        // NumberPatternQuery. Здесь — что она реально стоит на пути
        // HTTP-запроса, а не осталась в классе, который никто не зовёт.
        $this->listing(['msisdn' => '612345678']);
        $this->listing(['msisdn' => '698765432']);

        // '%' вырезается → маска пустая → фильтра нет → видно всё.
        // Это нормально: пустой поиск и должен показывать всё. Опасно было
        // бы обратное — если бы '%' дошёл до LIKE и сработал как wildcard
        // ПОВЕРХ других фильтров.
        $this->get(route('home', ['q' => '%']))->assertOk();

        // А вот здесь видно суть: с '%' в маске фильтр обязан вести себя
        // как «612», а не как «всё, что начинается на 612 и что угодно
        // дальше по всей базе».
        $this->get(route('home', ['q' => '612%']))
            ->assertOk()
            ->assertSee('612 34 56 78')
            ->assertDontSee('698 76 54 32');
    }

    #[Test]
    public function the_search_field_never_echoes_back_anything_but_digits_and_question_marks(): void
    {
        // Значение возвращается в input. Если туда протечёт ввод как есть,
        // получим и XSS-поверхность, и путаницу.
        $html = $this->get(route('home', ['q' => '6<script>alert(1)</script>1']))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    // -----------------------------------------------------------------
    // Фильтры
    // -----------------------------------------------------------------

    #[Test]
    public function filtering_by_province_works(): void
    {
        $madrid = Province::where('slug', 'madrid')->first();
        $barcelona = Province::where('slug', 'barcelona')->first();

        $this->listing(['msisdn' => '612345678', 'province_id' => $madrid->id]);
        $this->listing(['msisdn' => '698765432', 'province_id' => $barcelona->id]);

        $this->get(route('home', ['province' => [$madrid->id]]))
            ->assertOk()
            ->assertSee('612 34 56 78')
            ->assertDontSee('698 76 54 32');
    }

    #[Test]
    public function filtering_by_operator_works(): void
    {
        $movistar = Operator::where('slug', 'movistar')->first();
        $vodafone = Operator::where('slug', 'vodafone')->first();

        $this->listing(['msisdn' => '612345678', 'operator_id' => $movistar->id]);
        $this->listing(['msisdn' => '698765432', 'operator_id' => $vodafone->id]);

        $this->get(route('home', ['operator' => [$movistar->id]]))
            ->assertOk()
            ->assertSee('612 34 56 78')
            ->assertDontSee('698 76 54 32');
    }

    #[Test]
    public function filtering_by_price_hides_negotiable_listings(): void
    {
        // Человек, задавший «до 300 €», не хочет видеть «a consultar».
        $this->listing(['msisdn' => '612345678', 'price' => 100, 'is_negotiable' => false]);
        $this->listing(['msisdn' => '698765432', 'price' => null, 'is_negotiable' => true]);

        $this->get(route('home', ['price_max' => 300]))
            ->assertOk()
            ->assertSee('612 34 56 78')
            ->assertDontSee('698 76 54 32');
    }

    #[Test]
    public function filtering_by_pattern_tag_works(): void
    {
        $this->listing(['msisdn' => '666666666', 'pattern_tags' => ['repetido', 'capicua', 'facil']]);
        $this->listing(['msisdn' => '639284751', 'pattern_tags' => []]);

        $this->get(route('home', ['tag' => ['repetido']]))
            ->assertOk()
            ->assertSee('666 66 66 66')
            ->assertDontSee('639 28 47 51');
    }

    #[Test]
    public function an_unknown_tag_is_ignored_rather_than_breaking_the_page(): void
    {
        $this->listing(['msisdn' => '612345678']);

        $this->get(route('home', ['tag' => ['../../etc/passwd', 'repetido']]))->assertOk();
    }

    #[Test]
    public function garbage_in_the_id_filters_is_ignored(): void
    {
        $this->listing(['msisdn' => '612345678']);

        $this->get(route('home', ['province' => ['abc', '1 OR 1=1'], 'operator' => 'null']))->assertOk();
    }

    // -----------------------------------------------------------------
    // Сортировка и пагинация
    // -----------------------------------------------------------------

    #[Test]
    public function negotiable_listings_go_last_when_sorting_by_price(): void
    {
        // У них цены нет, и NULL первым выглядел бы как «самое дешёвое».
        $this->listing(['msisdn' => '612345678', 'price' => 500, 'is_negotiable' => false]);
        $this->listing(['msisdn' => '698765432', 'price' => null, 'is_negotiable' => true]);
        $this->listing(['msisdn' => '677111222', 'price' => 100, 'is_negotiable' => false]);

        $html = $this->get(route('home', ['sort' => 'price_asc']))->assertOk()->getContent();

        $cheap = strpos($html, '677 11 12 22');
        $mid   = strpos($html, '612 34 56 78');
        $nego  = strpos($html, '698 76 54 32');

        $this->assertLessThan($mid, $cheap, 'дешёвое не первым');
        $this->assertLessThan($nego, $mid, 'договорное не последним');
    }

    #[Test]
    public function pagination_uses_the_configured_page_size(): void
    {
        Listing::factory()->count(25)->create(['user_id' => User::factory(), 'status' => 'active']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('page=2');
    }

    #[Test]
    public function filters_survive_pagination(): void
    {
        // Иначе вторая страница молча сбрасывает поиск, и человек
        // не понимает, куда делись его результаты.
        Listing::factory()->count(25)->create([
            'user_id'   => User::factory(),
            'status'    => 'active',
            'condition' => 'new',
        ]);

        $this->get(route('home', ['condition' => 'new']))
            ->assertOk()
            ->assertSee('condition=new');
    }

    private function uniqueMsisdn(): string
    {
        static $n = 0;

        return '6'.str_pad((string) (10000000 + $n++), 8, '0', STR_PAD_LEFT);
    }
}
