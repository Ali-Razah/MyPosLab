<?php

namespace Modules\MplBatches\Models;

use App\Models\NsModel;
use App\Models\Procurement;
use App\Models\ProcurementProduct;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int         $id
 * @property int         $product_id
 * @property int         $unit_id
 * @property int|null    $procurement_id
 * @property int|null    $procurement_product_id
 * @property string      $batch_number
 * @property string|null $expiry_date            stored as Y-m-d (no date cast so SQL string comparisons stay exact)
 * @property float       $quantity_received
 * @property float       $quantity_remaining
 * @property float       $cost
 * @property string      $status
 * @property int|null    $author_id
 */
class Batch extends NsModel
{
    const STATUS_ACTIVE = 'active';

    const STATUS_DEPLETED = 'depleted';

    const STATUS_EXPIRED = 'expired';

    const STATUS_WRITTEN_OFF = 'written_off';

    protected $table = 'mpl_batch_batches';

    protected $casts = [
        'quantity_received' => 'float',
        'quantity_remaining' => 'float',
        'cost' => 'float',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo( Product::class, 'product_id' );
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo( Unit::class, 'unit_id' );
    }

    public function procurement(): BelongsTo
    {
        return $this->belongsTo( Procurement::class, 'procurement_id' );
    }

    public function procurementProduct(): BelongsTo
    {
        return $this->belongsTo( ProcurementProduct::class, 'procurement_product_id' );
    }

    public function allocations(): HasMany
    {
        return $this->hasMany( BatchAllocation::class, 'batch_id' );
    }

    public function scopeForStock( $query, int $productId, int $unitId )
    {
        return $query->where( 'product_id', $productId )->where( 'unit_id', $unitId );
    }

    public function scopeWithStock( $query )
    {
        return $query->where( 'quantity_remaining', '>', 0 );
    }

    /**
     * Batches that can be sold without restriction: active and not past expiry.
     */
    public function scopeSellable( $query, string $today )
    {
        return $query->where( 'status', self::STATUS_ACTIVE )
            ->where( fn( $query ) => $query->whereNull( 'expiry_date' )->orWhere( 'expiry_date', '>', $today ) );
    }

    /**
     * Batches holding stock that is past its expiry date.
     */
    public function scopeExpiredStock( $query, string $today )
    {
        return $query->whereIn( 'status', [ self::STATUS_ACTIVE, self::STATUS_EXPIRED ] )
            ->whereNotNull( 'expiry_date' )
            ->where( 'expiry_date', '<=', $today );
    }

    /**
     * First Expired First Out: earliest expiry first, batches without expiry last.
     */
    public function scopeFefo( $query )
    {
        return $query->orderByRaw( 'CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END' )
            ->orderBy( 'expiry_date' )
            ->orderBy( 'id' );
    }

    public function isExpiredOn( string $today ): bool
    {
        return ! empty( $this->expiry_date ) && substr( (string) $this->expiry_date, 0, 10 ) <= $today;
    }
}
