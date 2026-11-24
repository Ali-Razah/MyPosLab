<?php

namespace Modules\MplWhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\MplWhatsApp\Http\Requests\SendReceiptRequest;
use Modules\MplWhatsApp\Models\WhatsAppMessage;
use Modules\MplWhatsApp\Services\PhoneNormalizer;
use Modules\MplWhatsApp\Services\WhatsAppConfig;
use Modules\MplWhatsApp\Services\WhatsAppReceiptService;

class WhatsAppReceiptController extends Controller
{
    public function __construct(
        private WhatsAppReceiptService $receiptService,
        private WhatsAppConfig $config,
        private PhoneNormalizer $phoneNormalizer,
        private OrderAccessService $orderAccessService,
    ) {}

    /**
     * Data needed by the "Send via WhatsApp" popup.
     *
     * @return array<string, mixed>
     */
    public function context( Request $request, Order $order ): array
    {
        $this->orderAccessService->authorizeOrFail( $request->user(), $order );
        $order->load( 'customer' );

        $customerPhone = $this->receiptService->customerPhone( $order );
        $lastMessage = WhatsAppMessage::where( 'order_id', $order->id )->latest( 'id' )->first();

        return [
            'enabled' => $this->config->isEnabled(),
            'mode' => $this->config->mode(),
            'country_code' => $this->config->countryCode(),
            'order' => [
                'id' => $order->id,
                'code' => $order->code,
                'total' => (string) ns()->currency->define( $order->total ),
                'payment_status' => $order->payment_status,
                'customer_name' => $this->receiptService->customerName( $order ),
            ],
            'customer_phone' => $customerPhone,
            'normalized_phone' => $this->phoneNormalizer->tryNormalize( $customerPhone, $this->config->countryCode() ),
            'last_message' => $lastMessage?->only( [ 'status', 'mode', 'phone', 'error', 'created_at' ] ),
        ];
    }

    public function send( SendReceiptRequest $request, Order $order ): JsonResponse
    {
        $this->orderAccessService->authorizeOrFail( $request->user(), $order );

        try {
            $result = $this->receiptService->send(
                order: $order,
                phone: $request->input( 'phone' ),
                userId: $request->user()->id,
            );
        } catch ( InvalidArgumentException $exception ) {
            throw ValidationException::withMessages( [ 'phone' => $exception->getMessage() ] );
        }

        unset( $result[ 'log_id' ] );

        return response()->json( $result, $result[ 'status' ] === 'success' ? 200 : 502 );
    }
}
