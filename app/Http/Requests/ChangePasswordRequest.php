<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'new_password'     => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Введите текущий пароль',
            'new_password.required'     => 'Введите новый пароль',
            'new_password.min'          => 'Пароль должен быть минимум 8 символов',
            'new_password.confirmed'    => 'Пароли не совпадают',
        ];
    }
}