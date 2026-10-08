<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsherExperience extends Model
{
    protected $fillable = ['usher_id', 'event_name', 'client', 'role', 'event_type'];

    public function usher(): BelongsTo
    {
        return $this->belongsTo(Usher::class);
    }
}
