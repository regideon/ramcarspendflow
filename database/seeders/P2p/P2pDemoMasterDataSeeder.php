<?php

namespace Database\Seeders\P2p;

use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Models\P2p\EmailNotificationReceiver;
use App\Models\P2p\EscalationReason;
use App\Models\P2p\EscalationReasonStep;
use App\Models\P2p\MaterialClassification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Generic P2P master data for development, UAT and the mancom demo: material
 * classifications, escalation routes and Points of Contact. Items, delivery types,
 * companies, vendors and SLOC come from the SAP extracts (P2pItemSeeder,
 * P2pDeliveryTypeSeeder, P2pCompanySeeder, P2pVendorSeeder, P2pSlocMasterSeeder).
 * Safe to re-run (upserts by code). See docs/p2p/03-master-data.md.
 */
class P2pDemoMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('P2pDemoMasterDataSeeder creates generic demo data and must not run in production.');
        }

        DB::transaction(function (): void {
            $this->seedMaterialClassifications();
            $this->seedEscalationReasons();
            $this->seedPointsOfContact();
        });
    }

    protected function seedMaterialClassifications(): void
    {
        $classifications = [
            'raw_material' => ['Raw Materials', 'info', RoleName::WarehouseRawMaterials->value, 1],
            'spare_part' => ['Spare Parts', 'warning', RoleName::WarehouseSpareParts->value, 2],
            'unclassified' => ['Unclassified', 'gray', null, 99],
        ];

        foreach ($classifications as $code => [$name, $color, $roleName, $sort]) {
            MaterialClassification::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'color' => $color, 'role_name' => $roleName, 'sort' => $sort, 'is_active' => true],
            );
        }

        // The SLOC master itself is imported from T001L by P2pSlocMasterSeeder.
    }

    /**
     * @see docs/p2p/03-master-data.md §3.3
     */
    protected function seedEscalationReasons(): void
    {
        $reasons = [
            [
                'code' => 'quantity_difference',
                'name' => 'Quantity Difference',
                'origin_role' => 'vendor',
                'sort' => 1,
                'steps' => [
                    ['warehouse', P2pStatus::EscalatedToWarehouse, null, null],
                    ['vendor', P2pStatus::ReturnedToVendor, null, null],
                ],
            ],
            [
                'code' => 'incomplete_gr',
                'name' => 'Incomplete GR',
                'origin_role' => 'vendor',
                'sort' => 2,
                'steps' => [
                    ['warehouse', P2pStatus::EscalatedToWarehouse, null, null],
                    ['vendor', P2pStatus::ReturnedToVendor, null, null],
                ],
            ],
            [
                'code' => 'wrong_item_description',
                'name' => 'Wrong item description',
                'origin_role' => 'vendor',
                'sort' => 3,
                'steps' => [
                    ['procurement', P2pStatus::EscalateToProcurement, null, null],
                    ['vendor', P2pStatus::ReturnedToVendor, null, null],
                ],
            ],
            [
                'code' => 'wrong_unit_of_measure',
                'name' => 'Wrong unit of measure',
                'origin_role' => 'vendor',
                'sort' => 4,
                'steps' => [
                    ['procurement', P2pStatus::EscalateToProcurement, null, null],
                    ['vendor', P2pStatus::ReturnedToVendor, null, null],
                ],
            ],
            [
                'code' => 'price_difference',
                'name' => 'Price difference',
                'origin_role' => 'processor',
                'sort' => 5,
                'steps' => [
                    ['procurement', P2pStatus::EscalateToProcurement, null, null],
                    ['warehouse', P2pStatus::EscalatedToWarehouse, 'Reverse & re-GR', EscalationReasonStep::ACTION_REVERSE_REGR],
                    ['procurement', P2pStatus::EscalateToProcurement, null, null],
                    ['vendor', P2pStatus::ReturnedToVendor, null, null],
                ],
            ],
            [
                'code' => 'other',
                'name' => 'Other',
                'origin_role' => EscalationReason::ORIGIN_ANY,
                'sort' => 6,
                'steps' => [
                    ['procurement', P2pStatus::EscalateToProcurement, null, null],
                    ['warehouse', P2pStatus::EscalatedToWarehouse, null, null],
                    [EscalationReasonStep::ROLE_ORIGIN, null, null, null],
                ],
            ],
            [
                'code' => 'document_concern',
                'name' => 'Document concern / Wrong info',
                'origin_role' => 'processor',
                'sort' => 7,
                'is_active' => false, // retired: use Return to Vendor
                'steps' => [
                    ['vendor', P2pStatus::ReturnedToVendor, null, null],
                ],
            ],
        ];

        foreach ($reasons as $data) {
            $reason = EscalationReason::updateOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'origin_role' => $data['origin_role'],
                    'is_active' => $data['is_active'] ?? true,
                    'sort' => $data['sort'],
                ],
            );

            $reason->steps()->delete();

            $last = count($data['steps']) - 1;

            foreach ($data['steps'] as $index => [$role, $status, $actionLabel, $action]) {
                $reason->steps()->create([
                    'step_order' => $index + 1,
                    'role' => $role,
                    'status_id' => $status?->value,
                    'action_label' => $actionLabel,
                    'action' => $action,
                    'override_header_status' => $index === $last,
                ]);
            }
        }
    }

    protected function seedPointsOfContact(): void
    {
        $contacts = [
            ['ap.head@ramcar.com', 'AP Head', 'overdue_warning'],
            ['procurement.head@ramcar.com', 'Procurement Head', 'overdue_warning'],
            ['warehouse.head@ramcar.com', 'Warehouse Head', 'overdue_warning'],
        ];

        foreach ($contacts as [$email, $name, $type]) {
            EmailNotificationReceiver::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'notification_type' => $type, 'is_active' => true],
            );
        }
    }
}
