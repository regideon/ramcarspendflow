<?php

use App\Enums\P2pStatus;
use App\Enums\TaxCodeCategory;
use App\Models\P2p\Invoice;
use App\Models\TaxCode;
use App\Models\WithholdingTaxCode;
use App\Services\P2p\P2pTaxSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

it('computes a taxable VAT summary', function () {
    $taxCode = TaxCode::factory()->create(['code' => 'I2', 'category' => TaxCodeCategory::Taxable, 'rate' => 0.120]);

    $summary = app(P2pTaxSummaryService::class)->calculateSummary('1000.00', $taxCode, '0.00', null);

    expect($summary['vatable_sales'])->toBe('1000.00')
        ->and($summary['vat'])->toBe('120.00')
        ->and($summary['total'])->toBe('1120.00')
        ->and($summary['ewt'])->toBe('0.00')
        ->and($summary['net'])->toBe('1120.00');
});

it('routes a zero-rated code to the zero-rated bucket', function () {
    $taxCode = TaxCode::factory()->create(['code' => 'I0', 'category' => TaxCodeCategory::ZeroRated, 'rate' => 0]);

    $summary = app(P2pTaxSummaryService::class)->calculateSummary('500.00', $taxCode, '0.00', null);

    expect($summary['vatable_sales'])->toBe('0.00')
        ->and($summary['vat_zero_rated'])->toBe('500.00')
        ->and($summary['vat'])->toBe('0.00');
});

it('computes the EWT from a withholding code rate percentage', function () {
    $taxCode = TaxCode::factory()->create(['code' => 'I2', 'category' => TaxCodeCategory::Taxable, 'rate' => 0.120]);
    $wht = WithholdingTaxCode::factory()->create(['code' => '04', 'rate' => 2.00]);

    $summary = app(P2pTaxSummaryService::class)->calculateSummary('1000.00', $taxCode, '1000.00', $wht);

    // total = 1120.00, ewt = 1000 × 2% = 20.00, net = 1100.00
    expect($summary['ewt'])->toBe('20.00')
        ->and($summary['net'])->toBe('1100.00');
});

it('persists the recalculated summary on an invoice', function () {
    TaxCode::factory()->create(['code' => 'I2', 'category' => TaxCodeCategory::Taxable, 'rate' => 0.120]);

    $invoice = Invoice::factory()->create([
        'status' => P2pStatus::SubmittedToAp,
        'amount_submitted' => 2000.00,
        'tax_code' => 'I2',
    ]);

    app(P2pTaxSummaryService::class)->recalculate($invoice);

    $fresh = $invoice->fresh();

    expect($fresh->vat)->toBe('240.00')
        ->and($fresh->total)->toBe('2240.00')
        ->and($fresh->net)->toBe('2240.00');
});
