<?php

use App\Models\P2p\InvoiceLine;
use App\Models\P2p\MaterialClassification;
use App\Models\P2p\SlocClassification;
use App\Models\User;
use App\Services\P2p\P2pMaterialClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->raw = MaterialClassification::create(['code' => 'raw_material', 'name' => 'Raw Materials']);
    $this->spare = MaterialClassification::create(['code' => 'spare_part', 'name' => 'Spare Parts']);
    $this->unclassified = MaterialClassification::create(['code' => 'unclassified', 'name' => 'Unclassified']);

    SlocClassification::create(['storage_location' => 'B001', 'p2p_material_classification_id' => $this->raw->id]);
    SlocClassification::create(['company_code' => '1000', 'storage_location' => 'B002', 'p2p_material_classification_id' => $this->spare->id]);
});

it('resolves a global SLOC mapping', function () {
    expect(app(P2pMaterialClassificationService::class)->resolve('B001')->is($this->raw))->toBeTrue();
});

it('prefers a company-specific mapping', function () {
    $service = app(P2pMaterialClassificationService::class);

    expect($service->resolve('B002', '1000')->is($this->spare))->toBeTrue()
        ->and($service->resolve('B002')->is($this->unclassified))->toBeTrue();
});

it('falls back to unclassified for an unknown SLOC', function () {
    expect(app(P2pMaterialClassificationService::class)->resolve('ZZZZ')->is($this->unclassified))->toBeTrue()
        ->and(app(P2pMaterialClassificationService::class)->resolve(null)->is($this->unclassified))->toBeTrue();
});

it('records a manual classification', function () {
    $line = InvoiceLine::factory()->create();
    $user = User::factory()->create();

    app(P2pMaterialClassificationService::class)->classify($line, $this->spare, $user);

    expect($line->fresh()->p2p_material_classification_id)->toBe($this->spare->id)
        ->and($line->fresh()->classified_by)->toBe($user->id)
        ->and($line->fresh()->classified_at)->not->toBeNull();
});
