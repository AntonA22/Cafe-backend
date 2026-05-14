<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CartAddRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'dessert_id' => ['required', 'integer', 'exists:desserts,id'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('qty')) {
            $this->merge(['qty' => 1]);
        }
    }
}
