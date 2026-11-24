<?php

namespace Modules\MplBatches\Crud;

use App\Classes\CrudForm;
use App\Classes\CrudTable;
use App\Classes\FormInput;
use App\Services\CrudEntry;
use App\Services\CrudService;
use App\Services\Helper;
use Illuminate\Http\Request;
use Modules\MplBatches\Models\Batch;
use Modules\MplBatches\Services\BatchService;
use TorMorten\Eventy\Facades\Events as Hook;

class BatchCrud extends CrudService
{
    const AUTOLOAD = true;

    const IDENTIFIER = 'mplbatches.batches';

    protected $table = 'mpl_batch_batches';

    protected $mainRoute = 'mplbatches.batches';

    protected $namespace = 'mplbatches.batches';

    protected $model = Batch::class;

    /**
     * Batches are only created by stock receipts and never deleted:
     * they are the traceability record of a lot.
     */
    protected $permissions = [
        'create' => false,
        'read' => 'mplbatches.read',
        'update' => 'mplbatches.manage',
        'delete' => false,
    ];

    protected $showCheckboxes = false;

    public $relations = [
        [ 'nexopos_products as product', 'mpl_batch_batches.product_id', '=', 'product.id' ],
        [ 'nexopos_units as unit', 'mpl_batch_batches.unit_id', '=', 'unit.id' ],
        'leftJoin' => [
            [ 'nexopos_procurements as procurement', 'mpl_batch_batches.procurement_id', '=', 'procurement.id' ],
        ],
    ];

    public $pick = [
        'product' => [ 'name', 'sku' ],
        'unit' => [ 'name' ],
        'procurement' => [ 'name' ],
    ];

    public function __construct()
    {
        parent::__construct();

        $table = $this->hookTableName( $this->table );

        $this->queryFilters = [
            [
                'type' => 'select',
                'name' => $table . '.status',
                'label' => __m( 'Status', 'MplBatches' ),
                'description' => __m( 'Only show batches having this status.', 'MplBatches' ),
                'options' => Helper::kvToJsOptions( $this->getStatusLabels() ),
            ],
            [
                'type' => 'text',
                'name' => $table . '.batch_number',
                'label' => __m( 'Batch Number', 'MplBatches' ),
                'description' => __m( 'Exact batch number, e.g. the lot printed on the pack (use the search box for partial matches).', 'MplBatches' ),
            ],
            [
                'type' => 'text',
                'name' => 'product.sku',
                'label' => __m( 'Product SKU', 'MplBatches' ),
                'description' => __m( 'Only show batches of the product having this exact SKU.', 'MplBatches' ),
            ],
        ];
    }

    public function getStatusLabels(): array
    {
        return [
            Batch::STATUS_ACTIVE => __m( 'Active', 'MplBatches' ),
            Batch::STATUS_DEPLETED => __m( 'Depleted', 'MplBatches' ),
            Batch::STATUS_EXPIRED => __m( 'Expired', 'MplBatches' ),
            Batch::STATUS_WRITTEN_OFF => __m( 'Written Off', 'MplBatches' ),
        ];
    }

    public function getLabels()
    {
        return [
            'list_title' => __m( 'Batches', 'MplBatches' ),
            'list_description' => __m( 'Every stock receipt creates a batch. Sales consume batches by earliest expiry first (FEFO).', 'MplBatches' ),
            'no_entry' => __m( 'No batch has been recorded yet. Batches are created when a procurement is stocked.', 'MplBatches' ),
            'create_new' => __m( 'Add a batch', 'MplBatches' ),
            'create_title' => __m( 'Create a batch', 'MplBatches' ),
            'create_description' => __m( 'Batches are created from procurements.', 'MplBatches' ),
            'edit_title' => __m( 'Edit Batch', 'MplBatches' ),
            'edit_description' => __m( 'Correct the batch number or the expiry date.', 'MplBatches' ),
            'back_to_list' => __m( 'Return to Batches', 'MplBatches' ),
        ];
    }

    public function isEnabled( $feature ): bool
    {
        return false;
    }

    public function hook( $query ): void
    {
        $table = $this->hookTableName( $this->table );

        $query->orderByRaw( 'CASE WHEN ' . $query->getGrammar()->wrap( $table . '.expiry_date' ) . ' IS NULL THEN 1 ELSE 0 END' )
            ->orderBy( $table . '.expiry_date' )
            ->orderBy( $table . '.id', 'desc' );
    }

    public function getForm( $entry = null )
    {
        return CrudForm::form(
            main: FormInput::text(
                label: __m( 'Batch Number', 'MplBatches' ),
                name: 'batch_number',
                value: $entry->batch_number ?? '',
                validation: 'required|max:190',
                description: __m( 'Lot / batch number printed by the manufacturer. Used to trace which sales received this lot (recalls).', 'MplBatches' ),
            ),
            tabs: CrudForm::tabs(
                CrudForm::tab(
                    label: __m( 'General', 'MplBatches' ),
                    identifier: 'general',
                    fields: CrudForm::fields(
                        FormInput::date(
                            label: __m( 'Expiry Date', 'MplBatches' ),
                            name: 'expiry_date',
                            value: $entry->expiry_date ?? '',
                            description: __m( 'Last day the batch can be sold. Leave empty for batches that never expire (they are sold last).', 'MplBatches' ),
                        ),
                    )
                )
            )
        );
    }

