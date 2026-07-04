<?php

namespace App\Console\Commands;

use App\Jobs\FetchCallTranscript;
use App\Models\CallSession;
use App\Services\Runway;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:cleanup-stale-call-sessions')]
#[Description('Cancel Runway sessions that never ended so they stop holding the avatar concurrency slot')]
class CleanupStaleCallSessions extends Command
{
    /**
     * Sessions are capped at 10 minutes by maxDuration, so anything older
     * that is not marked ended is a zombie (e.g. the relay was restarted
     * mid-call and its disconnect handler never ran). Zombies hold the
     * tier's single gwm1_avatars concurrency slot, blocking avatar creation
     * with 429s until cancelled.
     */
    public function handle(Runway $runway): int
    {
        $stale = CallSession::query()
            ->whereIn('status', ['pending', 'claimed'])
            ->where('created_at', '<', now()->subMinutes(15))
            ->get();

        foreach ($stale as $session) {
            rescue(fn () => $runway->cancelRealtimeSession($session->runway_session_id));

            $session->update(['status' => 'ended']);

            FetchCallTranscript::dispatch($session)->delay(now()->addSeconds(30));

            $this->info("Cancelled stale session {$session->runway_session_id}");
        }

        $this->info("Cleaned up {$stale->count()} stale session(s).");

        return self::SUCCESS;
    }
}
