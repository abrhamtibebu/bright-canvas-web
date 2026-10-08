<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Project;
use App\Models\Rating;
use App\Models\Usher;
use App\Models\UsherPhoto;
use App\Support\StoredPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    public function confirm(string $token): JsonResponse
    {
        $assignment = $this->assignmentByToken($token);

        return response()->json([
            'project' => $assignment->project->presentPublic(),
            'usher' => [
                'id' => $assignment->usher_id,
                'name' => $assignment->usher->name,
            ],
            'response' => $assignment->response,
        ]);
    }

    public function confirmResponse(Request $request, string $token): JsonResponse
    {
        $assignment = $this->assignmentByToken($token);
        $data = $request->validate([
            'response' => ['required', 'in:Confirmed,Declined'],
        ]);
        $assignment->update(['response' => $data['response']]);

        return response()->json(['ok' => true]);
    }

    public function team(string $token): JsonResponse
    {
        $project = $this->project('client_token', $token);

        return response()->json([
            'project' => $project->presentPublic(),
            'ushers' => $this->confirmedUshers($project, $token),
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
            'ushers' => $this->confirmedUshers($project, $token),
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

    public function photo(string $token, UsherPhoto $photo): BinaryFileResponse
    {
        $project = Project::query()
            ->where(fn ($query) => $query->where('client_token', $token)->orWhere('rating_token', $token))
            ->firstOrFail();

        $confirmed = $project->assignments()
            ->where('usher_id', $photo->usher_id)
            ->where('response', 'Confirmed')
            ->exists();

        abort_unless($confirmed, 404);

        return StoredPhoto::response($photo->path);
    }

    private function assignmentByToken(string $token): Assignment
    {
        return Assignment::query()
            ->where('confirmation_token', $token)
            ->with(['project', 'usher'])
            ->firstOrFail();
    }

    private function project(string $column, string $token): Project
    {
        return Project::query()->where($column, $token)->firstOrFail();
    }

    private function confirmedUshers(Project $project, string $token)
    {
        return $project->assignments()
            ->where('response', 'Confirmed')
            ->with(['usher.experiences', 'usher.photos'])
            ->get()
            ->map(function ($assignment) use ($token) {
                $usher = $assignment->usher->presentPublic();
                $usher['photos'] = $assignment->usher->publicPhotoPaths($token);

                return $usher;
            })
            ->values();
    }
}
