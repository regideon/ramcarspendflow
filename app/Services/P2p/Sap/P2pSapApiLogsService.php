<?php

namespace App\Services\P2p\Sap;

use App\Models\P2p\Invoice;
use App\Models\P2p\SapApiLog;

/**
 * Persists an audit row for every SAP API call (SOAP writes, and reads when logged).
 * Passwords are redacted before storage.
 */
class P2pSapApiLogsService
{
    public function log(
        Invoice|int|null $invoice,
        string $endpoint,
        string $request,
        string $response,
        ?int $httpStatus = null,
        ?float $executionTime = null,
    ): SapApiLog {
        return SapApiLog::query()->create([
            'p2p_invoice_id' => $invoice instanceof Invoice ? $invoice->id : $invoice,
            'endpoint' => $endpoint,
            'request' => $this->redact($request),
            'response' => $this->redact($response),
            'http_status' => $httpStatus,
            'execution_time' => $executionTime,
        ]);
    }

    protected function redact(string $payload): string
    {
        return (string) preg_replace('/(password|PASSWORD|passwd)("?\s*[:=]\s*"?)[^"&\s<]+/', '$1$2***', $payload);
    }
}
