<?php

declare(strict_types=1);

use App\Domain\Auth\Services\PrivyEmailOtpClient;
use App\Models\User;
use Illuminate\Cache\ArrayStore;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;

/*
| POST /login/privy/send asks Privy to email a login code. It was throttled
| per IP only, so rotating IPs could flood one inbox. Each address now gets at
| most 3 codes an hour and 10 a day, counted on a keyed hash of the trimmed,
| lower-cased address. Over the limit Privy is not called and the answer is
| the same as for a sent code.
*/

beforeEach(function (): void {
    Cache::flush();
    config([
        'cache.default'                => 'array',
        'privy.app_id'                 => 'pcl-app-id',
        'privy.app_secret'             => 'pcl-app-secret',
        'privy.web_login_enabled'      => true,
        'registration.api_enabled'     => true,
        'registration.api_invite_only' => false,
    ]);
});

/**
 * Bind a Privy client double that counts the codes it was asked to send.
 *
 * @param  array<int, string>  $sent
 */
function pclPrivy(array &$sent): void
{
    /** @var PrivyEmailOtpClient&MockInterface $mock */
    $mock = Mockery::mock(PrivyEmailOtpClient::class);
    $mock->shouldReceive('sendCode')->andReturnUsing(function (string $email) use (&$sent): void {
        $sent[] = $email;
    });
    app()->instance(PrivyEmailOtpClient::class, $mock);
}

/**
 * Check that the answer is the one a sent code gets.
 *
 * @param  Illuminate\Testing\TestResponse<Symfony\Component\HttpFoundation\Response>  $response
 */
function pclAssertSentAnswer(Illuminate\Testing\TestResponse $response, string $normalised): void
{
    $response->assertRedirect(route('login', ['email' => $normalised, 'step' => 'verify']))
        ->assertSessionHas('status', "If this email can sign in, a 6-digit code is on its way to {$normalised}.")
        ->assertSessionHasNoErrors();
}

/**
 * A different client IP for each request.
 *
 * @return array<string, string>
 */
function pclIp(int $n): array
{
    return ['REMOTE_ADDR' => '198.51.100.' . ($n % 250 + 1)];
}

it('sends at most 3 codes an hour to one address, whatever the IP and letter case', function (): void {
    User::factory()->create(['email' => 'victim@example.com']);
    $sent = [];
    pclPrivy($sent);

    $variants = ['victim@example.com', 'Victim@Example.com', ' VICTIM@example.com ', 'victim@EXAMPLE.com', 'victim@example.com'];
    foreach ($variants as $n => $typed) {
        pclAssertSentAnswer($this->withServerVariables(pclIp($n))->from(route('login'))->post('/login/privy/send', ['email' => $typed]), 'victim@example.com');
    }

    expect($sent)->toBe(['victim@example.com', 'victim@example.com', 'victim@example.com']);
});

it('sends at most 10 codes a day to one address', function (): void {
    User::factory()->create(['email' => 'victim@example.com']);
    $sent = [];
    pclPrivy($sent);

    $n = 0;
    for ($hour = 0; $hour < 5; $hour++) {
        for ($i = 0; $i < 3; $i++) {
            pclAssertSentAnswer($this->withServerVariables(pclIp($n++))->from(route('login'))->post('/login/privy/send', ['email' => 'victim@example.com']), 'victim@example.com');
        }
        $this->travel(61)->minutes();
    }

    expect($sent)->toHaveCount(10);

    $this->travel(1)->days();
    pclAssertSentAnswer($this->withServerVariables(pclIp($n))->from(route('login'))->post('/login/privy/send', ['email' => 'victim@example.com']), 'victim@example.com');

    expect($sent)->toHaveCount(11);
});

it('keeps counting each address on its own', function (): void {
    $sent = [];
    pclPrivy($sent);

    for ($n = 0; $n < 4; $n++) {
        pclAssertSentAnswer($this->withServerVariables(pclIp($n))->from(route('login'))->post('/login/privy/send', ['email' => 'first@example.com']), 'first@example.com');
    }
    pclAssertSentAnswer($this->withServerVariables(pclIp(9))->from(route('login'))->post('/login/privy/send', ['email' => 'second@example.com']), 'second@example.com');

    expect(array_count_values($sent))->toBe(['first@example.com' => 3, 'second@example.com' => 1]);
});

it('never puts the raw address in a cache key', function (): void {
    $sent = [];
    pclPrivy($sent);

    for ($n = 0; $n < 4; $n++) {
        pclAssertSentAnswer($this->withServerVariables(pclIp($n))->from(route('login'))->post('/login/privy/send', ['email' => 'Secret.Person@Example.com']), 'secret.person@example.com');
    }

    $store = Cache::store('array')->getStore();
    expect($store)->toBeInstanceOf(ArrayStore::class);
    $storage = (new ReflectionProperty(ArrayStore::class, 'storage'))->getValue($store);
    $keys = implode("\n", array_keys((array) $storage));

    expect($keys)->toContain('privy-web-login:send-email')
        ->and(strtolower($keys))->not->toContain('secret.person')
        ->and(strtolower($keys))->not->toContain(sha1('secret.person@example.com'))
        ->and(strtolower($keys))->not->toContain(hash('sha256', 'secret.person@example.com'));
});
