<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Разрешить осиротение записей при удалении аккаунта — GDPR ст. 17.
 *
 * ── Что было не так ─────────────────────────────────────────────────────
 * `listings.user_id` и `contact_reveals.user_id` объявлены NOT NULL
 * с `cascadeOnDelete`. Это означало ровно две плохие вещи:
 *
 *  1. Удаление аккаунта СНОСИЛО бы все его объявления каскадом — вместе
 *     с историей рынка, по которой видно, за сколько уходят номера, и
 *     вместе со статистикой раскрытий у чужих объявлений.
 *
 *  2. Обезличить их (user_id → NULL) было невозможно: констрейнт NOT NULL.
 *
 * Право на удаление превращалось в выбор между «нарушить GDPR» и
 * «уничтожить данные, которые к персональным не относятся».
 *
 * ── Как правильно ───────────────────────────────────────────────────────
 * nullable + nullOnDelete. Удаление человека убирает ЛИЧНОСТЬ, а не факты:
 * объявление остаётся рыночной историей без владельца, раскрытие остаётся
 * счётчиком без того, кто раскрыл.
 *
 * NULL в UNIQUE(user_id, listing_id) на contact_reveals не конфликтует,
 * так что несколько обезличенных строк уживаются.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('contact_reveals', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('contact_reveals', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Обратно нельзя без потери данных: осиротевшие строки некуда
        // привязать. Если откат действительно нужен — сначала решить,
        // что делать с ними, руками.
        Schema::table('contact_reveals', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
