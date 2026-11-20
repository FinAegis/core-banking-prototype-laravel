<?php

declare(strict_types=1);

namespace App\Domain\AI\MCP\Tools\SMS;

use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use App\Domain\SMS\Services\SmsPricingService;
use App\Domain\SMS\Services\SmsSendBudget;
use App\Domain\SMS\Services\SmsService;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * MCP tool that allows AI agents to send SMS via VertexSMS.
 *
 * Agents discover this tool automatically. This tool provides pricing info
 * and delegates to the SMS service. A send through this tool is not paid
 * for, so it needs a signed-in user and counts against the same daily
 * unpaid-send limits as POST /api/v1/sms/send (per user and all users
 * together, {@see SmsSendBudget}).
 *
 * The public MCP server calls execute() without validating the input
 * schema, so execute() applies the same rules as POST /api/v1/sms/send
 * (SmsController::send: `to` and `from` formats, message at most 1600
 * characters) and a per-user per-minute limit
 * (`mcp.rate_limits.sms_per_minute`). The daily limits and the token's
 * spending limit count one unit per call, so a message must also fit in
 * one billed SMS part ({@see self::billedParts()}).
 */
class SmsSendTool implements MCPToolInterface
{
    /** Same rule as POST /api/v1/sms/send `to`. */
    public const TO_PATTERN = '/^\+?[1-9]\d{4,18}$/';

    /** Same rule as POST /api/v1/sms/send `from`. */
    public const FROM_PATTERN = '/^[a-zA-Z0-9 ]{1,20}$/';

    /** Same rule as POST /api/v1/sms/send `message` (max:1600). */
    public const MAX_MESSAGE_LENGTH = 1600;

    /** Billed SMS parts one call may send (one unit of every limit). */
    public const MAX_PARTS = 1;

    public function __construct(
        private readonly SmsService $smsService,
        private readonly SmsPricingService $pricing,
        private readonly SmsSendBudget $budget,
    ) {
    }

    public function getName(): string
    {
        return 'sms.send';
    }

    public function getCategory(): string
    {
        return 'sms';
    }

    public function getDescription(): string
    {
        return 'Send an SMS message via VertexSMS while SMS sending is enabled. Requires a '
            . 'signed-in user. A send through this tool is not paid per message: it counts '
            . 'against the daily limits for unpaid sends shared with POST /api/v1/sms/send, '
            . 'per user and across all users, which reset at 00:00 UTC (on the public MCP '
            . 'server it also uses the token\'s spending limit). A message must fit in one '
            . 'SMS part: 160 GSM characters, or 70 when it has other characters. Call '
            . 'sms.rates or set check_price_only to see the price for the destination country.';
    }

