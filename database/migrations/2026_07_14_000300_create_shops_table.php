<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Магазины (tiendas).
 *
 * Отдельная сущность, а не галочка в users: у магазина есть витрина, slug,
 * логотип и много объявлений. Это был пробел №17 блюпринта.
 *
 * NIF/CIF проверяется алгоритмом контрольной буквы в App\Rules\NifCif —
 * иначе «магазином» назовётся кто угодно (пробел №28).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('name', 120);
            $table->string('slug', 140)->unique();

            // NIF (частный предприниматель) или CIF (юрлицо). Хранится в верхнем
            // регистре без пробелов и дефисов. Уникален: один CIF — один магазин.
            $table->string('nif_cif', 12)->unique();

            $table->string('address', 200)->nullable();
            $table->string('city', 120)->nullable();
            $table->foreignId('province_id')->nullable()->constrained()->nullOnDelete();

            $table->string('website', 255)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->text('description')->nullable();

            // pending  — заявка подана, объявления магазина не публикуются
            // verified — проверен, объявления идут в общий конвейер
            // rejected — отказ
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->string('rejection_reason', 255)->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
