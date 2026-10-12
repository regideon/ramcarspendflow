<?php

use App\Console\Commands\ImportLegacyRfps;
use App\Enums\RfpRequestType;
use App\Enums\RfpStatus;
use App\Enums\RfpVendorType;
use App\Enums\RoleName;
use App\Models\CostCenter;
use App\Models\Rfp\History;
use App\Models\Rfp\Item;
use App\Models\Rfp\Rfp;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\AccountGroupSeeder;
use Database\Seeders\CompanySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\Rfp\DueTypeSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([CompanySeeder::class, CurrencySeeder::class, AccountGroupSeeder::class, PaymentMethodSeeder::class, RoleAndUserSeeder::class, DueTypeSeeder::class]);

    config(['database.connections.legacy_rfp' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]);
    DB::purge('legacy_rfp');

    createLegacySchema();
    seedLegacyData();
});

function createLegacySchema(): void
{
    $schema = Schema::connection('legacy_rfp');

    $tables = [
        'companies' => ['company_code', 'name', 'short_name'],
        'currencies' => ['short_code', 'name'],
        'payment_methods' => ['country', 'type', 'description'],
        'account_groups' => ['account_group', 'description'],
        'categories' => ['name'],
        'subcategories' => ['category_id', 'name', 'gl_code', 'doc_type_code', 'posting_gl', 'classification_type'],
        'cost_centers' => ['company_code', 'group', 'cost_center', 'cost_center_description', 'gl_code', 'gl_code_description', 'category', 'account_type', 'status'],
        'vendors' => ['supplier', 'company_code', 'country_key', 'account_group_id', 'name', 'reconcilation_account', 'payment_methods', 'payment_terms', 'created_at', 'deleted_at'],
        'one_time_vendors' => ['title', 'name', 'street', 'city', 'tax_number_1', 'created_at', 'updated_at'],
        'users' => ['name', 'email', 'password', 'email_verified_at', 'company_id', 'status', 'is_all_company', 'is_manager', 'is_department', 'is_vp', 'is_controller', 'created_at', 'updated_at', 'deleted_at'],
        'roles' => ['name'],
        'model_has_roles' => ['role_id', 'model_type', 'model_id'],
        'rfps' => ['company_id', 'account_group_id', 'receiver_id', 'due_type', 'is_reimbursement', 'payment_method_id', 'due_date', 'purpose', 'total_php_amount', 'supporting_documents', 'submitted_by', 'status_no', 'accounting_level', 'sequence', 'current_approver_id', 'manager_id', 'department_id', 'vp_id', 'controller_id', 'direct_to_vp', 'pre_approved', 'pre_approval_documents', 'created_at', 'updated_at'],
        'rfp_items' => ['rfp_id', 'invoice_ref', 'category_id', 'subcategory_id', 'company_id', 'vendor_type', 'vendor_id', 'one_time_vendor_id', 'cost_center_id', 'po_number', 'currency_id', 'exchange_rate', 'amount', 'total_php_amount', 'remarks', 'invoice_date', 'supporting_documents', 'created_at', 'updated_at'],
        'rfp_histories' => ['rfp_id', 'user_id', 'sequence', 'status_id', 'remarks', 'created_at'],
    ];

    foreach ($tables as $name => $columns) {
        $schema->create($name, function (Blueprint $table) use ($columns, $name): void {
            if ($name !== 'model_has_roles') {
                $table->id();
            }

            foreach ($columns as $column) {
                $table->text($column)->nullable();
            }
        });
    }
}

