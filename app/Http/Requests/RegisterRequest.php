<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // регистрироваться можно без авторизации
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('email')) {
            $this->merge([
                'email' => mb_strtolower(trim((string) $this->input('email'))),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:6', 'max:255', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.unique' => 'Такой username уже занят',
            'email.unique' => 'Такая почта уже зарегистрирована',
            'phone.unique' => 'Такой телефон уже зарегистрирован',
            'phone.required' => 'Укажите телефон',
            'password.min' => 'Пароль должен быть не короче 6 символов',
            'password.confirmed' => 'Пароли не совпадают',
        ];
    }
}
