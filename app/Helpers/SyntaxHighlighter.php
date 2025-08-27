<?php

namespace App\Helpers;

use Exception;
use Highlight\Highlighter;

class SyntaxHighlighter
{
    protected static $highlighter;

    public static function highlight($code, $language = 'auto')
    {
        if (! self::$highlighter) {
            self::$highlighter = new Highlighter();
        }

        try {
            if ($language === 'auto') {
                $highlighted = self::$highlighter->highlightAuto($code);
            } else {
                $highlighted = self::$highlighter->highlight($language, $code);
            }

            return $highlighted->value;
        } catch (Exception $e) {
            // Fallback to plain text if highlighting fails
            return htmlspecialchars($code);
        }
    }

    public static function getLanguageClass($language)
    {
        $map = [
            'javascript' => 'language-javascript',
            'js'         => 'language-javascript',
            'python'     => 'language-python',
            'php'        => 'language-php',
            'bash'       => 'language-bash',
            'shell'      => 'language-bash',
            'json'       => 'language-json',
            'html'       => 'language-html',
            'css'        => 'language-css',
            'sql'        => 'language-sql',
            'yaml'       => 'language-yaml',
            'yml'        => 'language-yaml',
        ];

        return $map[strtolower($language)] ?? 'language-plaintext';
    }
}
