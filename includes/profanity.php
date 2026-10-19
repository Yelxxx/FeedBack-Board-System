<?php
declare(strict_types=1);

// ============================================================================
// Profanity & Inappropriate Language Filter
// Supports English and Tagalog bad words, slurs, and obscenities.
// ============================================================================

class ProfanityFilter
{
    private static array $dictionary = [
        // English profanities & insults
        'fuck', 'fucking', 'fucked', 'fucker', 'fuckers', 'fuk',
        'shit', 'shitty', 'bullshit',
        'bitch', 'bitches', 'bitching',
        'asshole', 'assholes', 'bastard', 'bastards', 'cunt', 'dick', 'cock',
        'motherfucker', 'piss', 'slut', 'whore',

        // Filipino / Tagalog bad words, insults & curses
        'tangina', 'tang ina', 'ina mo', 'tanginamo', 'taena', 'tae',
        'putangina', 'putang ina', 'puta', 'putragis',
        'gago', 'gaga', 'pakyu', 'kupal', 'tarantado', 'tarantada',
        'bobo', 'tanga', 'ulol', 'leche', 'letse', 'hudas',
        'punyeta', 'kantot', 'iyot', 'puke', 'puki', 'titi', 'burat', 'bayag',
        'pokpok', 'malandi', 'siraulo', 'bwisit', 'buwisit',
        'pucha', 'animal ka', 'hayop ka', 'ogag'
    ];

    /**
     * Check if a given string contains profanity.
     * Returns true if profanity is detected, false otherwise.
     */
    public static function containsBadWord(string $text): bool
    {
        if (trim($text) === '') {
            return false;
        }

        $cleaned = self::normalizeText($text);

        foreach (self::$dictionary as $badWord) {
            $badPattern = preg_quote($badWord, '/');
            // If the bad word has spaces, match with flexible whitespace
            $badPattern = str_replace('\ ', '\s+', $badPattern);

            // Use word boundary \b so words like "attendance" or "faculty" are never flagged
            if (preg_match('/\b' . $badPattern . '\b/iu', $cleaned)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Finds which bad words were matched in the text.
     */
    public static function findBadWords(string $text): array
    {
        $found = [];
        $cleaned = self::normalizeText($text);

        foreach (self::$dictionary as $badWord) {
            $badPattern = preg_quote($badWord, '/');
            $badPattern = str_replace('\ ', '\s+', $badPattern);

            if (preg_match('/\b' . $badPattern . '\b/iu', $cleaned)) {
                $found[] = $badWord;
            }
        }

        return array_unique($found);
    }

    /**
     * Masks bad words with asterisks (e.g. gago -> g***).
     */
    public static function maskBadWords(string $text): string
    {
        $masked = $text;

        foreach (self::$dictionary as $badWord) {
            $badPattern = preg_quote($badWord, '/');
            $badPattern = str_replace('\ ', '\s+', $badPattern);

            $masked = preg_replace_callback('/(?:\b|^)(' . $badPattern . ')(?:\b|$)/iu', function ($matches) {
                $word = $matches[1];
                $len = mb_strlen($word);
                if ($len <= 2) {
                    return str_repeat('*', $len);
                }
                return mb_substr($word, 0, 1) . str_repeat('*', $len - 1);
            }, $masked);
        }

        return $masked;
    }

    /**
     * Normalizes text by replacing common leetspeak substitutions.
     */
    private static function normalizeText(string $text): string
    {
        $lower = mb_strtolower($text, 'UTF-8');
        // Replace common leetspeak numbers/symbols: @ -> a, 0 -> o, 1 -> i, 3 -> e, 5 -> s, $ -> s, etc.
        $leets = [
            '@' => 'a',
            '4' => 'a',
            '0' => 'o',
            '1' => 'i',
            '!' => 'i',
            '3' => 'e',
            '5' => 's',
            '$' => 's',
            '7' => 't',
        ];
        return strtr($lower, $leets);
    }
}
