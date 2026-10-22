<?php

namespace App\Services\P2p\Sap;

use App\Models\P2p\Invoice;
use App\Models\P2p\SapApiLog;
use Illuminate\Support\Facades\Auth;

/**
 * Persists an audit row for every SAP call (SOAP writes and OData reads).
 * Passwords, tokens and authorization headers are redacted before storage.
 */
class P2pSapApiLogsService
{
    /**
     * Payloads are capped so a large OData response cannot exhaust a longText column
     * or the log viewer. The cap is generous enough for a full SOAP envelope.
     */
    private const int MAX_PAYLOAD_LENGTH = 65535;

    public function log(
        Invoice|int|null $invoice,
        string $endpoint,
        mixed $request = null,
        mixed $response = null,
        ?int $httpStatus = null,
        ?float $executionTime = null,
        ?string $errorMessage = null,
        string $type = SapApiLog::TYPE_SOAP,
        ?int $userId = null,
    ): SapApiLog {
        return SapApiLog::query()->create([
            'type' => $type,
            'endpoint' => $endpoint,
            'request' => $this->sanitize($request),
            'response' => $this->sanitize($response),
            'http_status' => $httpStatus,
            'error_message' => $errorMessage,
            'execution_time' => $executionTime,
            'user_id' => $userId ?? Auth::id(),
            'p2p_invoice_id' => $invoice instanceof Invoice ? $invoice->id : $invoice,
        ]);
    }

    /**
     * Log an OData read performed during a SAP sync or status poll.
     *
     * @param  array<string, mixed>  $params
     */
    public function logOData(
        string $endpoint,
        array $params = [],
        ?int $responseStatus = null,
        ?float $executionTime = null,
        ?string $errorMessage = null,
        mixed $responsePayload = null,
        ?int $userId = null,
    ): SapApiLog {
        return $this->log(
            invoice: null,
            endpoint: $endpoint,
            request: $params,
            response: $responsePayload,
            httpStatus: $responseStatus,
            executionTime: $executionTime,
            errorMessage: $errorMessage,
            type: SapApiLog::TYPE_ODATA,
            userId: $userId,
        );
    }

    public function logSuccess(
        string $endpoint,
        mixed $request,
        mixed $response,
        int $httpStatus = 200,
        ?float $executionTime = null,
        ?int $invoiceId = null,
    ): SapApiLog {
        return $this->log(
            invoice: $invoiceId,
            endpoint: $endpoint,
            request: $request,
            response: $response,
            httpStatus: $httpStatus,
            executionTime: $executionTime,
        );
    }

    public function logError(
        string $endpoint,
        mixed $request,
        string $errorMessage,
        ?int $httpStatus = null,
        ?float $executionTime = null,
        ?int $invoiceId = null,
        mixed $response = null,
        string $type = SapApiLog::TYPE_SOAP,
    ): SapApiLog {
        return $this->log(
            invoice: $invoiceId,
            endpoint: $endpoint,
            request: $request,
            response: $response,
            httpStatus: $httpStatus,
            executionTime: $executionTime,
            errorMessage: $errorMessage,
            type: $type,
        );
    }

    /**
     * Delete logs older than the retention window. SOAP traffic is kept longer
     * than OData reads because it is the financial audit trail.
     */
    public function cleanupOldLogs(int $days = 90, ?string $type = null): int
    {
        return SapApiLog::query()
            ->when($type !== null, fn ($query) => $query->ofType($type))
            ->where('created_at', '<', now()->subDays(max(1, $days)))
            ->delete();
    }

    protected function sanitize(mixed $payload): ?string
    {
        if ($payload === null) {
            return null;
        }

        $value = match (true) {
            is_string($payload) => $this->redactString($payload),
            is_array($payload) => json_encode($this->redactArray($payload), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            is_object($payload) => json_encode($this->redactArray((array) $payload), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            default => (string) $payload,
        };

        return mb_substr($value, 0, self::MAX_PAYLOAD_LENGTH);
    }

    /**
     * Redact credentials in raw XML/SOAP or JSON strings.
     */
    protected function redactString(string $payload): string
    {
        $payload = (string) preg_replace(
            '/<password>.*?<\/password>/i',
            '<password>***REDACTED***</password>',
            $payload,
        );

        $payload = (string) preg_replace(
            '/(Authorization\s*:\s*)[^\r\n]+/i',
            '$1***REDACTED***',
            $payload,
        );

        return (string) preg_replace(
            '/("?(?:password|passwd|token|secret|api_key|client_secret|sap_password)"?\s*[:=]\s*"?)[^"&\s<]+/i',
            '$1***REDACTED***',
            $payload,
        );
    }

    /**
     * Redact credentials at any nesting level.
     *
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    protected function redactArray(array $payload): array
    {
        $sensitiveKeys = [
            'password', 'passwd', 'token', 'secret', 'api_key', 'authorization',
            'client_secret', 'sap_password', 'incoming_invoice_password',
        ];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->redactArray($value);

                continue;
            }

            if (is_string($key) && in_array(strtolower($key), $sensitiveKeys, true)) {
                $payload[$key] = '***REDACTED***';
            }
        }

        return $payload;
    }
}
