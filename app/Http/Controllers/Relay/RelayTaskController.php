<?php

namespace App\Http\Controllers\Relay;

use App\Http\Controllers\Controller;
use App\Models\NodeDemoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The Node sidecar's end of the showcase mailbox: it pulls pending tasks and
 * writes back whatever it produced in its own runtime.
 */
class RelayTaskController extends Controller
{
    /**
     * List task ids the Node sidecar still needs to run.
     * Stale requests are skipped so a restart doesn't answer abandoned tabs.
     */
    public function pending(): JsonResponse
    {
        $ids = NodeDemoRequest::query()
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(2))
            ->pluck('id');

        return response()->json(['tasks' => $ids]);
    }

    /**
     * Store the result the Node sidecar computed for a task.
     *
     * Relay routes run outside the web/api middleware groups, so there is no
     * implicit route-model binding here — we resolve the id ourselves, the way
     * the other relay controllers do.
     */
    public function complete(Request $request, string $id): Response
    {
        $validated = $request->validate([
            'result' => ['required', 'array'],
        ]);

        NodeDemoRequest::query()
            ->where('id', $id)
            ->where('status', 'pending')
            ->update([
                'status' => 'completed',
                'result' => json_encode($validated['result']),
                'completed_at' => now(),
            ]);

        return response()->noContent();
    }
}
