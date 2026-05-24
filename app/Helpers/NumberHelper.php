<?php

namespace App\Helpers;

/**
 * Utility helpers for number formatting.
 */
class NumberHelper
{
    private static array $ones = [
        '', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
        'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf',
    ];

    private static array $tens = [
        '', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante', 'quatre-vingt', 'quatre-vingt',
    ];

    /**
     * Convert an integer to French words (suitable for FCFA amounts).
     */
    public static function toWords(int $n, string $currency = 'Francs CFA'): string
    {
        if ($n < 0) {
            return 'moins ' . self::toWords(-$n, $currency);
        }
        if ($n === 0) {
            return 'Zéro ' . $currency;
        }

        return ucfirst(self::convertGroup($n)) . ' ' . $currency;
    }

    private static function convertGroup(int $n): string
    {
        if ($n === 0) return '';

        if ($n < 20) {
            return self::$ones[$n];
        }

        if ($n < 100) {
            $ten  = intdiv($n, 10);
            $unit = $n % 10;

            // 70-79 : soixante-dix…
            if ($ten === 7) {
                return 'soixante-' . self::$ones[10 + $unit];
            }
            // 90-99 : quatre-vingt-dix…
            if ($ten === 9) {
                return 'quatre-vingt-' . self::$ones[10 + $unit];
            }
            // 80 exactly → quatre-vingts (with s)
            if ($ten === 8 && $unit === 0) {
                return 'quatre-vingts';
            }

            $result = self::$tens[$ten];
            if ($unit === 1 && $ten !== 8) {
                $result .= '-et-un';
            } elseif ($unit > 0) {
                $result .= '-' . self::$ones[$unit];
            }
            return $result;
        }

        if ($n < 1000) {
            $h    = intdiv($n, 100);
            $rest = $n % 100;
            if ($h === 1) {
                return 'cent' . ($rest > 0 ? ' ' . self::convertGroup($rest) : 's');
            }
            return self::$ones[$h] . ' cent' . ($rest > 0 ? ' ' . self::convertGroup($rest) : 's');
        }

        if ($n < 1_000_000) {
            $t    = intdiv($n, 1000);
            $rest = $n % 1000;
            $prefix = $t === 1 ? 'mille' : self::convertGroup($t) . ' mille';
            return $prefix . ($rest > 0 ? ' ' . self::convertGroup($rest) : '');
        }

        if ($n < 1_000_000_000) {
            $m    = intdiv($n, 1_000_000);
            $rest = $n % 1_000_000;
            $prefix = self::convertGroup($m) . ' million' . ($m > 1 ? 's' : '');
            return $prefix . ($rest > 0 ? ' ' . self::convertGroup($rest) : '');
        }

        $b    = intdiv($n, 1_000_000_000);
        $rest = $n % 1_000_000_000;
        $prefix = self::convertGroup($b) . ' milliard' . ($b > 1 ? 's' : '');
        return $prefix . ($rest > 0 ? ' ' . self::convertGroup($rest) : '');
    }
}
