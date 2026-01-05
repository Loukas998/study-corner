<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $guarded = [];

    public function customer(): BelongsTo 
    {
        return $this->belongsTo(Customer::class);
    }

    public function package(): BelongsTo 
    {
        return $this->belongsTo(Package::class);
    }
}
