<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Роль в админке.
 *
 * NULL — обычный пользователь. Таких подавляющее большинство, и это
 * значение по умолчанию: доступ в админку не может достаться случайно.
 * Ошибаться здесь можно только в сторону отказа.
 *
 * moderator  — очередь модерации и жалобы. Больше ничего: модератору
 *              незачем править настройки и видеть аудит.
 * superadmin — всё, включая настройки, роли и блок-лист.
 *
 * Отдельная колонка, а не пакет ролей (spatie/permission): у нас две роли
 * и два человека. Тянуть ради этого таблицу ролей, таблицу разрешений и
 * pivot — это сложность, которая никогда не окупится.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['moderator', 'superadmin'])->nullable()->after('status');

            // Ищем администраторов при заходе в панель — редко, но пусть
            // не сканирует таблицу пользователей целиком.
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
