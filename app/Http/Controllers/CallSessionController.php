<?php

namespace App\Http\Controllers;

use App\CharacterStatus;
use App\Models\CallSession;
use App\Models\Character;
use App\Services\CharacterPersona;
use App\Services\ConversationTools;
use App\Services\Runway;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CallSessionController extends Controller
{
    /**
     * Start a Runway realtime session for a character. The client polls
     * the show endpoint until the session is ready, then connects.
     */
    public function store(Request $request, Character $character, Runway $runway): JsonResponse
    {
        Gate::allowIf($character->user_id === $request->user()->id);

        abort_unless($character->status === CharacterStatus::Ready, 409, 'Character is not ready for calls yet.');

        try {
            $runwaySessionId = $runway->createRealtimeSession(
                $character->runway_avatar_id,
                ConversationTools::definitions(),
                maxDuration: 600,
                personality: CharacterPersona::composeForSession($character),
            );
        } catch (RequestException $exception) {
            if ($exception->response->serverError()) {
                throw $exception;
            }

            // If the memory-enriched persona trips Runway's moderation, the
            // call still happens — just with the avatar's baked-in persona.
            $runwaySessionId = $runway->createRealtimeSession(
                $character->runway_avatar_id,
                ConversationTools::definitions(),
                maxDuration: 600,
            );
        }

        $callSession = $character->callSessions()->create([
            'runway_session_id' => $runwaySessionId,
        ]);

        return response()->json([
            'callSessionId' => $callSession->id,
        ], 201);
    }

    /**
     * Report session readiness. Once Runway marks the session READY, the
     * response includes the credentials the browser SDK needs to connect.
     */
    public function show(Request $request, CallSession $callSession, Runway $runway): JsonResponse
    {
        Gate::allowIf($callSession->character->user_id === $request->user()->id);

        $session = $runway->getRealtimeSession($callSession->runway_session_id);

        return response()->json([
            'status' => $session['status'],
            'sessionId' => $callSession->runway_session_id,
            'sessionKey' => $session['sessionKey'] ?? null,
            'failure' => $session['failure'] ?? null,
        ]);
    }
}
