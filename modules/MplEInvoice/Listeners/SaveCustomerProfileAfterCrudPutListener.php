<?php

namespace Modules\MplEInvoice\Listeners;

use App\Events\CrudAfterPutEvent;
use Modules\MplEInvoice\Filters\CustomerCrudFilter;

/**
 * Persists the buyer tax details submitted while editing a customer.
 */
class SaveCustomerProfileAfterCrudPutListener
{
    public function handle( CrudAfterPutEvent $event ): void
    {
        CustomerCrudFilter::saveProfile( $event->resource, $event->inputs, $event->entry );
    }
}
