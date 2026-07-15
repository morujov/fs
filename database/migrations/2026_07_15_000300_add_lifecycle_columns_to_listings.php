<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Колонки жизненного цикла.
 *
 * `expiry_notified_at` — чтобы не слать письмо об истечении каждый день
 * все семь дней подряд. Человек отпишется после третьего, и потом мы не
 * достучимся до него ничем, включая алерты по сохранённым поискам —
 * а это единственный механизм возврата аудитории.
 *
 * `renewals_count` — сколько раз продлевали. Нужен не для лимита, а чтобы
 * увидеть номер, который висит год и не продаётся: либо цена нереальна,
 * либо продавец забыл. И то и другое стоит показать в админке.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->timestamp('expiry_notified_at')->nullable()->after('expires_at');
            $table->unsignedSmallInteger('renewals_count')->default(0)->after('expiry_notified_at');
            $table->timestamp('sold_at')->nullable()->after('renewals_count');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['expiry_notified_at', 'renewals_count', 'sold_at']);
        });
    }
};
