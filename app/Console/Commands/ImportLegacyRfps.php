<?php

namespace App\Console\Commands;

use App\Enums\RfpRequestType;
use App\Enums\RfpStatus;
use App\Enums\RfpVendorType;
use App\Enums\RoleName;
use App\Models\Rfp\Rfp;
use App\Models\User;
use App\Services\Rfp\ApprovalEngine;
use Database\Seeders\Rfp\DueTypeSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Copies the legacy RFP Portal into Ramcar AP: users (with roles and their password
 * hashes), the master data the RFPs use, and every RFP with its items and history.
 *
 * Legacy codes become our tables and enums (see docs/09-legacy-rfp-import.md):
 * - status_no + accounting_level → RfpStatus; due_type → rfp_due_types; is_reimbursement → RfpRequestType
 * - item vendor_type 1/2 → RfpVendorType; subcategory classification_type → requires_* flags
 * - history status_id + sequence → action (+ status where it is clear)
 *
 * RFP, item and one-time vendor ids are kept, so legacy RFP 12357 is RFP-2026-012357 here.
 * Every existing RFP in this database is replaced. Safe to re-run.
 */
class ImportLegacyRfps extends Command
{
    protected $signature = 'rfp:import-legacy
        {--connection=legacy_rfp : Database connection of the legacy RFP Portal}
        {--force : Skip the confirmation}';

    protected $description = 'Import RFPs, users and master data from the legacy RFP Portal database';

    /**
     * Legacy role → our roles.
     *
     * @var array<string, list<RoleName>>
     */
    protected const array ROLE_MAP = [
        'Admin' => [RoleName::Admin],
        'Requestor' => [RoleName::Requestor],
        'Accounting Personnel' => [RoleName::ApProcessor],
        'Accounting Supervisor' => [RoleName::ApSupervisor],
        'Accounting Manager' => [RoleName::ApManager],
        'Treasury' => [RoleName::Treasury],
        'Department Head' => [RoleName::DepartmentHead],
    ];

    /**
     * Legacy approver flags on users → our matrix roles.
     *
     * @var array<string, RoleName>
     */
    protected const array FLAG_ROLE_MAP = [
        'is_manager' => RoleName::LineManager,
        'is_department' => RoleName::DepartmentHead,
        'is_vp' => RoleName::DivisionHead,
        'is_controller' => RoleName::Controller,
    ];

    protected ConnectionInterface $legacy;

    /** @var array<int, int> legacy company id → our id */
    protected array $companies = [];

    /** @var array<string, int> company code → our id */
    protected array $companyCodes = [];

    /** @var array<int, int> legacy currency id → our id */
    protected array $currencies = [];

    /** @var array<int, true> our currency ids that are PHP */
    protected array $phpCurrencies = [];

    /** @var array<int, int> */
    protected array $paymentMethods = [];

    /** @var array<int, int> legacy account group id → our id */
    protected array $accountGroups = [];

    /** @var array<string, int> G/L code → our id */
    protected array $glAccounts = [];

    /** @var array<string, int> "{our company id}|{code}" → our cost center id */
    protected array $costCenters = [];

    /** @var array<int, int> legacy category id → our id */
    protected array $categories = [];

    /** @var array<int, array{id: int, gl_account_id: int}> legacy subcategory id → ours */
    protected array $subcategories = [];

    /** @var array<int, list<string>> our category id → sub category names imported */
    protected array $subcategoryNames = [];

    /** @var array<int, int> legacy vendor row id → our vendor id */
    protected array $vendors = [];

    /** @var array<int, int> legacy user id → our id */
    protected array $users = [];

    /** @var array<string, int> due type code → id */
    protected array $dueTypes = [];

    /** @var array<int, array{company_id: int, reimbursement: bool, documents: list<string>}> imported legacy RFPs */
    protected array $rfps = [];

    /** @var array<string, int> */
    protected array $counts = [];

    /** @var array<string, int> */
    protected array $notes = [];

