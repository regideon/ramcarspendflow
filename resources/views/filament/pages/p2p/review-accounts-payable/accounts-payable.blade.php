@php
    /**
     * The Accounts Payable header (read only): the same values as the old screen's fields.
     *
     * @var array<string, mixed> $data
     */
    $references = implode(', ', (array) ($data['reference_number'] ?? []));
    $invoiceDate = filled($data['invoice_at'] ?? null) ? \Illuminate\Support\Carbon::parse($data['invoice_at'])->format('M j, Y') : null;

    $tiles = [
        ['Vendor', $data['vendor_name'] ?? null, $data['vendor_code'] ?? null, false],
        ['Company', $data['company_name'] ?? null, $data['company_code'] ?? null, false],
        ['Invoice Date', $invoiceDate, null, false],
        ['Reference No.', $references ?: null, null, true],
        ['Overall Invoice Total', filled($data['overall_invoice_total'] ?? null) ? $currency.$data['overall_invoice_total'] : null, null, false],
        ['Invoice No.', $data['invoice_number'] ?? null, null, true],
    ];
@endphp

<section class="sf-card p-6">
    <h2 class="font-display text-lg font-bold text-gray-900 dark:text-white">Accounts Payable</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400">Review and validate the submitted Accounts Payable before forwarding them for approval.</p>

    <dl class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-2">
        @foreach ($tiles as [$label, $value, $hint, $mono])
            <div class="flex flex-col justify-center rounded-xl bg-gray-50 p-3 dark:bg-white/5">
                <dt class="text-[0.6875rem] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                @if (filled($value))
                    <dd @class([
                        'mt-0.5 font-semibold text-gray-900 dark:text-white',
                        'font-mono' => $mono,
                        'font-display text-lg text-primary-700 dark:text-primary-300' => $label === 'Overall Invoice Total',
                    ])>
                        {{ $value }}
                        @if (filled($hint) && $hint !== 'N/A')
                            <span class="ms-1 text-xs font-medium text-gray-500 dark:text-gray-400">{{ $hint }}</span>
                        @endif
                    </dd>
                @else
                    <dd class="mt-0.5 text-sm italic text-gray-500 dark:text-gray-400">{{ $label === 'Invoice No.' ? 'SAP-populated on posting' : '—' }}</dd>
                @endif
            </div>
        @endforeach
    </dl>
</section>
