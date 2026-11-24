<?php

namespace Modules\MplBatches\Tests\Feature;

use App\Classes\Hook;
use Modules\MplBatches\Tests\MplBatchesTestCase;

/**
 * A core store reset truncates products, procurements and orders. Batch rows
 * reference those ids and must be wiped too, otherwise re-used ids would
 * attach stale batches to new products.
 */
final class StoreResetTest extends MplBatchesTestCase
{
    public function test_batch_tables_are_wiped_on_store_reset(): void
    {
        $tables = Hook::filter( 'ns-wipeable-tables', [] );

        foreach ( [ 'mpl_batch_batches', 'mpl_batch_allocations', 'mpl_batch_procurement_numbers' ] as $table ) {
            $this->assertContains( $table, $tables );
        }
    }
}
