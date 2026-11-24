<?php

namespace Modules\MplEInvoice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use Modules\MplEInvoice\Crud\EInvoiceCrud;
use Modules\MplEInvoice\Models\EInvoiceDocument;
use Modules\MplEInvoice\Services\EInvoiceService;
use Modules\MplEInvoice\Services\EInvoiceSettings;

class EInvoiceController extends Controller
{
    public function __construct( private EInvoiceService $service ) {}

    public function listDocuments()
    {
        return EInvoiceCrud::table();
    }

    /**
     * Download the stored UBL XML (never regenerated).
     */
    public function downloadXml( EInvoiceDocument $document ): Response
    {
        return response( $document->xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . preg_replace( '/[^A-Za-z0-9\-_]/', '_', $document->number ) . '.xml"',
        ] );
    }

    public function printDocument( EInvoiceDocument $document )
    {
        $document->load( [ 'order', 'originalDocument' ] );

        return View::make( 'MplEInvoice::print', [
            'document' => $document,
            'summary' => $document->summary ?? [],
            'title' => $document->number,
            'emirates' => EInvoiceSettings::emirates(),
        ] );
    }

    public function showDocument( EInvoiceDocument $document ): JsonResponse
    {
        return response()->json( $document->makeHidden( [ 'xml' ] ) );
    }

    public function submitDocument( EInvoiceDocument $document ): JsonResponse
    {
        if ( ! $document->isSubmittable() ) {
            return response()->json( [
                'status' => 'error',
                'message' => __m( 'This e-invoice has already been submitted to the ASP.', 'MplEInvoice' ),
            ], 403 );
        }

        $document = $this->service->submit( $document );

        if ( in_array( $document->status, [ EInvoiceDocument::STATUS_SUBMITTED, EInvoiceDocument::STATUS_ACCEPTED ], true ) ) {
            return response()->json( [
                'status' => 'success',
                'message' => sprintf( __m( 'The e-invoice %s has been submitted to the ASP.', 'MplEInvoice' ), $document->number ),
                'data' => [ 'document' => $document->makeHidden( [ 'xml' ] ) ],
            ] );
        }

        return response()->json( [
            'status' => 'error',
            'message' => sprintf( __m( 'The submission of %s failed: %s', 'MplEInvoice' ), $document->number, $document->error ),
            'data' => [ 'document' => $document->makeHidden( [ 'xml' ] ) ],
        ], 422 );
    }

    /**
     * Manually generate the e-invoice of a paid order (e.g. orders paid before
     * the module was enabled). Idempotent.
     */
    public function generateForOrder( Order $order ): JsonResponse
    {
        if ( $order->payment_status !== Order::PAYMENT_PAID ) {
            return response()->json( [
                'status' => 'error',
                'message' => __m( 'Only fully paid orders can receive a tax invoice.', 'MplEInvoice' ),
            ], 403 );
        }

        $document = $this->service->generateForOrder( $order );

        if ( ! $document instanceof EInvoiceDocument ) {
            return response()->json( [
                'status' => 'error',
                'message' => __m( 'E-invoicing is disabled. Enable it from the settings first.', 'MplEInvoice' ),
            ], 403 );
        }

        return response()->json( [
            'status' => 'success',
            'message' => sprintf( __m( 'The e-invoice %s is available.', 'MplEInvoice' ), $document->number ),
            'data' => [ 'document' => $document->makeHidden( [ 'xml' ] ) ],
        ] );
    }
}
