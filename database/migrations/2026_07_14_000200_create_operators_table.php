<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Операторы мобильной связи Испании.
 *
 * Пропуск этого поля был пробелом №9 в блюпринте: без фильтра по оператору
 * объявление покупателю бесполезно, потому что от оператора зависит
 * процедура портации.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operators', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('slug', 60)->unique();

            // true = виртуальный оператор (OMV/MVNO), работает на чужой сети.
            // Влияет на процедуру портации, полезно показывать покупателю.
            $table->boolean('is_mvno')->default(false);

            // На чьей сети работает MVNO: movistar / vodafone / orange / yoigo.
            // NULL для операторов с собственной сетью.
            $table->string('host_network', 60)->nullable();

            $table->string('logo_path', 255)->nullable();

            // Порядок в выпадающем списке: крупные операторы сверху.
            $table->unsignedSmallInteger('sort_order')->default(100);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operators');
    }
};
