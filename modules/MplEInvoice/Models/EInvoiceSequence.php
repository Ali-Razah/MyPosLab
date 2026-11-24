<?php

namespace Modules\MplEInvoice\Models;

use App\Models\NsModel;

/**
 * Gapless counter backing a document number series.
 *
 * @property int    $id
 * @property string $series
 * @property int    $last_number
 */
class EInvoiceSequence extends NsModel
{
    protected $table = 'mpl_einv_sequences';

    protected $casts = [
        'last_number' => 'integer',
    ];
}
