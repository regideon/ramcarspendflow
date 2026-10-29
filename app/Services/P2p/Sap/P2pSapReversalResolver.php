<?php

namespace App\Services\P2p\Sap;

/**
 * Resolves GR reversals from raw EKBE rows.
 *
 * Ported from the legacy `ReversalDetectionService` (BWART 101/102). The new
 * app currently applies document-level reversals only, so this returns the GRs
 * that were **fully** reversed and their replacement GR, if one exists.
 */
class P2pSapReversalResolver
{
    public const string MOVEMENT_GOODS_RECEIPT = '101';

    public const string MOVEMENT_REVERSAL = '102';

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{reversed: array<string, string>, replacements: array<string, string>}
     */
    public function resolve(array $rows): array
    {
        $normalized = [];

        foreach ($rows as $row) {
            $ebeln = trim((string) ($row['EBELN'] ?? ''));

            if ($ebeln === '') {
                continue;
            }

            $normalized[] = [
                'ebeln' => $ebeln,
                'ebelp' => self::normalizePoItem((string) ($row['EBELP'] ?? '')),
                'bwart' => trim((string) ($row['BWART'] ?? '')),
                'belnr' => trim((string) ($row['BELNR'] ?? '')),
                'lfbnr' => trim((string) ($row['LFBNR'] ?? '')),
                'budat' => trim((string) ($row['BUDAT'] ?? '')),
                'menge' => (float) ($row['MENGE'] ?? 0),
            ];
        }

        /** @var array<string, array<string, float>> $receivedByDocument */
        $receivedByDocument = [];
        /** @var array<string, array<string, array{document: string, quantity: float}>> $reversedByDocument */
        $reversedByDocument = [];
        /** @var array<string, string> $poByDocument */
        $poByDocument = [];
        /** @var array<string, list<array<string, mixed>>> $byLine */
        $byLine = [];

        foreach ($normalized as $row) {
            $byLine[$row['ebeln'].'|'.$row['ebelp']][] = $row;

            if ($row['bwart'] === self::MOVEMENT_GOODS_RECEIPT && $row['belnr'] !== '') {
                $receivedByDocument[$row['belnr']][$row['ebelp']] =
                    ($receivedByDocument[$row['belnr']][$row['ebelp']] ?? 0) + $row['menge'];
                $poByDocument[$row['belnr']] = $row['ebeln'];

                continue;
            }

            if ($row['bwart'] !== self::MOVEMENT_REVERSAL || $row['lfbnr'] === '') {
                continue;
            }

            $existing = $reversedByDocument[$row['lfbnr']][$row['ebelp']] ?? null;

            $reversedByDocument[$row['lfbnr']][$row['ebelp']] = [
                'document' => $existing['document'] ?? $row['belnr'],
                'quantity' => ($existing['quantity'] ?? 0) + $row['menge'],
            ];
        }

        /** @var array<string, string> $replacementsByLine */
        $replacementsByLine = [];

        foreach ($byLine as $lineKey => $lineRows) {
            usort(
                $lineRows,
                fn (array $a, array $b): int => [$a['budat'], $a['belnr']] <=> [$b['budat'], $b['belnr']],
            );

            foreach ($lineRows as $index => $row) {
                if ($row['bwart'] !== self::MOVEMENT_REVERSAL || $row['lfbnr'] === '') {
                    continue;
                }

                for ($next = $index + 1; $next < count($lineRows); $next++) {
                    $candidate = $lineRows[$next];

                    if ($candidate['bwart'] !== self::MOVEMENT_GOODS_RECEIPT
                        || $candidate['belnr'] === ''
                        || $candidate['belnr'] === $row['lfbnr']) {
                        continue;
                    }

                    $replacementsByLine[$lineKey] = self::earliestReplacement(
                        $replacementsByLine[$lineKey] ?? null,
                        $candidate['belnr'],
                    );

                    break;
                }
            }
        }

        $reversed = [];
        $replacements = [];

        foreach ($reversedByDocument as $original => $reversedLines) {
            $receivedLines = $receivedByDocument[$original] ?? [];
            $itemNumbers = array_unique(array_merge(array_keys($receivedLines), array_keys($reversedLines)));
            $fullyReversed = $itemNumbers !== [];
            $reversalDocument = null;
            $replacement = null;

            foreach ($itemNumbers as $item) {
                $received = (float) ($receivedLines[$item] ?? 0);
                $reversedInfo = $reversedLines[$item] ?? null;
                $reversedQuantity = (float) ($reversedInfo['quantity'] ?? 0);

                if ($reversedInfo !== null && $reversedQuantity <= 0) {
                    $reversedQuantity = $received;
                }

                if ($received > 0) {
                    $reversedQuantity = min($reversedQuantity, $received);
                }

                $lineFullyReversed = $reversedInfo !== null
                    && ($received <= 0 || $reversedQuantity >= $received);

                if (! $lineFullyReversed) {
                    $fullyReversed = false;
                }

                $lineReplacement = $replacementsByLine[
                    ($poByDocument[$original] ?? '').'|'.$item
                ] ?? null;

                $replacement = self::earliestReplacement($replacement, $lineReplacement);

                if ($reversedInfo !== null) {
                    $reversalDocument ??= $reversedInfo['document'];
                }
            }

            if (! $fullyReversed || $reversalDocument === null) {
                continue;
            }

            $reversed[$original] = $reversalDocument;

            if ($replacement !== null) {
                $replacements[$original] = $replacement;
            }
        }

        return ['reversed' => $reversed, 'replacements' => $replacements];
    }

    public static function normalizePoItem(?string $purchaseOrderItem): string
    {
        return str_pad(trim((string) $purchaseOrderItem), 5, '0', STR_PAD_LEFT);
    }

    protected static function earliestReplacement(?string $current, ?string $candidate): ?string
    {
        if ($current === null || $candidate === null) {
            return $current ?? $candidate;
        }

        return (int) $candidate < (int) $current ? $candidate : $current;
    }
}
