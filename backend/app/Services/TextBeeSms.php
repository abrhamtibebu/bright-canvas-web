<?php

namespace App\Services;

use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TextBeeSms
{
    /**
     * @param  list<string|null>  $recipients
     * @return array{queued: int, skipped: int, error: ?string}
     */
    public function send(string $message, array $recipients): array
    {
        $valid = [];
        $skipped = 0;

        foreach ($recipients as $phone) {
            $e164 = PhoneNumber::e164($phone);
            if ($e164 === null) {
                $skipped++;

                continue;
            }
            $valid[$e164] = $e164;
        }

        $valid = array_values($valid);

        if ($valid === []) {
            return [
                'queued' => 0,
                'skipped' => $skipped,
                'error' => $skipped > 0 ? 'No valid phone number.' : 'No recipients.',
            ];
        }

        $key = config('textbee.api_key');
        if (! is_string($key) || $key === '') {
            return [
                'queued' => 0,
                'skipped' => $skipped + count($valid),
                'error' => 'SMS is not configured.',
            ];
        }

        $payload = [
            'message' => $message,
            'recipients' => $valid,
        ];
        $deviceId = config('textbee.device_id');
        if (is_string($deviceId) && $deviceId !== '') {
            $payload['deviceId'] = $deviceId;
        }

        try {
            $response = Http::baseUrl(rtrim((string) config('textbee.base_url'), '/'))
                ->withHeaders(['x-api-key' => $key])
                ->acceptJson()
                ->asJson()
                ->timeout(15)
                ->post('/gateway/send-sms', $payload);
        } catch (Throwable $exception) {
            Log::warning('TextBee SMS request failed', ['error' => $exception->getMessage()]);

            return [
                'queued' => 0,
                'skipped' => $skipped,
                'error' => 'Could not send SMS.',
            ];
        }

        if (! $response->successful()) {
            $body = $response->json('message') ?? $response->json('error') ?? $response->body();
            $error = is_string($body) && trim($body) !== '' ? trim($body) : 'Could not send SMS.';
            Log::warning('TextBee SMS was rejected', [
                'status' => $response->status(),
                'error' => $error,
            ]);

            return [
                'queued' => 0,
                'skipped' => $skipped,
                'error' => $error,
            ];
        }

        return [
            'queued' => count($valid),
            'skipped' => $skipped,
            'error' => null,
        ];
    }

    /**
     * @return array{queued: bool, error: ?string}
     */
    public function sendOne(?string $phone, string $message): array
    {
        $result = $this->send($message, [$phone]);

        return [
            'queued' => $result['queued'] > 0,
            'error' => $result['error'],
        ];
    }
}
