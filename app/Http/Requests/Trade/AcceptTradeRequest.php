<?php

namespace App\Http\Requests\Trade;

use Illuminate\Foundation\Http\FormRequest;

class AcceptTradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'completion_method' => ['required', 'in:meetup,delivery'],
        ];
    }

    public function messages(): array
    {
        return [
            'completion_method.required' => 'Please select a completion method.',
            'completion_method.in'       => 'Completion method must be either meetup or delivery.',
        ];
    }
}