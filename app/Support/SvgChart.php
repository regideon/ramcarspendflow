<?php

namespace App\Support;

/**
 * Point lists for small inline SVG charts (sparklines, line charts) drawn in Blade.
 */
class SvgChart
{
    /**
     * "x,y x,y …" spreading $values across a $width × $height box, highest value at the top.
     *
     * @param  list<int|float>  $values
     */
    public static function points(array $values, float $width, float $height, ?float $min = 0, ?float $max = null, float $padding = 2): string
    {
        $min ??= min($values);
        $max ??= max($values);
        $range = ($max - $min) ?: 1;
        $step = count($values) > 1 ? $width / (count($values) - 1) : 0;

        return collect($values)
            ->map(fn (int|float $value, int $i): string => round($i * $step, 1).','.round($padding + ($height - 2 * $padding) * (1 - ($value - $min) / $range), 1))
            ->implode(' ');
    }

    /**
     * The same line closed along the bottom edge, for a filled area under it.
     *
     * @param  list<int|float>  $values
     */
    public static function area(array $values, float $width, float $height, ?float $min = 0, ?float $max = null, float $padding = 2): string
    {
        return "0,{$height} ".self::points($values, $width, $height, $min, $max, $padding)." {$width},{$height}";
    }
}