    public function filterPutInputs( $inputs, Batch $entry )
    {
        $inputs[ 'batch_number' ] = trim( (string) ( $inputs[ 'batch_number' ] ?? $entry->batch_number ) );
        $inputs[ 'expiry_date' ] = ! empty( $inputs[ 'expiry_date' ] ) ? substr( (string) $inputs[ 'expiry_date' ], 0, 10 ) : null;

        return collect( $inputs )->only( [ 'batch_number', 'expiry_date' ] )->toArray();
    }

    public function beforePut( $request, $entry )
    {
        $this->allowedTo( 'update' );

        return $request;
    }

    public function afterPut( $request, Batch $entry )
    {
        /**
         * A corrected expiry date can move a batch between active and expired.
         */
        $entry->refresh();
        app()->make( BatchService::class )->refreshStatus( $entry );
        $entry->save();

        return $request;
    }

    public function beforeDelete( $namespace, $id, $model )
    {
        $this->allowedTo( 'delete' );
    }

    public function getColumns(): array
    {
        return CrudTable::columns(
            CrudTable::column( __m( 'Product', 'MplBatches' ), 'product_name' ),
            CrudTable::column( __m( 'Batch No.', 'MplBatches' ), 'batch_number' ),
            CrudTable::column( __m( 'Expiry', 'MplBatches' ), 'expiry_date' ),
            CrudTable::column( __m( 'Remaining', 'MplBatches' ), 'quantity_remaining' ),
            CrudTable::column( __m( 'Received', 'MplBatches' ), 'quantity_received' ),
            CrudTable::column( __m( 'Unit', 'MplBatches' ), 'unit_name' ),
            CrudTable::column( __m( 'Status', 'MplBatches' ), 'status' ),
            CrudTable::column( __m( 'Procurement', 'MplBatches' ), 'procurement_name' ),
            CrudTable::column( __m( 'Received On', 'MplBatches' ), 'created_at' ),
        );
    }

    public function setActions( CrudEntry $entry ): CrudEntry
    {
        /**
         * @var BatchService $batchService
         */
        $batchService = app()->make( BatchService::class );
        $today = $batchService->today();
        $rawStatus = $entry->getOriginalValue( 'status' ) ?? $entry->status;
        $expiry = $entry->expiry_date ? substr( (string) $entry->expiry_date, 0, 10 ) : null;

        $entry->status = $this->getStatusLabels()[ $rawStatus ] ?? $rawStatus;
        $entry->expiry_date = $expiry ?: __m( 'No expiry', 'MplBatches' );
        $entry->procurement_name = $entry->procurement_name ?: __m( 'N/A', 'MplBatches' );

        if ( $expiry && in_array( $rawStatus, [ Batch::STATUS_ACTIVE, Batch::STATUS_EXPIRED ], true ) && (float) $entry->getOriginalValue( 'quantity_remaining' ) > 0 ) {
            $criticalLimit = now( ns()->date->getTimezone() )->addDays( $batchService->getCriticalDays() )->toDateString();

            if ( $expiry <= $today ) {
                $entry->addClass( 'error' );
            } elseif ( $expiry <= $criticalLimit ) {
                $entry->addClass( 'warning' );
            }
        }

        $entry->action(
            identifier: 'edit',
            label: __m( 'Edit', 'MplBatches' ),
            type: 'GOTO',
            url: ns()->route( 'mplbatches.batches.edit', [ 'batch' => $entry->id ] ),
            permissions: [ 'mplbatches.manage' ],
        );

        $entry->action(
            identifier: 'allocations',
            label: __m( 'Sales & Movements', 'MplBatches' ),
            type: 'GOTO',
            url: ns()->route( 'mplbatches.batches.allocations', [ 'batch' => $entry->id ] ),
            permissions: [ 'mplbatches.read' ],
        );

        if ( $rawStatus !== Batch::STATUS_WRITTEN_OFF && (float) $entry->getOriginalValue( 'quantity_remaining' ) > 0 ) {
            $entry->action(
                identifier: 'write_off',
                label: __m( 'Write Off', 'MplBatches' ),
                type: 'GET',
                url: ns()->url( '/api/mplbatches/batches/' . $entry->id . '/write-off' ),
                confirm: [
                    'title' => __m( 'Write off this batch?', 'MplBatches' ),
                    'message' => sprintf(
                        __m( 'The remaining %s of batch %s will be removed from the stock and recorded as an adjustment. This cannot be undone.', 'MplBatches' ),
                        $entry->getOriginalValue( 'quantity_remaining' ),
                        $entry->batch_number
                    ),
                ],
                permissions: [ 'mplbatches.manage' ],
            );
        }

        return $entry;
    }

    public function bulkAction( Request $request )
    {
        return Hook::filter( $this->namespace . '-catch-action', false, $request );
    }

    public function getLinks(): array
    {
        return [
            'list' => ns()->route( 'mplbatches.batches' ),
            'edit' => ns()->url( '/dashboard/mplbatches/batches/edit/' ),
            'put' => ns()->url( '/api/crud/' . self::IDENTIFIER . '/{id}' ),
        ];
    }

    public function getBulkActions(): array
    {
        return Hook::filter( $this->namespace . '-bulk', [] );
    }

    public function getExports()
    {
        return [];
    }
}
