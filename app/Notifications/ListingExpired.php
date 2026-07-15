<?php

namespace App\Notifications;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * «Твоё объявление истекло».
 *
 * Ссылка на продление тоже подписанная и тоже работает: истёкшее
 * объявление можно вернуть, номер при этом снова займётся — если его
 * за это время не выставил кто-то другой.
 */
class ListingExpired extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Listing $listing) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.expired.subject', [
                'number' => $this->listing->formattedMsisdn(),
            ]))
            ->greeting(__('notifications.greeting', ['name' => $notifiable->name]))
            ->line(__('notifications.expired.line', [
                'number' => $this->listing->formattedMsisdn(),
            ]))
            ->action(
                __('notifications.expired.action'),
                url()->signedRoute('seller.listings.renew', ['listing' => $this->listing])
            );
    }
}
