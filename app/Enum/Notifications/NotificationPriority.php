<?php

namespace App\Enum\Notifications;

enum NotificationPriority: string
{
    case Low    = 'low';
    case Medium = 'medium';
    case High   = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match($this) {
            self::Low    => 'Low',
            self::Medium => 'Medium',
            self::High   => 'High',
            self::Urgent => 'Urgent',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Low    => 'gray',
            self::Medium => 'blue',
            self::High   => 'orange',
            self::Urgent => 'red',
        };
    }
}
