<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Сохранённые поиски и email-алерты (пробел №15).
 *
 * «Сообщи, когда появится 6??77??77» — единственный механизм возврата
 * аудитории. При отсутствии монетизации это главный KPI проекта, поэтому
 * таблица заводится сразу в S1, а не в S9: доклеивать её потом к готовой
 * витрине дороже, чем заложить сейчас.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();

            // Подписаться можно только войдя через Google: email берём оттуда,
            // подтверждать не надо, отписка — по подписанной ссылке.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Человекочитаемое имя, задаёт пользователь: «Мои 777».
            $table->string('label', 120)->nullable();

            // Маска в пользовательском синтаксисе: цифры и '?'.
            // Санитизация до сохранения: ^[0-9?]{0,9}$ — иначе '%' от
            // пользователя превратит алерт в выгрузку всей базы.
            $table->string('pattern', 9)->nullable();

            // Остальные фильтры: провинции, операторы, цена, condition, line_type.
            $table->json('filters')->nullable();

            $table->enum('frequency', ['instant', 'daily', 'weekly'])->default('daily');
            $table->boolean('is_active')->default(true);

            // До какого объявления уже уведомляли — чтобы не слать одно дважды.
            $table->unsignedBigInteger('last_notified_listing_id')->nullable();
            $table->timestamp('last_notified_at')->nullable();

            // Для подписанных ссылок отписки в письмах.
            $table->string('unsubscribe_token', 64)->unique();

            $table->timestamps();

            // Крон рассылки: WHERE is_active AND frequency = ?
            $table->index(['is_active', 'frequency']);
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
    }
};
