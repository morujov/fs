<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Настройки, правятся из админки без деплоя.
 *
 * Сюда идут все пороги: лимиты раскрытий, TTL объявления, порог ручной
 * модерации, диапазон цен, фича-флаги. Хардкодить их в config/ нельзя —
 * на shared-хостинге каждый деплой это ручной git pull в рвущемся терминале.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();

            // Для приведения типа при чтении и для рендера правильного
            // контрола в админке.
            $table->enum('type', ['string', 'int', 'float', 'bool', 'json'])->default('string');

            // Группировка вкладок в админке: limits, moderation, listing, features.
            $table->string('group', 40)->default('general');

            $table->string('description', 255)->nullable();
            $table->timestamps();

            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
