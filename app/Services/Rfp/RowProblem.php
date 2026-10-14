<?php

namespace App\Services\Rfp;

use RuntimeException;

/**
 * A problem with one row of an uploaded RFP items sheet, shown to the requestor.
 */
class RowProblem extends RuntimeException {}
