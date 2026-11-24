<?php

namespace Modules\MplBranches\Tests\Feature;

use App\Classes\Hook;
use Modules\MplBranches\Tests\MplBranchesTestCase;

/**
 * A core store reset truncates orders and registers. Their branch links must be
 * wiped too, while the branches themselves and staff assignments are kept.
 */
final class StoreResetTest extends MplBranchesTestCase
{
    public function test_order_and_register_links_are_wiped_on_store_reset(): void
    {
        $tables = Hook::filter( 'ns-wipeable-tables', [] );

        $this->assertContains( 'mpl_br_branch_orders', $tables );
        $this->assertContains( 'mpl_br_branch_register', $tables );
        $this->assertNotContains( 'mpl_br_branches', $tables );
        $this->assertNotContains( 'mpl_br_branch_user', $tables );
    }
}
