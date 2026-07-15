<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Жалобы на объявления — denunciar anuncio (пробел №8).
 *
 * Обязательный элемент доски объявлений и юридическая защита площадки:
 * при претензии мы должны показать, что механизм реагирования существует
 * и что по жалобе принято решение.
 *
 * Жалобу можно оставить без входа через Google — иначе о чужом номере
 * никто не сообщит, а именно эти жалобы самые важные.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();

            // NULL, если жалоба анонимная.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('reporter_ip', 45);
            $table->string('reporter_email', 190)->nullable();

            // not_mine     — «это мой номер, я его не продаю» (высший приоритет)
            // fraud        — мошенничество
            // wrong_info   — неверные данные
            // spam         — спам/реклама
            // sold         — уже продан
            // other        — прочее
            $table->enum('reason', ['not_mine', 'fraud', 'wrong_info', 'spam', 'sold', 'other']);
            $table->text('comment')->nullable();

            $table->enum('status', ['open', 'reviewing', 'resolved', 'dismissed'])->default('open');
            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['listing_id', 'status']);

            // Очередь модератора: not_mine и fraud разбираются первыми.
            $table->index(['status', 'reason']);

            // Антифлуд: один IP — одна жалоба на объявление.
            $table->unique(['listing_id', 'reporter_ip'], 'uniq_listing_reporter');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
