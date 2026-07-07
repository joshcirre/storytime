<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Node Sidecar Status — PHP + Node on Laravel Cloud</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <style>
        :root {
            --bg: #0b0b0f;
            --panel: #15151d;
            --border: #26263540;
            --text: #eaeaf0;
            --muted: #9a9ab0;
            --coral: #f53003;
            --online: #22c55e;
            --offline: #ef4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: radial-gradient(1200px 600px at 50% -10%, #1a1230 0%, var(--bg) 55%);
            color: #eaeaf0;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 20px;
            line-height: 1.5;
        }
        .card {
            width: 100%;
            max-width: 720px;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 30px 80px -20px #000000cc;
        }
        .eyebrow {
            text-transform: uppercase;
            letter-spacing: 0.14em;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
        }
        h1 { font-size: 26px; margin: 8px 0 24px; font-weight: 700; letter-spacing: -0.02em; }
        .status-row { display: flex; align-items: center; gap: 14px; margin-bottom: 8px; }
        .dot { width: 14px; height: 14px; border-radius: 50%; flex: none; }
        .dot.online {
            background: var(--online);
            box-shadow: 0 0 0 0 #22c55e88;
            animation: pulse 1.8s infinite;
        }
        .dot.offline { background: var(--offline); }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 #22c55e88; }
            70% { box-shadow: 0 0 0 12px #22c55e00; }
            100% { box-shadow: 0 0 0 0 #22c55e00; }
        }
        .state-label { font-size: 20px; font-weight: 700; }
        .state-label.online { color: var(--online); }
        .state-label.offline { color: var(--offline); }
        .last-seen { color: var(--muted); font-size: 14px; margin-bottom: 28px; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 32px; }
        .stat { background: #ffffff08; border: 1px solid var(--border); border-radius: 14px; padding: 16px; }
        .stat .value { font-size: 28px; font-weight: 700; font-variant-numeric: tabular-nums; }
        .stat .key { font-size: 12px; color: var(--muted); margin-top: 4px; }
        .flow {
            display: flex; align-items: stretch; gap: 8px; flex-wrap: wrap;
            background: #00000030; border: 1px solid var(--border); border-radius: 14px;
            padding: 16px; margin-bottom: 24px;
        }
        .node { flex: 1 1 120px; text-align: center; padding: 10px 8px; border-radius: 10px; background: #ffffff06; }
        .node .rt { font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted); }
        .node .nm { font-size: 14px; font-weight: 600; margin-top: 2px; }
        .node.php { outline: 1px solid #7a86ff40; }
        .node.node { outline: 1px solid #22c55e40; }
        .arrow { align-self: center; color: var(--muted); font-size: 18px; }
        .explain { color: var(--muted); font-size: 14px; }
        .explain strong { color: #eaeaf0; }
        .explain .coral { color: var(--coral); font-weight: 600; }
        .usecases { margin-top: 24px; }
        .usecases-title { font-size: 13px; font-weight: 600; color: #eaeaf0; margin-bottom: 10px; }
        .usecases ul { list-style: none; display: grid; gap: 8px; }
        .usecases li { position: relative; padding-left: 22px; color: var(--muted); font-size: 14px; }
        .usecases li::before { content: "▹"; position: absolute; left: 4px; color: var(--coral); }
        footer { margin-top: 24px; font-size: 12px; color: var(--muted); display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
        code { background: #ffffff10; padding: 1px 6px; border-radius: 5px; font-size: 13px; }
        @media (max-width: 560px) { .grid { grid-template-columns: 1fr; } .card { padding: 28px 22px; } }
    </style>
</head>
<body>
    <main class="card">
        <div class="eyebrow">Live infrastructure</div>
        <h1>Node sidecar status</h1>

        <div class="status-row">
            <span id="dot" class="dot {{ $status['online'] ? 'online' : 'offline' }}"></span>
            <span id="state" class="state-label {{ $status['online'] ? 'online' : 'offline' }}">
                {{ $status['online'] ? 'Online' : 'Offline' }}
            </span>
        </div>
        <div class="last-seen" id="last-seen">
            @if ($status['last_seen_seconds'] === null)
                No heartbeat received yet
            @else
                Last heartbeat {{ $status['last_seen_seconds'] }}s ago
            @endif
        </div>

        <div class="grid">
            <div class="stat">
                <div class="value" id="active">{{ $status['active_sessions'] }}</div>
                <div class="key">Live tasks on Node</div>
            </div>
            <div class="stat">
                <div class="value" id="uptime">{{ $status['uptime_seconds'] === null ? '—' : $status['uptime_seconds'].'s' }}</div>
                <div class="key">Relay uptime</div>
            </div>
            <div class="stat">
                <div class="value" id="today">{{ $status['sessions_today'] }}</div>
                <div class="key">Sessions today</div>
            </div>
        </div>

        <div class="flow">
            <div class="node php">
                <div class="rt">PHP runtime</div>
                <div class="nm">Laravel app</div>
            </div>
            <div class="arrow">⇄</div>
            <div class="node node">
                <div class="rt">Node runtime</div>
                <div class="nm">Sidecar process</div>
            </div>
            <div class="arrow">⇄</div>
            <div class="node">
                <div class="rt">Node-only</div>
                <div class="nm">SDKs &amp; realtime services</div>
            </div>
        </div>

        <p class="explain">
            This page is served by <strong>Laravel (PHP)</strong>. The light above turns green only
            when a <strong>separate Node.js process</strong> — running as a background process in the
            <span class="coral">same Laravel Cloud environment</span> — is alive and heartbeating.
            Some SDKs and realtime clients only ship for Node. Instead of standing up a second app to
            run them, you keep one Laravel project and let a Node sidecar hold whatever it needs, talking
            to PHP over HTTP. Two runtimes, one deploy.
        </p>

        <div class="usecases">
            <div class="usecases-title">What you could run in that Node process</div>
            <ul>
                <li>Realtime servers — WebSocket / WebRTC / LiveKit gateways</li>
                <li>Headless browsers — Puppeteer &amp; Playwright for scraping and PDFs</li>
                <li>Node-first SDKs — AI, voice, and vendor clients with no PHP equivalent</li>
                <li>Streaming &amp; queue consumers that need a long-lived connection</li>
                <li>gRPC / protobuf and other socket clients PHP can't easily speak</li>
            </ul>
        </div>

        <footer>
            <span>Auto-refreshing every 2s · <code>node relay/index.mjs</code></span>
            <span id="checked"></span>
        </footer>
    </main>

    <script>
        function humanUptime(seconds) {
            if (seconds === null || seconds === undefined) return '—';
            if (seconds < 60) return seconds + 's';
            if (seconds < 3600) return Math.floor(seconds / 60) + 'm';
            if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ' + Math.floor((seconds % 3600) / 60) + 'm';
            return Math.floor(seconds / 86400) + 'd ' + Math.floor((seconds % 86400) / 3600) + 'h';
        }

        async function refresh() {
            try {
                const res = await fetch('{{ route('relay-status.data') }}', { headers: { Accept: 'application/json' } });
                if (!res.ok) return;
                const s = await res.json();

                const dot = document.getElementById('dot');
                const state = document.getElementById('state');
                dot.className = 'dot ' + (s.online ? 'online' : 'offline');
                state.className = 'state-label ' + (s.online ? 'online' : 'offline');
                state.textContent = s.online ? 'Online' : 'Offline';

                document.getElementById('last-seen').textContent = s.last_seen_seconds === null
                    ? 'No heartbeat received yet'
                    : 'Last heartbeat ' + s.last_seen_seconds + 's ago';
                document.getElementById('active').textContent = s.active_sessions;
                document.getElementById('uptime').textContent = humanUptime(s.uptime_seconds);
                document.getElementById('today').textContent = s.sessions_today;

                const checked = new Date(s.checked_at).toLocaleTimeString();
                document.getElementById('checked').textContent = 'Checked ' + checked;
            } catch (e) {
                // Network blips are expected; the next tick recovers.
            }
        }

        refresh();
        setInterval(refresh, 2000);
    </script>
</body>
</html>
