<?php

namespace App\Services\Search;

use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;

/**
 * Сборка запроса витрины из параметров URL.
 *
 * Один класс на все фильтры намеренно. Размазать их по контроллеру,
 * скоупам и вьюхе — значит гарантированно однажды забыть `status = active`
 * в одной из веток и показать анониму объявление, которое никто не
 * проверял. Здесь это видно одним взглядом.
 *
 * Ничего не гейтим: поиск, фильтры и карточка открыты анониму и Googlebot
 * (инвариант №4). Гейт стоит только на раскрытии контакта. Загейтить
 * просмотр = убить SEO = убить проект, потому что органика — единственный
 * канал трафика.
 */
final class ListingQuery
{
    public const SORTS = ['newest', 'price_asc', 'price_desc', 'cheapest_first'];

    /**
     * @param  array<string, mixed>  $params  $request->query()
     */
    public function build(array $params): Builder
    {
        $q = Listing::query()
            // Единственная точка, где решается видимость. Не выносить.
            ->active()
            ->with(['province', 'operator', 'shop']);

        $this->applyPattern($q, $params);
        $this->applyProvinces($q, $params);
        $this->applyOperators($q, $params);
        $this->applyPrice($q, $params);
        $this->applyEnums($q, $params);
        $this->applyTags($q, $params);
        $this->applySellerType($q, $params);
        $this->applySort($q, $params);

        return $q;
    }

    /**
     * Wildcard-маска. Вся санитизация — в NumberPatternQuery, здесь её
     * нет и быть не должно: два экземпляра защиты от '%' означают, что
     * однажды поправят один и забудут второй, а цена — вся база контактов.
     */
    private function applyPattern(Builder $q, array $p): void
    {
        $q->matchingPattern($p['q'] ?? null);
    }

    private function applyProvinces(Builder $q, array $p): void
    {
        $ids = $this->ids($p['province'] ?? null);

        if ($ids !== []) {
            $q->whereIn('province_id', $ids);
        }
    }

    private function applyOperators(Builder $q, array $p): void
    {
        $ids = $this->ids($p['operator'] ?? null);

        if ($ids !== []) {
            $q->whereIn('operator_id', $ids);
        }
    }

    private function applyPrice(Builder $q, array $p): void
    {
        $min = isset($p['price_min']) ? (float) $p['price_min'] : null;
        $max = isset($p['price_max']) ? (float) $p['price_max'] : null;

        if ($min === null && $max === null) {
            return;
        }

        // Договорные объявления цены не имеют. При фильтре по цене их
        // прячем: человек, задавший «до 300 €», не хочет видеть «a consultar».
        $q->whereNotNull('price');

        if ($min !== null) {
            $q->where('price', '>=', $min);
        }

        if ($max !== null) {
            $q->where('price', '<=', $max);
        }
    }

    private function applyEnums(Builder $q, array $p): void
    {
        if (in_array($p['condition'] ?? null, ['new', 'used'], true)) {
            $q->where('condition', $p['condition']);
        }

        if (in_array($p['line_type'] ?? null, ['prepago', 'contrato'], true)) {
            $q->where('line_type', $p['line_type']);
        }

        // Только явное 'libre' что-то значит: отсутствие параметра —
        // «неважно», а не «покажи с permanencia».
        if (($p['permanency'] ?? null) === 'libre') {
            $q->where('has_permanency', false);
        } elseif (($p['permanency'] ?? null) === 'con') {
            $q->where('has_permanency', true);
        }
    }

    /**
     * Фильтр по «красоте» номера. Это же и SEO-посадочные:
     * /numeros-repetidos, /numeros-capicua.
     */
    private function applyTags(Builder $q, array $p): void
    {
        $tags = array_values(array_intersect(
            (array) ($p['tag'] ?? []),
            PatternTagger::TAGS
        ));

        if ($tags === []) {
            return;
        }

        // JSON_CONTAINS работает в MySQL 5.7 — прод именно на нём.
        $q->where(function (Builder $sub) use ($tags) {
            foreach ($tags as $tag) {
                $sub->orWhereJsonContains('pattern_tags', $tag);
            }
        });
    }

    private function applySellerType(Builder $q, array $p): void
    {
        if (($p['seller'] ?? null) === 'shop') {
            $q->whereNotNull('shop_id');
        } elseif (($p['seller'] ?? null) === 'private') {
            $q->whereNull('shop_id');
        }
    }

    private function applySort(Builder $q, array $p): void
    {
        $sort = in_array($p['sort'] ?? null, self::SORTS, true) ? $p['sort'] : 'newest';

        match ($sort) {
            // Договорные в конец при сортировке по цене: у них цены нет,
            // и NULL первым выглядел бы как «самое дешёвое».
            'price_asc', 'cheapest_first' => $q->orderByRaw('price IS NULL, price ASC')->orderByDesc('id'),
            'price_desc' => $q->orderByRaw('price IS NULL, price DESC')->orderByDesc('id'),
            default      => $q->orderByDesc('published_at')->orderByDesc('id'),
        };
    }

    /**
     * Идентификаторы из query-строки: ?province[]=1&province[]=2 либо
     * ?province=1,2. Всё, что не целое число, выбрасываем молча —
     * это мусор или попытка что-то подсунуть, а не запрос пользователя.
     *
     * @return list<int>
     */
    private function ids(mixed $raw): array
    {
        if ($raw === null) {
            return [];
        }

        $items = is_array($raw) ? $raw : explode(',', (string) $raw);

        return array_values(array_filter(array_map(
            fn ($v) => filter_var($v, FILTER_VALIDATE_INT) ?: null,
            $items
        )));
    }
}
