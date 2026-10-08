<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Rating;
use App\Models\Usher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function store(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'usher_id' => ['required', 'integer', 'exists:ushers,id'],
            'score' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        Rating::record(
            $project,
            Usher::query()->findOrFail($data['usher_id']),
            $data['score'],
            $data['comment'] ?? null,
            'admin',
        );

        return response()->json(['ok' => true]);
    }
}
