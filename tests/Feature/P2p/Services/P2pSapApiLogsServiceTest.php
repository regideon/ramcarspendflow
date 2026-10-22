<?php

use App\Models\P2p\SapApiLog;
use App\Services\P2p\Sap\P2pSapApiLogsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

it('redacts passwords and authorization headers before storing a log', function () {
    $log = app(P2pSapApiLogsService::class)->log(
        invoice: null,
        endpoint: 'test',
        request: '<password>super-secret</password> Authorization: Basic abc123',
        response: ['password' => 'super-secret', 'safe' => 'ok'],
        type: SapApiLog::TYPE_SOAP,
    );

    expect($log->request)
        ->toContain('***REDACTED***')
        ->not->toContain('super-secret')
        ->not->toContain('abc123');

    expect($log->response)
        ->toContain('"safe": "ok"')
        ->not->toContain('super-secret');
});

it('prunes logs older than the retention window', function () {
    $old = SapApiLog::create([
        'type' => SapApiLog::TYPE_ODATA,
        'endpoint' => 'old',
        'request' => '{}',
        'http_status' => 200,
    ]);
    $old->created_at = now()->subDays(91);
    $old->save();

    $recent = SapApiLog::create([
        'type' => SapApiLog::TYPE_ODATA,
        'endpoint' => 'recent',
        'request' => '{}',
        'http_status' => 200,
    ]);

    $deleted = app(P2pSapApiLogsService::class)->cleanupOldLogs(90);

    expect($deleted)->toBe(1)
        ->and(SapApiLog::query()->find($old->id))->toBeNull()
        ->and(SapApiLog::query()->find($recent->id))->not->toBeNull();
});
