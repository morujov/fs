<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Добавляет исход `hold` в moderation_logs.result.
 *
 * Изначально enum был ('pass','flag','reject'). При постройке конвейера
 * выяснилось, что этого мало: непройденный OTP — не претензия к объявлению.
 * Продавец ничего плохого не сделал, он просто ещё не ввёл код.
 *
 *   reject — показать ему «отклонено» за то, что он в процессе. Неправда.
 *   flag   — отправить модератору, которому там делать нечего.
 *
 * Отдельной миграцией, а не правкой исходной: та уже в main.
 *
 * ALTER TABLE на enum в MySQL перестраивает таблицу. На пустой/маленькой
 * moderation_logs это мгновенно. Если таблица когда-нибудь вырастет до
 * миллионов строк — такую операцию делать только в окно обслуживания.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE `moderation_logs`
            MODIFY `result` ENUM('pass', 'flag', 'reject', 'hold') NOT NULL
        ");
    }

    public function down(): void
    {
        // Строки с 'hold' пришлось бы куда-то деть, иначе ALTER их обрежет
        // до пустой строки. Ближайший по смыслу — 'flag'.
        DB::statement("UPDATE `moderation_logs` SET `result` = 'flag' WHERE `result` = 'hold'");

        DB::statement("
            ALTER TABLE `moderation_logs`
            MODIFY `result` ENUM('pass', 'flag', 'reject') NOT NULL
        ");
    }
};
