<?php

/**
 * Creates the WhatsApp receipt message log table.
 */

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
        Schema::createIfMissing( 'mpl_wa_messages', function ( Blueprint $table ) {
            $table->bigIncrements( 'id' );
            $table->unsignedBigInteger( 'order_id' );
            $table->string( 'phone', 32 );
            $table->string( 'mode', 20 );
            $table->string( 'trigger', 20 )->default( 'manual' );
            $table->string( 'status', 20 )->default( 'pending' );
            $table->string( 'provider_message_id' )->nullable();
            $table->text( 'error' )->nullable();
            $table->unsignedBigInteger( 'user_id' )->nullable();
            $table->timestamps();
        } );

        if ( Schema::hasTable( 'mpl_wa_messages' ) ) {
            foreach ( [
                'order_id' => fn( Blueprint $table ) => $table->unsignedBigInteger( 'order_id' ),
                'phone' => fn( Blueprint $table ) => $table->string( 'phone', 32 ),
                'mode' => fn( Blueprint $table ) => $table->string( 'mode', 20 ),
                'trigger' => fn( Blueprint $table ) => $table->string( 'trigger', 20 )->default( 'manual' ),
                'status' => fn( Blueprint $table ) => $table->string( 'status', 20 )->default( 'pending' ),
                'provider_message_id' => fn( Blueprint $table ) => $table->string( 'provider_message_id' )->nullable(),
                'error' => fn( Blueprint $table ) => $table->text( 'error' )->nullable(),
                'user_id' => fn( Blueprint $table ) => $table->unsignedBigInteger( 'user_id' )->nullable(),
            ] as $column => $definition ) {
                if ( ! Schema::hasColumn( 'mpl_wa_messages', $column ) ) {
                    Schema::table( 'mpl_wa_messages', function ( Blueprint $table ) use ( $definition ) {
                        $definition( $table );
                    } );
                }
            }

            /**
             * The index name is derived from the store-resolved table name so it
             * stays unique per Multistore store (SQLite/PostgreSQL index names are global).
             */
            $resolvedTable = Hook::filter( 'ns-table-name', 'mpl_wa_messages' );

            if ( ! Schema::hasIndex( $resolvedTable, [ 'order_id' ] ) ) {
                Schema::table( 'mpl_wa_messages', function ( Blueprint $table ) use ( $resolvedTable ) {
                    $table->index( 'order_id', $resolvedTable . '_order_id_index' );
                } );
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ( Schema::hasTable( 'mpl_wa_messages' ) ) {
            Schema::drop( 'mpl_wa_messages' );
        }
    }
};
