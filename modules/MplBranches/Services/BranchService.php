<?php

namespace Modules\MplBranches\Services;

use App\Exceptions\NotAllowedException;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\MplBranches\Models\Branch;
use Modules\MplBranches\Models\BranchOrder;
use Modules\MplBranches\Models\BranchRegister;
use Modules\MplBranches\Models\BranchUser;
use Throwable;

class BranchService
{
    const SESSION_KEY = 'mplbranches.current_branch_id';

    const DEFAULT_CODE = 'MAIN';

    /**
     * Creates the default "Main Branch" when no default branch exists. Idempotent.
     */
    public function ensureDefaultBranch(): Branch
    {
        $default = Branch::default()->first();

        if ( $default instanceof Branch ) {
            return $default;
        }

        $branch = Branch::where( 'code', self::DEFAULT_CODE )->first() ?: new Branch;

        if ( ! $branch->exists ) {
            $branch->name = __m( 'Main Branch', 'MplBranches' );
            $branch->code = self::DEFAULT_CODE;
        }

        $branch->is_default = true;
        $branch->active = true;
        $branch->save();

        return $branch;
    }

    public function getDefaultBranch(): Branch
    {
        return Branch::default()->first() ?: $this->ensureDefaultBranch();
    }

    /**
     * Users who manage branches can work in any branch.
     */
    public function canAccessAllBranches( User $user ): bool
    {
        return $user->allowedTo( [ 'mplbranches.manage' ] );
    }

    /**
     * Active branches the user may work in.
     */
    public function getAllowedBranches( User $user ): Collection
    {
        if ( $this->canAccessAllBranches( $user ) ) {
            return Branch::active()->orderBy( 'name' )->get();
        }

        $ids = BranchUser::where( 'user_id', $user->id )->pluck( 'branch_id' );

        return Branch::active()->whereIn( 'id', $ids )->orderBy( 'name' )->get();
    }

    public function isAllowed( User $user, int $branchId ): bool
    {
        return $this->getAllowedBranches( $user )->contains( 'id', $branchId );
    }

    public function getPrimaryBranch( User $user ): ?Branch
    {
        $relation = BranchUser::where( 'user_id', $user->id )
            ->orderByDesc( 'is_primary' )
            ->orderBy( 'id' )
            ->get()
            ->first( fn ( BranchUser $relation ) => $relation->branch instanceof Branch && $relation->branch->active );

        return $relation?->branch;
    }

    /**
     * Current branch: valid session value, then primary branch, then default branch.
     */
    public function getCurrentBranch( ?User $user = null ): Branch
    {
        $user = $user ?: Auth::user();

        if ( ! $user instanceof User ) {
            return $this->getDefaultBranch();
        }

        return $this->resolveUserBranch( $user, true )[0];
    }

    /**
     * @return array{0: Branch, 1: string}
     */
    public function resolveUserBranch( User $user, bool $useSession ): array
    {
        if ( $useSession ) {
            $sessionBranchId = (int) $this->getSessionBranchId();

            if ( $sessionBranchId > 0 && $this->isAllowed( $user, $sessionBranchId ) ) {
                return [ Branch::find( $sessionBranchId ), BranchOrder::SOURCE_SESSION ];
            }
        }

        $primary = $this->getPrimaryBranch( $user );

        if ( $primary instanceof Branch ) {
            return [ $primary, BranchOrder::SOURCE_PRIMARY ];
        }

        return [ $this->getDefaultBranch(), BranchOrder::SOURCE_DEFAULT ];
    }

    public function switchBranch( User $user, int $branchId ): Branch
    {
        $branch = Branch::find( $branchId );

        if ( ! $branch instanceof Branch || ! $branch->active || ! $this->isAllowed( $user, $branchId ) ) {
            throw new NotAllowedException( __m( 'You are not allowed to work in this branch.', 'MplBranches' ) );
        }

        session()->put( self::SESSION_KEY, $branch->id );

        return $branch;
    }

    protected function getSessionBranchId(): ?int
    {
        try {
            $value = session()->get( self::SESSION_KEY );

            return $value === null ? null : (int) $value;
        } catch ( Throwable $exception ) {
            return null;
        }
    }

