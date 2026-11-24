<?php

namespace Modules\MplBatches\Services;

use App\Exceptions\NotAllowedException;
use App\Models\OrderProduct;
use App\Models\ProcurementProduct;
use App\Models\Product;
use App\Models\ProductHistory;
use App\Models\ProductSubItem;
use App\Models\ProductUnitQuantity;
use App\Models\Unit;
use App\Services\ProductService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\MplBatches\Models\Batch;
use Modules\MplBatches\Models\BatchAllocation;
use Modules\MplBatches\Models\ProcurementBatchNumber;

class BatchService
{
    const OPTION_ENABLED = 'mplbatches_enabled';

    const OPTION_ALERT_DAYS = 'mplbatches_alert_days';

    const OPTION_CRITICAL_DAYS = 'mplbatches_critical_days';

    const OPTION_BLOCK_EXPIRED = 'mplbatches_block_expired';

    const OPTION_LAST_ALERT = 'mplbatches_last_alert_date';

    const BLOCK_FOLLOW_PRODUCT = 'product';

    const BLOCK_ALWAYS = 'always';

    const BLOCK_NEVER = 'never';

    /**
     * Quantity below this threshold is considered zero (float noise).
     */
    const EPSILON = 0.00001;

    /**
     * When the module writes off a batch, the stock adjustment it triggers
     * must be taken from that exact batch rather than through FEFO.
     */
    private ?int $writeOffBatchId = null;

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    */

    public function isEnabled(): bool
    {
        return ns()->option->get( self::OPTION_ENABLED, 'yes' ) !== 'no';
    }

    public function getAlertDays(): int
    {
        return max( 1, (int) ns()->option->get( self::OPTION_ALERT_DAYS, 30 ) );
    }

    public function getCriticalDays(): int
    {
        return max( 0, (int) ns()->option->get( self::OPTION_CRITICAL_DAYS, 7 ) );
    }

    public function getBlockMode(): string
    {
        $mode = ns()->option->get( self::OPTION_BLOCK_EXPIRED, self::BLOCK_FOLLOW_PRODUCT );

        return in_array( $mode, [ self::BLOCK_FOLLOW_PRODUCT, self::BLOCK_ALWAYS, self::BLOCK_NEVER ], true )
            ? $mode
            : self::BLOCK_FOLLOW_PRODUCT;
    }

    public function today(): string
    {
        return now( ns()->date->getTimezone() )->toDateString();
    }

