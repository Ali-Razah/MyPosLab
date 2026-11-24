<?php

namespace Modules\MplBranches\Crud;

use App\Classes\CrudForm;
use App\Classes\CrudTable;
use App\Classes\FormInput;
use App\Exceptions\NotAllowedException;
use App\Models\Register;
use App\Models\Role;
use App\Models\User;
use App\Services\CrudEntry;
use App\Services\CrudService;
use App\Services\Helper;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\MplBranches\Models\Branch;
use Modules\MplBranches\Models\BranchRegister;
use Modules\MplBranches\Models\BranchUser;
use Modules\MplBranches\Services\BranchService;
use TorMorten\Eventy\Facades\Events as Hook;

class BranchCrud extends CrudService
{
    const AUTOLOAD = true;

    const IDENTIFIER = 'mplbranches.branches';

    protected $table = 'mpl_br_branches';

    protected $slug = 'mplbranches/branches';

    protected $namespace = 'mplbranches.branches';

    protected $model = Branch::class;

    protected $permissions = [
        'create' => 'mplbranches.manage',
        'read' => 'mplbranches.manage',
        'update' => 'mplbranches.manage',
        'delete' => 'mplbranches.manage',
    ];

    public $relations = [];

    protected $tabsRelations = [];

    public $pick = [];

    protected $listWhere = [];

    protected $whereIn = [];

    /**
     * Only branch columns are stored on the model. Users and registers are synced in afterPost/afterPut.
     */
    public $fillable = [
        'name',
        'code',
        'address',
        'city',
        'emirate',
        'phone',
        'email',
        'trn',
        'receipt_header',
        'receipt_footer',
        'is_default',
        'active',
    ];

    public function getLabels()
    {
        return CrudTable::labels(
            list_title: __m( 'Branches', 'MplBranches' ),
            list_description: __m( 'Manage the shops of this business. Sales, cash registers and staff are tagged by branch. The product catalogue and the stock are shared by all branches (per-branch stock is not supported yet).', 'MplBranches' ),
            no_entry: __m( 'No branch has been registered.', 'MplBranches' ),
            create_new: __m( 'Add a new branch', 'MplBranches' ),
            create_title: __m( 'Create a branch', 'MplBranches' ),
            create_description: __m( 'Register a branch, its receipt details, its staff and its cash registers. Stock stays shared with the other branches.', 'MplBranches' ),
            edit_title: __m( 'Edit branch', 'MplBranches' ),
            edit_description: __m( 'Modify the branch details, staff and cash registers. Stock stays shared with the other branches.', 'MplBranches' ),
            back_to_list: __m( 'Return to Branches', 'MplBranches' ),
        );
    }

    public function isEnabled( $feature ): bool
    {
        return false;
    }

