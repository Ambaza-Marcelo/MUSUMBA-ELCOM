<?php

namespace App\Helpers;

class NumberToWordsFrench
{
    /**
     * Convert an integer to French words (supports large amounts: milliards, millions, etc.).
     */
    public static function convert($number): string
    {
        $number = (int) $number;

        if ($number < 0) {
            return 'moins ' . self::convert(-$number);
        }

        if ($number === 0) {
            return 'zéro';
        }

        $parts = [];

        if ($number >= 1000000000) {
            $chunk = intdiv($number, 1000000000);
            $parts[] = self::formatScale($chunk, 'milliard', 'milliards');
            $number %= 1000000000;
        }

        if ($number >= 1000000) {
            $chunk = intdiv($number, 1000000);
            $parts[] = self::formatScale($chunk, 'million', 'millions');
            $number %= 1000000;
        }

        if ($number >= 1000) {
            $chunk = intdiv($number, 1000);
            if ($chunk === 1) {
                $parts[] = 'mille';
            } else {
                $parts[] = self::convert($chunk) . ' mille';
            }
            $number %= 1000;
        }

        if ($number > 0) {
            $parts[] = self::convertBelowThousand($number);
        }

        return trim(implode(' ', $parts));
    }

    /**
     * Safe conversion for PDFs: falls back to formatted digits if conversion fails.
     */
    public static function convertSafe($number): string
    {
        try {
            return self::convert($number);
        } catch (\Throwable $e) {
            return number_format((int) round($number), 0, ',', ' ');
        }
    }

    protected static function formatScale(int $chunk, string $singular, string $plural): string
    {
        $words = self::convert($chunk);

        return $words . ' ' . ($chunk === 1 ? $singular : $plural);
    }

    /**
     * Convert a number from 1 to 999 into French words.
     */
    protected static function convertBelowThousand(int $number): string
    {
        $ones = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
                 'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];
        $tens = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante', 'quatre-vingt', 'quatre-vingt'];

        $result = '';

        if ($number >= 100) {
            $hundred = intdiv($number, 100);
            if ($hundred === 1) {
                $result .= 'cent';
            } else {
                $result .= $ones[$hundred] . ' cent';
            }
            $number %= 100;
            if ($number === 0 && $hundred > 1) {
                $result .= 's';
            } elseif ($number > 0) {
                $result .= ' ';
            }
        }

        if ($number >= 20) {
            $ten = intdiv($number, 10);
            $one = $number % 10;

            if ($ten === 7) {
                $result .= 'soixante';
                $result .= $one > 0 ? '-' . $ones[10 + $one] : '-dix';
            } elseif ($ten === 8) {
                $result .= 'quatre-vingt';
                if ($one > 0) {
                    $result .= '-' . $ones[$one];
                } else {
                    $result .= 's';
                }
            } elseif ($ten === 9) {
                $result .= 'quatre-vingt';
                $result .= $one > 0 ? '-' . $ones[10 + $one] : '-dix';
            } else {
                $result .= $tens[$ten];
                if ($one > 0) {
                    if ($ten === 2 && $one === 1) {
                        $result .= '-et-un';
                    } elseif ($ten === 8 && $one === 1) {
                        $result .= '-et-un';
                    } else {
                        $result .= '-' . $ones[$one];
                    }
                }
            }
        } elseif ($number > 0) {
            $result .= $ones[$number];
        }

        return trim($result);
    }
}
