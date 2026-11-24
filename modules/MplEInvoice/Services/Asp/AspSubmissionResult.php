<?php

namespace Modules\MplEInvoice\Services\Asp;

use Modules\MplEInvoice\Models\EInvoiceDocument;

/**
 * Outcome of a submission attempt to an Accredited Service Provider.
 */
class AspSubmissionResult
{
    public function __construct(
        public string $status,
        public ?string $reference = null,
        public ?string $payload = null,
        public ?string $error = null,
    ) {}

    public static function accepted( ?string $reference, ?string $payload ): self
    {
        return new self( EInvoiceDocument::STATUS_ACCEPTED, $reference, $payload );
    }

    public static function submitted( ?string $reference, ?string $payload ): self
    {
        return new self( EInvoiceDocument::STATUS_SUBMITTED, $reference, $payload );
    }

    public static function rejected( ?string $reference, ?string $payload, string $error ): self
    {
        return new self( EInvoiceDocument::STATUS_REJECTED, $reference, $payload, $error );
    }

    public static function failed( ?string $payload, string $error ): self
    {
        return new self( EInvoiceDocument::STATUS_FAILED, null, $payload, $error );
    }

    public function isSuccessful(): bool
    {
        return in_array( $this->status, [ EInvoiceDocument::STATUS_SUBMITTED, EInvoiceDocument::STATUS_ACCEPTED ], true );
    }
}
