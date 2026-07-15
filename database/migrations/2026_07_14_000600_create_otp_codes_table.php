<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OTP-коды для подтверждения владения продаваемым номером (пробел №1).
 *
 * Без этой проверки люди выставят чужие номера — прямой ущерб третьим лицам
 * и репутационная смерть площадки на второй месяц.
 *
 * Код хранится хэшем: утечка таблицы не должна давать возможности
 * подтвердить чужие объявления.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();

            // Дублируем номер: объявление могут отредактировать, а код
            // должен оставаться привязанным к тому номеру, куда он ушёл.
            $table->char('msisdn', 9);

            $table->string('code_hash', 255);

            // Защита от перебора шестизначного кода.
            $table->unsignedTinyInteger('attempts')->default(0);

            // Сколько SMS уже отправлено по этому объявлению — против
            // накрутки счёта за SMS через кнопку «отправить ещё раз».
            $table->unsignedTinyInteger('sends')->default(1);

            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['listing_id', 'expires_at']);
            $table->index(['msisdn', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
