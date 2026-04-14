<?php

declare(strict_types=1);

namespace App\Domain\PaymentRails\Services;

use App\Domain\ISO20022\ValueObjects\Pacs002;
use App\Domain\ISO20022\ValueObjects\Pacs008;
use App\Domain\PaymentRails\Enums\PaymentRail;
use App\Domain\PaymentRails\Enums\RailStatus;
use App\Domain\PaymentRails\Models\PaymentRailTransaction;
use DateTimeImmutable;
use Exception;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use SimpleXMLElement;

final class FedNowService
{
    /** @var class-string<Pacs008> */
    private string $pacs008Class;

    /** @var class-string<Pacs002> */
    private string $pacs002Class;

    public function __construct(
        Pacs008 $pacs008Template,
        Pacs002 $pacs002Template,
    ) {
        $this->pacs008Class = $pacs008Template::class;
        $this->pacs002Class = $pacs002Template::class;
    }

    /**
     * Send an instant payment via FedNow using ISO 20022 Pacs.008.
     *
     * @throws InvalidArgumentException when amount exceeds the configured maximum
     * @return array{transaction_id: string, external_id: string, status: string, rail: string, amount: string, currency: string, iso20022_message_id: string}
     */
    public function sendInstantPayment(
        int $userId,
        string $amount,
        string $currency,
        string $creditorName,
        string $creditorIban,
        string $creditorBic,
        ?string $reference = null,
    ): array {
        $maxAmount = (int) config('payment_rails.fednow.max_amount', 50000000);

        if ((int) round((float) $amount * 100) > $maxAmount) {
            throw new InvalidArgumentException(
                "FedNow amount {$amount} exceeds maximum allowed {$maxAmount} cents."
            );
        }

        $messageId = 'FN-' . strtoupper(Str::random(16));
        $endToEndId = 'E2E-' . strtoupper(Str::random(12));
        $uetr = Str::uuid()->toString();
        $externalId = $messageId;

        $participantId = (string) config('payment_rails.fednow.participant_id', 'FNAEGISUS');

        $pacs008Class = $this->pacs008Class;
        $pacs008 = new $pacs008Class(
            messageId: $messageId,
            creationDateTime: new DateTimeImmutable(),
            numberOfTransactions: 1,
            settlementMethod: 'CLRG',
            instructingAgentBic: $participantId,
            instructedAgentBic: $creditorBic,
            endToEndId: $endToEndId,
            uetr: $uetr,
            amount: $amount,
            currency: $currency,
            debtorName: 'FinAegis',
            debtorIban: (string) config('payment_rails.fednow.participant_id', ''),
            creditorName: $creditorName,
            creditorIban: $creditorIban,
        );

        $xmlMessage = $pacs008->toXml();

        $transaction = PaymentRailTransaction::create([
            'user_id'     => $userId,
            'rail'        => PaymentRail::FEDNOW,
            'external_id' => $externalId,
            'amount'      => $amount,
            'currency'    => $currency,
            'status'      => RailStatus::PROCESSING,
            'direction'   => 'debit',
            'metadata'    => [
                'creditor_name'  => $creditorName,
                'creditor_iban'  => $creditorIban,
                'creditor_bic'   => $creditorBic,
                'reference'      => $reference,
                'message_id'     => $messageId,
                'end_to_end_id'  => $endToEndId,
                'uetr'           => $uetr,
                'iso20022_xml'   => $xmlMessage,
                'participant_id' => $participantId,
            ],
        ]);

        return [
            'transaction_id'      => $transaction->id,
            'external_id'         => $externalId,
            'status'              => RailStatus::PROCESSING->value,
            'rail'                => PaymentRail::FEDNOW->value,
            'amount'              => $amount,
            'currency'            => $currency,
            'iso20022_message_id' => $messageId,
        ];
    }

