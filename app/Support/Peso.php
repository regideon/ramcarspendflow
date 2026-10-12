<?php

namespace App\Support;

/**
 * Philippine peso display: "₱1,250,000.00", or compact "₱1.25M" / "₱642.3K" for tiles.
 */
class Peso
{
    public static function format(float $amount, bool $compact = false): string
    {
        if ($compact && abs($amount) >= 1_000_000) {
            return '₱'.number_format($amount / 1_000_000, 2).'M';
        }

        if ($compact && abs($amount) >= 1_000) {
            return '₱'.number_format($amount / 1_000, 1).'K';
        }

        return '₱'.number_format($amount, 2);
    }
}
