<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkspaceSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkspaceController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json($this->setting()->present());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'registrationOpen' => ['required', 'boolean'],
        ]);
        $setting = $this->setting();
        $setting->registration_open = $data['registrationOpen'];
        $setting->save();

        return response()->json($setting->present());
    }

    public function rotateRegistration(): JsonResponse
    {
        $setting = $this->setting();
        $setting->registration_token = Str::random(40);
        $setting->registration_open = true;
        $setting->save();

        return response()->json($setting->present());
    }

    private function setting(): WorkspaceSetting
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

        return $setting;
    }
}
