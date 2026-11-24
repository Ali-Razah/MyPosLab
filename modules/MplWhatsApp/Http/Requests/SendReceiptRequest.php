<?php

namespace Modules\MplWhatsApp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendReceiptRequest extends FormRequest
{
    /**
     * Authorization is enforced by the route permission middleware
     * and the order access check in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => [ 'nullable', 'string', 'max:40' ],
        ];
    }
}
