<?php

namespace Database\Seeders\P2p;

use App\Models\Company;
use Illuminate\Database\Seeder;

/**
 * The SAP company codes (BUKRS) used by P2P, taken from `p2p_prod_clone.companies`.
 * Shared with RFP through the top-level `companies` table; the four RFP companies
 * keep their short names (set by CompanySeeder). Re-runnable.
 */
class P2pCompanySeeder extends Seeder
{
    public function run(): void
    {
        $companies = [
            '0002' => 'Ramcar Inc',
            '0003' => 'Foreign Batt Holding Co.',
            '0004' => 'Ramcar Holdings Inc.',
            '0005' => 'Ramcar Batteries Int. Ltd',
            '0006' => 'Gardien Ins. Agcy. Inc.',
            '1000' => 'Philippine Batteries Inc.',
            '1100' => 'Ramcar Batteries Inc.',
            '2000' => 'Oriental & Motolite Mktg',
            '2010' => 'Oriental Yuasa Battery Co',
            '2200' => 'Supercharge Batteries',
            '2201' => 'Eaglefield',
            '2202' => 'Lightbase Batteries Pty L',
            '2203' => 'Eaglefield Batteries Pty',
            '2204' => 'Enirgi Power Storage PL',
            '2205' => 'Enirgi Power Storage NZ',
            '2206' => 'Enirgi Power Storage Recy',
            '2207' => 'Lion Batteries Holdings',
            '2230' => 'Supercharge New Zealand',
            '2250' => 'Marshall Power Australia',
            '2270' => 'Marshall Power Limited',
            '2650' => 'Enirgi Power Storage Recy',
            '3000' => 'Philippine Recyclers Inc.',
            '3300' => 'MNA Metal Resources Sdn B',
            '4000' => 'Ramcar Technology Inc.',
            '4010' => 'Marchant Stainless Steel',
            '4020' => 'Greener Solar Power & EM',
            '4030' => 'Subic Super Yacht Haven',
            '4040' => 'Power Accumulator Complia',
            '5000' => 'Evergreen Envtl Resources',
            '5500' => 'Yokohama Reclamation Sdn.',
            '6800' => 'Sta. Maria Industrial Par',
            '8800' => 'Century Motolite Battery',
            '9000' => 'Integrated Enterprise Sol',
            '9010' => 'Worldwide Technology Serv',
            '9020' => 'Ramcar Plastics Co. Inc.',
            '9800' => 'Alur Hijau Sdn. Bhd.',
            '9900' => 'Good Function Sdn. Bhd.',
            'AU01' => 'Country Template AU',
            'BE01' => 'Country Template BE',
            'CA01' => 'Country Template CA',
            'GB01' => 'Country Template GB',
            'HK01' => 'Country Template HK',
            'ID01' => 'Country Template ID',
            'IE01' => 'Country Template IE',
            'IN01' => 'India Model Company, IN',
            'MY01' => 'Country Template MY',
            'NZ01' => 'Country Template NZ',
            'PH01' => 'Country Template PH',
            'SG01' => 'SAP Asia',
            'US01' => 'Country Template US',
            'ZA01' => 'Country Template ZA',
        ];

        foreach ($companies as $code => $name) {
            Company::query()->updateOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
        }
    }
}
