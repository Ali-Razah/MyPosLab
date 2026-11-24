<?php

namespace Modules\MplEInvoice\Crud;

use App\Casts\CurrencyCast;
use App\Casts\DateCast;
use App\Classes\CrudTable;
use App\Services\CrudEntry;
use App\Services\CrudService;
use App\Services\Helper;
use Modules\MplEInvoice\Casts\DocumentStatusCast;
use Modules\MplEInvoice\Casts\DocumentTypeCast;
use Modules\MplEInvoice\Models\EInvoiceDocument;

/**
 * Read-only list of the generated e-invoices. Documents are created by the
 * module from orders and refunds, never manually, and are never deleted.
 */
class EInvoiceCrud extends CrudService
{
    const AUTOLOAD = true;

    const IDENTIFIER = 'mpleinvoice.documents';

    protected $table = 'mpl_einv_documents';

    protected $namespace = 'mpleinvoice.documents';

    protected $model = EInvoiceDocument::class;

    protected $prependOptions = true;

    protected $showCheckboxes = false;

    public $relations = [
        'leftJoin' => [
            [ 'nexopos_orders as order', 'mpl_einv_documents.order_id', '=', 'order.id' ],
        ],
    ];

    public $pick = [
        'order' => [ 'code' ],
    ];

    protected $permissions = [
        'create' => false,
        'read' => 'mpleinvoice.read',
        'update' => false,
        'delete' => false,
    ];

    protected $casts = [
        'type' => DocumentTypeCast::class,
        'status' => DocumentStatusCast::class,
        'total_gross' => CurrencyCast::class,
        'total_vat' => CurrencyCast::class,
        'issued_at' => DateCast::class,
    ];

    public function __construct()
    {
        parent::__construct();

        $this->queryFilters = [
            [
                'type' => 'select',
                'name' => 'status',
                'label' => __m( 'Status', 'MplEInvoice' ),
                'description' => __m( 'Only display the documents with this submission status.', 'MplEInvoice' ),
                'options' => Helper::kvToJsOptions( [
                    EInvoiceDocument::STATUS_GENERATED => __m( 'Generated', 'MplEInvoice' ),
                    EInvoiceDocument::STATUS_SUBMITTED => __m( 'Submitted', 'MplEInvoice' ),
                    EInvoiceDocument::STATUS_ACCEPTED => __m( 'Accepted', 'MplEInvoice' ),
                    EInvoiceDocument::STATUS_REJECTED => __m( 'Rejected', 'MplEInvoice' ),
                    EInvoiceDocument::STATUS_FAILED => __m( 'Failed', 'MplEInvoice' ),
                ] ),
            ],
            [
                'type' => 'select',
                'name' => 'type',
                'label' => __m( 'Type', 'MplEInvoice' ),
                'description' => __m( 'Only display the documents of this type.', 'MplEInvoice' ),
                'options' => Helper::kvToJsOptions( [
                    EInvoiceDocument::TYPE_TAX_INVOICE => __m( 'Tax Invoice', 'MplEInvoice' ),
                    EInvoiceDocument::TYPE_SIMPLIFIED => __m( 'Simplified Tax Invoice', 'MplEInvoice' ),
                    EInvoiceDocument::TYPE_CREDIT_NOTE => __m( 'Credit Note', 'MplEInvoice' ),
                ] ),
            ],
        ];
    }

    public function getLabels()
    {
        return CrudTable::labels(
            list_title: __m( 'E-Invoices', 'MplEInvoice' ),
            list_description: __m( 'UAE tax invoices and credit notes generated from orders and refunds.', 'MplEInvoice' ),
            no_entry: __m( 'No e-invoice has been generated yet.', 'MplEInvoice' ),
            create_new: __m( 'New e-invoice', 'MplEInvoice' ),
            create_title: __m( 'New e-invoice', 'MplEInvoice' ),
            create_description: __m( 'E-invoices are generated automatically from paid orders.', 'MplEInvoice' ),
            edit_title: __m( 'E-Invoice', 'MplEInvoice' ),
            edit_description: __m( 'E-invoices cannot be edited.', 'MplEInvoice' ),
            back_to_list: __m( 'Return to E-Invoices', 'MplEInvoice' ),
        );
    }

    public function getLinks(): array
    {
        return [
            'list' => ns()->url( '/dashboard/mpl-einvoice/documents' ),
        ];
    }

    public function getColumns(): array
    {
        return CrudTable::columns(
            CrudTable::column( label: __m( 'Number', 'MplEInvoice' ), identifier: 'number', width: '150px' ),
            CrudTable::column( label: __m( 'Order', 'MplEInvoice' ), identifier: 'order_code', width: '140px' ),
            CrudTable::column( label: __m( 'Customer', 'MplEInvoice' ), identifier: 'buyer_name' ),
            CrudTable::column( label: __m( 'Type', 'MplEInvoice' ), identifier: 'type', width: '170px' ),
            CrudTable::column( label: __m( 'Total', 'MplEInvoice' ), identifier: 'total_gross', width: '120px' ),
            CrudTable::column( label: __m( 'Status', 'MplEInvoice' ), identifier: 'status', width: '110px' ),
            CrudTable::column( label: __m( 'Date', 'MplEInvoice' ), identifier: 'issued_at', width: '150px' ),
        );
    }

    public function setActions( CrudEntry $entry ): CrudEntry
    {
        $status = $entry->getOriginalValue( 'status' );

        $entry->{ '$cssClass' } = match ( $status ) {
            EInvoiceDocument::STATUS_ACCEPTED => 'success border text-sm',
            EInvoiceDocument::STATUS_SUBMITTED => 'info border text-sm',
            EInvoiceDocument::STATUS_REJECTED, EInvoiceDocument::STATUS_FAILED => 'error border text-sm',
            default => 'border text-sm',
        };

        $entry->action(
            identifier: 'download-xml',
            label: '<i class="mr-2 las la-file-code"></i> ' . __m( 'Download XML', 'MplEInvoice' ),
            type: 'TAB',
            url: ns()->url( '/dashboard/mpl-einvoice/documents/' . $entry->id . '/xml' ),
        );

        $entry->action(
            identifier: 'print',
            label: '<i class="mr-2 las la-print"></i> ' . __m( 'Print Tax Invoice', 'MplEInvoice' ),
            type: 'TAB',
            url: ns()->url( '/dashboard/mpl-einvoice/documents/' . $entry->id . '/print' ),
        );

        if ( in_array( $status, [ EInvoiceDocument::STATUS_GENERATED, EInvoiceDocument::STATUS_FAILED, EInvoiceDocument::STATUS_REJECTED ], true ) ) {
            $entry->action(
                identifier: 'submit',
                label: '<i class="mr-2 las la-paper-plane"></i> ' . ( $status === EInvoiceDocument::STATUS_GENERATED ? __m( 'Submit to ASP', 'MplEInvoice' ) : __m( 'Retry Submission', 'MplEInvoice' ) ),
                type: 'GET',
                url: ns()->url( '/api/mpl-einvoice/documents/' . $entry->id . '/submit' ),
                permissions: [ 'mpleinvoice.submit' ],
                confirm: [
                    'message' => __m( 'Would you like to send this e-invoice to the Accredited Service Provider?', 'MplEInvoice' ),
                ],
            );
        }

        return $entry;
    }

    public function hook( $query ): void
    {
        if ( empty( request()->query( 'direction' ) ) ) {
            $query->orderBy( 'mpl_einv_documents.id', 'desc' );
        }
    }
}
