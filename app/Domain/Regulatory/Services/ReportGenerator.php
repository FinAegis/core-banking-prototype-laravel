<?php

declare(strict_types=1);

namespace App\Domain\Regulatory\Services;

use RuntimeException;

class ReportGenerator
{
    public function generateBSAReport(array $data): string
    {
        $result = json_encode($data);
        if ($result === false) {
            throw new RuntimeException('Failed to encode BSA report data: ' . json_last_error_msg());
        }

        return $result;
    }

    public function generateSARReport(array $data): string
    {
        $result = json_encode($data);
        if ($result === false) {
            throw new RuntimeException('Failed to encode SAR report data: ' . json_last_error_msg());
        }

        return $result;
    }

    public function generateCTRReport(array $data): string
    {
        $result = json_encode($data);
        if ($result === false) {
            throw new RuntimeException('Failed to encode CTR report data: ' . json_last_error_msg());
        }

        return $result;
    }

    public function generateOFACReport(array $data): string
    {
        $result = json_encode($data);
        if ($result === false) {
            throw new RuntimeException('Failed to encode OFAC report data: ' . json_last_error_msg());
        }

        return $result;
    }
}
