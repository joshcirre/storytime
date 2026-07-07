<?php

namespace App\Http\Controllers;

use App\Models\NodeDemoRequest;
use Illuminate\Http\JsonResponse;

/**
 * Public endpoints the showcase page uses to hand a task to the Node sidecar
 * and read back its result. The browser can't reach the Node process directly
 * (it isn't web-exposed), so every request round-trips through the PHP app —
 * which is the collaboration the page is meant to demonstrate.
 */
class NodeDemoController extends Controller
{
    /**
     * Queue a task for the Node sidecar to pick up on its next poll.
     */
    public function store(): JsonResponse
    {
        $request = NodeDemoRequest::create(['status' => 'pending']);

        return response()->json(['id' => $request->id], 202);
    }

    /**
     * Report whether the Node sidecar has answered yet.
     */
    public function show(NodeDemoRequest $nodeDemoRequest): JsonResponse
    {
        return response()->json([
            'status' => $nodeDemoRequest->status,
            'result' => $nodeDemoRequest->result,
        ]);
    }
}
