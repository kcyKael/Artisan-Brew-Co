<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffInvitation extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'user_id',
        'email',
        'token_hash',
        'status',
        'invited_by',
        'expires_at',
        'accepted_at',
        'revoked_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by')->withTrashed();
    }

    public function isUsable(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        if ($this->expires_at === null || $this->expires_at->isPast()) {
            return false;
        }

        return $this->user !== null && $this->user->deleted_at === null;
    }

    public function effectiveStatus(): string
    {
        if ($this->status === self::STATUS_PENDING && $this->expires_at !== null && $this->expires_at->isPast()) {
            return self::STATUS_EXPIRED;
        }

        return $this->status;
    }
}
