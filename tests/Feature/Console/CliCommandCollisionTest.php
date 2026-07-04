<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

// Wave 1B — the four partner:api-key subcommands previously all declared the
// signature 'partner:api-key <action> …'. Symfony takes the first whitespace
// token as the command NAME, so all four registered as 'partner:api-key' and
// create/list/rotate were unreachable. Colon-separated names fix it. Same class
// of bug for the two 'voting:setup' commands.

it('registers each partner:api-key subcommand as a distinct command', function () {
    $commands = array_keys(Artisan::all());

    $expected = [
        'partner:api-key:create',
        'partner:api-key:list',
        'partner:api-key:rotate',
        'partner:api-key:revoke',
    ];

    foreach ($expected as $name) {
        $this->assertContains($name, $commands, "Command {$name} is not registered");
    }

    // The old colliding bare name must no longer exist.
    $this->assertNotContains('partner:api-key', $commands);
});

it('registers both voting setup commands under distinct names', function () {
    $commands = array_keys(Artisan::all());

    $this->assertContains('voting:setup', $commands);
    $this->assertContains('voting:setup-batch', $commands);
});