    public function getRegisterBranch( ?int $registerId ): ?Branch
    {
        if ( empty( $registerId ) ) {
            return null;
        }

        $relation = BranchRegister::where( 'register_id', $registerId )->first();

        return $relation?->branch;
    }

    /**
     * Branch resolution for an order: register branch, then the author's current
     * branch (session only when the author is the signed-in user), then the
     * author's primary branch, then the default branch.
     *
     * @return array{0: Branch, 1: string}
     */
    public function resolveBranchForOrder( Order $order ): array
    {
        $registerBranch = $this->getRegisterBranch( (int) $order->register_id );

        if ( $registerBranch instanceof Branch ) {
            return [ $registerBranch, BranchOrder::SOURCE_REGISTER ];
        }

        $author = $order->author_id ? User::find( $order->author_id ) : null;

        if ( $author instanceof User ) {
            return $this->resolveUserBranch( $author, (int) Auth::id() === (int) $author->id );
        }

        return [ $this->getDefaultBranch(), BranchOrder::SOURCE_DEFAULT ];
    }

    /**
     * Records the branch of an order. Never throws: order creation must not fail
     * because of branch tagging.
     */
    public function tagOrder( Order $order ): ?BranchOrder
    {
        try {
            [ $branch, $source ] = $this->resolveBranchForOrder( $order );
        } catch ( Throwable $exception ) {
            Log::error( 'MplBranches: unable to resolve the branch of order #' . $order->id . ': ' . $exception->getMessage() );
            $branch = null;
            $source = BranchOrder::SOURCE_FALLBACK;
        }

        try {
            $branch = $branch ?: $this->getDefaultBranch();

            $branchOrder = BranchOrder::where( 'order_id', $order->id )->first() ?: new BranchOrder;
            $branchOrder->order_id = $order->id;
            $branchOrder->branch_id = $branch->id;
            $branchOrder->register_id = $order->register_id ?: null;
            $branchOrder->author_id = $order->author_id;
            $branchOrder->source = $source;
            $branchOrder->save();

            return $branchOrder;
        } catch ( Throwable $exception ) {
            Log::error( 'MplBranches: unable to tag order #' . $order->id . ': ' . $exception->getMessage() );

            return null;
        }
    }

    /**
     * When a register is attached later (e.g. on payment), the register branch wins.
     */
    public function retagOrderFromRegister( Order $order ): void
    {
        try {
            $branchOrder = BranchOrder::where( 'order_id', $order->id )->first();

            if ( ! $branchOrder instanceof BranchOrder ) {
                $this->tagOrder( $order );

                return;
            }

            /**
             * Only a register attached after the tagging (first payment) may change
             * the branch: once a register is recorded, moving that register to another
             * branch or paying an instalment elsewhere must not move a past sale.
             */
            if ( ! empty( $branchOrder->register_id ) ) {
                return;
            }

            $registerBranch = $this->getRegisterBranch( (int) $order->register_id );

            if ( $registerBranch instanceof Branch && (int) $branchOrder->branch_id !== (int) $registerBranch->id ) {
                $branchOrder->branch_id = $registerBranch->id;
                $branchOrder->register_id = $order->register_id;
                $branchOrder->source = BranchOrder::SOURCE_REGISTER;
                $branchOrder->save();
            }
        } catch ( Throwable $exception ) {
            Log::error( 'MplBranches: unable to retag order #' . $order->id . ': ' . $exception->getMessage() );
        }
    }

    public function getOrderBranch( int $orderId ): ?Branch
    {
        return BranchOrder::where( 'order_id', $orderId )->first()?->branch;
    }

