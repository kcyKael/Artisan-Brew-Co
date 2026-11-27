<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailySalesReport extends Model
{
    protected $fillable = [
        'report_key',
        'business_date',
        'period_start_utc',
        'period_end_utc',
        'summary_json',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'period_start_utc' => 'datetime',
            'period_end_utc' => 'datetime',
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
