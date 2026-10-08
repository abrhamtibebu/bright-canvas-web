<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Usher;
use App\Support\EventTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProjectController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json($this->projects());
    }

    public function store(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'client' => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'location' => ['required', 'string', 'max:255'],
            'required' => ['required', 'integer', 'min:1'],
            'call_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'transport_provided' => ['required', 'boolean'],
            'food_provided' => ['required', 'boolean'],
            'compensation' => ['required', 'string', 'max:255'],
            'dress_code' => ['required', 'string', 'max:255'],
        ]);
        $validator->after(function ($validator) use ($request): void {
            $start = EventTime::minutes((string) $request->input('call_time'));
            $end = EventTime::minutes((string) $request->input('end_time'));
            if ($start !== null && $end !== null && $end <= $start) {
                $validator->errors()->add('end_time', 'Work must end after it starts.');
            }
        });
        $data = $validator->validate();
        $transport = $request->boolean('transport_provided');
        $food = $request->boolean('food_provided');

        $project = Project::query()->create([
            'name' => $data['name'],
            'client' => $data['client'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'date_label' => EventTime::spanLabel($data['starts_on'], $data['ends_on']),
            'location' => $data['location'],
            'required_ushers' => $data['required'],
            'status' => 'Upcoming',
            'call_time' => EventTime::label($data['call_time'], '7:00 AM'),
            'end_time' => EventTime::label($data['end_time'], '6:00 PM'),
            'compensation_label' => $data['compensation'],
            'transport_and_lunch' => EventTime::logistics($transport, $food),
            'transport_provided' => $transport,
            'food_provided' => $food,
            'dress_code' => $data['dress_code'],
        ]);

        $project->confirmed_count = 0;

        return response()->json($project->present(), 201);
    }

    public function show(Project $project): JsonResponse
    {
        $project->loadCount(['assignments as confirmed_count' => fn ($query) => $query->where('response', 'Confirmed')]);

        return response()->json($project->present());
    }

    public function destroy(Project $project): Response
    {
        $project->delete();

        return response()->noContent();
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
