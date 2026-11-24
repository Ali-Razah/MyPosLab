<?php

namespace Modules\MplEInvoice\Tests\Feature;

use App\Classes\Hook;
use Modules\MplEInvoice\Tests\EInvoiceTestCase;

/**
 * A core store reset truncates orders. E-invoice documents reference order ids
 * and must be wiped too; the number sequence must survive so issued numbers
 * are never reused.
 */
final class StoreResetTest extends EInvoiceTestCase
{
    public function test_documents_are_wiped_but_sequences_kept_on_store_reset(): void
    {
        $tables = Hook::filter( 'ns-wipeable-tables', [] );

        $this->assertContains( 'mpl_einv_documents', $tables );
        $this->assertNotContains( 'mpl_einv_sequences', $tables );
        $this->assertNotContains( 'mpl_einv_customer_profiles', $tables );
    }
}
