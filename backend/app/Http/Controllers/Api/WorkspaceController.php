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
            'company' => 'Validity Events',
            'workspace' => 'Usher Directory',
            'timezone' => 'Addis Ababa (UTC+3)',
            'currency' => 'Ethiopian birr (ETB)',
        ]);

        if ($setting->company === 'Validity Event & Marketing') {
            $setting->company = 'Validity Events';
            $setting->save();
        }

        return response()->json($setting->present());
    }
}
