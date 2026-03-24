<?php

declare(strict_types=1);

namespace App\Domain\AI\MCP\Tools\SMS;

use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use App\Domain\SMS\Services\SmsPricingService;
use App\Domain\SMS\Services\SmsService;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * MCP tool that allows AI agents to send SMS via VertexSMS.
 *
 * Agents discover this tool automatically. In practice, the SMS endpoint
 * is MPP-gated — the agent must pay via one of the supported rails.
 * This tool provides pricing info and delegates to the SMS service.
 */
class SmsSendTool implements MCPToolInterface
{
    public function __construct(
        private readonly SmsService $smsService,
        private readonly SmsPricingService $pricing,
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
        return 'Send an SMS message via VertexSMS. The SMS endpoint requires payment '
            . 'via MPP (x402 USDC, Stripe card, or Lightning). Call sms.rates first '
            . 'to check pricing for the destination country.';
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
                    'pattern'     => '^\+?[0-9]{5,20}$',
                ],
                'from' => [
                    'type'        => 'string',
                    'description' => 'Sender ID or phone number (default: Zelta)',
                    'maxLength'   => 20,
                ],
                'message' => [
                    'type'        => 'string',
                    'description' => 'SMS message text (max 1600 chars)',
                    'minLength'   => 1,
                    'maxLength'   => 1600,
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
        return ['write', 'sms', 'communication', 'payment-required', 'mpp-gated'];
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

        try {
            $price = $this->pricing->getPriceForNumber($to);

            if ($checkPriceOnly) {
                return ToolExecutionResult::success([
                    'price_usdc' => $price['amount_usdc'],
                    'rate_eur'   => $price['rate_eur'],
                    'country'    => $price['country_code'],
                    'parts'      => 1,
                    'note'       => 'This is the price for a single-part SMS. '
                        . 'The actual endpoint POST /api/v1/sms/send requires MPP payment.',
                ]);
            }

            $result = $this->smsService->send($to, $from, $message);

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
}
