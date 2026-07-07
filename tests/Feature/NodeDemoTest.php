<?php

use App\Models\NodeDemoRequest;

beforeEach(function () {
    config(['services.relay.token' => 'test-relay-token']);
});

function relayTokenHeaders(): array
{
    return ['X-Relay-Token' => 'test-relay-token'];
}

test('the page can queue a task for the node sidecar', function () {
    $this->postJson(route('node-demo.store'))
        ->assertStatus(202)
        ->assertJsonStructure(['id']);

    expect(NodeDemoRequest::query()->where('status', 'pending')->count())->toBe(1);
});

test('the relay lists pending tasks behind the token', function () {
    $pending = NodeDemoRequest::factory()->create();
    NodeDemoRequest::factory()->completed()->create();
    NodeDemoRequest::factory()->create(['created_at' => now()->subMinutes(5)]);

    $this->getJson(route('relay.tasks.pending'))->assertForbidden();

    $this->getJson(route('relay.tasks.pending'), relayTokenHeaders())
        ->assertOk()
        ->assertJson(['tasks' => [$pending->id]]);
});

test('the relay writes back a result the page can read', function () {
    $request = NodeDemoRequest::factory()->create();

    $result = ['runtime' => 'Node.js v22.0.0', 'engine' => 'V8 12.4', 'nonce' => 'abc'];

    $this->postJson(route('relay.tasks.complete', $request->id), ['result' => $result], relayTokenHeaders())
        ->assertNoContent();

    expect($request->refresh())
        ->status->toBe('completed')
        ->result->toBe($result)
        ->completed_at->not->toBeNull();

    $this->getJson(route('node-demo.show', $request))
        ->assertOk()
        ->assertJson(['status' => 'completed', 'result' => $result]);
});

test('completing a task requires a result payload', function () {
    $request = NodeDemoRequest::factory()->create();

    $this->postJson(route('relay.tasks.complete', $request->id), [], relayTokenHeaders())
        ->assertUnprocessable();
});

test('a pending task reads back as pending until answered', function () {
    $request = NodeDemoRequest::factory()->create();

    $this->getJson(route('node-demo.show', $request))
        ->assertOk()
        ->assertJson(['status' => 'pending', 'result' => null]);
});