function seedLegacyData(): void
{
    $legacy = DB::connection('legacy_rfp');
    $at = '2026-10-01 09:00:00';

    $legacy->table('companies')->insert(['id' => 2, 'company_code' => '1000', 'name' => 'Philippine Batteries Inc.', 'short_name' => 'PBI']);
    $legacy->table('currencies')->insert([['id' => 128, 'short_code' => 'PHP', 'name' => 'Philippine Peso'], ['id' => 150, 'short_code' => 'USD', 'name' => 'US Dollar']]);
    $legacy->table('payment_methods')->insert(['id' => 8, 'country' => 'PH', 'type' => 'T', 'description' => 'Bank Transfer']);
    $legacy->table('account_groups')->insert(['id' => 1, 'account_group' => '0001', 'description' => 'Vendor']);
    $legacy->table('categories')->insert([['id' => 15, 'name' => 'Supplies'], ['id' => 43, 'name' => 'Advances to Supplier']]);
    $legacy->table('subcategories')->insert([
        ['id' => 145, 'category_id' => 15, 'name' => 'Factory Supplies - 5021010', 'gl_code' => '5021010', 'doc_type_code' => 'KR', 'posting_gl' => '40', 'classification_type' => '["Cost Center"]'],
        ['id' => 198, 'category_id' => 43, 'name' => 'Supplier Down Payment - 1151010', 'gl_code' => '1151010', 'doc_type_code' => 'KR', 'posting_gl' => '29', 'classification_type' => '["PO Number"]'],
    ]);
    $legacy->table('cost_centers')->insert(['company_code' => '1000', 'group' => 'Manufacturing Expenses', 'cost_center' => '103100', 'cost_center_description' => 'PLANT OPERATIONS', 'gl_code' => '5021010', 'gl_code_description' => 'Factory Supplies', 'category' => 'Supplies', 'account_type' => 'Accounts Payable', 'status' => 1]);
    $legacy->table('vendors')->insert(['id' => 6284, 'supplier' => '203923', 'company_code' => '1000', 'country_key' => 'PH', 'account_group_id' => '1', 'name' => 'Real Vendor Corp.', 'reconcilation_account' => '2000000', 'payment_methods' => 'CT', 'payment_terms' => ' V60N', 'created_at' => $at]);
    $legacy->table('one_time_vendors')->insert(['id' => 1907, 'name' => 'Juan Cruz', 'tax_number_1' => '123456789000', 'created_at' => $at, 'updated_at' => $at]);
    $legacy->table('users')->insert([
        ['id' => 70, 'name' => 'Legacy Requestor', 'email' => 'Legacy.Requestor@motolite.com', 'password' => '$2y$12$legacyhashlegacyhashlegacyhashlegacyhashlegacyhashleg', 'company_id' => 2, 'status' => 1, 'is_all_company' => 0, 'is_manager' => 1, 'is_department' => 0, 'is_vp' => 0, 'is_controller' => 0, 'created_at' => $at, 'updated_at' => $at],
        ['id' => 71, 'name' => 'Owner', 'email' => RoleAndUserSeeder::OWNER_EMAIL, 'password' => 'x', 'company_id' => 2, 'status' => 1, 'is_all_company' => 1, 'is_manager' => 0, 'is_department' => 0, 'is_vp' => 0, 'is_controller' => 0, 'created_at' => $at, 'updated_at' => $at],
    ]);
    $legacy->table('roles')->insert([['id' => 1, 'name' => 'Requestor'], ['id' => 2, 'name' => 'Accounting Supervisor']]);
    $legacy->table('model_has_roles')->insert([['role_id' => 1, 'model_type' => 'App\Models\User', 'model_id' => 70], ['role_id' => 2, 'model_type' => 'App\Models\User', 'model_id' => 71]]);

    $legacy->table('rfps')->insert([
        ['id' => 12120, 'company_id' => 2, 'account_group_id' => 1, 'receiver_id' => 6284, 'due_type' => 'Urgent', 'is_reimbursement' => 1, 'payment_method_id' => 8, 'due_date' => '2026-10-02', 'purpose' => 'Plant supplies', 'total_php_amount' => '6325.00', 'supporting_documents' => '["header-receipt.pdf"]', 'submitted_by' => 70, 'status_no' => 11, 'accounting_level' => 2, 'direct_to_vp' => 0, 'pre_approved' => 0, 'pre_approval_documents' => null, 'created_at' => $at, 'updated_at' => $at],
        ['id' => 12121, 'company_id' => 2, 'account_group_id' => 1, 'receiver_id' => 6284, 'due_type' => 'Standard', 'is_reimbursement' => 0, 'payment_method_id' => 8, 'due_date' => '2026-10-09', 'purpose' => 'Rejected one', 'total_php_amount' => '100.00', 'supporting_documents' => null, 'submitted_by' => 70, 'status_no' => 12, 'accounting_level' => 0, 'direct_to_vp' => 0, 'pre_approved' => 0, 'pre_approval_documents' => null, 'created_at' => $at, 'updated_at' => $at],
    ]);
    $legacy->table('rfps')->insert(['id' => 12122, 'company_id' => 2, 'account_group_id' => 1, 'receiver_id' => 6284, 'due_type' => 'Standard', 'is_reimbursement' => 1, 'payment_method_id' => 8, 'purpose' => 'With the line manager', 'total_php_amount' => '10.00', 'submitted_by' => 70, 'status_no' => 2, 'accounting_level' => 0, 'sequence' => 1, 'manager_id' => 71, 'direct_to_vp' => 0, 'pre_approved' => 0, 'created_at' => $at, 'updated_at' => $at]);
    $legacy->table('rfp_items')->insert([
        ['id' => 34510, 'rfp_id' => 12120, 'invoice_ref' => 'INV-1', 'category_id' => 15, 'subcategory_id' => 145, 'company_id' => 2, 'vendor_type' => 2, 'vendor_id' => null, 'one_time_vendor_id' => 1907, 'cost_center_id' => '103100', 'po_number' => null, 'currency_id' => 128, 'exchange_rate' => '1.00', 'amount' => null, 'total_php_amount' => '525.00', 'remarks' => 'Gloves', 'invoice_date' => '2026-09-30', 'supporting_documents' => '{"a1":"uploads/rfps/12120/gloves.pdf"}', 'created_at' => $at, 'updated_at' => $at],
        ['id' => 34511, 'rfp_id' => 12120, 'invoice_ref' => 'INV-2', 'category_id' => 43, 'subcategory_id' => 198, 'company_id' => null, 'vendor_type' => null, 'vendor_id' => null, 'one_time_vendor_id' => null, 'cost_center_id' => '4100329357', 'po_number' => null, 'currency_id' => 150, 'exchange_rate' => '58.00', 'amount' => '100.00', 'total_php_amount' => '5800.00', 'remarks' => 'Down payment', 'invoice_date' => '2026-09-30', 'supporting_documents' => null, 'created_at' => $at, 'updated_at' => $at],
        ['id' => 34512, 'rfp_id' => 12121, 'invoice_ref' => 'INV-3', 'category_id' => 15, 'subcategory_id' => 145, 'company_id' => null, 'vendor_type' => null, 'vendor_id' => null, 'one_time_vendor_id' => null, 'cost_center_id' => '190134', 'po_number' => null, 'currency_id' => 128, 'exchange_rate' => '1.00', 'amount' => null, 'total_php_amount' => '100.00', 'remarks' => 'Old cost center', 'invoice_date' => '2026-09-30', 'supporting_documents' => null, 'created_at' => $at, 'updated_at' => $at],
    ]);
    $legacy->table('rfp_histories')->insert([
        ['rfp_id' => 12120, 'user_id' => 71, 'sequence' => 1, 'status_id' => 1, 'remarks' => null, 'created_at' => '2026-10-01 10:00:00'],
        ['rfp_id' => 12120, 'user_id' => 71, 'sequence' => 61, 'status_id' => 5, 'remarks' => null, 'created_at' => '2026-10-01 11:00:00'],
        ['rfp_id' => 12121, 'user_id' => 71, 'sequence' => 2, 'status_id' => 2, 'remarks' => 'Price difference', 'created_at' => '2026-10-01 12:00:00'],
    ]);
}

