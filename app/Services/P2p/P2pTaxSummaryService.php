<?php

namespace App\Services\P2p;

use App\Enums\TaxCodeCategory;
use App\Models\P2p\Invoice;
use App\Models\TaxCode;
use App\Models\WithholdingTaxCode;

/**
 * Computes the VAT / EWT summary for a P2P invoice. WHT rates come from the
 * resolved WHT code here; the live SAP resolution chain (LFBW → T001 → T059Z) is
 * deferred with the SAP integration (docs/p2p/07-sap-integration.md §5).
 *
 * Rules (legacy parity, docs/p2p/06-workflow-statuses.md §8):
 * - vatable_sales / vat_exempt / vat_zero_rated split by the VAT code's category
 * - vat  = vatable_sales × tax rate            (rate stored as a fraction, e.g. 0.12)
 * - total = sales + vat
 * - ewt  = withholding_tax_base × WHT rate / 100   (rate stored as a percentage, e.g. 2.00)
 * - net  = total − ewt
 */
class P2pTaxSummaryService
{
    /**
     * @return array{vatable_sales: string, vat_exempt: string, vat_zero_rated: string, vat: string, total: string, ewt: string, net: string}
     */
    public function calculateSummary(
        string $sales,
        ?TaxCode $taxCode,
        string $withholdingTaxBase,
        ?WithholdingTaxCode $withholdingTaxCode,
    ): array {
        $category = $taxCode?->category ?? TaxCodeCategory::VatExempt;

        $vatableSales = $category === TaxCodeCategory::Taxable ? $sales : '0.00';
        $vatExempt = $category === TaxCodeCategory::VatExempt ? $sales : '0.00';
        $vatZeroRated = $category === TaxCodeCategory::ZeroRated ? $sales : '0.00';

        $vat = $taxCode !== null
            ? bcmul($vatableSales, (string) $taxCode->rate, 2)
            : '0.00';

        $total = bcadd($sales, $vat, 2);

        $ewt = $withholdingTaxCode !== null
            ? bcmul($withholdingTaxBase, bcdiv((string) $withholdingTaxCode->rate, '100', 6), 2)
            : '0.00';

        $net = bcsub($total, $ewt, 2);

        return [
            'vatable_sales' => $vatableSales,
            'vat_exempt' => $vatExempt,
            'vat_zero_rated' => $vatZeroRated,
            'vat' => $vat,
            'total' => $total,
            'ewt' => $ewt,
            'net' => $net,
        ];
    }

    /**
     * Recompute and persist an invoice's VAT/EWT summary from its codes.
     */
    public function recalculate(Invoice $invoice): void
    {
        $taxCode = $invoice->tax_code !== null
            ? TaxCode::query()->where('code', $invoice->tax_code)->first()
            : null;

        $withholdingTaxCode = $invoice->withholding_tax_code !== null
            ? WithholdingTaxCode::query()->where('code', $invoice->withholding_tax_code)->first()
            : null;

        $summary = $this->calculateSummary(
            (string) $invoice->amount_submitted,
            $taxCode,
            (string) ($invoice->withholding_tax_base ?? $invoice->amount_submitted),
            $withholdingTaxCode,
        );

        $invoice->forceFill($summary)->save();
    }
}
