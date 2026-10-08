<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkspaceSetting;
use Illuminate\Http\JsonResponse;

class WorkspaceController extends Controller
{
    public function show(): JsonResponse
    {
        $setting = WorkspaceSetting::query()->firstOrCreate([], [
            'company' => 'Validity Event & Marketing',
            'workspace' => 'Usher Directory',
            'timezone' => 'Addis Ababa (UTC+3)',
            'currency' => 'Ethiopian birr (ETB)',
        ]);

        return response()->json($setting->present());
    }
}
