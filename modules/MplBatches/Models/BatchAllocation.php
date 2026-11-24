<?php

namespace Modules\MplBatches\Models;

use App\Models\NsModel;
use App\Models\Order;
use App\Models\OrderProduct;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int         $id
 * @property int|null    $batch_id           null when the quantity was not covered by any batch
 * @property int         $product_id
 * @property int         $unit_id
 * @property int|null    $order_id
 * @property int|null    $order_product_id
 * @property int|null    $product_history_id
 * @property float       $quantity
 * @property string      $type
 * @property string|null $note
 * @property int|null    $author_id
 */
class BatchAllocation extends NsModel
{
    const TYPE_SALE = 'sale';

    const TYPE_REFUND = 'refund';

    const TYPE_ADJUSTMENT = 'adjustment';

    protected $table = 'mpl_batch_allocations';

    protected $casts = [
        'quantity' => 'float',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo( Batch::class, 'batch_id' );
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo( Order::class, 'order_id' );
    }

    public function orderProduct(): BelongsTo
    {
        return $this->belongsTo( OrderProduct::class, 'order_product_id' );
    }
}
