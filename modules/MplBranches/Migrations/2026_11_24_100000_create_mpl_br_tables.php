<?php

/**
 * Creates the MplBranches storage.
 *
 * v1 scope: the product catalogue and stock are shared across branches.
 * Branch ids are kept as plain indexed integers so a future
 * "mpl_br_branch_stock" (branch_id, product_id, unit_id, quantity) table
 * can reference them without touching these tables.
 */

namespace Modules\MplBranches\Migrations;

use App\Classes\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Modules\MplBranches\Services\BranchService;

return new class extends Migration
{
    public function up(): void
    {
        Schema::createIfMissing( 'mpl_br_branches', function ( Blueprint $table ) {
            $table->id();
            $table->string( 'name' );
            $table->string( 'code', 50 )->unique();
            $table->string( 'address' )->nullable();
            $table->string( 'city' )->nullable();
            $table->string( 'emirate' )->nullable();
            $table->string( 'phone', 50 )->nullable();
            $table->string( 'email' )->nullable();
            $table->string( 'trn', 50 )->nullable();
            $table->text( 'receipt_header' )->nullable();
            $table->text( 'receipt_footer' )->nullable();
            $table->boolean( 'is_default' )->default( false );
            $table->boolean( 'active' )->default( true );
            $table->timestamps();
        } );

        Schema::createIfMissing( 'mpl_br_branch_user', function ( Blueprint $table ) {
            $table->id();
            $table->unsignedBigInteger( 'user_id' );
            $table->unsignedBigInteger( 'branch_id' );
            $table->boolean( 'is_primary' )->default( false );
            $table->timestamps();
            $table->unique( [ 'user_id', 'branch_id' ] );
            $table->index( 'branch_id' );
        } );

        Schema::createIfMissing( 'mpl_br_branch_register', function ( Blueprint $table ) {
            $table->id();
            $table->unsignedBigInteger( 'register_id' )->unique();
            $table->unsignedBigInteger( 'branch_id' );
            $table->timestamps();
            $table->index( 'branch_id' );
        } );

        Schema::createIfMissing( 'mpl_br_branch_orders', function ( Blueprint $table ) {
            $table->id();
            $table->unsignedBigInteger( 'order_id' )->unique();
            $table->unsignedBigInteger( 'branch_id' );
            $table->unsignedBigInteger( 'register_id' )->nullable();
            $table->unsignedBigInteger( 'author_id' )->nullable();
            $table->string( 'source', 20 )->nullable();
            $table->timestamps();
            $table->index( 'branch_id' );
        } );

        /**
         * Column guards for installations that ran a partial version of this migration.
         */
        if ( Schema::hasTable( 'mpl_br_branch_orders' ) && ! Schema::hasColumn( 'mpl_br_branch_orders', 'source' ) ) {
            Schema::table( 'mpl_br_branch_orders', function ( Blueprint $table ) {
                $table->string( 'source', 20 )->nullable();
            } );
        }

        app()->make( BranchService::class )->ensureDefaultBranch();
    }

    public function down(): void
    {
        Schema::dropIfExists( 'mpl_br_branch_orders' );
        Schema::dropIfExists( 'mpl_br_branch_register' );
        Schema::dropIfExists( 'mpl_br_branch_user' );
        Schema::dropIfExists( 'mpl_br_branches' );
    }
};
