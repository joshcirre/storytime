<?php

namespace App\Jobs;

use App\Models\CallSession;
use App\Services\Runway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchCallTranscript implements ShouldQueue
{
    use Queueable;

    /**
     * Runway finalizes the conversation shortly after the room closes, so
     * a couple of spaced attempts cover the lag.
     *
     * @var array<int, int>
     */
    public array $backoff = [60, 300];

    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(public CallSession $callSession) {}

    /**
     * Store the conversation transcript so future sessions can remember it.
     */
    public function handle(Runway $runway): void
    {
        $conversation = $runway->getConversation($this->callSession->runway_session_id);

        $transcript = collect($conversation['transcript'] ?? [])
            ->filter(fn (array $line): bool => filled($line['content'] ?? null))
            ->map(fn (array $line): array => [
                'role' => $line['role'],
                'content' => $line['content'],
            ])
            ->values()
            ->all();

        if ($transcript === []) {
            return;
        }

        $this->callSession->update(['transcript' => $transcript]);
    }
}
