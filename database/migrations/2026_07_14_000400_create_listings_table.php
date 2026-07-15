<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Объявления — ядро проекта.
 *
 * Ключевые решения:
 *  - msisdn CHAR(9): только цифры, без +34, первая цифра 6 или 7.
 *    Фиксированная длина обязательна для скорости wildcard-поиска
 *    (LIKE '6__12__34' по CHAR(9)).
 *  - Дубли (пробел №7) закрыты на уровне БД генерируемой колонкой
 *    active_msisdn + UNIQUE. См. комментарий ниже.
 *  - Контакты продавца хранятся открыто, но НИКОГДА не отдаются в HTML
 *    без проверки сессии. Маскировка — на сервере. См. блюпринт 4A.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->nullable()->constrained()->nullOnDelete();

            // --- Товар ---
            $table->char('msisdn', 9);

            // Цена в евро. NULL допустим только при is_negotiable = true
            // («a consultar»), иначе продавцы вобьют 1 или 999999 (пробел №13).
            $table->decimal('price', 10, 2)->nullable();
            $table->boolean('is_negotiable')->default(false);

            // --- Характеристики линии (пробелы №9–11) ---
            $table->foreignId('operator_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('line_type', ['prepago', 'contrato'])->default('prepago');
            $table->boolean('has_permanency')->default(false);
            $table->date('permanency_until')->nullable();

            // new  — номер никогда не активировался
            // used — номер был в обиходе
            // Подпись-подсказка в форме обязательна, иначе заполнят как попало (пробел №12).
            $table->enum('condition', ['new', 'used'])->default('used');

            // Паттерны номера: ["repetido","capicua","escalera","pareja"].
            // Считаются при сохранении в App\Services\Search\PatternTagger.
            // Это и навигация по сайту, и SEO-посадочные (пробел №14).
            // MySQL 5.7.8+ поддерживает JSON — проверено на хосте.
            $table->json('pattern_tags')->nullable();

            // --- География ---
            $table->foreignId('province_id')->constrained();
            $table->string('city', 120)->nullable();

            // --- Описание ---
            $table->text('description')->nullable();
            $table->char('description_lang', 2)->nullable();

            // --- Контакты продавца (маскируются до входа через Google) ---
            $table->string('contact_name', 120);
            $table->string('contact_phone', 20);
            $table->string('contact_email', 190)->nullable();
            $table->boolean('contact_whatsapp')->default(false);

            // --- Жизненный цикл (пробелы №5–6) ---
            $table->enum('status', [
                'draft',      // черновик
                'pending',    // ждёт модерации или OTP
                'active',     // опубликовано
                'rejected',   // отклонено конвейером
                'sold',       // продано, отмечает продавец
                'expired',    // истёк TTL (60 дней)
                'archived',   // снято владельцем
            ])->default('draft');

            // Сумма флагов конвейера модерации. >= порога → ручная очередь.
            $table->unsignedTinyInteger('moderation_score')->default(0);
            $table->string('rejection_reason', 255)->nullable();

            // OTP-SMS на продаваемый номер. Пока NULL — не публикуем (пробел №1).
            // Google подтверждает личность, но не владение номером: проверки разные.
            $table->timestamp('phone_verified_at')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            // --- Метрики ---
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('contact_reveals')->default(0);

            $table->string('slug', 160)->unique();
            $table->timestamps();
            $table->softDeletes();

            // --- Индексы ---
            // Листинг витрины: WHERE status='active' ORDER BY published_at DESC
            $table->index(['status', 'published_at']);

            // Wildcard-поиск с известным префиксом (LIKE '6__...') использует его.
            $table->index('msisdn');

            // Комбинированные фильтры витрины.
            $table->index(['status', 'province_id', 'operator_id', 'price']);

            $table->index(['user_id', 'status']);
            $table->index(['shop_id', 'status']);

            // Очередь модерации в админке.
            $table->index(['status', 'moderation_score']);

            // Крон автоархивации по TTL.
            $table->index(['status', 'expires_at']);
        });

        /**
         * Антидубль на уровне БД (пробел №7).
         *
         * UNIQUE(msisdn, status) не годится: он запретил бы и два `expired`
         * объявления на один номер, а история нам нужна.
         *
         * Генерируемая колонка равна msisdn только у активных, иначе NULL.
         * В MySQL NULL'ы в UNIQUE-индексе не конфликтуют между собой, поэтому
         * получаем ровно то, что нужно: «один активный msisdn — один раз»,
         * при любом количестве неактивных.
         *
         * STORED generated columns есть в MySQL 5.7.6+ — на хосте 5.7.44, проходит.
         * Через DB::statement, потому что синтаксис специфичен для MySQL.
         */
        DB::statement("
            ALTER TABLE `listings`
            ADD COLUMN `active_msisdn` CHAR(9)
                GENERATED ALWAYS AS (IF(`status` = 'active', `msisdn`, NULL)) STORED
        ");

        DB::statement("
            ALTER TABLE `listings`
            ADD UNIQUE INDEX `uniq_active_msisdn` (`active_msisdn`)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
