<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class CreateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'        => ['required', 'string', 'max:255'],
            'description'  => ['required', 'string', 'min:20'],
            'category_id'  => ['required', 'uuid', 'exists:categories,id'],
            'condition'    => ['required', 'in:new,good,fair,poor'],
            'desired_trade'=> ['nullable', 'string', 'max:500'],
            'is_service'   => ['nullable', 'boolean'],
            'location'     => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required'       => 'Please provide a title for your listing.',
            'title.max'            => 'Title may not exceed 255 characters.',
            'description.required' => 'Please provide a description.',
            'description.min'      => 'Description must be at least 20 characters.',
            'category_id.required' => 'Please select a category.',
            'category_id.exists'   => 'The selected category is invalid.',
            'condition.required'   => 'Please select the item condition.',
            'condition.in'         => 'Condition must be one of: new, good, fair, poor.',
        ];
    }
}