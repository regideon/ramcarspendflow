<?php

namespace App\Services\Rfp;

use App\Ai\Agents\ReceiptReader;
use App\Enums\RfpStatus;
use App\Models\Currency;
use App\Models\Rfp\Item;
use App\Models\Rfp\OneTimeVendor;
use App\Models\Rfp\ReceiptRead;
use App\Models\Rfp\Subcategory;
use App\Models\Vendor;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * Read receipt: sends a line's supporting documents to the AI (ReceiptReader), then maps the
 * result to the line's fields and flags what the requestor should check before applying it.
 */
class ReceiptScanner
{
    public const array READABLE_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    /** One document per read: Supporting Documents takes one file per item. */
    public const int MAX_FILES = 1;

    /**
     * PDFs and images from a Supporting Documents upload (new uploads or saved files).
     *
     * @param  array<mixed>|null  $state
     * @return list<array{path: string, mime: string}>
     */
    public static function readableFiles(?array $state, string $disk = 'local'): array
    {
        return collect($state ?? [])
            ->map(function (mixed $file) use ($disk): ?array {
                try {
                    return match (true) {
                        $file instanceof TemporaryUploadedFile => ['path' => $file->getRealPath(), 'mime' => (string) $file->getMimeType()],
                        is_string($file) && Storage::disk($disk)->exists($file) => ['path' => Storage::disk($disk)->path($file), 'mime' => (string) Storage::disk($disk)->mimeType($file)],
                        default => null,
                    };
                } catch (Throwable) {
                    return null;
                }
            })
            ->filter(fn (?array $file): bool => $file !== null && in_array($file['mime'], self::READABLE_TYPES, true))
            ->take(self::MAX_FILES)
            ->values()
            ->all();
    }

    /**
     * The saved read of these documents, when they were read before (no AI call, no cost).
     *
     * @param  list<array{path: string, mime: string}>  $files
     * @return array<string, mixed>|null
     */
    public function savedRead(array $files): ?array
    {
        return ReceiptRead::query()
            ->where('file_hash', $this->fileHash($files))
            ->where('reader_version', ReceiptReader::VERSION)
            ->value('result');
    }

    /**
     * Read the documents with the AI and save the result for reuse. Returns the raw AI result.
     *
     * @param  list<array{path: string, mime: string}>  $files
     * @param  list<string>  $subcategories  names the line may use
     * @return array<string, mixed>
     */
    public function read(array $files, array $subcategories = []): array
    {
        $response = (new ReceiptReader($subcategories))->prompt(
            'Read the attached supporting document.',
            attachments: array_map(fn (array $file) => $file['mime'] === 'application/pdf'
                ? Files\Document::fromPath($file['path'])
                : Files\Image::fromPath($file['path']), $files),
        );

        $result = $response->toArray();

        ReceiptRead::query()->updateOrCreate(
            ['file_hash' => $this->fileHash($files), 'reader_version' => ReceiptReader::VERSION],
            ['result' => $result, 'model' => $response->meta->model ?? config('services.receipt_reader.model'), 'user_id' => auth()->id()],
        );

        return $result;
    }

    /**
     * SHA-256 of the documents' contents: the same file gives the same hash, whatever its name.
     *
     * @param  list<array{path: string, mime: string}>  $files
     */
    public function fileHash(array $files): string
    {
        $hashes = array_map(fn (array $file): string => hash_file('sha256', $file['path']), $files);

        return count($hashes) === 1 ? $hashes[0] : hash('sha256', implode('|', $hashes));
    }

