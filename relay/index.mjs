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
import { randomUUID } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { createRpcHandler } from '@runwayml/avatars-node-rpc';

// Read the installed package.json straight off disk (its `exports` map hides
// the file from `require`), so the reported version is whatever Node actually
// loaded — proof a Node-only SDK is present in this runtime.
function readSdkVersion() {
    try {
        const pkgUrl = new URL('./node_modules/@runwayml/avatars-node-rpc/package.json', import.meta.url);

        return JSON.parse(readFileSync(pkgUrl, 'utf8')).version;
    } catch {
        return 'unknown';
    }
}

const sdkVersion = readSdkVersion();

const APP_URL = (process.env.APP_URL ?? 'http://localhost:8000').replace(/\/$/, '');
const RELAY_TOKEN = process.env.RELAY_TOKEN;
const API_KEY = process.env.RUNWAYML_API_SECRET;
const POLL_INTERVAL_MS = 2000;

if (!RELAY_TOKEN || !API_KEY) {
    console.error('[relay] APP_URL, RELAY_TOKEN, and RUNWAYML_API_SECRET must be set.');
    process.exit(1);
}

const handlers = new Map();
const STARTED_AT = new Date().toISOString();

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

async function heartbeat() {
    try {
        await laravel('/relay/heartbeat', {
            method: 'POST',
            body: { active_sessions: handlers.size, started_at: STARTED_AT },
        });
    } catch (error) {
        console.error(`[relay] heartbeat failed: ${error.message}`);
    }
}

/**
 * Produce a payload the PHP app could not have generated itself: it runs in
 * V8, reports the Node/engine build, and names a Node-only SDK it has loaded.
 */
function runDemo() {
    const start = process.hrtime.bigint();
    const nonce = randomUUID();
    const computedInMs = Number(process.hrtime.bigint() - start) / 1e6;

    return {
        runtime: `Node.js ${process.version}`,
        engine: `V8 ${process.versions.v8}`,
        platform: `${process.platform}/${process.arch}`,
        sdk: `@runwayml/avatars-node-rpc@${sdkVersion}`,
        uptime_seconds: Math.round(process.uptime()),
        nonce,
        computed_in_ms: Number(computedInMs.toFixed(3)),
    };
}

/**
 * Answer any tasks the page has queued. Runs faster than the session poll so
 * the showcase button feels responsive.
 */
async function pollTasks() {
    try {
        const { tasks } = await laravel('/relay/tasks');

        for (const id of tasks) {
            await laravel(`/relay/tasks/${id}`, { method: 'POST', body: { result: runDemo() } });
            console.log(`[relay] answered demo task ${id}`);
        }
    } catch (error) {
        console.error(`[relay] task poll failed: ${error.message}`);
    }

    setTimeout(pollTasks, 750);
}

async function poll() {
    await heartbeat();

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
pollTasks();
