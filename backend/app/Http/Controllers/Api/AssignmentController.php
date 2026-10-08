<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AssignmentController extends Controller
{
    public function index(): JsonResponse
    {
        $assignments = Assignment::query()
            ->with('project')
            ->orderBy('id')
            ->get()
            ->map(fn (Assignment $assignment) => $assignment->present());

        return response()->json($assignments);
    }

    public function update(Request $request, Assignment $assignment): JsonResponse
    {
        $data = $request->validate([
            'response' => ['sometimes', 'in:Invited,Confirmed,Declined'],
            'attendance' => ['sometimes', 'in:Expected,Checked in,Checked out'],
        ]);

        $assignment->fill($data)->save();
        $assignment->load('project');

        return response()->json($assignment->present());
    }

    public function destroy(Assignment $assignment): Response
    {
        $assignment->delete();

        return response()->noContent();
    }
}
