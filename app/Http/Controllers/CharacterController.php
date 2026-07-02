<?php

namespace App\Http\Controllers;

use App\CharacterStatus;
use App\Http\Requests\StoreCharacterRequest;
use App\Jobs\ProcessCharacter;
use App\Models\Character;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CharacterController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('characters/index', [
            'characters' => $request->user()->characters()->latest()->get()
                ->map(fn (Character $character): array => $this->presentCharacter($character)),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('characters/create', [
            'voices' => Character::VOICES,
        ]);
    }

    public function store(StoreCharacterRequest $request): RedirectResponse
    {
        $drawingPath = $request->hasFile('drawing')
            ? $request->file('drawing')->store('drawings')
            : null;

        $character = $request->user()->characters()->create([
            ...$request->safe()->only(['name', 'personality', 'voice', 'prompt']),
            'drawing_path' => $drawingPath,
        ]);

        ProcessCharacter::dispatch($character);

        return to_route('characters.show', $character);
    }

    public function retry(Request $request, Character $character): RedirectResponse
    {
        Gate::allowIf($character->user_id === $request->user()->id);

        abort_unless($character->status === CharacterStatus::Failed, 409, 'Only failed characters can be retried.');

        $character->update([
            'status' => CharacterStatus::Pending,
            'failure_reason' => null,
        ]);

        ProcessCharacter::dispatch($character);

        return to_route('characters.show', $character);
    }

    public function show(Request $request, Character $character): Response
    {
        Gate::allowIf($character->user_id === $request->user()->id);

        return Inertia::render('characters/show', [
            'character' => $this->presentCharacter($character),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function presentCharacter(Character $character): array
    {
        return [
            'id' => $character->id,
            'name' => $character->name,
            'personality' => $character->personality,
            'status' => $character->status->value,
            'isProcessing' => $character->status->isProcessing(),
            'failureReason' => $character->failure_reason,
            'imageUrl' => $character->imageUrl(),
            'drawingUrl' => $character->drawingUrl(),
            'runwayAvatarId' => $character->runway_avatar_id,
        ];
    }
}
