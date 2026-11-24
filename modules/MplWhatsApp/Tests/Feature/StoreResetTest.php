<?php

namespace Modules\MplWhatsApp\Tests\Feature;

use App\Classes\Hook;
use Modules\MplWhatsApp\Tests\MplWhatsAppTestCase;

/**
 * A core store reset truncates orders; the message log points at order ids
 * and must be wiped with them, otherwise new orders inherit old history.
 */
final class StoreResetTest extends MplWhatsAppTestCase
{
    public function test_message_log_is_wiped_on_store_reset(): void
    {
        $this->assertContains( 'mpl_wa_messages', Hook::filter( 'ns-wipeable-tables', [] ) );
    }
}
