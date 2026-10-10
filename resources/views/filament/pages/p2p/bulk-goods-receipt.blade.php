<x-filament-panels::page>
    @if ($this->receipts->isEmpty())
        <x-filament::section>
            <p class="text-gray-500 dark:text-gray-400">No goods receipts selected.</p>
        </x-filament::section>
    @else
        <x-filament::section heading="Invoice details">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <label class="block">
                    <span class="text-sm font-medium">Invoice date</span>
                    <input type="date" wire:model="invoiceAt"
                           class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900" />
                    @error('invoiceAt') <span class="text-danger-600 text-sm">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="text-sm font-medium">Reference no(s). (comma separated)</span>
                    <input type="text" wire:model="referenceNumbers" placeholder="DR-1234, DR-1235"
                           class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900" />
                    @error('referenceNumbers') <span class="text-danger-600 text-sm">{{ $message }}</span> @enderror
                </label>
            </div>
        </x-filament::section>

        @foreach ($this->receipts as $receipt)
            <x-filament::section
                heading="GR {{ $receipt->good_receipt_number }} · PO {{ $receipt->purchase_order }}"
                description="{{ $receipt->vendor?->name }} · {{ $receipt->status->getLabel() }}">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-gray-500">
                            <tr>
                                <th class="w-10"></th>
                                <th class="py-1">Item</th>
                                <th class="py-1">PO item</th>
                                <th class="py-1">UoM</th>
                                <th class="py-1 text-right">Received</th>
                                <th class="py-1 text-right">Unit cost</th>
                                <th class="py-1 text-right">Qty to bill</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($receipt->sapGoodReceipt?->items ?? [] as $item)
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="py-1">
                                        <input type="checkbox" wire:model="selected.{{ $item->id }}" />
                                    </td>
                                    <td class="py-1">{{ $item->item_name }}</td>
                                    <td class="py-1">{{ $item->purchase_order_item }}</td>
                                    <td class="py-1">{{ $item->uom }}</td>
                                    <td class="py-1 text-right">{{ $item->quantity_served ?? $item->quantity_po }}</td>
                                    <td class="py-1 text-right">{{ number_format((float) $item->unit_cost, 2) }}</td>
                                    <td class="py-1 text-right">
                                        <input type="number" step="0.001" min="0"
                                               wire:model="quantities.{{ $item->id }}"
                                               class="w-28 rounded-lg border-gray-300 text-right dark:border-gray-600 dark:bg-gray-900" />
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="py-2 text-gray-500">No lines.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endforeach

        <x-filament::section heading="Escalate instead">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <label class="block">
                    <span class="text-sm font-medium">Reason</span>
                    <select wire:model="escalationReason"
                            class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900">
                        <option value="">—</option>
                        @foreach ($this->escalationReasons as $reason)
                            <option value="{{ $reason->code }}">{{ $reason->name }}</option>
                        @endforeach
                    </select>
                    @error('escalationReason') <span class="text-danger-600 text-sm">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="text-sm font-medium">Remarks</span>
                    <input type="text" wire:model="escalationRemarks"
                           class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900" />
                    @error('escalationRemarks') <span class="text-danger-600 text-sm">{{ $message }}</span> @enderror
                </label>
            </div>
        </x-filament::section>

        <div class="flex flex-wrap gap-3">
            <x-filament::button wire:click="submit" icon="heroicon-o-paper-airplane">
                Submit to AP
            </x-filament::button>
            <x-filament::button wire:click="escalate" color="warning" icon="heroicon-o-arrow-trending-up">
                Escalate
            </x-filament::button>
        </div>
    @endif
</x-filament-panels::page>
