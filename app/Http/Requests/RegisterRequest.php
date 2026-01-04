<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // регистрироваться можно без авторизации
    }

    public function rules(): array
    {
        return [
            'username'   => ['required', 'string', 'min:3', 'max:50', 'alpha_dash', 'unique:users,username'],
            'email'      => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone'      => ['nullable', 'string', 'max:20', 'unique:users,phone'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name'  => ['nullable', 'string', 'max:100'],
            'password'   => ['required', 'string', 'min:6', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.unique' => 'Такой username уже занят',
            'email.unique'    => 'Такая почта уже зарегистрирована',
            'phone.unique'    => 'Такой телефон уже зарегистрирован',
        ];
    }
}