<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Rating;
use App\Models\Usher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicProjectController extends Controller
{
    public function availability(string $token): JsonResponse
    {
        $project = $this->project('availability_token', $token);
        $project->load('assignments.usher');

        return response()->json([
            'project' => $project->presentPublic(),
            'ushers' => $project->assignments->map(fn ($assignment) => [
                'id' => $assignment->usher_id,
                'name' => $assignment->usher->name,
            ])->values(),
        ]);
    }

    public function respond(Request $request, string $token): JsonResponse
    {
        $project = $this->project('availability_token', $token);
        $data = $request->validate([
            'usher_id' => ['required', 'integer'],
            'response' => ['required', 'in:Confirmed,Declined'],
        ]);

        $assignment = $project->assignments()->where('usher_id', $data['usher_id'])->firstOrFail();
        $assignment->update(['response' => $data['response']]);

        return response()->json(['ok' => true]);
    }

    public function team(string $token): JsonResponse
    {
        $project = $this->project('client_token', $token);

        return response()->json([
            'project' => $project->presentPublic(),
            'ushers' => $this->confirmedUshers($project),
        ]);
    }

    public function select(Request $request, string $token): JsonResponse
    {
        $project = $this->project('client_token', $token);
        $data = $request->validate([
            'usher_ids' => ['required', 'array', 'min:1'],
            'usher_ids.*' => ['integer'],
        ]);

        $project->syncSelection($data['usher_ids']);

        return response()->json(['ok' => true]);
    }

    public function ratings(string $token): JsonResponse
    {
        $project = $this->project('rating_token', $token);

        return response()->json([
            'project' => $project->presentPublic(),
            'ushers' => $this->confirmedUshers($project),
        ]);
    }

    public function rate(Request $request, string $token): JsonResponse
    {
        $project = $this->project('rating_token', $token);
        $data = $request->validate([
            'ratings' => ['required', 'array', 'min:1'],
            'ratings.*.usher_id' => ['required', 'integer'],
            'ratings.*.score' => ['required', 'integer', 'min:1', 'max:5'],
            'ratings.*.comment' => ['nullable', 'string', 'max:2000'],
        ]);

        foreach ($data['ratings'] as $rating) {
            $assignment = $project->assignments()
                ->where('usher_id', $rating['usher_id'])
                ->where('response', 'Confirmed')
                ->first();
            if (! $assignment) {
                continue;
            }
            Rating::record(
                $project,
                Usher::query()->findOrFail($rating['usher_id']),
                $rating['score'],
                $rating['comment'] ?? null,
                'client',
            );
        }

        return response()->json(['ok' => true]);
    }

    private function project(string $column, string $token): Project
    {
        return Project::query()->where($column, $token)->firstOrFail();
    }

    private function confirmedUshers(Project $project)
    {
        return $project->assignments()
            ->where('response', 'Confirmed')
            ->with('usher.experiences')
            ->get()
            ->map(fn ($assignment) => $assignment->usher->presentPublic())
            ->values();
    }
}
