<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactEnquiry extends Model
{
    public const STATUS_RECEIVED = 'received';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'reference_code',
        'name',
        'email',
        'subject',
        'message',
        'status',
        'ip_address',
        'admin_notes',
        'handled_by',
    ];

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by')->withTrashed();
    }
}
