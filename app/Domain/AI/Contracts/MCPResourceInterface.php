<?php

declare(strict_types=1);

namespace App\Domain\AI\Contracts;

interface MCPResourceInterface
{
    public function getUri(): string;

    public function getName(): string;

    public function getDescription(): string;

    public function getMimeType(): string;

    public function read(array $params = []): array;
}