    public function handle(): int
    {
        if (! config('rfp.legacy_import_enabled')) {
            $this->components->error('The legacy import is locked (RFP_LEGACY_IMPORT_ENABLED=false). It replaces every RFP here, so it is off after go-live.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('This replaces every RFP in this database with the legacy RFP Portal data. Continue?')) {
            return self::FAILURE;
        }

        $this->legacy = DB::connection($this->option('connection'));

        DB::transaction(function (): void {
            $this->components->task('Companies, currencies, payment methods, account groups', fn () => $this->importLookups());
            $this->components->task('G/L accounts and cost centers', fn () => $this->importCostCenters());
            $this->components->task('Expense categories', fn () => $this->importCategories());
            $this->components->task('Vendors', fn () => $this->importVendors());
            $this->components->task('One-time vendors', fn () => $this->importOneTimeVendors());
            $this->components->task('Users and roles', fn () => $this->importUsers());
            $this->components->task('RFPs', fn () => $this->importRfps());
            $this->components->task('RFP items', fn () => $this->importItems());
            $this->components->task('RFP history', fn () => $this->importHistories());
            $this->components->task('Approval routes for RFPs in progress', fn () => $this->routeRfpsInProgress());
        });

        $this->newLine();
        $this->table(['Imported', 'Rows'], collect($this->counts)->map(fn (int $count, string $what): array => [$what, number_format($count)])->values()->all());

        if ($this->notes !== []) {
            $this->table(['Note', 'Rows'], collect($this->notes)->map(fn (int $count, string $note): array => [$note, number_format($count)])->values()->all());
        }

        return self::SUCCESS;
    }

    protected function importLookups(): void
    {
        foreach ($this->legacy->table('companies')->get() as $company) {
            $id = DB::table('companies')->where('code', $company->company_code)->value('id')
                ?? DB::table('companies')->insertGetId([
                    'code' => $company->company_code,
                    'name' => $company->name,
                    'short_name' => $company->short_name,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            $this->companies[$company->id] = $id;
            $this->companyCodes[$company->company_code] = $id;
        }

        foreach ($this->legacy->table('currencies')->get() as $currency) {
            $this->currencies[$currency->id] = DB::table('currencies')->where('code', $currency->short_code)->value('id')
                ?? DB::table('currencies')->insertGetId([
                    'code' => $currency->short_code,
                    'name' => Str::limit($currency->name, 50, ''),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($currency->short_code === 'PHP') {
                $this->phpCurrencies[$this->currencies[$currency->id]] = true;
            }
        }

        foreach ($this->legacy->table('payment_methods')->get() as $method) {
            $this->paymentMethods[$method->id] = DB::table('payment_methods')->where('country', $method->country)->where('code', $method->type)->value('id')
                ?? DB::table('payment_methods')->insertGetId([
                    'country' => $method->country,
                    'code' => $method->type,
                    'description' => $method->description,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        foreach ($this->legacy->table('account_groups')->get() as $group) {
            $this->accountGroups[$group->id] = DB::table('account_groups')->where('code', $group->account_group)->value('id')
                ?? DB::table('account_groups')->insertGetId([
                    'code' => $group->account_group,
                    'description' => $group->description,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        if (! DB::table('rfp_due_types')->exists()) {
            $this->callSilently('db:seed', ['--class' => DueTypeSeeder::class, '--force' => true]);
        }

        $this->dueTypes = DB::table('rfp_due_types')->pluck('id', 'code')->all();
    }

    /**
     * Legacy cost_centers has one row per cost center and allowed G/L account.
     */
    protected function importCostCenters(): void
    {
        $rows = $this->legacy->table('cost_centers')->get();
        $subcategoryGls = $this->legacy->table('subcategories')->get(['name', 'gl_code']);

        $glDescriptions = $rows->pluck('gl_code_description', 'gl_code')
            ->union($subcategoryGls->mapWithKeys(fn ($row): array => [$row->gl_code => $this->subcategoryName($row->name, $row->gl_code)]))
            ->filter(fn ($description, $code): bool => filled($code));

        foreach ($glDescriptions as $code => $description) {
            $this->glAccounts[(string) $code] = $this->upsertGetId('gl_accounts', ['code' => (string) $code], [
                'description' => Str::limit((string) ($description ?: $code), 150, ''),
                'is_active' => true,
            ]);
        }

        $imported = [];

        foreach ($rows->groupBy(fn ($row): string => "{$row->company_code}|{$row->cost_center}") as $glRows) {
            $first = $glRows->first();
            $companyId = $this->companyCodes[$first->company_code] ?? null;

            if ($companyId === null) {
                $this->note('Cost center rows of an unknown company (skipped)', $glRows->count());

                continue;
            }

            $id = $this->upsertGetId('cost_centers', ['company_id' => $companyId, 'code' => $first->cost_center], [
                'description' => Str::limit((string) $first->cost_center_description, 150, ''),
                'is_active' => $glRows->contains(fn ($row): bool => (bool) $row->status),
            ]);

            $this->costCenters["{$companyId}|{$first->cost_center}"] = $id;
            $imported[] = $id;

            DB::table('cost_center_gl_account')->where('cost_center_id', $id)->delete();
            DB::table('cost_center_gl_account')->insert($glRows->unique('gl_code')->map(fn ($row): array => [
                'cost_center_id' => $id,
                'gl_account_id' => $this->glAccounts[$row->gl_code],
                'expense_group' => Str::limit((string) ($row->group ?: 'Operating Expenses'), 50, ''),
                'category' => $row->category ? Str::limit($row->category, 100, '') : null,
                'account_type' => Str::limit((string) ($row->account_type ?: 'Accounts Payable'), 30, ''),
                'is_active' => (bool) $row->status,
                'created_at' => now(),
                'updated_at' => now(),
            ])->values()->all());
        }

        // Demo cost centers are deactivated, never deleted
        DB::table('cost_centers')->whereNotIn('id', $imported)->update(['is_active' => false]);

        $this->counts['Cost centers'] = count($imported);
        $this->counts['G/L accounts'] = count($this->glAccounts);
    }

    protected function importCategories(): void
    {
        foreach ($this->legacy->table('categories')->get() as $category) {
            $this->categories[$category->id] = $this->upsertGetId('rfp_categories', ['name' => $category->name], ['is_active' => true]);
        }

        foreach ($this->legacy->table('subcategories')->get() as $subcategory) {
            $categoryId = $this->categories[$subcategory->category_id] ?? null;
            $glAccountId = $this->glAccounts[$subcategory->gl_code] ?? null;

            if ($categoryId === null || $glAccountId === null) {
                $this->note('Sub categories without a category or G/L (skipped)');

                continue;
            }

            $classification = (array) json_decode((string) $subcategory->classification_type, true);
            $name = $this->subcategoryName($subcategory->name, $subcategory->gl_code);

            // Two legacy rows can share a name once the G/L suffix is dropped; keep them apart
            if (in_array($name, $this->subcategoryNames[$categoryId] ?? [], true)) {
                $name = Str::limit($subcategory->name, 150, '');
            }

            $this->subcategoryNames[$categoryId][] = $name;

            $this->subcategories[$subcategory->id] = [
                'id' => $this->upsertGetId('rfp_subcategories', ['rfp_category_id' => $categoryId, 'name' => $name], [
                    'gl_account_id' => $glAccountId,
                    'document_type' => substr((string) ($subcategory->doc_type_code ?: 'KR'), 0, 2),
                    'posting_key' => substr((string) ($subcategory->posting_gl ?: '40'), 0, 2),
                    'requires_cost_center' => in_array('Cost Center', $classification, true),
                    'requires_po_number' => in_array('PO Number', $classification, true),
                    'is_active' => true,
                ]),
                'gl_account_id' => $glAccountId,
            ];
        }

        // Demo expense categories are deactivated, never deleted
        DB::table('rfp_categories')->whereNotIn('id', $this->categories)->update(['is_active' => false]);
        DB::table('rfp_subcategories')->whereNotIn('id', Arr::pluck($this->subcategories, 'id'))->update(['is_active' => false]);

        $this->counts['Expense categories'] = count($this->categories);
        $this->counts['Sub categories'] = count($this->subcategories);
    }

    /**
     * Legacy has one vendor row per supplier and company; ours is a vendor + company_vendor.
     */
    protected function importVendors(): void
    {
        $rows = $this->legacy->table('vendors')->orderBy('id')->get();

        foreach ($rows->groupBy('supplier')->chunk(500) as $suppliers) {
            DB::table('vendors')->upsert($suppliers->map(function (Collection $vendorRows, string $code): array {
                $latest = $vendorRows->last();

                return [
                    'code' => $code,
                    'name' => Str::limit((string) $latest->name, 150, ''),
                    'country' => $latest->country_key ?: 'PH',
                    'account_group_id' => $this->accountGroups[$latest->account_group_id] ?? null,
                    'is_active' => $vendorRows->contains(fn ($row): bool => $row->deleted_at === null),
                    'created_at' => $vendorRows->first()->created_at ?? now(),
                    'updated_at' => now(),
                ];
            })->values()->all(), ['code'], ['name', 'country', 'account_group_id', 'is_active', 'updated_at']);
        }

        $ids = DB::table('vendors')->pluck('id', 'code');

        foreach ($rows->chunk(1000) as $chunk) {
            $pivot = [];

            foreach ($chunk as $row) {
                $this->vendors[$row->id] = $ids[$row->supplier];
                $companyId = $this->companyCodes[$row->company_code] ?? null;

                if ($companyId === null) {
                    continue;
                }

                $pivot["{$companyId}|{$row->supplier}"] = [
                    'company_id' => $companyId,
                    'vendor_id' => $ids[$row->supplier],
                    'reconciliation_account' => trim((string) $row->reconcilation_account),
                    'payment_terms' => filled($row->payment_terms) ? substr(trim($row->payment_terms), 0, 4) : null,
                    'payment_methods' => filled($row->payment_methods) ? trim($row->payment_methods) : null,
                    'is_active' => $row->deleted_at === null,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('company_vendor')->upsert(array_values($pivot), ['company_id', 'vendor_id'], ['reconciliation_account', 'payment_terms', 'payment_methods', 'is_active', 'updated_at']);
        }

        // Demo vendors are deactivated, never deleted (vendors with portal logins are kept)
        DB::table('vendors')
            ->whereNotIn('code', $rows->pluck('supplier')->unique()->all())
            ->whereNotExists(fn ($query) => $query->from('users')->whereColumn('users.vendor_id', 'vendors.id'))
            ->update(['is_active' => false]);

        $this->counts['Vendors'] = $rows->pluck('supplier')->unique()->count();
    }

    /**
     * Ids are kept, so items keep pointing at the same one-time vendor.
     */
    protected function importOneTimeVendors(): void
    {
        $this->legacy->table('one_time_vendors')->orderBy('id')->chunk(1000, function (Collection $chunk): void {
            DB::table('rfp_one_time_vendors')->upsert($chunk->map(fn ($vendor): array => [
                'id' => $vendor->id,
                'title' => $vendor->title,
                'name' => $vendor->name,
                'street' => $vendor->street,
                'city' => $vendor->city,
                'tin' => substr((string) $vendor->tax_number_1, 0, 15),
                'created_at' => $vendor->created_at,
                'updated_at' => $vendor->updated_at,
            ])->all(), ['id'], ['title', 'name', 'street', 'city', 'tin', 'updated_at']);
        });

        $this->counts['One-time vendors'] = $this->legacy->table('one_time_vendors')->count();
    }

    /**
     * Users are matched by email. New ones keep their legacy password hash, so they log in as before.
     */
    protected function importUsers(): void
    {
        $legacyRoles = $this->legacy->table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', 'like', '%User')
            ->get(['model_has_roles.model_id', 'roles.name'])
            ->groupBy('model_id');

        $allCompanyIds = DB::table('companies')->pluck('id')->all();

        foreach ($this->legacy->table('users')->orderBy('id')->get() as $legacyUser) {
            $email = Str::lower(trim($legacyUser->email));
            $companyId = $this->companies[$legacyUser->company_id] ?? null;

            $id = DB::table('users')->whereRaw('lower(email) = ?', [$email])->value('id')
                ?? DB::table('users')->insertGetId([
                    'name' => $legacyUser->name,
                    'email' => $email,
                    'password' => $legacyUser->password,
                    'email_verified_at' => $legacyUser->email_verified_at,
                    'company_id' => $companyId,
                    'is_active' => (bool) $legacyUser->status,
                    'created_at' => $legacyUser->created_at,
                    'updated_at' => $legacyUser->updated_at,
                    'deleted_at' => $legacyUser->deleted_at,
                ]);

            $this->users[$legacyUser->id] = $id;

            $companyIds = $legacyUser->is_all_company ? $allCompanyIds : array_filter([$companyId]);
            DB::table('company_user')->upsert(
                array_map(fn (int $company): array => ['company_id' => $company, 'user_id' => $id, 'created_at' => now(), 'updated_at' => now()], $companyIds),
                ['company_id', 'user_id'],
                ['updated_at'],
            );

            $roles = collect($legacyRoles->get($legacyUser->id, []))
                ->flatMap(function ($role): array {
                    if (! array_key_exists($role->name, self::ROLE_MAP) && $role->name !== 'Approver') {
                        $this->note("Legacy role \"{$role->name}\" has no equivalent (not assigned)");
                    }

                    return self::ROLE_MAP[$role->name] ?? [];
                })
                ->merge(collect(self::FLAG_ROLE_MAP)->filter(fn (RoleName $role, string $flag): bool => (bool) $legacyUser->{$flag})->values())
                ->unique()
                ->map(fn (RoleName $role): string => $role->value)
                ->all();

            if ($roles !== []) {
                $user = User::withTrashed()->find($id);
                $user->assignRole($roles);

                $user->syncCompanyRoles([
                    ...$user->companyRoleRows(),
                    ...collect($roles)
                        ->filter(fn (string $role): bool => in_array(RoleName::from($role), RoleName::companyScoped(), true))
                        ->map(fn (string $role): array => ['role' => $role, 'companies' => array_values($companyIds)])
                        ->all(),
                ]);
            }
        }

        $this->counts['Users'] = count($this->users);
    }

    protected function importRfps(): void
    {
        DB::table('rfps')->delete(); // items and history cascade

        $submittedAt = $this->legacy->table('rfp_histories')->groupBy('rfp_id')->selectRaw('rfp_id, min(created_at) as first_at')->pluck('first_at', 'rfp_id');

        $this->legacy->table('rfps')->orderBy('id')->chunk(500, function (Collection $chunk) use ($submittedAt): void {
            $rows = [];

            foreach ($chunk as $rfp) {
                $requestorId = $this->users[$rfp->submitted_by] ?? null;
                $companyId = $this->companies[$rfp->company_id] ?? null;

                if ($requestorId === null || $companyId === null) {
                    $this->note('RFPs without a known requestor or company (skipped)');

                    continue;
                }

                $status = $this->status((int) $rfp->status_no, (int) $rfp->accounting_level);
                $isReimbursement = (bool) $rfp->is_reimbursement;

                $this->rfps[$rfp->id] = [
                    'company_id' => $companyId,
                    'reimbursement' => $isReimbursement,
                    'documents' => $this->paths($rfp->supporting_documents),
                ];

                if ($rfp->receiver_id !== null && ! isset($this->vendors[$rfp->receiver_id])) {
                    $this->note('RFPs whose payee vendor is missing (payee left empty)');
                }

                $rows[] = [
                    'id' => $rfp->id,
                    'rfp_number' => sprintf('RFP-%s-%06d', substr((string) $rfp->created_at, 0, 4), $rfp->id),
                    'company_id' => $companyId,
                    'requestor_id' => $requestorId,
                    'request_type' => ($isReimbursement ? RfpRequestType::Reimbursement : RfpRequestType::Standard)->value,
                    'rfp_due_type_id' => $this->dueTypes[Str::lower((string) $rfp->due_type)] ?? null,
                    'account_group_id' => $this->accountGroups[$rfp->account_group_id] ?? null,
                    'vendor_id' => $this->vendors[$rfp->receiver_id] ?? null,
                    'total_php_amount' => $rfp->total_php_amount ?? 0,
                    'payment_method_id' => $this->paymentMethods[$rfp->payment_method_id] ?? null,
                    'due_date' => $rfp->due_date,
                    'purpose' => (string) $rfp->purpose,
                    'is_direct_to_vp' => (bool) $rfp->direct_to_vp,
                    'is_pre_approved' => (bool) $rfp->pre_approved,
                    'is_ids' => (bool) ($rfp->ids_type ?? true),
                    'pre_approval_documents' => ($documents = $this->paths($rfp->pre_approval_documents)) === [] ? null : json_encode($documents),
                    'status' => $status->value,
                    ...$this->currentApprover($rfp, $status),
                    'submitted_at' => $status === RfpStatus::Draft ? null : ($submittedAt[$rfp->id] ?? $rfp->created_at),
                    'created_at' => $rfp->created_at,
                    'updated_at' => $rfp->updated_at,
                ];
            }

            DB::table('rfps')->insert($rows);
        });

        $this->counts['RFPs'] = count($this->rfps);
    }

    protected function importItems(): void
    {
        $sort = [];
        $imported = 0;

        $this->legacy->table('rfp_items')->orderBy('id')->chunk(1000, function (Collection $chunk) use (&$sort, &$imported): void {
            $rows = [];

            foreach ($chunk as $item) {
                $rfp = $this->rfps[$item->rfp_id] ?? null;

                if ($rfp === null) {
                    $this->note('Items of an RFP that was not imported (skipped)');

                    continue;
                }

                $subcategory = $this->subcategories[$item->subcategory_id] ?? $this->placeholderSubcategory((int) $item->subcategory_id, $item->category_id);
                [$costCenterId, $poNumber] = $this->chargeTo($rfp['company_id'], $item->cost_center_id, $item->po_number);

                $currencyId = $this->currencies[$item->currency_id] ?? null;
                $isPhp = isset($this->phpCurrencies[$currencyId]);
                $phpAmount = $item->total_php_amount ?? 0;

                $documents = $this->paths($item->supporting_documents);
                $sort[$item->rfp_id] = ($sort[$item->rfp_id] ?? 0) + 1;

                // Pre-2025-11 RFPs kept the documents on the header; they go to the first item
                if ($sort[$item->rfp_id] === 1) {
                    $documents = array_values(array_unique([...$documents, ...$rfp['documents']]));
                }

                $vendorType = match ((int) $item->vendor_type) {
                    1 => RfpVendorType::Vendor,
                    2 => RfpVendorType::OneTimeVendor,
                    default => null,
                };
                $keepPaidTo = $rfp['reimbursement'] && $vendorType !== null;

                $rows[] = [
                    'id' => $item->id,
                    'rfp_id' => $item->rfp_id,
                    'sort' => $sort[$item->rfp_id],
                    'company_id' => $keepPaidTo ? ($this->companies[$item->company_id] ?? null) : null,
                    'vendor_type' => $keepPaidTo ? $vendorType->value : null,
                    'vendor_id' => $keepPaidTo && $vendorType === RfpVendorType::Vendor ? ($this->vendors[$item->vendor_id] ?? null) : null,
                    'rfp_one_time_vendor_id' => $keepPaidTo && $vendorType === RfpVendorType::OneTimeVendor ? $item->one_time_vendor_id : null,
                    'rfp_subcategory_id' => $subcategory['id'],
                    'gl_account_id' => $subcategory['gl_account_id'],
                    'cost_center_id' => $costCenterId,
                    'invoice_number' => $item->invoice_ref,
                    'invoice_date' => $item->invoice_date,
                    'po_number' => $poNumber,
                    'description' => $item->remarks,
                    'currency_id' => $currencyId,
                    'amount' => $isPhp ? $phpAmount : ($item->amount ?? $phpAmount),
                    'exchange_rate' => $isPhp ? 1 : ($item->exchange_rate ?? 0),
                    'php_amount' => $phpAmount,
                    'attachments' => $documents === [] ? null : json_encode($documents),
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ];
            }

            DB::table('rfp_items')->insert($rows);
            $imported += count($rows);
        });

        $this->counts['RFP items'] = $imported;
    }

    protected function importHistories(): void
    {
        $imported = 0;

        $this->legacy->table('rfp_histories')->orderBy('id')->chunk(2000, function (Collection $chunk) use (&$imported): void {
            $rows = [];

            foreach ($chunk as $history) {
                if (! isset($this->rfps[$history->rfp_id])) {
                    $this->note('History of an RFP that was not imported (skipped)');

                    continue;
                }

                [$action, $status] = $this->historyAction((int) $history->status_id, (int) $history->sequence);

                $rows[] = [
                    'rfp_id' => $history->rfp_id,
                    'user_id' => $this->users[$history->user_id] ?? null,
                    'action' => $action,
                    'from_status' => null,
                    'to_status' => $status?->value,
                    'remarks' => $history->remarks,
                    'created_at' => $history->created_at ?? now(),
                ];
            }

            DB::table('rfp_histories')->insert($rows);
            $imported += count($rows);
        });

        $this->counts['RFP history rows'] = $imported;
    }

    /**
     * Legacy status_no (+ accounting_level for "For Accounting Processing") → RfpStatus.
     * Pending is the Accounting Personnel's first review (legacy "Endorse / Revise");
     * Processing is with the business approvers (Manager → Dept Head → VP → Controller).
     */
    public static function status(int $statusNo, int $accountingLevel = 0): RfpStatus
    {
        return match ($statusNo) {
            0 => RfpStatus::Draft,
            1 => RfpStatus::ForApReview,
            2 => RfpStatus::ForApproval,
            11 => match ($accountingLevel) {
                2, 3 => RfpStatus::ForAccountingApproval, // Accounting Supervisor / Manager
                4 => RfpStatus::ForTreasury,
                default => RfpStatus::ForApProcessing, // Accounting Personnel
            },
            12 => RfpStatus::Rejected,
            13 => RfpStatus::ForRevision,
            14 => RfpStatus::Cancelled,
            15, 21 => RfpStatus::Completed, // 21 = SAP Posted
            22 => RfpStatus::ForApProcessing, // SAP reprocess
            default => RfpStatus::Draft,
        };
    }

    /**
     * Who the RFP waits for, from legacy sequence (business approvers) or accounting_level.
     *
     * @return array{current_approver_role: ?string, current_approver_id: ?int}
     */
    protected function currentApprover(object $rfp, RfpStatus $status): array
    {
        $sequence = (int) $rfp->sequence;

        $role = match ($status) {
            RfpStatus::ForApproval => [1 => RoleName::LineManager, 2 => RoleName::DepartmentHead, 3 => RoleName::DivisionHead, 4 => RoleName::Controller][$sequence] ?? null,
            RfpStatus::ForApReview, RfpStatus::ForApProcessing => RoleName::ApProcessor,
            RfpStatus::ForAccountingApproval => (int) $rfp->accounting_level === 3 ? RoleName::ApManager : RoleName::ApSupervisor,
            RfpStatus::ForTreasury => RoleName::Treasury,
            default => null,
        };

        $legacyPerson = $role === null ? null : ($rfp->current_approver_id ?? match ($status) {
            RfpStatus::ForApproval => [1 => $rfp->manager_id, 2 => $rfp->department_id, 3 => $rfp->vp_id, 4 => $rfp->controller_id][$sequence] ?? null,
            default => null,
        });

        return [
            'current_approver_role' => $role?->value,
            'current_approver_id' => $this->users[$legacyPerson] ?? null,
        ];
    }

    /**
     * Legacy history status_id (+ sequence) → our action label and, where clear, status.
     *
     * @return array{0: string, 1: ?RfpStatus}
     */
    public static function historyAction(int $statusId, int $sequence): array
    {
        return match ($statusId) {
            0, 2 => ['Rejected', RfpStatus::Rejected],
            1 => [match ($sequence) {
                0 => 'Endorsed',
                5 => 'Processed',
                default => 'Approved',
            }, null],
            3 => ['Returned', RfpStatus::ForRevision],
            5 => ['Tax codes processed', null],
            14 => ['Cancelled', RfpStatus::Cancelled],
            15 => ['Completed', RfpStatus::Completed],
            21 => ['Updated', null],
            default => ["Legacy action {$statusId}", null],
        };
    }

    /**
     * Legacy items keep the cost center code; some older ones hold the PO number there.
     *
     * @return array{0: ?int, 1: ?string}
     */
    protected function chargeTo(int $companyId, mixed $code, ?string $poNumber): array
    {
        $code = trim((string) $code);

        if ($code === '' || $code === '0') {
            return [null, $poNumber];
        }

        if (strlen($code) === 10 && str_starts_with($code, '4') && blank($poNumber)) {
            $this->note('Items whose "cost center" was a PO number (moved to PO no.)');

            return [null, $code];
        }

        if (! isset($this->costCenters["{$companyId}|{$code}"])) {
            $this->note('Cost centers used by items but missing from legacy master (added inactive)');

            $this->costCenters["{$companyId}|{$code}"] = $this->upsertGetId('cost_centers', ['company_id' => $companyId, 'code' => Str::limit($code, 10, '')], [
                'description' => "Legacy cost center {$code}",
                'is_active' => false,
            ]);
        }

        return [$this->costCenters["{$companyId}|{$code}"], $poNumber];
    }

    /**
     * Some items point at a sub category deleted from legacy; keep them on an inactive stand-in.
     *
     * @return array{id: int, gl_account_id: int}
     */
    protected function placeholderSubcategory(int $legacyId, mixed $legacyCategoryId): array
    {
        $this->note('Items whose sub category was deleted in legacy (kept on an inactive stand-in)');

        $glAccountId = $this->glAccounts['0000000'] ??= $this->upsertGetId('gl_accounts', ['code' => '0000000'], [
            'description' => 'Unknown (legacy)',
            'is_active' => false,
        ]);

        $categoryId = $this->categories[$legacyCategoryId] ?? ($this->categories[0] ??= $this->upsertGetId('rfp_categories', ['name' => 'Legacy (unmapped)'], ['is_active' => false]));

        return $this->subcategories[$legacyId] = [
            'id' => $this->upsertGetId('rfp_subcategories', ['rfp_category_id' => $categoryId, 'name' => "Legacy sub category #{$legacyId}"], [
                'gl_account_id' => $glAccountId,
                'document_type' => 'KR',
                'posting_key' => '40',
                'requires_cost_center' => false,
                'requires_po_number' => false,
                'is_active' => false,
            ]),
            'gl_account_id' => $glAccountId,
        ];
    }

    /**
     * Drop the " - 6101130" G/L suffix legacy appends to sub category names.
     */
    protected function subcategoryName(string $name, ?string $glCode): string
    {
        return Str::limit(trim((string) preg_replace('/\s*-\s*'.preg_quote((string) $glCode, '/').'\s*$/', '', $name)) ?: $name, 150, '');
    }

    /**
     * File paths from a legacy JSON column: a list, or an object of uuid → path.
     *
     * @return list<string>
     */
    protected function paths(?string $json): array
    {
        $decoded = json_decode((string) $json, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    /**
     * @param  array<string, mixed>  $keys
     * @param  array<string, mixed>  $values
     */
    protected function upsertGetId(string $table, array $keys, array $values): int
    {
        $id = DB::table($table)->where($keys)->value('id');

        if ($id !== null) {
            DB::table($table)->where('id', $id)->update($values + ['updated_at' => now()]);

            return $id;
        }

        return DB::table($table)->insertGetId($keys + $values + ['created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Give every imported RFP still in approval its route, so it can be worked here.
     */
    protected function routeRfpsInProgress(): void
    {
        $engine = app(ApprovalEngine::class);
        $routed = 0;

        Rfp::query()
            ->whereNotIn('status', [RfpStatus::Draft, RfpStatus::ForRevision, ...RfpStatus::final()])
            ->lazyById()
            ->each(function (Rfp $rfp) use ($engine, &$routed): void {
                $problem = $engine->adopt($rfp);

                if ($problem === null) {
                    $routed++;
                } else {
                    $this->note("Not routed (still visible, no actions): {$problem}");
                }
            });

        $this->counts['Approval routes'] = $routed;
    }

    protected function note(string $note, int $count = 1): void
    {
        $this->notes[$note] = ($this->notes[$note] ?? 0) + $count;
    }
}
