<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingSubscriber extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUBSCRIBED = 'subscribed';
    public const STATUS_UNSUBSCRIBED = 'unsubscribed';
    public const STATUS_SUPPRESSED = 'suppressed';

    protected $fillable = [
        'email',
        'name',
        'customer_id',
        'status',
        'consent_source',
        'consent_notice_version',
        'consented_at',
        'confirmed_at',
        'unsubscribed_at',
        'suppressed_at',
        'suppression_reason',
        'confirm_token_hash',
        'confirm_expires_at',
        'unsubscribe_token_hash',
        'provider_contact_id',
        'provider_list_id',
        'provider_blacklisted',
    ];

    protected $hidden = [
        'confirm_token_hash',
        'unsubscribe_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'suppressed_at' => 'datetime',
            'confirm_expires_at' => 'datetime',
            'provider_list_id' => 'integer',
            'provider_blacklisted' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function isEligibleForCampaigns(): bool
    {
        return $this->status === self::STATUS_SUBSCRIBED
            && ! $this->provider_blacklisted
            && $this->suppressed_at === null
            && $this->unsubscribed_at === null;
    }
}
