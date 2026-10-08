<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsherPhoto extends Model
{
    protected $fillable = ['usher_id', 'path', 'kind'];

    public function usher(): BelongsTo
    {
        return $this->belongsTo(Usher::class);
    }
}
