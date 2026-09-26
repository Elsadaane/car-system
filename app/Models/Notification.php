<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'app_notifications';

    protected $fillable = [
        'notification_type', 'notifiable_type', 'notifiable_id', 'title_ar', 'title_en',
        'message_ar', 'message_en', 'action_url', 'recipient_type', 'recipient_id', 'is_read',
        'read_at', 'channels', 'channel_status', 'sent_at', 'priority', 'retry_count',
        'next_retry_at', 'notes'
    ];

    protected $casts = [
        'channels' => 'array',
        'channel_status' => 'array',
        'read_at' => 'datetime',
        'sent_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'is_read' => 'boolean',
    ];

    public function notifiable()
    {
        return $this->morphTo();
    }

    public function recipient()
    {
        return $this->morphTo();
    }

    public function logs()
    {
        return $this->hasMany(NotificationLog::class, 'notification_id');
    }


    // ===== Scopes =====

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeRecent($query)
    {
        return $query->orderByDesc('created_at');
    }

    public function scopeForRecipient($query, $recipient)
    {
        return $query->where('recipient_type', get_class($recipient))
                     ->where('recipient_id', $recipient->id);
    }

    public function scopeUnsent($query)
    {
        return $query->whereNull('sent_at');
    }

    // ===== Helpers =====

    public function markAsRead(): void
    {
        if (! $this->is_read) {
            $this->forceFill(['is_read' => true, 'read_at' => now()])->save();
        }
    }

    public function markChannelSent(string $channel, string $state = 'sent', ?string $error = null): void
    {
        $status           = $this->channel_status ?? [];
        $status[$channel] = $state;

        $this->forceFill([
            'channel_status' => $status,
            'sent_at'        => $this->sent_at ?? now(),
        ])->save();

        $this->logs()->create([
            'channel'       => $channel,
            'status'        => $state,
            'error_message' => $error,
            'sent_at'       => now(),
        ]);
    }

    /** العنوان والنص حسب لغة المستخدم */
    public function title(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar'
            ? ($this->title_ar ?: $this->title_en)
            : ($this->title_en ?: $this->title_ar);
    }

    public function message(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'ar'
            ? ($this->message_ar ?: $this->message_en)
            : ($this->message_en ?: $this->message_ar);
    }
}
