<?php

namespace Modules\MplBatches\Models;

use App\Models\NsModel;

/**
 * Batch number typed on the procurement screen, kept until the line is stocked.
 *
 * @property int    $id
 * @property int    $procurement_product_id
 * @property string $batch_number
 */
class ProcurementBatchNumber extends NsModel
{
    protected $table = 'mpl_batch_procurement_numbers';
}
