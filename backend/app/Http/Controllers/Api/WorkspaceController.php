<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkspaceSetting;
use Illuminate\Http\JsonResponse;

class WorkspaceController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(WorkspaceSetting::query()->firstOrFail()->present());
    }
}
