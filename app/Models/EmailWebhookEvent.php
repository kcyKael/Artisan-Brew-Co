<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailWebhookEvent extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_QUARANTINED = 'quarantined';
    public const STATUS_IGNORED = 'ignored';

    protected $fillable = [
        'event_key',
        'scope',
        'event_type',
        'provider_message_id',
        'provider_campaign_id',
        'email',
        'occurred_at',
        'email_delivery_id',
        'status',
        'payload_json',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'provider_campaign_id' => 'integer',
            'occurred_at' => 'datetime',
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(EmailDelivery::class, 'email_delivery_id');
    }
}
