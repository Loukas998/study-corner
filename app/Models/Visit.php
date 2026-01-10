<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Visit extends Model
{
    protected $guarded = [];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function getVisitDurationDisplayAttribute(): string
    {
        return $this->exit_time === null
            ? 'Still Active'
            : $this->visit_duration;
    }
}
