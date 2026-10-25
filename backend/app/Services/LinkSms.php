<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Usher;
use App\Support\PhoneNumber;
use App\Support\PublicAppUrl;
use Illuminate\Support\Collection;

class LinkSms
{
    public function __construct(private TextBeeSms $sms) {}

    /**
     * @return array{queued: bool, error: ?string}
     */
    public function correction(Usher $usher): array
    {
        $token = $usher->profile_edit_token;
        if (! is_string($token) || $token === '') {
            return ['queued' => false, 'error' => 'No correction link.'];
        }

        $url = PublicAppUrl::path('profile/'.$token);

        return $this->sms->sendOne(
            $usher->phone,
            'Validity Events: Dear '.$this->greeting($usher).', please update your usher profile: '.$url,
        );
    }

    /**
     * @param  iterable<int, Usher|null>  $ushers
     * @return array{queued: int, skipped: int, error: ?string}
     */
    public function availability(Project $project, iterable $ushers): array
    {
        $token = $project->availability_token;
        if (! is_string($token) || $token === '') {
            return ['queued' => 0, 'skipped' => 0, 'error' => 'No availability link.'];
        }

        $url = PublicAppUrl::path('respond/'.$token);
        $queued = 0;
        $skipped = 0;
        $error = null;

        foreach (Collection::make($ushers)->filter() as $usher) {
            $result = $this->sms->sendOne(
                $usher->phone,
                'Validity Events: Dear '.$this->greeting($usher).', you are shortlisted for '.$project->name.'. Confirm availability: '.$url,
            );

            if ($result['queued']) {
                $queued++;
                continue;
            }

            $skipped++;
            if (PhoneNumber::e164($usher->phone) !== null) {
                $error ??= $result['error'];
            }
        }

        if ($queued === 0 && $error === null) {
            $error = $skipped > 0 ? 'No valid phone number.' : 'No recipients.';
        }

        return [
            'queued' => $queued,
            'skipped' => $skipped,
            'error' => $error,
        ];
    }

    private function greeting(Usher $usher): string
    {
        $name = trim((string) $usher->name);

        return $name !== '' ? $name : 'usher';
    }
}
