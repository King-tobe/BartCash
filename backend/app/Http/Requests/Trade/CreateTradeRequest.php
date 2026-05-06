<?php

namespace App\Http\Requests\Trade;

use Illuminate\Foundation\Http\FormRequest;

class CreateTradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receiver_id'       => ['required', 'uuid', 'exists:users,id'],
            'receiver_item_id'  => ['required', 'uuid', 'exists:items,id'],
            'offered_item_ids'  => ['required', 'array', 'min:1'],
            'offered_item_ids.*'=> ['required', 'uuid', 'exists:items,id'],
            'message'           => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'receiver_id.required'        => 'Please specify who you are trading with.',
            'receiver_id.exists'          => 'The specified user does not exist.',
            'receiver_item_id.required'   => 'Please specify the item you want.',
            'receiver_item_id.exists'     => 'The requested item does not exist.',
            'offered_item_ids.required'   => 'Please select at least one item to offer.',
            'offered_item_ids.min'        => 'Please select at least one item to offer.',
            'offered_item_ids.*.uuid'     => 'One or more offered item IDs are invalid.',
            'offered_item_ids.*.exists'   => 'One or more offered items do not exist.',
            'message.max'                 => 'Message may not exceed 500 characters.',
        ];
    }
}