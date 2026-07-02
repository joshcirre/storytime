<?php

namespace App\Http\Controllers;

use App\CharacterStatus;
use App\Http\Resources\CharacterResource;
use App\Models\CallSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        $characters = $request->user()->characters()->latest()->get();

        return Inertia::render('dashboard', [
            'characters' => CharacterResource::collection($characters->take(6))->resolve(),
            'stats' => [
                'characters' => $characters->count(),
                'ready' => $characters->where('status', CharacterStatus::Ready)->count(),
                'calls' => CallSession::whereIn('character_id', $characters->pluck('id'))->count(),
            ],
        ]);
    }
}
