<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailDelivery extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_BOUNCED = 'bounced';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_FAILED = 'failed';
    public const STATUS_UNCERTAIN = 'uncertain';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_SIMULATED = 'simulated';

    protected $fillable = [
        'message_key',
        'idempotency_key',
        'purpose',
        'queue_name',
        'recipient_email',
        'recipient_name',
        'subject',
        'template_name',
        'template_version',
        'related_type',
        'related_id',
        'initiated_by',
        'is_sensitive',
        'payload_encrypted',
        'status',
        'provider_message_id',
        'attempts',
        'last_http_status',
        'last_error_code',
        'last_error',
        'last_event_at',
        'dispatched_at',
        'accepted_at',
        'delivered_at',
        'failed_at',
        'expires_at',
        'payload_pruned_at',
    ];

    protected $hidden = [
        'payload_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'is_sensitive' => 'boolean',
            'attempts' => 'integer',
            'last_http_status' => 'integer',
            'last_event_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'accepted_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
            'expires_at' => 'datetime',
            'payload_pruned_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by')->withTrashed();
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(EmailWebhookEvent::class, 'email_delivery_id');
    }

    public function maskedRecipient(): string
    {
        $email = (string) $this->recipient_email;
        if (! str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);
        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));

        return $visible.str_repeat('*', max(1, mb_strlen($local) - mb_strlen($visible))).'@'.$domain;
    }

    public function canRetry(): bool
    {
        if (! in_array($this->status, [self::STATUS_FAILED, self::STATUS_QUEUED, self::STATUS_SIMULATED], true)) {
            return false;
        }

        if ($this->payload_pruned_at !== null || empty($this->payload_encrypted)) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
