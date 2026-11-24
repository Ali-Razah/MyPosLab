<?php

namespace Modules\MplBranches\Models;

use App\Models\NsModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchUser extends NsModel
{
    protected $table = 'mpl_br_branch_user';

    protected $fillable = [ 'user_id', 'branch_id', 'is_primary' ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo( Branch::class, 'branch_id' );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo( User::class, 'user_id' );
    }
}
