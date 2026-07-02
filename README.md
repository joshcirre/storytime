# Storytime

Turn a kid's drawing into a 3D character you can video-call.

Upload a drawing (or describe a character), give it a name, a personality, and a voice — Storytime renders it as a 3D animated-film style portrait and brings it to life as a live conversational avatar. Mid-call, the character can check the real weather for your city or fetch a joke.

Built as a demo of working with third-party APIs, primarily the [Runway API](https://docs.dev.runwayml.com).

## How it works

1. **Portrait** — the drawing is sent to Runway `text_to_image` (`gpt_image_2`) as a reference image with a prompt that keeps the drawing's design but re-renders it as 3D. The result is stored in object storage.
2. **Avatar** — the portrait becomes a Runway conversational avatar (`POST /v1/avatars`) with a persona composed from the name and personality you entered.
3. **Call** — starting a call creates a Runway realtime session with two `backend_rpc` tools declared (`get_weather`, `tell_joke`). The browser connects with [`@runwayml/avatars-react`](https://github.com/runwayml/avatars-sdk-react).
4. **Tools** — Runway's backend tools ride on a LiveKit connection only their Node SDK speaks, so a small relay (`relay/index.mjs`) polls the Laravel app for new sessions, joins each one, and forwards tool calls back to token-protected Laravel routes. The actual tool logic (Open-Meteo weather, icanhazdadjoke) lives in PHP.

## Stack

- Laravel 13, Inertia v3 + React 19, Tailwind v4 (React starter kit)
- Runway API: image generation, avatars, realtime sessions, backend RPC tools
- Node relay process for LiveKit tool calls
- Deployed on Laravel Cloud: Postgres, private R2 bucket (signed URLs), queue worker + relay as background processes

## Local development

```sh
composer setup        # install, migrate, build
composer dev          # server, queue, logs, vite, and the relay
```

Add your Runway key to `.env`:

```
RUNWAYML_API_SECRET=key_...
RELAY_TOKEN=any-random-string
```

Then create a character and call it. Tests: `php artisan test`.
