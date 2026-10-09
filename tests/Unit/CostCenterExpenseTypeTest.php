<?php

use App\Models\CostCenter;

it('reads OPEX or MFG from the third digit of the code', function (string $code, ?bool $isManufacturing) {
    expect((new CostCenter(['code' => $code]))->isManufacturing())->toBe($isManufacturing);
})->with([
    'OPEX (1)' => ['101100', false],
    'OPEX (2)' => ['102100', false],
    'MFG (3)' => ['103100', true],
    'MFG (5)' => ['105100', true],
    'no type (0)' => ['110000', null],
]);

it('matches G/L 5… to MFG and G/L 6… to OPEX', function (string $code, string $glCode, bool $allowed) {
    expect((new CostCenter(['code' => $code]))->allowsGlCode($glCode))->toBe($allowed);
})->with([
    'OPEX + 6' => ['101100', '6021010', true],
    'OPEX + 5' => ['101100', '5021010', false],
    'MFG + 5' => ['103100', '5021010', true],
    'MFG + 6' => ['103100', '6021010', false],
    'balance sheet G/L' => ['103100', '1151010', true],
    'no type' => ['110000', '5021010', true],
]);
