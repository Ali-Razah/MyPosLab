<?php

namespace Modules\MplBranches\Models;

use App\Models\NsModel;
use App\Models\Order;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchOrder extends NsModel
{
    /**
     * How the branch was resolved for the order.
     */
    const SOURCE_REGISTER = 'register';

    const SOURCE_SESSION = 'session';

    const SOURCE_PRIMARY = 'primary';

    const SOURCE_DEFAULT = 'default';

    const SOURCE_FALLBACK = 'fallback';

    protected $table = 'mpl_br_branch_orders';

    protected $fillable = [ 'order_id', 'branch_id', 'register_id', 'author_id', 'source' ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo( Branch::class, 'branch_id' );
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo( Order::class, 'order_id' );
    }
}
