<?php

declare(strict_types=1);

namespace App\Domain\AI\Activities;

use Workflow\Activity;

class ToolSelectionActivity extends Activity
{
    public function select(string $intentType, array $entities = []): array
    {
        // Map intent types to appropriate tools
        $toolMap = [
            'balance_inquiry'  => ['CheckBalanceTool', 'AccountBalanceTool'],
            'money_transfer'   => ['TransferTool', 'ValidateAccountTool'],
            'payment_request'  => ['PaymentTool', 'PaymentStatusTool'],
            'loan_application' => ['CreditCheckTool', 'LoanEligibilityTool'],
            'trading_request'  => ['QuoteTool', 'TradeTool', 'LiquidityPoolTool'],
            'general_inquiry'  => ['SearchTool', 'FAQTool'],
        ];

        return $toolMap[$intentType] ?? ['GeneralAssistanceTool'];
    }
}
