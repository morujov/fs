<?php

namespace App\Notifications;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * «Твоё объявление скоро истечёт».
 *
 * Продление — в один клик из письма по подписанной ссылке: заставлять
 * человека логиниться и искать объявление в кабинете ради одной кнопки
 * значит гарантировать, что он этого не сделает.
 *
 * ShouldQueue: на shared-хостинге письмо в синхронном запросе — это
 * секунды ожидания в браузере и риск таймаута. Очередь на database,
 * supervisor'а там нет.
 */
class ListingExpiringSoon extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Listing $listing) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $days = (int) now()->diffInDays($this->listing->expires_at, false);

        return (new MailMessage)
            ->subject(__('notifications.expiring_soon.subject', [
                'number' => $this->listing->formattedMsisdn(),
            ]))
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name]))
            ->line(__('notifications.expiring_soon.line', [
                'number' => $this->listing->formattedMsisdn(),
                'days'   => max($days, 1),
            ]))
            ->action(
                __('notifications.expiring_soon.action'),
                url()->signedRoute('seller.listings.renew', ['listing' => $this->listing])
            )
            ->line(__('notifications.expiring_soon.sold_hint'));
    }
}
