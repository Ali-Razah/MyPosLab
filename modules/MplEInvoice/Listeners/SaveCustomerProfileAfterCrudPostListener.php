<?php

namespace Modules\MplEInvoice\Listeners;

use App\Events\CrudAfterPostEvent;
use Modules\MplEInvoice\Filters\CustomerCrudFilter;

/**
 * Persists the buyer tax details submitted with a new customer.
 */
class SaveCustomerProfileAfterCrudPostListener
{
    public function handle( CrudAfterPostEvent $event ): void
    {
        CustomerCrudFilter::saveProfile( $event->resource, $event->inputs, $event->entry );
    }
}
