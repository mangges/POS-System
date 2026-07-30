<?php

namespace App\Models;

use App\Enum\Notifications\NotificationPriority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AppNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'title',
        'message',
        'record_type',
        'record_id',
        'priority',
        'data',
        'created_by',
    ];

    protected $casts = [
        'priority' => NotificationPriority::class,
        'data' => 'array',
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(AppNotificationRecipient::class, 'notification_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function record(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'record_type', 'record_id');
    }
}
