<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
        $userId = $this->user()->id;

        return [
            'username' => [
                'sometimes',
                'string',
                'min:3',
                'max:32',
                Rule::unique('users', 'username')->ignore($userId),
            ],

            'email' => [
                'sometimes',
                'email',
                Rule::unique('users', 'email')->ignore($userId),
            ],

            'phone' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('users', 'phone')->ignore($userId),
            ],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.string' => 'Логин должен быть строкой',
            'username.min' => 'Логин слишком короткий',
            'username.max' => 'Логин слишком длинный',
            'username.unique' => 'Этот логин уже занят',

            'email.email' => 'Некорректный email',
            'email.unique' => 'Этот email уже используется',

            'first_name.string' => 'Имя должно быть строкой',
            'last_name.string' => 'Фамилия должна быть строкой',
            'phone.string' => 'Телефон должен быть строкой',
            'phone.unique' => 'Этот телефон уже используется',
        ];
    }
}
