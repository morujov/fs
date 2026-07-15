<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Блок-лист номеров и паттернов.
 *
 * Хранит паттерн в том же синтаксисе, что и поиск: цифры и '?'.
 * Например '70???????' — вся персональная нумерация, '112' — экстренные.
 * Правило конвейера конвертирует '?' в '_' и матчит через LIKE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocklist_numbers', function (Blueprint $table) {
            $table->id();

            // Паттерн: цифры и '?'. До 9 символов.
            $table->string('msisdn_pattern', 9);

            $table->string('reason', 255);

            // NULL = засеяно системой при установке (служебные диапазоны).
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('msisdn_pattern');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocklist_numbers');
    }
};
