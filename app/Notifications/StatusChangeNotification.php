<?php

namespace App\Notifications;

class StatusChangeNotification extends BaseNotification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(...$params)
    {
        parent::__construct();
    }

    /**
     * Get the mail representation of the notification.
     */
    // public function toMail(object $notifiable): Mailable
    // {
    //     // return (new InvoicePaidMailable($this->invoice))
    //     //             ->to($notifiable->email);
    // }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
