<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Rating;
use App\Models\Usher;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function show(): JsonResponse
    {
        $responded = Assignment::query()->count();
        $confirmed = Assignment::query()->where('response', 'Confirmed')->count();
        $attended = Assignment::query()
            ->where('response', 'Confirmed')
            ->whereIn('attendance', ['Checked in', 'Checked out'])
            ->count();

        $performance = Usher::query()->where('status', 'Active')->avg('rating');
        $satisfaction = Rating::query()->where('source', 'client')->avg('score');

        $adminRatings = [];
        Rating::query()->where('source', 'admin')->orderBy('id')->get()->each(function (Rating $rating) use (&$adminRatings) {
            $adminRatings[(string) $rating->usher_id] = (string) $rating->score;
        });

        return response()->json([
            'averagePerformance' => number_format(round((float) ($performance ?? 0), 1), 1).' / 5',
            'attendanceRate' => $this->percent($attended, $confirmed),
            'confirmationRate' => $this->percent($confirmed, $responded),
            'clientSatisfaction' => number_format(round((float) ($satisfaction ?? 0), 1), 1).' / 5',
            'adminRatings' => $adminRatings,
        ]);
    }

    private function percent(int $part, int $whole): string
    {
        if ($whole === 0) {
            return '0%';
        }

        return round($part / $whole * 100).'%';
    }
}
