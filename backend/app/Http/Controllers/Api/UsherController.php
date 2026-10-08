<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usher;
use App\Models\UsherPhoto;
use App\Support\StoredPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UsherController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $ushers = Usher::query()
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
                $query->where('name', 'like', '%'.$request->string('search').'%');
            })
            ->when($request->filled('status') && $request->string('status') !== 'All statuses', function ($query) use ($request) {
                $query->where('status', $request->string('status'));
            })
            ->when($request->filled('city') && $request->string('city') !== 'All cities', function ($query) use ($request) {
                $query->where('city', $request->string('city'));
            })
            ->when($request->boolean('available'), function ($query) {
                $query->where('available', true);
            })
            ->with(['photos', 'experiences', 'references'])
            ->orderBy('id')
            ->get()
            ->filter(function (Usher $usher) use ($request) {
                $skill = $request->string('skill')->toString();
                if ($skill === '' || $skill === 'All skills') {
                    return true;
                }

                return in_array($skill, $usher->skills ?? [], true);
            })
            ->map(fn (Usher $usher) => $usher->present())
            ->values();

        return response()->json($ushers);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:100'],
        ]);

        $usher = Usher::query()->create([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'city' => $data['city'] ?? 'Addis Ababa',
            'gender' => 'Not specified',
            'skills' => ['Registration'],
            'languages' => ['Amharic'],
            'status' => 'Pending',
            'available' => true,
        ]);

        return response()->json($usher->present(), 201);
    }

    public function show(Usher $usher): JsonResponse
    {
        $usher->load(['photos', 'experiences', 'references']);

        return response()->json($usher->present());
    }

    public function photo(Usher $usher, UsherPhoto $photo): BinaryFileResponse
    {
        abort_unless($photo->usher_id === $usher->id, 404);

        return StoredPhoto::response($photo->path);
    }

    public function storePhoto(Request $request, Usher $usher): JsonResponse
    {
        $data = $request->validate([
            'photo' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
        ]);

        $path = $data['photo']->store('ushers/'.$usher->id, 'local');
        $usher->photos()->create([
            'path' => $path,
            'kind' => $usher->photos()->where('kind', 'profile')->exists() ? 'additional' : 'profile',
        ]);
        $usher->load('photos');

        return response()->json($usher->present());
    }

    public function destroyPhoto(Usher $usher, UsherPhoto $photo): Response
    {
        abort_unless($photo->usher_id === $usher->id, 404);
        StoredPhoto::delete($photo->path);
        $photo->delete();

        return response()->noContent();
    }

    public function destroy(Usher $usher): Response
    {
        $usher->load('photos');
        foreach ($usher->photos as $photo) {
            StoredPhoto::delete($photo->path);
        }
        $usher->delete();

        return response()->noContent();
    }

    public function update(Request $request, Usher $usher): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:Active,Correction Required'],
        ]);

        $usher->update(['status' => $data['status']]);

        return response()->json($usher->present());
    }
}
