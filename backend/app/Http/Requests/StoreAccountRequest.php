<?php

namespace App\Http\Requests;

use App\Rules\UniqueCaseInsensitive;
use App\Services\AuthService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Akun bersama (source manual): username bukan format NIK, departemen dari data master.
 */
class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('username'))) {
            $this->merge(['username' => mb_strtolower(trim($this->input('username')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $username = $this->input('username');

        return [
            // Huruf kecil, angka, titik, garis bawah, atau strip; minimal satu huruf agar tidak dianggap NIK saat login.
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^(?=.*[a-z])[a-z0-9._-]+$/', new UniqueCaseInsensitive('users', 'username')],
            'name' => ['required', 'string', 'max:100'],
            'department' => ['required', 'string', Rule::exists('departments', 'name')],
            'password' => app(AuthService::class)->newPasswordRules(null, is_string($username) ? $username : null),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => 'Username hanya huruf kecil, angka, titik, garis bawah, atau strip, dan minimal satu huruf.',
            'department.exists' => 'Departemen tidak ada di data master.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nama tampilan', 'department' => 'departemen'];
    }
}
