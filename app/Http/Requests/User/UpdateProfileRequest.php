<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name'  => ['sometimes', 'string', 'max:100'],
            'bio'        => ['nullable', 'string', 'max:200'],
            'location'   => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.max' => 'First name may not exceed 100 characters.',
            'last_name.max'  => 'Last name may not exceed 100 characters.',
            'bio.max'        => 'Bio may not exceed 200 characters.',
            'location.max'   => 'Location may not exceed 100 characters.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $data = $this->only(['first_name', 'last_name', 'bio', 'location']);
            $filled = array_filter($data, fn($value) => !is_null($value));

            if (empty($filled)) {
                $validator->errors()->add('general', 'At least one field must be provided.');
            }
        });
    }
}