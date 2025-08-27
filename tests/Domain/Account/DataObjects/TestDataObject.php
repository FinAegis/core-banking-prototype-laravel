<?php

declare(strict_types=1);

namespace Tests\Domain\Account\DataObjects;

use App\Domain\Account\DataObjects\DataObject;

/**
 * Concrete implementation of DataObject for testing purposes.
 */
readonly class TestDataObject extends DataObject
{
    public function __construct(
        public string $name,
        public int $value
    ) {
    }

    public function toArray(): array
    {
        return [
            'name'  => $this->name,
            'value' => $this->value,
        ];
    }
}
