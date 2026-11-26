<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Models\ApiKey;
use App\Models\User;

/*
| The owner sets the money limits and the account pin of a key on the web
| API key pages. Limits are optional positive amounts with at most two
| decimals, never above API_KEY_LIMIT_MAX; picked accounts must be the
| owner's own.
*/

beforeEach(function (): void {
    config(['cache.default' => 'array']);

    $this->owner = User::factory()->create();
    $this->account = Account::factory()->create(['user_uuid' => $this->owner->uuid, 'name' => 'Savings for taxes']);
    $this->secondAccount = Account::factory()->create(['user_uuid' => $this->owner->uuid, 'name' => 'Daily spending']);
    $this->strangerAccount = Account::factory()->create(['user_uuid' => User::factory()->create()->uuid]);

    $this->actingAs($this->owner);
});

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function akmfForm(array $extra = []): array
{
    return array_merge([
        'name'        => 'Shop integration',
        'permissions' => ['read', 'write'],
        'expires_in'  => 'never',
    ], $extra);
}

function akmfLatestKey(User $user): ApiKey
{
    $key = ApiKey::query()->where('user_uuid', $user->uuid)->latest('id')->first();
    assert($key instanceof ApiKey);

    return $key;
}

it('stores both limits and the pinned accounts on create', function (): void {
    $this->post(route('api-keys.store'), akmfForm([
        'transfer_limit'        => '250.5',
        'daily_limit'           => '1200',
        'allowed_account_uuids' => [$this->account->uuid],
    ]))->assertRedirect()->assertSessionHasNoErrors();

    $key = akmfLatestKey($this->owner);
    expect($key->transfer_limit)->toBe('250.50')
        ->and($key->daily_limit)->toBe('1200.00')
        ->and($key->pinnedAccountUuids())->toBe([$this->account->uuid])
        ->and($key->effectiveTransferLimit())->toBe('250.50');
});

it('leaves the limits empty to use the standard limits and all accounts', function (): void {
    $this->post(route('api-keys.store'), akmfForm(['transfer_limit' => '', 'daily_limit' => '']))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $key = akmfLatestKey($this->owner);
    expect($key->transfer_limit)->toBeNull()
        ->and($key->daily_limit)->toBeNull()
        ->and($key->allowed_account_uuids)->toBeNull()
        ->and($key->effectiveTransferLimit())->toBe('1000.00')
        ->and($key->effectiveDailyLimit())->toBe('5000.00');
});

it('refuses a limit that is not a positive amount with at most two decimals', function (string $value): void {
    $this->post(route('api-keys.store'), akmfForm(['transfer_limit' => $value]))
        ->assertSessionHasErrors('transfer_limit');

    expect(ApiKey::query()->where('user_uuid', $this->owner->uuid)->count())->toBe(0);
})->with(['0', '0.00', '-5', '12.345', 'ten', '1e3']);

it('refuses a limit above the ceiling', function (): void {
    $this->post(route('api-keys.store'), akmfForm(['daily_limit' => '50000.01']))
        ->assertSessionHasErrors(['daily_limit' => 'The limit cannot be more than 50,000.00.']);

    $this->post(route('api-keys.store'), akmfForm(['daily_limit' => '50000']))
        ->assertSessionHasNoErrors();
});

it('refuses an account the owner does not own', function (): void {
    $this->post(route('api-keys.store'), akmfForm(['allowed_account_uuids' => [$this->strangerAccount->uuid]]))
        ->assertSessionHasErrors('allowed_account_uuids.0');

    expect(ApiKey::query()->where('user_uuid', $this->owner->uuid)->count())->toBe(0);
});

it('updates the limits and the pin, and clears them again', function (): void {
    $key = ApiKey::createForUser($this->owner, ['name' => 'k', 'permissions' => ['read', 'write']])['api_key'];

    $this->put(route('api-keys.update', $key), akmfForm([
        'transfer_limit'        => '75',
        'daily_limit'           => '300',
        'allowed_account_uuids' => [$this->secondAccount->uuid],
    ]))->assertRedirect(route('api-keys.show', $key))->assertSessionHasNoErrors();

    $key->refresh();
    expect($key->transfer_limit)->toBe('75.00')
        ->and($key->daily_limit)->toBe('300.00')
        ->and($key->pinnedAccountUuids())->toBe([$this->secondAccount->uuid]);

    $this->put(route('api-keys.update', $key), akmfForm())->assertSessionHasNoErrors();

    $key->refresh();
    expect($key->transfer_limit)->toBeNull()
        ->and($key->daily_limit)->toBeNull()
        ->and($key->allowed_account_uuids)->toBeNull();
});

