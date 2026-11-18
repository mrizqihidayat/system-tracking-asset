<?php

namespace App\Http\Requests;

use App\Services\AuthService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'max:72'],
            'password' => [...app(AuthService::class)->newPasswordRules($this->user()), 'different:current_password'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['password.different' => 'Password baru tidak boleh sama dengan password saat ini.'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['current_password' => 'password saat ini', 'password' => 'password baru'];
    }
}
