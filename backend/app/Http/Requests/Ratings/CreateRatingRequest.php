<?php

namespace App\Http\Requests\Rating;

use Illuminate\Foundation\Http\FormRequest;

class CreateRatingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'trade_id' => ['required', 'uuid', 'exists:trades,id'],
            'score'    => ['required', 'integer', 'min:1', 'max:5'],
            'review'   => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'trade_id.required' => 'Please provide a trade ID.',
            'trade_id.exists'   => 'The specified trade does not exist.',
            'score.required'    => 'Please provide a rating score.',
            'score.min'         => 'Score must be at least 1.',
            'score.max'         => 'Score may not exceed 5.',
            'review.max'        => 'Review may not exceed 500 characters.',
        ];
    }
}