it('imports legacy RFPs with their ids, statuses and lookups mapped to our tables', function () {
    $this->artisan('rfp:import-legacy', ['--force' => true])->assertSuccessful();

    $rfp = Rfp::with('items')->find(12120);

    expect($rfp)
        ->rfp_number->toBe('RFP-2026-012120')
        ->status->toBe(RfpStatus::ForAccountingApproval)
        ->request_type->toBe(RfpRequestType::Reimbursement)
        ->and($rfp->dueType->code)->toBe('urgent')
        ->and($rfp->vendor->code)->toBe('203923')
        ->and($rfp->requestor->email)->toBe('legacy.requestor@motolite.com')
        ->and($rfp->total_php_amount)->toBe('6325.00')
        ->and($rfp->submitted_at->toDateTimeString())->toBe('2026-10-01 10:00:00')
        ->and(Rfp::find(12121)->status)->toBe(RfpStatus::Rejected);
});

it('sets who each imported RFP is waiting for', function () {
    $this->artisan('rfp:import-legacy', ['--force' => true])->assertSuccessful();

    $owner = User::firstWhere('email', RoleAndUserSeeder::OWNER_EMAIL);

    expect(Rfp::find(12122))
        ->status->toBe(RfpStatus::ForApproval)
        ->current_approver_role->toBe(RoleName::LineManager)
        ->current_approver_id->toBe($owner->id)
        ->and(Rfp::find(12120))
        ->current_approver_role->toBe(RoleName::ApSupervisor)
        ->current_approver_id->toBeNull()
        ->and(Rfp::find(12121)->current_approver_role)->toBeNull()
        ->and(Rfp::query()->awaiting($owner)->pluck('id')->all())->toContain(12122, 12120);
});

