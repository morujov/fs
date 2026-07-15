<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Аудит действий администраторов (пробел №25).
 *
 * Кто удалил объявление, кто разблокировал аккаунт, кто поменял порог лимита.
 * Полиморфная привязка к любой сущности.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // NULL для системных действий (крон, конвейер).
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();

            // listing.approve, listing.reject, user.block, shop.verify,
            // setting.update, report.resolve...
            $table->string('action', 60);

            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            // Что изменилось: {"field": {"old": ..., "new": ...}}
            $table->json('diff')->nullable();

            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['actor_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