    /**
     * Whether expired batch stock of this product may not be sold.
     * "product" mode mirrors core: the product must expire and use "prevent_sales".
     */
    public function blocksExpiredSales( Product $product ): bool
    {
        return match ( $this->getBlockMode() ) {
            self::BLOCK_ALWAYS => true,
            self::BLOCK_NEVER => false,
            default => (bool) $product->expires && $product->on_expiration === Product::EXPIRES_PREVENT_SALES,
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Batch creation
    |--------------------------------------------------------------------------
    */

    public function defaultBatchNumber( ProcurementProduct $procurementProduct ): string
    {
        return 'B' . $procurementProduct->procurement_id . '-' . $procurementProduct->id;
    }

    /**
     * Remember the batch number typed on the procurement screen.
     */
    public function rememberProcurementBatchNumber( ProcurementProduct $procurementProduct, ?string $batchNumber ): void
    {
        $batchNumber = trim( (string) $batchNumber );

        $record = ProcurementBatchNumber::where( 'procurement_product_id', $procurementProduct->id )->first();

        if ( $batchNumber === '' ) {
            $record?->delete();

            return;
        }

        if ( ! $record instanceof ProcurementBatchNumber ) {
            $record = new ProcurementBatchNumber;
            $record->procurement_product_id = $procurementProduct->id;
        }

        $record->batch_number = mb_substr( $batchNumber, 0, 190 );
        $record->save();

        /**
         * If the line is already stocked we update the batch right away.
         */
        Batch::where( 'procurement_product_id', $procurementProduct->id )
            ->get()
            ->each( function ( Batch $batch ) use ( $record ) {
                $batch->batch_number = $record->batch_number;
                $batch->save();
            } );
    }

    public function getProcurementBatchNumber( int $procurementProductId ): ?string
    {
        $batch = Batch::where( 'procurement_product_id', $procurementProductId )->orderBy( 'id' )->first();

        if ( $batch instanceof Batch ) {
            return $batch->batch_number;
        }

        return ProcurementBatchNumber::where( 'procurement_product_id', $procurementProductId )->value( 'batch_number' );
    }

    /**
     * Creates the batch for a procurement line that has just been stocked.
     */
    public function createFromStockedHistory( ProductHistory $history ): ?Batch
    {
        $procurementProduct = ProcurementProduct::find( $history->procurement_product_id );

        if ( ! $procurementProduct instanceof ProcurementProduct ) {
            return null;
        }

        $existing = Batch::where( 'procurement_product_id', $procurementProduct->id )
            ->where( 'unit_id', $history->unit_id )
            ->first();

        if ( $existing instanceof Batch ) {
            return $existing;
        }

        $batchNumber = ProcurementBatchNumber::where( 'procurement_product_id', $procurementProduct->id )->value( 'batch_number' )
            ?: $this->defaultBatchNumber( $procurementProduct );

        return $this->createBatch(
            productId: $history->product_id,
            unitId: $history->unit_id,
            quantity: (float) $history->quantity,
            cost: (float) $procurementProduct->purchase_price,
            batchNumber: $batchNumber,
            expiryDate: $procurementProduct->expiration_date ? substr( (string) $procurementProduct->expiration_date, 0, 10 ) : null,
            procurementId: $procurementProduct->procurement_id,
            procurementProductId: $procurementProduct->id,
        );
    }

    /**
     * When a stocked procurement line is converted to another unit, the
     * converted quantity keeps the batch number and expiry of its source.
     */
    public function createFromConversion( ProductHistory $history ): ?Batch
    {
        $source = Batch::where( 'procurement_product_id', $history->procurement_product_id )
            ->where( 'unit_id', '<>', $history->unit_id )
            ->orderBy( 'id' )
            ->first();

        if ( ! $source instanceof Batch ) {
            return null;
        }

        $existing = Batch::where( 'procurement_product_id', $history->procurement_product_id )
            ->where( 'unit_id', $history->unit_id )
            ->first();

        if ( $existing instanceof Batch ) {
            $existing->quantity_received = $existing->quantity_received + (float) $history->quantity;
            $existing->quantity_remaining = $existing->quantity_remaining + (float) $history->quantity;
            $this->refreshStatus( $existing );
            $existing->save();

            return $existing;
        }

        return $this->createBatch(
            productId: $history->product_id,
            unitId: $history->unit_id,
            quantity: (float) $history->quantity,
            cost: (float) $history->unit_price,
            batchNumber: $source->batch_number,
            expiryDate: $source->expiry_date,
            procurementId: $source->procurement_id,
            procurementProductId: $source->procurement_product_id,
        );
    }

    public function createBatch(
        int $productId,
        int $unitId,
        float $quantity,
        float $cost,
        string $batchNumber,
        ?string $expiryDate,
        ?int $procurementId = null,
        ?int $procurementProductId = null,
    ): Batch {
        $batch = new Batch;
        $batch->product_id = $productId;
        $batch->unit_id = $unitId;
        $batch->procurement_id = $procurementId;
        $batch->procurement_product_id = $procurementProductId;
        $batch->batch_number = $batchNumber;
        $batch->expiry_date = $expiryDate ? substr( $expiryDate, 0, 10 ) : null;
        $batch->quantity_received = $quantity;
        $batch->quantity_remaining = $quantity;
        $batch->cost = $cost;
        $batch->status = Batch::STATUS_ACTIVE;
        $batch->author_id = Auth::id();
        $this->refreshStatus( $batch );
        $batch->save();

        return $batch;
    }

    /**
     * Status is derived from quantity and expiry, except write-offs which are final.
     */
    public function refreshStatus( Batch $batch ): Batch
    {
        /**
         * Final only while empty: stock returned to it later (refund of a sale
         * made before the write-off) must be tracked again.
         */
        if ( $batch->status === Batch::STATUS_WRITTEN_OFF && $batch->quantity_remaining <= self::EPSILON ) {
            return $batch;
        }

        if ( $batch->quantity_remaining <= self::EPSILON ) {
            $batch->quantity_remaining = 0;
            $batch->status = Batch::STATUS_DEPLETED;
        } elseif ( $batch->isExpiredOn( $this->today() ) ) {
            $batch->status = Batch::STATUS_EXPIRED;
        } else {
            $batch->status = Batch::STATUS_ACTIVE;
        }

        return $batch;
    }

    /*
    |--------------------------------------------------------------------------
    | Stock movements
    |--------------------------------------------------------------------------
    */

    /**
     * Entry point for every core stock adjustment (sales, returns, adjustments).
     */
    public function handleStockAdjustment( ProductHistory $history ): void
    {
        $product = Product::find( $history->product_id );

        if ( ! $product instanceof Product || $product->type === Product::TYPE_GROUPED ) {
            return;
        }

        $action = $history->operation_type;
        $quantity = abs( (float) $history->quantity );

        if ( $quantity <= self::EPSILON ) {
            return;
        }

        /**
         * Idempotency: a product history is mirrored at most once, even if the
         * core event is dispatched again for it.
         */
        if ( ! empty( $history->id ) && BatchAllocation::where( 'product_history_id', $history->id )->exists() ) {
            return;
        }

        $isReduce = in_array( $action, ProductHistory::STOCK_REDUCE, true );
        $isIncrease = in_array( $action, ProductHistory::STOCK_INCREASE, true );

        if ( ! empty( $history->order_product_id ) ) {
            if ( in_array( $action, [ ProductHistory::ACTION_SOLD, ProductHistory::ACTION_ADJUSTMENT_SALE ], true ) ) {
                $this->allocateFefo(
                    history: $history,
                    product: $product,
                    quantity: $quantity,
                    type: BatchAllocation::TYPE_SALE,
                );

                return;
            }

            if ( $isIncrease ) {
                $this->restoreForOrderProduct( $history, $quantity );

                return;
            }

            if ( $isReduce ) {
                /**
                 * e.g. a damaged return: the stock that just came back is pulled out again.
                 */
                $this->removeReturnedStock( $history, $product, $quantity );

                return;
            }

            return;
        }

        if ( $action === ProductHistory::ACTION_CONVERT_IN && ! empty( $history->procurement_product_id ) ) {
            $this->createFromConversion( $history );

            return;
        }

        if ( $isReduce ) {
            $this->deductAdjustment( $history, $product, $quantity );
        }

        /**
         * Other increases (manual "added", transfers in) are not linked to a
         * procurement: that stock stays unbatched until received through a procurement.
         */
    }

    /**
     * FEFO allocation: sellable batches by earliest expiry (no expiry last), then
     * expired batches when selling them is allowed. Anything left is recorded as unallocated.
     */
    public function allocateFefo( ProductHistory $history, Product $product, float $quantity, string $type ): Collection
    {
        $today = $this->today();
        $allocations = collect();

        $pools = [
            Batch::forStock( $product->id, $history->unit_id )->sellable( $today )->withStock()->fefo(),
        ];

        if ( ! $this->blocksExpiredSales( $product ) ) {
            $pools[] = Batch::forStock( $product->id, $history->unit_id )->expiredStock( $today )->withStock()->fefo();
        }

        $remaining = $quantity;

        foreach ( $pools as $query ) {
            if ( $remaining <= self::EPSILON ) {
                break;
            }

            foreach ( $query->get() as $batch ) {
                if ( $remaining <= self::EPSILON ) {
                    break;
                }

                $take = min( $remaining, $batch->quantity_remaining );
                $this->decrementBatch( $batch, $take );
                $allocations->push( $this->recordAllocation( $history, $batch, $take, $type ) );
                $remaining -= $take;
            }
        }

        if ( $remaining > self::EPSILON ) {
            $allocations->push( $this->recordAllocation(
                history: $history,
                batch: null,
                quantity: $remaining,
                type: $type,
                note: __m( 'Not covered by any batch (unbatched stock).', 'MplBatches' )
            ) );
        }

        return $allocations;
    }

    /**
     * Gives the returned quantity back to the batches the order product was taken from,
     * most recent allocation first.
     */
    public function restoreForOrderProduct( ProductHistory $history, float $quantity ): Collection
    {
        $restored = collect();
        $remaining = $quantity;

        $sales = BatchAllocation::where( 'order_product_id', $history->order_product_id )
            ->where( 'product_id', $history->product_id )
            ->where( 'unit_id', $history->unit_id )
            ->get();

        /**
         * Net quantity still "out" per batch (null batch = unallocated part).
         */
        $outstanding = [];

        foreach ( $sales as $allocation ) {
            $key = $allocation->batch_id ?? 0;
            $outstanding[ $key ] = ( $outstanding[ $key ] ?? 0 ) + match ( $allocation->type ) {
                BatchAllocation::TYPE_SALE => $allocation->quantity,
                BatchAllocation::TYPE_REFUND => -$allocation->quantity,
                default => 0,
            };
        }

        $order = $sales->where( 'type', BatchAllocation::TYPE_SALE )
            ->sortByDesc( 'id' )
            ->map( fn( BatchAllocation $allocation ) => $allocation->batch_id ?? 0 )
            ->unique()
            ->values();

        foreach ( $order as $batchId ) {
            if ( $remaining <= self::EPSILON ) {
                break;
            }

            $available = $outstanding[ $batchId ] ?? 0;

            if ( $available <= self::EPSILON ) {
                continue;
            }

            $give = min( $remaining, $available );
            $batch = $batchId ? Batch::find( $batchId ) : null;

            if ( $batch instanceof Batch ) {
                $this->incrementBatch( $batch, $give );
            }

            $restored->push( $this->recordAllocation(
                history: $history,
                batch: $batch,
                quantity: $give,
                type: BatchAllocation::TYPE_REFUND,
                note: $batch instanceof Batch ? null : __m( 'Returned stock that was not covered by any batch.', 'MplBatches' )
            ) );

            $remaining -= $give;
        }

        if ( $remaining > self::EPSILON ) {
            $restored->push( $this->recordAllocation(
                history: $history,
                batch: null,
                quantity: $remaining,
                type: BatchAllocation::TYPE_REFUND,
                note: __m( 'Returned stock with no recorded batch allocation.', 'MplBatches' )
            ) );
        }

        return $restored;
    }

    /**
     * Pulls out stock that was just returned for an order product (damaged return).
     */
    private function removeReturnedStock( ProductHistory $history, Product $product, float $quantity ): void
    {
        $remaining = $quantity;

        $refunds = BatchAllocation::where( 'order_product_id', $history->order_product_id )
            ->where( 'product_id', $history->product_id )
            ->where( 'unit_id', $history->unit_id )
            ->where( 'type', BatchAllocation::TYPE_REFUND )
            ->whereNotNull( 'batch_id' )
            ->orderByDesc( 'id' )
            ->get();

        foreach ( $refunds as $refund ) {
            if ( $remaining <= self::EPSILON ) {
                break;
            }

            $batch = Batch::find( $refund->batch_id );

            if ( ! $batch instanceof Batch || $batch->quantity_remaining <= self::EPSILON ) {
                continue;
            }

            $take = min( $remaining, $refund->quantity, $batch->quantity_remaining );
            $this->decrementBatch( $batch, $take );
            $this->recordAllocation( $history, $batch, $take, BatchAllocation::TYPE_ADJUSTMENT, __m( 'Damaged return removed from stock.', 'MplBatches' ) );
            $remaining -= $take;
        }

        if ( $remaining > self::EPSILON ) {
            $this->allocateFefo( $history, $product, $remaining, BatchAllocation::TYPE_ADJUSTMENT );
        }
    }

    /**
     * Manual removal (defective, lost, removed, deleted, conversion out...).
     */
    private function deductAdjustment( ProductHistory $history, Product $product, float $quantity ): void
    {
        $remaining = $quantity;
        $targets = collect();

        if ( $this->writeOffBatchId !== null ) {
            $targets = Batch::whereKey( $this->writeOffBatchId )->get();
        } elseif ( ! empty( $history->procurement_product_id ) ) {
            $targets = Batch::where( 'procurement_product_id', $history->procurement_product_id )
                ->where( 'unit_id', $history->unit_id )
                ->withStock()
                ->get();
        }

        foreach ( $targets as $batch ) {
            if ( $remaining <= self::EPSILON ) {
                break;
            }

            $take = min( $remaining, $batch->quantity_remaining );

            if ( $take <= self::EPSILON ) {
                continue;
            }

            $this->decrementBatch( $batch, $take );
            $this->recordAllocation( $history, $batch, $take, BatchAllocation::TYPE_ADJUSTMENT, $history->description ?: null );
            $remaining -= $take;
        }

        if ( $remaining <= self::EPSILON ) {
            return;
        }

        /**
         * Removing stock is never blocked by expiry: expired stock goes first
         * (it is what is usually thrown away), then FEFO on the rest.
         */
        $today = $this->today();
        $pools = [
            Batch::forStock( $product->id, $history->unit_id )->expiredStock( $today )->withStock()->fefo(),
            Batch::forStock( $product->id, $history->unit_id )->sellable( $today )->withStock()->fefo(),
        ];

        foreach ( $pools as $query ) {
            foreach ( $query->get() as $batch ) {
                if ( $remaining <= self::EPSILON ) {
                    break 2;
                }

                $take = min( $remaining, $batch->quantity_remaining );
                $this->decrementBatch( $batch, $take );
                $this->recordAllocation( $history, $batch, $take, BatchAllocation::TYPE_ADJUSTMENT, $history->description ?: null );
                $remaining -= $take;
            }
        }

        if ( $remaining > self::EPSILON ) {
            $this->recordAllocation( $history, null, $remaining, BatchAllocation::TYPE_ADJUSTMENT, __m( 'Not covered by any batch (unbatched stock).', 'MplBatches' ) );
        }
    }

    private function decrementBatch( Batch $batch, float $quantity ): void
    {
        $batch->quantity_remaining = max( 0, round( $batch->quantity_remaining - $quantity, 5 ) );
        $this->refreshStatus( $batch );
        $batch->save();
    }

    private function incrementBatch( Batch $batch, float $quantity ): void
    {
        $batch->quantity_remaining = round( $batch->quantity_remaining + $quantity, 5 );
        $this->refreshStatus( $batch );
        $batch->save();
    }

    private function recordAllocation( ProductHistory $history, ?Batch $batch, float $quantity, string $type, ?string $note = null ): BatchAllocation
    {
        $allocation = new BatchAllocation;
        $allocation->batch_id = $batch?->id;
        $allocation->product_id = $history->product_id;
        $allocation->unit_id = $history->unit_id;
        $allocation->order_id = $history->order_id ?: ( $history->order_product_id ? OrderProduct::whereKey( $history->order_product_id )->value( 'order_id' ) : null );
        $allocation->order_product_id = $history->order_product_id ?: null;
        $allocation->product_history_id = $history->id ?: null;
        $allocation->quantity = round( $quantity, 5 );
        $allocation->type = $type;
        $allocation->note = $note ? mb_substr( $note, 0, 190 ) : null;
        $allocation->author_id = $history->author_id ?: Auth::id();
        $allocation->save();

        return $allocation;
    }

    /*
    |--------------------------------------------------------------------------
    | Selling restrictions
    |--------------------------------------------------------------------------
    */

    /**
     * Quantity that can be sold without touching expired batches:
     * non-expired batch stock + stock not covered by any batch.
     */
    public function getSellableQuantity( Product $product, int $unitId, ?float $onHand = null ): float
    {
        $today = $this->today();

        if ( $onHand === null ) {
            $onHand = (float) ProductUnitQuantity::where( 'product_id', $product->id )
                ->where( 'unit_id', $unitId )
                ->value( 'quantity' );
        }

        $sellable = (float) Batch::forStock( $product->id, $unitId )->sellable( $today )->withStock()->sum( 'quantity_remaining' );
        $batched = (float) Batch::forStock( $product->id, $unitId )
            ->whereIn( 'status', [ Batch::STATUS_ACTIVE, Batch::STATUS_EXPIRED ] )
            ->withStock()
            ->sum( 'quantity_remaining' );

        $unbatched = max( 0, $onHand - $batched );

        return min( $onHand, $sellable + $unbatched );
    }

    /**
     * Called before an order is persisted: refuses a sale that could only be
     * fulfilled with expired batches when the product (or the settings) prevents it.
     *
     * @throws NotAllowedException
     */
    public function assertOrderDoesNotSellExpiredStock( array $fields ): void
    {
        $requested = [];

        /**
         * Grouped products leave the stock through their sub-items (same
         * quantities as ProductService::handleStockAdjustmentsForGroupedProducts).
         */
        $lines = collect( $fields[ 'products' ] ?? [] )->flatMap( function ( $line ) {
            $product = $line[ 'product' ] ?? null;

            if ( ! $product instanceof Product || $product->type !== Product::TYPE_GROUPED ) {
                return [ $line ];
            }

            $parentUnit = Unit::find( (int) ( $line[ 'unit_id' ] ?? 0 ) );

            if ( ! $parentUnit instanceof Unit ) {
                return [];
            }

            $product->loadMissing( 'sub_items.product' );

            return $product->sub_items->map( fn( ProductSubItem $subItem ) => [
                'id' => $line[ 'id' ] ?? null,
                'product' => $subItem->product,
                'unit_id' => $subItem->unit_id,
                'quantity' => app()->make( ProductService::class )->computeSubItemQuantity(
                    subItemQuantity: (float) $subItem->quantity,
                    parentUnit: $parentUnit,
                    parentQuantity: abs( (float) ( $line[ 'quantity' ] ?? 0 ) )
                ),
            ] )->all();
        } );

        foreach ( $lines as $line ) {
            $product = $line[ 'product' ] ?? null;

            if (
                ! $product instanceof Product ||
                $product->type === Product::TYPE_GROUPED ||
                $product->stock_management !== Product::STOCK_MANAGEMENT_ENABLED ||
                ! $this->blocksExpiredSales( $product )
            ) {
                continue;
            }

            $unitId = (int) ( $line[ 'unit_id' ] ?? 0 );
            $quantity = abs( (float) ( $line[ 'quantity' ] ?? 0 ) );

            /**
             * When an existing line is edited, only the additional quantity leaves the stock.
             */
            if ( ! empty( $line[ 'id' ] ) ) {
                $alreadyAllocated = BatchAllocation::where( 'order_product_id', $line[ 'id' ] )
                    ->where( 'product_id', $product->id )
                    ->where( 'unit_id', $unitId )
                    ->get()
                    ->sum( fn( BatchAllocation $allocation ) => match ( $allocation->type ) {
                        BatchAllocation::TYPE_SALE => $allocation->quantity,
                        BatchAllocation::TYPE_REFUND => -$allocation->quantity,
                        default => 0,
                    } );

                $quantity = max( 0, $quantity - $alreadyAllocated );
            }

            $key = $product->id . '-' . $unitId;
            $requested[ $key ] = [
                'product' => $product,
                'unit_id' => $unitId,
                'quantity' => ( $requested[ $key ][ 'quantity' ] ?? 0 ) + $quantity,
            ];
        }

        foreach ( $requested as $request ) {
            if ( $request[ 'quantity' ] <= self::EPSILON ) {
                continue;
            }

            $hasExpiredStock = Batch::forStock( $request[ 'product' ]->id, $request[ 'unit_id' ] )
                ->expiredStock( $this->today() )
                ->withStock()
                ->exists();

            if ( ! $hasExpiredStock ) {
                continue;
            }

            $sellable = $this->getSellableQuantity( $request[ 'product' ], $request[ 'unit_id' ] );

            if ( $request[ 'quantity' ] - $sellable > self::EPSILON ) {
                throw new NotAllowedException( sprintf(
                    __m( 'Unable to sell %s of "%s": only %s is available from non-expired batches. Write off the expired batches or adjust the quantity.', 'MplBatches' ),
                    $request[ 'quantity' ],
                    $request[ 'product' ]->name,
                    $sellable
                ) );
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Write-off
    |--------------------------------------------------------------------------
    */

    /**
     * Removes the remaining (or a given) quantity of a batch from the stock
     * through a core stock adjustment, so the inventory and history stay accurate.
     *
     * @throws NotAllowedException
     */
    public function writeOff( Batch $batch, ?float $quantity = null, string $reason = '' ): Batch
    {
        if ( $batch->status === Batch::STATUS_WRITTEN_OFF ) {
            throw new NotAllowedException( __m( 'This batch has already been written off.', 'MplBatches' ) );
        }

        $quantity = $quantity === null ? $batch->quantity_remaining : abs( $quantity );

        if ( $quantity <= self::EPSILON ) {
            throw new NotAllowedException( __m( 'This batch has no remaining quantity to write off.', 'MplBatches' ) );
        }

        if ( $quantity - $batch->quantity_remaining > self::EPSILON ) {
            throw new NotAllowedException( sprintf(
                __m( 'Unable to write off %s: the batch only has %s remaining.', 'MplBatches' ),
                $quantity,
                $batch->quantity_remaining
            ) );
        }

        $product = Product::find( $batch->product_id );

        if ( ! $product instanceof Product ) {
            throw new NotAllowedException( __m( 'The product linked to this batch no longer exists.', 'MplBatches' ) );
        }

        /**
         * Core ignores stock adjustments on such products: the write-off would silently do nothing.
         */
        if ( $product->stock_management !== Product::STOCK_MANAGEMENT_ENABLED ) {
            throw new NotAllowedException( __m( 'Unable to write off this batch: the stock management is disabled for the product.', 'MplBatches' ) );
        }

        $description = sprintf(
            __m( 'Batch %s written off. %s', 'MplBatches' ),
            $batch->batch_number,
            $reason
        );

        return DB::transaction( function () use ( $batch, $quantity, $description ) {
            $procurementProduct = $batch->procurement_product_id ? ProcurementProduct::find( $batch->procurement_product_id ) : null;

            $this->writeOffBatchId = $batch->id;

            try {
                /**
                 * @var ProductService $productService
                 */
                $productService = app()->make( ProductService::class );
                $productService->stockAdjustment( ProductHistory::ACTION_REMOVED, [
                    'product_id' => $batch->product_id,
                    'unit_id' => $batch->unit_id,
                    'unit_price' => $batch->cost,
                    'total_price' => $batch->cost * $quantity,
                    'quantity' => $quantity,
                    'description' => trim( $description ),
                    'procurementProduct' => $procurementProduct instanceof ProcurementProduct && (int) $procurementProduct->unit_id === (int) $batch->unit_id
                        ? $procurementProduct
                        : null,
                ] );
            } finally {
                $this->writeOffBatchId = null;
            }

            $batch->refresh();

            if ( $batch->quantity_remaining <= self::EPSILON ) {
                $batch->quantity_remaining = 0;
                $batch->status = Batch::STATUS_WRITTEN_OFF;
                $batch->save();
            }

            return $batch;
        } );
    }

    /*
    |--------------------------------------------------------------------------
    | Expiry
    |--------------------------------------------------------------------------
    */

    /**
     * Flags active batches whose expiry date is reached.
     */
    public function markExpiredBatches(): int
    {
        $today = $this->today();
        $count = 0;

        Batch::where( 'status', Batch::STATUS_ACTIVE )
            ->whereNotNull( 'expiry_date' )
            ->where( 'expiry_date', '<=', $today )
            ->get()
            ->each( function ( Batch $batch ) use ( &$count ) {
                $this->refreshStatus( $batch );

                if ( $batch->isDirty( 'status' ) ) {
                    $batch->save();
                    $count++;
                }
            } );

        return $count;
    }

    /**
     * Groups batches with stock into expired / critical / soon buckets.
     *
     * @return array{expired: Collection, critical: Collection, soon: Collection, today: string, critical_days: int, alert_days: int}
     */
    public function getExpiryBuckets( ?int $alertDays = null, ?int $criticalDays = null ): array
    {
        $today = $this->today();
        $alertDays = $alertDays ?? $this->getAlertDays();
        $criticalDays = $criticalDays ?? $this->getCriticalDays();
        $criticalLimit = now( ns()->date->getTimezone() )->addDays( $criticalDays )->toDateString();
        $alertLimit = now( ns()->date->getTimezone() )->addDays( max( $alertDays, $criticalDays ) )->toDateString();

        $base = fn() => Batch::with( [ 'product:id,name,sku', 'unit:id,name' ] )->withStock()->fefo();

        $expired = $base()->expiredStock( $today )->get();
        $critical = $base()->sellable( $today )->where( 'expiry_date', '<=', $criticalLimit )->get();
        $soon = $base()->sellable( $today )
            ->where( 'expiry_date', '>', $criticalLimit )
            ->where( 'expiry_date', '<=', $alertLimit )
            ->get();

        return [
            'expired' => $expired,
            'critical' => $critical,
            'soon' => $soon,
            'today' => $today,
            'critical_days' => $criticalDays,
            'alert_days' => $alertDays,
        ];
    }

    public function stockValue( Collection $batches ): float
    {
        return round( $batches->sum( fn( Batch $batch ) => $batch->quantity_remaining * $batch->cost ), 5 );
    }

    public function daysLeft( Batch $batch ): ?int
    {
        if ( empty( $batch->expiry_date ) ) {
            return null;
        }

        return (int) now( ns()->date->getTimezone() )->startOfDay()->diffInDays( substr( (string) $batch->expiry_date, 0, 10 ), false );
    }
}
