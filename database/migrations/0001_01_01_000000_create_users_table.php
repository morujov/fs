<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Пользователи.
 *
 * Авторизация только через Google OAuth. Колонки `password` и таблицы
 * `password_reset_tokens` нет намеренно — сбрасывать нечего. Email приходит
 * от Google уже верифицированным, поэтому `email_verified_at` тоже не нужен.
 * См. блюпринт, раздел 4A.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // --- Google identity ---
            $table->string('google_id', 64)->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('avatar_url', 512)->nullable();

            // Роль продавца. NULL, пока пользователь не подал первое объявление:
            // на входе мы не знаем и не спрашиваем, покупатель он или продавец.
            $table->enum('seller_type', ['private', 'shop'])->nullable();

            // Контактный телефон продавца — не путать с продаваемым номером.
            // Формат E.164 (+34...). Заполняется при подаче объявления.
            $table->string('phone', 20)->nullable();

            $table->string('locale', 5)->default('es');

            // active  — обычный пользователь
            // flagged — подозрение на скрейпинг, ждёт ручного разбора
            // blocked — вход закрыт
            $table->enum('status', ['active', 'flagged', 'blocked'])->default('active');

            // Денормализованные счётчики раскрытий контактов: чтобы не гонять
            // COUNT(*) по contact_reveals при каждой проверке лимита.
            $table->unsignedInteger('reveal_count_total')->default(0);
            $table->timestamp('last_reveal_at')->nullable();

            $table->rememberToken();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
