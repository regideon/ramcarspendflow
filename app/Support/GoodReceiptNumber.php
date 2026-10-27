<?php

namespace App\Support;

/**
 * Local `-P{n}` split suffixes. SAP only knows the base goods-receipt number, so
 * outbound payloads and invoice matching always use {@see self::base()}.
 */
final class GoodReceiptNumber
{
    public static function base(?string $number): string
    {
        $number = (string) $number;

        return preg_replace('/-P\d+$/i', '', $number) ?: $number;
    }

    public static function isSplit(?string $number): bool
    {
        $number = (string) $number;

        return $number !== '' && self::base($number) !== $number;
    }

    public static function suffix(?string $number): ?int
    {
        if (! self::isSplit($number)) {
            return null;
        }

        return (int) preg_replace('/^.*-P(\d+)$/i', '$1', (string) $number);
    }
}
