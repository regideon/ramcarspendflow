<?php

namespace App\Services\P2p\Sap;

/**
 * Reads ACDOCA accounting documents from SAP.
 *
 * Ported from the legacy `AcdocaService`. A parked invoice is looked up by its
 * SAP document number (`AWREF`), company code and fiscal year; the response
 * carries the FI document (`BELNR`) and the clearing document (`AUGBL`).
 */
class P2pAcdocaService
{
    private const string ENDPOINT = '/sap/opu/odata/sap/ZCDS_FI_ACDOCA_CDS/ZCDS_FI_ACDOCA';

    public function __construct(private readonly P2pSapClient $client) {}

    /**
     * @return array{success: bool, data?: list<array<string, mixed>>, error?: string, status?: int}
     */
    public function getAccountingDocuments(
        ?string $awref = null,
        ?string $rbukrs = null,
        ?string $aworg = null,
        ?string $belnr = null,
        ?string $gjahr = null,
        ?string $koart = 'K',
    ): array {
        $filters = [];

        foreach ([
            'AWREF' => $awref,
            'RBUKRS' => $rbukrs,
            'AWORG' => $aworg,
            'BELNR' => $belnr,
            'GJAHR' => $gjahr,
            'KOART' => $koart,
        ] as $field => $value) {
            if ($value !== null && trim($value) !== '') {
                $filters[] = "{$field} eq '".str_replace("'", "''", trim($value))."'";
            }
        }

        $params = $filters === [] ? [] : ['$filter' => implode(' and ', $filters)];
        $result = $this->client->get(self::ENDPOINT, $params);

        return [
            'success' => $result['success'],
            'data' => is_array($result['data'] ?? null) ? $result['data'] : [],
            'error' => $result['error'] ?? null,
            'status' => $result['status'] ?? null,
        ];
    }
}