    public function getForm( $entry = null )
    {
        $staffOptions = Helper::toJsOptions(
            User::whereHas( 'roles', fn ( $query ) => $query->whereNotIn( 'namespace', [ Role::STORECUSTOMER ] ) )->orderBy( 'username' )->get(),
            [ 'id', 'username' ]
        );

        $assignedUsers = $entry instanceof Branch
            ? BranchUser::where( 'branch_id', $entry->id )->pluck( 'user_id' )->map( fn ( $id ) => (int) $id )->toArray()
            : [];

        $assignedRegisters = $entry instanceof Branch
            ? BranchRegister::where( 'branch_id', $entry->id )->pluck( 'register_id' )->map( fn ( $id ) => (int) $id )->toArray()
            : [];

        $yesNo = Helper::kvToJsOptions( [ 1 => __m( 'Yes', 'MplBranches' ), 0 => __m( 'No', 'MplBranches' ) ] );

        return CrudForm::form(
            main: FormInput::text(
                label: __m( 'Name', 'MplBranches' ),
                name: 'name',
                value: $entry->name ?? '',
                validation: 'required',
                description: __m( 'Name of the branch as shown to staff, on reports and on receipts (e.g. "Al Nahda Grocery").', 'MplBranches' ),
            ),
            tabs: CrudForm::tabs(
                CrudForm::tab(
                    identifier: 'general',
                    label: __m( 'General', 'MplBranches' ),
                    fields: [
                        FormInput::text(
                            label: __m( 'Code', 'MplBranches' ),
                            name: 'code',
                            value: $entry->code ?? '',
                            validation: 'required',
                            description: __m( 'Short unique code used to identify the branch on reports and filters (e.g. DXB-01).', 'MplBranches' ),
                        ),
                        FormInput::switch(
                            label: __m( 'Active', 'MplBranches' ),
                            name: 'active',
                            options: $yesNo,
                            value: isset( $entry ) ? (int) $entry->active : 1,
                            validation: 'required',
                            description: __m( 'Inactive branches cannot be selected by staff. Use this instead of deleting a branch that has sales.', 'MplBranches' ),
                        ),
                        FormInput::switch(
                            label: __m( 'Default Branch', 'MplBranches' ),
                            name: 'is_default',
                            options: $yesNo,
                            value: isset( $entry ) ? (int) $entry->is_default : 0,
                            validation: 'required',
                            description: __m( 'Orders that cannot be linked to a register or a staff branch are recorded here. Only one branch can be the default: selecting this one replaces the previous default.', 'MplBranches' ),
                        ),
                        FormInput::textarea(
                            label: __m( 'Address', 'MplBranches' ),
                            name: 'address',
                            value: $entry->address ?? '',
                            description: __m( 'Street address printed on the receipts of this branch.', 'MplBranches' ),
                        ),
                        FormInput::text(
                            label: __m( 'City', 'MplBranches' ),
                            name: 'city',
                            value: $entry->city ?? '',
                            description: __m( 'City of the branch, printed with the address.', 'MplBranches' ),
                        ),
                        FormInput::text(
                            label: __m( 'Emirate / Region', 'MplBranches' ),
                            name: 'emirate',
                            value: $entry->emirate ?? '',
                            description: __m( 'Emirate or region of the branch (e.g. Dubai, Sharjah).', 'MplBranches' ),
                        ),
                        FormInput::text(
                            label: __m( 'Phone', 'MplBranches' ),
                            name: 'phone',
                            value: $entry->phone ?? '',
                            description: __m( 'Branch phone number printed on receipts.', 'MplBranches' ),
                        ),
                        FormInput::email(
                            label: __m( 'Email', 'MplBranches' ),
                            name: 'email',
                            value: $entry->email ?? '',
                            validation: 'nullable|email',
                            description: __m( 'Contact email of the branch.', 'MplBranches' ),
                        ),
                        FormInput::text(
                            label: __m( 'TRN', 'MplBranches' ),
                            name: 'trn',
                            value: $entry->trn ?? '',
                            description: __m( 'Tax Registration Number printed on the receipts of this branch. Leave empty when the branch uses the company TRN.', 'MplBranches' ),
                        ),
                    ]
                ),
                CrudForm::tab(
                    identifier: 'receipt',
                    label: __m( 'Receipt', 'MplBranches' ),
                    fields: [
                        FormInput::textarea(
                            label: __m( 'Receipt Header', 'MplBranches' ),
                            name: 'receipt_header',
                            value: $entry->receipt_header ?? '',
                            description: __m( 'Text printed above the receipt for orders of this branch.', 'MplBranches' ),
                        ),
                        FormInput::textarea(
                            label: __m( 'Receipt Footer', 'MplBranches' ),
                            name: 'receipt_footer',
                            value: $entry->receipt_footer ?? '',
                            description: __m( 'Text printed below the receipt for orders of this branch (e.g. return policy).', 'MplBranches' ),
                        ),
                    ]
                ),
                CrudForm::tab(
                    identifier: 'assignments',
                    label: __m( 'Staff & Registers', 'MplBranches' ),
                    fields: [
                        FormInput::multiselect(
                            label: __m( 'Staff', 'MplBranches' ),
                            name: 'user_ids',
                            options: $staffOptions,
                            value: $assignedUsers,
                            description: __m( 'Users who can work in this branch. A user may belong to several branches; the first branch assigned becomes the user\'s primary branch.', 'MplBranches' ),
                        ),
                        FormInput::multiselect(
                            label: __m( 'Cash Registers', 'MplBranches' ),
                            name: 'register_ids',
                            options: Helper::toJsOptions( Register::orderBy( 'name' )->get(), [ 'id', 'name' ] ),
                            value: $assignedRegisters,
                            description: __m( 'Registers located in this branch. Sales made on these registers are always recorded for this branch. A register belongs to one branch only.', 'MplBranches' ),
                        ),
                    ]
                ),
            )
        );
    }

