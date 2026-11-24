<?php

use App\Classes\Hook;
use App\Classes\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::createIfMissing( 'mpl_batch_batches', function ( Blueprint $table ) {
            $table->id();
            $table->unsignedBigInteger( 'product_id' );
            $table->unsignedBigInteger( 'unit_id' );
            $table->unsignedBigInteger( 'procurement_id' )->nullable();
            $table->unsignedBigInteger( 'procurement_product_id' )->nullable();
            $table->string( 'batch_number' );
            $table->date( 'expiry_date' )->nullable();
            $table->decimal( 'quantity_received', 18, 5 )->default( 0 );
            $table->decimal( 'quantity_remaining', 18, 5 )->default( 0 );
            $table->decimal( 'cost', 18, 5 )->default( 0 );
            $table->string( 'status' )->default( 'active' );
            $table->unsignedBigInteger( 'author_id' )->nullable();
            $table->timestamps();
        } );

        $this->guardColumns( 'mpl_batch_batches', [
            'procurement_id' => fn( Blueprint $table ) => $table->unsignedBigInteger( 'procurement_id' )->nullable(),
            'procurement_product_id' => fn( Blueprint $table ) => $table->unsignedBigInteger( 'procurement_product_id' )->nullable(),
            'cost' => fn( Blueprint $table ) => $table->decimal( 'cost', 18, 5 )->default( 0 ),
            'author_id' => fn( Blueprint $table ) => $table->unsignedBigInteger( 'author_id' )->nullable(),
        ] );

        $this->guardIndex( 'mpl_batch_batches', [ 'product_id', 'unit_id', 'status' ], 'stock_idx' );
        $this->guardIndex( 'mpl_batch_batches', [ 'expiry_date' ], 'expiry_idx' );
        $this->guardIndex( 'mpl_batch_batches', [ 'procurement_product_id' ], 'pp_idx' );

        Schema::createIfMissing( 'mpl_batch_allocations', function ( Blueprint $table ) {
            $table->id();
            $table->unsignedBigInteger( 'batch_id' )->nullable();
            $table->unsignedBigInteger( 'product_id' );
            $table->unsignedBigInteger( 'unit_id' );
            $table->unsignedBigInteger( 'order_id' )->nullable();
            $table->unsignedBigInteger( 'order_product_id' )->nullable();
            $table->unsignedBigInteger( 'product_history_id' )->nullable();
            $table->decimal( 'quantity', 18, 5 )->default( 0 );
            $table->string( 'type' )->default( 'sale' );
            $table->string( 'note' )->nullable();
            $table->unsignedBigInteger( 'author_id' )->nullable();
            $table->timestamps();
        } );

        $this->guardIndex( 'mpl_batch_allocations', [ 'batch_id' ], 'batch_idx' );
        $this->guardIndex( 'mpl_batch_allocations', [ 'order_product_id' ], 'op_idx' );
        $this->guardIndex( 'mpl_batch_allocations', [ 'order_id' ], 'order_idx' );

        /**
         * Batch numbers typed on the procurement screen are kept here until
         * the procurement is stocked (it can stay pending for days).
         */
        Schema::createIfMissing( 'mpl_batch_procurement_numbers', function ( Blueprint $table ) {
            $table->id();
            $table->unsignedBigInteger( 'procurement_product_id' );
            $table->string( 'batch_number' );
            $table->timestamps();
        } );

        $this->guardIndex( 'mpl_batch_procurement_numbers', [ 'procurement_product_id' ], 'pp_idx' );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists( 'mpl_batch_procurement_numbers' );
        Schema::dropIfExists( 'mpl_batch_allocations' );
        Schema::dropIfExists( 'mpl_batch_batches' );
    }

    /**
     * @param array<string, callable> $columns
     */
    private function guardColumns( string $table, array $columns ): void
    {
        if ( ! Schema::hasTable( $table ) ) {
            return;
        }

        foreach ( $columns as $column => $definition ) {
            if ( ! Schema::hasColumn( $table, $column ) ) {
                Schema::table( $table, fn( Blueprint $blueprint ) => $definition( $blueprint ) );
            }
        }
    }

    private function guardIndex( string $table, array $columns, string $suffix ): void
    {
        if ( ! Schema::hasTable( $table ) ) {
            return;
        }

        /**
         * Index names are global on some drivers: derive them from the
         * store-aware table name so Multistore copies never collide.
         */
        $resolvedTable = Hook::filter( 'ns-table-name', $table );
        $name = $resolvedTable . '_' . $suffix;

        if ( ! Schema::hasIndex( $resolvedTable, $name ) ) {
            Schema::table( $table, fn( Blueprint $blueprint ) => $blueprint->index( $columns, $name ) );
        }
    }
};
