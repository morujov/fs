<?php

namespace Database\Seeders;

use App\Models\Province;
use Illuminate\Database\Seeder;

/**
 * 52 провинции Испании: 50 провинций + автономные города Сеута и Мелилья.
 * Коды — официальные INE (01–52), они же префикс почтового индекса.
 *
 * Названия на ca/gl/eu заполнены только там, где официальное название
 * действительно отличается. Это не перевод: Girona и Gerona — два
 * официальных имени, а не одно в двух написаниях. Где отличий нет — NULL,
 * и localizedName() откатится на кастильский.
 */
class ProvinceSeeder extends Seeder
{
    public function run(): void
    {
        // [code, es, ca, gl, eu, en, community, slug]
        $rows = [
            ['01', 'Álava', null, null, 'Araba', null, 'País Vasco', 'alava'],
            ['02', 'Albacete', null, null, null, null, 'Castilla-La Mancha', 'albacete'],
            ['03', 'Alicante', 'Alacant', null, null, null, 'Comunidad Valenciana', 'alicante'],
            ['04', 'Almería', null, null, null, null, 'Andalucía', 'almeria'],
            ['05', 'Ávila', null, null, null, null, 'Castilla y León', 'avila'],
            ['06', 'Badajoz', null, null, null, null, 'Extremadura', 'badajoz'],
            ['07', 'Baleares', 'Illes Balears', null, null, 'Balearic Islands', 'Illes Balears', 'baleares'],
            ['08', 'Barcelona', null, null, null, null, 'Cataluña', 'barcelona'],
            ['09', 'Burgos', null, null, null, null, 'Castilla y León', 'burgos'],
            ['10', 'Cáceres', null, null, null, null, 'Extremadura', 'caceres'],
            ['11', 'Cádiz', null, null, null, null, 'Andalucía', 'cadiz'],
            ['12', 'Castellón', 'Castelló', null, null, null, 'Comunidad Valenciana', 'castellon'],
            ['13', 'Ciudad Real', null, null, null, null, 'Castilla-La Mancha', 'ciudad-real'],
            ['14', 'Córdoba', null, null, null, null, 'Andalucía', 'cordoba'],
            ['15', 'La Coruña', null, 'A Coruña', null, null, 'Galicia', 'a-coruna'],
            ['16', 'Cuenca', null, null, null, null, 'Castilla-La Mancha', 'cuenca'],
            ['17', 'Gerona', 'Girona', null, null, null, 'Cataluña', 'girona'],
            ['18', 'Granada', null, null, null, null, 'Andalucía', 'granada'],
            ['19', 'Guadalajara', null, null, null, null, 'Castilla-La Mancha', 'guadalajara'],
            ['20', 'Guipúzcoa', null, null, 'Gipuzkoa', null, 'País Vasco', 'gipuzkoa'],
            ['21', 'Huelva', null, null, null, null, 'Andalucía', 'huelva'],
            ['22', 'Huesca', null, null, null, null, 'Aragón', 'huesca'],
            ['23', 'Jaén', null, null, null, null, 'Andalucía', 'jaen'],
            ['24', 'León', null, null, null, null, 'Castilla y León', 'leon'],
            ['25', 'Lérida', 'Lleida', null, null, null, 'Cataluña', 'lleida'],
            ['26', 'La Rioja', null, null, null, null, 'La Rioja', 'la-rioja'],
            ['27', 'Lugo', null, null, null, null, 'Galicia', 'lugo'],
            ['28', 'Madrid', null, null, null, null, 'Comunidad de Madrid', 'madrid'],
            ['29', 'Málaga', null, null, null, null, 'Andalucía', 'malaga'],
            ['30', 'Murcia', null, null, null, null, 'Región de Murcia', 'murcia'],
            ['31', 'Navarra', null, null, 'Nafarroa', null, 'Comunidad Foral de Navarra', 'navarra'],
            ['32', 'Orense', null, 'Ourense', null, null, 'Galicia', 'ourense'],
            ['33', 'Asturias', null, null, null, null, 'Principado de Asturias', 'asturias'],
            ['34', 'Palencia', null, null, null, null, 'Castilla y León', 'palencia'],
            ['35', 'Las Palmas', null, null, null, null, 'Canarias', 'las-palmas'],
            ['36', 'Pontevedra', null, null, null, null, 'Galicia', 'pontevedra'],
            ['37', 'Salamanca', null, null, null, null, 'Castilla y León', 'salamanca'],
            ['38', 'Santa Cruz de Tenerife', null, null, null, null, 'Canarias', 'santa-cruz-de-tenerife'],
            ['39', 'Cantabria', null, null, null, null, 'Cantabria', 'cantabria'],
            ['40', 'Segovia', null, null, null, null, 'Castilla y León', 'segovia'],
            ['41', 'Sevilla', null, null, null, 'Seville', 'Andalucía', 'sevilla'],
            ['42', 'Soria', null, null, null, null, 'Castilla y León', 'soria'],
            ['43', 'Tarragona', null, null, null, null, 'Cataluña', 'tarragona'],
            ['44', 'Teruel', null, null, null, null, 'Aragón', 'teruel'],
            ['45', 'Toledo', null, null, null, null, 'Castilla-La Mancha', 'toledo'],
            ['46', 'Valencia', 'València', null, null, null, 'Comunidad Valenciana', 'valencia'],
            ['47', 'Valladolid', null, null, null, null, 'Castilla y León', 'valladolid'],
            ['48', 'Vizcaya', null, null, 'Bizkaia', null, 'País Vasco', 'bizkaia'],
            ['49', 'Zamora', null, null, null, null, 'Castilla y León', 'zamora'],
            ['50', 'Zaragoza', null, null, null, 'Saragossa', 'Aragón', 'zaragoza'],
            ['51', 'Ceuta', null, null, null, null, 'Ceuta', 'ceuta'],
            ['52', 'Melilla', null, null, null, null, 'Melilla', 'melilla'],
        ];

        foreach ($rows as [$code, $es, $ca, $gl, $eu, $en, $community, $slug]) {
            Province::updateOrCreate(
                ['code' => $code],
                [
                    'name_es'   => $es,
                    'name_ca'   => $ca,
                    'name_gl'   => $gl,
                    'name_eu'   => $eu,
                    'name_en'   => $en,
                    'community' => $community,
                    'slug'      => $slug,
                ]
            );
        }
    }
}
