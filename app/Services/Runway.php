<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

class Runway
{
    /**
     * Start a text-to-image generation task and return its task ID.
     *
     * @param  array<int, array{uri: string, tag?: string}>  $referenceImages
     */
    public function createImageTask(string $promptText, array $referenceImages = []): string
    {
        $payload = [
            'model' => 'gpt_image_2',
            'promptText' => $promptText,
            'ratio' => '1920:1920',
            'quality' => 'high',
        ];

        if ($referenceImages !== []) {
            $payload['referenceImages'] = $referenceImages;
        }

        return $this->request()->post('/v1/text_to_image', $payload)->throw()->json('id');
    }

    /**
     * Retrieve a generation task. Poll no faster than every 5 seconds.
     *
     * @return array{id: string, status: string, output?: array<int, string>, failure?: string, progress?: float}
     */
    public function getTask(string $taskId): array
    {
        return $this->request()->get("/v1/tasks/{$taskId}")->throw()->json();
    }

    /**
     * Create a conversational avatar from an HTTPS reference image URL.
     *
     * @return array{id: string, status: string, failureReason?: string}
     */
    public function createAvatar(string $name, string $personality, string $referenceImageUrl, string $voicePresetId): array
    {
        return $this->request()->post('/v1/avatars', [
            'name' => $name,
            'personality' => $personality,
            'referenceImage' => $referenceImageUrl,
            'voice' => ['type' => 'runway-live-preset', 'presetId' => $voicePresetId],
            'imageProcessing' => 'optimize',
        ])->throw()->json();
    }

    /**
     * Retrieve an avatar. Status is PROCESSING, READY, or FAILED.
     *
     * @return array{id: string, status: string, failureReason?: string}
     */
    public function getAvatar(string $avatarId): array
    {
        return $this->request()->get("/v1/avatars/{$avatarId}")->throw()->json();
    }

    /**
     * Delete an avatar from Runway.
     */
    public function deleteAvatar(string $avatarId): void
    {
        $this->request()->delete("/v1/avatars/{$avatarId}")->throw();
    }

    /**
     * Create a realtime conversation session for a custom avatar and return its session ID.
     *
     * @param  array<int, array<string, mixed>>  $tools
     */
    public function createRealtimeSession(string $avatarId, array $tools = [], ?int $maxDuration = null): string
    {
        $payload = [
            'model' => 'gwm1_avatars',
            'avatar' => ['type' => 'custom', 'avatarId' => $avatarId],
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
        }

        if ($maxDuration !== null) {
            $payload['maxDuration'] = $maxDuration;
        }

        return $this->request()->post('/v1/realtime_sessions', $payload)->throw()->json('id');
    }

    /**
     * Retrieve a realtime session. READY responses include the sessionKey
     * the browser uses to consume the session.
     *
     * @return array{id: string, status: string, sessionKey?: string, failure?: string, queued?: bool}
     */
    public function getRealtimeSession(string $sessionId): array
    {
        return $this->request()->get("/v1/realtime_sessions/{$sessionId}")->throw()->json();
    }

    /**
     * Cancel an active realtime session.
     */
    public function cancelRealtimeSession(string $sessionId): void
    {
        $this->request()->delete("/v1/realtime_sessions/{$sessionId}")->throw();
    }

    /**
     * Rate limits (429) and transient server errors are retried with backoff
     * so a busy moment doesn't fail a multi-minute character pipeline.
     */
    protected function request(): PendingRequest
    {
        return Http::baseUrl(config('services.runway.base_url'))
            ->withToken(config('services.runway.secret'))
            ->withHeader('X-Runway-Version', config('services.runway.version'))
            ->acceptJson()
            ->timeout(30)
            ->retry(
                [2000, 5000, 15000, 30000],
                when: fn (Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException
                        && in_array($exception->response->status(), [429, 500, 502, 503, 504])),
            );
    }
}
