<?php

namespace Modules\MplEInvoice\Services\Asp;

use Modules\MplEInvoice\Models\EInvoiceDocument;

/**
 * Contract implemented by every Accredited Service Provider integration.
 *
 * Bind a different implementation in the container to plug a specific ASP
 * (e.g. one with a proprietary authentication flow) without touching the
 * document lifecycle handled by EInvoiceService.
 */
interface AspClient
{
    /**
     * Transmit the document XML to the ASP. Implementations must not throw on
     * transport or validation errors: they report them through the result.
     */
    public function submit( EInvoiceDocument $document ): AspSubmissionResult;
}
