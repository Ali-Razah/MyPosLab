<?php

namespace Modules\MplWhatsApp\Http\Controllers;

use App\Classes\Hook;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentType;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use Modules\MplWhatsApp\Services\ReceiptLinkService;
use Modules\MplWhatsApp\Services\WhatsAppConfig;

/**
 * Public (no login) receipt page opened from the WhatsApp message.
 */
class PublicReceiptController extends Controller
{
    public function __construct(
        private ReceiptLinkService $receiptLinkService,
        private WhatsAppConfig $config,
    ) {}

    public function show( Request $request, string $order ): Response
    {
        $model = ctype_digit( $order ) ? Order::find( (int) $order ) : null;

        /**
         * Unknown orders and bad signatures answer the same way so the
         * page cannot be used to probe order ids.
         */
        if (
            ! $this->config->isEnabled()
            || ! $model instanceof Order
            || ! $this->receiptLinkService->isValid( $model, $request->query( 'expires' ), $request->query( 'signature' ) )
        ) {
            return response()
                ->view( 'MplWhatsApp::receipt-invalid', [], 403 )
                ->withHeaders( $this->headers() );
        }

        $model->load( [ 'customer', 'products.unit', 'payments', 'taxes', 'refunds' ] );

        $paymentTypes = PaymentType::get()->mapWithKeys( fn( PaymentType $type ) => [ $type->identifier => $type->label ] );

        return response( View::make( 'MplWhatsApp::receipt', [
            'order' => $model,
            'products' => Hook::filter( 'ns-receipt-products', $model->combinedProducts ),
            'paymentTypes' => $paymentTypes,
            'storeName' => ns()->option->get( 'ns_store_name', config( 'app.name' ) ),
            'logo' => ns()->option->get( 'ns_invoice_receipt_logo' ),
            'footer' => ns()->option->get( 'ns_invoice_receipt_footer' ),
        ] ) )->withHeaders( $this->headers() );
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
        ];
    }
}
