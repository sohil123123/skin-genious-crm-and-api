<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('facial v34 self-test requires authentication', function () {
    $response = $this->getJson('/api/facial-v34/self-test');
    $response->assertStatus(401);
});

test('facial v34 self-test returns success for authenticated users', function () {
    $user = User::factory()->make();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/facial-v34/self-test');
    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Facial V3.4 runner is available.',
            'results' => [
                'ok' => true,
                'engine_version' => 'facial_v3_4',
            ],
        ]);
});
