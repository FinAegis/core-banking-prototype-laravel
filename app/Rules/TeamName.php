<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Team names are shown to other people, including in the invitation email
 * (a Markdown template). Allow letters, numbers, spaces and basic
 * punctuation only: no brackets, parentheses, backslash or other Markdown
 * or HTML syntax, and no line breaks.
 */
final class TeamName implements ValidationRule
{
    public const PATTERN = "/^[\\pL\\pM\\pN .,'’&!?-]+$/uD";

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            $fail("The :attribute may only contain letters, numbers, spaces and these punctuation marks: . , ' & - ! ?");
        }
    }
}
