<?php

namespace Modules\MplBranches\Filters;

use App\Classes\CrudTable;
use App\Services\Helper;
use Modules\MplBranches\Models\Branch;
use Throwable;

/**
 * Extends the core Orders list (App\Crud\OrderCrud) without core edits:
 * joins the branch tag, adds a "Branch" column and a "Branch" query filter.
 */
class OrderCrudFilter
{
    const ORDER_ALIAS = 'mplbrorder';

    const BRANCH_ALIAS = 'mplbranch';

    public static function relations( array $relations ): array
    {
        $relations[ 'leftJoin' ] = array_merge( $relations[ 'leftJoin' ] ?? [], [
            [ 'mpl_br_branch_orders as ' . self::ORDER_ALIAS, 'nexopos_orders.id', '=', self::ORDER_ALIAS . '.order_id' ],
            [ 'mpl_br_branches as ' . self::BRANCH_ALIAS, self::ORDER_ALIAS . '.branch_id', '=', self::BRANCH_ALIAS . '.id' ],
        ] );

        return $relations;
    }

    public static function picked( array $pick ): array
    {
        $pick[ self::ORDER_ALIAS ] = [ 'branch_id' ];
        $pick[ self::BRANCH_ALIAS ] = [ 'name' ];

        return $pick;
    }

    public static function columns( array $columns ): array
    {
        $branchColumn = CrudTable::columns( CrudTable::column(
            label: __m( 'Branch', 'MplBranches' ),
            identifier: self::BRANCH_ALIAS . '_name',
            sort: false,
            width: '140px',
        ) );

        if ( array_key_exists( 'customer_first_name', $columns ) ) {
            return array_insert_after( $columns, 'customer_first_name', $branchColumn );
        }

        return array_merge( $columns, $branchColumn );
    }

    /**
     * The filter name is the joined column: core applies unknown filter keys
     * as "where key = value", which targets the joined branch tag.
     */
    public static function queryFilters( array $filters ): array
    {
        try {
            $filters[] = [
                'type' => 'select',
                'label' => __m( 'Branch', 'MplBranches' ),
                'name' => self::ORDER_ALIAS . '.branch_id',
                'description' => __m( 'Restrict the orders to the selected branch.', 'MplBranches' ),
                'options' => Helper::toJsOptions( Branch::orderBy( 'name' )->get(), [ 'id', 'name' ] ),
            ];
        } catch ( Throwable $exception ) {
            // tables not migrated yet
        }

        return $filters;
    }
}
