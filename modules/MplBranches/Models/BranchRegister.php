<?php

namespace Modules\MplBranches\Models;

use App\Models\NsModel;
use App\Models\Register;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchRegister extends NsModel
{
    protected $table = 'mpl_br_branch_register';

    protected $fillable = [ 'register_id', 'branch_id' ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo( Branch::class, 'branch_id' );
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo( Register::class, 'register_id' );
    }
}
