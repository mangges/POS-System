<?php

namespace App\Notifications;

use App\Events\OrderStatusUpdated;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class OrderStatusPushNotification extends Notification
{
    public function __construct(private OrderStatusUpdated $event) {}

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Status pesanan')
            ->body($this->event->message)
            ->data(['order_id' => $this->event->order->id]);
    }
}