    public function filterPostInputs( $inputs )
    {
        return $this->filterBranchInputs( $inputs );
    }

    public function filterPutInputs( $inputs, Branch $entry )
    {
        return $this->filterBranchInputs( $inputs );
    }

    protected function filterBranchInputs( array $inputs ): array
    {
        unset( $inputs[ 'user_ids' ], $inputs[ 'register_ids' ] );

        $inputs[ 'code' ] = strtoupper( trim( (string) ( $inputs[ 'code' ] ?? '' ) ) );
        $inputs[ 'is_default' ] = (bool) (int) ( $inputs[ 'is_default' ] ?? 0 );
        $inputs[ 'active' ] = (bool) (int) ( $inputs[ 'active' ] ?? 1 );

        return $inputs;
    }

    public function beforePost( $request )
    {
        $this->allowedTo( 'create' );
        $this->validateBranch( $request, null );

        return $request;
    }

    public function afterPost( $request, Branch $entry )
    {
        $this->afterSave( $request, $entry );

        return $request;
    }

    public function beforePut( $request, $entry )
    {
        $this->allowedTo( 'update' );
        $this->validateBranch( $request, $entry );

        return $request;
    }

    public function afterPut( $request, $entry )
    {
        $this->afterSave( $request, $entry );

        return $request;
    }

    /**
     * Business validation not expressible in the form rules.
     */
    protected function validateBranch( array $request, ?Branch $entry ): void
    {
        $code = strtoupper( trim( (string) ( $request[ 'code' ] ?? '' ) ) );
        $isDefault = (bool) (int) ( $request[ 'is_default' ] ?? 0 );
        $active = (bool) (int) ( $request[ 'active' ] ?? 1 );

        validator(
            [ 'code' => $code ],
            [ 'code' => [ 'required', 'max:50', Rule::unique( ( new Branch )->getTable(), 'code' )->ignore( $entry?->id ) ] ],
            [],
            [ 'code' => __m( 'Code', 'MplBranches' ) ]
        )->validate();

        if ( $entry instanceof Branch && $entry->is_default && ! $isDefault ) {
            throw new NotAllowedException( __m( 'A default branch is required. Set another branch as default instead of removing this flag.', 'MplBranches' ) );
        }

        if ( $isDefault && ! $active ) {
            throw new NotAllowedException( __m( 'The default branch must stay active.', 'MplBranches' ) );
        }

        $registerIds = collect( $request[ 'register_ids' ] ?? [] )->map( fn ( $id ) => (int) $id )->filter();

        if ( $registerIds->isNotEmpty() && Register::whereIn( 'id', $registerIds )->count() !== $registerIds->unique()->count() ) {
            throw new NotAllowedException( __m( 'One of the selected cash registers does not exist.', 'MplBranches' ) );
        }
    }

    protected function afterSave( array $request, Branch $entry ): void
    {
        /**
         * @var BranchService $branchService
         */
        $branchService = app()->make( BranchService::class );

        if ( $entry->is_default ) {
            $branchService->makeDefault( $entry );
        }

        $branchService->syncBranchUsers( $entry, (array) ( $request[ 'user_ids' ] ?? [] ) );
        $branchService->syncBranchRegisters( $entry, (array) ( $request[ 'register_ids' ] ?? [] ) );
    }

    public function get( $param )
    {
        switch ( $param ) {
            case 'model': return $this->model;
        }
    }