    /**
     * Map a read to line fields, with warnings for the review.
     *
     * @param  array<string, mixed>  $read  ReceiptReader output
     * @param  array<int, string>  $subcategoryOptions  allowed sub categories for the line, keyed by id
     * @param  array{reimbursement?: bool, rfp_id?: int|null, payee_id?: int|null}  $context
     * @return array{fields: array<string, mixed>, confidence: array<string, string>, warnings: list<string>}
     */
    public function review(array $read, array $subcategoryOptions = [], array $context = []): array
    {
        $date = $this->date($read['invoice_date'] ?? null);
        $amount = is_numeric($read['total_amount'] ?? null) ? round((float) $read['total_amount'], 2) : null;
        $currencyCode = filled($read['currency'] ?? null) ? strtoupper(trim($read['currency'])) : null;
        $currencyId = $currencyCode ? Currency::query()->where('is_active', true)->where('code', $currencyCode)->value('id') : null;
        $invoiceNumber = filled($read['invoice_number'] ?? null) ? mb_substr(trim($read['invoice_number']), 0, 50) : null;
        $tin = $read['vendor_tin'] ?? null;

        $subcategoryId = $this->subcategoryId($read['suggested_subcategory'] ?? null, array_keys($subcategoryOptions));

        $warnings = [];

        if ($date && ($date->lt(today()->subMonths(6)) || $date->gt(today()->addDay()))) {
            $warnings[] = "The document date ({$date->format('M j, Y')}) is outside what an RFP accepts (the last 6 months).";
        }

        if ($amount !== null && $amount <= 0) {
            $warnings[] = 'The total read is zero or negative.';
        }

        if ($currencyCode && ! $currencyId) {
            $warnings[] = "The currency {$currencyCode} is not set up here. Choose the currency yourself.";
        }

        if (is_numeric($read['vatable_amount'] ?? null) && is_numeric($read['vat_amount'] ?? null)
            && abs(round((float) $read['vatable_amount'] * 0.12, 2) - (float) $read['vat_amount']) > 1) {
            $warnings[] = 'The VAT printed is not 12% of the VATable sales. Check the amounts.';
        }

        if ($duplicate = $this->duplicateRfpNumber($invoiceNumber, $amount, $context['rfp_id'] ?? null)) {
            $warnings[] = "Invoice no. {$invoiceNumber} for this amount is already on {$duplicate}. Make sure it isn't billed twice.";
        }

        $vendor = $this->vendorByTin($tin);

        if (! ($context['reimbursement'] ?? false) && filled($context['payee_id'] ?? null) && $this->tinKey($tin)) {
            $payee = Vendor::find($context['payee_id']);

            if ($payee && $this->tinKey($payee->tax_number) && $this->tinKey($payee->tax_number) !== $this->tinKey($tin)) {
                $warnings[] = "The document is from {$read['vendor_name']} (TIN {$tin}), not the vendor on this RFP ({$payee->name}).";
            }
        }

        if (filled($read['notes'] ?? null)) {
            $warnings[] = 'From the reader: '.$read['notes'];
        }

        return [
            'fields' => [
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $date?->toDateString(),
                'description' => filled($read['description'] ?? null) ? mb_substr(trim($read['description']), 0, 150) : null,
                'purpose' => filled($read['purpose'] ?? null) ? mb_substr(trim($read['purpose']), 0, 1000) : null,
                'currency_id' => $currencyId,
                'amount' => $amount,
                'vendor_name' => $read['vendor_name'] ?? null,
                'vendor_tin' => $tin,
                'vendor_id' => $vendor?->id,
                'rfp_subcategory_id' => $subcategoryId,
            ],
            'confidence' => (array) ($read['confidence'] ?? []),
            'warnings' => $warnings,
        ];
    }

    /**
     * An active vendor with the same TIN (branch code ignored).
     */
    public function vendorByTin(?string $tin): ?Vendor
    {
        $key = $this->tinKey($tin);

        return $key ? Vendor::query()->where('is_active', true)->tap(fn (Builder $query) => $this->whereTin($query, 'tax_number', $key))->first() : null;
    }

    /**
     * The one-time vendor with this TIN, created from the reviewed name when there is none.
     */
    public function oneTimeVendorFor(string $name, string $tin): OneTimeVendor
    {
        $key = $this->tinKey($tin);

        return ($key ? OneTimeVendor::query()->tap(fn (Builder $query) => $this->whereTin($query, 'tin', $key))->first() : null)
            ?? OneTimeVendor::create(['name' => mb_substr($name, 0, 150), 'tin' => $this->formatTin($tin)]);
    }

    /**
     * The first 9 digits of a TIN (the branch code varies between documents).
     */
    public function tinKey(?string $tin): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $tin);

        return strlen($digits) >= 9 ? substr($digits, 0, 9) : null;
    }

    /**
     * 000-000-000-000: the 9-digit TIN and a 3-digit branch code (5-digit codes keep their last 3).
     */
    public function formatTin(string $tin): string
    {
        $digits = preg_replace('/\D/', '', $tin);

        if (strlen($digits) < 9) {
            return mb_substr(trim($tin), 0, 15);
        }

        $branch = str_pad(substr(substr($digits, 9), -3), 3, '0', STR_PAD_LEFT);

        return implode('-', [...str_split(substr($digits, 0, 9), 3), $branch]);
    }

    /**
     * TINs are stored with or without dashes and spaces; match on the first 9 digits.
     */
    protected function whereTin(Builder $query, string $column, string $key): void
    {
        $query->whereRaw("REPLACE(REPLACE({$column}, '-', ''), ' ', '') LIKE ?", ["{$key}%"]);
    }

    protected function date(mixed $value): ?CarbonImmutable
    {
        try {
            return filled($value) ? CarbonImmutable::createFromFormat('!Y-m-d', (string) $value) ?: null : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  list<int>  $allowedIds
     */
    protected function subcategoryId(?string $name, array $allowedIds): ?int
    {
        if (blank($name) || $allowedIds === []) {
            return null;
        }

        return Subcategory::query()->whereKey($allowedIds)->where('name', trim($name))->value('id');
    }

    protected function duplicateRfpNumber(?string $invoiceNumber, ?float $amount, ?int $rfpId): ?string
    {
        if (blank($invoiceNumber) || $amount === null) {
            return null;
        }

        return Item::query()
            ->where('invoice_number', $invoiceNumber)
            ->where('amount', $amount)
            ->whereHas('rfp', fn (Builder $query) => $query
                ->whereNotIn('status', [RfpStatus::Rejected, RfpStatus::Cancelled])
                ->when($rfpId, fn (Builder $query) => $query->whereKeyNot($rfpId)))
            ->with('rfp:id,rfp_number')
            ->first()
            ?->rfp
            ?->rfp_number;
    }
}
