<?php

namespace App\Http\Controllers\Relay;

use App\Http\Controllers\Controller;
use App\Jobs\FetchCallTranscript;
use App\Models\CallSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class RelaySessionController extends Controller
{
    /**
     * List sessions the relay should attach a tool handler to.
     * Stale sessions are skipped so a relay restart doesn't join long-dead rooms.
     */
    public function pending(): JsonResponse
    {
        $sessionIds = CallSession::query()
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(15))
            ->pluck('runway_session_id');

        return response()->json(['sessions' => $sessionIds]);
    }

    /**
     * Mark a session as claimed by the relay so it is not handed out again.
     */
    public function claim(string $runwaySessionId): Response
    {
        CallSession::query()
            ->where('runway_session_id', $runwaySessionId)
            ->where('status', 'pending')
            ->update(['status' => 'claimed', 'claimed_at' => now()]);

        return response()->noContent();
    }

    /**
     * Mark a session as ended once the relay detects the room closed, and
     * fetch its transcript so the character remembers the conversation.
     */
    public function end(string $runwaySessionId): Response
    {
        $callSession = CallSession::query()
            ->where('runway_session_id', $runwaySessionId)
            ->first();

        if ($callSession !== null && $callSession->status !== 'ended') {
            $callSession->update(['status' => 'ended']);

            FetchCallTranscript::dispatch($callSession)->delay(now()->addSeconds(30));
        }

        return response()->noContent();
    }
}
