<?php

namespace Modules\MplEInvoice\Models;

use App\Models\Customer;
use App\Models\NsModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\MplEInvoice\Services\TrnValidator;

/**
 * Buyer tax identity used for B2B tax invoices.
 *
 * @property int         $id
 * @property int         $customer_id
 * @property string|null $trn
 * @property string|null $legal_name
 * @property string|null $address
 * @property string|null $city
 * @property string|null $emirate
 * @property string      $country_code
 * @property string|null $peppol_id
 * @property int|null    $author_id
 */
class EInvoiceCustomerProfile extends NsModel
{
    protected $table = 'mpl_einv_customer_profiles';

    protected $fillable = [
        'customer_id',
        'trn',
        'legal_name',
        'address',
        'city',
        'emirate',
        'country_code',
        'peppol_id',
        'author_id',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo( Customer::class, 'customer_id' );
    }

    /**
     * A buyer is treated as a VAT registered business (B2B)
     * only when a structurally valid TRN is stored.
     */
    public function isBusiness(): bool
    {
        return TrnValidator::isValid( $this->trn );
    }
}
