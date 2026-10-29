<?php

use App\Services\P2p\P2pNumberService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Links a P2P request to the RFP it settles (e.g. a down-payment RFP) and adds
 * the generated P2P number (P2P-YYYY-########).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('p2p_invoices', function (Blueprint $table) {
            $table->string('p2p_number')->nullable()->unique()->after('code');
            $table->foreignId('rfp_id')->nullable()->after('p2p_number')->constrained('rfps')->nullOnDelete();
        });

        $this->backfillNumbers();
    }

    public function down(): void
    {
        Schema::table('p2p_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rfp_id');
            $table->dropUnique(['p2p_number']);
            $table->dropColumn('p2p_number');
        });
    }

    /**
     * Existing requests get the number they would have been given on creation.
     */
    private function backfillNumbers(): void
    {
        $numbers = app(P2pNumberService::class);

        DB::table('p2p_invoices')
            ->select(['id', 'grc_number', 'created_at'])
            ->orderBy('id')
            ->chunkById(500, function ($invoices) use ($numbers): void {
                foreach ($invoices as $invoice) {
                    $sequence = filled($invoice->grc_number)
                        ? (int) preg_replace('/\D+/', '', $invoice->grc_number)
                        : (int) $invoice->id;

                    DB::table('p2p_invoices')->where('id', $invoice->id)->update([
                        'p2p_number' => $numbers->forStoredInvoice($sequence, $invoice->created_at),
                    ]);
                }
            });
    }
};
