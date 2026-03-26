<?php

declare(strict_types=1);

test('a2a agent card is served at well-known endpoint', function () {
    $response = $this->getJson('/api/.well-known/agent.json');
    $response->assertOk();
    $response->assertJsonStructure([
        'name', 'description', 'url', 'provider', 'version',
        'capabilities'   => ['streaming', 'pushNotifications'],
        'authentication' => ['schemes'],
        'defaultInputModes', 'defaultOutputModes',
        'skills' => [['id', 'name', 'description']],
    ]);
});

test('a2a agent card returns correct provider info', function () {
    $response = $this->getJson('/api/.well-known/agent.json');
    $response->assertOk();
    $response->assertJsonFragment(['version' => '1.0.0']);
});

test('a2a agent card does not require authentication', function () {
    $response = $this->getJson('/api/.well-known/agent.json');
    $response->assertOk();
});
