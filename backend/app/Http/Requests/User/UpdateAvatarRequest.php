<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'avatar' => ['required', 'file', 'mimes:jpeg,jpg,png', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.required' => 'Please provide an image file.',
            'avatar.file'     => 'The upload must be a valid file.',
            'avatar.mimes'    => 'Avatar must be a JPEG or PNG image.',
            'avatar.max'      => 'Avatar may not exceed 5MB.',
        ];
    }
}