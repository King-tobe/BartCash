<?php

namespace App\Http\Requests\Dispute;

use Illuminate\Foundation\Http\FormRequest;

class CreateDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'trade_id' => ['required', 'uuid', 'exists:trades,id'],
            'reason'   => ['required', 'string', 'min:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'trade_id.required' => 'Please provide a trade ID.',
            'trade_id.exists'   => 'The specified trade does not exist.',
            'reason.required'   => 'Please describe the issue.',
            'reason.min'        => 'Reason must be at least 50 characters.',
        ];
    }
}