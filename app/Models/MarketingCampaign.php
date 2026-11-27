<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingCampaign extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'idempotency_key',
        'name',
        'subject',
        'preview_text',
        'body_html',
        'status',
        'audience_count',
        'provider_campaign_id',
        'provider_status',
        'stats_json',
        'created_by',
        'sent_by',
        'last_tested_at',
        'sent_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'audience_count' => 'integer',
            'provider_campaign_id' => 'integer',
            'last_tested_at' => 'datetime',
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by')->withTrashed();
    }
}
