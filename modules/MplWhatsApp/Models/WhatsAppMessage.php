<?php

namespace Modules\MplWhatsApp\Models;

use App\Models\NsModel;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A WhatsApp receipt attempt for an order.
 *
 * @property int         $id
 * @property int         $order_id
 * @property string      $phone
 * @property string      $mode
 * @property string      $trigger
 * @property string      $status
 * @property string|null $provider_message_id
 * @property string|null $error
 * @property int|null    $user_id
 */
class WhatsAppMessage extends NsModel
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_LINK_CREATED = 'link_created';

    public const MODE_LINK = 'link';

    public const MODE_CLOUD_API = 'cloud_api';

    public const TRIGGER_MANUAL = 'manual';

    public const TRIGGER_AUTO = 'auto';

    protected $table = 'mpl_wa_messages';

    protected $fillable = [
        'order_id',
        'phone',
        'mode',
        'trigger',
        'status',
        'provider_message_id',
        'error',
        'user_id',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo( Order::class, 'order_id' );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo( User::class, 'user_id' );
    }
}
