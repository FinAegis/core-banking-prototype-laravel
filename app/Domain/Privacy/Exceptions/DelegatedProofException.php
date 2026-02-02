<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Exceptions;

use Exception;

/**
 * Exception for delegated proof generation errors.
 */
class DelegatedProofException extends Exception
{
    public const CODE_JOB_NOT_FOUND = 'ERR_PRIVACY_310';

    public const CODE_UNSUPPORTED_PROOF_TYPE = 'ERR_PRIVACY_311';

    public const CODE_UNSUPPORTED_NETWORK = 'ERR_PRIVACY_312';

    public const CODE_MISSING_INPUTS = 'ERR_PRIVACY_313';

    public const CODE_QUEUE_FULL = 'ERR_PRIVACY_314';

    public const CODE_CANNOT_CANCEL = 'ERR_PRIVACY_315';

    public const CODE_GENERATION_FAILED = 'ERR_PRIVACY_316';

    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $httpStatusCode = 400,
    ) {
        parent::__construct($message);
    }

    public static function jobNotFound(string $jobId): self
    {
        return new self(
            "Delegated proof job not found: {$jobId}",
            self::CODE_JOB_NOT_FOUND,
            404
        );
    }

    /**
     * @param array<string> $supported
     */
    public static function unsupportedProofType(string $proofType, array $supported): self
    {
        return new self(
            "Unsupported proof type: {$proofType}. Supported: " . implode(', ', $supported),
            self::CODE_UNSUPPORTED_PROOF_TYPE,
            400
        );
    }

    /**
     * @param array<string> $supported
     */
    public static function unsupportedNetwork(string $network, array $supported): self
    {
        return new self(
            "Unsupported network for delegated proving: {$network}. Supported: " . implode(', ', $supported),
            self::CODE_UNSUPPORTED_NETWORK,
            400
        );
    }

    /**
     * @param array<int|string, string> $missingInputs
     */
    public static function missingInputs(array $missingInputs): self
    {
        return new self(
            'Missing required public inputs: ' . implode(', ', $missingInputs),
            self::CODE_MISSING_INPUTS,
            400
        );
    }

    public static function queueFull(int $maxSize): self
    {
        return new self(
            "Too many pending proof jobs. Maximum: {$maxSize}",
            self::CODE_QUEUE_FULL,
            429
        );
    }

    public static function cannotCancel(string $jobId, string $status): self
    {
        return new self(
            "Cannot cancel job {$jobId} with status: {$status}",
            self::CODE_CANNOT_CANCEL,
            400
        );
    }

    public static function generationFailed(string $reason): self
    {
        return new self(
            "Proof generation failed: {$reason}",
            self::CODE_GENERATION_FAILED,
            500
        );
    }
}
