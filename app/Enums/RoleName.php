<?php

namespace App\Enums;

/**
 * Every Spatie role the app relies on. The value is the role's `name` in the `roles` table.
 *
 * Replaces the legacy names: P2P `processor`/`checker`/`supplier` and RFP
 * `Accounting Personnel`/`Accounting Supervisor`/`Accounting Manager`/`Approver`.
 */
enum RoleName: string
{
    // System
    case Admin = 'Admin';

    // Requesters
    case Requestor = 'Requestor';
    case Vendor = 'Vendor';

    // Accounting (shared by RFP and P2P)
    case ApProcessor = 'AP Processor';
    case ApSupervisor = 'AP Supervisor';
    case ApManager = 'AP Manager';
    case Treasury = 'Treasury';

    // RFP business approvers (Step 3 approval matrix)
    case LineManager = 'Line Manager';
    case DepartmentHead = 'Department Head';
    case DivisionHead = 'Division Head';
    case Controller = 'Controller';
    case BuHead = 'BU Head';
    case BuFpa = 'BU FP&A';
    case FpaHead = 'FP&A Head';
    case EnterpriseController = 'Enterprise Controller';
    case President = 'President';
    case ExcomChairman = 'ExCom / Chairman';

    // P2P escalation handlers
    case Procurement = 'Procurement';
    case Warehouse = 'Warehouse';
    case WarehouseRawMaterials = 'Warehouse - Raw Materials';
    case WarehouseSpareParts = 'Warehouse - Spare Parts';

    /**
     * Accounting roles that work the RFP after (and before) the business approvers.
     *
     * @return list<self>
     */
    public static function rfpAccounting(): array
    {
        return [self::ApProcessor, self::ApSupervisor, self::ApManager, self::Treasury];
    }

    /**
     * Roles that use the RFP Approvals page.
     *
     * @return list<self>
     */
    public static function rfpApprovalsUsers(): array
    {
        return [self::Admin, ...self::rfpAccounting(), ...self::rfpApprovers()];
    }

    /**
     * Roles that may appear as a step in the RFP approval matrix.
     *
     * @return list<self>
     */
    public static function rfpApprovers(): array
    {
        return [
            self::LineManager,
            self::DepartmentHead,
            self::DivisionHead,
            self::Controller,
            self::BuHead,
            self::BuFpa,
            self::FpaHead,
            self::EnterpriseController,
            self::President,
            self::ExcomChairman,
        ];
    }
}
