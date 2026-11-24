<?php

/**
 * Creates the storage used by the UAE e-invoicing module.
 */

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
        Schema::createIfMissing( 'mpl_einv_customer_profiles', function ( Blueprint $table ) {
            $table->id();
            $table->unsignedBigInteger( 'customer_id' )->unique();
            $table->string( 'trn', 15 )->nullable();
            $table->string( 'legal_name' )->nullable();
            $table->string( 'address' )->nullable();
            $table->string( 'city' )->nullable();
            $table->string( 'emirate' )->nullable();
            $table->string( 'country_code', 2 )->default( 'AE' );
            $table->string( 'peppol_id' )->nullable();
            $table->unsignedBigInteger( 'author_id' )->nullable();
            $table->timestamps();
        } );

        Schema::createIfMissing( 'mpl_einv_sequences', function ( Blueprint $table ) {
            $table->id();
            $table->string( 'series', 50 )->unique();
            $table->unsignedBigInteger( 'last_number' )->default( 0 );
            $table->timestamps();
        } );

        Schema::createIfMissing( 'mpl_einv_documents', function ( Blueprint $table ) {
            $table->id();
            $table->string( 'source_key', 100 )->unique();
            $table->unsignedBigInteger( 'order_id' );
            $table->unsignedBigInteger( 'order_refund_id' )->nullable();
            $table->unsignedBigInteger( 'original_document_id' )->nullable();
            $table->unsignedBigInteger( 'customer_id' )->nullable();
            $table->enum( 'type', [ 'tax_invoice', 'simplified', 'credit_note' ] );
            $table->string( 'series', 50 );
            $table->unsignedBigInteger( 'sequence_number' );
            $table->string( 'number', 100 )->unique();
            $table->uuid( 'uuid' )->unique();
            $table->dateTime( 'issued_at' );
            $table->string( 'currency', 3 )->default( 'AED' );
            $table->decimal( 'total_net', 18, 2 )->default( 0 );
            $table->decimal( 'total_vat', 18, 2 )->default( 0 );
            $table->decimal( 'total_gross', 18, 2 )->default( 0 );
            $table->string( 'buyer_name' )->nullable();
            $table->string( 'buyer_trn', 15 )->nullable();
            $table->longText( 'xml' );
            $table->json( 'summary' )->nullable();
            $table->enum( 'status', [ 'generated', 'submitted', 'accepted', 'rejected', 'failed' ] )->default( 'generated' );
            $table->unsignedInteger( 'attempts' )->default( 0 );
            $table->string( 'asp_reference' )->nullable();
            $table->longText( 'response_payload' )->nullable();
            $table->dateTime( 'submitted_at' )->nullable();
            $table->text( 'error' )->nullable();
            $table->unsignedBigInteger( 'author_id' )->nullable();
            $table->timestamps();

            $table->index( 'order_id' );
            $table->index( 'order_refund_id' );
            $table->index( 'status' );
            $table->index( 'type' );
            $table->unique( [ 'series', 'sequence_number' ] );
        } );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists( 'mpl_einv_documents' );
        Schema::dropIfExists( 'mpl_einv_sequences' );
        Schema::dropIfExists( 'mpl_einv_customer_profiles' );
    }
};
