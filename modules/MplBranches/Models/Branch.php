<?php

namespace Modules\MplBranches\Models;

use App\Models\NsModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int    $id
 * @property string $name
 * @property string $code
 * @property bool   $is_default
 * @property bool   $active
 */
class Branch extends NsModel
{
    protected $table = 'mpl_br_branches';

    protected $fillable = [
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

    protected $casts = [
        'is_default' => 'boolean',
        'active' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany( BranchUser::class, 'branch_id' );
    }

    public function registers(): HasMany
    {
        return $this->hasMany( BranchRegister::class, 'branch_id' );
    }

    public function orders(): HasMany
    {
        return $this->hasMany( BranchOrder::class, 'branch_id' );
    }

    public function scopeActive( Builder $query ): Builder
    {
        return $query->where( 'active', true );
    }

    public function scopeDefault( Builder $query ): Builder
    {
        return $query->where( 'is_default', true );
    }
}
