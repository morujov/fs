<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Назначение роли superadmin.
 *
 * ── Почему не создаём пользователя ──────────────────────────────────────
 * Пароля в системе нет, аккаунт заводится только Google-входом. Создать
 * администратора «из воздуха» невозможно и не нужно: сначала владелец
 * заходит на сайт через Google, потом эта команда выдаёт ему роль.
 *
 * Обычный для Laravel `make:filament-user` здесь тоже не сработает — он
 * спрашивает пароль, которого у нас не существует.
 *
 * ── Как пользоваться ────────────────────────────────────────────────────
 *   ADMIN_EMAIL=orujov@gmail.com php artisan db:seed --class=AdminSeeder
 *
 * Email берётся из окружения, а не из кода: зашитый в репозиторий адрес
 * администратора — это и утечка, и грабли при смене владельца.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');

        if (blank($email)) {
            $this->command->warn(
                'ADMIN_EMAIL не задан — роль никому не выдана. '
                .'Это не ошибка: на проде роль выдаётся руками, осознанно.'
            );

            return;
        }

        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->command->error(
                "Пользователь {$email} не найден. Сначала войдите на сайт через Google — "
                .'аккаунт создаётся только так, пароля в системе нет.'
            );

            return;
        }

        $user->update(['role' => 'superadmin']);

        $this->command->info("{$email} — superadmin.");
    }
}
