<?php

namespace Modules\MplEInvoice\Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\MenuService;
use Illuminate\Database\Eloquent\Builder;
use Modules\MplEInvoice\Tests\EInvoiceTestCase;

class DocumentOutputTest extends EInvoiceTestCase
{
    public function test_tax_invoice_can_be_printed_and_downloaded(): void
    {
        $this->attemptAuthenticate();

        $vat = $this->taxGroup( 5, 'MplEInvoice VAT 5%' );
        $order = $this->placePaidOrder(
            customer: $this->createCustomer( '100444555600003' ),
            lines: [ $this->line( 'Desk lamp', 2, 31.5, $vat, 5 ) ],
            taxValue: 3
        );
        $document = $this->documentForOrder( $order );

        $admin = User::whereHas( 'roles', fn( Builder $query ) => $query->where( 'namespace', Role::ADMIN ) )->first();
        $this->actingAs( $admin );

        $print = $this->get( 'dashboard/mpl-einvoice/documents/' . $document->id . '/print' );
        $print->assertOk()
            ->assertSee( 'Tax Invoice' )
            ->assertSee( 'فاتورة ضريبية' )
            ->assertSee( 'dir="rtl"', false )
            ->assertSee( $document->number )
            ->assertSee( '100123456700003' )
            ->assertSee( '100444555600003' )
            ->assertSee( 'Gulf Buyer Trading LLC' )
            ->assertSee( '63.00' )
            ->assertSee( '3.00' );

        $download = $this->get( 'dashboard/mpl-einvoice/documents/' . $document->id . '/xml' );
        $download->assertOk();
        $this->assertStringContainsString( 'attachment; filename="' . $document->number . '.xml"', $download->headers->get( 'Content-Disposition' ) );
        $this->assertSame( $document->xml, $download->getContent() );
    }

    public function test_simplified_invoice_title_is_bilingual(): void
    {
        $this->attemptAuthenticate();

        $vat = $this->taxGroup( 5, 'MplEInvoice VAT 5%' );
        $document = $this->documentForOrder( $this->placePaidOrder(
            customer: $this->createCustomer(),
            lines: [ $this->line( 'Water', 1, 2.1, $vat, 5 ) ]
        ) );

        $admin = User::whereHas( 'roles', fn( Builder $query ) => $query->where( 'namespace', Role::ADMIN ) )->first();

        $this->actingAs( $admin )
            ->get( 'dashboard/mpl-einvoice/documents/' . $document->id . '/print' )
            ->assertOk()
            ->assertSee( 'Simplified Tax Invoice' )
            ->assertSee( 'فاتورة ضريبية مبسطة' );
    }

    public function test_dashboard_pages_render_for_admin(): void
    {
        $admin = User::whereHas( 'roles', fn( Builder $query ) => $query->where( 'namespace', Role::ADMIN ) )->first();
        $this->actingAs( $admin );

        $this->get( 'dashboard/mpl-einvoice/documents' )
            ->assertOk()
            ->assertSee( 'mpleinvoice.documents', false );

        $this->get( 'dashboard/mpl-einvoice/settings' )
            ->assertOk()
            ->assertSee( 'api/mpl-einvoice/settings', false );

        $menus = app()->make( MenuService::class )->getMenus();
        $this->assertTrue( $menus->has( 'mpl-einvoice' ) );
        $this->assertArrayHasKey( 'mpl-einvoice-documents', $menus->get( 'mpl-einvoice' )[ 'childrens' ] );
    }
}
