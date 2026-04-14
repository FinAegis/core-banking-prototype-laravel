<?php

declare(strict_types=1);

namespace App\Domain\Governance\Enums;

enum PollType: string
{
    case SINGLE_CHOICE = 'single_choice';
    case MULTIPLE_CHOICE = 'multiple_choice';
    case WEIGHTED_CHOICE = 'weighted_choice';
    case YES_NO = 'yes_no';
    case RANKED_CHOICE = 'ranked_choice';

    public function getLabel(): string
    {
        return match ($this) {
            self::SINGLE_CHOICE   => 'Single Choice',
            self::MULTIPLE_CHOICE => 'Multiple Choice',
            self::WEIGHTED_CHOICE => 'Weighted Choice',
            self::YES_NO          => 'Yes/No',
            self::RANKED_CHOICE   => 'Ranked Choice',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::SINGLE_CHOICE   => 'Voters can select only one option',
            self::MULTIPLE_CHOICE => 'Voters can select multiple options',
            self::WEIGHTED_CHOICE => 'Voters can allocate weight/percentage to options',
            self::YES_NO          => 'Simple yes or no question',
            self::RANKED_CHOICE   => 'Voters rank options in order of preference',
        };
    }

    public function allowsMultipleSelections(): bool
    {
        return match ($this) {
            self::MULTIPLE_CHOICE, self::WEIGHTED_CHOICE, self::RANKED_CHOICE => true,
            self::SINGLE_CHOICE, self::YES_NO                                 => false,
        };
    }
}