    /** @return array<string, mixed> */
    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'to' => [
                    'type'        => 'string',
                    'description' => 'Destination phone number in E.164 format (e.g., +37069912345)',
                    'pattern'     => '^\+?[1-9][0-9]{4,18}$',
                ],
                'from' => [
                    'type'        => 'string',
                    'description' => 'Sender ID: 1-20 letters, digits or spaces (default: Zelta)',
                    'pattern'     => '^[a-zA-Z0-9 ]{1,20}$',
                    'maxLength'   => 20,
                ],
                'message' => [
                    'type'        => 'string',
                    'description' => 'SMS message text that fits in one SMS part: up to 160 GSM characters, or 70 when it has other characters',
                    'minLength'   => 1,
                    'maxLength'   => 160,
                ],
                'check_price_only' => [
                    'type'        => 'boolean',
                    'description' => 'If true, returns price without sending. Default: false.',
                ],
            ],
            'required' => ['to', 'message'],
        ];
    }

    /** @return array<string, mixed> */
    public function getOutputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'message_id' => ['type' => 'string'],
                'status'     => ['type' => 'string'],
                'parts'      => ['type' => 'integer'],
                'price_usdc' => ['type' => 'string'],
                'country'    => ['type' => 'string'],
                'note'       => ['type' => 'string'],
            ],
        ];
    }

    /** @return array<string> */
    public function getCapabilities(): array
    {
        return ['write', 'sms', 'communication', 'daily-limits', 'spending-limits'];
    }

    public function isCacheable(): bool
    {
        return false;
    }

    public function getCacheTtl(): int
    {
        return 0;
    }

    /** @param array<string, mixed> $parameters */
    public function validateInput(array $parameters): bool
    {
        return isset($parameters['to'], $parameters['message'])
            && is_string($parameters['to'])
            && is_string($parameters['message'])
            && $parameters['to'] !== ''
            && $parameters['message'] !== '';
    }

    public function authorize(?string $userId): bool
    {
        return (bool) config('sms.enabled', false);
    }

    /** @param array<string, mixed> $parameters */
    public function execute(array $parameters, ?string $conversationId = null): ToolExecutionResult
    {
        $to = (string) ($parameters['to'] ?? '');
        $message = (string) ($parameters['message'] ?? '');
        $from = (string) ($parameters['from'] ?? config('sms.defaults.sender_id', 'Zelta'));
        $checkPriceOnly = (bool) ($parameters['check_price_only'] ?? false);

        if ($to === '' || $message === '') {
            return ToolExecutionResult::failure('Missing required parameters: to, message');
        }

        $invalid = self::invalidInput($to, $from, $message);
        if ($invalid !== null) {
            return ToolExecutionResult::failure($invalid);
        }

        // The public MCP server executes tools without calling authorize(),
        // so the master switch is checked here as well (and in SmsService).
        if (! $this->authorize(null)) {
            return ToolExecutionResult::failure('SMS service is not enabled');
        }

        try {
            $price = $this->pricing->getPriceForNumber($to);

            if ($checkPriceOnly) {
                return ToolExecutionResult::success([
                    'price_usdc' => $price['amount_usdc'],
                    'rate_eur'   => $price['rate_eur'],
                    'country'    => $price['country_code'],
                    'parts'      => 1,
                    'note'       => 'This is the price for a single-part SMS, in atomic USDC. '
                        . 'Sends through this tool and POST /api/v1/sms/send with a Sanctum token '
                        . 'are not paid: they need a signed-in user and count against the per-user '
                        . 'and all-users daily limits, which reset at 00:00 UTC. Where MPP payment '
                        . 'is enabled for POST /api/v1/sms/send, a payment of at least '
                        . $this->minimumPaidPrice() . ' atomic USDC counts as a paid send.',
                ]);
            }

            $user = $this->resolveUser();

            if ($user === null) {
                return ToolExecutionResult::failure('SMS sending requires a signed-in user');
            }

            $minuteKey = 'mcp-sms-send:' . $user->id . ':minute';
            $perMinute = max(1, (int) config('mcp.rate_limits.sms_per_minute', 10));
            if (RateLimiter::tooManyAttempts($minuteKey, $perMinute)) {
                return ToolExecutionResult::failure(sprintf(
                    'Too many SMS sends. Try again in %d seconds.',
                    RateLimiter::availableIn($minuteKey),
                ));
            }
            RateLimiter::hit($minuteKey, 60);

            // Release against the day the unit was reserved from.
            $day = SmsSendBudget::today();

            if ($this->budget->reserve($user->id, 'mcp', $day) !== null) {
                return ToolExecutionResult::failure("Today's SMS sending limit has been reached. Try again after 00:00 UTC.");
            }

            try {
                $result = $this->smsService->send($to, $from, $message);
            } catch (Exception $e) {
                $this->budget->release($user->id, $day);

                throw $e;
            }

            Log::info('MCP: SMS sent via tool', [
                'to'         => $to,
                'message_id' => $result['message_id'],
            ]);

            return ToolExecutionResult::success([
                'message_id' => $result['message_id'],
                'status'     => $result['status'],
                'parts'      => $result['parts'],
                'price_usdc' => $result['price_usdc'],
                'country'    => $price['country_code'],
                'note'       => 'SMS sent successfully. Use GET /api/v1/sms/status/{message_id} to check delivery.',
            ]);
        } catch (Exception $e) {
            Log::warning('MCP: SMS send failed', [
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);

            return ToolExecutionResult::failure('SMS send failed: ' . $e->getMessage());
        }
    }

    /**
     * Why the input breaks the POST /api/v1/sms/send rules, or null.
     */
    private static function invalidInput(string $to, string $from, string $message): ?string
    {
        if (preg_match(self::TO_PATTERN, $to) !== 1) {
            return 'Invalid to: use a phone number in E.164 format (5-19 digits, optional leading +)';
        }

        if (preg_match(self::FROM_PATTERN, $from) !== 1) {
            return 'Invalid from: use 1-20 letters, digits or spaces';
        }

        if (mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            return sprintf('Invalid message: at most %d characters', self::MAX_MESSAGE_LENGTH);
        }

        if (self::billedParts($message) > self::MAX_PARTS) {
            return 'Invalid message: it must fit in one SMS part (160 GSM characters, or 70 when it has other characters)';
        }

        return null;
    }

    /**
     * SMS parts the message is billed as ({@see SmsSendBudget::billedParts()}).
     */
    public static function billedParts(string $message): int
    {
        return SmsSendBudget::billedParts($message);
    }

    /**
     * Smallest payment, in atomic USDC, that counts as a paid send
     * (SMS_MIN_PAID_PRICE_USDC).
     */
    private function minimumPaidPrice(): string
    {
        return (string) max(0, (int) config('sms.min_paid_price_usdc', 10000));
    }

    /**
     * The signed-in user: under the MCP server, the user the bearer token is
     * bound to (McpOAuthGuard switches the default guard to `api`).
     */
    private function resolveUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
