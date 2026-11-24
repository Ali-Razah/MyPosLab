<?php

namespace Modules\MplWhatsApp\Jobs;

use App\Models\Order;
use App\Traits\NsSerialize;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\MplWhatsApp\Models\WhatsAppMessage;
use Modules\MplWhatsApp\Services\WhatsAppConfig;
use Modules\MplWhatsApp\Services\WhatsAppReceiptService;
use Throwable;

/**
 * Automatically sends the receipt through the WhatsApp Cloud API once an order is paid.
 *
 * Delivery failures are stored on mpl_wa_messages (status "failed" + error) and
 * reported to the log instead of being rethrown: with a "sync" queue, an exception
 * here would turn an already committed POS sale into an HTTP error.
 */
class SendWhatsAppReceiptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, NsSerialize, Queueable;

    public int $tries = 1;

    public function __construct( public Order $order, public ?string $phone = null )
    {
        $this->prepareSerialization();
    }

    public function handle( WhatsAppConfig $config, WhatsAppReceiptService $service ): void
    {
        if ( ! $config->shouldAutoSend() ) {
            return;
        }

        /**
         * Never auto-send twice for the same order (payment status can flip
         * back and forth, the job can be retried by an operator...).
         */
        $alreadySent = WhatsAppMessage::where( 'order_id', $this->order->id )
            ->where( 'trigger', WhatsAppMessage::TRIGGER_AUTO )
            ->where( 'status', WhatsAppMessage::STATUS_SENT )
            ->exists();

        if ( $alreadySent ) {
            return;
        }

        try {
            $result = $service->send( $this->order, $this->phone, null, WhatsAppMessage::TRIGGER_AUTO );
        } catch ( InvalidArgumentException $exception ) {
            Log::notice( 'MplWhatsApp: automatic receipt skipped.', [
                'order_id' => $this->order->id,
                'reason' => $exception->getMessage(),
            ] );

            return;
        } catch ( Throwable $exception ) {
            report( $exception );

            return;
        }

        if ( $result[ 'status' ] !== 'success' ) {
            Log::error( 'MplWhatsApp: automatic receipt failed.', [
                'order_id' => $this->order->id,
                'log_id' => $result[ 'log_id' ],
                'error' => $result[ 'message' ],
            ] );
        }
    }
}
