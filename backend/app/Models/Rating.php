<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rating extends Model
{
    protected $fillable = ['project_id', 'usher_id', 'score', 'comment', 'source'];

    public function usher(): BelongsTo
    {
        return $this->belongsTo(Usher::class);
    }

    public static function record(Project $project, Usher $usher, int $score, ?string $comment, string $source): void
    {
        static::query()->updateOrCreate(
            [
                'project_id' => $project->id,
                'usher_id' => $usher->id,
                'source' => $source,
            ],
            [
                'score' => $score,
                'comment' => $comment,
            ],
        );

        $usher->refreshRating();
    }
}
