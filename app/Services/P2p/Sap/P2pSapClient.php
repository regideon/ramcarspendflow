<?php

namespace App\Services\P2p\Sap;

use App\Models\P2p\SapApiLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The single transport for SAP communication.
 *
 * Ported from the legacy `SapBaseService` and `SapIncomingInvoiceService`, with
 * the security fixes recorded in docs/p2p/07-sap-integration.md: TLS
 * verification is configurable and ON by default, and every call is logged
 * (redacted) to `p2p_sap_api_logs`.
 */
class P2pSapClient
{
    private const int MAX_ATTEMPTS = 3;

    public function __construct(private readonly P2pSapApiLogsService $logs) {}

    /**
     * OData GET. Retries transient failures; logs one row for the final attempt.
     *
     * @param  array<string, mixed>  $params
     * @return array{success: bool, status?: int, data?: mixed, body?: string, error?: string}
     */
    public function get(string $endpoint, array $params = []): array
    {
        $params = array_merge([
            '$format' => 'json',
            'sap-client' => $this->clientCode(),
        ], $params);

        $url = $this->baseUrl().$endpoint;
        $started = microtime(true);
        $response = null;
        $error = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $response = Http::withBasicAuth($this->username(), $this->password())
                    ->withOptions(['verify' => $this->verifyTls()])
                    ->timeout($this->timeout())
                    ->get($url, $params);

                if (! $response->serverError()) {
                    break;
                }

                $error = 'SAP returned HTTP '.$response->status();
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }

            if ($attempt < self::MAX_ATTEMPTS) {
                usleep(250_000 * $attempt);
            }
        }

        $duration = microtime(true) - $started;

        if ($response === null) {
            $this->logs->logOData($endpoint, $params, null, $duration, $error ?? 'SAP request failed');

            return ['success' => false, 'error' => $error ?? 'SAP request failed'];
        }

        $body = $response->body();
        $successful = $response->successful();

        if (config('p2p.sap.log_odata', true)) {
            $this->logs->logOData(
                endpoint: $endpoint,
                params: $params,
                responseStatus: $response->status(),
                executionTime: $duration,
                errorMessage: $successful ? null : ($error ?? 'SAP OData request failed'),
                responsePayload: Str::limit($body, 2000),
            );
        }

        if (! $successful) {
            return [
                'success' => false,
                'status' => $response->status(),
                'body' => $body,
                'error' => $error ?? 'SAP OData request failed with HTTP '.$response->status(),
            ];
        }

        return [
            'success' => true,
            'status' => $response->status(),
            'data' => $response->json('d.results') ?? $response->json('d'),
            'body' => $body,
        ];
    }

    /**
     * SOAP POST. Never retried: parking an invoice is not idempotent in SAP.
     *
     * @return array{success: bool, status?: int, body?: string, error?: string}
     */
    public function postSoap(
        string $endpoint,
        string $xml,
        string $soapAction,
        ?int $invoiceId = null,
    ): array {
        $started = microtime(true);

        try {
            $response = Http::withBasicAuth($this->invoiceUsername(), $this->invoicePassword())
                ->withHeaders([
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => $soapAction,
                ])
                ->withOptions(['verify' => $this->verifyTls()])
                ->timeout($this->timeout())
                ->withBody($xml, 'text/xml; charset=utf-8')
                ->post($endpoint);
        } catch (Throwable $exception) {
            $duration = microtime(true) - $started;

            $this->logs->logError(
                endpoint: $endpoint,
                request: $xml,
                errorMessage: $exception->getMessage(),
                executionTime: $duration,
                invoiceId: $invoiceId,
            );

            return ['success' => false, 'error' => $exception->getMessage()];
        }

        $duration = microtime(true) - $started;
        $successful = $response->successful();

        $this->logs->log(
            invoice: $invoiceId,
            endpoint: $endpoint,
            request: $xml,
            response: $response->body(),
            httpStatus: $response->status(),
            executionTime: $duration,
            errorMessage: $successful ? null : 'SAP SOAP request failed with HTTP '.$response->status(),
            type: SapApiLog::TYPE_SOAP,
        );

        return [
            'success' => $successful,
            'status' => $response->status(),
            'body' => $response->body(),
            'error' => $successful ? null : 'SAP SOAP request failed with HTTP '.$response->status(),
        ];
    }

    public function baseUrl(): string
    {
        $url = rtrim((string) config('p2p.sap.base_url'), '/');

        if ($url === '') {
            throw new RuntimeException('SAP base URL is not configured (SAP_BASE_URL).');
        }

        return $url;
    }

    public function clientCode(): string
    {
        return (string) config('p2p.sap.client', '200');
    }

    public function username(): string
    {
        return (string) config('p2p.sap.username');
    }

    public function password(): string
    {
        return (string) config('p2p.sap.password');
    }

    public function invoiceUsername(): string
    {
        return (string) config('p2p.sap.incoming_invoice_username');
    }

    public function invoicePassword(): string
    {
        return (string) config('p2p.sap.incoming_invoice_password');
    }

    public function verifyTls(): bool
    {
        return filter_var(config('p2p.sap.verify_tls', true), FILTER_VALIDATE_BOOL);
    }

    public function timeout(): int
    {
        return max(1, (int) config('p2p.sap.timeout', 60));
    }
}
