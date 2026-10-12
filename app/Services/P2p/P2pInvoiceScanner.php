<?php

namespace App\Services\P2p;

use App\Ai\Agents\VendorInvoiceReader;
use App\Models\P2p\InvoiceRead;
use Laravel\Ai\Files\Document;

/**
 * Read invoice with AI (P2P): reads a vendor's PDF into invoices and lines (VendorInvoiceReader),
 * saving each read by file hash so the same PDF is only paid for once.
 */
class P2pInvoiceScanner
{
    /**
     * The saved read of this PDF, when it was read before (no AI call, no cost).
     *
     * @return array<string, mixed>|null
     */
    public function savedRead(string $path): ?array
    {
        return InvoiceRead::query()
            ->where('file_hash', hash_file('sha256', $path))
            ->where('reader_version', VendorInvoiceReader::VERSION)
            ->value('result');
    }

    /**
     * Read the PDF with the AI and save the result for reuse.
     *
     * @return array<string, mixed>
     */
    public function read(string $path): array
    {
        $response = (new VendorInvoiceReader)->prompt(
            'Read the attached vendor document.',
            attachments: [Document::fromPath($path)],
        );

        $result = $response->toArray();

        InvoiceRead::query()->updateOrCreate(
            ['file_hash' => hash_file('sha256', $path), 'reader_version' => VendorInvoiceReader::VERSION],
            ['result' => $result, 'model' => $response->meta->model ?? config('services.invoice_reader.model'), 'user_id' => auth()->id()],
        );

        return $result;
    }

    /**
     * The saved read, or a new one.
     *
     * @return array{result: array<string, mixed>, reused: bool}
     */
    public function readOrReuse(string $path): array
    {
        $saved = $this->savedRead($path);

        return $saved !== null
            ? ['result' => $saved, 'reused' => true]
            : ['result' => $this->read($path), 'reused' => false];
    }
}
