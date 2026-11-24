<?php

namespace Tests\Feature;

use App\Classes\Output;
use App\Events\RenderFooterEvent;
use App\Listeners\RenderFooterEventListener;
use App\Models\Role;
use App\Services\AdService;
use App\Services\UserOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TelemetryOptInTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs( Role::namespace( 'admin' )->users->first(), [ '*' ] );
        ns()->option->delete( 'ns_telemetry_last_sent' );
    }

    public function test_telemetry_is_disabled_by_default(): void
    {
        $this->assertFalse( config( 'nexopos.telemetry' ) );
    }

    public function test_endpoint_sends_nothing_when_disabled(): void
    {
        config( [ 'nexopos.telemetry' => false ] );
        Http::fake();

        $this->postJson( 'api/system/telemetry' )
            ->assertOk()
            ->assertJson( [ 'status' => 'skipped' ] );

        Http::assertNothingSent();
    }

    public function test_endpoint_reports_when_enabled(): void
    {
        config( [ 'nexopos.telemetry' => true ] );
        Http::fake( [ 'my.nexopos.com/*' => Http::response( [], 200 ) ] );

        $this->postJson( 'api/system/telemetry' )
            ->assertOk()
            ->assertJson( [ 'status' => 'success' ] );

        Http::assertSent( fn ( $request ) => str_contains( $request->url(), 'my.nexopos.com/api/nexoplatform/telemetry' ) );
    }

    public function test_upstream_ads_hidden_by_default(): void
    {
        $this->assertFalse( config( 'nexopos.upstream_ads' ) );

        $user = Role::namespace( 'admin' )->users->first();

        $this->assertNull( app( AdService::class )->getAdToDisplay( $user, 'ns.dashboard.home' ) );
    }

    public function test_upstream_ads_shown_when_enabled(): void
    {
        config( [ 'nexopos.upstream_ads' => true ] );
        Cache::put( 'nexoplatform_ads', [ [ 'title' => 'Test', 'message' => 'Hello', 'routes' => [], 'strict' => false ] ], 60 );

        $user = Role::namespace( 'admin' )->users->first();
        ( new UserOptions( $user->id ) )->delete( 'snooze_ads_24h' );

        $this->assertSame( 'Test', app( AdService::class )->getAdToDisplay( $user, 'ns.dashboard.home' )[ 'title' ] ?? null );
    }

    public function test_footer_script_only_injected_when_enabled(): void
    {
        $listener = new RenderFooterEventListener;

        config( [ 'nexopos.telemetry' => false ] );
        $event = new RenderFooterEvent( new Output, 'ns.dashboard.home' );
        $listener->handle( $event );
        $this->assertStringNotContainsString( 'system/telemetry', (string) $event->output );

        config( [ 'nexopos.telemetry' => true ] );
        $event = new RenderFooterEvent( new Output, 'ns.dashboard.home' );
        $listener->handle( $event );
        $this->assertStringContainsString( 'system/telemetry', (string) $event->output );
    }
}