    /**
     * Replaces the users assigned to a branch. A user without primary branch gets this one as primary.
     */
    public function syncBranchUsers( Branch $branch, array $userIds ): void
    {
        $userIds = collect( $userIds )->map( fn ( $id ) => (int) $id )->filter()->unique()->values();

        $removed = BranchUser::where( 'branch_id', $branch->id )->whereNotIn( 'user_id', $userIds )->get();
        $removed->each( fn ( BranchUser $relation ) => $relation->delete() );

        foreach ( $userIds as $userId ) {
            $relation = BranchUser::firstOrNew( [ 'branch_id' => $branch->id, 'user_id' => $userId ] );

            if ( ! $relation->exists ) {
                $relation->is_primary = ! BranchUser::where( 'user_id', $userId )->where( 'is_primary', true )->exists();
                $relation->save();
            }
        }

        // users who lost their primary branch get another one promoted
        $removed->pluck( 'user_id' )->unique()->each( function ( $userId ) {
            if ( ! BranchUser::where( 'user_id', $userId )->where( 'is_primary', true )->exists() ) {
                $next = BranchUser::where( 'user_id', $userId )->orderBy( 'id' )->first();

                if ( $next instanceof BranchUser ) {
                    $next->is_primary = true;
                    $next->save();
                }
            }
        } );
    }

    /**
     * Replaces the registers of a branch. A register belongs to one branch only,
     * so assigning it here moves it from any other branch.
     */
    public function syncBranchRegisters( Branch $branch, array $registerIds ): void
    {
        $registerIds = collect( $registerIds )->map( fn ( $id ) => (int) $id )->filter()->unique()->values();

        BranchRegister::where( 'branch_id', $branch->id )
            ->whereNotIn( 'register_id', $registerIds )
            ->get()
            ->each( fn ( BranchRegister $relation ) => $relation->delete() );

        foreach ( $registerIds as $registerId ) {
            $relation = BranchRegister::firstOrNew( [ 'register_id' => $registerId ] );
            $relation->branch_id = $branch->id;
            $relation->save();
        }
    }

    public function setPrimaryBranch( User $user, Branch $branch ): void
    {
        BranchUser::where( 'user_id', $user->id )->where( 'branch_id', '!=', $branch->id )->update( [ 'is_primary' => false ] );

        $relation = BranchUser::firstOrNew( [ 'branch_id' => $branch->id, 'user_id' => $user->id ] );
        $relation->is_primary = true;
        $relation->save();
    }

    /**
     * Keeps exactly one default branch: promoting a branch demotes the others.
     */
    public function makeDefault( Branch $branch ): void
    {
        Branch::where( 'id', '!=', $branch->id )
            ->where( 'is_default', true )
            ->get()
            ->each( function ( Branch $other ) {
                $other->is_default = false;
                $other->save();
            } );
    }

    public function assertCanDelete( Branch $branch ): void
    {
        if ( $branch->is_default ) {
            throw new NotAllowedException( __m( 'The default branch cannot be deleted.', 'MplBranches' ) );
        }

        if ( BranchOrder::where( 'branch_id', $branch->id )->exists() ) {
            throw new NotAllowedException( __m( 'This branch has orders and cannot be deleted. Deactivate it instead.', 'MplBranches' ) );
        }
    }

    /**
     * Removes assignments once a branch is deleted (application managed, no cascade).
     */
    public function cleanupDeletedBranch( int $branchId ): void
    {
        BranchUser::where( 'branch_id', $branchId )->get()->each( fn ( BranchUser $relation ) => $relation->delete() );
        BranchRegister::where( 'branch_id', $branchId )->get()->each( fn ( BranchRegister $relation ) => $relation->delete() );
    }

    /**
     * Data for the receipt / template tags.
     */
    public function getReceiptData( Order $order ): array
    {
        $branch = $this->getOrderBranch( (int) $order->id );

        return [
            'branch_name' => $branch?->name ?? '',
            'branch_code' => $branch?->code ?? '',
            'branch_address' => collect( [ $branch?->address, $branch?->city, $branch?->emirate ] )->filter()->join( ', ' ),
            'branch_phone' => $branch?->phone ?? '',
            'branch_email' => $branch?->email ?? '',
            'branch_trn' => $branch?->trn ?? '',
            'branch_receipt_header' => $branch?->receipt_header ?? '',
            'branch_receipt_footer' => $branch?->receipt_footer ?? '',
        ];
    }
}
