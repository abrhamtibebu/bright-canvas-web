<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsherReference extends Model
{
    protected $fillable = ['usher_id', 'name', 'organization', 'relationship', 'phone', 'email', 'notes'];

    public function usher(): BelongsTo
    {
        return $this->belongsTo(Usher::class);
    }
}
