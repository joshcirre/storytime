<?php

namespace App\Http\Controllers\Relay;

use App\Http\Controllers\Controller;
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
     * Mark a session as ended once the relay detects the room closed.
     */
    public function end(string $runwaySessionId): Response
    {
        CallSession::query()
            ->where('runway_session_id', $runwaySessionId)
            ->update(['status' => 'ended']);

        return response()->noContent();
    }
}
