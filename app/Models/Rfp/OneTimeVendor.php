<?php

namespace App\Models\Rfp;

use Database\Factories\Rfp\OneTimeVendorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Payee that is not in the SAP vendor master (paid through the one-time vendor account).
 */
#[Table('rfp_one_time_vendors')]
#[Fillable(['title', 'name', 'street', 'city', 'tin'])]
class OneTimeVendor extends Model
{
    /** @use HasFactory<OneTimeVendorFactory> */
    use HasFactory;
}
