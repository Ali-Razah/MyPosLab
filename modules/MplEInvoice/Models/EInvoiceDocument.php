<?php

namespace Modules\MplEInvoice\Models;

use App\Models\Customer;
use App\Models\NsModel;
use App\Models\Order;
use App\Models\OrderRefund;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A UAE tax invoice / credit note generated from a NexoPOS order or refund.
 *
 * @property int         $id
 * @property string      $source_key
 * @property int         $order_id
 * @property int|null    $order_refund_id
 * @property int|null    $original_document_id
 * @property int|null    $customer_id
 * @property string      $type
 * @property string      $series
 * @property int         $sequence_number
 * @property string      $number
 * @property string      $uuid
 * @property string      $issued_at
 * @property string      $currency
 * @property float       $total_net
 * @property float       $total_vat
 * @property float       $total_gross
 * @property string|null $buyer_name
 * @property string|null $buyer_trn
 * @property string      $xml
 * @property array|null  $summary
 * @property string      $status
 * @property int         $attempts
 * @property string|null $asp_reference
 * @property string|null $response_payload
 * @property string|null $submitted_at
 * @property string|null $error
 * @property int|null    $author_id
 */
class EInvoiceDocument extends NsModel
{
    const TYPE_TAX_INVOICE = 'tax_invoice';

    const TYPE_SIMPLIFIED = 'simplified';

    const TYPE_CREDIT_NOTE = 'credit_note';

    const STATUS_GENERATED = 'generated';

    const STATUS_SUBMITTED = 'submitted';

    const STATUS_ACCEPTED = 'accepted';

    const STATUS_REJECTED = 'rejected';

    const STATUS_FAILED = 'failed';

    protected $table = 'mpl_einv_documents';

    protected $casts = [
        'summary' => 'array',
        'total_net' => 'float',
        'total_vat' => 'float',
        'total_gross' => 'float',
        'attempts' => 'integer',
        'sequence_number' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo( Order::class, 'order_id' );
    }

    public function orderRefund(): BelongsTo
    {
        return $this->belongsTo( OrderRefund::class, 'order_refund_id' );
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo( Customer::class, 'customer_id' );
    }

    public function originalDocument(): BelongsTo
    {
        return $this->belongsTo( EInvoiceDocument::class, 'original_document_id' );
    }

    /**
     * Whether the document can be (re)submitted to the ASP.
     */
    public function isSubmittable(): bool
    {
        return in_array( $this->status, [ self::STATUS_GENERATED, self::STATUS_FAILED, self::STATUS_REJECTED ], true );
    }

    public function isCreditNote(): bool
    {
        return $this->type === self::TYPE_CREDIT_NOTE;
    }
}
