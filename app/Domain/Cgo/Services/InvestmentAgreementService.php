<?php

namespace App\Domain\Cgo\Services;

use App\Domain\Cgo\Models\CgoInvestment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InvestmentAgreementService
{
    /**
     * Generate investment agreement PDF.
     */
    public function generateAgreement(CgoInvestment $investment): string
    {
        try {
            // Load investment with relationships
            $investment->load(['user', 'round']);

            // Generate agreement data
            $data = $this->prepareAgreementData($investment);

            // Generate PDF
            $pdf = Pdf::loadView('cgo.agreements.investment-agreement', $data);

            // Configure PDF settings
            $pdf->setPaper('A4', 'portrait');
            $pdf->setOption('isRemoteEnabled', true);
            $pdf->setOption('isHtml5ParserEnabled', true);

            // Generate filename
            $filename = $this->generateFilename($investment);

            // Save to storage
            $path = 'cgo/agreements/' . $filename;
            Storage::put($path, $pdf->output());

            // Update investment record
            $investment->update(
                [
                'agreement_path'         => $path,
                'agreement_generated_at' => now(),
                ]
            );

            Log::info(
                'CGO investment agreement generated',
                [
                'investment_id' => $investment->id,
                'path'          => $path,
                ]
            );

            return $path;
        } catch (\Exception $e) {
            Log::error(
                'Failed to generate CGO investment agreement',
                [
                'investment_id' => $investment->id,
                'error'         => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }

    /**
     * Generate certificate of investment.
     */
    public function generateCertificate(CgoInvestment $investment): string
    {
        try {
            // Ensure investment is confirmed
            if ($investment->status !== 'confirmed') {
                throw new \Exception('Investment must be confirmed to generate certificate');
            }

            // Load relationships
            $investment->load(['user', 'round']);

            // Generate certificate number if not exists
            if (! $investment->certificate_number) {
                $investment->update(
                    [
                    'certificate_number'    => $investment->generateCertificateNumber(),
                    'certificate_issued_at' => now(),
                    ]
                );
            }

            // Prepare certificate data
            $data = $this->prepareCertificateData($investment);

            // Generate PDF
            $pdf = Pdf::loadView('cgo.agreements.investment-certificate', $data);

            // Configure PDF settings
            $pdf->setPaper('A4', 'landscape');
            $pdf->setOption('isRemoteEnabled', true);
            $pdf->setOption('isHtml5ParserEnabled', true);

            // Generate filename
            $filename = 'certificate_' . $investment->certificate_number . '.pdf';

            // Save to storage
            $path = 'cgo/certificates/' . $filename;
            Storage::put($path, $pdf->output());

            // Update investment record
            $investment->update(
                [
                'certificate_path' => $path,
                ]
            );

            Log::info(
                'CGO investment certificate generated',
                [
                'investment_id'      => $investment->id,
                'certificate_number' => $investment->certificate_number,
                'path'               => $path,
                ]
            );

            return $path;
        } catch (\Exception $e) {
            Log::error(
                'Failed to generate CGO investment certificate',
                [
                'investment_id' => $investment->id,
                'error'         => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }

    /**
     * Prepare agreement data.
     */
    protected function prepareAgreementData(CgoInvestment $investment): array
    {
        $round = $investment->round;
        $user = $investment->user;

        return [
            'investment' => $investment,
            'investor'   => [
                'name'    => $user->name,
                'email'   => $user->email,
                'address' => $user->profile?->address ?? 'Not provided',
                'country' => $user->country_code ?? 'Not provided',
            ],
            'company' => [
                'name'         => config('app.company_name', 'FinAegis Ltd'),
                'registration' => config('app.company_registration', '12345678'),
                'address'      => config('app.company_address', '123 Business St, London, UK'),
                'email'        => config('app.company_email', 'info@finaegis.org'),
            ],
            'investment_details' => [
                'amount'               => $investment->amount,
                'currency'             => $investment->currency,
                'shares'               => $investment->shares_purchased,
                'share_price'          => $investment->share_price,
                'ownership_percentage' => $investment->ownership_percentage,
                'tier'                 => ucfirst($investment->tier),
                'round_name'           => $round->name,
                'valuation'            => $round->pre_money_valuation,
            ],
            'terms'            => $this->getInvestmentTerms($investment),
            'risks'            => $this->getInvestmentRisks(),
            'agreement_date'   => now()->format('F d, Y'),
            'agreement_number' => 'CGO-AGR-' . $investment->uuid,
        ];
    }

    /**
     * Prepare certificate data.
     */
    protected function prepareCertificateData(CgoInvestment $investment): array
    {
        return [
            'certificate_number'   => $investment->certificate_number,
            'investor_name'        => $investment->user->name,
            'investment_amount'    => $investment->amount,
            'currency'             => $investment->currency,
            'shares_purchased'     => $investment->shares_purchased,
            'share_price'          => $investment->share_price,
            'ownership_percentage' => $investment->ownership_percentage,
            'tier'                 => ucfirst($investment->tier),
            'investment_date'      => $investment->payment_completed_at->format('F d, Y'),
            'issue_date'           => now()->format('F d, Y'),
            'company_name'         => config('app.company_name', 'FinAegis Ltd'),
            'signatures'           => [
                'ceo' => config('app.ceo_name', 'John Doe'),
                'cfo' => config('app.cfo_name', 'Jane Smith'),
            ],
        ];
    }

    /**
     * Get investment terms based on tier.
     */
    protected function getInvestmentTerms(CgoInvestment $investment): array
    {
        $baseTerms = [
            'lock_in_period'        => '12 months',
            'dividend_rights'       => 'Pro-rata based on ownership percentage',
            'voting_rights'         => 'One vote per share',
            'transfer_restrictions' => 'Subject to company approval and right of first refusal',
            'dilution_protection'   => 'None',
            'information_rights'    => 'Annual financial statements',
        ];

        // Add tier-specific terms
        switch ($investment->tier) {
            case 'gold':
                $baseTerms['dilution_protection'] = 'Anti-dilution protection for first 24 months';
                $baseTerms['information_rights'] = 'Quarterly financial statements and board updates';
                $baseTerms['board_observer'] = 'Board observer rights for investments above $100,000';
                break;

            case 'silver':
                $baseTerms['information_rights'] = 'Semi-annual financial statements';
                break;
        }

        return $baseTerms;
    }

    /**
     * Get standard investment risks.
     */
    protected function getInvestmentRisks(): array
    {
        return [
            'Total loss of investment capital is possible',
            'No guarantee of dividends or returns',
            'Shares may be illiquid and difficult to sell',
            'Company valuation may decrease',
            'Future funding rounds may dilute ownership',
            'Regulatory changes may affect operations',
            'Market conditions may impact business performance',
            'Technology risks and cybersecurity threats',
        ];
    }

    /**
     * Generate unique filename.
     */
    protected function generateFilename(CgoInvestment $investment): string
    {
        $timestamp = now()->format('Ymd_His');
        $uuid = Str::substr($investment->uuid, 0, 8);

        return "agreement_{$investment->tier}_{$uuid}_{$timestamp}.pdf";
    }

    /**
     * Send agreement to investor.
     */
    public function sendAgreementToInvestor(CgoInvestment $investment): void
    {
        // This would integrate with email service
        // Implementation depends on email system
        Log::info(
            'Agreement email would be sent',
            [
            'investment_id' => $investment->id,
            'email'         => $investment->user->email,
            ]
        );
    }
}
