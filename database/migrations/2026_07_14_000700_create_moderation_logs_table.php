<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Лог конвейера модерации: по строке на каждое сработавшее правило.
 *
 * Нужен, чтобы отвечать на вопрос «почему это объявление отклонили» через
 * полгода, и чтобы видеть, какие правила ложно срабатывают чаще других.
 * Без него настройка порогов превращается в гадание.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();

            // Идентификатор правила: format, blocklist, duplicate, otp_missing,
            // price_range, contacts_in_text, rate_limit, honeypot, shop_unverified...
            $table->string('rule', 60);

            $table->enum('result', ['pass', 'flag', 'reject']);

            // Детали срабатывания: что именно нашли, какой был порог.
            $table->json('payload')->nullable();

            // 'system' для автоправил, иначе users.id модератора.
            $table->string('actor', 60)->default('system');

            $table->timestamp('created_at')->useCurrent();

            $table->index(['listing_id', 'created_at']);

            // Аналитика: какое правило сколько раз отклонило за период.
            $table->index(['rule', 'result', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_logs');
    }
};
