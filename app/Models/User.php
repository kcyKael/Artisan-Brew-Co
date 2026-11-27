<?php

namespace App\Models;

use App\Notifications\StaffPasswordResetNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_CASHIER = 'cashier';

    protected $fillable = [
        'username',
        'full_name',
        'email',
        'password',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'pending_email_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'email_verified_at' => 'datetime',
            'pending_email_expires_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isCashier(): bool
    {
        return $this->role === self::ROLE_CASHIER;
    }

    public function isActiveAccount(): bool
    {
        return $this->is_active && $this->deleted_at === null;
    }

    public function avatarUrl(): ?string
    {
        if (! $this->avatar) {
            return null;
        }

        return route('media.show', [
            'type' => 'avatars',
            'filename' => basename($this->avatar),
        ]);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->full_name)) ?: [];
        $letters = array_map(fn (string $p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice($parts, 0, 2));

        return implode('', $letters) ?: 'AB';
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'sold_by');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'sold_by');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(StaffInvitation::class, 'user_id');
    }

    public function latestInvitation(): HasOne
    {
        return $this->hasOne(StaffInvitation::class, 'user_id')->latestOfMany();
    }

    public function hasPendingInvitation(): bool
    {
        return $this->invitations()
            ->where('status', StaffInvitation::STATUS_PENDING)
            ->exists();
    }

    public function canReceivePasswordReset(): bool
    {
        return $this->isActiveAccount()
            && filled($this->email)
            && ! $this->hasPendingInvitation();
    }

    public function getEmailForPasswordReset(): string
    {
        return (string) ($this->email ?? '');
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new StaffPasswordResetNotification((string) $token));
    }
}
