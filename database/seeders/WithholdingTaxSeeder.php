<?php

namespace Database\Seeders;

use App\Models\WithholdingTaxCode;
use App\Models\WithholdingTaxType;
use Illuminate\Database\Seeder;

class WithholdingTaxSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Types and codes carried over from the RFP portal. The legacy codes were never linked
     * to a type, so withholding_tax_type_id stays null until Tax confirms the mapping.
     */
    public function run(): void
    {
        $types = [
            '01' => 'Withholding Tax for Customers',
            '02' => 'Withholding Tax for Vendors',
            'CI' => 'Exp. Withholding Tax (Upon Customer Invoice)',
            'CP' => 'Exp. Withholding Tax (Customer Payment)',
            'EI' => 'Expanded Withholding Tax (Invoice)',
            'EP' => 'Expanded Withholding Tax (Payment)',
            'EV' => 'Expanded VAT on Sale to Govt Entity',
            'EX' => 'Payees Exempt from Withholding Tax',
            'FI' => 'Final Tax (Invoice)',
            'FP' => 'Final Tax (Payment)',
        ];

        foreach ($types as $code => $name) {
            WithholdingTaxType::updateOrCreate(['code' => $code], ['name' => $name]);
        }

        // [code, BIR ATC, description, rate %]
        $codes = [
            ['00', null, 'EWT Not Applicable', 0.00],
            ['01', 'WC010', 'Prof/talent fees paid GI<720', 10.00],
            ['02', 'WC100', 'Rentals (corporation)', 5.00],
            ['03', 'WC110', 'cinematogr film rentals (corp)', 5.00],
            ['04', 'WC120', 'prime /sub-contractors (corp)', 2.00],
            ['07', 'WC156', 'credit card comp payments', 0.50],
            ['08', 'WC640', "paym by gov't to supp of Goods", 1.00],
            ['09', 'WC158', 'Paid by Top Corp to supp-Goods', 1.00],
            ['19', 'WI100', 'rentals(individual)', 5.00],
            ['20', 'WI110', 'cinematogr. film rentals (ind)', 5.00],
            ['21', 'WI120', 'prime /sub-contractors (ind)', 2.00],
            ['22', 'WI130', 'Beneficiaries of Estate/Trust', 15.00],
            ['23', 'WI140', 'Comm/Brokers Fee if GI >3M', 10.00],
            ['25', 'WI152', 'Paym to Partners of GPP <720K', 10.00],
            ['26', 'WI156', 'credit card comp payments', 0.50],
            ['27', 'WI640', "paym by gov't to supp of Goods", 1.00],
            ['28', 'WC160', 'Paid by Top Corp - Services', 2.00],
            ['29', 'WI159', "paym to gov't personnel from i", 15.00],
            ['30', 'WC011', "Prof'l/talent fees paid GI>720", 15.00],
            ['40', 'WI153', 'Paym to Partners of GPP >720K', 15.00],
            ['41', 'WI160', 'Paid by Top Ind - Services', 2.00],
            ['42', 'WI158', 'Paid by Top Ind - Goods', 1.00],
            ['43', 'WC140', 'Comm/Brokers Fee if GI >720K', 15.00],
            ['44', 'WI010', "Prof'l/talent fees paid GI<3M", 5.00],
            ['45', 'WI020', "prof'l entertainers GI<3M", 5.00],
            ['46', 'WI030', "prof'l athletes GI<3M", 5.00],
            ['47', 'WI040', 'movie,stage,mus director GI<3M', 5.00],
            ['48', 'WI050', 'mngt and techn consultants <3M', 5.00],
            ['49', 'WI060', 'Bus & Bookkeeping agents GI<3M', 5.00],
            ['50', 'WI070', 'Insurance agents GI < 3M', 5.00],
            ['51', 'WI080', 'Other talent fees GI<3M', 5.00],
            ['52', 'WI090', 'Directors fees-not Emp GI <3M', 5.00],
            ['53', 'WI011', "Prof'l/talent fees paid GI>3M", 10.00],
            ['54', 'WI021', "prof'l entertainers GI >3M", 10.00],
            ['55', 'WI031', "prof'l athletes GI>3M", 10.00],
            ['56', 'WI041', 'movie,stage,mus director GI>3M', 10.00],
            ['57', 'WI051', 'mngt and techn consultants>3M', 10.00],
            ['58', 'WI061', 'Bus & Bookkeeping agents GI>3M', 10.00],
            ['59', 'WI071', 'Insurance agents GI > 3M', 10.00],
            ['60', 'WI081', 'Other talent fees GI>3M', 10.00],
            ['61', 'WI091', 'Directors fees-not Emp GI >3M', 10.00],
            ['62', 'WI139', 'Comm/Brokers Fee if GI <3M', 5.00],
            ['63', 'WC139', 'Comm/Brokers Fee if GI <720K', 10.00],
            ['64', 'WI151', 'Prof fees-medical prac GI <3M', 5.00],
            ['65', 'WI150', 'Prof fees-medical prac GI >3M', 10.00],
            ['66', 'WC151', 'Prof fees-med prac GI<720K', 10.00],
            ['67', 'WC150', 'Prof fees-med prac GI>720K', 15.00],
            ['68', 'WC020', "prof'l entertainers GI<720K", 10.00],
            ['69', 'WC021', "prof'l entertainers GI>720K", 15.00],
            ['70', 'WC030', "prof'l athletes GI<720K", 10.00],
            ['71', 'WC031', "prof'l athletes GI>720K", 15.00],
            ['72', 'WC040', 'movie,stage director GI<720K', 10.00],
            ['73', 'WC041', 'movie,stage director GI>720K', 15.00],
            ['74', 'WC050', 'mngt & tech consultants <720K', 10.00],
            ['75', 'WC051', 'mngt & tech consultants >720K', 15.00],
            ['76', 'WC060', 'Bus/Bookkeeping agents GI<720K', 10.00],
            ['77', 'WC061', 'Bus/Bookkeeping agents GI>720K', 15.00],
            ['78', 'WC070', 'Insurance agents GI <720K', 10.00],
            ['79', 'WC071', 'Insurance agents GI >720K', 15.00],
            ['80', 'WC080', 'Other talent fees GI <720K', 10.00],
            ['81', 'WC081', 'Other talent fees GI >720K', 15.00],
            ['82', 'WI157', "paid by gov't to Services", 2.00],
            ['83', 'WC157', "paid by gov't to Services", 2.00],
            ['84', 'WI515', 'Comm/discount-Sales Rep GI <3M', 5.00],
            ['85', 'WI516', 'Comm/discount-SalesRep GI >3M', 10.00],
            ['86', 'WC515', 'Comm/disc-Sales Rep GI<720K', 10.00],
            ['87', 'WC516', 'Comm/disc-Sales Rep GI>720K', 15.00],
            ['88', 'WI540', 'Tolling fees paid to refineries', 5.00],
            ['89', 'WC540', 'Tolling fees paid to refineries', 5.00],
            ['91', null, 'Final WHT - VAT on Digital Services NRSP', 0.00],
        ];

        foreach ($codes as [$code, $atc, $description, $rate]) {
            WithholdingTaxCode::updateOrCreate(
                ['withholding_tax_type_id' => null, 'code' => $code],
                ['atc' => $atc, 'description' => $description, 'rate' => $rate],
            );
        }
    }
}
