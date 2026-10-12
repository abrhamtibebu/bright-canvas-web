<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function show(Project $project): JsonResponse
    {
        $project->loadCount(['assignments as confirmed_count' => fn ($query) => $query->where('response', 'Confirmed')]);

        $rows = $project->assignments()
            ->where('response', 'Confirmed')
            ->with('usher')
            ->orderBy('id')
            ->get()
            ->map(fn ($assignment) => [
                'assignmentId' => $assignment->id,
                'usherId' => $assignment->usher_id,
                'name' => $assignment->usher?->name,
                'phone' => $assignment->usher?->phone,
                'role' => $assignment->role,
                'compensation' => $project->compensation_label,
                'paymentStatus' => $assignment->payment_status ?? 'Unpaid',
                'paymentMethod' => $assignment->usher?->payment_method,
                'bankName' => $assignment->usher?->bank_name,
                'accountHolder' => $assignment->usher?->account_holder,
                'accountNumber' => $assignment->usher?->account_number,
                'telebirrNumber' => $assignment->usher?->telebirr_number,
            ]);

        return response()->json([
            'project' => $project->present(),
            'rows' => $rows,
        ]);
    }
}
