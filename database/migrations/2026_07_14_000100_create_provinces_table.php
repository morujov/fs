<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 52 провинции Испании (50 провинций + Сеута и Мелилья).
 *
 * Названия хранятся колонками, а не в lang-файлах: это данные справочника,
 * а не строки интерфейса. Названия провинций официально различаются между
 * кастильским, каталанским, галисийским и баскским (Girona/Gerona,
 * A Coruña/La Coruña, Araba/Álava) — это не перевод, а официальная топонимика.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();

            // Официальный код INE (01–52), он же префикс почтового индекса.
            $table->char('code', 2)->unique();

            $table->string('name_es', 80);
            $table->string('name_ca', 80)->nullable();
            $table->string('name_gl', 80)->nullable();
            $table->string('name_eu', 80)->nullable();
            $table->string('name_en', 80)->nullable();

            // Автономное сообщество (Comunidad Autónoma) — для группировки в UI.
            $table->string('community', 80);

            // ЧПУ для SEO-страниц: /es/provincia/madrid
            $table->string('slug', 80)->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provinces');
    }
};
