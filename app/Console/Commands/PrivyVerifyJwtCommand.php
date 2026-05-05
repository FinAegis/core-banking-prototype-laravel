<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Auth\Exceptions\PrivyJwtException;
use App\Domain\Auth\Services\PrivyJwtVerifier;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

/**
 * Verifies a Privy session JWT against the configured app and dumps the
 * parsed claims. Useful for confirming the claim shape that the live Privy
 * issuer sends before locking the verifier (e.g. when integrating with a new
 * mobile build).
 *
 * Usage:
 *   php artisan privy:verify-jwt "eyJhbGciOiJFUzI1NiIsImtpZCI6Ii4uLiJ9..."
 *   php artisan privy:verify-jwt "...token..." --raw   (skip signature, dump payload only)
 */
class PrivyVerifyJwtCommand extends Command
{
    protected $signature = 'privy:verify-jwt
        {token : Privy access token (JWT) to verify}
        {--raw : Decode without verifying signature (for debugging issuer/audience/exp issues)}';

    protected $description = 'Verify a Privy JWT and dump the parsed claims';

    public function handle(PrivyJwtVerifier $verifier): int
    {
        $token = (string) $this->argument('token');
        if ($token === '') {
            $this->error('Token argument is required.');

            return self::FAILURE;
        }

        $this->line('Configured app:');
        $this->line('  iss expected: ' . (string) config('privy.issuer'));
        $this->line('  aud expected: ' . (string) config('privy.app_id'));
        $this->line('  jwks_url:     ' . (string) config('privy.jwks_url'));
        $this->newLine();

        if ($this->option('raw')) {
            return $this->dumpRawPayload($token);
        }

        try {
            $claims = $verifier->verify($token);
        } catch (PrivyJwtException $e) {
            $this->error('JWT verification failed: ' . $e->getMessage());
            $this->newLine();
            $this->warn('Re-run with --raw to inspect the unverified payload.');

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('Unexpected error: ' . $e::class . ': ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info('JWT verified successfully.');
        $this->newLine();
        $this->line('Claims:');
        $this->line('  privy_user_id:  ' . $claims->privyUserId);
        $this->line('  issuer:         ' . $claims->issuer);
        $this->line('  audience:       ' . $claims->audience);
        $this->line('  issued_at:      ' . $claims->issuedAt->format(DATE_ATOM));
        $this->line('  expires_at:     ' . $claims->expiresAt->format(DATE_ATOM) .
            ' (' . max(0, $claims->expiresAt->getTimestamp() - time()) . 's remaining)');

        if ($claims->linkedAccounts === []) {
            $this->line('  linked_accounts: (none)');
        } else {
            $this->line('  linked_accounts (' . count($claims->linkedAccounts) . '):');
            foreach ($claims->linkedAccounts as $i => $account) {
                $type = isset($account['type']) && is_string($account['type']) ? $account['type'] : '(unknown)';
                $address = isset($account['address']) && is_string($account['address']) ? $account['address'] : null;
                $chain = isset($account['chain_type']) && is_string($account['chain_type']) ? $account['chain_type'] : null;
                $line = sprintf('    [%d] type=%s', $i, $type);
                if ($chain !== null) {
                    $line .= sprintf(' chain=%s', $chain);
                }
                if ($address !== null) {
                    $line .= sprintf(' address=%s', $address);
                }
                $this->line($line);
            }
        }

        return self::SUCCESS;
    }

    private function dumpRawPayload(string $token): int
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            $this->error('Token does not look like a JWT (expected 3 dot-separated segments, got ' . count($parts) . ').');

            return self::FAILURE;
        }

        try {
            $header = $this->decodeSegment($parts[0]);
            $payload = $this->decodeSegment($parts[1]);
        } catch (InvalidArgumentException $e) {
            $this->error('Could not decode token: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->warn('Signature NOT verified. Treat values as untrusted.');
        $this->newLine();

        $this->line('Header:');
        $this->line((string) json_encode($header, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->newLine();
        $this->line('Payload:');
        $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeSegment(string $segment): array
    {
        $padded = strtr($segment, '-_', '+/');
        $padded = str_pad($padded, (int) (4 * ceil(strlen($padded) / 4)), '=', STR_PAD_RIGHT);
        $raw = base64_decode($padded, true);
        if ($raw === false) {
            throw new InvalidArgumentException('segment is not valid base64url');
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('segment is not valid JSON');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
