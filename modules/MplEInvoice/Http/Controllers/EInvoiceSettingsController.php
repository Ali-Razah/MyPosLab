<?php

namespace Modules\MplEInvoice\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\MplEInvoice\Settings\EInvoiceSettingsPage;

class EInvoiceSettingsController extends Controller
{
    public function show()
    {
        return EInvoiceSettingsPage::renderForm();
    }

    public function getForm(): array
    {
        return ( new EInvoiceSettingsPage )->getForm();
    }

    public function save( Request $request ): array
    {
        $page = new EInvoiceSettingsPage;

        $request->validate( $page->validateForm( $request ) );

        return $page->saveForm( $request );
    }
}
