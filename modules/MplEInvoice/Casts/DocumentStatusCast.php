<?php

namespace Modules\MplEInvoice\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Modules\MplEInvoice\Services\EInvoiceService;

/**
 * Displays the localized label of an e-invoice submission status.
 */
class DocumentStatusCast implements CastsAttributes
{
    public function get( $model, string $key, $value, array $attributes )
    {
        return EInvoiceService::statusLabel( (string) $value );
    }

    public function set( $model, string $key, $value, array $attributes )
    {
        return $value;
    }
}
