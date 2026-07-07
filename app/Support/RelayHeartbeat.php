<?php

namespace App\Support;

use App\Http\Controllers\Relay\RelaySessionController;
use App\Models\CallSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Tracks liveness of the standalone Node relay process.
 *
 * The relay runs as a separate runtime (a Laravel Cloud background process),
 * so the PHP app only knows it is alive if it keeps hearing from it. The relay
 * pings {@see RelaySessionController::heartbeat()}
 * on every poll; we cache the last ping and expose a derived status for the
 * public showcase page.
 */
class RelayHeartbeat
{
    private const CACHE_KEY = 'relay.heartbeat';

    /**
     * A relay is considered online if we have heard from it this recently.
     * The relay pings every ~2s, so this tolerates a couple of missed beats.
     */
    private const ONLINE_THRESHOLD_SECONDS = 10;

    /**
     * Store the latest ping from the relay.
     */
    public static function record(int $activeSessions, string $startedAt): void
    {
        Cache::put(self::CACHE_KEY, [
            'active_sessions' => $activeSessions,
            'started_at' => $startedAt,
            'received_at' => now()->toIso8601String(),
        ], now()->addMinutes(5));
    }

    /**
     * Derive the current, human-facing status of the relay.
     *
     * @return array{
     *     online: bool,
     *     last_seen_seconds: int|null,
     *     active_sessions: int,
     *     uptime_seconds: int|null,
     *     sessions_today: int,
     *     checked_at: string,
     * }
     */
    public static function status(): array
    {
        $heartbeat = Cache::get(self::CACHE_KEY);

        $lastSeen = isset($heartbeat['received_at'])
            ? Carbon::parse($heartbeat['received_at'])
            : null;

        $lastSeenSeconds = $lastSeen !== null
            ? (int) abs($lastSeen->diffInSeconds(now()))
            : null;

        return [
            'online' => $lastSeenSeconds !== null && $lastSeenSeconds <= self::ONLINE_THRESHOLD_SECONDS,
            'last_seen_seconds' => $lastSeenSeconds,
            'active_sessions' => (int) ($heartbeat['active_sessions'] ?? 0),
            'uptime_seconds' => isset($heartbeat['started_at'])
                ? (int) abs(Carbon::parse($heartbeat['started_at'])->diffInSeconds(now()))
                : null,
            'sessions_today' => CallSession::query()->whereDate('created_at', today())->count(),
            'checked_at' => now()->toIso8601String(),
        ];
    }
}