it('maps items: cost centers by code, PO numbers kept in the cost center field, currencies and documents', function () {
    $this->artisan('rfp:import-legacy', ['--force' => true])->assertSuccessful();

    [$gloves, $downPayment] = Item::query()->where('rfp_id', 12120)->orderBy('sort')->get()->all();

    expect($gloves)
        ->id->toBe(34510)
        ->amount->toBe('525.00')->php_amount->toBe('525.00')
        ->vendor_type->toBe(RfpVendorType::OneTimeVendor)
        ->rfp_one_time_vendor_id->toBe(1907)
        ->attachments->toBe(['uploads/rfps/12120/gloves.pdf', 'header-receipt.pdf'])
        ->and($gloves->costCenter->code)->toBe('103100')
        ->and($gloves->subcategory)->name->toBe('Factory Supplies')->requires_cost_center->toBeTrue()
        ->and($downPayment)
        ->cost_center_id->toBeNull()
        ->po_number->toBe('4100329357')
        ->amount->toBe('100.00')->exchange_rate->toBe('58.000000')->php_amount->toBe('5800.00')
        ->vendor_type->toBeNull()
        ->and($downPayment->currency->code)->toBe('USD')
        ->and(Item::find(34512)->costCenter)->code->toBe('190134')->is_active->toBeFalse();
});

it('keeps legacy history as actions', function () {
    $this->artisan('rfp:import-legacy', ['--force' => true])->assertSuccessful();

    expect(History::query()->where('rfp_id', 12120)->oldest('created_at')->pluck('action')->all())->toBe(['Approved', 'Tax codes processed'])
        ->and(History::firstWhere('rfp_id', 12121))
        ->action->toBe('Rejected')->to_status->toBe(RfpStatus::Rejected)->remarks->toBe('Price difference');
});

it('imports users with their password hash and mapped roles, matching existing ones by email', function () {
    $usersBefore = User::count();

    $this->artisan('rfp:import-legacy', ['--force' => true])->assertSuccessful();

    $requestor = User::firstWhere('email', 'legacy.requestor@motolite.com');

    expect(User::count())->toBe($usersBefore + 1)
        ->and($requestor->password)->toBe('$2y$12$legacyhashlegacyhashlegacyhashlegacyhashlegacyhashleg')
        ->and($requestor->getRoleNames()->sort()->values()->all())->toBe([RoleName::LineManager->value, RoleName::Requestor->value])
        ->and(User::firstWhere('email', RoleAndUserSeeder::OWNER_EMAIL)->hasRole(RoleName::ApSupervisor))->toBeTrue()
        ->and(Vendor::firstWhere('code', '203923')->companies->first()->pivot->payment_terms)->toBe('V60N');
});

it('can be run again without duplicating anything', function () {
    $this->artisan('rfp:import-legacy', ['--force' => true])->assertSuccessful();
    $counts = fn (): array => [Rfp::count(), Item::count(), History::count(), User::count(), Vendor::count(), CostCenter::count()];
    $first = $counts();

    $this->artisan('rfp:import-legacy', ['--force' => true])->assertSuccessful();

    expect($counts())->toBe($first);
});

it('maps legacy statuses and history codes', function (int $statusNo, int $level, RfpStatus $status) {
    expect(ImportLegacyRfps::status($statusNo, $level))->toBe($status);
})->with([
    'draft' => [0, 0, RfpStatus::Draft],
    'pending' => [1, 0, RfpStatus::ForApproval],
    'processing' => [2, 0, RfpStatus::ForApproval],
    'accounting personnel' => [11, 1, RfpStatus::ForApProcessing],
    'accounting supervisor' => [11, 2, RfpStatus::ForAccountingApproval],
    'accounting manager' => [11, 3, RfpStatus::ForAccountingApproval],
    'rejected' => [12, 0, RfpStatus::Rejected],
    'returned' => [13, 0, RfpStatus::ForRevision],
    'cancelled' => [14, 0, RfpStatus::Cancelled],
    'completed' => [15, 0, RfpStatus::Completed],
    'SAP posted' => [21, 0, RfpStatus::Completed],
]);
