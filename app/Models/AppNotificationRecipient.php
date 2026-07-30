<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AppNotificationRecipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'notification_id',
        'recipient_scope',
        'recipient_role',
        'recipient_type',
        'recipient_id',
        'is_read',
        'read_at',
        'is_archived',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'is_archived' => 'boolean',
    ];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(AppNotification::class, 'notification_id');
    }

    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    public function scopeForRole(Builder $query, string $role): Builder
    {
        return $query->where('recipient_scope', 'role')->where('recipient_role', $role);
    }

    public function markAsRead(): void
    {
        $this->update(['is_read' => true, 'read_at' => now()]);
    }
}
