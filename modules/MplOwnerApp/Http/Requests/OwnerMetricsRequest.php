<?php

namespace Modules\MplOwnerApp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\MplOwnerApp\Services\OwnerMetricsService;

class OwnerMetricsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'period' => [ 'sometimes', 'nullable', 'string', Rule::in( OwnerMetricsService::PERIODS ) ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period.in' => __m( 'The period must be one of: today, yesterday, 7d, 30d, month.', 'MplOwnerApp' ),
        ];
    }

    public function period(): string
    {
        return $this->validated( 'period' ) ?: 'today';
    }
}
