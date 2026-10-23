<?php

namespace App\Services\Rfp;

use App\Models\TaxCode;
use App\Models\WithholdingTaxCode;

/**
 * Step 4 tax math for one RFP line (legacy "Process Tax Codes"). The gross amount is
 * VAT-inclusive and in the line's own currency:
 * - net of VAT = gross ÷ (1 + input tax rate)
 * - VAT        = gross − net of VAT
 * - withholding tax = net of VAT × withholding rate
 * - net of withholding tax = gross − withholding tax
 * Rates are stored as percentages (12.000, 2.00). Amounts round half up to centavos.
 */
class RfpTaxCalculator
{
    /**
     * @return array{vat_amount: string, net_of_vat_amount: string, withholding_tax_amount: string, net_of_withholding_tax_amount: string}
     */
    public function forLine(string $gross, ?TaxCode $taxCode, ?WithholdingTaxCode $withholdingTaxCode): array
    {
        $vatDivisor = bcadd('1', bcdiv((string) ($taxCode?->rate ?? '0'), '100', 6), 6);
        $netOfVat = $this->round(bcdiv($gross, $vatDivisor, 6));
        $withholding = $this->round(bcmul($netOfVat, bcdiv((string) ($withholdingTaxCode?->rate ?? '0'), '100', 6), 6));

        return [
            'vat_amount' => bcsub($gross, $netOfVat, 2),
            'net_of_vat_amount' => $netOfVat,
            'withholding_tax_amount' => $withholding,
            'net_of_withholding_tax_amount' => bcsub($gross, $withholding, 2),
        ];
    }

    private function round(string $amount): string
    {
        $half = str_starts_with($amount, '-') ? '-0.005' : '0.005';

        return bcadd($amount, $half, 2);
    }
}
