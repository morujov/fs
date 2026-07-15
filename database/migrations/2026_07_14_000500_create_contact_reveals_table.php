<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Лог раскрытий контактов.
 *
 * Google-аккаунт стоит ноль евро, поэтому сам по себе OAuth скрейпинг
 * не останавливает — он только повышает цену. Останавливают лимиты,
 * а лимиты считаются по этой таблице. Она же — источник для виджета
 * «топ аккаунтов по раскрытиям за 24 ч», где скрейпер виден в первый день.
 *
 * Пороги (в settings, правятся из админки без деплоя):
 *   5/мин на аккаунт, 20/сутки на аккаунт, 40/сутки на IP,
 *   3 аккаунта с одного IP, 50/сутки → автоблок.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_reveals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();

            // IPv6 влезает в 45 символов.
            $table->string('ip', 45);
            $table->string('user_agent', 512)->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Лимит на аккаунт за period.
            $table->index(['user_id', 'created_at']);

            // Лимит на IP + детект «много аккаунтов с одного IP».
            $table->index(['ip', 'created_at']);

            // Статистика по объявлению.
            $table->index(['listing_id', 'created_at']);

            // Один аккаунт, повторно открывший тот же контакт, не должен
            // расходовать лимит второй раз — проверяем этой парой.
            $table->unique(['user_id', 'listing_id'], 'uniq_user_listing_reveal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_reveals');
    }
};
