<?php

use App\Console\Commands\ExpireListings;
use App\Console\Commands\PruneOldData;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Расписание
|--------------------------------------------------------------------------
| На Bluehost нет supervisor, поэтому очереди и расписание крутятся одним
| системным кроном раз в минуту:
|
|   * * * * * cd /home4/clsthmmy/numeros-es && php artisan schedule:run
|
| ВАЖНО (инвариант №10): аккаунт делит процессы с cac.az — боевым сайтом
| с платежами, который уже упирается в лимит. Крон включаем не сразу после
| деплоя, а померив запас, и сразу смотрим, не начал ли cac.az тормозить.
*/

// Истечение объявлений. Раз в сутки ночью: TTL измеряется днями, гонять
// это чаще незачем, а на аккаунте с исчерпанным лимитом процессов каждый
// лишний запуск — это чужой платёжный сайт.
Schedule::command(ExpireListings::class)
    ->dailyAt('04:10')
    ->withoutOverlapping()
    ->onOneServer();

// Очередь. queue:work демоном на shared-хостинге нельзя — это постоянно
// живущий процесс. --stop-when-empty отрабатывает пачку и выходит.
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();

// Ограничение хранения (GDPR ст. 5). Раз в неделю: сроки измеряются
// месяцами, гонять чаще незачем, а на аккаунте, который делит процессы
// с платёжным cac.az, каждый лишний запуск — чужой риск.
//
// Ночь воскресенья: если что-то пойдёт не так, у нас сутки до рабочего
// понедельника cac.az.
Schedule::command(PruneOldData::class)
    ->weeklyOn(0, '03:30')
    ->withoutOverlapping()
    ->onOneServer();
