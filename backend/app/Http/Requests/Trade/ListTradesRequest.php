<?php

namespace App\Http\Requests\Trade;

use Illuminate\Foundation\Http\FormRequest;

class ListTradesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'in:pending,accepted,completed,disputed,cancelled,declined'],
            'cursor' => ['nullable', 'string'],
            'limit'  => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in'    => 'Status must be one of: pending, accepted, completed, disputed, cancelled, declined.',
            'limit.max'    => 'Limit may not exceed 50 items per page.',
        ];
    }
}