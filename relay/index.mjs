/**
 * RPC relay: joins Runway realtime sessions as the backend tool handler and
 * forwards every tool call to the Laravel app, where the real logic lives.
 *
 * Runway's backend tools ride on a LiveKit realtime connection, which only
 * their Node package speaks — so this process stays a dumb pipe: poll Laravel
 * for sessions to join, attach a handler, forward tool calls back over HTTP.
 *
 * Required env: APP_URL, RELAY_TOKEN, RUNWAYML_API_SECRET.
 * Run with: node --env-file=.env relay/index.mjs
 */
import { createRpcHandler } from '@runwayml/avatars-node-rpc';

const APP_URL = (process.env.APP_URL ?? 'http://localhost:8000').replace(/\/$/, '');
const RELAY_TOKEN = process.env.RELAY_TOKEN;
const API_KEY = process.env.RUNWAYML_API_SECRET;
const POLL_INTERVAL_MS = 2000;

if (!RELAY_TOKEN || !API_KEY) {
    console.error('[relay] APP_URL, RELAY_TOKEN, and RUNWAYML_API_SECRET must be set.');
    process.exit(1);
}

const handlers = new Map();

async function laravel(path, { method = 'GET', body } = {}) {
    const response = await fetch(`${APP_URL}${path}`, {
        method,
        headers: {
            'X-Relay-Token': RELAY_TOKEN,
            'Content-Type': 'application/json',
            Accept: 'application/json',
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    if (!response.ok) {
        throw new Error(`Laravel responded ${response.status} for ${method} ${path}`);
    }

    return response.status === 204 ? null : response.json();
}

function forwardTo(path) {
    return async (args) => {
        console.log(`[relay] tool call → ${path}`, args);

        return laravel(path, { method: 'POST', body: args });
    };
}

async function attach(sessionId) {
    const handler = await createRpcHandler({
        apiKey: API_KEY,
        sessionId,
        tools: {
            get_weather: forwardTo('/relay/tools/weather'),
            tell_joke: forwardTo('/relay/tools/joke'),
        },
        onDisconnected: () => {
            handlers.delete(sessionId);
            laravel(`/relay/sessions/${sessionId}/end`, { method: 'POST' }).catch(() => {});
            console.log(`[relay] session ${sessionId} disconnected`);
        },
        onError: (error) => console.error(`[relay] session ${sessionId} error:`, error.message),
    });

    handlers.set(sessionId, handler);
    await laravel(`/relay/sessions/${sessionId}/claim`, { method: 'POST' });
    console.log(`[relay] attached to session ${sessionId}`);
}

async function poll() {
    try {
        const { sessions } = await laravel('/relay/sessions/pending');

        for (const sessionId of sessions) {
            if (handlers.has(sessionId)) {
                continue;
            }

            // Attach failures are expected while the session is still
            // spinning up; the session stays pending and we retry next poll.
            await attach(sessionId).catch((error) => {
                console.log(`[relay] could not attach to ${sessionId} yet: ${error.message}`);
            });
        }
    } catch (error) {
        console.error(`[relay] poll failed: ${error.message}`);
    }

    setTimeout(poll, POLL_INTERVAL_MS);
}

async function shutdown() {
    console.log('[relay] shutting down...');
    await Promise.allSettled([...handlers.values()].map((handler) => handler.close()));
    process.exit(0);
}

process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);

console.log(`[relay] watching ${APP_URL} for sessions...`);
poll();