    /**
     * Parse an incoming ISO 20022 Pacs.002 status report and update the corresponding transaction.
     *
     * @return array{original_message_id: string, group_status: string, transactions_updated: int}
     */
    public function processStatusReport(string $xml): array
    {
        try {
            $element = new SimpleXMLElement($xml, LIBXML_NONET);
        } catch (Exception $e) {
            throw new RuntimeException('Invalid Pacs.002 XML: ' . $e->getMessage(), 0, $e);
        }

        $element->registerXPathNamespace('pacs', 'urn:iso:std:iso:20022:tech:xsd:pacs.002.001.10');

        // Extract group header fields
        $msgIdNodes = $element->xpath('//pacs:GrpHdr/pacs:MsgId') ?: $element->xpath('//*[local-name()="GrpHdr"]/*[local-name()="MsgId"]') ?: [];
        $creDtTmNodes = $element->xpath('//*[local-name()="GrpHdr"]/*[local-name()="CreDtTm"]') ?: [];
        $origMsgIdNodes = $element->xpath('//*[local-name()="OrgnlGrpInfAndSts"]/*[local-name()="OrgnlMsgId"]') ?: [];
        $origMsgNmNodes = $element->xpath('//*[local-name()="OrgnlGrpInfAndSts"]/*[local-name()="OrgnlMsgNmId"]') ?: [];
        $grpStsNodes = $element->xpath('//*[local-name()="OrgnlGrpInfAndSts"]/*[local-name()="GrpSts"]') ?: [];

        $messageId = isset($msgIdNodes[0]) ? (string) $msgIdNodes[0] : ('UNKNOWN-' . Str::random(8));
        $creationDtTm = isset($creDtTmNodes[0]) ? (string) $creDtTmNodes[0] : 'now';
        $origMessageId = isset($origMsgIdNodes[0]) ? (string) $origMsgIdNodes[0] : '';
        $origMsgType = isset($origMsgNmNodes[0]) ? (string) $origMsgNmNodes[0] : 'pacs.008.001.08';
        $groupStatus = isset($grpStsNodes[0]) ? (string) $grpStsNodes[0] : 'RJCT';

        // Build transaction statuses from TxInfAndSts elements
        $txNodes = $element->xpath('//*[local-name()="TxInfAndSts"]') ?: [];
        $txStatuses = [];
        $updatedCount = 0;

        foreach ($txNodes as $txNode) {
            $e2eNodes = $txNode->xpath('*[local-name()="OrgnlEndToEndId"]') ?: [];
            $stsNodes = $txNode->xpath('*[local-name()="TxSts"]') ?: [];
            $reasonNodes = $txNode->xpath('*[local-name()="StsRsnInf"]/*[local-name()="Rsn"]/*[local-name()="Cd"]') ?: [];
            $infoNodes = $txNode->xpath('*[local-name()="StsRsnInf"]/*[local-name()="AddtlInf"]') ?: [];

            $e2eId = isset($e2eNodes[0]) ? (string) $e2eNodes[0] : '';
            $txStatus = isset($stsNodes[0]) ? (string) $stsNodes[0] : $groupStatus;
            $reasonCode = isset($reasonNodes[0]) ? (string) $reasonNodes[0] : null;
            $reasonInfo = isset($infoNodes[0]) ? (string) $infoNodes[0] : null;

            $entry = [
                'original_end_to_end_id' => $e2eId,
                'status'                 => $txStatus,
            ];

            if ($reasonCode !== null) {
                $entry['reason_code'] = $reasonCode;
            }

            if ($reasonInfo !== null) {
                $entry['reason_info'] = $reasonInfo;
            }

            $txStatuses[] = $entry;
        }

        // Hydrate a Pacs002 DTO from the parsed XML data for validation / downstream use
        $pacs002Class = $this->pacs002Class;
        $pacs002Class::fromArray([
            'message_id'            => $messageId,
            'creation_date_time'    => $creationDtTm,
            'original_message_id'   => $origMessageId,
            'original_message_type' => $origMsgType,
            'group_status'          => $groupStatus,
            'transaction_statuses'  => $txStatuses,
        ]);

        foreach ($txStatuses as $txStatus) {
            $railStatus = $this->mapIsoStatus($txStatus['status']);

            if ($origMessageId === '') {
                continue;
            }

            $transaction = PaymentRailTransaction::where('rail', PaymentRail::FEDNOW->value)
                ->whereJsonContains('metadata->message_id', $origMessageId)
                ->first();

            if ($transaction !== null && ! $transaction->status->isTerminal()) {
                $updates = ['status' => $railStatus];
                $info = $txStatus['reason_info'] ?? null;

                if ($info !== null) {
                    $updates['error_message'] = $info;
                }

                if ($railStatus->isTerminal()) {
                    $updates['completed_at'] = now();
                }

                $transaction->update($updates);
                $updatedCount++;
            }
        }

        return [
            'original_message_id'  => $origMessageId,
            'group_status'         => $groupStatus,
            'transactions_updated' => $updatedCount,
        ];
    }

    /**
     * Retrieve the status of a FedNow transaction by internal transaction ID.
     *
     * @return array{transaction_id: string, external_id: string|null, status: string, rail: string, amount: string, currency: string}|null
     */
    public function getPaymentStatus(string $transactionId): ?array
    {
        $transaction = PaymentRailTransaction::where('id', $transactionId)
            ->where('rail', PaymentRail::FEDNOW->value)
            ->first();

        if ($transaction === null) {
            return null;
        }

        return [
            'transaction_id' => $transaction->id,
            'external_id'    => $transaction->external_id,
            'status'         => $transaction->status->value,
            'rail'           => $transaction->rail->value,
            'amount'         => $transaction->amount,
            'currency'       => $transaction->currency,
        ];
    }

    /**
     * Map an ISO 20022 Pacs.002 transaction status code to a RailStatus.
     */
    private function mapIsoStatus(string $isoStatus): RailStatus
    {
        return match (strtoupper($isoStatus)) {
            'ACSC', 'ACCC', 'ACSP', 'ACWC', 'ACWP' => RailStatus::COMPLETED,
            'PDNG'                                 => RailStatus::PENDING,
            'RJCT'                                 => RailStatus::FAILED,
            'CANC'                                 => RailStatus::CANCELLED,
            default                                => RailStatus::PROCESSING,
        };
    }
}
