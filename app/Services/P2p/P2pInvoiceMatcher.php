<?php

namespace App\Services\P2p;

use App\Enums\P2pStatus;
use App\Enums\P2pThreeWayMatchStatus;
use App\Models\P2p\Invoice;
use App\Models\P2p\SapGoodReceiptItem;
use App\Support\GoodReceiptNumber;
use Illuminate\Support\Collection;

/**
 * Links the lines of the vendor's invoices (as read by VendorInvoiceReader) to the GR items on
 * the Process Goods Receipts screen, and checks each link: quantity (via the 3-way match
 * service) and PO price and currency, exact to the centavo.
 *
 * Handwritten material codes and GR numbers are often misread, so a line is linked by score:
 * GR no., PO no., material code (exact or one or two characters off), exact price, quantity
 * and shared words in the description. The AI only reads; every decision here is in code.
 */
class P2pInvoiceMatcher
{
    /** A line needs at least this score to be linked (unless it is the only possible row). */
    public const int MIN_SCORE = 3;

    public function __construct(
        protected P2pThreeWayMatchService $threeWay,
        protected P2pGoodReceiptSubmissionService $submissions,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $invoices  VendorInvoiceReader "invoices", from every attached file
     * @param  Collection<int, SapGoodReceiptItem>  $items  GR items on screen (with item and goodReceipt loaded)
     * @param  Invoice|null  $current  the invoice being edited (returned to vendor), excluded from billed quantities
     * @return array{
     *     rows: array<int, array{quantity: float, invoice_number: ?string, invoice_date: ?string, description: ?string, unit_price: ?float, currency: ?string, status: string, price_ok: ?bool, price_reasons: list<string>, notes: list<string>, reasons: list<string>, score: int}>,
     *     unmatched_lines: list<array{invoice_number: ?string, description: ?string, quantity: mixed, unit_price: mixed, reason: string}>,
     *     charges: list<array{invoice_number: ?string, description: ?string, amount: float}>,
     *     invoices: list<array{invoice_number: ?string, invoice_date: ?string, currency: ?string, total_amount: mixed}>,
     *     warnings: list<string>,
     * }
     */
    public function match(array $invoices, Collection $items, ?Invoice $current = null): array
    {
        $remaining = $this->submissions->remainingQuantitiesFor($items, $current);
        $rows = $items->keyBy('id');

        $result = ['rows' => [], 'unmatched_lines' => [], 'charges' => [], 'invoices' => [], 'warnings' => []];

        if ($invoices === []) {
            $result['warnings'][] = 'No invoice was found in the attachments.';

            return $result;
        }

        $candidates = []; // [score, invoice index, line index, item id]

        foreach ($invoices as $i => $invoice) {
            $result['invoices'][] = [
                'invoice_number' => $invoice['invoice_number'] ?? null,
                'invoice_date' => $invoice['invoice_date'] ?? null,
                'currency' => $invoice['currency'] ?? null,
                'total_amount' => $invoice['total_amount'] ?? null,
            ];

            array_push($result['warnings'], ...$this->invoiceWarnings($invoice, $rows, $current));

            foreach ($invoice['charges'] ?? [] as $charge) {
                if ((float) ($charge['amount'] ?? 0) > 0) {
                    $result['charges'][] = [
                        'invoice_number' => $invoice['invoice_number'] ?? null,
                        'description' => $charge['description'] ?? null,
                        'amount' => (float) $charge['amount'],
                    ];
                }
            }

            $lines = $invoice['lines'] ?? [];

            foreach ($lines as $l => $line) {
                $pool = $this->candidateRows($line, $invoice, $rows);

                foreach ($pool as $item) {
                    $score = $this->score($line, $invoice, $item, $remaining[$item->id] ?? 0);

                    // The only line against the only possible row links even without other evidence
                    if ($score < self::MIN_SCORE && count($lines) === 1 && $pool->count() === 1) {
                        $score = self::MIN_SCORE;
                    }

                    if ($score >= self::MIN_SCORE) {
                        $candidates[] = [$score, $i, $l, $item->id];
                    }
                }
            }
        }

        // Best links first; each line and each row is used once
        usort($candidates, fn (array $a, array $b): int => $b[0] <=> $a[0]);
        $usedLines = [];

        foreach ($candidates as [$score, $i, $l, $itemId]) {
            if (isset($usedLines["{$i}.{$l}"]) || isset($result['rows'][$itemId])) {
                continue;
            }

            $usedLines["{$i}.{$l}"] = true;
            $result['rows'][$itemId] = $this->check($invoices[$i], $invoices[$i]['lines'][$l], $rows[$itemId], $remaining[$itemId] ?? 0, $score);
        }

        foreach ($invoices as $i => $invoice) {
            foreach ($invoice['lines'] ?? [] as $l => $line) {
                if (! isset($usedLines["{$i}.{$l}"])) {
                    $result['unmatched_lines'][] = [
                        'invoice_number' => $invoice['invoice_number'] ?? null,
                        'description' => $line['description'] ?? null,
                        'quantity' => $line['quantity'] ?? null,
                        'unit_price' => $line['unit_price'] ?? null,
                        'reason' => 'No GR line on this screen matches it (check the PO and the GRs selected).',
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Quantity, price and currency checks for one linked line.
     *
     * @param  array<string, mixed>  $invoice
     * @param  array<string, mixed>  $line
     * @return array{quantity: float, invoice_number: ?string, invoice_date: ?string, description: ?string, unit_price: ?float, currency: ?string, status: string, price_ok: ?bool, price_reasons: list<string>, notes: list<string>, reasons: list<string>, score: int}
     */
    public function check(array $invoice, array $line, SapGoodReceiptItem $item, int $remaining, int $score = 0): array
    {
        $quantity = (float) ($line['quantity'] ?? 0);
        $received = (int) ($item->quantity_served ?? $item->quantity_po ?? 0);
        $billedBefore = $received - $remaining;

        $status = $this->threeWay->statusFor((int) $item->quantity_po, $received, (int) ceil($billedBefore + $quantity));
        $quantityReasons = $this->quantityReasons($quantity, $remaining);

        if ($quantity > $remaining) {
            $status = P2pThreeWayMatchStatus::NoMatch;
        }

        $notes = [];
        $priceReasons = [];
        $poCurrency = $item->currency;
        $currency = filled($invoice['currency'] ?? null) ? strtoupper((string) $invoice['currency']) : null;

        if ($currency === null && $poCurrency !== null) {
            $notes[] = "No currency printed on the invoice: taken as the PO currency ({$poCurrency}).";
            $currency = $poCurrency;
        }

        $unitPrice = is_numeric($line['unit_price'] ?? null) ? (float) $line['unit_price'] : null;
        $poPrice = $item->poUnitPrice();

        if ($poPrice === null) {
            $notes[] = 'The PO price is not available, so the price was not checked.';
        } elseif ($poCurrency !== null && $currency !== $poCurrency) {
            $priceReasons[] = "Currency {$currency} is not the PO currency {$poCurrency}.";
        } elseif ($unitPrice !== null && $this->cents($unitPrice) !== $this->cents((float) $poPrice)) {
            $priceReasons[] = sprintf('Price %s is not the PO price %s.', $this->money($unitPrice), $this->money((float) $poPrice));
        } elseif ($unitPrice === null && is_numeric($line['amount'] ?? null)
            && $this->cents((float) $line['amount']) !== $this->cents($quantity * (float) $poPrice)) {
            $priceReasons[] = sprintf('Amount %s is not %s × PO price %s.', $this->money((float) $line['amount']), $this->number($quantity), $this->money((float) $poPrice));
        }

        if ($priceReasons !== []) {
            $status = P2pThreeWayMatchStatus::NoMatch;
        }

        if (($line['confidence'] ?? 'high') === 'low') {
            $notes[] = 'Hard to read on the invoice: check the quantity and price.';
        }

        return [
            'quantity' => $quantity,
            'invoice_number' => $invoice['invoice_number'] ?? null,
            'invoice_date' => $invoice['invoice_date'] ?? null,
            'description' => $line['description'] ?? null,
            'unit_price' => $unitPrice,
            'currency' => $currency,
            'status' => $status->value,
            'price_ok' => $poPrice === null ? null : $priceReasons === [],
            'price_reasons' => $priceReasons,
            'notes' => $notes,
            'reasons' => [...$quantityReasons, ...$priceReasons, ...$notes],
            'score' => $score,
        ];
    }

    /**
     * Why an invoiced quantity does not simply equal what is left to bill.
     *
     * @return list<string>
     */
    public function quantityReasons(float $quantity, int $remaining): array
    {
        return match (true) {
            $quantity <= 0 => ['No quantity was read for this line.'],
            $quantity > $remaining => [sprintf('Inv Qty %s is more than the %s received and not yet billed.', $this->number($quantity), $remaining)],
            $quantity < $remaining => [sprintf('Partial: %s of %s billed on this invoice.', $this->number($quantity), $remaining)],
            default => [],
        };
    }

    /**
     * Rows on the line's PO (or the invoice's POs); every row when the invoice names none on screen.
     *
     * @param  array<string, mixed>  $line
     * @param  array<string, mixed>  $invoice
     * @param  Collection<int, SapGoodReceiptItem>  $rows
     * @return Collection<int, SapGoodReceiptItem>
     */
    protected function candidateRows(array $line, array $invoice, Collection $rows): Collection
    {
        $purchaseOrders = filled($line['po_number'] ?? null)
            ? [$this->digits($line['po_number'])]
            : array_map($this->digits(...), $invoice['po_numbers'] ?? []);

        $onPo = $rows->filter(fn (SapGoodReceiptItem $item): bool => in_array($this->digits($item->purchase_order), $purchaseOrders, true));

        return $onPo->isNotEmpty() ? $onPo : $rows;
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<string, mixed>  $invoice
     */
    protected function score(array $line, array $invoice, SapGoodReceiptItem $item, int $remaining): int
    {
        $score = 0;

        $grNumbers = array_map(
            fn (mixed $number): string => $this->digits(GoodReceiptNumber::base(is_scalar($number) ? (string) $number : null)),
            array_filter([$line['gr_number'] ?? null, ...($invoice['gr_numbers'] ?? [])]),
        );

        if (in_array($this->digits(GoodReceiptNumber::base($item->goodReceipt?->good_receipt_number)), $grNumbers, true)) {
            $score += 2;
        }

        $code = strtoupper(trim((string) ($line['material_code'] ?? '')));
        $material = strtoupper((string) $item->item?->code);

        if ($code !== '' && $material !== '') {
            $distance = strlen($code) === strlen($material) ? levenshtein($code, $material) : PHP_INT_MAX;
            $score += match (true) {
                $distance === 0 => 5,
                $distance <= 2 => 3,
                default => 0,
            };
        }

        $poPrice = $item->poUnitPrice();

        if ($poPrice !== null && is_numeric($line['unit_price'] ?? null) && $this->cents((float) $line['unit_price']) === $this->cents((float) $poPrice)) {
            $score += 3;
        }

        if (is_numeric($line['quantity'] ?? null) && (float) $line['quantity'] === (float) $remaining) {
            $score += 2;
        }

        $score += min(3, count(array_intersect(
            $this->words($line['description'] ?? ''),
            $this->words($item->item_name.' '.$item->item_description.' '.$item->item?->name),
        )));

        return $score;
    }

    /**
     * Warnings about an invoice as a whole: totals that don't add up, an invoice number already
     * billed, POs that are not on screen.
     *
     * @param  array<string, mixed>  $invoice
     * @param  Collection<int, SapGoodReceiptItem>  $rows
     * @return list<string>
     */
    protected function invoiceWarnings(array $invoice, Collection $rows, ?Invoice $current): array
    {
        $number = $invoice['invoice_number'] ?? null;
        $label = $number ? "Invoice {$number}" : 'An invoice';
        $warnings = [];

        if (blank($number)) {
            $warnings[] = 'An invoice number could not be read. Enter it in Reference Number.';
        } elseif ($this->alreadyBilled($number, $current)) {
            $warnings[] = "{$label} is already on another submission. Make sure it isn't billed twice.";
        }

        $lines = collect($invoice['lines'] ?? []);
        $sum = $lines->sum(fn (array $line): float => (float) ($line['amount'] ?? 0))
            + collect($invoice['charges'] ?? [])->sum(fn (array $charge): float => (float) ($charge['amount'] ?? 0))
            - (float) ($invoice['discount'] ?? 0);

        if (is_numeric($invoice['total_amount'] ?? null) && $lines->isNotEmpty()
            && ! $this->addsUp($sum, (float) $invoice['total_amount'], (float) ($invoice['vat_amount'] ?? 0))) {
            $warnings[] = sprintf('%s: the lines add up to %s, but the total is %s.', $label, $this->money($sum), $this->money((float) $invoice['total_amount']));
        }

        $onScreen = $rows->map(fn (SapGoodReceiptItem $item): string => $this->digits($item->purchase_order))->unique()->all();
        $missing = array_diff(array_map($this->digits(...), $invoice['po_numbers'] ?? []), $onScreen);

        if ($missing !== []) {
            $warnings[] = sprintf('%s is for PO %s, which is not among the GRs selected.', $label, implode(', ', $missing));
        }

        return $warnings;
    }

    protected function alreadyBilled(string $number, ?Invoice $current): bool
    {
        return Invoice::query()
            ->where('status', '!=', P2pStatus::Cancelled->value)
            ->when($current, fn ($query) => $query->whereKeyNot($current->getKey()))
            ->where(fn ($query) => $query->where('invoice_number', $number)->orWhereJsonContains('reference_number', $number))
            ->exists();
    }

    /**
     * The total may or may not include VAT on top of the lines.
     */
    protected function addsUp(float $sum, float $total, float $vat): bool
    {
        return $this->cents($sum) === $this->cents($total) || $this->cents($sum + $vat) === $this->cents($total);
    }

    /**
     * @return list<string>
     */
    protected function words(string $text): array
    {
        $words = preg_split('/[^A-Z0-9]+/', strtoupper($text), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_filter($words, fn (string $word): bool => strlen($word) >= 3 && ! ctype_digit($word))));
    }

    protected function digits(mixed $value): string
    {
        return preg_replace('/\D/', '', (string) $value);
    }

    protected function cents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    protected function money(float $amount): string
    {
        return number_format($amount, 2);
    }

    protected function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }
}
