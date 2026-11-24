<?php

namespace Modules\MplWhatsApp\Tests\Feature;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\MplWhatsApp\Jobs\SendWhatsAppReceiptJob;
use Modules\MplWhatsApp\Models\WhatsAppMessage;
use Modules\MplWhatsApp\Services\WhatsAppConfig;
use Modules\MplWhatsApp\Tests\MplWhatsAppTestCase;

final class AutoSendTest extends MplWhatsAppTestCase
{
    public function test_a_paid_order_queues_the_automatic_receipt_when_enabled(): void
    {
        $this->configureCloudApi( autoSend: true );
        $customer = $this->createCustomer();

        Queue::fake();

        $order = $this->createPaidOrder( $customer );

        $this->assertSame( Order::PAYMENT_PAID, $order->payment_status );
        Queue::assertPushed( SendWhatsAppReceiptJob::class, fn( SendWhatsAppReceiptJob $job ) => $job->order->id === $order->id );
    }

    public function test_nothing_is_queued_when_auto_send_is_off_or_in_link_mode_or_without_phone(): void
    {
        $customer = $this->createCustomer();
        $withoutPhone = $this->createCustomer( null );

        Queue::fake();

        $this->configureCloudApi( autoSend: false );
        $this->createPaidOrder( $customer );

        $this->configure( [ WhatsAppConfig::AUTO_SEND => 'yes', WhatsAppConfig::MODE => 'link' ] );
        $this->createPaidOrder( $customer );

        $this->configure( [ WhatsAppConfig::MODE => 'cloud_api' ] );
        $this->createPaidOrder( $withoutPhone );

        Queue::assertNotPushed( SendWhatsAppReceiptJob::class );
    }

    public function test_the_job_sends_through_the_cloud_api_on_payment(): void
    {
        $this->configureCloudApi( autoSend: true );
        $customer = $this->createCustomer( '+971 50 999 8877' );

        Http::fake( [
            'graph.facebook.com/*' => Http::response( [ 'messages' => [ [ 'id' => 'wamid.AUTO123' ] ] ], 200 ),
        ] );

        /**
         * The module test configuration uses the "sync" queue:
         * the job runs right after the order transaction commits.
         */
        $order = $this->createPaidOrder( $customer );

        Http::assertSentCount( 1 );
        Http::assertSent( fn( $request ) => $request[ 'to' ] === '971509998877' );

        $this->assertDatabaseHas( ( new WhatsAppMessage )->getTable(), [
            'order_id' => $order->id,
            'phone' => '971509998877',
            'mode' => 'cloud_api',
            'trigger' => 'auto',
            'status' => 'sent',
            'provider_message_id' => 'wamid.AUTO123',
            'user_id' => null,
        ] );

        /**
         * Running the job again does not message the customer twice.
         */
        SendWhatsAppReceiptJob::dispatchSync( $order );
        Http::assertSentCount( 1 );
    }

    public function test_a_provider_failure_during_auto_send_is_logged_and_does_not_break_the_sale(): void
    {
        $this->configureCloudApi( autoSend: true );
        $customer = $this->createCustomer();

        Http::fake( [
            'graph.facebook.com/*' => Http::response( [ 'error' => [ 'message' => 'Invalid OAuth access token.', 'code' => 190 ] ], 401 ),
        ] );

        $order = $this->createPaidOrder( $customer );

        $this->assertSame( Order::PAYMENT_PAID, $order->payment_status );

        $log = WhatsAppMessage::where( 'order_id', $order->id )->firstOrFail();
        $this->assertSame( 'failed', $log->status );
        $this->assertSame( 'auto', $log->trigger );
        $this->assertStringContainsString( 'Invalid OAuth access token', $log->error );
    }
}
