<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Usher;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function ushers(): StreamedResponse
    {
        $rows = Usher::query()->orderBy('name')->get();

        return $this->csv('ushers.csv', [
            'Name', 'Phone', 'Email', 'City', 'Gender', 'Status', 'Experience years',
            'Events', 'Rating', 'Payment method', 'Bank', 'Account holder', 'Account number', 'Telebirr',
        ], $rows->map(fn (Usher $usher) => [
            $usher->name,
            $usher->phone,
            $usher->email,
            $usher->city,
            $usher->gender,
            $usher->status,
            $usher->years_experience,
            $usher->events_count,
            $usher->rating,
            $usher->payment_method,
            $usher->bank_name,
            $usher->account_holder,
            $usher->account_number,
            $usher->telebirr_number,
        ])->all());
    }

    public function projects(): StreamedResponse
    {
        $rows = Project::query()
            ->withCount(['assignments as confirmed_count' => fn ($query) => $query->where('response', 'Confirmed')])
            ->orderByDesc('id')
            ->get();

        return $this->csv('projects.csv', [
            'Name', 'Client', 'Date', 'Location', 'Required', 'Confirmed', 'Status',
            'Call time', 'End time', 'Compensation', 'Dress code',
        ], $rows->map(fn (Project $project) => [
            $project->name,
            $project->client,
            $project->date_label,
            $project->location,
            $project->required_ushers,
            $project->confirmed_count,
            $project->status,
            $project->call_time,
            $project->end_time,
            $project->compensation_label,
            $project->dress_code,
        ])->all());
    }

    public function payments(Project $project): StreamedResponse
    {
        $rows = $project->assignments()
            ->where('response', 'Confirmed')
            ->with('usher')
            ->orderBy('id')
            ->get();

        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($project->name)) ?: 'project';

        return $this->csv($slug.'-payments.csv', [
            'Usher', 'Phone', 'Role', 'Compensation', 'Payment status', 'Method',
            'Bank', 'Account holder', 'Account number', 'Telebirr',
        ], $rows->map(fn ($assignment) => [
            $assignment->usher?->name,
            $assignment->usher?->phone,
            $assignment->role,
            $project->compensation_label,
            $assignment->payment_status ?? 'Unpaid',
            $assignment->usher?->payment_method,
            $assignment->usher?->bank_name,
            $assignment->usher?->account_holder,
            $assignment->usher?->account_number,
            $assignment->usher?->telebirr_number,
        ])->all());
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<mixed>>  $rows
     */
    private function csv(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($value) => $value === null ? '' : (string) $value, $row));
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
