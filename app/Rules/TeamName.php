<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Team names are shown to other people, including in the invitation email
 * (a Markdown template). Allow letters, numbers, spaces and basic
 * punctuation only: no brackets, parentheses, backslash or other Markdown
 * or HTML syntax, and no line breaks. A name may not contain a web address
 * either, such as "example.com", because mail clients turn those into
 * links.
 */
final class TeamName implements ValidationRule
{
    public const PATTERN = "/^[\\pL\\pM\\pN .,'’&!?-]+$/uD";

    /**
     * A dot between a letter, digit or hyphen and two or more letters, as in
     * a domain name. "Acme Ltd." and "J.P. Morgan" do not match.
     */
    public const LINK_PATTERN = '/[\pL\pN-]\.\pL{2,}/u';

    /** Used when a user's name does not make a valid personal team name. */
    public const PERSONAL_TEAM_FALLBACK = 'Personal Team';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            $fail("The :attribute may only contain letters, numbers, spaces and these punctuation marks: . , ' & - ! ?");

            return;
        }

        if (preg_match(self::LINK_PATTERN, $value) === 1) {
            $fail('The :attribute may not contain a web address.');
        }
    }

    /**
     * Whether a stored team name meets this rule. Names saved before the
     * rule existed may not.
     */
    public static function isValid(string $name): bool
    {
        return mb_strlen($name) <= 255
            && preg_match(self::PATTERN, $name) === 1
            && preg_match(self::LINK_PATTERN, $name) !== 1;
    }

    /**
     * The personal team name for a user: "<first name>'s Team" when that
     * meets this rule, otherwise a neutral name.
     */
    public static function personalTeamName(string $userName): string
    {
        $name = explode(' ', $userName, 2)[0] . "'s Team";

        return self::isValid($name) ? $name : self::PERSONAL_TEAM_FALLBACK;
    }
}
