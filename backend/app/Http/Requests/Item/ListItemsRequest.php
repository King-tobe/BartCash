<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class ListItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'      => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'condition'   => ['nullable', 'in:new,good,fair,poor'],
            'value_min'   => ['nullable', 'numeric', 'min:0'],
            'value_max'   => ['nullable', 'numeric', 'min:0', 'gte:value_min'],
            'is_service'  => ['nullable', 'boolean'],
            'cursor'      => ['nullable', 'string'],
            'limit'       => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.exists' => 'The selected category is invalid.',
            'condition.in'       => 'Condition must be one of: new, good, fair, poor.',
            'value_max.gte'      => 'Maximum value must be greater than or equal to minimum value.',
            'limit.max'          => 'Limit may not exceed 50 items per page.',
        ];
    }
}