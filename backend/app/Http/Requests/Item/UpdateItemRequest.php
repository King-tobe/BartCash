<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class UpdateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'        => ['sometimes', 'string', 'max:255'],
            'description'  => ['sometimes', 'string', 'min:20'],
            'category_id'  => ['sometimes', 'uuid', 'exists:categories,id'],
            'condition'    => ['sometimes', 'in:new,good,fair,poor'],
            'desired_trade'=> ['nullable', 'string', 'max:500'],
            'location'     => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.max'          => 'Title may not exceed 255 characters.',
            'description.min'    => 'Description must be at least 20 characters.',
            'category_id.exists' => 'The selected category is invalid.',
            'condition.in'       => 'Condition must be one of: new, good, fair, poor.',
        ];
    }
}