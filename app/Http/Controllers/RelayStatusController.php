<?php

namespace App\Http\Controllers;

use App\Support\RelayHeartbeat;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Public showcase page proving the Node relay runs alongside the PHP app
 * within a single Laravel Cloud environment. No auth: it is meant to be shared.
 */
class RelayStatusController extends Controller
{
    /**
     * Render the live status page.
     */
    public function show(): View
    {
        return view('relay-status', ['status' => RelayHeartbeat::status()]);
    }

    /**
     * Return the raw status the page polls for live updates.
     */
    public function data(): JsonResponse
    {
        return response()->json(RelayHeartbeat::status());
    }
}