    public function beforeDelete( $namespace, $id, $model )
    {
        if ( $namespace === self::IDENTIFIER ) {
            $this->allowedTo( 'delete' );

            app()->make( BranchService::class )->assertCanDelete( $model );
            app()->make( BranchService::class )->cleanupDeletedBranch( (int) $model->id );
        }
    }

    public function getColumns(): array
    {
        return CrudTable::columns(
            CrudTable::column( label: __m( 'Name', 'MplBranches' ), identifier: 'name' ),
            CrudTable::column( label: __m( 'Code', 'MplBranches' ), identifier: 'code', width: '120px' ),
            CrudTable::column( label: __m( 'City', 'MplBranches' ), identifier: 'city' ),
            CrudTable::column( label: __m( 'Emirate', 'MplBranches' ), identifier: 'emirate' ),
            CrudTable::column( label: __m( 'Default', 'MplBranches' ), identifier: 'is_default', width: '100px' ),
            CrudTable::column( label: __m( 'Active', 'MplBranches' ), identifier: 'active', width: '100px' ),
            CrudTable::column( label: __m( 'Created At', 'MplBranches' ), identifier: 'created_at', width: '160px' ),
        );
    }

    public function setActions( CrudEntry $entry ): CrudEntry
    {
        $entry->is_default = (int) $entry->is_default === 1 ? __m( 'Yes', 'MplBranches' ) : __m( 'No', 'MplBranches' );
        $entry->active = (int) $entry->active === 1 ? __m( 'Yes', 'MplBranches' ) : __m( 'No', 'MplBranches' );

        $entry->action(
            identifier: 'edit',
            label: '<i class="mr-2 las la-edit"></i> ' . __m( 'Edit', 'MplBranches' ),
            type: 'GOTO',
            url: ns()->route( 'mplbranches.dashboard.branches.edit', [ 'branch' => $entry->id ] ),
        );

        $entry->action(
            identifier: 'delete',
            label: '<i class="mr-2 las la-trash"></i> ' . __m( 'Delete', 'MplBranches' ),
            type: 'DELETE',
            url: ns()->url( '/api/crud/' . self::IDENTIFIER . '/' . $entry->id ),
            confirm: [
                'message' => __m( 'Would you like to delete this branch? Branches with orders can only be deactivated.', 'MplBranches' ),
            ],
        );

        return $entry;
    }

    public function bulkAction( Request $request )
    {
        if ( $request->input( 'action' ) == 'delete_selected' ) {
            $this->allowedTo( 'delete' );

            $status = [ 'success' => 0, 'error' => 0 ];

            /**
             * @var BranchService $branchService
             */
            $branchService = app()->make( BranchService::class );

            foreach ( (array) $request->input( 'entries' ) as $id ) {
                $entity = Branch::find( $id );

                try {
                    if ( ! $entity instanceof Branch ) {
                        throw new NotAllowedException;
                    }

                    $branchService->assertCanDelete( $entity );
                    $branchService->cleanupDeletedBranch( (int) $entity->id );
                    $entity->delete();
                    $status[ 'success' ]++;
                } catch ( NotAllowedException $exception ) {
                    $status[ 'error' ]++;
                }
            }

            return $status;
        }

        return Hook::filter( $this->namespace . '-catch-action', false, $request );
    }

    public function getLinks(): array
    {
        return [
            'list' => ns()->route( 'mplbranches.dashboard.branches' ),
            'create' => ns()->route( 'mplbranches.dashboard.branches.create' ),
            'edit' => ns()->url( '/dashboard/mplbranches/branches/edit/{id}' ),
            'post' => ns()->url( '/api/crud/' . self::IDENTIFIER ),
            'put' => ns()->url( '/api/crud/' . self::IDENTIFIER . '/{id}' ),
        ];
    }

    public function getBulkActions(): array
    {
        return Hook::filter( $this->namespace . '-bulk', [
            [
                'label' => __m( 'Delete Selected Branches', 'MplBranches' ),
                'identifier' => 'delete_selected',
                'url' => ns()->route( 'ns.api.crud-bulk-actions', [
                    'namespace' => $this->namespace,
                ] ),
            ],
        ] );
    }

    public function getExports()
    {
        return [];
    }
}
