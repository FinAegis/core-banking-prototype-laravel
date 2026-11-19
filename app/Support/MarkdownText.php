<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Free text placed inside a Markdown mail template.
 */
final class MarkdownText
{
    /**
     * Make free text render literally when echoed with {{ }} inside a
     * Markdown mail template. Line breaks, tabs and control characters
     * become single spaces, so the text cannot start a new block. Every
     * ASCII punctuation character is escaped with a backslash in a single
     * pass, the backslash itself included, so CommonMark never reads it as
     * a link, image, emphasis, code span or heading. The characters & < > "
     * and ' are left to the Blade echo, which turns them into HTML entities
     * that CommonMark never reads as syntax.
     */
    public static function literal(string $text): string
    {
        $text = (string) preg_replace('/[\s\p{Cc}\p{Cf}]+/u', ' ', mb_scrub($text, 'UTF-8'));

        return (string) preg_replace('/[\x21\x23-\x25\x28-\x2F\x3A\x3B\x3D\x3F\x40\x5B-\x60\x7B-\x7E]/', '\\\\$0', trim($text));
    }
}
