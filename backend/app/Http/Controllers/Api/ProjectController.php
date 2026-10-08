<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Usher;
use App\Support\EventTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json($this->projects());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'client' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'location' => ['required', 'string', 'max:255'],
            'required' => ['required', 'integer', 'min:1'],
            'call_time' => ['nullable', 'date_format:H:i'],
        ]);

        $project = Project::query()->create([
            'name' => $data['name'],
            'client' => $data['client'],
            'date_label' => EventTime::dateLabel($data['date']),
            'location' => $data['location'],
            'required_ushers' => $data['required'],
            'status' => 'Upcoming',
            'call_time' => EventTime::label($data['call_time'] ?? null, '7:00 AM'),
        ]);

        $project->confirmed_count = 0;

        return response()->json($project->present(), 201);
    }

    public function show(Project $project): JsonResponse
    {
        $project->loadCount(['assignments as confirmed_count' => fn ($query) => $query->where('response', 'Confirmed')]);

        return response()->json($project->present());
    }

    public function invite(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'usher_ids' => ['required', 'array', 'min:1'],
            'usher_ids.*' => ['integer', 'exists:ushers,id'],
        ]);

        $ushers = Usher::query()
            ->whereIn('id', $data['usher_ids'])
            ->where('status', 'Active')
            ->get();

        foreach ($ushers as $usher) {
            $assignment = $project->assignments()->firstOrNew(['usher_id' => $usher->id]);
            $assignment->role = $assignment->role ?: 'Registration';
            if ($assignment->response !== 'Confirmed') {
                $assignment->response = 'Invited';
            }
            $assignment->save();
        }

        return response()->json([
            'prepared' => $ushers->count(),
        ]);
    }

    public function select(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'usher_ids' => ['required', 'array', 'min:1'],
            'usher_ids.*' => ['integer'],
        ]);

        $project->syncSelection($data['usher_ids']);

        return response()->json(['ok' => true]);
    }

    private function projects()
    {
        return Project::query()
            ->withCount(['assignments as confirmed_count' => fn ($query) => $query->where('response', 'Confirmed')])
            ->orderBy('id')
            ->get()
            ->map(fn (Project $project) => $project->present())
            ->values();
    }
}
