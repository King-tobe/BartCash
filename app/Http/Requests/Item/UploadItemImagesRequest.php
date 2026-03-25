<?php

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class UploadItemImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'images'   => ['required', 'array', 'min:1', 'max:6'],
            'images.*' => ['required', 'file', 'mimes:jpeg,jpg,png', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'images.required'  => 'Please upload at least one image.',
            'images.array'     => 'Images must be submitted as an array.',
            'images.min'       => 'Please upload at least one image.',
            'images.max'       => 'You may upload a maximum of 6 images at once.',
            'images.*.required'=> 'Each image file is required.',
            'images.*.file'    => 'Each upload must be a valid file.',
            'images.*.mimes'   => 'Images must be JPEG or PNG format.',
            'images.*.max'     => 'Each image may not exceed 5MB.',
        ];
    }
}