it('refuses an update with someone else\'s account', function (): void {
    $key = ApiKey::createForUser($this->owner, ['name' => 'k', 'permissions' => ['read', 'write']])['api_key'];

    $this->put(route('api-keys.update', $key), akmfForm(['allowed_account_uuids' => [$this->strangerAccount->uuid]]))
        ->assertSessionHasErrors('allowed_account_uuids.0');

    expect($key->refresh()->allowed_account_uuids)->toBeNull();
});

it('keeps the money controls when the key is regenerated', function (): void {
    $key = ApiKey::createForUser($this->owner, [
        'name'                  => 'k',
        'permissions'           => ['read', 'write'],
        'transfer_limit'        => '40.00',
        'daily_limit'           => '90.00',
        'allowed_account_uuids' => [$this->account->uuid],
    ])['api_key'];

    $this->post(route('api-keys.regenerate', $key))->assertRedirect();

    $new = akmfLatestKey($this->owner);
    expect($new->id)->not->toBe($key->id)
        ->and($new->transfer_limit)->toBe('40.00')
        ->and($new->daily_limit)->toBe('90.00')
        ->and($new->pinnedAccountUuids())->toBe([$this->account->uuid]);
});

it('shows the account picker and the standard limits on the forms', function (): void {
    $key = ApiKey::createForUser($this->owner, ['name' => 'k', 'permissions' => ['read']])['api_key'];

    foreach ([route('api-keys.create'), route('api-keys.edit', $key)] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertSee('Largest single amount')
            ->assertSee('Largest total per day')
            ->assertSee('Leave this empty to use the standard limit of 1,000.00.')
            ->assertSee('Neither limit can be higher than 50,000.00.')
            ->assertSee('Savings for taxes')
            ->assertSee('Daily spending')
            ->assertDontSee($this->strangerAccount->uuid);
    }
});

it('shows the current limits and pinned accounts on the key page', function (): void {
    $key = ApiKey::createForUser($this->owner, [
        'name'                  => 'k',
        'permissions'           => ['read', 'write'],
        'daily_limit'           => '2500',
        'allowed_account_uuids' => [$this->account->uuid],
    ])['api_key'];

    $this->get(route('api-keys.show', $key))
        ->assertOk()
        ->assertSeeInOrder(['Largest single amount', '1,000.00', '(the standard limit)'])
        ->assertSeeInOrder(['Largest total per day', '2,500.00'])
        ->assertSeeInOrder(['Moved today', '0.00'])
        ->assertSee('Savings for taxes')
        ->assertDontSee('Daily spending');

    $unpinned = ApiKey::createForUser($this->owner, ['name' => 'k2', 'permissions' => ['read']])['api_key'];
    $this->get(route('api-keys.show', $unpinned))->assertOk()->assertSee('All of your accounts.');
});

it('refuses a per-transfer limit above the daily limit', function (array $limits): void {
    $this->post(route('api-keys.store'), akmfForm($limits))
        ->assertSessionHasErrors(['transfer_limit' => 'The limit for one transfer cannot be more than the daily limit.']);

    expect(ApiKey::query()->where('user_uuid', $this->owner->uuid)->count())->toBe(0);
})->with([
    'both set'         => [['transfer_limit' => '1500', 'daily_limit' => '1000']],
    'daily left empty' => [['transfer_limit' => '6000', 'daily_limit' => '']],
]);

it('accepts a daily limit below the standard per-transfer limit when no per-transfer limit is entered', function (): void {
    $this->post(route('api-keys.store'), akmfForm(['transfer_limit' => '', 'daily_limit' => '500']))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(akmfLatestKey($this->owner)->daily_limit)->toBe('500.00');
});

it('accepts a per-transfer limit equal to the daily limit', function (): void {
    $this->post(route('api-keys.store'), akmfForm(['transfer_limit' => '800', 'daily_limit' => '800.00']))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(akmfLatestKey($this->owner)->transfer_limit)->toBe('800.00');
});

it('refuses a per-transfer limit above the daily limit on edit too', function (): void {
    $key = ApiKey::createForUser($this->owner, ['name' => 'edit me', 'permissions' => ['read']])['api_key'];

    $this->put(route('api-keys.update', $key), akmfForm(['transfer_limit' => '300', 'daily_limit' => '200']))
        ->assertSessionHasErrors('transfer_limit');

    expect($key->fresh()->transfer_limit)->toBeNull();
});
